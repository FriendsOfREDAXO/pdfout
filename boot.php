<?php

require_once $this->getPath('vendor/dompdf/src/'.'Autoloader.php');

$print_pdftest = rex_request('pdftest', 'int');
if ($print_pdftest==1) {

    rex_response::cleanOutputBuffers(); // OutputBuffer leeren
    $file = rex_file::get(rex_path::addon('pdfout','README.md'));
    $readmeHtml = rex_markdown::factory()->parse($file);
    $art_pdf_name =  'pdftest'; // Dateiname
    $dompdf = new Dompdf\Dompdf();
    $dompdf->loadHtml($readmeHtml);
    // Optionen festlegen
    $dompdf->set_option('defaultFont', 'Helvetica');
    $dompdf->set_option('dpi', '100');
    // Rendern des PDF
    $dompdf->render();
    // Ausliefern des PDF
    header('Content-Type: application/pdf');
    $dompdf->stream($art_pdf_name ,array('Attachment'=>false)); // bei true wird Download erzwungen
    die();
}


