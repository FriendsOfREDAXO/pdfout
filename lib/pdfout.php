<?php
use Dompdf\Dompdf;

class PdfOut extends Dompdf
{
    public static function sendPdf(string $name = 'pdf_file', string $html = '', string $orientation = 'portrait', string $defaultFont = 'Courier', bool $attachment = false, bool $remoteFiles = true, string $saveToPath = ''): void
    {
        rex_response::cleanOutputBuffers(); // OutputBuffer leeren
        $pdf = new self();
        $pdf->loadHtml($html);

        // Optionen festlegen
        $options = $pdf->getOptions();
        $options->setChroot(rex_path::frontend());
        $options->setDefaultFont($defaultFont);
        $options->setDpi(100);
        $options->setFontCache(rex_path::addonCache('pdfout', 'fonts'));
        $options->setIsRemoteEnabled($remoteFiles);
        $pdf->setOptions($options);
        $pdf->setPaper('A4', $orientation);

        // Rendern des PDF
        $pdf->render();
        // Ausliefern des PDF - entweder anzeigen der File oder auf Server speichern
        if($saveToPath === '') {
            header('Content-Type: application/pdf');
            $pdf->stream(rex_string::normalize($name), array('Attachment' => $attachment));
            die();
        } else {
            if ($outattach === $pdf->output()) {
            rex_file::put($saveToPath.rex_string::normalize($name).'.pdf', $outattach);
            }
        }

    }

    public static function viewer(string $file = ''): string
    {
        if ($file!=='')
        {
            return rex_url::assets('addons/pdfout/vendor/web/viewer.html?file='.urlencode($file));
        }
        else {
            return '#pdf_missing';
        }
    }

}

