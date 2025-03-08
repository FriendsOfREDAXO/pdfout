<?php
use FriendsOfRedaxo\PDFOut\PdfOut;

$addon = rex_addon::get('pdfout');
rex_dir::create($addon->getCachePath());
rex_dir::create(rex_path::addonCache('pdfout', 'fonts'));

require_once $addon->getPath('vendor/' . 'autoload.php');
