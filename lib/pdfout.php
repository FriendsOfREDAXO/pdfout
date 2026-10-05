<?php
namespace FriendsOfRedaxo\PdfOut;

use Dompdf\Dompdf;
use Exception;
use rex;
use rex_addon;
use rex_addon_interface;
use rex_article;
use rex_article_content;
use rex_dir;
use rex_extension;
use rex_extension_point;
use rex_file;
use rex_logger;
use rex_media_manager;
use rex_path;
use rex_response;
use rex_string;
use rex_url;
use Throwable;

/**
 * PdfOut: PDF aus HTML oder REDAXO-Artikeln erzeugen (dompdf) – und direkt weiterverarbeiten
 * (signieren, schützen, anhängen, stempeln … über PdfDocument).
 *
 *     PdfOut::create()
 *         ->html($html)
 *         ->paper('A4')
 *         ->sign(Certificate::fromAddon(), reason: 'Rechnung')
 *         ->protect('geheim', allow: [Permission::Print])
 *         ->append(rex_path::media('agb.pdf'))
 *         ->download('rechnung.pdf');
 *
 * Die bisherigen Methoden (setHtml(), run(), enableDigitalSignature() …) funktionieren weiter.
 *
 * @phpstan-consistent-constructor
 */
class PdfOut extends Dompdf
{
    /** @var string Name der PDF-Datei */
    protected $name = 'pdf_file';

    /** @var string HTML-Inhalt des PDFs */
    protected $html = '';

    /** @var string Ausrichtung des PDFs (portrait/landscape) */
    protected $orientation = 'portrait';

    /** @var string Zu verwendende Schriftart */
    protected $font = 'Dejavu Sans';

    /** @var bool Ob das PDF als Anhang gesendet werden soll */
    protected $attachment = false;

    /** @var bool Ob entfernte Dateien (z.B. Bilder) erlaubt sind */
    protected $remoteFiles = true;

    /** @var string Pfad zum Speichern des PDFs */
    protected $saveToPath = '';

    /** @var int DPI-Einstellung für das PDF */
    protected $dpi = 100;

    /** @var bool Ob das PDF gespeichert und gesendet werden soll */
    protected $saveAndSend = true;

    /** @var string Optionales Grundtemplate für das PDF */
    protected $baseTemplate = '';

    /** @var string Platzhalter für den Inhalt im Grundtemplate */
    protected $contentPlaceholder = '{{CONTENT}}';

    /** @var string|array{0: float, 1: float, 2: float, 3: float} Papierformat für das PDF */
    protected $paperSize = 'A4';

    // ---- Weiterverarbeitung (PdfDocument)

    /** @var bool Ob das PDF signiert werden soll */
    protected $enableSigning = false;

    /** @var string Pfad zum Zertifikat (.p12/.pfx oder .pem) */
    protected $certificatePath = '';

    /** @var string Passwort für das Zertifikat */
    protected $certificatePassword = '';

    protected ?Certificate $certificate = null;

    /** @var array{enabled: bool, x: float|int, y: float|int, width: float|int, height: float|int, page: int, name: string, location: string, reason: string, contact_info: string} */
    protected $visibleSignature = [
        'enabled' => false,
        'x' => 180,
        'y' => 60,
        'width' => 15,
        'height' => 15,
        'page' => -1,  // -1 für die letzte Seite
        'name' => '',
        'location' => '',
        'reason' => '',
        'contact_info' => '',
    ];

    /** @var bool Ob das PDF passwortgeschützt werden soll */
    protected $enablePasswordProtection = false;

    /** @var string User-Passwort für das PDF */
    protected $userPassword = '';

    /** @var string Owner-Passwort für das PDF */
    protected $ownerPassword = '';

    /** @var list<Permission|string> erlaubte Rechte bei Passwortschutz */
    protected $permissions = ['print'];

    /** @var list<PdfDocument|string> anzuhängende PDFs */
    protected array $appendDocuments = [];

    /** @var list<callable(PdfDocument): PdfDocument> weitere Bearbeitungsschritte */
    protected array $documentSteps = [];

    /**
     * Konstruktor - lädt Standardkonfiguration
     */
    public function __construct()
    {
        parent::__construct();

        $addon = rex_addon::get('pdfout');

        $this->paperSize = $addon->getConfig('default_paper_size', 'A4');
        $this->orientation = $addon->getConfig('default_orientation', 'portrait');
        $this->font = $addon->getConfig('default_font', 'Dejavu Sans');
        $this->dpi = (int) $addon->getConfig('default_dpi', 100);
        $this->attachment = (bool) $addon->getConfig('default_attachment', false);
        $this->remoteFiles = (bool) $addon->getConfig('default_remote_files', true);

        // Signatur standardmäßig aktiv (Einstellungen)
        if ($addon->getConfig('enable_signature_by_default', false)) {
            $this->enableSigning = true;
            $selection = (string) $addon->getConfig('default_certificate_selection', '');
            $path = '' !== $selection && basename($selection) === $selection ? $addon->getDataPath('certificates/' . $selection) : '';
            $this->certificatePath = '' !== $path && is_file($path)
                ? $path
                : ((string) $addon->getConfig('default_certificate_path', '') ?: $addon->getDataPath('certificates/default.p12'));
            $this->certificatePassword = (string) $addon->getConfig('default_certificate_password', '');
            $this->visibleSignature['x'] = $addon->getConfig('default_signature_position_x', 180);
            $this->visibleSignature['y'] = $addon->getConfig('default_signature_position_y', 60);
            $this->visibleSignature['width'] = $addon->getConfig('default_signature_width', 15);
            $this->visibleSignature['height'] = $addon->getConfig('default_signature_height', 15);
        }

        // Passwortschutz standardmäßig aktiv (Einstellungen)
        if ($addon->getConfig('enable_password_protection_by_default', false)) {
            $this->enablePasswordProtection = true;
            $this->userPassword = (string) $addon->getConfig('default_user_password', '');
            $this->ownerPassword = (string) $addon->getConfig('default_owner_password', '');
            $this->permissions = (array) $addon->getConfig('default_pdf_permissions', ['print']);
        }
    }

    /** neue Instanz mit den Standard-Einstellungen des Addons */
    public static function create(): static
    {
        return new static();
    }

    /**
     * Ersetzt den Platzhalter für die Seitenzahl im PDF
     *
     * @param Dompdf $dompdf Das Dompdf-Objekt
     */
    private function injectPageCount(Dompdf $dompdf): void
    {
        /** @var \Dompdf\Adapter\CPDF $canvas */
        $canvas = $dompdf->getCanvas();
        $pdf = $canvas->get_cpdf();
        foreach ($pdf->objects as &$o) {
            if ($o['t'] === 'contents') {
                $o['c'] = str_replace('DOMPDF_PAGE_COUNT_PLACEHOLDER', (string) $canvas->get_page_count(), $o['c']);
            }
        }
    }

    /**
     * Setzt das Papierformat und die Ausrichtung für das PDF
     *
     * @param string|array{0: float, 1: float, 2: float, 3: float} $size Das Papierformat (z.B. 'A4', 'letter' oder [0, 0, Breite, Höhe] in Punkt)
     * @param string $orientation Die Ausrichtung (portrait/landscape)
     * @return self
     */
    public function setPaperSize(string|array $size = 'A4', string $orientation = 'portrait'): self
    {
        $this->paperSize = $size;
        $this->orientation = $orientation;
        return $this;
    }

    /**
     * Setzt den Namen der PDF-Datei
     *
     * @param string $name Der Name der PDF-Datei
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Setzt den HTML-Inhalt des PDFs
     *
     * @param string $html Der HTML-Inhalt
     * @param bool $outputfilter Optional: Ob der Outputfilter angewendet werden soll
     * @return self
     */
    public function setHtml(string $html, bool $outputfilter = false): self
    {
        if ($outputfilter) {
            $html = rex_extension::registerPoint(new rex_extension_point('OUTPUT_FILTER', $html));
        }
        $this->html = $html;
        return $this;
    }

    /**
     * Setzt die Ausrichtung des PDFs
     *
     * @param string $orientation Die Ausrichtung (portrait/landscape)
     * @return self
     */
    public function setOrientation(string $orientation): self
    {
        $this->orientation = $orientation;
        return $this;
    }

    /**
     * Setzt die zu verwendende Schriftart
     *
     * @param string $font Die Schriftart
     * @return self
     */
    public function setFont(string $font): self
    {
        $this->font = $font;
        return $this;
    }

    /**
     * Legt fest, ob das PDF als Anhang gesendet werden soll
     *
     * @param bool $attachment Ob als Anhang gesendet werden soll
     * @return self
     */
    public function setAttachment(bool $attachment): self
    {
        $this->attachment = $attachment;
        return $this;
    }

    /**
     * Legt fest, ob entfernte Dateien erlaubt sind
     *
     * @param bool $remoteFiles Ob entfernte Dateien erlaubt sind
     * @return self
     */
    public function setRemoteFiles(bool $remoteFiles): self
    {
        $this->remoteFiles = $remoteFiles;
        return $this;
    }

    /**
     * Setzt den Pfad zum Speichern des PDFs
     *
     * @param string $saveToPath Der Speicherpfad
     * @return self
     */
    public function setSaveToPath(string $saveToPath): self
    {
        $this->saveToPath = $saveToPath;
        return $this;
    }

    /**
     * Setzt die DPI-Einstellung für das PDF
     *
     * @param int $dpi Der DPI-Wert
     * @return self
     */
    public function setDpi(int $dpi): self
    {
        $this->dpi = $dpi;
        return $this;
    }

    /**
     * Legt fest, ob das PDF gespeichert und gesendet werden soll
     *
     * @param bool $saveAndSend Ob gespeichert und gesendet werden soll
     * @return self
     */
    public function setSaveAndSend(bool $saveAndSend): self
    {
        $this->saveAndSend = $saveAndSend;
        return $this;
    }

    /**
     * Setzt ein optionales Grundtemplate für das PDF
     *
     * @param string $template Das HTML-Template
     * @param string $placeholder Optional: Der Platzhalter für den Inhalt
     * @return self
     */
    public function setBaseTemplate(string $template, string $placeholder = '{{CONTENT}}'): self
    {
        $this->baseTemplate = $template;
        $this->contentPlaceholder = $placeholder;
        return $this;
    }

    /**
     * Fügt den Inhalt eines REDAXO-Artikels zum PDF hinzu
     *
     * @param int $articleId Die ID des Artikels
     * @param int|null $ctype Optional: Die ID des Inhaltstyps (ctype)
     * @param bool $applyOutputFilter Optional: Ob der OUTPUT_FILTER angewendet werden soll
     * @return self
     */
    public function addArticle(int $articleId, ?int $ctype = null, bool $applyOutputFilter = true): self
    {
        // Artikel ermitteln
        $article = rex_article::get($articleId);

        if ($article) {
            // Instanz von rex_article_content erstellen, um den Inhalt mit Clang zu laden
            $articleContent = new rex_article_content($article->getId(), $article->getClang());

            // Wenn ein ctype angegeben wurde, nur diesen ausgeben, sonst den gesamten Artikelinhalt
            $content = $ctype !== null ? $articleContent->getArticle($ctype) : $articleContent->getArticle();

            // OUTPUT_FILTER anwenden, wenn gewünscht
            if ($applyOutputFilter) {
                $content = rex_extension::registerPoint(new rex_extension_point('OUTPUT_FILTER', $content));
            }

            // Inhalt zur HTML-Ausgabe hinzufügen
            $this->html .= $content;
        }

        return $this;
    }


    // ------------------------------------------------------------------ Fluent-API

    /** HTML-Inhalt setzen (optional durch den OUTPUT_FILTER) */
    public function html(string $html, bool $outputFilter = false): static
    {
        $this->setHtml($html, $outputFilter);
        return $this;
    }

    /** Inhalt eines REDAXO-Artikels anhängen */
    public function article(int $articleId, ?int $ctype = null, bool $outputFilter = true): static
    {
        $this->addArticle($articleId, $ctype, $outputFilter);
        return $this;
    }

    /**
     * Papierformat
     *
     * @param string|array{0: float, 1: float, 2: float, 3: float} $size z. B. 'A4', 'letter' oder [0, 0, Breite, Höhe] in Punkt
     */
    public function paper(string|array $size = 'A4', string $orientation = 'portrait'): static
    {
        $this->setPaperSize($size, $orientation);
        return $this;
    }

    /** Grundschrift (dompdf) */
    public function font(string $font): static
    {
        $this->setFont($font);
        return $this;
    }

    /** Grundtemplate mit Platzhalter für den Inhalt */
    public function template(string $template, string $placeholder = '{{CONTENT}}'): static
    {
        $this->setBaseTemplate($template, $placeholder);
        return $this;
    }

    /** digital signieren (PAdES); sichtbar mit SignatureField */
    public function sign(?Certificate $certificate = null, string $name = '', string $reason = '', string $location = '', string $contact = '', ?SignatureField $field = null): static
    {
        $this->enableSigning = true;
        $this->certificate = $certificate ?? Certificate::fromAddon();
        $this->visibleSignature = array_merge($this->visibleSignature, [
            'enabled' => null !== $field,
            'name' => $name,
            'reason' => $reason,
            'location' => $location,
            'contact_info' => $contact,
        ]);
        if (null !== $field) {
            $this->visibleSignature = array_merge($this->visibleSignature, ['x' => $field->x, 'y' => $field->y, 'width' => $field->width, 'height' => $field->height, 'page' => $field->page]);
        }
        return $this;
    }

    /**
     * mit Passwort schützen (AES-256)
     *
     * @param iterable<Permission|string> $allow erlaubte Rechte
     */
    public function protect(string $userPassword = '', ?string $ownerPassword = null, iterable $allow = [Permission::Print, Permission::PrintHigh]): static
    {
        $this->enablePasswordProtection = true;
        $this->userPassword = $userPassword;
        $this->ownerPassword = (string) $ownerPassword;
        $this->permissions = Permission::list($allow);
        return $this;
    }

    /** PDFs anhängen (Dateipfad, PDF-Daten oder PdfDocument), z. B. AGB an eine Rechnung */
    public function append(PdfDocument|string ...$documents): static
    {
        array_push($this->appendDocuments, ...$documents);
        return $this;
    }

    /**
     * beliebige Bearbeitung des fertigen Dokuments, z. B. ->with(fn (PdfDocument $d) => $d->stamp('ENTWURF'))
     *
     * @param callable(PdfDocument): PdfDocument $step
     */
    public function with(callable $step): static
    {
        $this->documentSteps[] = $step;
        return $this;
    }

    /** fertiges Dokument zum Weiterbearbeiten */
    public function document(): PdfDocument
    {
        $doc = PdfDocument::fromString($this->renderHtml(), rex_string::normalize($this->name) . '.pdf');
        if ([] !== $this->appendDocuments) {
            $doc->append(...$this->appendDocuments);
        }
        foreach ($this->documentSteps as $step) {
            $doc = $step($doc);
        }
        if ($this->enableSigning) {
            $field = $this->visibleSignature['enabled']
                ? new SignatureField((float) $this->visibleSignature['x'], (float) $this->visibleSignature['y'], (float) $this->visibleSignature['width'], (float) $this->visibleSignature['height'], (int) $this->visibleSignature['page'])
                : null;
            $doc->sign($this->resolveCertificate(), $this->visibleSignature['name'], $this->visibleSignature['reason'], $this->visibleSignature['location'], $this->visibleSignature['contact_info'], $field);
        }
        if ($this->enablePasswordProtection) {
            $doc->protect($this->userPassword, $this->ownerPassword, $this->permissions);
        }
        return $doc;
    }

    /** PDF als String */
    public function toString(): string
    {
        return $this->document()->toString();
    }

    /** speichern; gibt den Pfad zurück */
    public function save(string $path, bool $overwrite = true): string
    {
        return $this->document()->save($path, $overwrite);
    }

    /** im Browser anzeigen */
    public function inline(?string $filename = null): never
    {
        $this->document()->inline($filename);
    }

    /** als Download senden */
    public function download(?string $filename = null): never
    {
        $this->document()->download($filename);
    }

    // ------------------------------------------------------------------ Erzeugung

    /**
     * HTML mit dompdf in ein PDF umwandeln (ohne Weiterverarbeitung)
     */
    protected function renderHtml(): string
    {
        $finalHtml = '' !== $this->baseTemplate ? str_replace($this->contentPlaceholder, $this->html, $this->baseTemplate) : $this->html;

        $dompdf = clone $this; // eigene Instanz: dompdf lässt sich nur einmal rendern
        $dompdf->loadHtml($finalHtml);
        $options = $dompdf->getOptions();
        $options->setChroot(rex_path::frontend());
        $options->setDefaultFont($this->font);
        $options->setDpi($this->dpi);
        $options->setFontCache(rex_path::addonCache('pdfout', 'fonts'));
        $options->setIsRemoteEnabled($this->remoteFiles);
        $dompdf->setOptions($options);
        $dompdf->setPaper($this->paperSize, $this->orientation);
        $dompdf->render();
        $this->injectPageCount($dompdf);

        return (string) $dompdf->output();
    }

    protected function resolveCertificate(): Certificate
    {
        if (null !== $this->certificate) {
            return $this->certificate;
        }
        $path = '' !== $this->certificatePath ? $this->certificatePath : rex_addon::get('pdfout')->getDataPath('certificates/default.p12');
        return $this->certificate = Certificate::fromFile($path, $this->certificatePassword);
    }

    protected function hasProcessing(): bool
    {
        return $this->enableSigning || $this->enablePasswordProtection || [] !== $this->appendDocuments || [] !== $this->documentSteps;
    }

    /**
     * Führt die PDF-Erstellung aus (bisherige API): speichert nach setSaveToPath() und/oder sendet das PDF
     */
    public function run(): void
    {
        $startTime = microtime(true);
        $addon = rex_addon::get('pdfout');
        $log = (bool) $addon->getConfig('log_pdf_generation', false);
        if ($log) {
            rex_logger::factory()->info('PDFOut: Starte PDF-Generierung für "' . $this->name . '"', ['paperSize' => $this->paperSize, 'orientation' => $this->orientation, 'dpi' => $this->dpi]);
        }

        try {
            $doc = $this->document();
            $filename = rex_string::normalize($this->name) . '.pdf';

            if ('' !== $this->saveToPath) {
                $doc->save($this->saveToPath . $filename);
            }
            if ($log) {
                rex_logger::factory()->info('PDFOut: PDF-Generierung erfolgreich für "' . $this->name . '" in ' . round((microtime(true) - $startTime) * 1000, 2) . 'ms');
            }
            if ('' === $this->saveToPath || $this->saveAndSend) {
                $this->attachment ? $doc->download($filename) : $doc->inline($filename);
            }
        } catch (Throwable $e) {
            if ($log) {
                rex_logger::factory()->error('PDFOut: Fehler bei PDF-Generierung für "' . $this->name . '": ' . $e->getMessage());
            }
            if ($addon->getConfig('enable_debug_mode', false)) {
                throw $e;
            }
            throw new Exception('PDF-Generierung fehlgeschlagen: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generiert eine URL für ein Media-Element
     *
     * @param string $type Der Media Manager Typ
     * @param string $file Der Dateiname
     * @return string Die generierte URL
     */
    public static function mediaUrl(string $type, string $file): string
    {
        $addon = rex_addon::get('pdfout');
        $url = rex_media_manager::getUrl($type, $file);
        if ($addon->getProperty('aspdf', false) || rex_request('pdfout', 'int', 0) === 1) {
            return rtrim(rex::getServer(),'/') . $url;
        }
        return $url;
    }

    /**
     * Generiert eine URL für den PDF-Viewer
     *
     * @param string $file Optional: Die anzuzeigende PDF-Datei
     * @param string $returnUrl Optional: Rücksprung-Adresse (gleiche Domain) – zeigt im Viewer einen Knopf „Zurück“,
     *                          z. B. wenn der Viewer als eigene Seite geöffnet wird
     * @return string Die generierte URL
     */
    public static function viewer(string $file = '', string $returnUrl = ''): string
    {
        if ($file !== '') {
            $addon = rex_addon::get('pdfout');
            $params = ['file' => $file];

            $toolbarParams = self::getToolbarViewerParams($addon);
            if ([] !== $toolbarParams) {
                $params = array_merge($params, $toolbarParams);
            }

            if ('' !== $returnUrl) {
                $params['returnUrl'] = $returnUrl;
            }
            $params['viewerVersion'] = $addon->getVersion();

            return self::buildViewerUrl($params);
        } else {
            return '#pdf_missing';
        }
    }

    /**
     * Generiert eine URL für den PDF-Viewer mit einem gezielten Profil.
     *
     * @param string $file Die anzuzeigende PDF-Datei
     * @param string $profileName Der Profilname aus der Toolbar-Konfiguration
     * @return string Die generierte URL
     */
    public static function viewerWithProfile(string $file, string $profileName): string
    {
        if ('' === $file) {
            return '#pdf_missing';
        }

        $addon = rex_addon::get('pdfout');
        $params = ['file' => $file];

        $toolbarParams = self::getToolbarViewerParamsForProfile($addon, $profileName);
        if ([] === $toolbarParams) {
            $toolbarParams = self::getToolbarViewerParams($addon);
        }

        if ([] !== $toolbarParams) {
            $params = array_merge($params, $toolbarParams);
        }

        $params['viewerVersion'] = $addon->getVersion();

        return self::buildViewerUrl($params);
    }

    /**
     * Baut die finale Viewer-URL aus Query-Parametern.
     *
     * @param array<string, string> $params Query-Parameter
     * @return string
     */
    private static function buildViewerUrl(array $params): string
    {
        return rex_url::assets('addons/pdfout/vendor/web/viewer.html') . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Liefert die Toolbar-Parameter für den PDF-Viewer.
     *
     * @param rex_addon_interface $addon AddOn-Instanz.
     * @return array<string, string>
     */
    private static function getToolbarViewerParams(rex_addon_interface $addon): array
    {
        $profiles = $addon->getConfig('toolbar_profiles', []);
        if (!is_array($profiles)) {
            $profiles = [];
        }

        $activeProfile = (string) $addon->getConfig('toolbar_active_profile', '');
        $profileConfig = [];

        if ('' !== $activeProfile && isset($profiles[$activeProfile]) && is_array($profiles[$activeProfile])) {
            $profileConfig = $profiles[$activeProfile];
        }

        if ([] === $profileConfig) {
            $preset = (string) $addon->getConfig('toolbar_preset', 'balanced');
            $hiddenGroups = $addon->getConfig('toolbar_hidden_groups', []);
            if (!is_array($hiddenGroups)) {
                $hiddenGroups = [];
            }

            return [
                'toolbarPreset' => $preset,
                'toolbarHiddenGroups' => implode(',', $hiddenGroups),
            ];
        }

        $preset = (string) ($profileConfig['preset'] ?? 'balanced');
        $hiddenGroups = $profileConfig['hidden_groups'] ?? [];
        if (!is_array($hiddenGroups)) {
            $hiddenGroups = [];
        }

        return [
            'toolbarPreset' => $preset,
            'toolbarHiddenGroups' => implode(',', $hiddenGroups),
        ];
    }

    /**
     * Liefert die Toolbar-Parameter für ein konkretes Profil.
     *
     * @param rex_addon_interface $addon AddOn-Instanz
     * @param string $profileName Profilname
     * @return array<string, string>
     */
    private static function getToolbarViewerParamsForProfile(rex_addon_interface $addon, string $profileName): array
    {
        $profiles = $addon->getConfig('toolbar_profiles', []);
        if (!is_array($profiles)) {
            return [];
        }

        if (!isset($profiles[$profileName]) || !is_array($profiles[$profileName])) {
            return [];
        }

        $profileConfig = $profiles[$profileName];
        $preset = (string) ($profileConfig['preset'] ?? 'balanced');
        $hiddenGroups = $profileConfig['hidden_groups'] ?? [];
        if (!is_array($hiddenGroups)) {
            $hiddenGroups = [];
        }

        return [
            'toolbarPreset' => $preset,
            'toolbarHiddenGroups' => implode(',', $hiddenGroups),
        ];
    }

    // ------------------------------------------------------------------ bisherige API (weiter nutzbar)

    /**
     * Aktiviert die digitale Signierung des PDFs
     *
     * @param string $certificatePath Zertifikat (.p12/.pfx oder .pem); leer = Standard des Addons
     */
    public function enableDigitalSignature(
        string $certificatePath = '',
        string $password = '',
        string $name = '',
        string $location = '',
        string $reason = '',
        string $contactInfo = '',
    ): self {
        $this->enableSigning = true;
        $this->certificate = null;
        $this->certificatePath = '' !== $certificatePath ? $certificatePath : rex_addon::get('pdfout')->getDataPath('certificates/default.p12');
        $this->certificatePassword = $password;
        $this->visibleSignature['name'] = $name;
        $this->visibleSignature['location'] = $location;
        $this->visibleSignature['reason'] = $reason;
        $this->visibleSignature['contact_info'] = $contactInfo;
        return $this;
    }

    /**
     * Sichtbare Signatur (Millimeter ab links oben; Seite -1 = letzte)
     */
    public function setVisibleSignature(int|float $x = 180, int|float $y = 60, int|float $width = 15, int|float $height = 15, int $page = -1): self
    {
        $this->visibleSignature = array_merge($this->visibleSignature, ['enabled' => true, 'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'page' => $page]);
        return $this;
    }

    /**
     * Aktiviert den Passwortschutz
     *
     * @param array<Permission|string> $permissions erlaubte Rechte, z. B. ['print', 'copy']
     */
    public function enablePasswordProtection(string $userPassword, string $ownerPassword = '', array $permissions = ['print']): self
    {
        $this->enablePasswordProtection = true;
        $this->userPassword = $userPassword;
        $this->ownerPassword = $ownerPassword;
        $this->permissions = Permission::list($permissions);
        return $this;
    }

    /**
     * Signiert ein vorhandenes PDF
     *
     * @param array<string, mixed> $signatureInfo Name, Location, Reason, ContactInfo; visible + x/y/width/height für ein sichtbares Feld
     */
    public function signExistingPdf(string $inputPdfPath, string $outputPdfPath, string $certificatePath = '', string $password = '', array $signatureInfo = []): bool
    {
        try {
            $certificate = Certificate::fromFile('' !== $certificatePath ? $certificatePath : rex_addon::get('pdfout')->getDataPath('certificates/default.p12'), $password);
            $field = !empty($signatureInfo['visible'])
                ? SignatureField::at((float) ($signatureInfo['x'] ?? 15), (float) ($signatureInfo['y'] ?? 255), (float) ($signatureInfo['width'] ?? 70), (float) ($signatureInfo['height'] ?? 25))
                : null;
            PdfDocument::fromFile($inputPdfPath)
                ->sign($certificate, (string) ($signatureInfo['Name'] ?? $signatureInfo['signer'] ?? ''), (string) ($signatureInfo['Reason'] ?? ''), (string) ($signatureInfo['Location'] ?? ''), (string) ($signatureInfo['ContactInfo'] ?? ''), $field)
                ->save($outputPdfPath);
            return true;
        } catch (Throwable $e) {
            rex_logger::logException($e);
            return false;
        }
    }

    /**
     * Konfiguriert Inhalt, Signatur und Schutz in einem Schritt (Ausgabe mit run())
     *
     * @param array<string, mixed> $signatureOptions certificatePath, password, name, location, reason, contactInfo, visible, x, y, width, height, page
     * @param array<string, mixed> $pdfOptions paperSize, orientation, font, dpi, attachment, saveToPath, baseTemplate, userPassword, ownerPassword, permissions
     */
    public function generateSignedPdf(string $html, string $filename = 'signed_document', array $signatureOptions = [], array $pdfOptions = []): self
    {
        $this->setHtml($html);
        $this->setName($filename);
        if (isset($pdfOptions['paperSize'])) {
            $this->setPaperSize($pdfOptions['paperSize'], $pdfOptions['orientation'] ?? 'portrait');
        }
        if (isset($pdfOptions['font'])) {
            $this->setFont((string) $pdfOptions['font']);
        }
        if (isset($pdfOptions['dpi'])) {
            $this->setDpi((int) $pdfOptions['dpi']);
        }
        if (isset($pdfOptions['attachment'])) {
            $this->setAttachment((bool) $pdfOptions['attachment']);
        }
        if (isset($pdfOptions['saveToPath'])) {
            $this->setSaveToPath((string) $pdfOptions['saveToPath']);
        }
        if (isset($pdfOptions['baseTemplate'])) {
            $this->setBaseTemplate((string) $pdfOptions['baseTemplate'], (string) ($pdfOptions['contentPlaceholder'] ?? '{{CONTENT}}'));
        }
        if ([] !== $signatureOptions) {
            $this->enableDigitalSignature(
                (string) ($signatureOptions['certificatePath'] ?? ''),
                (string) ($signatureOptions['password'] ?? ''),
                (string) ($signatureOptions['name'] ?? ''),
                (string) ($signatureOptions['location'] ?? ''),
                (string) ($signatureOptions['reason'] ?? ''),
                (string) ($signatureOptions['contactInfo'] ?? ''),
            );
            if (!empty($signatureOptions['visible'])) {
                $this->setVisibleSignature($signatureOptions['x'] ?? 15, $signatureOptions['y'] ?? 255, $signatureOptions['width'] ?? 70, $signatureOptions['height'] ?? 25, (int) ($signatureOptions['page'] ?? -1));
            }
        }
        if (!empty($pdfOptions['userPassword'])) {
            $this->enablePasswordProtection((string) $pdfOptions['userPassword'], (string) ($pdfOptions['ownerPassword'] ?? ''), (array) ($pdfOptions['permissions'] ?? ['print']));
        }
        return $this;
    }

    /**
     * wie generateSignedPdf() mit sichtbarem Feld unten links
     *
     * @param array<string, mixed> $signatureOptions
     * @param array<string, mixed> $pdfOptions
     */
    public function generateProfessionalSignedPdf(string $html, string $filename = 'professional_signed_document', array $signatureOptions = [], array $pdfOptions = []): self
    {
        return $this->generateSignedPdf($html, $filename, array_merge([
            'visible' => true, 'x' => 15, 'y' => 255, 'width' => 70, 'height' => 25, 'page' => -1,
        ], $signatureOptions), $pdfOptions);
    }

    /**
     * Prüft die Signaturen eines PDFs (Poppler/pdfsig)
     *
     * @return array{valid: bool, signatures: list<array<string, mixed>>, errors: list<string>, warnings: list<string>}
     */
    public function validateSignedPdf(string $pdfPath, string $password = ''): array
    {
        $results = ['valid' => false, 'signatures' => [], 'errors' => [], 'warnings' => []];
        if (!is_file($pdfPath)) {
            $results['errors'][] = 'PDF-Datei nicht gefunden: ' . $pdfPath;
            return $results;
        }
        if (!Poppler::isAvailable()) {
            $results['errors'][] = PopplerUnavailableException::forFeature('Die Signaturprüfung')->getMessage();
            return $results;
        }
        try {
            $signatures = Poppler::signatures($pdfPath, $password);
        } catch (Throwable $e) {
            $results['errors'][] = $e->getMessage();
            return $results;
        }
        if ([] === $signatures) {
            $results['errors'][] = 'Das PDF enthält keine Signatur.';
            return $results;
        }
        foreach ($signatures as $signature) {
            $results['signatures'][] = $signature + [
                'status_code' => $signature['valid'] ? 'valid' : 'invalid',
                'document_intact' => $signature['valid'],
                'certificate_valid' => $signature['certificate_trusted'],
            ];
            if (!$signature['certificate_trusted']) {
                $results['warnings'][] = sprintf('Signatur „%s“: %s', $signature['signer'], $signature['certificate'] ?: 'Zertifikat nicht vertrauenswürdig');
            }
            if (!$signature['whole_document']) {
                $results['warnings'][] = sprintf('Signatur „%s“ deckt nicht das ganze Dokument ab – nach dem Signieren wurde etwas geändert.', $signature['signer']);
            }
        }
        $results['valid'] = [] === array_filter($signatures, static fn (array $s): bool => !$s['valid']);
        return $results;
    }

    /**
     * HTML erzeugen und signieren; gibt die PDF-Daten zurück (false bei Fehler)
     *
     * @param array<string, mixed> $signatureInfo Name, Location, Reason, ContactInfo
     */
    public function generateCleanSignedPdf(string $content, string $certificatePath, string $certificatePassword, array $signatureInfo = []): string|false
    {
        try {
            return (clone $this)->html($content)->document()
                ->sign(Certificate::fromFile($certificatePath, $certificatePassword), (string) ($signatureInfo['Name'] ?? ''), (string) ($signatureInfo['Reason'] ?? ''), (string) ($signatureInfo['Location'] ?? ''), (string) ($signatureInfo['ContactInfo'] ?? ''), SignatureField::bottomLeft())
                ->toString();
        } catch (Throwable $e) {
            rex_logger::logException($e);
            return false;
        }
    }

    /**
     * PDF aus HTML erstellen und signieren; speichern oder ausgeben
     *
     * @param array<string, mixed> $signatureInfo Name, Location, Reason, ContactInfo
     * @return bool|string true bei Ausgabe, Pfad bei Speicherung
     */
    public function createSignedWorkflow(
        string $html,
        string $certificatePath = '',
        string $certificatePassword = '',
        array $signatureInfo = [],
        string $filename = 'signed_document.pdf',
        string $cacheDir = '',
        string $saveToPath = '',
        bool $replaceOriginal = false,
    ): bool|string {
        $certificate = Certificate::fromFile(
            '' !== $certificatePath ? $certificatePath : rex_path::addonData('pdfout', 'certificates/default.p12'),
            '' !== $certificatePassword ? $certificatePassword : (string) rex_addon::get('pdfout')->getConfig('default_certificate_password', ''),
        );
        $doc = (clone $this)->html($html)->document()->sign(
            $certificate,
            (string) ($signatureInfo['Name'] ?? ''),
            (string) ($signatureInfo['Reason'] ?? ''),
            (string) ($signatureInfo['Location'] ?? ''),
            (string) ($signatureInfo['ContactInfo'] ?? ''),
        );
        return $this->deliver($doc, $filename, $saveToPath, $replaceOriginal);
    }

    /** @return bool|string */
    public function createSignedDocument(string $html, string $filename = 'document.pdf', string $saveToPath = '', bool $replaceOriginal = false): bool|string
    {
        return $this->createSignedWorkflow($html, '', '', [], $filename, '', $saveToPath, $replaceOriginal);
    }

    /**
     * PDF aus HTML erstellen und mit Passwort schützen; speichern oder ausgeben
     *
     * @param array<Permission|string> $permissions erlaubte Rechte
     * @return bool|string true bei Ausgabe, Pfad bei Speicherung
     */
    public function createPasswordProtectedWorkflow(
        string $html,
        string $userPassword,
        string $ownerPassword = '',
        array $permissions = ['print'],
        string $filename = 'protected_document.pdf',
        string $cacheDir = '',
        string $saveToPath = '',
        bool $replaceOriginal = false,
    ): bool|string {
        $doc = (clone $this)->html($html)->document()->protect($userPassword, $ownerPassword, $permissions);
        return $this->deliver($doc, $filename, $saveToPath, $replaceOriginal);
    }

    /** @return bool|string */
    public function createPasswordProtectedDocument(string $html, string $userPassword, string $filename = 'protected_document.pdf', string $saveToPath = '', bool $replaceOriginal = false): bool|string
    {
        return $this->createPasswordProtectedWorkflow($html, $userPassword, '', ['print'], $filename, '', $saveToPath, $replaceOriginal);
    }

    /**
     * PDF-Dateien zusammenführen
     *
     * @param list<string> $pdfPaths
     * @param bool $addPageBreaks Trennseite „Dokument n“ zwischen den Dateien
     * @return bool|string true bei Ausgabe, Pfad bei Speicherung
     */
    public function mergePdfs(array $pdfPaths, string $outputFilename = 'merged_document.pdf', bool $addPageBreaks = false, string $cacheDir = '', string $saveToPath = '', bool $replaceOriginal = false): bool|string
    {
        if ([] === $pdfPaths) {
            throw new Exception('Mindestens eine PDF-Datei muss angegeben werden');
        }
        $documents = [];
        foreach ($pdfPaths as $index => $path) {
            if ($addPageBreaks && $index > 0) {
                $documents[] = (clone $this)->html('<div style="text-align:center;margin-top:120px;font-family:sans-serif"><hr><p>Dokument ' . ($index + 1) . '</p><hr></div>')->document();
            }
            $documents[] = PdfDocument::fromFile($path);
        }
        $doc = array_shift($documents)->append(...$documents);
        return $this->deliver($doc, $outputFilename, $saveToPath, $replaceOriginal);
    }

    /**
     * mehrere HTML-Inhalte als ein PDF
     *
     * @param list<string> $htmlContents
     * @return bool|string
     */
    public function mergeHtmlToPdf(array $htmlContents, string $outputFilename = 'merged_document.pdf', bool $addPageBreaks = true, string $saveToPath = '', bool $replaceOriginal = false): bool|string
    {
        if ([] === $htmlContents) {
            throw new Exception('Mindestens ein HTML-Inhalt muss angegeben werden');
        }
        $documents = array_map(fn (string $html): PdfDocument => (clone $this)->html($html)->document(), $htmlContents);
        $doc = array_shift($documents)->append(...$documents);
        return $this->deliver($doc, $outputFilename, $saveToPath, $replaceOriginal);
    }

    /**
     * PDF aus HTML erstellen und vorhandene PDFs anhängen (z. B. Rechnung + AGB)
     *
     * @param list<string> $appendPdfPaths
     * @return bool|string
     */
    public function createWithAppendedPdfs(string $html, array $appendPdfPaths = [], string $filename = 'document_with_attachments.pdf', string $cacheDir = '', string $saveToPath = '', bool $replaceOriginal = false): bool|string
    {
        return $this->deliver((clone $this)->html($html)->append(...$appendPdfPaths)->document(), $filename, $saveToPath, $replaceOriginal);
    }

    /**
     * @param list<string> $appendPdfPaths
     * @return bool|string
     */
    public function createDocumentWithAttachments(string $html, array $appendPdfPaths = [], string $filename = 'document_with_attachments.pdf', string $saveToPath = '', bool $replaceOriginal = false): bool|string
    {
        return $this->createWithAppendedPdfs($html, $appendPdfPaths, $filename, '', $saveToPath, $replaceOriginal);
    }

    /**
     * speichern (Pfad zurück) oder ausgeben (true) – gemeinsamer Abschluss der Workflow-Methoden
     */
    protected function deliver(PdfDocument $doc, string $filename, string $saveToPath, bool $replaceOriginal): bool|string
    {
        if ('' !== $saveToPath) {
            return $doc->save(rtrim($saveToPath, '/') . '/' . basename($filename), $replaceOriginal);
        }
        $doc->inline($filename);
    }
}
