<?php
require_once $this->getPath('vendor/dompdf/src/'.'Autoloader.php');

$print_pdftest = rex_request('pdftest', 'int');
if ($print_pdftest==1) {

    rex_response::cleanOutputBuffers(); // OutputBuffer leeren
    // Artikel laden oder alternativ ein Template
    $file = rex_file::get(rex_path::addon('pdfout','README.md'));
    $pdfcontent = rex_markdown::factory()->parse($file);
    $art_pdf_name =  'pdftest'; // Dateiname
    header('Content-Type: application/pdf');
    $dompdf = new Dompdf\Dompdf();
    $dompdf->loadHtml($pdfcontent);
    // Optionen festlegen
    $dompdf->set_option('defaultFont', 'Helvetica');
    $dompdf->set_option('dpi', '100');
    // Rendern des PDF
    $dompdf->render();
    // Ausliefern des PDF
    $dompdf->stream($art_pdf_name ,array('Attachment'=>false)); // bei true wird Download erzwungen
    die();
}

?>