<?php

/**
 * Installation und Update (update.php bindet diese Datei ein):
 * Voraussetzungen prüfen, Ordner anlegen, fehlende Einstellungen ergänzen.
 */

use FriendsOfRedaxo\PdfOut\Poppler;

$addon = rex_addon::get('pdfout');

require_once $addon->getPath('vendor/autoload.php');

// Poppler ist optional: ohne die poppler-utils fehlen nur Prüfen und Auslesen (Signaturen, Metadaten, Text)
$missing = function_exists('proc_open') ? Poppler::missing() : Poppler::REQUIRED;
if ([] !== $missing) {
    $addon->setProperty('successmsg', 'pdfout ist installiert. Hinweis: die poppler-utils fehlen (' . implode(', ', $missing) . ') – '
        . 'Prüfen und Auslesen von PDFs sind deshalb ausgeblendet. Nachrüsten z. B. mit „apt install poppler-utils“ oder „brew install poppler“.');
}

rex_dir::create($addon->getCachePath());
rex_dir::create(rex_path::addonCache('pdfout', 'fonts'));
rex_dir::create($addon->getCachePath('thumbnails'));
rex_dir::create($addon->getDataPath('certificates'));

$defaults = [
    'default_certificate_path' => '',
    'default_certificate_password' => '',
    'enable_signature_by_default' => false,
    'enable_password_protection_by_default' => false,
    'default_signature_position_x' => 15,
    'default_signature_position_y' => 257,
    'default_signature_width' => 70,
    'default_signature_height' => 25,
    'poppler_path' => '',
    'poppler_enabled' => true,
    'toolbar_preset' => 'balanced',
    'toolbar_hidden_groups' => ['open_file', 'presentation', 'rotation', 'cursor_tools', 'scroll_mode', 'spread_mode', 'document_properties', 'editor_tools'],
    'toolbar_profiles' => [],
    'toolbar_active_profile' => '',
];
foreach ($defaults as $key => $value) {
    if (!$addon->hasConfig($key)) {
        $addon->setConfig($key, $value);
    }
}
