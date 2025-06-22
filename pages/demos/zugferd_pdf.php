<?php
/**
 * ZUGFeRD/Factur-X Rechnung Demo
 */

return [
    'title' => 'ZUGFeRD/Factur-X Rechnung',
    'description' => 'Erstellt eine ZUGFeRD-konforme Rechnung mit eingebetteter XML für die automatische Verarbeitung in der Buchhaltung.',
    'panel_class' => 'panel-default',
    'btn_class' => 'btn-default',
    'icon' => 'fa-file-code-o',
    'code' => '// Rechnungsdaten vorbereiten
$invoiceData = PdfOut::getExampleZugferdData();

$pdf = new PdfOut();
$pdf->setName(\'demo_zugferd_rechnung\')
    ->setHtml($rechnungsHtml)  // Ihr Rechnungs-HTML
    ->enableZugferd($invoiceData, \'BASIC\', \'factur-x.xml\')
    ->run();

// Die Rechnung enthält jetzt strukturierte Daten
// die automatisch von Buchhaltungssoftware 
// verarbeitet werden können!'
];