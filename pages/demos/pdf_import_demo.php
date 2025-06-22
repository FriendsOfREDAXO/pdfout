<?php
/**
 * PDF-Import & Erweiterung Demo
 */

return [
    'title' => 'PDF-Import & Erweiterung',
    'description' => 'Demonstriert echte PDF-Import-Funktionalität mit FPDI. Importiert ein bestehendes PDF und fügt neue Inhalte hinzu.',
    'panel_class' => 'panel-default',
    'btn_class' => 'btn-default',
    'icon' => 'fa-copy',
    'availability_check' => '!class_exists(\'setasign\\Fpdi\\Tcpdf\\Fpdi\')',
    'availability_message' => 'FPDI ist nicht installiert. PDF-Import nicht verfügbar.',
    'code' => '// Existierendes PDF importieren und erweitern
$pdf = new PdfOut();
$pdf->setName(\'imported_document\')
    ->importAndExtendPdf(
        \'/path/to/existing.pdf\',
        \'<h1>Neuer Inhalt</h1><p>Hinzugefügt per FPDI</p>\',
        true  // Als neue Seite hinzufügen
    )
    ->run();

// Alternativ: Mehrere PDFs zusammenführen
$pdf = new PdfOut();
$pdf->setName(\'merged_document\')
    ->mergePdfs([
        \'/path/to/first.pdf\',
        \'/path/to/second.pdf\',
        \'/path/to/third.pdf\'
    ])
    ->run();'
];