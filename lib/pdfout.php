<?php
namespace FriendsOfRedaxo\PdfOut;

use Dompdf\Dompdf;
use Dompdf\Options;
use rex;
use rex_addon;
use rex_article;
use rex_article_content;
use rex_extension;
use rex_extension_point;
use rex_file;
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
        $finalHtml = $this->html;

        // Wenn ein Grundtemplate gesetzt wurde, füge den Inhalt ein
        if ($this->baseTemplate !== '') {
            $finalHtml = str_replace($this->contentPlaceholder, $this->html, $this->baseTemplate);
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
            return rex::getServer() . $url;
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
}
