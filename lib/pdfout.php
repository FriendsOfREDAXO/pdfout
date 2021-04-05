<?php
use Dompdf\Dompdf;
class PdfOut extends Dompdf
{
 public static function sendPdf($name = 'pdf_file', $html = '', $orientation = 'portrait', $defaultFont ='Courier', $attachment = false, $remoteFiles = true)
 {
  rex_response::cleanOutputBuffers(); // OutputBuffer leeren
        $dompdf = new self();
        $dompdf->loadHtml($html);
        // Optionen festlegen
        $dompdf->set_option('isRemoteEnabled', $remoteFiles);
        $dompdf->set_option('chroot', mb_substr(rex_path::base(), 0, -1));
        $dompdf->set_option('font_cache', rex_path::addonCache('pdfout', 'fonts'));
        $dompdf->set_option('defaultFont', $defaultFont);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->set_option('dpi', '100');
        // Rendern des PDF
        $dompdf->render();
        // Ausliefern des PDF
        header('Content-Type: application/pdf');
        $dompdf->stream($name ,array('Attachment'=>false));
   die();
 }
 
}
