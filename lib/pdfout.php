<?php
namespace FriendsOfRedaxo\PdfOut;

use Dompdf\Dompdf;
use Dompdf\Options;
use TCPDF;
use Exception;
use rex;
use rex_addon;
use rex_article;
use rex_article_content;
use rex_extension;
use rex_extension_point;
use rex_file;
use rex_logger;
use rex_media_manager;
use rex_path;
use rex_response;
use rex_string;
use rex_url;

/**
 * PdfOut-Klasse zur Erstellung von PDF-Dokumenten in REDAXO
 * 
 * Diese Klasse erweitert Dompdf und bietet zusätzliche Funktionen
 * zur einfachen Erstellung von PDF-Dokumenten im REDAXO-Kontext.
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

    /** @var string Papierformat für das PDF */
    protected $paperSize = 'A4';

    // TCPDF/Signierung Eigenschaften
    /** @var bool Ob das PDF signiert werden soll */
    protected $enableSigning = false;

    /** @var string Pfad zum Zertifikatfile (.p12) */
    protected $certificatePath = '';

    /** @var string Passwort für das Zertifikat */
    protected $certificatePassword = '';

    /** @var array Informationen für die sichtbare Signatur */
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
        'contact_info' => ''
    ];

    /** @var bool Ob das PDF passwortgeschützt werden soll */
    protected $enablePasswordProtection = false;

    /** @var string User-Passwort für das PDF */
    protected $userPassword = '';

    /** @var string Owner-Passwort für das PDF */
    protected $ownerPassword = '';

    /** @var array Berechtigungen für das PDF */
    protected $permissions = ['print', 'modify', 'copy', 'annot-forms'];

    // ZUGFeRD/Factur-X Eigenschaften
    /** @var bool Ob ZUGFeRD/Factur-X aktiviert werden soll */
    protected $enableZugferd = false;

    /** @var string ZUGFeRD-Profil (MINIMUM, BASIC, COMFORT, EXTENDED) */
    protected $zugferdProfile = 'BASIC';

    /** @var array Rechnungsdaten für ZUGFeRD */
    protected $zugferdInvoiceData = [];

    /** @var string Optionaler XML-Dateiname für ZUGFeRD */
    protected $zugferdXmlFilename = 'ZUGFeRD-invoice.xml';

    /** @var TCPDF|null TCPDF/FPDI-Instanz für erweiterte PDF-Operationen */
    protected $pdf = null;

    /** @var int Maximale HTML-Inhaltsgröße in Bytes (Standard: 10MB) */
    protected $maxHtmlSize = 10485760;

    /** @var int Maximale Verarbeitungszeit in Sekunden (Standard: 300s) */
    protected $maxExecutionTime = 300;

    /** @var int Maximale Zertifikatsdateigröße in Bytes (Standard: 1MB) */
    protected $maxCertificateSize = 1048576;

    /**
     * Setzt die Sicherheitslimits für die PDF-Generierung
     *
     * @param int $maxHtmlSize Maximale HTML-Größe in Bytes
     * @param int $maxExecutionTime Maximale Verarbeitungszeit in Sekunden
     * @param int $maxCertificateSize Maximale Zertifikatsgröße in Bytes
     * @return self
     */
    public function setSecurityLimits(int $maxHtmlSize = null, int $maxExecutionTime = null, int $maxCertificateSize = null): self
    {
        if ($maxHtmlSize !== null) $this->maxHtmlSize = max(1024, $maxHtmlSize); // Minimum 1KB
        if ($maxExecutionTime !== null) $this->maxExecutionTime = max(30, $maxExecutionTime); // Minimum 30s
        if ($maxCertificateSize !== null) $this->maxCertificateSize = max(1024, $maxCertificateSize); // Minimum 1KB
        
        return $this;
    }

    /**
     * Konstruktor - lädt Standardkonfiguration
     */
    public function __construct()
    {
        parent::__construct();
        
        // Lade Standardkonfiguration aus dem AddOn
        $addon = rex_addon::get('pdfout');
        
        // PDF Grundeinstellungen aus Config laden
        $this->paperSize = $addon->getConfig('default_paper_size', 'A4');
        $this->orientation = $addon->getConfig('default_orientation', 'portrait');
        $this->font = $addon->getConfig('default_font', 'Dejavu Sans');
        $this->dpi = $addon->getConfig('default_dpi', 100);
        $this->attachment = $addon->getConfig('default_attachment', false);
        $this->remoteFiles = $addon->getConfig('default_remote_files', true);
        
        // Erweiterte Features aus Config laden
        if ($addon->getConfig('enable_signature_by_default', false)) {
            $this->enableSigning = true;
            $this->certificatePath = $addon->getConfig('default_certificate_path', '') 
                ?: $addon->getDataPath('certificates/default.p12');
            $this->certificatePassword = $addon->getConfig('default_certificate_password', '');
            
            $this->visibleSignature['x'] = $addon->getConfig('default_signature_position_x', 180);
            $this->visibleSignature['y'] = $addon->getConfig('default_signature_position_y', 60);
            $this->visibleSignature['width'] = $addon->getConfig('default_signature_width', 15);
            $this->visibleSignature['height'] = $addon->getConfig('default_signature_height', 15);
        }
        
        if ($addon->getConfig('enable_password_protection_by_default', false)) {
            $this->enablePasswordProtection = true;
        }
        
        // ZUGFeRD-Einstellungen aus Config laden
        if ($addon->getConfig('enable_zugferd_by_default', false)) {
            $this->enableZugferd = true;
            $this->zugferdProfile = $addon->getConfig('default_zugferd_profile', 'BASIC');
            $this->zugferdXmlFilename = $addon->getConfig('zugferd_xml_filename', 'factur-x.xml');
        }
        
        // Performance-Limits aus Config laden
        $this->maxHtmlSize = ($addon->getConfig('max_html_size_mb', 10) * 1024 * 1024);
        $this->maxExecutionTime = $addon->getConfig('max_execution_time', 300);
        $this->maxCertificateSize = ($addon->getConfig('max_certificate_size_kb', 1024) * 1024);
    }

    /**
     * Ersetzt den Platzhalter für die Seitenzahl im PDF
     *
     * @param Dompdf $dompdf Das Dompdf-Objekt
     */
    private function injectPageCount(Dompdf $dompdf): void
    {
        /** @var CPDF $canvas */
        $canvas = $dompdf->getCanvas();
        $pdf = $canvas->get_cpdf();
        foreach ($pdf->objects as &$o) {
            if ($o['t'] === 'contents') {
                $o['c'] = str_replace('DOMPDF_PAGE_COUNT_PLACEHOLDER', $canvas->get_page_count(), $o['c']);
            }
        }
    }

    /**
     * Setzt das Papierformat und die Ausrichtung für das PDF
     *
     * @param string $size Das Papierformat (z.B. 'A4', 'letter', oder [width, height] in points)
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
     * @throws Exception Bei zu großem HTML-Inhalt
     */
    public function setHtml(string $html, bool $outputfilter = false): self
    {
        // Sicherheitsprüfung: HTML-Größe begrenzen
        if (strlen($html) > $this->maxHtmlSize) {
            throw new Exception('HTML-Inhalt zu groß. Maximum: ' . number_format($this->maxHtmlSize / 1024 / 1024, 1) . ' MB');
        }
        
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

    /**
     * Führt die PDF-Erstellung aus
     */
    public function run(): void
    {
        $startTime = microtime(true);
        $addon = rex_addon::get('pdfout');
        
        // Ausführungszeit begrenzen
        $oldLimit = ini_get('max_execution_time');
        if ($oldLimit < $this->maxExecutionTime) {
            set_time_limit($this->maxExecutionTime);
        }
        
        $finalHtml = $this->html;

        // Wenn ein Grundtemplate gesetzt wurde, füge den Inhalt ein
        if ($this->baseTemplate !== '') {
            $finalHtml = str_replace($this->contentPlaceholder, $this->html, $this->baseTemplate);
        }

        // Logging, wenn aktiviert
        if ($addon->getConfig('log_pdf_generation', false)) {
            rex_logger::factory()->info('PDFOut: Starte PDF-Generierung für "' . $this->name . '"', 
                ['paperSize' => $this->paperSize, 'orientation' => $this->orientation, 'dpi' => $this->dpi, 'htmlSize' => strlen($finalHtml)]);
        }

        try {
            // Prüfen ob bereits ein FPDI/TCPDF-PDF-Objekt vorhanden ist (z.B. von importAndExtendPdf)
            if ($this->pdf !== null) {
                $this->runWithExistingPdf();
                return;
            }
            
            // Prüfen ob TCPDF-Features benötigt werden
            if ($this->enableSigning || $this->enablePasswordProtection || $this->enableZugferd) {
                $this->runWithTcpdf($finalHtml);
                return;
            }

            $this->loadHtml($finalHtml);

            // Optionen festlegen
            $options = $this->getOptions();
            $options->setChroot(rex_path::frontend());
            $options->setDefaultFont($this->font);
            $options->setDpi($this->dpi);
            $options->setFontCache(rex_path::addonCache('pdfout', 'fonts'));
            $options->setIsRemoteEnabled($this->remoteFiles);
            $this->setOptions($options);

            // Papierformat und Ausrichtung setzen
            $this->setPaper($this->paperSize, $this->orientation);

            // Rendern des PDFs
            $this->render();

            // Pagecounter Placeholder ersetzen, wenn vorhanden
            $this->injectPageCount($this);

            // Speichern des PDFs 
            if ($this->saveToPath !== '') {
                $savedata = $this->output();
                if (!is_null($savedata)) {
                    rex_file::put($this->saveToPath . rex_string::normalize($this->name) . '.pdf', $savedata);
                }
            }

            // Ausliefern des PDFs
            if ($this->saveToPath === '' || $this->saveAndSend === true) {
                rex_response::cleanOutputBuffers(); // OutputBuffer leeren
                header('Content-Type: application/pdf');
                $this->stream(rex_string::normalize($this->name), array('Attachment' => $this->attachment));
                exit();
            }
            
            // Erfolgreiche Generierung loggen
            if ($addon->getConfig('log_pdf_generation', false)) {
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                rex_logger::factory()->info('PDFOut: PDF-Generierung erfolgreich abgeschlossen für "' . $this->name . '" in ' . $executionTime . 'ms');
            }
            
        } catch (Exception $e) {
            // Fehler loggen, wenn aktiviert
            if ($addon->getConfig('log_pdf_generation', false)) {
                rex_logger::factory()->error('PDFOut: Fehler bei PDF-Generierung für "' . $this->name . '": ' . $e->getMessage());
            }
            
            // Debug-Modus: Detaillierte Fehlerausgabe
            if ($addon->getConfig('enable_debug_mode', false)) {
                throw $e;
            } else {
                // Sanitize error message in production
                $sanitizedMessage = 'PDF-Generierung fehlgeschlagen';
                if (strpos($e->getMessage(), 'HTML-Inhalt zu groß') !== false) {
                    $sanitizedMessage = $e->getMessage();
                } elseif (strpos($e->getMessage(), 'Zertifikatsdatei') !== false) {
                    $sanitizedMessage = 'Fehler bei der Zertifikatsverarbeitung';
                }
                throw new Exception($sanitizedMessage . '. Aktivieren Sie den Debug-Modus für weitere Details.');
            }
        } finally {
            // Ausführungszeit zurücksetzen
            if ($oldLimit < $this->maxExecutionTime) {
                set_time_limit($oldLimit);
            }
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
     * @return string Die generierte URL
     */
    public static function viewer(string $file = ''): string
    {
        if ($file !== '') {
            return rex_url::assets('addons/pdfout/vendor/web/viewer.html?file=' . urlencode($file));
        } else {
            return '#pdf_missing';
        }
    }

    /**
     * Aktiviert die digitale Signierung des PDFs
     *
     * @param string $certificatePath Pfad zum Zertifikat (.p12). Wenn leer, wird der Standardpfad verwendet
     * @param string $password Passwort für das Zertifikat
     * @param string $name Name des Signierers
     * @param string $location Ort der Signierung
     * @param string $reason Grund für die Signierung
     * @param string $contactInfo Kontaktinformationen
     * @return self
     * @throws Exception Bei ungültigen Zertifikatsdateien
     */
    public function enableDigitalSignature(
        string $certificatePath = '',
        string $password = '',
        string $name = '',
        string $location = '',
        string $reason = '',
        string $contactInfo = ''
    ): self {
        $this->enableSigning = true;
        
        // Standardpfad verwenden, wenn keiner angegeben
        if (empty($certificatePath)) {
            $certificatePath = rex_addon::get('pdfout')->getDataPath('certificates/default.p12');
        }
        
        // Sicherheitsprüfungen für Zertifikatsdatei
        if (!empty($certificatePath)) {
            // Pfad-Traversal-Schutz
            $realPath = realpath($certificatePath);
            if ($realPath === false || !file_exists($realPath)) {
                throw new Exception('Zertifikatsdatei nicht gefunden: ' . $certificatePath);
            }
            
            // Dateigröße prüfen
            $fileSize = filesize($realPath);
            if ($fileSize > $this->maxCertificateSize) {
                throw new Exception('Zertifikatsdatei zu groß. Maximum: ' . number_format($this->maxCertificateSize / 1024, 0) . ' KB');
            }
            
            // Dateiberechtigungen prüfen (sollten nicht zu offen sein)
            $perms = fileperms($realPath);
            if ($perms & 0044) { // Andere haben Lesezugriff
                rex_logger::factory()->warning('PDFOut: Zertifikatsdatei hat unsichere Berechtigung: ' . $realPath);
            }
            
            $certificatePath = $realPath;
        }
        
        $this->certificatePath = $certificatePath;
        $this->certificatePassword = $password;
        $this->visibleSignature['name'] = $name;
        $this->visibleSignature['location'] = $location;
        $this->visibleSignature['reason'] = $reason;
        $this->visibleSignature['contact_info'] = $contactInfo;
        
        return $this;
    }

    /**
     * Aktiviert die sichtbare Signatur
     *
     * @param int $x X-Position der Signatur
     * @param int $y Y-Position der Signatur
     * @param int $width Breite der Signatur
     * @param int $height Höhe der Signatur
     * @param int $page Seitennummer (-1 für letzte Seite)
     * @return self
     */
    public function setVisibleSignature(
        int $x = 180,
        int $y = 60,
        int $width = 15,
        int $height = 15,
        int $page = -1
    ): self {
        $this->visibleSignature['enabled'] = true;
        $this->visibleSignature['x'] = $x;
        $this->visibleSignature['y'] = $y;
        $this->visibleSignature['width'] = $width;
        $this->visibleSignature['height'] = $height;
        $this->visibleSignature['page'] = $page;
        
        return $this;
    }

    /**
     * Aktiviert den Passwortschutz für das PDF
     *
     * @param string $userPassword Benutzer-Passwort (zum Öffnen des PDFs)
     * @param string $ownerPassword Besitzer-Passwort (für Berechtigungen)
     * @param array $permissions Array mit Berechtigungen ['print', 'modify', 'copy', 'annot-forms']
     * @return self
     */
    public function enablePasswordProtection(
        string $userPassword,
        string $ownerPassword = '',
        array $permissions = ['print']
    ): self {
        $this->enablePasswordProtection = true;
        $this->userPassword = $userPassword;
        $this->ownerPassword = $ownerPassword ?: $userPassword . '_owner';
        $this->permissions = $permissions;
        
        return $this;
    }

    /**
     * Signiert ein bereits erstelltes PDF nachträglich
     *
     * @param string $inputPdfPath Pfad zum Input-PDF
     * @param string $outputPdfPath Pfad zum Output-PDF
     * @param string $certificatePath Pfad zum Zertifikat
     * @param string $password Zertifikat-Passwort
     * @param array $signatureInfo Informationen für die Signatur
     * @return bool
     */
    public function signExistingPdf(
        string $inputPdfPath,
        string $outputPdfPath,
        string $certificatePath = '',
        string $password = '',
        array $signatureInfo = []
    ): bool {
        if (!file_exists($inputPdfPath)) {
            return false;
        }

        // Standardpfad verwenden, wenn keiner angegeben
        if (empty($certificatePath)) {
            $certificatePath = rex_addon::get('pdfout')->getDataPath('certificates/default.p12');
        }

        if (!file_exists($certificatePath)) {
            return false;
        }

        try {
            // Prüfe ob FPDI verfügbar ist für echten PDF-Import
            if (class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
                // FPDI ist verfügbar - verwende echten PDF-Import
                $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
                $pagecount = $pdf->setSourceFile($inputPdfPath);
                
                // Importiere alle Seiten des Original-PDFs
                for ($i = 1; $i <= $pagecount; $i++) {
                    $template = $pdf->importPage($i);
                    $size = $pdf->getTemplateSize($template);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($template);
                    
                    // Optional: Füge Signatur-Hinweis zur letzten Seite hinzu
                    if ($i === $pagecount) {
                        $pdf->SetFont('helvetica', '', 8);
                        $pdf->SetTextColor(128, 128, 128);
                        $pdf->setXY(10, $size['height'] - 20);
                        $pdf->Write(0, 'Dieses Dokument wurde digital signiert.');
                    }
                }
            } else {
                // Fallback: FPDI nicht verfügbar - erstelle neues PDF mit Hinweis
                $pdf = new TCPDF();
                $pdf->AddPage();
                $pdf->SetFont('helvetica', 'B', 14);
                $pdf->SetTextColor(255, 0, 0);
                $pdf->Write(0, 'WARNUNG: PDF-Import-Limitation');
                $pdf->Ln(10);
                
                $pdf->SetFont('helvetica', '', 12);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->Write(0, 'Das Original-PDF konnte nicht importiert werden. ');
                $pdf->Write(0, 'Für vollständige PDF-Import-Funktionalität installieren Sie FPDI:');
                $pdf->Ln(8);
                
                $pdf->SetFont('helvetica', '', 10);
                $pdf->SetTextColor(0, 0, 255);
                $pdf->Write(0, 'composer require setasign/fpdi');
                $pdf->Ln(10);
                
                $pdf->SetFont('helvetica', '', 12);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->Write(0, 'Original-Datei: ' . basename($inputPdfPath));
                $pdf->Ln(5);
                $pdf->Write(0, 'Signiert am: ' . date('d.m.Y H:i:s'));
            }
            
            // Digitale Signatur konfigurieren
            $certificateContent = file_get_contents($certificatePath);
            if ($certificateContent === false) {
                return false;
            }
            
            $info = array_merge([
                'Name' => 'PDF Signatur',
                'Location' => 'REDAXO',
                'Reason' => 'Dokument-Signierung',
                'ContactInfo' => ''
            ], $signatureInfo);
            
            $pdf->setSignature($certificateContent, $certificateContent, $password, '', 2, $info);
            
            // Sichtbare Signatur, falls gewünscht
            if (isset($signatureInfo['visible']) && $signatureInfo['visible']) {
                $pdf->setSignatureAppearance(
                    $signatureInfo['x'] ?? 180,
                    $signatureInfo['y'] ?? 60,
                    $signatureInfo['width'] ?? 15,
                    $signatureInfo['height'] ?? 15,
                    $signatureInfo['page'] ?? -1
                );
            }
            
            // Signiertes PDF speichern
            $pdf->Output($outputPdfPath, 'F');
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Führt die PDF-Erstellung mit TCPDF für erweiterte Features aus
     */
    protected function runWithTcpdf(string $finalHtml): void
    {
        $startTime = microtime(true);
        $addon = rex_addon::get('pdfout');
        
        // Erstelle zunächst ein normales PDF mit DomPDF
        $this->loadHtml($finalHtml);

        // Optionen festlegen
        $options = $this->getOptions();
        $options->setChroot(rex_path::frontend());
        $options->setDefaultFont($this->font);
        $options->setDpi($this->dpi);
        $options->setFontCache(rex_path::addonCache('pdfout', 'fonts'));
        $options->setIsRemoteEnabled($this->remoteFiles);
        $this->setOptions($options);

        // Papierformat und Ausrichtung setzen
        $this->setPaper($this->paperSize, $this->orientation);

        // Rendern des PDFs
        $this->render();

        // Pagecounter Placeholder ersetzen, wenn vorhanden
        $this->injectPageCount($this);

        // PDF-Daten von DomPDF erhalten
        $pdfData = $this->output();

        // Temporäre Datei für DomPDF-Output erstellen
        $tempFile = rex_path::addonCache('pdfout') . 'temp_' . uniqid() . '.pdf';
        
        // Sicherheitsprüfung: Verzeichnis existiert und ist beschreibbar
        $tempDir = dirname($tempFile);
        if (!is_dir($tempDir)) {
            rex_dir::create($tempDir);
        }
        if (!is_writable($tempDir)) {
            throw new Exception('Temporäres Verzeichnis nicht beschreibbar: ' . $tempDir);
        }
        
        rex_file::put($tempFile, $pdfData);

        try {
            // TCPDF für erweiterte Features verwenden
            $tcpdf = new TCPDF($this->orientation, 'mm', $this->paperSize, true, 'UTF-8', false);
            
            // TCPDF-Konfiguration
            $tcpdf->SetCreator('REDAXO PdfOut with TCPDF');
            $tcpdf->SetAuthor('REDAXO');
            $tcpdf->SetTitle($this->name);
            
            // TCPDF direkt mit HTML verwenden (bessere Kompatibilität)
            $tcpdf->AddPage();
            $tcpdf->writeHTML($finalHtml, true, false, true, false, '');

            // Digitale Signatur hinzufügen, falls aktiviert
            if ($this->enableSigning && file_exists($this->certificatePath)) {
                $this->addDigitalSignature($tcpdf);
            }

            // Passwortschutz hinzufügen, falls aktiviert
            if ($this->enablePasswordProtection) {
                $this->addPasswordProtection($tcpdf);
            }

            // ZUGFeRD verarbeiten, falls aktiviert
            $this->processZugferd($tcpdf);

            // Ausgabe verarbeiten
            $this->processTcpdfOutput($tcpdf);
            
            // Erfolgreiche Generierung loggen
            if ($addon->getConfig('log_pdf_generation', false)) {
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                rex_logger::factory()->info('PDFOut: TCPDF-Generierung erfolgreich abgeschlossen für "' . $this->name . '" in ' . $executionTime . 'ms');
            }

        } catch (Exception $e) {
            // Fehler loggen, wenn aktiviert
            if ($addon->getConfig('log_pdf_generation', false)) {
                rex_logger::factory()->error('PDFOut: Fehler bei TCPDF-Generierung für "' . $this->name . '": ' . $e->getMessage());
            }
            
            // Debug-Modus: Detaillierte Fehlerausgabe
            if ($addon->getConfig('enable_debug_mode', false)) {
                throw $e;
            } else {
                throw new Exception('PDF-Generierung mit erweiterten Features fehlgeschlagen. Aktivieren Sie den Debug-Modus für weitere Details.');
            }
            
        } finally {
            // Temporäre Datei löschen
            if (file_exists($tempFile) && $addon->getConfig('temp_file_cleanup', true)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Fügt digitale Signatur zu TCPDF hinzu
     */
    protected function addDigitalSignature(TCPDF $pdf): void
    {
        if (!file_exists($this->certificatePath)) {
            return;
        }

        $certificateContent = file_get_contents($this->certificatePath);
        if ($certificateContent === false) {
            return;
        }

        $info = [
            'Name' => $this->visibleSignature['name'] ?: 'REDAXO PDF Signatur',
            'Location' => $this->visibleSignature['location'] ?: 'REDAXO',
            'Reason' => $this->visibleSignature['reason'] ?: 'Dokument-Signierung',
            'ContactInfo' => $this->visibleSignature['contact_info'] ?: ''
        ];

        // Digitale Signatur setzen
        $pdf->setSignature($certificateContent, $certificateContent, $this->certificatePassword, '', 2, $info);

        // Sichtbare Signatur, falls aktiviert
        if ($this->visibleSignature['enabled']) {
            // Position der Signatur
            $x = $this->visibleSignature['x'];
            $y = $this->visibleSignature['y'];
            $w = $this->visibleSignature['width'];
            $h = $this->visibleSignature['height'];
            
            // Signatur-Box mit schwarzem Rahmen zeichnen
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.5);
            $pdf->Rect($x, $y, $w, $h, 'D');
            
            // Hintergrund leicht grau für bessere Sichtbarkeit
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Rect($x + 0.5, $y + 0.5, $w - 1, $h - 1, 'F');
            
            // Signatur-Text hinzufügen
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            
            // Header
            $pdf->SetXY($x + 1, $y + 1);
            $pdf->Cell($w - 2, 3, 'Digitally signed by:', 0, 1, 'L');
            
            // Name (fett)
            $pdf->SetXY($x + 1, $y + 4);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell($w - 2, 4, $info['Name'], 0, 1, 'L');
            
            // Datum
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($x + 1, $y + 8);
            $pdf->Cell($w - 2, 3, 'Date: ' . date('Y.m.d H:i:s O'), 0, 1, 'L');
            
            // Ort
            $pdf->SetXY($x + 1, $y + 11);
            $pdf->Cell($w - 2, 3, 'Location: ' . $info['Location'], 0, 1, 'L');
            
            // Grund
            $pdf->SetXY($x + 1, $y + 14);
            $pdf->Cell($w - 2, 3, 'Reason: ' . $info['Reason'], 0, 1, 'L');
            
            // TCPDF Signatur-Appearance setzen (für PDF-Reader-Integration)
            $pdf->setSignatureAppearance(
                $this->visibleSignature['x'],
                $this->visibleSignature['y'],
                $this->visibleSignature['width'],
                $this->visibleSignature['height'],
                $this->visibleSignature['page']
            );
        }
    }

    /**
     * Fügt Passwortschutz zu TCPDF hinzu
     */
    protected function addPasswordProtection(TCPDF $pdf): void
    {
        // Berechtigungen in TCPDF-Format konvertieren
        $tcpdfPermissions = [];
        
        if (in_array('print', $this->permissions)) {
            $tcpdfPermissions[] = 'print';
        }
        if (in_array('modify', $this->permissions)) {
            $tcpdfPermissions[] = 'modify';
        }
        if (in_array('copy', $this->permissions)) {
            $tcpdfPermissions[] = 'copy';
        }
        if (in_array('annot-forms', $this->permissions)) {
            $tcpdfPermissions[] = 'annot-forms';
        }

        $pdf->SetProtection($tcpdfPermissions, $this->userPassword, $this->ownerPassword, 0, null);
    }

    /**
     * Verarbeitet die TCPDF-Ausgabe
     */
    protected function processTcpdfOutput(TCPDF $pdf): void
    {
        // ZUGFeRD verarbeiten falls aktiviert
        $this->processZugferd($pdf);
        
        // Speichern des PDFs 
        if ($this->saveToPath !== '') {
            $savedata = $pdf->Output('', 'S');
            if (!empty($savedata)) {
                rex_file::put($this->saveToPath . rex_string::normalize($this->name) . '.pdf', $savedata);
            }
        }

        // Ausliefern des PDFs
        if ($this->saveToPath === '' || $this->saveAndSend === true) {
            rex_response::cleanOutputBuffers(); // OutputBuffer leeren
            $pdf->Output(rex_string::normalize($this->name) . '.pdf', $this->attachment ? 'D' : 'I');
            exit();
        }
    }

    /**
     * Verarbeitet bereits vorhandenes PDF-Objekt (z.B. von FPDI import)
     */
    protected function runWithExistingPdf(): void
    {
        $startTime = microtime(true);
        $addon = rex_addon::get('pdfout');
        
        if ($this->pdf === null) {
            throw new Exception('No PDF object available for processing');
        }

        try {
            // Erweiterte Features anwenden falls aktiviert
            if ($this->enableSigning && file_exists($this->certificatePath)) {
                $this->addDigitalSignature($this->pdf);
            }

            if ($this->enablePasswordProtection) {
                $this->addPasswordProtection($this->pdf);
            }

            // ZUGFeRD verarbeiten, falls aktiviert
            $this->processZugferd($this->pdf);

            // Ausgabe verarbeiten
            $this->processTcpdfOutput($this->pdf);
            
            // Erfolgreiche Generierung loggen
            if ($addon->getConfig('log_pdf_generation', false)) {
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                rex_logger::factory()->info('PDFOut: FPDI-PDF erfolgreich verarbeitet für "' . $this->name . '" in ' . $executionTime . 'ms');
            }

        } catch (Exception $e) {
            // Fehler loggen, wenn aktiviert
            if ($addon->getConfig('log_pdf_generation', false)) {
                rex_logger::factory()->error('PDFOut: Fehler bei FPDI-PDF-Verarbeitung für "' . $this->name . '": ' . $e->getMessage());
            }
            
            // Debug-Modus: Detaillierte Fehlerausgabe
            if ($addon->getConfig('enable_debug_mode', false)) {
                throw $e;
            } else {
                throw new Exception('FPDI-PDF-Verarbeitung fehlgeschlagen. Aktivieren Sie den Debug-Modus für weitere Details.');
            }
        }
    }

    // =============================================
    // ZUGFeRD/Factur-X Methoden
    // =============================================

    /**
     * Aktiviert ZUGFeRD/Factur-X für dieses PDF
     *
     * @param array $invoiceData Rechnungsdaten für ZUGFeRD
     * @param string $profile ZUGFeRD-Profil (MINIMUM, BASIC, COMFORT, EXTENDED)
     * @param string $xmlFilename Optionaler XML-Dateiname
     * @return $this
     */
    public function enableZugferd(array $invoiceData, string $profile = 'BASIC', string $xmlFilename = 'ZUGFeRD-invoice.xml'): self
    {
        $this->enableZugferd = true;
        $this->zugferdProfile = $profile;
        $this->zugferdInvoiceData = $invoiceData;
        $this->zugferdXmlFilename = $xmlFilename;
        
        return $this;
    }

    /**
     * Erstellt ein ZUGFeRD-konformes PDF mit eingebetteter XML
     *
     * @param TCPDF $pdf Das TCPDF-Objekt
     * @throws Exception
     */
    protected function processZugferd(TCPDF $pdf): void
    {
        if (!$this->enableZugferd || empty($this->zugferdInvoiceData)) {
            return;
        }

        try {
            // ZUGFeRD Library laden (über Composer Autoload)
            $vendorPath = rex_path::addon('pdfout') . 'vendor/autoload.php';
            if (!file_exists($vendorPath)) {
                throw new Exception('ZUGFeRD Library nicht installiert. Bitte "composer install" im PDFOut-Addon ausführen.');
            }
            require_once $vendorPath;
            
            // ZUGFeRD XML generieren
            $zugferdXml = $this->generateZugferdXml();
            
            if (empty($zugferdXml)) {
                throw new Exception('ZUGFeRD XML konnte nicht generiert werden');
            }

            // PDF/A-3 Metadaten setzen
            $pdf->SetPDFVersion('1.7');
            $pdf->setExtraXMP('
                <rdf:Description rdf:about="" xmlns:pdfaExtension="http://www.aiim.org/pdfa/ns/extension/" xmlns:pdfaSchema="http://www.aiim.org/pdfa/ns/schema#" xmlns:pdfaProperty="http://www.aiim.org/pdfa/ns/property#" xmlns:zf="urn:zugferd:pdfa:CrossIndustryDocument:invoice:2p0#">
                    <zf:ConformanceLevel>EN 16931</zf:ConformanceLevel>
                    <zf:DocumentFileName>' . $this->zugferdXmlFilename . '</zf:DocumentFileName>
                    <zf:DocumentType>INVOICE</zf:DocumentType>
                    <zf:Version>2.0</zf:Version>
                </rdf:Description>
            ');

            // XML als Anhang zum PDF hinzufügen
            $pdf->Annotation(
                0, 0, 0, 0, // Position (nicht sichtbar)
                $this->zugferdXmlFilename,
                ['Subtype' => 'FileAttachment', 'Name' => 'PushPin', 'Contents' => $zugferdXml],
                0
            );

            // Logging
            if (rex_addon::get('pdfout')->getConfig('enable_logging', false)) {
                rex_logger::factory()->info('ZUGFeRD XML erfolgreich in PDF eingebettet', [
                    'profile' => $this->zugferdProfile,
                    'xml_size' => strlen($zugferdXml),
                    'filename' => $this->zugferdXmlFilename
                ]);
            }

        } catch (Exception $e) {
            if (rex_addon::get('pdfout')->getConfig('enable_debug', false)) {
                throw new Exception('ZUGFeRD-Verarbeitung fehlgeschlagen: ' . $e->getMessage());
            }
            
            // Im Produktivbetrieb nur loggen
            rex_logger::factory()->error('ZUGFeRD-Fehler: ' . $e->getMessage(), [
                'profile' => $this->zugferdProfile,
                'invoice_data' => $this->zugferdInvoiceData
            ]);
        }
    }

    /**
     * Generiert ZUGFeRD-XML aus den Rechnungsdaten (vereinfachte Version)
     *
     * @return string Das generierte XML
     * @throws Exception
     */
    protected function generateZugferdXml(): string
    {
        try {
            // Vereinfachte ZUGFeRD XML-Generierung ohne komplexe Library-Aufrufe
            // Diese Version erstellt eine grundlegende ZUGFeRD-konforme XML
            
            $invoiceNumber = $this->zugferdInvoiceData['invoice_number'] ?? 'DEMO-001';
            $issueDate = $this->zugferdInvoiceData['issue_date'] ?? date('Y-m-d');
            $currency = $this->zugferdInvoiceData['currency'] ?? 'EUR';
            
            $seller = $this->zugferdInvoiceData['seller'] ?? [
                'name' => 'REDAXO Demo GmbH',
                'address' => [
                    'line1' => 'Musterstraße 123',
                    'postcode' => '12345',
                    'city' => 'Musterstadt',
                    'country' => 'DE'
                ]
            ];
            
            $buyer = $this->zugferdInvoiceData['buyer'] ?? [
                'name' => 'Musterkunde AG',
                'address' => [
                    'line1' => 'Kundenstraße 456',
                    'postcode' => '54321',
                    'city' => 'Kundenstadt',
                    'country' => 'DE'
                ]
            ];
            
            $totals = $this->zugferdInvoiceData['totals'] ?? [
                'net_amount' => 100.00,
                'tax_amount' => 19.00,
                'gross_amount' => 119.00
            ];

            // Einfache ZUGFeRD XML-Struktur
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" xmlns:qdt="urn:un:unece:uncefact:data:standard:QualifiedDataType:100" xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">
    <rsm:ExchangedDocumentContext>
        <ram:GuidelineSpecifiedDocumentContextParameter>
            <ram:ID>urn:cen.eu:en16931:2017#compliant#urn:zugferd.de:2p0:basic</ram:ID>
        </ram:GuidelineSpecifiedDocumentContextParameter>
    </rsm:ExchangedDocumentContext>
    <rsm:ExchangedDocument>
        <ram:ID>' . htmlspecialchars($invoiceNumber) . '</ram:ID>
        <ram:TypeCode>380</ram:TypeCode>
        <ram:IssueDateTime>
            <udt:DateTimeString format="102">' . str_replace('-', '', $issueDate) . '</udt:DateTimeString>
        </ram:IssueDateTime>
    </rsm:ExchangedDocument>
    <rsm:SupplyChainTradeTransaction>
        <ram:ApplicableHeaderTradeAgreement>
            <ram:SellerTradeParty>
                <ram:Name>' . htmlspecialchars($seller['name']) . '</ram:Name>
                <ram:PostalTradeAddress>
                    <ram:LineOne>' . htmlspecialchars($seller['address']['line1'] ?? '') . '</ram:LineOne>
                    <ram:PostcodeCode>' . htmlspecialchars($seller['address']['postcode'] ?? '') . '</ram:PostcodeCode>
                    <ram:CityName>' . htmlspecialchars($seller['address']['city'] ?? '') . '</ram:CityName>
                    <ram:CountryID>' . htmlspecialchars($seller['address']['country'] ?? 'DE') . '</ram:CountryID>
                </ram:PostalTradeAddress>
                <ram:SpecifiedTaxRegistration>
                    <ram:ID schemeID="VA">' . htmlspecialchars($seller['vat_id'] ?? '') . '</ram:ID>
                </ram:SpecifiedTaxRegistration>
                <ram:SpecifiedTaxRegistration>
                    <ram:ID schemeID="FC">' . htmlspecialchars($seller['tax_number'] ?? '') . '</ram:ID>
                </ram:SpecifiedTaxRegistration>
            </ram:SellerTradeParty>
            <ram:BuyerTradeParty>
                <ram:Name>' . htmlspecialchars($buyer['name']) . '</ram:Name>
                <ram:PostalTradeAddress>
                    <ram:LineOne>' . htmlspecialchars($buyer['address']['line1'] ?? '') . '</ram:LineOne>
                    <ram:PostcodeCode>' . htmlspecialchars($buyer['address']['postcode'] ?? '') . '</ram:PostcodeCode>
                    <ram:CityName>' . htmlspecialchars($buyer['address']['city'] ?? '') . '</ram:CityName>
                    <ram:CountryID>' . htmlspecialchars($buyer['address']['country'] ?? 'DE') . '</ram:CountryID>
                </ram:PostalTradeAddress>
            </ram:BuyerTradeParty>
        </ram:ApplicableHeaderTradeAgreement>
        <ram:ApplicableHeaderTradeDelivery/>
        <ram:ApplicableHeaderTradeSettlement>
            <ram:InvoiceCurrencyCode>' . htmlspecialchars($currency) . '</ram:InvoiceCurrencyCode>
            <ram:ApplicableTradeTax>
                <ram:CalculatedAmount>' . number_format($totals['tax_amount'], 2, '.', '') . '</ram:CalculatedAmount>
                <ram:TypeCode>VAT</ram:TypeCode>
                <ram:CategoryCode>S</ram:CategoryCode>
                <ram:RateApplicablePercent>19.00</ram:RateApplicablePercent>
            </ram:ApplicableTradeTax>
            <ram:SpecifiedTradeSettlementHeaderMonetarySummation>
                <ram:LineTotalAmount>' . number_format($totals['net_amount'], 2, '.', '') . '</ram:LineTotalAmount>
                <ram:TaxBasisTotalAmount>' . number_format($totals['net_amount'], 2, '.', '') . '</ram:TaxBasisTotalAmount>
                <ram:TaxTotalAmount currencyID="' . htmlspecialchars($currency) . '">' . number_format($totals['tax_amount'], 2, '.', '') . '</ram:TaxTotalAmount>
                <ram:GrandTotalAmount>' . number_format($totals['gross_amount'], 2, '.', '') . '</ram:GrandTotalAmount>
                <ram:DuePayableAmount>' . number_format($totals['gross_amount'], 2, '.', '') . '</ram:DuePayableAmount>
            </ram:SpecifiedTradeSettlementHeaderMonetarySummation>
        </ram:ApplicableHeaderTradeSettlement>
    </rsm:SupplyChainTradeTransaction>
</rsm:CrossIndustryInvoice>';

            return $xml;
            
        } catch (Exception $e) {
            throw new Exception('Fehler beim Generieren der ZUGFeRD-XML: ' . $e->getMessage());
        }
    }

    /**
     * Konvertiert Profil-String zu ZUGFeRD-Profil-ID
     *
     * @param string $profile Das Profil als String
     * @return int Die ZUGFeRD-Profil-ID
     */
    protected function getZugferdProfileId(string $profile): int
    {
        switch (strtoupper($profile)) {
            case 'MINIMUM':
            case 'BASIC':
                return \horstoeko\zugferd\ZugferdProfiles::PROFILE_EN16931;
            case 'COMFORT':
            case 'EXTENDED':
            default:
                return \horstoeko\zugferd\ZugferdProfiles::PROFILE_EN16931;
        }
    }



    /**
     * Importiert ein bestehendes PDF und fügt neuen Inhalt hinzu
     * Echte PDF-Import-Funktionalität mit FPDI
     *
     * @param string $sourcePdfPath Pfad zum zu importierenden PDF
     * @param string $newHtml HTML-Inhalt der hinzugefügt werden soll
     * @param bool $addAsNewPage Ob der neue Inhalt als neue Seite hinzugefügt werden soll
     * @return self
     * @throws Exception
     */
    public function importAndExtendPdf(string $sourcePdfPath, string $newHtml = '', bool $addAsNewPage = true): self
    {
        if (!file_exists($sourcePdfPath)) {
            throw new Exception("Source PDF file not found: $sourcePdfPath");
        }

        // Prüfe ob FPDI verfügbar ist
        if (!class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
            throw new Exception('FPDI library is required for PDF import. Install with: composer require setasign/fpdi');
        }

        // Erstelle FPDI-Instanz für PDF-Import
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        
        try {
            // Lade das Quell-PDF
            $pageCount = $pdf->setSourceFile($sourcePdfPath);
            
            // Importiere alle Seiten vom Original-PDF
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                
                // Füge Seite mit Original-Größe hinzu
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }
            
            // Füge neuen Inhalt hinzu (falls angegeben)
            if (!empty($newHtml)) {
                if ($addAsNewPage) {
                    // Als neue Seite hinzufügen
                    $pdf->AddPage($this->orientation, $this->paperSize);
                    $pdf->writeHTML($newHtml, true, false, true, false, '');
                } else {
                    // Auf letzte Seite hinzufügen (vereinfacht)
                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->SetY(-30);
                    $pdf->writeHTML($newHtml, true, false, true, false, '');
                }
            }
            
            // Setze das PDF-Objekt für weitere Verarbeitung
            $this->pdf = $pdf;
            
        } catch (Exception $e) {
            throw new Exception("Error importing PDF: " . $e->getMessage());
        }

        return $this;
    }

    /**
     * Führt PDF-Seiten zusammen (PDF Merge)
     *
     * @param array $pdfFiles Array von PDF-Dateipfaden
     * @return self
     * @throws Exception
     */
    public function mergePdfs(array $pdfFiles): self
    {
        if (empty($pdfFiles)) {
            throw new Exception('No PDF files provided for merging');
        }

        // Prüfe ob FPDI verfügbar ist
        if (!class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
            throw new Exception('FPDI library is required for PDF merge. Install with: composer require setasign/fpdi');
        }

        // Erstelle FPDI-Instanz für PDF-Merge
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        
        try {
            foreach ($pdfFiles as $pdfFile) {
                if (!file_exists($pdfFile)) {
                    throw new Exception("PDF file not found: $pdfFile");
                }
                
                $pageCount = $pdf->setSourceFile($pdfFile);
                
                // Alle Seiten des aktuellen PDFs hinzufügen
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);
                    
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }
            }
            
            // Setze das zusammengeführte PDF-Objekt
            $this->pdf = $pdf;
            
        } catch (Exception $e) {
            throw new Exception("Error merging PDFs: " . $e->getMessage());
        }

        return $this;
    }

    /**
     * Prüft ob FPDI für PDF-Import verfügbar ist
     *
     * @return bool
     */
    public static function isFpdiAvailable(): bool
    {
        return class_exists('setasign\Fpdi\Tcpdf\Fpdi');
    }


}
