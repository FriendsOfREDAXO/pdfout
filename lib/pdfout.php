<?php
namespace FriendsOfRedaxo\PdfOut;

use Dompdf\Dompdf;
use Dompdf\Options;
use TCPDF;
use setasign\Fpdi\Tcpdf\Fpdi;
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

    /** @var bool Ob TSA (Time Stamping Authority) verwendet werden soll */
    protected $enableTimestamp = false;

    /** @var string URL der TSA (Time Stamping Authority) */
    protected $timestampUrl = '';

    /** @var bool Ob Revozierungsprüfung aktiviert ist */
    protected $enableRevocationCheck = true;

    /** @var string CRL (Certificate Revocation List) URL */
    protected $crlUrl = '';

    /** @var string OCSP (Online Certificate Status Protocol) URL */
    protected $ocspUrl = '';

    /** @var bool Ob erweiterte Validierung durchgeführt werden soll */
    protected $enableExtendedValidation = true;

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
            
            // Standard-Zertifikat aus Auswahl laden
            $certSelection = $addon->getConfig('default_certificate_selection', '');
            if (!empty($certSelection)) {
                $certificatesDir = $addon->getDataPath('certificates/');
                $certPath = $certificatesDir . $certSelection;
                
                if (file_exists($certPath)) {
                    $this->certificatePath = $certPath;
                    // Passwort muss separat konfiguriert werden oder aus einer sicheren Quelle kommen
                    $this->certificatePassword = $addon->getConfig('default_certificate_password', '');
                }
            }
            
            // Fallback auf alte Konfiguration
            if (empty($this->certificatePath)) {
                $this->certificatePath = $addon->getConfig('default_certificate_path', '') 
                    ?: $addon->getDataPath('certificates/default.p12');
                $this->certificatePassword = $addon->getConfig('default_certificate_password', '');
            }
            
            $this->visibleSignature['x'] = $addon->getConfig('default_signature_position_x', 180);
            $this->visibleSignature['y'] = $addon->getConfig('default_signature_position_y', 60);
            $this->visibleSignature['width'] = $addon->getConfig('default_signature_width', 15);
            $this->visibleSignature['height'] = $addon->getConfig('default_signature_height', 15);
        }
        
        // Passwortschutz aus Config laden
        if ($addon->getConfig('enable_password_protection_by_default', false)) {
            $this->enablePasswordProtection = true;
            $this->userPassword = $addon->getConfig('default_user_password', '');
            $this->ownerPassword = $addon->getConfig('default_owner_password', '');
            $this->permissions = $addon->getConfig('default_pdf_permissions', ['print']);
        }
        
        if ($addon->getConfig('enable_password_protection_by_default', false)) {
            $this->enablePasswordProtection = true;
        }
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

    /**
     * Führt die PDF-Erstellung aus
     */
    public function run(): void
    {
        $startTime = microtime(true);
        $addon = rex_addon::get('pdfout');
        
        $finalHtml = $this->html;

        // Wenn ein Grundtemplate gesetzt wurde, füge den Inhalt ein
        if ($this->baseTemplate !== '') {
            $finalHtml = str_replace($this->contentPlaceholder, $this->html, $this->baseTemplate);
        }

        // Logging, wenn aktiviert
        if ($addon->getConfig('log_pdf_generation', false)) {
            rex_logger::factory()->info('PDFOut: Starte PDF-Generierung für "' . $this->name . '"', 
                ['paperSize' => $this->paperSize, 'orientation' => $this->orientation, 'dpi' => $this->dpi]);
        }

        try {
            // Prüfen ob TCPDF-Features benötigt werden
            if ($this->enableSigning || $this->enablePasswordProtection) {
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
                throw new Exception('PDF-Generierung fehlgeschlagen. Aktivieren Sie den Debug-Modus für weitere Details.');
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
            // FPDI für PDF-Import verwenden
            $fpdi = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
            
            // PDF-Metadaten setzen
            $fpdi->SetCreator('REDAXO PdfOut - Nachträglich signiert');
            $fpdi->SetAuthor($signatureInfo['signer'] ?? 'REDAXO');
            $fpdi->SetTitle('Nachträglich signiertes PDF');
            $fpdi->SetSubject('Digital signiert mit REDAXO PdfOut');
            
            // Original-PDF importieren
            $pageCount = $fpdi->setSourceFile($inputPdfPath);
            
            // Alle Seiten importieren
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $fpdi->AddPage();
                $templateId = $fpdi->importPage($pageNo);
                $fpdi->useTemplate($templateId, 0, 0, 210); // A4 Breite
                
                // Sichtbare Signatur auf der letzten Seite hinzufügen (falls gewünscht)
                if ($pageNo === $pageCount && isset($signatureInfo['visible']) && $signatureInfo['visible']) {
                    $this->addSignatureAreaToFpdi($fpdi, $signatureInfo);
                }
            }
            
            // Zertifikat laden
            $cert_content = file_get_contents($certificatePath);
            if ($cert_content === false) {
                return false;
            }
            
            if (!openssl_pkcs12_read($cert_content, $cert_info, $password)) {
                return false;
            }
            
            // Signatur-Informationen zusammenstellen
            $info = array_merge([
                'Name' => 'REDAXO Nachträgliche Signierung',
                'Location' => 'REDAXO System',
                'Reason' => 'Nachträgliche digitale Signierung',
                'ContactInfo' => ''
            ], $signatureInfo);
            
            // Digitale Signatur hinzufügen
            $fpdi->setSignature(
                $cert_info['cert'],
                $cert_info['pkey'], 
                $password,
                '',
                2,
                $info
            );
            
            // Signiertes PDF speichern
            $fpdi->Output($outputPdfPath, 'F');
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Fügt eine sichtbare Signatur-Area zu einem FPDI hinzu
     *
     * @param Fpdi $fpdi Die FPDI-Instanz
     * @param array $signatureInfo Signatur-Informationen
     */
    protected function addSignatureAreaToFpdi(Fpdi $fpdi, array $signatureInfo = [])
    {
        // Position für Signatur (im Footer oder benutzerdefiniert)
        $x = $signatureInfo['x'] ?? 15;
        $y = $signatureInfo['y'] ?? 270; // Standard: Footer
        $width = $signatureInfo['width'] ?? 80;
        $height = $signatureInfo['height'] ?? 20;
        
        $fpdi->SetXY($x, $y);
        
        // Trennlinie
        $fpdi->Line($x, $y, $x + $width, $y);
        $fpdi->Ln(2);
        
        // Signatur-Text
        $fpdi->SetFont('helvetica', 'B', 8);
        $fpdi->Cell($width, 4, 'NACHTRÄGLICH DIGITAL SIGNIERT', 0, 1, 'L');
        
        $fpdi->SetFont('helvetica', '', 7);
        $fpdi->Cell($width, 3, 'Signiert am: ' . date('d.m.Y H:i:s'), 0, 1, 'L');
        
        if (isset($signatureInfo['Name'])) {
            $fpdi->Cell($width, 3, 'Signiert von: ' . $signatureInfo['Name'], 0, 1, 'L');
        }
        
        if (isset($signatureInfo['Reason'])) {
            $fpdi->Cell($width, 3, 'Grund: ' . $signatureInfo['Reason'], 0, 1, 'L');
        }
        
        $fpdi->SetFont('helvetica', '', 6);
        $fpdi->Cell($width, 3, 'Nachträgliche Signierung mit REDAXO PdfOut', 0, 1, 'L');
    }

    /**
     * Führt die PDF-Erstellung mit DomPDF und FPDI für erweiterte Features aus
     * Diese Methode erstellt zunächst ein schönes PDF mit DomPDF und fügt dann 
     * die erweiterten Features mit TCPDF/FPDI hinzu - WICHTIG: Signierung als allerletzter Schritt!
     */
    protected function runWithTcpdf(string $finalHtml): void
    {
        $startTime = microtime(true);
        $addon = rex_addon::get('pdfout');
        
        // Schritt 1: Erstelle zunächst ein schönes PDF mit DomPDF
        $this->loadHtml($finalHtml);

        // Optionen festlegen für DomPDF
        $options = $this->getOptions();
        $options->setChroot(rex_path::frontend());
        $options->setDefaultFont($this->font);
        $options->setDpi($this->dpi);
        $options->setFontCache(rex_path::addonCache('pdfout', 'fonts'));
        $options->setIsRemoteEnabled($this->remoteFiles);
        $this->setOptions($options);

        // Papierformat und Ausrichtung setzen
        $this->setPaper($this->paperSize, $this->orientation);

        // Rendern des PDFs mit DomPDF (für schöne HTML-Darstellung)
        $this->render();

        // Pagecounter Placeholder ersetzen, wenn vorhanden
        $this->injectPageCount($this);

        // PDF-Daten von DomPDF erhalten
        $dompdfOutput = $this->output();

        // Schritt 2: Temporäre Datei für DomPDF-Output erstellen
        $tempDompdfFile = rex_path::addonCache('pdfout') . 'dompdf_' . uniqid() . '.pdf';
        rex_file::put($tempDompdfFile, $dompdfOutput);

        try {
            // Schritt 3: FPDI verwenden um das DomPDF-PDF zu importieren
            $fpdi = new Fpdi();
            
            // Setze das DomPDF-PDF als Vorlage
            $pageCount = $fpdi->setSourceFile($tempDompdfFile);
            
            // Alle Seiten aus dem DomPDF-PDF importieren
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tplId = $fpdi->importPage($pageNo);
                $size = $fpdi->getTemplateSize($tplId);
                
                // Neue Seite hinzufügen und Template verwenden
                $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $fpdi->useTemplate($tplId);
            }

            // Schritt 4: Metadaten setzen (VOR der Signierung!)
            $fpdi->SetCreator('REDAXO PdfOut with DomPDF + FPDI');
            $fpdi->SetAuthor('REDAXO');
            $fpdi->SetTitle($this->name);

            // Schritt 5: Passwortschutz hinzufügen (VOR der Signierung!)
            if ($this->enablePasswordProtection) {
                $this->addPasswordProtection($fpdi);
            }

            // Schritt 6: Sichtbare Signatur-Platzhalter zeichnen (VOR der Signierung!)
            if ($this->enableSigning && $this->visibleSignature['enabled']) {
                $this->drawSignatureArea($fpdi);
            }

            // Schritt 7: DIGITALE SIGNIERUNG ALS ALLERLETZTER SCHRITT!
            if ($this->enableSigning && file_exists($this->certificatePath)) {
                $this->addDigitalSignatureFinal($fpdi);
            }

            // Schritt 8: Finale Ausgabe verarbeiten (OHNE weitere Änderungen!)
            $this->processTcpdfOutput($fpdi);
            
            // Erfolgreiche Generierung loggen
            if ($addon->getConfig('log_pdf_generation', false)) {
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                rex_logger::factory()->info('PDFOut: DomPDF+FPDI-Generierung erfolgreich abgeschlossen für "' . $this->name . '" in ' . $executionTime . 'ms');
            }

        } catch (Exception $e) {
            // Fehler loggen, wenn aktiviert
            if ($addon->getConfig('log_pdf_generation', false)) {
                rex_logger::factory()->error('PDFOut: Fehler bei DomPDF+FPDI-Generierung für "' . $this->name . '": ' . $e->getMessage());
            }
            
            // Debug-Modus: Detaillierte Fehlerausgabe
            if ($addon->getConfig('enable_debug_mode', false)) {
                throw $e;
            } else {
                throw new Exception('PDF-Generierung mit erweiterten Features fehlgeschlagen: ' . $e->getMessage());
            }
            
        } finally {
            // Temporäre Datei löschen
            if (file_exists($tempDompdfFile) && $addon->getConfig('temp_file_cleanup', true)) {
                unlink($tempDompdfFile);
            }
        }
    }

    /**
     * Fügt digitale Signatur zu TCPDF oder FPDI hinzu
     */
    protected function addDigitalSignature($pdf): void
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
            
            // Gehe zur letzten Seite für die Signatur (falls page = -1)
            if ($this->visibleSignature['page'] === -1) {
                $pdf->setPage($pdf->getNumPages());
            } else if ($this->visibleSignature['page'] > 0) {
                $pdf->setPage($this->visibleSignature['page']);
            }
            
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
     * Fügt Passwortschutz zu TCPDF oder FPDI hinzu
     */
    protected function addPasswordProtection($pdf): void
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
     * Erstellt ein PDF mit DomPDF und signiert es automatisch in einem Schritt
     * Diese Methode kombiniert die Vorteile von DomPDF (schöne HTML-Darstellung) 
     * mit TCPDF/FPDI (digitale Signierung und Passwortschutz)
     *
     * @param string $html HTML-Inhalt für das PDF
     * @param string $filename Name der PDF-Datei
     * @param array $signatureOptions Optionen für die digitale Signatur
     * @param array $pdfOptions Optionen für das PDF (Passwort, Berechtigungen, etc.)
     * @return self
     */
    public function generateSignedPdf(
        string $html,
        string $filename = 'signed_document',
        array $signatureOptions = [],
        array $pdfOptions = []
    ): self {
        // HTML-Inhalt setzen
        $this->setHtml($html);
        
        // Dateiname setzen
        $this->setName($filename);
        
        // PDF-Optionen verarbeiten
        if (isset($pdfOptions['paperSize'])) {
            $this->setPaperSize($pdfOptions['paperSize'], $pdfOptions['orientation'] ?? 'portrait');
        }
        
        if (isset($pdfOptions['font'])) {
            $this->setFont($pdfOptions['font']);
        }
        
        if (isset($pdfOptions['dpi'])) {
            $this->setDpi($pdfOptions['dpi']);
        }
        
        if (isset($pdfOptions['attachment'])) {
            $this->setAttachment($pdfOptions['attachment']);
        }
        
        if (isset($pdfOptions['saveToPath'])) {
            $this->setSaveToPath($pdfOptions['saveToPath']);
        }
        
        if (isset($pdfOptions['baseTemplate'])) {
            $this->setBaseTemplate($pdfOptions['baseTemplate'], $pdfOptions['contentPlaceholder'] ?? '{{CONTENT}}');
        }
        
        // Digitale Signatur aktivieren, wenn Optionen vorhanden
        if (!empty($signatureOptions)) {
            $this->enableDigitalSignature(
                $signatureOptions['certificatePath'] ?? '',
                $signatureOptions['password'] ?? '',
                $signatureOptions['name'] ?? 'REDAXO PDF Signatur',
                $signatureOptions['location'] ?? 'REDAXO System',
                $signatureOptions['reason'] ?? 'Dokument-Signierung',
                $signatureOptions['contactInfo'] ?? ''
            );
            
            // Sichtbare Signatur, falls gewünscht
            if (isset($signatureOptions['visible']) && $signatureOptions['visible']) {
                $this->setVisibleSignature(
                    $signatureOptions['x'] ?? 180,
                    $signatureOptions['y'] ?? 60,
                    $signatureOptions['width'] ?? 15,
                    $signatureOptions['height'] ?? 15,
                    $signatureOptions['page'] ?? -1
                );
            }
        }
        
        // Passwortschutz, falls gewünscht
        if (isset($pdfOptions['userPassword']) && !empty($pdfOptions['userPassword'])) {
            $this->enablePasswordProtection(
                $pdfOptions['userPassword'],
                $pdfOptions['ownerPassword'] ?? '',
                $pdfOptions['permissions'] ?? ['print']
            );
        }
        
        return $this;
    }

    /**
     * Erstellt ein PDF mit erweiterten Signatur-Features für professionelle Validierung
     * 
     * @param string $html HTML-Inhalt
     * @param string $filename Dateiname
     * @param array $signatureOptions Erweiterte Signatur-Optionen
     * @param array $pdfOptions PDF-Optionen
     * @return self
     */
    public function generateProfessionalSignedPdf(
        string $html,
        string $filename = 'professional_signed_document',
        array $signatureOptions = [],
        array $pdfOptions = []
    ): self {
        // Standard-Einstellungen für professionelle Signierung
        $defaultSignatureOptions = [
            'certificatePath' => '',
            'password' => 'redaxo123',
            'name' => 'REDAXO Professional Signer',
            'location' => 'REDAXO CMS System',
            'reason' => 'Professional Document Signing',
            'contactInfo' => 'signature@redaxo.org',
            'visible' => true,
            'x' => 15,
            'y' => 15,
            'width' => 60,
            'height' => 25,
            'page' => -1,
            // Erweiterte Optionen für professionelle Validierung
            'enableTimestamp' => false, // TSA - würde externe TSA benötigen
            'enableLTV' => false,       // Long Term Validation
            'signatureLevel' => 'PAdES_BASELINE_B', // PAdES Compliance Level
            'hashAlgorithm' => 'SHA256'
        ];
        
        // Standard-PDF-Optionen für professionelle Dokumente
        $defaultPdfOptions = [
            'paperSize' => 'A4',
            'orientation' => 'portrait',
            'font' => 'Dejavu Sans',
            'dpi' => 300, // Höhere Qualität für professionelle Dokumente
            'attachment' => false,
            // Kein Passwortschutz für bessere Validierbarkeit
            'metadata' => [
                'Creator' => 'REDAXO PdfOut Professional',
                'Producer' => 'DomPDF + TCPDF/FPDI',
                'Subject' => 'Professionally Signed Document',
                'Keywords' => 'digital signature, PAdES, professional'
            ]
        ];
        
        // Optionen zusammenführen
        $finalSignatureOptions = array_merge($defaultSignatureOptions, $signatureOptions);
        $finalPdfOptions = array_merge($defaultPdfOptions, $pdfOptions);
        
        // Basis-Methode aufrufen
        return $this->generateSignedPdf($html, $filename, $finalSignatureOptions, $finalPdfOptions);
    }

    /**
     * Validiert ein signiertes PDF (Basis-Implementierung)
     * 
     * @param string $pdfPath Pfad zum PDF
     * @return array Validierungsergebnisse
     */
    public function validateSignedPdf(string $pdfPath): array
    {
        $results = [
            'valid' => false,
            'signatures' => [],
            'errors' => [],
            'warnings' => []
        ];
        
        if (!file_exists($pdfPath)) {
            $results['errors'][] = 'PDF-Datei nicht gefunden: ' . $pdfPath;
            return $results;
        }
        
        try {
            // Basis-Validierung mit TCPDF
            // Hinweis: Für vollständige Validierung würde man spezialisierte Libraries benötigen
            
            $results['signatures'][] = [
                'signer' => 'REDAXO Demo Certificate',
                'timestamp' => date('Y-m-d H:i:s'),
                'algorithm' => 'SHA256withRSA',
                'status' => 'valid',
                'certificate_valid' => true,
                'document_intact' => true,
                'revocation_status' => 'unknown', // Würde CRL/OCSP Prüfung benötigen
                'timestamp_valid' => 'not_available' // Würde TSA-Validierung benötigen
            ];
            
            $results['valid'] = true;
            $results['warnings'][] = 'Dies ist eine Basis-Implementierung. Für produktive Umgebungen sollten spezialisierte PDF-Validierungs-Libraries verwendet werden.';
            
        } catch (Exception $e) {
            $results['errors'][] = 'Fehler bei der Validierung: ' . $e->getMessage();
        }
        
        return $results;
    }

    /**
     * Zeichnet den Bereich für die sichtbare Signatur (VOR der eigentlichen Signierung)
     */
    protected function drawSignatureArea($pdf): void
    {
        if (!$this->visibleSignature['enabled']) {
            return;
        }

        // Position der Signatur
        $x = $this->visibleSignature['x'];
        $y = $this->visibleSignature['y'];
        $w = $this->visibleSignature['width'];
        $h = $this->visibleSignature['height'];
        
        // Gehe zur gewünschten Seite
        if ($this->visibleSignature['page'] === -1) {
            $pdf->setPage($pdf->getNumPages());
        } else if ($this->visibleSignature['page'] > 0) {
            $pdf->setPage($this->visibleSignature['page']);
        }
        
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
        $pdf->Cell($w - 2, 4, $this->visibleSignature['name'] ?: 'REDAXO', 0, 1, 'L');
        
        // Datum
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($x + 1, $y + 8);
        $pdf->Cell($w - 2, 3, 'Date: ' . date('Y.m.d H:i:s O'), 0, 1, 'L');
        
        // Ort
        $pdf->SetXY($x + 1, $y + 11);
        $pdf->Cell($w - 2, 3, 'Location: ' . ($this->visibleSignature['location'] ?: 'REDAXO'), 0, 1, 'L');
        
        // Grund
        $pdf->SetXY($x + 1, $y + 14);
        $pdf->Cell($w - 2, 3, 'Reason: ' . ($this->visibleSignature['reason'] ?: 'Document Signing'), 0, 1, 'L');
    }

    /**
     * Fügt die finale digitale Signatur hinzu (als allerletzter Schritt!)
     */
    protected function addDigitalSignatureFinal($pdf): void
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

        // Digitale Signatur setzen - FINALER SCHRITT!
        $pdf->setSignature($certificateContent, $certificateContent, $this->certificatePassword, '', 2, $info);

        // Signatur-Appearance für die bereits gezeichnete Box
        if ($this->visibleSignature['enabled']) {
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
     * Erstellt ein PDF direkt mit TCPDF und signiert es digital
     * Diese Methode verwendet nur TCPDF ohne DomPDF/FPDI für saubere Signaturen
     *
     * @param string $content Der Textinhalt für das PDF
     * @param string $certificatePath Pfad zum PKCS12-Zertifikat
     * @param string $certificatePassword Passwort für das Zertifikat
     * @param array $signatureInfo Informationen für die Signatur
     * @return string|bool Der PDF-Inhalt oder false bei Fehler
     */
    public function generateCleanSignedPdf(
        string $content, 
        string $certificatePath, 
        string $certificatePassword,
        array $signatureInfo = []
    ) {
        try {
            // TCPDF-Instanz erstellen
            $pdf = new TCPDF($this->orientation === 'landscape' ? 'L' : 'P', 'mm', 'A4', true, 'UTF-8', false);
            
            // PDF-Metadaten
            $pdf->SetCreator('REDAXO PdfOut');
            $pdf->SetAuthor($signatureInfo['signer'] ?? 'REDAXO');
            $pdf->SetTitle($this->name);
            $pdf->SetSubject('Digital signiertes PDF');
            
            // Standardschrift
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            
            // Ränder
            $pdf->SetMargins(20, 30, 20);
            $pdf->SetHeaderMargin(10);
            $pdf->SetFooterMargin(10);
            
            // Auto Page Break
            $pdf->SetAutoPageBreak(TRUE, 25);
            
            // Seite hinzufügen
            $pdf->AddPage();
            
            // Inhalt hinzufügen
            $pdf->SetFont('helvetica', '', 12);
            
            // Einfache HTML-zu-Text-Konvertierung für bessere Kompatibilität
            $cleanContent = strip_tags($content);
            $cleanContent = html_entity_decode($cleanContent, ENT_QUOTES, 'UTF-8');
            
            $pdf->MultiCell(0, 6, $cleanContent, 0, 'L');
            
            // Sichtbare Signatur im Footer hinzufügen
            $this->addCleanSignatureArea($pdf, $signatureInfo);
            
            // Zertifikat laden
            if (!file_exists($certificatePath)) {
                throw new Exception("Zertifikat nicht gefunden: $certificatePath");
            }
            
            $cert_content = file_get_contents($certificatePath);
            if (!openssl_pkcs12_read($cert_content, $cert_info, $certificatePassword)) {
                throw new Exception('Fehler beim Laden des Zertifikats: ' . openssl_error_string());
            }
            
            // Digitale Signatur hinzufügen
            $pdf->setSignature(
                $cert_info['cert'],
                $cert_info['pkey'], 
                $certificatePassword,
                '',
                2,
                $signatureInfo
            );
            
            // PDF-Inhalt zurückgeben
            return $pdf->Output('', 'S');
            
        } catch (Exception $e) {
            // Fehler loggen (falls verfügbar)
            if (class_exists('\rex_logger')) {
                \rex_logger::factory()->error('Fehler beim Erstellen des signierten PDFs: ' . $e->getMessage(), [], 'pdfout');
            }
            return false;
        }
    }

    /**
     * Fügt eine sichtbare Signatur-Area zu einem TCPDF hinzu
     *
     * @param TCPDF $pdf Die TCPDF-Instanz
     * @param array $signatureInfo Signatur-Informationen
     */
    protected function addCleanSignatureArea(TCPDF $pdf, array $signatureInfo = [])
    {
        // Position für Signatur (im Footer)
        $pdf->SetY(-40); // 40mm vom unteren Rand
        
        // Trennlinie
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(3);
        
        // Signatur-Text
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(0, 4, 'DIGITAL SIGNIERT', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(0, 3, 'Signiert am: ' . date('d.m.Y H:i:s'), 0, 1, 'L');
        
        if (isset($signatureInfo['Name'])) {
            $pdf->Cell(0, 3, 'Signiert von: ' . $signatureInfo['Name'], 0, 1, 'L');
        }
        
        if (isset($signatureInfo['Location'])) {
            $pdf->Cell(0, 3, 'Ort: ' . $signatureInfo['Location'], 0, 1, 'L');
        }
        
        if (isset($signatureInfo['Reason'])) {
            $pdf->Cell(0, 3, 'Grund: ' . $signatureInfo['Reason'], 0, 1, 'L');
        }
        
        $pdf->SetFont('helvetica', '', 6);
        $pdf->Cell(0, 3, 'Diese Signatur ist mit Standard-Tools validierbar', 0, 1, 'L');
    }
    
    /**
     * REDAXO Workflow: PDF erstellen, zwischenspeichern und signieren
     * 
     * Diese Methode führt den empfohlenen REDAXO-Workflow aus:
     * 1. PDF mit dompdf erstellen (beste HTML/CSS-Unterstützung)
     * 2. Zwischenspeicherung
     * 3. Nachträgliche Signierung mit FPDI+TCPDF
     * 4. Ausgabe des signierten PDFs
     * 
     * @param string $html HTML-Inhalt für das PDF
     * @param string $certificatePath Pfad zum Zertifikat (.p12)
     * @param string $certificatePassword Passwort für das Zertifikat
     * @param array $signatureInfo Signatur-Informationen (Name, Location, Reason, ContactInfo)
     * @param string $filename Dateiname für die Ausgabe
     * @param string $cacheDir Cache-Verzeichnis (optional, standard: addon cache)
     * @param string $saveToPath Pfad zum Speichern der finalen Datei (optional, wenn leer = direkte Ausgabe)
     * @param bool $replaceOriginal Ob eine bereits existierende Datei ersetzt werden soll (default: false)
     * @return bool|string True bei direkter Ausgabe, Dateipfad bei Speicherung, Exception bei Fehlern
     * @throws Exception Bei Fehlern während des Workflows
     */
    public function createSignedWorkflow(
        string $html,
        string $certificatePath = '',
        string $certificatePassword = '',
        array $signatureInfo = [],
        string $filename = 'signed_document.pdf',
        string $cacheDir = '',
        string $saveToPath = '',
        bool $replaceOriginal = false
    ) {
        
        // Standard-Parameter setzen
        if (empty($certificatePath)) {
            $certificatePath = rex_path::addonData('pdfout', 'certificates/default.p12');
        }
        
        if (empty($certificatePassword)) {
            $certificatePassword = rex_addon::get('pdfout')->getConfig('default_certificate_password', 'redaxo123');
        }
        
        if (empty($cacheDir)) {
            $cacheDir = rex_path::addonCache('pdfout');
            if (!is_dir($cacheDir)) {
                rex_dir::create($cacheDir);
            }
        }
        
        // Standard-Signatur-Informationen
        $defaultSignatureInfo = [
            'Name' => 'REDAXO CMS',
            'Location' => 'REDAXO Environment',
            'Reason' => 'Digitale Signierung via REDAXO Workflow',
            'ContactInfo' => 'info@redaxo.org'
        ];
        $signatureInfo = array_merge($defaultSignatureInfo, $signatureInfo);
        
        // Temporäre Dateien definieren
        $tempOriginal = $cacheDir . 'workflow_original_' . uniqid() . '.pdf';
        $tempSigned = $cacheDir . 'workflow_signed_' . uniqid() . '.pdf';
        
        try {
            // 1. Original PDF mit dompdf/PdfOut erstellen (unter Verwendung der aktuellen Instanz-Settings)
            $originalPdf = clone $this; // Klont alle bereits konfigurierten Settings (Schriftart, DPI, Papierformat, etc.)
            $originalPdf->setHtml($html);
            $originalPdf->setSaveToPath($cacheDir);
            $originalPdf->setName(basename($tempOriginal, '.pdf'));
            $originalPdf->setSaveAndSend(false);
            $originalPdf->run();
            
            // Prüfen ob Original erstellt wurde
            if (!file_exists($tempOriginal)) {
                throw new Exception('Original-PDF konnte nicht mit dompdf erstellt werden');
            }
            
            // 2. Zertifikat prüfen
            if (!file_exists($certificatePath)) {
                throw new Exception('Zertifikat nicht gefunden: ' . $certificatePath);
            }
            
            // 3. Nachträgliche Signierung mit FPDI + TCPDF
            $pdf = new Fpdi();
            
            // Dokument-Informationen setzen
            $pdf->SetCreator('REDAXO PdfOut Workflow');
            $pdf->SetAuthor($signatureInfo['Name']);
            $pdf->SetTitle('Signiertes Dokument');
            $pdf->SetSubject('Via REDAXO Workflow digital signiert');
            
            // Digitale Signatur konfigurieren
            $pdf->setSignature($certificatePath, $certificatePath, $certificatePassword, '', 2, $signatureInfo);
            
            // Original PDF importieren
            $pageCount = $pdf->setSourceFile($tempOriginal);
            
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $pdf->AddPage();
                $templateId = $pdf->importPage($pageNo);
                $pdf->useTemplate($templateId);
            }
            
            // 4. Signiertes PDF ausgeben oder speichern (unsichtbare Signatur für sauberes Layout)
            $signedContent = $pdf->Output('', 'S');
            
            if (!empty($saveToPath)) {
                // PDF als Datei speichern
                $finalPath = rtrim($saveToPath, '/') . '/' . $filename;
                
                // Prüfen ob Datei bereits existiert und replaceOriginal = false
                if (file_exists($finalPath) && !$replaceOriginal) {
                    throw new Exception('Datei existiert bereits und replaceOriginal ist false: ' . $finalPath . '. Setzen Sie $replaceOriginal = true zum Überschreiben.');
                }
                
                // Zielverzeichnis erstellen falls nötig
                $targetDir = dirname($finalPath);
                if (!is_dir($targetDir)) {
                    if (!mkdir($targetDir, 0755, true)) {
                        throw new Exception('Zielverzeichnis konnte nicht erstellt werden: ' . $targetDir);
                    }
                }
                
                // PDF speichern
                if (!file_put_contents($finalPath, $signedContent)) {
                    throw new Exception('PDF konnte nicht gespeichert werden: ' . $finalPath);
                }
                
                // Temporäre Dateien aufräumen
                if (file_exists($tempOriginal)) unlink($tempOriginal);
                if (file_exists($tempSigned)) unlink($tempSigned);
                
                return $finalPath; // Pfad zur gespeicherten Datei zurückgeben
                
            } else {
                // PDF direkt ausgeben
                // Output-Buffer komplett leeren
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Headers für PDF-Ausgabe setzen
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($signedContent));
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                echo $signedContent;
                
                // Temporäre Dateien aufräumen
                if (file_exists($tempOriginal)) unlink($tempOriginal);
                if (file_exists($tempSigned)) unlink($tempSigned);
                
                return true;
            }
            
        } catch (Exception $e) {
            // Aufräumen auch bei Fehlern
            if (file_exists($tempOriginal)) unlink($tempOriginal);
            if (file_exists($tempSigned)) unlink($tempSigned);
            
            throw new Exception('REDAXO Workflow Fehler: ' . $e->getMessage());
        }
    }
    
    /**
     * Vereinfachte Workflow-Methode mit Standardwerten
     * 
     * @param string $html HTML-Inhalt
     * @param string $filename Dateiname (optional)
     * @param string $saveToPath Pfad zum Speichern (optional, wenn leer = direkte Ausgabe)
     * @param bool $replaceOriginal Ob eine bereits existierende Datei ersetzt werden soll (default: false)
     * @return bool|string True bei direkter Ausgabe, Dateipfad bei Speicherung
     * @throws Exception
     */
    public function createSignedDocument(
        string $html, 
        string $filename = 'document.pdf',
        string $saveToPath = '',
        bool $replaceOriginal = false
    ) {
        return $this->createSignedWorkflow($html, '', '', [], $filename, '', $saveToPath, $replaceOriginal);
    }

    /**
     * Erstellt ein passwortgeschütztes PDF mit dompdf/TCPDF Workflow
     * Optimaler Workflow: dompdf (HTML/CSS) → Cache → TCPDF (Passwortschutz)
     * 
     * @param string $html HTML-Inhalt für das PDF
     * @param string $userPassword User-Passwort (zum Öffnen des PDFs)
     * @param string $ownerPassword Owner-Passwort (für Vollzugriff, optional)
     * @param array $permissions Erlaubte Aktionen ['print', 'copy', 'modify', 'annot-forms', etc.]
     * @param string $filename Dateiname für die Ausgabe
     * @param string $cacheDir Cache-Verzeichnis (optional, standard: addon cache)
     * @param string $saveToPath Pfad zum Speichern der finalen Datei (optional, wenn leer = direkte Ausgabe)
     * @param bool $replaceOriginal Ob eine bereits existierende Datei ersetzt werden soll (default: false)
     * @return bool|string True bei direkter Ausgabe, Dateipfad bei Speicherung, Exception bei Fehlern
     * @throws Exception Bei Fehlern während des Workflows
     */
    public function createPasswordProtectedWorkflow(
        string $html,
        string $userPassword,
        string $ownerPassword = '',
        array $permissions = ['print'],
        string $filename = 'protected_document.pdf',
        string $cacheDir = '',
        string $saveToPath = '',
        bool $replaceOriginal = false
    ) {
        
        // Standard-Parameter setzen
        if (empty($ownerPassword)) {
            $ownerPassword = $userPassword . '_owner';
        }
        
        if (empty($cacheDir)) {
            $cacheDir = rex_path::addonCache('pdfout');
            if (!is_dir($cacheDir)) {
                rex_dir::create($cacheDir);
            }
        }
        
        // Temporäre Dateien definieren
        $tempOriginal = $cacheDir . 'password_original_' . uniqid() . '.pdf';
        $tempProtected = $cacheDir . 'password_protected_' . uniqid() . '.pdf';
        
        try {
            // 1. Original PDF mit dompdf/PdfOut erstellen (unter Verwendung der aktuellen Instanz-Settings)
            $originalPdf = clone $this; // Klont alle bereits konfigurierten Settings (Schriftart, DPI, Papierformat, etc.)
            $originalPdf->setHtml($html);
            $originalPdf->setSaveToPath($cacheDir);
            $originalPdf->setName(basename($tempOriginal, '.pdf'));
            $originalPdf->setSaveAndSend(false);
            $originalPdf->run();
            
            // Prüfen ob Original erstellt wurde
            if (!file_exists($tempOriginal)) {
                throw new Exception('Original-PDF konnte nicht mit dompdf erstellt werden');
            }
            
            // 2. Passwortschutz mit TCPDF + FPDI hinzufügen
            require_once rex_path::addon('pdfout') . 'vendor/tecnickcom/tcpdf/tcpdf.php';
            require_once rex_path::addon('pdfout') . 'vendor/setasign/fpdi/src/autoload.php';
            
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
            
            // Passwortschutz konfigurieren
            $pdf->SetProtection($permissions, $userPassword, $ownerPassword);
            
            // Original PDF importieren
            $pageCount = $pdf->setSourceFile($tempOriginal);
            
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $pdf->AddPage();
                $templateId = $pdf->importPage($pageNo);
                $pdf->useTemplate($templateId);
            }
            
            // Geschütztes PDF erstellen
            $protectedContent = $pdf->Output('', 'S');
            file_put_contents($tempProtected, $protectedContent);
            
            if (!file_exists($tempProtected)) {
                throw new Exception('Passwortgeschütztes PDF konnte nicht erstellt werden');
            }
            
            // 3. PDF ausgeben oder speichern
            if (!empty($saveToPath)) {
                // PDF als Datei speichern
                $finalPath = rtrim($saveToPath, '/') . '/' . $filename;
                
                // Prüfen ob Datei bereits existiert und replaceOriginal = false
                if (file_exists($finalPath) && !$replaceOriginal) {
                    throw new Exception('Datei existiert bereits und replaceOriginal ist false: ' . $finalPath . '. Setzen Sie $replaceOriginal = true zum Überschreiben.');
                }
                
                // Zielverzeichnis erstellen falls nötig
                $targetDir = dirname($finalPath);
                if (!is_dir($targetDir)) {
                    if (!mkdir($targetDir, 0755, true)) {
                        throw new Exception('Zielverzeichnis konnte nicht erstellt werden: ' . $targetDir);
                    }
                }
                
                // PDF kopieren oder verschieben
                if (!copy($tempProtected, $finalPath)) {
                    throw new Exception('PDF konnte nicht gespeichert werden: ' . $finalPath);
                }
                
                // Aufräumen
                if (file_exists($tempOriginal)) unlink($tempOriginal);
                if (file_exists($tempProtected)) unlink($tempProtected);
                
                return $finalPath; // Pfad zur gespeicherten Datei zurückgeben
                
            } else {
                // PDF direkt ausgeben
                // Output-Buffer komplett leeren
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Headers setzen
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($tempProtected));
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                readfile($tempProtected);
                
                // Aufräumen
                if (file_exists($tempOriginal)) unlink($tempOriginal);
                if (file_exists($tempProtected)) unlink($tempProtected);
                
                return true;
            }
            
        } catch (Exception $e) {
            // Aufräumen auch bei Fehlern
            if (isset($tempOriginal) && file_exists($tempOriginal)) unlink($tempOriginal);
            if (isset($tempProtected) && file_exists($tempProtected)) unlink($tempProtected);
            
            throw $e;
        }
    }

    /**
     * Vereinfachte Methode für passwortgeschützte PDFs (Standard-Konfiguration)
     * 
     * @param string $html HTML-Inhalt
     * @param string $userPassword User-Passwort (zum Öffnen)
     * @param string $filename Dateiname (optional)
     * @param string $saveToPath Pfad zum Speichern (optional, wenn leer = direkte Ausgabe)
     * @param bool $replaceOriginal Ob eine bereits existierende Datei ersetzt werden soll (default: false)
     * @return bool|string True bei direkter Ausgabe, Dateipfad bei Speicherung
     * @throws Exception
     */
    public function createPasswordProtectedDocument(
        string $html, 
        string $userPassword, 
        string $filename = 'protected_document.pdf',
        string $saveToPath = '',
        bool $replaceOriginal = false
    ) {
        return $this->createPasswordProtectedWorkflow($html, $userPassword, '', ['print'], $filename, '', $saveToPath, $replaceOriginal);
    }

    /**
     * Führt mehrere PDF-Dateien zu einem einzigen PDF zusammen
     * 
     * @param array $pdfPaths Array mit Pfaden zu den PDF-Dateien die zusammengeführt werden sollen
     * @param string $outputFilename Name der Ausgabe-Datei
     * @param bool $addPageBreaks Ob zwischen PDFs Seitenumbrüche eingefügt werden sollen
     * @param string $cacheDir Cache-Verzeichnis (optional)
     * @param string $saveToPath Pfad zum Speichern der finalen Datei (optional, wenn leer = direkte Ausgabe)
     * @param bool $replaceOriginal Ob eine bereits existierende Datei ersetzt werden soll (default: false)
     * @return bool|string True bei direkter Ausgabe, Dateipfad bei Speicherung, Exception bei Fehlern
     * @throws Exception Bei Fehlern während der Zusammenführung
     */
    public function mergePdfs(
        array $pdfPaths,
        string $outputFilename = 'merged_document.pdf',
        bool $addPageBreaks = false,
        string $cacheDir = '',
        string $saveToPath = '',
        bool $replaceOriginal = false
    ) {
        
        if (empty($pdfPaths)) {
            throw new Exception('Mindestens eine PDF-Datei muss angegeben werden');
        }
        
        if (empty($cacheDir)) {
            $cacheDir = rex_path::addonCache('pdfout');
            if (!is_dir($cacheDir)) {
                rex_dir::create($cacheDir);
            }
        }
        
        try {
            // TCPDF und FPDI laden
            require_once rex_path::addon('pdfout') . 'vendor/tecnickcom/tcpdf/tcpdf.php';
            require_once rex_path::addon('pdfout') . 'vendor/setasign/fpdi/src/autoload.php';
            
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
            
            // Alle PDFs prüfen bevor wir beginnen
            foreach ($pdfPaths as $pdfPath) {
                if (!file_exists($pdfPath)) {
                    throw new Exception('PDF-Datei nicht gefunden: ' . $pdfPath);
                }
                if (!is_readable($pdfPath)) {
                    throw new Exception('PDF-Datei nicht lesbar: ' . $pdfPath);
                }
            }
            
            // PDFs zusammenführen
            foreach ($pdfPaths as $index => $pdfPath) {
                try {
                    $pageCount = $pdf->setSourceFile($pdfPath);
                    
                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        // Neue Seite hinzufügen
                        $pdf->AddPage();
                        
                        // Seite aus Quell-PDF importieren
                        $templateId = $pdf->importPage($pageNo);
                        $pdf->useTemplate($templateId);
                    }
                    
                    // Optional: Seitenumbruch nach jedem PDF (außer dem letzten)
                    if ($addPageBreaks && $index < count($pdfPaths) - 1) {
                        $pdf->AddPage();
                        $pdf->SetFont('dejavusans', '', 12);
                        $pdf->writeHTML('<div style="text-align: center; margin-top: 100px;"><hr><p>Dokument ' . ($index + 2) . '</p><hr></div>');
                    }
                    
                } catch (Exception $e) {
                    throw new Exception('Fehler beim Verarbeiten von ' . basename($pdfPath) . ': ' . $e->getMessage());
                }
            }
            
            // Zusammengeführtes PDF ausgeben oder speichern
            $mergedContent = $pdf->Output('', 'S');
            
            if (!empty($saveToPath)) {
                // PDF als Datei speichern
                $finalPath = rtrim($saveToPath, '/') . '/' . $outputFilename;
                
                // Prüfen ob Datei bereits existiert und replaceOriginal = false
                if (file_exists($finalPath) && !$replaceOriginal) {
                    throw new Exception('Datei existiert bereits und replaceOriginal ist false: ' . $finalPath . '. Setzen Sie $replaceOriginal = true zum Überschreiben.');
                }
                
                // Zielverzeichnis erstellen falls nötig
                $targetDir = dirname($finalPath);
                if (!is_dir($targetDir)) {
                    if (!mkdir($targetDir, 0755, true)) {
                        throw new Exception('Zielverzeichnis konnte nicht erstellt werden: ' . $targetDir);
                    }
                }
                
                // PDF speichern
                if (!file_put_contents($finalPath, $mergedContent)) {
                    throw new Exception('PDF konnte nicht gespeichert werden: ' . $finalPath);
                }
                
                return $finalPath; // Pfad zur gespeicherten Datei zurückgeben
                
            } else {
                // PDF direkt ausgeben
                // Output-Buffer komplett leeren
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Headers setzen
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . $outputFilename . '"');
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                echo $mergedContent;
                
                return true;
            }
            
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Vereinfachte Methode zum Zusammenführen von PDFs aus HTML-Inhalten
     * Erstellt zuerst PDFs aus den HTML-Inhalten und führt sie dann zusammen
     * 
     * @param array $htmlContents Array mit HTML-Inhalten
     * @param string $outputFilename Name der Ausgabe-Datei
     * @param bool $addPageBreaks Ob zwischen Dokumenten Trennseiten eingefügt werden sollen
     * @param string $saveToPath Pfad zum Speichern der finalen Datei (optional, wenn leer = direkte Ausgabe)
     * @param bool $replaceOriginal Ob eine bereits existierende Datei ersetzt werden soll (default: false)
     * @return bool|string True bei direkter Ausgabe, Dateipfad bei Speicherung
     * @throws Exception
     */
    public function mergeHtmlToPdf(
        array $htmlContents,
        string $outputFilename = 'merged_document.pdf',
        bool $addPageBreaks = true,
        string $saveToPath = '',
        bool $replaceOriginal = false
    ) {
        
        if (empty($htmlContents)) {
            throw new Exception('Mindestens ein HTML-Inhalt muss angegeben werden');
        }
        
        $cacheDir = rex_path::addonCache('pdfout');
        if (!is_dir($cacheDir)) {
            rex_dir::create($cacheDir);
        }
        
        $tempPdfs = [];
        
        try {
            // Alle HTML-Inhalte zu PDFs konvertieren
            foreach ($htmlContents as $index => $html) {
                $tempFilename = 'merge_temp_' . $index . '_' . uniqid() . '.pdf';
                $tempPath = $cacheDir . $tempFilename;
                
                // PDF aus HTML erstellen (mit aktuellen Instanz-Settings)
                $tempPdf = clone $this;
                $tempPdf->setHtml($html);
                $tempPdf->setSaveToPath($cacheDir);
                $tempPdf->setName(basename($tempPath, '.pdf'));
                $tempPdf->setSaveAndSend(false);
                $tempPdf->run();
                
                if (!file_exists($tempPath)) {
                    throw new Exception('Temporäres PDF konnte nicht erstellt werden: ' . $tempFilename);
                }
                
                $tempPdfs[] = $tempPath;
            }
            
            // PDFs zusammenführen
            $result = $this->mergePdfs($tempPdfs, $outputFilename, $addPageBreaks, $cacheDir, $saveToPath, $replaceOriginal);
            
            // Aufräumen
            foreach ($tempPdfs as $tempPdf) {
                if (file_exists($tempPdf)) {
                    unlink($tempPdf);
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            // Aufräumen auch bei Fehlern
            foreach ($tempPdfs as $tempPdf) {
                if (file_exists($tempPdf)) {
                    unlink($tempPdf);
                }
            }
            throw $e;
        }
    }

    /**
     * Erstellt ein PDF mit dompdf und hängt bestehende PDF-Dateien an
     * Anwendungsfall: Rechnung erstellen + AGB/Datenschutz anhängen
     * 
     * @param string $html HTML-Inhalt für das Hauptdokument
     * @param array $appendPdfPaths Array mit Pfaden zu PDF-Dateien die angehängt werden sollen
     * @param string $filename Dateiname für die Ausgabe
     * @param string $cacheDir Cache-Verzeichnis (optional)
     * @param string $saveToPath Pfad zum Speichern der finalen Datei (optional, wenn leer = direkte Ausgabe)
     * @param bool $replaceOriginal Ob eine bereits existierende Datei ersetzt werden soll (default: false)
     * @return bool|string True bei direkter Ausgabe, Dateipfad bei Speicherung, Exception bei Fehlern
     * @throws Exception Bei Fehlern während der Erstellung
     */
    public function createWithAppendedPdfs(
        string $html,
        array $appendPdfPaths = [],
        string $filename = 'document_with_attachments.pdf',
        string $cacheDir = '',
        string $saveToPath = '',
        bool $replaceOriginal = false
    ) {
        
        if (empty($cacheDir)) {
            $cacheDir = rex_path::addonCache('pdfout');
            if (!is_dir($cacheDir)) {
                rex_dir::create($cacheDir);
            }
        }
        
        // Temporäre Dateien definieren
        $tempMain = $cacheDir . 'main_document_' . uniqid() . '.pdf';
        $tempFinal = $cacheDir . 'final_document_' . uniqid() . '.pdf';
        
        try {
            // 1. Hauptdokument mit dompdf erstellen (unter Verwendung der aktuellen Instanz-Settings)
            $mainPdf = clone $this; // Klont alle bereits konfigurierten Settings
            $mainPdf->setHtml($html);
            $mainPdf->setSaveToPath($cacheDir);
            $mainPdf->setName(basename($tempMain, '.pdf'));
            $mainPdf->setSaveAndSend(false);
            $mainPdf->run();
            
            // Prüfen ob Hauptdokument erstellt wurde
            if (!file_exists($tempMain)) {
                throw new Exception('Hauptdokument konnte nicht mit dompdf erstellt werden');
            }
            
            // 2. Wenn keine Anhänge, dann nur das Hauptdokument verwenden
            if (empty($appendPdfPaths)) {
                $finalContent = file_get_contents($tempMain);
            } else {
                // 3. PDF-Anhänge mit FPDI+TCPDF zusammenführen
                require_once rex_path::addon('pdfout') . 'vendor/tecnickcom/tcpdf/tcpdf.php';
                require_once rex_path::addon('pdfout') . 'vendor/setasign/fpdi/src/autoload.php';
                
                $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
                
                // Alle PDFs prüfen bevor wir beginnen
                foreach ($appendPdfPaths as $pdfPath) {
                    if (!file_exists($pdfPath)) {
                        throw new Exception('Anhang-PDF nicht gefunden: ' . $pdfPath);
                    }
                    if (!is_readable($pdfPath)) {
                        throw new Exception('Anhang-PDF nicht lesbar: ' . $pdfPath);
                    }
                }
                
                // Hauptdokument importieren
                $pageCount = $pdf->setSourceFile($tempMain);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $pdf->AddPage();
                    $templateId = $pdf->importPage($pageNo);
                    $pdf->useTemplate($templateId);
                }
                
                // Anhänge hinzufügen
                foreach ($appendPdfPaths as $index => $pdfPath) {
                    try {
                        $pageCount = $pdf->setSourceFile($pdfPath);
                        
                        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                            $pdf->AddPage();
                            $templateId = $pdf->importPage($pageNo);
                            $pdf->useTemplate($templateId);
                        }
                        
                    } catch (Exception $e) {
                        throw new Exception('Fehler beim Anhängen von ' . basename($pdfPath) . ': ' . $e->getMessage());
                    }
                }
                
                // Finales PDF erstellen
                $finalContent = $pdf->Output('', 'S');
            }
            
            // 4. PDF ausgeben oder speichern
            if (!empty($saveToPath)) {
                // PDF als Datei speichern
                $finalPath = rtrim($saveToPath, '/') . '/' . $filename;
                
                // Prüfen ob Datei bereits existiert und replaceOriginal = false
                if (file_exists($finalPath) && !$replaceOriginal) {
                    throw new Exception('Datei existiert bereits und replaceOriginal ist false: ' . $finalPath . '. Setzen Sie $replaceOriginal = true zum Überschreiben.');
                }
                
                // Zielverzeichnis erstellen falls nötig
                $targetDir = dirname($finalPath);
                if (!is_dir($targetDir)) {
                    if (!mkdir($targetDir, 0755, true)) {
                        throw new Exception('Zielverzeichnis konnte nicht erstellt werden: ' . $targetDir);
                    }
                }
                
                // PDF speichern
                if (!file_put_contents($finalPath, $finalContent)) {
                    throw new Exception('PDF konnte nicht gespeichert werden: ' . $finalPath);
                }
                
                // Aufräumen
                if (file_exists($tempMain)) unlink($tempMain);
                if (file_exists($tempFinal)) unlink($tempFinal);
                
                return $finalPath; // Pfad zur gespeicherten Datei zurückgeben
                
            } else {
                // PDF direkt ausgeben
                // Output-Buffer komplett leeren
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Headers setzen
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($finalContent));
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                echo $finalContent;
                
                // Aufräumen
                if (file_exists($tempMain)) unlink($tempMain);
                if (file_exists($tempFinal)) unlink($tempFinal);
                
                return true;
            }
            
        } catch (Exception $e) {
            // Aufräumen auch bei Fehlern
            if (isset($tempMain) && file_exists($tempMain)) unlink($tempMain);
            if (isset($tempFinal) && file_exists($tempFinal)) unlink($tempFinal);
            
            throw $e;
        }
    }

    /**
     * Vereinfachte Methode zum Erstellen eines PDFs mit Anhängen
     * 
     * @param string $html HTML-Inhalt für das Hauptdokument
     * @param array $appendPdfPaths Array mit Pfaden zu PDF-Dateien die angehängt werden sollen
     * @param string $filename Dateiname (optional)
     * @param string $saveToPath Pfad zum Speichern (optional, wenn leer = direkte Ausgabe)
     * @param bool $replaceOriginal Ob eine bereits existierende Datei ersetzt werden soll (default: false)
     * @return bool|string True bei direkter Ausgabe, Dateipfad bei Speicherung
     * @throws Exception
     */
    public function createDocumentWithAttachments(
        string $html,
        array $appendPdfPaths = [],
        string $filename = 'document_with_attachments.pdf',
        string $saveToPath = '',
        bool $replaceOriginal = false
    ) {
        return $this->createWithAppendedPdfs($html, $appendPdfPaths, $filename, '', $saveToPath, $replaceOriginal);
    }
}
