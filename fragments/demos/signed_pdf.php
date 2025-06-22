<?php
/**
 * Digital Signed PDF Demo Configuration
 */

return [
    'title' => 'Digital signiertes PDF',
    'description' => 'Erstellt ein digital signiertes PDF mit sichtbarer Signatur. Verwendet das Standard-Testzertifikat.',
    'panel_class' => 'panel-default',
    'btn_class' => 'btn-default',
    'icon' => 'fa-certificate',
    'code' => '$pdf = new PdfOut();
$pdf->setName(\'demo_signed\')
    ->setHtml(\'<h1>Signiertes PDF Demo</h1><p>Dies ist ein digital signiertes PDF.</p>\')
    ->enableDigitalSignature(
        \'\',                // Standard-Zertifikat verwenden
        \'redaxo123\',       // Zertifikatspasswort
        \'REDAXO Demo\',     // Name
        \'Demo-Umgebung\',   // Ort
        \'Demo-Signierung\', // Grund
        \'demo@redaxo.org\'  // Kontakt
    )
    ->setVisibleSignature(120, 200, 70, 30, -1) // X, Y, Breite, Höhe, Seite
    ->run();'
];