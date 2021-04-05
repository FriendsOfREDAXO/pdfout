<?php
use Dompdf\Dompdf;

class PdfOut extends Dompdf
{
    public static function sendPdf($name = 'pdf_file', $html = '', $orientation = 'portrait', $defaultFont = 'Courier', $attachment = false, $remoteFiles = true)
    {
        rex_response::cleanOutputBuffers(); // OutputBuffer leeren
        $pdf = new self();
        $pdf->loadHtml($html);
        // Optionen festlegen
        $pdf->set_option('isRemoteEnabled', $remoteFiles);
        $pdf->set_option('chroot', rex_path::base());
        $pdf->set_option('font_cache', rex_path::addonCache('pdfout', 'fonts'));
        $pdf->set_option('defaultFont', $defaultFont);
        $pdf->setPaper('A4', $orientation);
        $pdf->set_option('dpi', '100');
        // Rendern des PDF
        $pdf->render();
        // Ausliefern des PDF
        header('Content-Type: application/pdf');
        $pdf->stream(rex_string::normalize($name), array('Attachment' => false));
        die();
    }
    public static function viewer($file = '')
    {
        if ($file!='')
        {
            return rex_url::assets('addons/pdfout/vendor/web/viewer.html?file=');
        }
        else {
            return '#';
        }
    }

}
