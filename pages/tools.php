<?php

/**
 * pdfout – Werkzeuge: PDFs zusammenführen, Seiten wählen, stempeln, nummerieren, signieren, schützen.
 *
 * Die Verarbeitung steckt in FriendsOfRedaxo\PdfOut\Backend\ToolsRunner; hier nur Formular und Ablauf.
 * Downloads: PdfDocument::download() leert die Ausgabepuffer des Backends (Layout-Kopf) und beendet
 * das Skript – deshalb wird vor der Verarbeitung nichts ausgegeben.
 */

use FriendsOfRedaxo\PdfOut\Backend\ToolsOptions;
use FriendsOfRedaxo\PdfOut\Backend\ToolsRunner;
use FriendsOfRedaxo\PdfOut\Permission;

$csrf = rex_csrf_token::factory('pdfout_tools');
$mediaPdfs = ToolsRunner::mediaPdfs();
$certificates = ToolsRunner::certificates();
$permissionLabels = ToolsRunner::permissionLabels();

/** @var array<string, mixed> $input Formularwerte (zum erneuten Anzeigen nach Fehlern) */
$input = [];
$message = '';

if ('post' === rex_request_method() && rex_post('pdfout_tools_run', 'bool')) {
    foreach (['pages', 'stamp_text', 'stamp_opacity', 'stamp_pages', 'stamp_pages_custom', 'numbers', 'numbers_format',
        'numbers_position', 'numbers_skip_first', 'meta_title', 'meta_author', 'meta_subject', 'sign', 'sign_certificate',
        'sign_password', 'sign_name', 'sign_reason', 'sign_location', 'sign_visible', 'sign_position', 'protect',
        'user_password', 'owner_password', 'output', 'filename', 'category_id'] as $key) {
        $input[$key] = rex_post($key, 'string', '');
    }
    $input['media'] = rex_post('media', 'array', []);
    $input['allow'] = rex_post('allow', 'array', []);

    if (!$csrf->isValid()) {
        $message = rex_view::error('Das Formular ist abgelaufen (CSRF-Schutz). Bitte erneut absenden.');
    } else {
        try {
            $options = ToolsOptions::fromArray($input);
            $uploadField = rex_files('uploads', 'array', []);
            $uploads = ToolsRunner::readUploads(is_array($uploadField) ? $uploadField : []);
            $doc = ToolsRunner::build($options, $uploads);
            $filename = ToolsRunner::outputFilename($options);

            if (ToolsOptions::OUTPUT_DOWNLOAD === $options->output) {
                // verarbeitet das PDF, verwirft die bisherige Backend-Ausgabe und sendet die Datei
                $doc->download($filename);
            }

            if (!rex::requireUser()->getComplexPerm('media')->hasCategoryPerm($options->categoryId)) {
                throw new InvalidArgumentException('Keine Berechtigung für diese Medienkategorie.');
            }
            $saved = ToolsRunner::saveToMedia($doc, $filename, $options->categoryId, $options->metaTitle);
            $message = rex_view::success(
                'PDF im Medienpool gespeichert: <strong>' . rex_escape($saved['filename']) . '</strong>'
                . ($saved['renamed'] ? ' (Name angepasst, vorhandene Dateien bleiben unverändert)' : '')
                . '<br><a href="' . rex_url::media($saved['filename']) . '" target="_blank" rel="noopener">PDF öffnen</a>'
                . ' · <a href="' . rex_url::backendPage('mediapool/media', ['file_name' => $saved['filename']]) . '">Im Medienpool anzeigen</a>'
                . (FriendsOfRedaxo\PdfOut\Poppler::isAvailable() ? ' · <a href="' . rex_url::backendPage('pdfout/tools/verify', ['file' => $saved['filename']]) . '">Prüfen</a>' : ''),
            );
        } catch (InvalidArgumentException | RuntimeException $e) {
            $message = rex_view::error(rex_escape($e->getMessage()));
        }
    }
    // Passwörter nicht wieder ins Formular schreiben
    unset($input['sign_password'], $input['user_password'], $input['owner_password']);
}

/** Formularwert (nach dem Absenden) oder Vorgabe */
$value = static fn (string $key, string $default = ''): string => isset($input[$key]) && is_scalar($input[$key]) ? (string) $input[$key] : $default;
$checked = static fn (string $key): string => '' !== $value($key) ? ' checked' : '';
$selected = static fn (string $current, string $option): string => $current === $option ? ' selected' : '';

/**
 * Formulargruppe mit Label und optionalem Hilfetext
 */
$group = static function (string $id, string $label, string $control, string $help = ''): string {
    $helpHtml = '' !== $help ? '<p class="help-block" id="' . $id . '-help">' . $help . '</p>' : '';
    return '<div class="form-group"><label for="' . $id . '">' . rex_escape($label) . '</label>' . $control . $helpHtml . '</div>';
};
$text = static fn (string $id, string $name, string $current, string $attributes = ''): string => '<input class="form-control" type="text" id="' . $id . '" name="' . $name . '" value="' . rex_escape($current) . '"' . $attributes . '>';
$checkbox = static fn (string $id, string $name, string $label, string $state): string => '<div class="checkbox"><label for="' . $id . '"><input type="checkbox" id="' . $id . '" name="' . $name . '" value="1"' . $state . '> ' . rex_escape($label) . '</label></div>';
/** @param array<string, string> $options */
$select = static function (string $id, string $name, array $options, string $current) use ($selected): string {
    $html = '<select class="form-control" id="' . $id . '" name="' . $name . '">';
    foreach ($options as $key => $label) {
        $html .= '<option value="' . rex_escape((string) $key) . '"' . $selected($current, (string) $key) . '>' . rex_escape((string) $label) . '</option>';
    }
    return $html . '</select>';
};
$section = static function (string $title, string $body, string $class = 'edit'): string {
    $fragment = new rex_fragment();
    $fragment->setVar('class', $class, false);
    $fragment->setVar('title', $title, false);
    $fragment->setVar('body', $body, false);
    return $fragment->parse('core/page/section.php');
};

echo $message;

echo rex_view::info(
    '<strong>Hinweis:</strong> Bei jeder Bearbeitung werden die Seiten neu aufgebaut – Links, Formularfelder und Lesezeichen gehen dabei verloren. '
    . 'Ohne gewählte Option wird ein einzelnes PDF unverändert übernommen; mehrere PDFs werden nur zusammengeführt.',
);

// ------------------------------------------------------------------ Quellen

$sourceRows = 8;
$chosenMedia = array_values(array_filter(array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', (array) ($input['media'] ?? [])), static fn (string $v): bool => '' !== $v));
$body = '<p id="pdfout-sources-help">PDFs aus dem Medienpool in der gewünschten Reihenfolge wählen (1. = Anfang). Hochgeladene PDFs werden danach angehängt.</p>';
if ([] === $mediaPdfs) {
    $body .= rex_view::warning('Im Medienpool gibt es noch keine PDFs – nur Hochladen ist möglich.');
}
$body .= '<ol class="list-unstyled pdfout-sources">';
for ($i = 0; $i < $sourceRows; ++$i) {
    $current = $chosenMedia[$i] ?? '';
    $id = 'pdfout-media-' . $i;
    $optionHtml = '<option value="">– kein PDF –</option>';
    foreach ($mediaPdfs as $pdf) {
        $label = '' !== $pdf['title'] ? $pdf['title'] . ' (' . $pdf['filename'] . ')' : $pdf['filename'];
        $optionHtml .= '<option value="' . rex_escape($pdf['filename']) . '"' . $selected($current, $pdf['filename']) . '>' . rex_escape($label) . '</option>';
    }
    $body .= '<li class="form-group pdfout-source-row" data-filled="' . ('' !== $current ? '1' : '0') . '">'
        . '<label for="' . $id . '">' . ($i + 1) . '. PDF aus dem Medienpool</label>'
        . '<select class="form-control selectpicker" data-live-search="true" id="' . $id . '" name="media[]" aria-describedby="pdfout-sources-help">' . $optionHtml . '</select>'
        . '</li>';
}
$body .= '</ol>';
$body .= '<p><button type="button" class="btn btn-default hidden" id="pdfout-source-more"><i class="rex-icon fa-plus" aria-hidden="true"></i> Weiteres PDF</button></p>';
$body .= $group(
    'pdfout-uploads',
    'PDFs hochladen (optional, mehrere möglich)',
    '<input type="file" id="pdfout-uploads" name="uploads[]" accept="application/pdf,.pdf" multiple aria-describedby="pdfout-uploads-help">',
    'Nur PDF, je Datei höchstens ' . rex_formatter::bytes(ToolsRunner::MAX_UPLOAD) . '. Hochgeladene Dateien werden nur für diesen Vorgang verwendet und nicht gespeichert.',
);
$form = $section('Quellen', $body);

// ------------------------------------------------------------------ Seiten

$form .= $section('Seitenauswahl', $group(
    'pdfout-pages',
    'Seiten (optional)',
    $text('pdfout-pages', 'pages', $value('pages'), ' placeholder="z. B. 1-3,5,-1" aria-describedby="pdfout-pages-help"'),
    'Seiten des zusammengeführten PDFs in der gewünschten Reihenfolge: „1-3“ = Seiten 1 bis 3, „5“ = Seite 5, „-1“ = letzte Seite, „3-1“ = rückwärts. Leer = alle Seiten.',
));

// ------------------------------------------------------------------ Stempel

$body = $group(
    'pdfout-stamp-text',
    'Stempeltext (optional)',
    $text('pdfout-stamp-text', 'stamp_text', $value('stamp_text'), ' placeholder="z. B. ENTWURF" aria-describedby="pdfout-stamp-text-help"'),
    'Wird schräg und halbtransparent über die Seiten gelegt. Leer = kein Stempel.',
);
$body .= '<div class="row"><div class="col-sm-4">' . $group(
    'pdfout-stamp-opacity',
    'Deckkraft in %',
    '<input class="form-control" type="number" min="5" max="100" step="5" id="pdfout-stamp-opacity" name="stamp_opacity" value="' . rex_escape($value('stamp_opacity', '15')) . '">',
) . '</div><div class="col-sm-4">' . $group(
    'pdfout-stamp-pages',
    'Auf welchen Seiten',
    $select('pdfout-stamp-pages', 'stamp_pages', ToolsOptions::STAMP_PAGES, $value('stamp_pages', 'all')),
) . '</div><div class="col-sm-4">' . $group(
    'pdfout-stamp-pages-custom',
    'Seitenauswahl für den Stempel',
    $text('pdfout-stamp-pages-custom', 'stamp_pages_custom', $value('stamp_pages_custom'), ' placeholder="z. B. 1-3" aria-describedby="pdfout-stamp-pages-custom-help"'),
    'Nur bei „Auswahl …“; bezieht sich auf die fertigen Seiten.',
) . '</div></div>';
$form .= $section('Stempel / Wasserzeichen', $body);

// ------------------------------------------------------------------ Seitenzahlen

$body = $checkbox('pdfout-numbers', 'numbers', 'Seitenzahlen einfügen', $checked('numbers'));
$body .= '<div class="row"><div class="col-sm-6">' . $group(
    'pdfout-numbers-format',
    'Format',
    $text('pdfout-numbers-format', 'numbers_format', $value('numbers_format', 'Seite {page} von {pages}'), ' aria-describedby="pdfout-numbers-format-help"'),
    '{page} = aktuelle Seite, {pages} = Anzahl der Seiten.',
) . '</div><div class="col-sm-6">' . $group(
    'pdfout-numbers-position',
    'Position',
    $select('pdfout-numbers-position', 'numbers_position', ToolsOptions::NUMBER_POSITIONS, $value('numbers_position', 'bottom-center')),
) . '</div></div>';
$body .= $checkbox('pdfout-numbers-skip-first', 'numbers_skip_first', 'Erste Seite ohne Seitenzahl (z. B. Deckblatt)', $checked('numbers_skip_first'));
$form .= $section('Seitenzahlen', $body);

// ------------------------------------------------------------------ Metadaten

$body = '<p>Leere Felder werden nicht gesetzt. Hinweis: Bei einer Bearbeitung entfallen die bisherigen Metadaten.</p>';
$body .= '<div class="row">';
foreach (['meta_title' => 'Titel', 'meta_author' => 'Autor', 'meta_subject' => 'Thema'] as $key => $label) {
    $id = 'pdfout-' . str_replace('_', '-', $key);
    $body .= '<div class="col-sm-4">' . $group($id, $label, $text($id, $key, $value($key))) . '</div>';
}
$body .= '</div>';
$form .= $section('Metadaten', $body);

// ------------------------------------------------------------------ Signatur

$body = '';
if ([] === $certificates) {
    $body .= rex_view::warning('Keine Zertifikate vorhanden. Zertifikate lassen sich auf der Seite <a href="' . rex_url::backendPage('pdfout/settings/certificates') . '">Zertifikate</a> hochladen oder erzeugen.');
} else {
    $certificateOptions = ['' => '– Zertifikat wählen –'];
    foreach ($certificates as $certificate) {
        $certificateOptions[$certificate] = $certificate;
    }
    $body .= $checkbox('pdfout-sign', 'sign', 'PDF digital signieren', $checked('sign'));
    $body .= '<div class="row"><div class="col-sm-6">' . $group(
        'pdfout-sign-certificate',
        'Zertifikat',
        $select('pdfout-sign-certificate', 'sign_certificate', $certificateOptions, $value('sign_certificate')),
    ) . '</div><div class="col-sm-6">' . $group(
        'pdfout-sign-password',
        'Passwort des Zertifikats',
        '<input class="form-control" type="password" id="pdfout-sign-password" name="sign_password" value="" autocomplete="off">',
    ) . '</div></div>';
    $body .= '<div class="row">';
    foreach (['sign_name' => 'Name (leer = aus dem Zertifikat)', 'sign_reason' => 'Grund', 'sign_location' => 'Ort'] as $key => $label) {
        $id = 'pdfout-' . str_replace('_', '-', $key);
        $body .= '<div class="col-sm-4">' . $group($id, $label, $text($id, $key, $value($key))) . '</div>';
    }
    $body .= '</div>';
    $body .= '<div class="row"><div class="col-sm-6">'
        . $checkbox('pdfout-sign-visible', 'sign_visible', 'Sichtbares Feld (Kasten mit Name, Datum, Ort, Grund)', $checked('sign_visible'))
        . '</div><div class="col-sm-6">' . $group(
            'pdfout-sign-position',
            'Position des sichtbaren Felds',
            $select('pdfout-sign-position', 'sign_position', ToolsOptions::SIGNATURE_POSITIONS, $value('sign_position', 'bottom-right')),
        ) . '</div></div>';
}
$form .= $section('Digitale Signatur', $body);

// ------------------------------------------------------------------ Passwortschutz

$allowed = array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', (array) ($input['allow'] ?? [Permission::Print->value, Permission::PrintHigh->value]));
$body = $checkbox('pdfout-protect', 'protect', 'PDF mit Passwort schützen (AES-256)', $checked('protect'));
$body .= '<div class="row"><div class="col-sm-6">' . $group(
    'pdfout-user-password',
    'Passwort zum Öffnen',
    '<input class="form-control" type="password" id="pdfout-user-password" name="user_password" value="" autocomplete="new-password" aria-describedby="pdfout-user-password-help">',
    'Leer = PDF lässt sich ohne Passwort öffnen, nur die Rechte sind eingeschränkt.',
) . '</div><div class="col-sm-6">' . $group(
    'pdfout-owner-password',
    'Passwort für alle Rechte (Besitzer)',
    '<input class="form-control" type="password" id="pdfout-owner-password" name="owner_password" value="" autocomplete="new-password" aria-describedby="pdfout-owner-password-help">',
    'Leer = zufälliges Passwort; die Rechte lassen sich dann später nicht mehr ändern.',
) . '</div></div>';
$body .= '<fieldset><legend class="h5">Erlaubt (alles andere ist gesperrt)</legend>';
foreach ($permissionLabels as $permission => $label) {
    $id = 'pdfout-allow-' . $permission;
    $always = Permission::Extract->value === $permission;
    $body .= '<div class="checkbox"><label for="' . $id . '"><input type="checkbox" id="' . $id . '" name="allow[]" value="' . rex_escape($permission) . '"'
        . ($always || in_array($permission, $allowed, true) ? ' checked' : '') . ($always ? ' disabled' : '') . '> ' . rex_escape($label) . '</label></div>';
}
$body .= '</fieldset>';
$form .= $section('Passwortschutz', $body);

// ------------------------------------------------------------------ Ausgabe

$output = $value('output', ToolsOptions::OUTPUT_DOWNLOAD);
$categorySelect = new rex_media_category_select();
$categorySelect->setName('category_id');
$categorySelect->setId('pdfout-category');
$categorySelect->setSize(1);
$categorySelect->setAttribute('class', 'form-control');
$categorySelect->addOption('Keine Kategorie', '0');
$categorySelect->setSelected($value('category_id', '0'));

$body = '<fieldset><legend class="h5">Ergebnis</legend>'
    . '<div class="radio"><label for="pdfout-output-download"><input type="radio" id="pdfout-output-download" name="output" value="' . ToolsOptions::OUTPUT_DOWNLOAD . '"'
    . (ToolsOptions::OUTPUT_MEDIA !== $output ? ' checked' : '') . '> Herunterladen</label></div>'
    . '<div class="radio"><label for="pdfout-output-media"><input type="radio" id="pdfout-output-media" name="output" value="' . ToolsOptions::OUTPUT_MEDIA . '"'
    . (ToolsOptions::OUTPUT_MEDIA === $output ? ' checked' : '') . '> Im Medienpool speichern</label></div>'
    . '</fieldset>';
$body .= '<div class="row"><div class="col-sm-6">' . $group(
    'pdfout-filename',
    'Dateiname',
    $text('pdfout-filename', 'filename', $value('filename'), ' placeholder="z. B. speisekarte-komplett.pdf" aria-describedby="pdfout-filename-help"'),
    'Leer = Name des ersten PDFs. Im Medienpool wird nichts überschrieben: Gibt es den Namen schon, wird „_1“, „_2“ … angehängt.',
) . '</div><div class="col-sm-6">' . $group('pdfout-category', 'Medienkategorie (nur beim Speichern)', $categorySelect->get()) . '</div></div>';

$buttons = '<button class="btn btn-save rex-form-aligned" type="submit" name="pdfout_tools_run" value="1"><i class="rex-icon fa-cogs" aria-hidden="true"></i> PDF erstellen</button>';
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', 'Ausgabe', false);
$fragment->setVar('body', $body, false);
$fragment->setVar('buttons', $buttons, false);
$form .= $fragment->parse('core/page/section.php');

echo '<form action="' . rex_url::currentBackendPage() . '" method="post" enctype="multipart/form-data">'
    . $csrf->getHiddenField()
    . '<input type="hidden" name="MAX_FILE_SIZE" value="' . ToolsRunner::MAX_UPLOAD . '">'
    . $form
    . '</form>';
?>
<script nonce="<?= rex_response::getNonce() ?>">
// nur die belegten Quellen-Zeilen und eine leere zeigen; weitere über „Weiteres PDF“
$(document).on('rex:ready', function (event, container) {
    var rows = container.find('.pdfout-source-row');
    var button = container.find('#pdfout-source-more');
    if (!rows.length) {
        return;
    }
    var visible = Math.max(1, rows.filter('[data-filled="1"]').length + 1);
    rows.each(function (index) {
        $(this).toggleClass('hidden', index >= visible);
    });
    button.toggleClass('hidden', visible >= rows.length).off('click.pdfout').on('click.pdfout', function () {
        var next = rows.filter('.hidden').first();
        next.removeClass('hidden');
        next.find('select').trigger('focus');
        button.toggleClass('hidden', !rows.filter('.hidden').length);
    });
});
</script>
