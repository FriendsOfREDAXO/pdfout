<?php
/**
 * Simple PDF Demo Configuration
 */

return [
    'title' => 'Einfaches PDF',
    'description' => 'Erstellt ein einfaches PDF ohne erweiterte Features. Ideal für schnelle Dokumente oder erste Tests.',
    'panel_class' => 'panel-default',
    'btn_class' => 'btn-default',
    'icon' => 'fa-file-pdf-o',
    'code' => '$pdf = new PdfOut();
$pdf->setName(\'demo_simple\')
    ->setHtml(\'<h1>Einfaches PDF Demo</h1><p>Dies ist ein einfaches PDF.</p>\')
    ->run();'
];