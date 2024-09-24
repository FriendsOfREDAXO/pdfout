<?php

use Dompdf\Dompdf;

class PdfOut extends Dompdf
{
    protected $name = 'pdf_file';
    protected $html = '';
    protected $orientation = 'portrait';
    protected $font = 'Dejavu Sans';
    protected $attachment = false;
    protected $remoteFiles = true;
    protected $saveToPath = '';
    protected $dpi = 100;
    protected $saveAndSend = true;

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

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setHtml(string $html, bool $outputfilter = false): self
    {
        if ($outputfilter) {
            $html = rex_extension::registerPoint(new rex_extension_point('OUTPUT_FILTER', $html));
        }
        $this->html = $html;
        return $this;
    }

    public function setOrientation(string $orientation): self
    {
        $this->orientation = $orientation;
        return $this;
    }

    public function setFont(string $font): self
    {
        $this->font = $font;
        return $this;
    }

    public function setAttachment(bool $attachment): self
    {
        $this->attachment = $attachment;
        return $this;
    }

    public function setRemoteFiles(bool $remoteFiles): self
    {
        $this->remoteFiles = $remoteFiles;
        return $this;
    }

    public function setSaveToPath(string $saveToPath): self
    {
        $this->saveToPath = $saveToPath;
        return $this;
    }

    public function setDpi(int $dpi): self
    {
        $this->dpi = $dpi;
        return $this;
    }

    public function setSaveAndSend(bool $saveAndSend): self
    {
        $this->saveAndSend = $saveAndSend;
        return $this;
    }

    /**
    * Fügt den Inhalt eines REDAXO-Artikels zum PDF hinzu
    *
    * @param int $articleId Die ID des Artikels
    * @param int|null $ctype Optional: Die ID des Inhaltstyps (ctype)
    * @return self
    */
    public function addArticle(int $articleId, ?int $ctype = null): self
    {
        $article = rex_article::get($articleId);
        if ($article) {
            $content = $ctype !== null ? $article->getArticle($ctype) : $article->getArticle();
            $this->html .= $content;
        }
        return $this;
    }

    public function run(): void
    {
        $this->loadHtml($this->html);
        // Optionen festlegen
        $options = $this->getOptions();
        $options->setChroot(rex_path::frontend());
        $options->setDefaultFont($this->font);
        $options->setDpi($this->dpi);
        $options->setFontCache(rex_path::addonCache('pdfout', 'fonts'));
        $options->setIsRemoteEnabled($this->remoteFiles);
        $this->setOptions($options);
        $this->setPaper('A4', $this->orientation);
        // Rendern des PDF
        $this->render();
        // Pagecounter Placeholder esetzen, wenn vorhanden
        $this->injectPageCount($this);

        // Speichern des PDF 
        if ($this->saveToPath !== '') {
            $savedata = $this->output();
            if (!is_null($savedata)) {
                rex_file::put($this->saveToPath . rex_string::normalize($this->name) . '.pdf', $savedata);
            }
        }

        // Ausliefern des PDF
        if ($this->saveToPath === '' || $this->saveAndSend === true) {
            rex_response::cleanOutputBuffers(); // OutputBuffer leeren
            header('Content-Type: application/pdf');
            $this->stream(rex_string::normalize($this->name), array('Attachment' => $this->attachment));
            die();
        }
    }

    public static function mediaUrl(string $type, string $file): string
    {
        $paddon = rex_addon::get('pdfout');
        $url = rex_media_manager::getUrl($type, $file);
        if ($addon->getProperty('aspdf', false) || rex_request('pdfout', 'int', 0) === 1) {
            return rex::getServer() . $url;
        }
        return $url;
    }

    public static function viewer(string $file = ''): string
    {
        if ($file !== '') {
            return rex_url::assets('addons/pdfout/vendor/web/viewer.html?file=' . urlencode($file));
        } else {
            return '#pdf_missing';
        }
    }
}
