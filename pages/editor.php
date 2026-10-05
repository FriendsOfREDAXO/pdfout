<?php

/**
 * pdfout – PDF-Editor: PDF aus dem Medienpool im pdf.js-Viewer mit Editor-Werkzeugen öffnen
 * und das Ergebnis als neue Datei oder als Ersatz des Originals im Medienpool speichern.
 */

$addon = rex_addon::get('pdfout');
$user = rex::requireUser();

if (!rex_api_pdfout_editor_save::mayUseEditor($user)) {
    echo rex_view::warning('Für den Editor sind das Recht „PDF-Werkzeuge“ und Zugriff auf den Medienpool nötig.');
    return;
}

$mediaPerm = $user->getComplexPerm('media');
$mayEditCategory = static fn (int $categoryId): bool => $user->isAdmin()
    || $mediaPerm->hasCategoryPerm($categoryId);

// ------------------------------------------------------------------ PDFs aus dem Medienpool (nur erlaubte Kategorien)

$pdfs = [];
foreach (rex_sql::factory()->getArray('SELECT filename, title, category_id FROM ' . rex::getTable('media') . " WHERE filetype = 'application/pdf' ORDER BY title, filename") as $row) {
    $filename = (string) $row['filename'];
    if ($mayEditCategory((int) $row['category_id'])) {
        $pdfs[$filename] = (string) $row['title'];
    }
}

$file = rex_request('file', 'string', '');
$media = '' !== $file ? rex_media::get($file) : null;
if (null !== $media && (!isset($pdfs[$media->getFileName()]) || !is_file(rex_path::media($media->getFileName())))) {
    $media = null;
}
if ('' !== $file && null === $media) {
    echo rex_view::error('Die Datei „' . rex_escape($file) . '“ wurde nicht gefunden oder ist kein PDF.');
}

// ------------------------------------------------------------------ Hinweise

$intro = '<p>Der Editor öffnet ein PDF aus dem Medienpool im Viewer mit eingeblendeten Bearbeitungswerkzeugen. '
    . 'Ergänzungen werden als Anmerkungen in das PDF geschrieben; das Original bleibt unverändert, solange nicht „Original ersetzen“ gewählt wird.</p>'
    . '<div class="row">'
    . '<div class="col-sm-6"><h2 class="h4">Möglich</h2><ul>'
    . '<li>Text hinzufügen (Freitext)</li>'
    . '<li>Zeichnen (Freihand)</li>'
    . '<li>Unterschrift einfügen (zeichnen, tippen oder als Bild)</li>'
    . '<li>Bilder einfügen</li>'
    . '<li>Textstellen hervorheben</li>'
    . '</ul></div>'
    . '<div class="col-sm-6"><h2 class="h4">Nicht möglich</h2><ul>'
    . '<li>Vorhandenen Text ändern oder löschen</li>'
    . '<li>Seiten umsortieren, löschen oder drehen (dafür „Werkzeuge“ nutzen)</li>'
    . '<li>Digitale Signatur mit Zertifikat – eine eingefügte Unterschrift ist nur ein Bild</li>'
    . '</ul></div>'
    . '</div>'
    . '<p class="help-block">Ist das PDF digital signiert, wird die Signatur durch das Speichern ungültig.</p>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'info', false);
$fragment->setVar('title', 'PDF-Editor', false);
$fragment->setVar('body', $intro, false);
echo $fragment->parse('core/page/section.php');

// ------------------------------------------------------------------ PDF wählen

if ([] === $pdfs) {
    echo rex_view::info('Im Medienpool sind keine PDF-Dateien vorhanden, auf die zugegriffen werden kann.');
    return;
}

$options = '';
foreach ($pdfs as $filename => $title) {
    $label = '' !== $title ? $title . ' (' . $filename . ')' : $filename;
    $selected = null !== $media && $media->getFileName() === $filename ? ' selected' : '';
    $options .= '<option value="' . rex_escape($filename) . '"' . $selected . '>' . rex_escape($label) . '</option>';
}

$selectForm = '<form action="' . rex_url::backendController() . '" method="get">'
    . '<input type="hidden" name="page" value="pdfout/tools/editor">'
    . '<div class="form-group">'
    . '<label for="pdfout-editor-file">PDF wählen</label>'
    . '<select id="pdfout-editor-file" name="file" class="form-control selectpicker" data-live-search="true" data-width="100%" required>'
    . (null === $media ? '<option value="">– bitte wählen –</option>' : '')
    . $options
    . '</select>'
    . '</div>'
    . '<button type="submit" class="btn btn-primary"><i class="rex-icon fa-pencil" aria-hidden="true"></i> Im Editor öffnen</button>'
    . '</form>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', 'PDF wählen', false);
$fragment->setVar('body', $selectForm, false);
echo $fragment->parse('core/page/section.php');

if (null === $media) {
    return;
}

// ------------------------------------------------------------------ Editor

$filename = $media->getFileName();
// Cache-Buster: Änderungsdatum im Medienpool + Größe (filemtime bleibt beim Ersetzen teils erhalten)
$fileUrl = rex_url::media($filename) . '?v=' . $media->getUpdateDate() . '-' . $media->getSize();
$viewerUrl = rex_url::assets('addons/pdfout/vendor/web/viewer.html');
$saveUrl = rex_url::backendController(['page' => 'pdfout/tools/editor'] + rex_api_pdfout_editor_save::getUrlParams(), false);
$suggested = rex_api_pdfout_editor_save::targetFilename('', $filename);
$maxSize = rex_api_pdfout_editor_save::maxUploadSize();

$categorySelect = new rex_media_category_select(true);
$categorySelect->setId('pdfout-editor-category');
$categorySelect->setName('category_id');
$categorySelect->setAttribute('class', 'form-control');
$categorySelect->setSelected($media->getCategoryId());
if ($user->isAdmin() || $mediaPerm->hasAll()) {
    $categorySelect->addOption(rex_i18n::msg('pool_kats_no'), '0');
}

$mayReplace = $mayEditCategory($media->getCategoryId());

$body = '<div id="pdfout-editor"'
    . ' data-viewer="' . rex_escape($viewerUrl) . '"'
    . ' data-file="' . rex_escape($fileUrl) . '"'
    . ' data-save-url="' . rex_escape($saveUrl) . '"'
    . ' data-original="' . rex_escape($filename) . '"'
    . ' data-max-size="' . $maxSize . '"'
    . ' data-version="' . rex_escape($addon->getVersion()) . '">'
    . '<p class="pdfout-editor-meta"><strong>' . rex_escape('' !== $media->getTitle() ? $media->getTitle() : $filename) . '</strong> '
    . '<code>' . rex_escape($filename) . '</code> · <a href="' . rex_escape($fileUrl) . '" target="_blank" rel="noopener">PDF in neuem Tab öffnen</a></p>'
    . '<noscript>' . rex_view::warning('Der Editor benötigt JavaScript.') . '</noscript>'
    . '<iframe class="pdfout-editor-frame" title="PDF-Editor: ' . rex_escape($filename) . '" allow="clipboard-read; clipboard-write"></iframe>'
    . '<div class="pdfout-editor-save">'
    . '<div class="row">'
    . '<div class="col-sm-6 form-group">'
    . '<label for="pdfout-editor-filename">Dateiname der neuen Datei</label>'
    . '<input type="text" id="pdfout-editor-filename" class="form-control" value="' . rex_escape($suggested) . '" maxlength="180" autocomplete="off">'
    . '</div>'
    . '<div class="col-sm-6 form-group">'
    . '<label for="pdfout-editor-category">Medienkategorie der neuen Datei</label>'
    . $categorySelect->get()
    . '</div>'
    . '</div>'
    . '<p>'
    . '<button type="button" class="btn btn-save" data-pdfout-save="new" disabled><i class="rex-icon fa-floppy-o" aria-hidden="true"></i> Speichern als neue Datei</button> '
    . ($mayReplace ? '<button type="button" class="btn btn-delete" data-pdfout-save="replace" disabled><i class="rex-icon fa-exchange" aria-hidden="true"></i> Original ersetzen</button>' : '')
    . '</p>'
    . '<p id="pdfout-editor-status" class="pdfout-editor-status" role="status" aria-live="polite">Editor wird geladen …</p>'
    . '<p class="help-block">Höchstens ' . rex_escape(rex_formatter::bytes($maxSize)) . '. „Original ersetzen“ überschreibt '
    . '<code>' . rex_escape($filename) . '</code> – überall, wo die Datei verwendet wird, erscheint dann die bearbeitete Fassung.</p>'
    . '</div>'
    . '</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Bearbeiten: ' . rex_escape($filename), false);
$fragment->setVar('body', $body, false);
echo $fragment->parse('core/page/section.php');

$assetVersion = rex_escape($addon->getVersion());
echo '<link rel="stylesheet" href="' . rex_escape($addon->getAssetsUrl('editor.css')) . '?v=' . $assetVersion . '">';
echo '<script src="' . rex_escape($addon->getAssetsUrl('editor.js')) . '?v=' . $assetVersion . '" nonce="' . rex_response::getNonce() . '"></script>';
