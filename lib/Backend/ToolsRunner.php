<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\PdfOut\Backend;

use FriendsOfRedaxo\PdfOut\Certificate;
use FriendsOfRedaxo\PdfOut\PdfDocument;
use FriendsOfRedaxo\PdfOut\Permission;
use FriendsOfRedaxo\PdfOut\SignatureField;
use InvalidArgumentException;
use RuntimeException;
use rex;
use rex_addon;
use rex_api_exception;
use rex_dir;
use rex_file;
use rex_formatter;
use rex_media;
use rex_media_service;
use rex_path;
use rex_sql;

/**
 * Verarbeitung der Seite „Werkzeuge“: Quellen einlesen, Schritte anwenden, speichern.
 *
 * @internal
 */
final class ToolsRunner
{
    /** maximale Größe eines hochgeladenen PDFs */
    public const MAX_UPLOAD = 50 * 1024 * 1024;

    /** @return array<string, string> erlaubte Rechte mit deutscher Beschriftung (Wert => Text) */
    public static function permissionLabels(): array
    {
        return [
            Permission::Print->value => 'Drucken (geringe Qualität)',
            Permission::PrintHigh->value => 'Drucken in voller Qualität',
            Permission::Copy->value => 'Text und Bilder kopieren',
            Permission::Modify->value => 'Inhalt ändern',
            Permission::Annotate->value => 'Kommentare und Formularfelder bearbeiten',
            Permission::FillForms->value => 'Formularfelder ausfüllen',
            Permission::Assemble->value => 'Seiten einfügen, drehen, löschen',
            Permission::Extract->value => 'Inhalte für Screenreader auslesen (immer erlaubt)',
        ];
    }

    /** @return list<array{filename: string, title: string}> PDFs im Medienpool */
    public static function mediaPdfs(): array
    {
        $rows = rex_sql::factory()->getArray(
            'SELECT filename, title FROM ' . rex::getTable('media') . ' WHERE filetype = ? ORDER BY title, filename',
            ['application/pdf'],
        );
        $list = [];
        foreach ($rows as $row) {
            $list[] = ['filename' => (string) $row['filename'], 'title' => (string) $row['title']];
        }
        return $list;
    }

    /** @return list<string> Zertifikatsdateien im Addon-Ordner data/certificates */
    public static function certificates(): array
    {
        $dir = rex_addon::get('pdfout')->getDataPath('certificates/');
        $files = [];
        foreach (glob($dir . '*') ?: [] as $path) {
            if (is_file($path) && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['p12', 'pfx', 'pem'], true)) {
                $files[] = basename($path);
            }
        }
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);
        return $files;
    }

    /**
     * Hochgeladene PDFs prüfen und einlesen (Feld mit `multiple`, also $_FILES['feld']['name'][…])
     *
     * @param array<mixed> $files Eintrag aus $_FILES
     * @param bool $requireUpload nur echte Uploads akzeptieren (is_uploaded_file); false nur für Tests
     * @return list<array{name: string, data: string}>
     * @throws InvalidArgumentException bei ungültigen Dateien
     */
    public static function readUploads(array $files, bool $requireUpload = true): array
    {
        $names = (array) ($files['name'] ?? []);
        $tmpNames = (array) ($files['tmp_name'] ?? []);
        $errors = (array) ($files['error'] ?? []);
        $sizes = (array) ($files['size'] ?? []);

        $uploads = [];
        foreach ($names as $i => $name) {
            $name = basename(is_scalar($name) ? (string) $name : '');
            $error = (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE);
            if (UPLOAD_ERR_NO_FILE === $error || ('' === $name && UPLOAD_ERR_OK !== $error)) {
                continue;
            }
            if (UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error) {
                throw new InvalidArgumentException(sprintf('„%s“ ist zu groß für den Server-Upload (upload_max_filesize).', $name));
            }
            if (UPLOAD_ERR_OK !== $error) {
                throw new InvalidArgumentException(sprintf('„%s“ wurde nicht vollständig hochgeladen (Fehler %d).', $name, $error));
            }
            $tmp = (string) ($tmpNames[$i] ?? '');
            if ('' === $tmp || !is_file($tmp) || ($requireUpload && !is_uploaded_file($tmp))) {
                throw new InvalidArgumentException(sprintf('„%s“: Upload nicht gefunden.', $name));
            }
            $size = (int) ($sizes[$i] ?? filesize($tmp));
            if ($size > self::MAX_UPLOAD || (int) filesize($tmp) > self::MAX_UPLOAD) {
                throw new InvalidArgumentException(sprintf('„%s“ ist zu groß (höchstens %s).', $name, rex_formatter::bytes(self::MAX_UPLOAD)));
            }
            if ('application/pdf' !== rex_file::mimeType($tmp, $name)) {
                throw new InvalidArgumentException(sprintf('„%s“ ist kein PDF.', $name));
            }
            $uploads[] = ['name' => '' !== $name ? $name : 'upload.pdf', 'data' => (string) file_get_contents($tmp)];
        }
        return $uploads;
    }

    /**
     * PdfDocument aus den Quellen bauen und die gewählten Schritte anwenden
     *
     * Reihenfolge der Quellen: erst die Medienpool-PDFs (in Auswahlreihenfolge), dann die Uploads.
     *
     * @param list<array{name: string, data: string}> $uploads
     * @throws InvalidArgumentException bei ungültigen Angaben
     */
    public static function build(ToolsOptions $options, array $uploads = []): PdfDocument
    {
        $sources = [];
        foreach ($options->media as $filename) {
            $media = rex_media::get($filename);
            if (null === $media || $filename !== basename($filename)) {
                throw new InvalidArgumentException(sprintf('„%s“ gibt es im Medienpool nicht.', $filename));
            }
            if ('pdf' !== strtolower($media->getExtension())) {
                throw new InvalidArgumentException(sprintf('„%s“ ist kein PDF.', $filename));
            }
            $sources[] = PdfDocument::fromMedia($filename);
        }
        foreach ($uploads as $upload) {
            $sources[] = PdfDocument::fromString($upload['data'], $upload['name']);
        }
        if ([] === $sources) {
            throw new InvalidArgumentException('Mindestens ein PDF wählen oder hochladen.');
        }

        $doc = array_shift($sources);
        if ([] !== $sources) {
            $doc->append(...$sources);
        }

        if ('' !== $options->pages) {
            // früh prüfen, damit Fehler in der Seitenauswahl verständlich gemeldet werden
            if (!preg_match('/^[\d\s,\-]+$/', $options->pages)) {
                throw new InvalidArgumentException('Seitenauswahl: nur Zahlen, Kommas und Bindestriche verwenden (z. B. „1-3,5,-1“).');
            }
            $doc->pages($options->pages);
        }
        if ('' !== $options->stampText) {
            $doc->stamp($options->stampText, opacity: $options->stampOpacity, pages: $options->stampPages);
        }
        if ($options->numbers) {
            $doc->pageNumbers($options->numbersFormat, $options->numbersPosition, skipFirst: $options->numbersSkipFirst);
        }
        if ($options->hasMetadata()) {
            $doc->metadata(
                title: '' !== $options->metaTitle ? $options->metaTitle : null,
                author: '' !== $options->metaAuthor ? $options->metaAuthor : null,
                subject: '' !== $options->metaSubject ? $options->metaSubject : null,
            );
        }
        if ($options->sign) {
            if (!in_array($options->signCertificate, self::certificates(), true)) {
                throw new InvalidArgumentException('Signatur: Zertifikat wählen (Dateien im Ordner data/certificates, siehe „Zertifikate“).');
            }
            $field = null;
            if ($options->signVisible) {
                $field = 'bottom-left' === $options->signPosition ? SignatureField::bottomLeft() : SignatureField::bottomRight();
            }
            $doc->sign(
                Certificate::fromAddon($options->signCertificate, $options->signPassword),
                name: $options->signName,
                reason: $options->signReason,
                location: $options->signLocation,
                field: $field,
            );
        }
        if ($options->protect) {
            if ('' === $options->userPassword && '' === $options->ownerPassword) {
                throw new InvalidArgumentException('Passwortschutz: mindestens ein Passwort angeben (zum Öffnen oder für alle Rechte).');
            }
            $doc->protect($options->userPassword, '' !== $options->ownerPassword ? $options->ownerPassword : null, $options->allow);
        }

        return $doc->filename(self::outputFilename($options));
    }

    /** Dateiname der Ausgabe: Eingabe oder Name der ersten Quelle, immer mit .pdf */
    public static function outputFilename(ToolsOptions $options): string
    {
        $name = $options->filename;
        if ('' === $name) {
            $name = $options->media[0] ?? 'dokument.pdf';
        }
        $name = trim(str_replace(['/', '\\', "\r", "\n", '"'], '', $name));
        if ('' === $name || '.pdf' === strtolower($name)) {
            $name = 'dokument.pdf';
        }
        return str_ends_with(strtolower($name), '.pdf') ? $name : $name . '.pdf';
    }

    /**
     * Im Medienpool speichern – vorhandene Dateien werden nie ersetzt, sondern bekommen einen neuen Namen (_1, _2 …).
     *
     * @return array{filename: string, renamed: bool}
     * @throws RuntimeException wenn der Medienpool die Datei ablehnt
     */
    public static function saveToMedia(PdfDocument $doc, string $filename, int $categoryId, string $title = ''): array
    {
        $tmp = rex_path::addonCache('pdfout', 'tools_' . bin2hex(random_bytes(8)) . '.pdf');
        rex_dir::create(dirname($tmp));
        try {
            $doc->save($tmp);
            $result = rex_media_service::addMedia([
                'title' => '' !== $title ? $title : pathinfo($filename, PATHINFO_FILENAME),
                'category_id' => $categoryId,
                'file' => ['name' => $filename, 'path' => $tmp],
            ], true);
        } catch (rex_api_exception $e) {
            throw new RuntimeException('Speichern im Medienpool nicht möglich: ' . html_entity_decode(strip_tags($e->getMessage())), 0, $e);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
        $saved = (string) $result['filename'];
        return ['filename' => $saved, 'renamed' => $saved !== $filename];
    }
}
