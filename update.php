<?php

$addon = rex_addon::get('pdfout');
rex_dir::create($addon->getCachePath());
rex_dir::create(rex_path::addonCache('pdfout', 'fonts'));

// Erstelle Zertifikats-Verzeichnis
rex_dir::create($addon->getDataPath('certificates'));

// Aktualisiere Konfiguration, falls neue Einstellungen hinzugefügt wurden
$currentConfig = $addon->getConfig();
$defaultConfig = [
    'default_certificate_path' => '',
    'default_certificate_password' => '',
    'enable_signature_by_default' => false,
    'enable_password_protection_by_default' => false,
    'default_signature_position_x' => 180,
    'default_signature_position_y' => 60,
    'default_signature_width' => 15,
    'default_signature_height' => 15,
];

// Füge fehlende Konfigurationsoptionen hinzu
$missingConfig = [];
foreach ($defaultConfig as $key => $value) {
    if (!isset($currentConfig[$key])) {
        $missingConfig[$key] = $value;
    }
}
if (!empty($missingConfig)) {
    $addon->setConfig($missingConfig);
}
