<?php
/**
 * Passwortgeschütztes PDF Demo
 */

return [
    'title' => 'Passwortgeschütztes PDF',
    'description' => 'Erstellt ein passwortgeschütztes PDF mit Benutzer- und Besitzer-Passwort.<br><strong>Passwort:</strong> demo123',
    'panel_class' => 'panel-default',
    'btn_class' => 'btn-default',
    'icon' => 'fa-lock',
    'code' => '$pdf = new PdfOut();
$pdf->setName(\'demo_password\')
    ->setHtml(\'<h1>Passwortgeschütztes PDF</h1><p>Passwort: demo123</p>\')
    ->enablePasswordProtection(
        \'demo123\',    // Benutzer-Passwort
        \'owner456\',   // Besitzer-Passwort
        [\'print\', \'copy\'] // Erlaubte Aktionen
    )
    ->run();'
];