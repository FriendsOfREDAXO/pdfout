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

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setHtml(string $html, bool $outputfiler = false): self
    {
        if ($outputfilter)
        {    
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
        // Speichern des PDF 
        if ($this->saveToPath !== '') {
            $savedata = $this->output();
            if (!is_null($savedata)) {
                rex_file::put($this->saveToPath . rex_string::normalize($this->name) . '.pdf', $savedata);
            }
        }
        // Ausliefern des PDF - als File und/oder auf Server speichern
        if ($this->saveToPath === '' || $this->saveAndSend === true) {
            rex_response::cleanOutputBuffers(); // OutputBuffer leeren
            header('Content-Type: application/pdf');
            $this->stream(rex_string::normalize($this->name), array('Attachment' => $this->attachment));
            die();
        }
    }

    /**
     * @deprecated since 7.0.0
     */
    public static function sendPdf(string $name = 'pdf_file', string $html = '', string $orientation = 'portrait', string $defaultFont = 'Courier', bool $attachment = false, bool $remoteFiles = true, string $saveToPath = ''): void
    {
        $pdf = new PdfOut();
        // Set the PDF properties
        $pdf->setName($name)
            ->setFont($defaultFont)
            ->setHtml($html)
            ->setOrientation($orientation)
            ->setAttachment($attachment)
            ->setRemoteFiles($remoteFiles)
            ->setSaveAndSend(false)
            ->setSaveToPath($saveToPath)
            ->setDpi(100);
        $pdf->run();
    }


    public static function viewer(string $file = ''): string
    {
        if ($file !== '') {
            return rex_url::assets('addons/pdfout/web/viewer.html?file=' . urlencode($file));
        } else {
            return '#pdf_missing';
        }
    }
}
