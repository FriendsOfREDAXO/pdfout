<?php

/**
 * Installation und Update (update.php bindet diese Datei ein):
 * Voraussetzungen prüfen, Ordner anlegen, fehlende Einstellungen ergänzen.
 */

use FriendsOfRedaxo\PdfOut\Poppler;

$addon = rex_addon::get('pdfout');

require_once $addon->getPath('vendor/autoload.php');

// Poppler (pdfinfo, pdfsig, pdftoppm, pdftotext) ist Voraussetzung: Prüfen, Seitenzahlen, Vorschaubilder
if (!function_exists('proc_open')) {
    throw new rex_functional_exception('pdfout benötigt die PHP-Funktion proc_open() (in disable_functions freigeben).');
}
$missing = Poppler::missing();
if ([] !== $missing) {
    throw new rex_functional_exception(
        'pdfout benötigt die poppler-utils – nicht gefunden: ' . implode(', ', $missing) . '. '
        . 'Installation: Debian/Ubuntu „apt install poppler-utils“, macOS „brew install poppler“. '
        . 'Liegen die Programme in einem anderen Ordner, diesen vorher per Konsole setzen: '
        . 'php bin/console config:set --type=string pdfout poppler_path /pfad/zu/bin',
    );
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
