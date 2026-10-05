<?php

$addon = rex_addon::get('pdfout');
rex_dir::create($addon->getCachePath());
rex_dir::create(rex_path::addonCache('pdfout', 'fonts'));

require_once $addon->getPath('vendor/autoload.php');

if (rex::isBackend()) {
    rex_perm::register('pdfout[tools]', rex_i18n::msg('pdfout_perm_tools'));
    rex_perm::register('pdfout[demo]', rex_i18n::msg('pdfout_perm_demo'));
    rex_perm::register('pdfout[certificates]', rex_i18n::msg('pdfout_perm_certificates'));
    rex_perm::register('pdfout[config]', rex_i18n::msg('pdfout_perm_config'));
}

// Media-Manager-Effekt für PDF-Thumbnails registrieren
if (rex_addon::get('media_manager')->isAvailable()) {
    rex_media_manager::addEffect(rex_effect_pdf_thumbnail::class);
}

rex_api_function::register('pdfout_toolbar', rex_api_pdfout_toolbar::class);
rex_api_function::register('pdfout_editor_save', rex_api_pdfout_editor_save::class);
