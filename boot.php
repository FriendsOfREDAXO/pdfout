<?php
$addon = rex_addon::get('pdfout');
rex_dir::create($addon->getCachePath());
rex_dir::create(rex_path::addonCache('pdfout', 'fonts'));

require_once $addon->getPath('vendor/' . 'autoload.php');

if (rex::isBackend() && rex::getUser() !== null) {
    $print_pdftest = rex_request('pdftest', 'int');
    if ($print_pdftest === 1) {
        $file = '';
        $file = rex_file::get(rex_path::addon('pdfout', 'README.md'));
        if ($file !== '' && $file !== null) {
            $readmeHtml = '<style>body {font-family: DejaVu Sans; }</style>' . rex_markdown::factory()->parse($file);
            $pdf = new PdfOut();
            $pdf->setName('PDFOut-Readme')
                ->setFont('DejaVu Sans')
                ->setHtml($readmeHtml, true)
                ->setOrientation('portrait')
                ->setAttachment(false)
                ->setRemoteFiles(false);
            // execute and generate
            $pdf->run();
        }
    }
}
