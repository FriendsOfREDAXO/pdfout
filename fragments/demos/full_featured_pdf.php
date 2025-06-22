<?php
/**
 * Full Featured PDF Demo Configuration
 */

return [
    'title' => 'Vollausgestattetes PDF',
    'description' => 'Kombiniert alle Features: Digitale Signierung und Passwortschutz in einem PDF.<br><strong>Passwort:</strong> demo123',
    'panel_class' => 'panel-default',
    'btn_class' => 'btn-default',
    'icon' => 'fa-star',
    'code' => '$pdf = new PdfOut();
$pdf->setName(\'demo_full_featured\')
    ->setHtml(\'<h1>Vollausgestattetes PDF</h1><p>Alle Features kombiniert.</p>\')
    ->enableDigitalSignature(\'\', \'redaxo123\', \'REDAXO Demo\', \'Demo-Umgebung\', \'Full-Feature Demo\', \'demo@redaxo.org\')
    ->setVisibleSignature(120, 220, 70, 30, -1)
    ->enablePasswordProtection(\'demo123\', \'owner456\', [\'print\'])
    ->run();'
];