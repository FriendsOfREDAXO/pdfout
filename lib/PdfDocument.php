<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\PdfOut;

use Com\Tecnick\Pdf\Encrypt\Encrypt;
use Com\Tecnick\Pdf\Page\Unit;
use Com\Tecnick\Pdf\PdfConformance;
use Com\Tecnick\Pdf\Sign\Config as SignConfig;
use Com\Tecnick\Pdf\Tcpdf;
use Com\Tecnick\Pdf\TextHAlign;
use Com\Tecnick\Pdf\TextVAlign;
use InvalidArgumentException;
use RuntimeException;
use rex_addon;
use rex_dir;
use rex_file;
use rex_path;
use rex_response;

/**
 * Ein PDF bearbeiten und ausgeben: zusammenführen, Seiten auswählen, Stempel und Seitenzahlen,
 * Metadaten, digitale Signatur, Passwortschutz – und prüfen (Poppler).
 *
 *     PdfDocument::fromMedia('preisliste.pdf')
 *         ->append(PdfDocument::fromMedia('agb.pdf'))
 *         ->stamp('ENTWURF')
 *         ->pageNumbers()
 *         ->sign(Certificate::fromAddon(), reason: 'Freigabe')
 *         ->protect('geheim', allow: [Permission::Print])
 *         ->save(rex_path::addonData('mein_addon', 'preisliste.pdf'));
 *
 * Die Arbeitsschritte werden gesammelt und beim Ausgeben in einem Durchgang angewendet.
 * Ohne Bearbeitung bleibt das PDF unverändert (inklusive Links und Formularfeldern) – beim
 * Bearbeiten werden die Seiten neu aufgebaut, dabei gehen Links, Formulare und Lesezeichen verloren.
 */
final class PdfDocument
{
    /** @var list<string> PDF-Daten der Quellen in Reihenfolge */
    private array $sources = [];

    /** @var list<int>|string|null Seitenauswahl (1-basiert, -1 = letzte) oder Ausdruck wie „1-3,5,-1“ */
    private array|string|null $selection = null;

    /** @var array{text: string, size: float, color: string, opacity: float, angle: float, pages: string}|null */
    private ?array $stamp = null;

    /** @var array{format: string, position: string, size: float, color: string, skipFirst: bool}|null */
    private ?array $pageNumbers = null;

    /** @var array<string, string> */
    private array $metadata = [];

    private ?Certificate $certificate = null;

    /** @var array{Name: string, Location: string, Reason: string, ContactInfo: string} */
    private array $signatureInfo = ['Name' => '', 'Location' => '', 'Reason' => '', 'ContactInfo' => ''];

    private ?SignatureField $signatureField = null;

    private ?string $userPassword = null;

    private ?string $ownerPassword = null;

    /** @var list<Permission> */
    private array $allowed = [];

    private string $filename = 'document.pdf';

    private ?string $rendered = null;

    /** Seitenzahl des zuletzt erzeugten Dokuments (ohne Poppler bekannt) */
    private ?int $renderedPages = null;

    private function __construct()
    {
    }

    // ------------------------------------------------------------------ Quellen

    public static function fromString(string $pdf, string $filename = 'document.pdf'): self
    {
        if (!str_starts_with(ltrim(substr($pdf, 0, 1024)), '%PDF-')) {
            throw new InvalidArgumentException('Die Daten sind kein PDF.');
        }
        $doc = new self();
        $doc->sources[] = $pdf;
        $doc->filename = self::cleanFilename($filename);
        return $doc;
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('PDF nicht gefunden: ' . $path);
        }
        return self::fromString((string) file_get_contents($path), basename($path));
    }

    /** Datei aus dem Medienpool */
    public static function fromMedia(string $filename): self
    {
        if ('' === $filename || $filename !== basename($filename)) {
            throw new InvalidArgumentException('Ungültiger Dateiname: ' . $filename);
        }
        return self::fromFile(rex_path::media($filename));
    }

    /** HTML mit dompdf (Einstellungen wie bei PdfOut) */
    public static function fromHtml(string $html, string $filename = 'document.pdf'): self
    {
        return self::fromString(PdfOut::create()->html($html)->toString(), $filename);
    }

    // ------------------------------------------------------------------ Bearbeiten

    /** weitere PDFs anhängen (PdfDocument, Dateipfad oder PDF-Daten) */
    public function append(self|string ...$documents): self
    {
        foreach ($documents as $document) {
            if ($document instanceof self) {
                $this->sources[] = $document->toString();
            } elseif (str_starts_with(ltrim(substr($document, 0, 1024)), '%PDF-')) {
                $this->sources[] = $document;
            } else {
                $this->sources[] = self::fromFile($document)->toString();
            }
        }
        return $this->changed();
    }

    /**
     * nur bestimmte Seiten behalten (in dieser Reihenfolge)
     *
     * @param list<int>|string $pages z. B. [1, 3] oder „1-3,5,-1“ (-1 = letzte Seite)
     */
    public function pages(array|string $pages): self
    {
        $this->selection = $pages;
        return $this->changed();
    }

    /**
     * Text als Stempel/Wasserzeichen über die Seiten legen
     *
     * @param string $pages „all“, „first“, „last“ oder Auswahl wie „1-3“
     */
    public function stamp(string $text, float $size = 60, string $color = '#b3261e', float $opacity = 0.15, float $angle = 45, string $pages = 'all'): self
    {
        $this->stamp = ['text' => $text, 'size' => $size, 'color' => $color, 'opacity' => max(0.0, min(1.0, $opacity)), 'angle' => $angle, 'pages' => $pages];
        return $this->changed();
    }

    /**
     * Seitenzahlen einfügen
     *
     * @param string $format Platzhalter {page} und {pages}
     * @param string $position bottom-center, bottom-right, bottom-left, top-center, top-right, top-left
     */
    public function pageNumbers(string $format = 'Seite {page} von {pages}', string $position = 'bottom-center', float $size = 9, string $color = '#444444', bool $skipFirst = false): self
    {
        $this->pageNumbers = ['format' => $format, 'position' => $position, 'size' => $size, 'color' => $color, 'skipFirst' => $skipFirst];
        return $this->changed();
    }

    public function metadata(?string $title = null, ?string $author = null, ?string $subject = null, ?string $keywords = null, ?string $creator = null): self
    {
        foreach (['title' => $title, 'author' => $author, 'subject' => $subject, 'keywords' => $keywords, 'creator' => $creator] as $key => $value) {
            if (null !== $value) {
                $this->metadata[$key] = $value;
            }
        }
        return $this->changed();
    }

    /** Dateiname für inline()/download() */
    public function filename(string $filename): self
    {
        $this->filename = self::cleanFilename($filename);
        return $this;
    }

    /** digital signieren (PAdES, SHA-256); sichtbar mit SignatureField, sonst unsichtbar */
    public function sign(Certificate $certificate, string $name = '', string $reason = '', string $location = '', string $contact = '', ?SignatureField $field = null): self
    {
        $this->certificate = $certificate;
        $this->signatureInfo = [
            'Name' => '' !== $name ? $name : $certificate->commonName(),
            'Location' => $location,
            'Reason' => $reason,
            'ContactInfo' => $contact,
        ];
        $this->signatureField = $field;
        return $this->changed();
    }

    /**
     * mit Passwort schützen (AES-256)
     *
     * @param string $userPassword zum Öffnen ('' = ohne Passwort öffnen, nur Rechte einschränken)
     * @param string|null $ownerPassword für alle Rechte (leer = zufällig)
     * @param iterable<Permission|string> $allow erlaubte Rechte, alles andere ist gesperrt
     */
    public function protect(string $userPassword = '', ?string $ownerPassword = null, iterable $allow = [Permission::Print, Permission::PrintHigh]): self
    {
        $this->userPassword = $userPassword;
        $this->ownerPassword = null !== $ownerPassword && '' !== $ownerPassword ? $ownerPassword : bin2hex(random_bytes(16));
        $this->allowed = Permission::list($allow);
        return $this->changed();
    }

    // ------------------------------------------------------------------ Ausgabe

    public function toString(): string
    {
        return $this->rendered ??= $this->render();
    }

    /**
     * speichern; gibt den Pfad zurück
     *
     * @param bool $overwrite vorhandene Datei ersetzen
     */
    public function save(string $path, bool $overwrite = true): string
    {
        if (!$overwrite && is_file($path)) {
            throw new RuntimeException('Datei existiert bereits: ' . $path);
        }
        rex_dir::create(dirname($path));
        if (!rex_file::put($path, $this->toString())) {
            throw new RuntimeException('PDF konnte nicht gespeichert werden: ' . $path);
        }
        return $path;
    }

    /** im Browser anzeigen */
    public function inline(?string $filename = null): never
    {
        $this->send($filename ?? $this->filename, false);
    }

    /** als Download senden */
    public function download(?string $filename = null): never
    {
        $this->send($filename ?? $this->filename, true);
    }

    // ------------------------------------------------------------------ Lesen

    /** Stehen info(), text() und signatures() zur Verfügung? (benötigen die poppler-utils) */
    public static function canInspect(): bool
    {
        return Poppler::isAvailable();
    }

    /** Seitenzahl – funktioniert auch ohne Poppler */
    public function pageCount(): int
    {
        if ($this->hasChanges()) {
            $this->toString();
            return (int) $this->renderedPages;
        }
        if (Poppler::isAvailable()) {
            return $this->withTempFile(static fn (string $file): int => Poppler::pageCount($file), true);
        }
        $pdf = new Tcpdf();
        return $pdf->getSourcePageCount($pdf->setImportSourceData($this->sources[0]));
    }

    /**
     * Metadaten (benötigt Poppler)
     *
     * @return array<string, string>
     * @throws PopplerUnavailableException
     */
    public function info(): array
    {
        self::requirePoppler('Das Auslesen der Metadaten');
        return $this->withTempFile(fn (string $file): array => Poppler::info($file, $this->userPassword ?? ''));
    }

    /**
     * Text (benötigt Poppler)
     *
     * @throws PopplerUnavailableException
     */
    public function text(bool $layout = false): string
    {
        self::requirePoppler('Das Auslesen des Textes');
        return $this->withTempFile(fn (string $file): string => Poppler::text($file, $this->userPassword ?? '', $layout));
    }

    /**
     * Signaturen prüfen
     *
     * @return list<array{field: string, signer: string, subject: string, signed_at: string, hash: string, type: string, valid: bool, status: string, certificate: string, certificate_trusted: bool, whole_document: bool}>
     */
    public function signatures(): array
    {
        self::requirePoppler('Die Signaturprüfung');
        return $this->withTempFile(fn (string $file): array => Poppler::signatures($file, $this->userPassword ?? ''));
    }

    // ------------------------------------------------------------------ intern

    private function changed(): self
    {
        $this->rendered = null;
        $this->renderedPages = null;
        return $this;
    }

    private static function requirePoppler(string $feature): void
    {
        if (!Poppler::isAvailable()) {
            throw PopplerUnavailableException::forFeature($feature);
        }
    }

    private function hasChanges(): bool
    {
        return count($this->sources) > 1 || null !== $this->selection || null !== $this->stamp || null !== $this->pageNumbers
            || [] !== $this->metadata || null !== $this->certificate || null !== $this->userPassword;
    }

    private function render(): string
    {
        if ([] === $this->sources) {
            throw new RuntimeException('Kein PDF vorhanden.');
        }
        if (!$this->hasChanges()) {
            return $this->sources[0];
        }
        self::defineFontPath();

        $encrypt = null;
        if (null !== $this->userPassword) {
            $encrypt = new Encrypt(
                enabled: true,
                file_id: md5(implode('', array_map('md5', $this->sources)) . microtime()),
                mode: 3, // AES-256
                permissions: Permission::blockedFor($this->allowed),
                user_pass: $this->userPassword,
                owner_pass: (string) $this->ownerPassword,
            );
        }
        $pdf = new Tcpdf(unit: Unit::Millimeter, isunicode: true, subsetfont: true, compress: true, mode: PdfConformance::None, objEncrypt: $encrypt);
        $pdf->setCreator($this->metadata['creator'] ?? 'REDAXO pdfout');
        foreach (['title' => 'setTitle', 'author' => 'setAuthor', 'subject' => 'setSubject', 'keywords' => 'setKeywords'] as $key => $method) {
            if (isset($this->metadata[$key])) {
                $pdf->{$method}($this->metadata[$key]);
            }
        }
        $pdf->setPDFFilename($this->filename);

        // alle Seiten der Quellen, dann Auswahl
        $all = [];
        foreach ($this->sources as $data) {
            $sourceId = $pdf->setImportSourceData($data);
            for ($n = 1, $count = $pdf->getSourcePageCount($sourceId); $n <= $count; ++$n) {
                $all[] = [$sourceId, $n];
            }
        }
        $pages = null === $this->selection ? $all : array_map(static fn (int $i): array => $all[$i - 1], self::resolvePages($this->selection, count($all)));
        if ([] === $pages) {
            throw new RuntimeException('Die Seitenauswahl ergibt keine Seiten.');
        }
        $total = count($pages);
        $this->renderedPages = $total;
        $stampPages = null !== $this->stamp ? self::stampPages($this->stamp['pages'], $total) : [];
        $signaturePage = null !== $this->signatureField ? ($this->signatureField->page < 0 ? $total + 1 + $this->signatureField->page : $this->signatureField->page) : 0;

        foreach ($pages as $index => [$sourceId, $pageNum]) {
            $number = $index + 1;
            $tpl = $pdf->addPageFromImport($sourceId, $pageNum);
            $width = $pdf->toUnit($tpl->getWidth());
            $height = $pdf->toUnit($tpl->getHeight());
            if (in_array($number, $stampPages, true)) {
                $this->drawStamp($pdf, $width, $height);
            }
            if (null !== $this->pageNumbers && !($this->pageNumbers['skipFirst'] && 1 === $number)) {
                $this->drawPageNumber($pdf, $number, $total, $width, $height);
            }
            if ($number === $signaturePage && null !== $this->signatureField && $this->signatureField->drawBox) {
                $this->drawSignatureBox($pdf, $this->signatureField);
            }
        }

        if (null !== $this->certificate) {
            $pdf->signature()->configure([
                'appearance' => ['empty' => [], 'name' => '', 'page' => 0, 'rect' => ''],
                'approval' => '',
                'cert_type' => 2, // Änderungen an Formularen und Kommentaren erlaubt
                'extracerts' => $this->certificate->chainBundle(),
                'info' => $this->signatureInfo,
                'password' => '',
                'privkey' => $this->certificate->privateKey,
                'signcert' => $this->certificate->certificate,
                'profile' => SignConfig::PROFILE_PADES_B_B,
                'digest_algorithm' => 'sha256',
            ]);
            if (null !== $this->signatureField) {
                $field = $this->signatureField;
                $pdf->signature()->appearance()->place(posx: $field->x, posy: $field->y, width: $field->width, height: $field->height, page: $signaturePage - 1, name: $field->name);
            }
        }
        return $pdf->getOutPDFString();
    }

    private function drawStamp(Tcpdf $pdf, float $width, float $height): void
    {
        $stamp = (array) $this->stamp;
        $font = $pdf->font->insert($pdf->pon, 'helvetica', 'B', (float) $stamp['size']);
        $out = $pdf->graph->getStartTransform()
            . $pdf->graph->getAlpha((float) $stamp['opacity'])
            . $pdf->graph->getRotation((float) $stamp['angle'], $width / 2, $height / 2)
            . $font['out']
            . $pdf->color->getPdfColor((string) $stamp['color'])
            . $pdf->getTextCell(txt: (string) $stamp['text'], posx: 0, posy: $height / 2 - (float) $stamp['size'] * 0.2, width: $width, height: 0, valign: TextVAlign::Center, halign: TextHAlign::Center)
            . $pdf->graph->getStopTransform();
        $pdf->page->addContent($out);
    }

    private function drawPageNumber(Tcpdf $pdf, int $number, int $total, float $width, float $height): void
    {
        $options = (array) $this->pageNumbers;
        $text = strtr((string) $options['format'], ['{page}' => (string) $number, '{pages}' => (string) $total]);
        [$vertical, $horizontal] = array_pad(explode('-', (string) $options['position']), 2, 'center');
        $margin = 10.0;
        $posy = 'top' === $vertical ? $margin : $height - $margin - 4;
        $halign = match ($horizontal) {
            'left' => TextHAlign::Left,
            'right' => TextHAlign::Right,
            default => TextHAlign::Center,
        };
        $font = $pdf->font->insert($pdf->pon, 'helvetica', '', (float) $options['size']);
        $pdf->page->addContent(
            $pdf->graph->getStartTransform() . $font['out'] . $pdf->color->getPdfColor((string) $options['color'])
            . $pdf->getTextCell(txt: $text, posx: $margin, posy: $posy, width: $width - 2 * $margin, height: 0, valign: TextVAlign::Top, halign: $halign)
            . $pdf->graph->getStopTransform(),
        );
    }

    private function drawSignatureBox(Tcpdf $pdf, SignatureField $field): void
    {
        $info = $this->signatureInfo;
        $lines = array_values(array_filter([
            'Digital signiert',
            $info['Name'],
            date('d.m.Y H:i'),
            '' !== $info['Location'] ? 'Ort: ' . $info['Location'] : '',
            '' !== $info['Reason'] ? 'Grund: ' . $info['Reason'] : '',
        ], static fn (string $line): bool => '' !== $line));

        $out = $pdf->graph->getStartTransform()
            . $pdf->graph->getRect($field->x, $field->y, $field->width, $field->height, 'DF', [
                'all' => ['lineWidth' => 0.3, 'lineColor' => '#5c5c5c', 'fillColor' => '#f5f5f2'],
            ]);
        $lineHeight = min(4.2, ($field->height - 3) / max(1, count($lines)));
        $size = max(5.0, min(9.0, $lineHeight * 2.1));
        foreach ($lines as $i => $line) {
            $font = $pdf->font->insert($pdf->pon, 'helvetica', 1 === $i ? 'B' : '', $size);
            $out .= $font['out'] . $pdf->color->getPdfColor('#222222')
                . $pdf->getTextCell(txt: $line, posx: $field->x + 2, posy: $field->y + 1.5 + $i * $lineHeight, width: $field->width - 4, height: 0, valign: TextVAlign::Top, halign: TextHAlign::Left);
        }
        $pdf->page->addContent($out . $pdf->graph->getStopTransform());
    }

    /**
     * Seitenauswahl in 1-basierte Seitennummern auflösen
     *
     * @param list<int>|string $selection
     * @return list<int>
     */
    public static function resolvePages(array|string $selection, int $total): array
    {
        $parts = is_array($selection) ? array_map('strval', $selection) : preg_split('/\s*,\s*/', trim($selection), -1, PREG_SPLIT_NO_EMPTY);
        $resolve = static fn (int $n): int => $n < 0 ? $total + 1 + $n : $n;
        $pages = [];
        foreach ($parts ?: [] as $part) {
            if (preg_match('/^(-?\d+)\s*-\s*(-?\d+)$/', $part, $m)) {
                $from = $resolve((int) $m[1]);
                $to = $resolve((int) $m[2]);
                foreach ($from <= $to ? range($from, $to) : range($from, $to, -1) as $n) {
                    $pages[] = $n;
                }
            } elseif (preg_match('/^-?\d+$/', $part)) {
                $pages[] = $resolve((int) $part);
            } else {
                throw new InvalidArgumentException('Ungültige Seitenauswahl: ' . $part);
            }
        }
        foreach ($pages as $n) {
            if ($n < 1 || $n > $total) {
                throw new InvalidArgumentException(sprintf('Seite %d gibt es nicht (das PDF hat %d Seiten).', $n, $total));
            }
        }
        return $pages;
    }

    /** @return list<int> */
    private static function stampPages(string $pages, int $total): array
    {
        return match ($pages) {
            'all' => range(1, $total),
            'first' => [1],
            'last' => [$total],
            default => self::resolvePages($pages, $total),
        };
    }

    private static function defineFontPath(): void
    {
        if (!defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', rex_addon::get('pdfout')->getPath('fonts/'));
        }
    }

    private static function cleanFilename(string $filename): string
    {
        $filename = trim(str_replace(['"', '\\', '/', "\r", "\n"], '', $filename));
        if ('' === $filename) {
            return 'document.pdf';
        }
        return str_ends_with(strtolower($filename), '.pdf') ? $filename : $filename . '.pdf';
    }

    private function send(string $filename, bool $download): never
    {
        $data = $this->toString();
        $filename = self::cleanFilename($filename);
        rex_response::cleanOutputBuffers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        echo $data;
        exit;
    }

    /**
     * @template T
     * @param callable(string): T $callback
     * @param bool $source true = Quelle verwenden, wenn nichts zu bearbeiten ist (schneller, unverschlüsselt)
     * @return T
     */
    private function withTempFile(callable $callback, bool $source = false): mixed
    {
        $file = rex_path::addonCache('pdfout', 'tmp_' . bin2hex(random_bytes(8)) . '.pdf');
        rex_dir::create(dirname($file));
        rex_file::put($file, $source && !$this->hasChanges() ? $this->sources[0] : $this->toString());
        try {
            return $callback($file);
        } finally {
            @unlink($file);
        }
    }
}
