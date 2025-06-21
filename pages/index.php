<?php
/**
 * PDFOut Addon Hauptseite
 */

$addon = rex_addon::get('pdfout');

echo rex_view::title($addon->i18n('title'));

// Subpage einbinden wenn vorhanden
rex_be_controller::includeCurrentPageSubPath();
