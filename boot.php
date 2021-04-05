<?php
$addon = rex_addon::get('pdfout');
rex_dir::create($addon->getCachePath());
rex_dir::create(rex_path::addonCache('pdfout', 'fonts'));

require_once $this->getPath('vendor/'.'autoload.php');

if (rex::isBackend() && rex::getUser()) {
    $print_pdftest = rex_request('pdftest', 'int');
    if ($print_pdftest == 1) {
        rex_response::cleanOutputBuffers(); // OutputBuffer leeren
        $file = rex_file::get(rex_path::addon('pdfout', 'README.md'));
        $readmeHtml = '<style>body {font-family: DejaVu Sans; }</style>'.rex_markdown::factory()->parse($file);
        $dompdf = new Dompdf\Dompdf();
        $dompdf->loadHtml($readmeHtml);
        // Optionen festlegen
        $dompdf->set_option('font_cache', rex_path::addonCache('pdfout', 'fonts'));
        $dompdf->set_option('defaultFont', 'Courier');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->set_option('dpi', '100');
        // Rendern des PDF
        $dompdf->render();
        // Ausliefern des PDF
        header('Content-Type: application/pdf');
        $dompdf->stream('readme', array('Attachment' => false)); // bei true wird Download erzwungen
        die();

    }
}

