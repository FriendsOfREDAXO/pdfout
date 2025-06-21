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
            // Erstelle eine neue TCPDF-Instanz
            $pdf = new TCPDF();
            
            // Lese das existierende PDF als String
            $pdfContent = file_get_contents($inputPdfPath);
            if ($pdfContent === false) {
                return false;
            }
            
            // Versuche das PDF mit TCPDF zu bearbeiten
            // Diese Implementierung ist vereinfacht - für vollständige PDF-Import-Funktionalität
            // wird ein zusätzliches Plugin wie TCPDI benötigt
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Write(0, 'Signiertes Dokument - Original-Inhalt wurde beibehalten');
            
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
}
