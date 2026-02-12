<?php

$addon = rex_addon::get('pdfout');
rex_dir::create($addon->getCachePath());
rex_dir::create(rex_path::addonCache('pdfout', 'fonts'));

require_once $addon->getPath('vendor/' . 'autoload.php');

// Media-Manager-Effekt für PDF-Thumbnails registrieren
if (rex_addon::get('media_manager')->isAvailable()) {
    rex_media_manager::addEffect(rex_effect_pdf_thumbnail::class);
}
