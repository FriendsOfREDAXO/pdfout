<?php
use Dompdf\Dompdf;
class PdfOut extends Dompdf
{
 public static function sendPdf($html = '', $orientation = 'portrait', $defaultFont ='Courier', $Attachment = false, $remoteFiles = true;)
 {
  rex_response::cleanOutputBuffers(); // OutputBuffer leeren
        $dompdf = new self();
        $dompdf->loadHtml($html);
        // Optionen festlegen
        $dompdf->set_option('isRemoteEnabled', $remoteFiles);
        $dompdf->set_option('chroot', rex_path::base());
        $dompdf->set_option('font_cache', rex_path::addonCache('pdfout', 'fonts'));
        $dompdf->set_option('defaultFont', $defaultFont);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->set_option('dpi', '100');
        // Rendern des PDF
        $dompdf->render();
        // Ausliefern des PDF
        header('Content-Type: application/pdf');
        $dompdf->stream('readme', array('Attachment' => $Attachment)); // bei true wird Download erzwungen
   die();
 }
 
}
