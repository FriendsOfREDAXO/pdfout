<?php

/**
 * pdfout – Prüfen: Metadaten, Signaturen, Text und Vorschau eines PDFs (über die poppler-utils).
 *
 * Auswahl aus dem Medienpool (auch per Link: ?page=pdfout/verify&file=datei.pdf) oder Upload.
 * Hochgeladene Dateien liegen nur während der Prüfung im Cache des Addons.
 */

use FriendsOfRedaxo\PdfOut\Backend\PdfInspector;
use FriendsOfRedaxo\PdfOut\Backend\ToolsRunner;
use FriendsOfRedaxo\PdfOut\Poppler;

$section = static function (string $title, string $body, string $class = 'edit', string $buttons = ''): string {
    $fragment = new rex_fragment();
    $fragment->setVar('class', $class, false);
    $fragment->setVar('title', $title, false);
    $fragment->setVar('body', $body, false);
    if ('' !== $buttons) {
        $fragment->setVar('buttons', $buttons, false);
    }
    return $fragment->parse('core/page/section.php');
};

$missing = Poppler::missing();
if ([] !== $missing) {
    echo rex_view::error(
        '<strong>Die poppler-utils fehlen</strong> – ohne sie lassen sich PDFs nicht prüfen. Nicht gefunden: <code>' . rex_escape(implode(', ', $missing)) . '</code>'
        . '<br>Installation: Debian/Ubuntu <code>apt install poppler-utils</code>, Alpine <code>apk add poppler-utils</code>, '
        . 'macOS <code>brew install poppler</code>. Liegen die Programme in einem anderen Ordner, ihn in den '
        . '<a href="' . rex_url::backendPage('pdfout/config') . '">Einstellungen</a> eintragen.',
    );
    return;
}

$csrf = rex_csrf_token::factory('pdfout_verify');
$mediaPdfs = ToolsRunner::mediaPdfs();
$mediaFile = rex_request('file', 'string', '');
$message = '';
$result = null;
$label = '';

$isPost = 'post' === rex_request_method() && rex_post('pdfout_verify_run', 'bool');
if ($isPost || '' !== $mediaFile) {
    $password = $isPost ? rex_post('password', 'string', '') : '';
    $tmp = null;
    try {
        if ($isPost && !$csrf->isValid()) {
            throw new InvalidArgumentException('Das Formular ist abgelaufen (CSRF-Schutz). Bitte erneut absenden.');
        }
        $uploadField = $isPost ? rex_files('upload', 'array', []) : [];
        $uploads = ToolsRunner::readUploads(is_array($uploadField) ? $uploadField : []);
        if ([] !== $uploads) {
            $label = $uploads[0]['name'];
            $tmp = rex_path::addonCache('pdfout', 'verify_' . bin2hex(random_bytes(8)) . '.pdf');
            if (!rex_file::put($tmp, $uploads[0]['data'])) {
                throw new RuntimeException('Die hochgeladene Datei konnte nicht zwischengespeichert werden.');
            }
            $file = $tmp;
            $mediaFile = '';
        } elseif ('' !== $mediaFile) {
            $media = rex_media::get($mediaFile);
            if (null === $media || 'pdf' !== strtolower($media->getExtension())) {
                throw new InvalidArgumentException(sprintf('„%s“ ist kein PDF im Medienpool.', $mediaFile));
            }
            $label = $media->getFileName();
            $file = rex_path::media($media->getFileName());
        } else {
            throw new InvalidArgumentException('PDF aus dem Medienpool wählen oder hochladen.');
        }
        $result = PdfInspector::inspect($file, $password);
    } catch (InvalidArgumentException | RuntimeException $e) {
        $message = rex_view::error(rex_escape($e->getMessage()));
    } finally {
        if (null !== $tmp) {
            @unlink($tmp);
        }
    }
}

// ------------------------------------------------------------------ Formular

$optionHtml = '<option value="">– PDF wählen –</option>';
foreach ($mediaPdfs as $pdf) {
    $text = '' !== $pdf['title'] ? $pdf['title'] . ' (' . $pdf['filename'] . ')' : $pdf['filename'];
    $optionHtml .= '<option value="' . rex_escape($pdf['filename']) . '"' . ($mediaFile === $pdf['filename'] ? ' selected' : '') . '>' . rex_escape($text) . '</option>';
}
$body = '<div class="row"><div class="col-sm-6"><div class="form-group">'
    . '<label for="pdfout-verify-file">PDF aus dem Medienpool</label>'
    . '<select class="form-control selectpicker" data-live-search="true" id="pdfout-verify-file" name="file">' . $optionHtml . '</select>'
    . '</div></div><div class="col-sm-6"><div class="form-group">'
    . '<label for="pdfout-verify-upload">oder PDF hochladen</label>'
    . '<input type="file" id="pdfout-verify-upload" name="upload" accept="application/pdf,.pdf" aria-describedby="pdfout-verify-upload-help">'
    . '<p class="help-block" id="pdfout-verify-upload-help">Höchstens ' . rex_formatter::bytes(ToolsRunner::MAX_UPLOAD) . '; ein Upload hat Vorrang vor der Auswahl. Die Datei wird nach der Prüfung gelöscht.</p>'
    . '</div></div></div>'
    . '<div class="form-group"><label for="pdfout-verify-password">Passwort (nur bei geschützten PDFs)</label>'
    . '<input class="form-control" type="password" id="pdfout-verify-password" name="password" value="" autocomplete="off"></div>';
$buttons = '<button class="btn btn-save" type="submit" name="pdfout_verify_run" value="1"><i class="rex-icon fa-check-circle" aria-hidden="true"></i> Prüfen</button>';

echo $message;
echo '<form action="' . rex_url::currentBackendPage() . '" method="post" enctype="multipart/form-data">'
    . $csrf->getHiddenField()
    . '<input type="hidden" name="MAX_FILE_SIZE" value="' . ToolsRunner::MAX_UPLOAD . '">'
    . $section('PDF prüfen', $body, 'edit', $buttons)
    . '</form>';

if (null === $result) {
    return;
}

// ------------------------------------------------------------------ Metadaten

$info = $result['info'];
$rows = '';
foreach (PdfInspector::INFO_LABELS as $key => $title) {
    $value = $info[$key] ?? '';
    if ('' === $value) {
        continue;
    }
    $value = match ($key) {
        'CreationDate', 'ModDate' => PdfInspector::formatDate($value),
        'Encrypted' => str_starts_with($value, 'yes') ? 'ja' . (strlen($value) > 3 ? ' ' . substr($value, 3) : '') : 'nein',
        'Tagged' => 'yes' === $value ? 'ja' : 'nein',
        'File size' => rex_formatter::bytes((int) $value),
        default => $value,
    };
    $rows .= '<tr><th scope="row">' . rex_escape($title) . '</th><td>' . rex_escape($value) . '</td></tr>';
}
$table = '<table class="table table-striped"><caption class="sr-only">Metadaten von ' . rex_escape($label) . '</caption><tbody>' . $rows . '</tbody></table>';
if (null !== $result['thumbnail']) {
    $body = '<div class="row"><div class="col-sm-9">' . $table . '</div><div class="col-sm-3">'
        . '<figure><img src="' . rex_escape($result['thumbnail']) . '" alt="Vorschau der ersten Seite von ' . rex_escape($label) . '" class="img-responsive img-thumbnail">'
        . '<figcaption class="help-block">Seite 1</figcaption></figure></div></div>';
} else {
    $body = $table;
}
echo $section('Metadaten: ' . rex_escape($label), $body, 'info');

// ------------------------------------------------------------------ Signaturen

$signatures = $result['signatures'];
if ([] === $signatures) {
    $body = rex_view::info('Das PDF enthält keine digitale Signatur.');
} else {
    $body = '<p class="help-block">Hinweis: Selbst erstellte Zertifikate (z. B. Testzertifikate von pdfout) gelten als „nicht vertrauenswürdig“, '
        . 'weil sie von keiner bekannten Zertifizierungsstelle stammen. Die Signatur kann trotzdem gültig sein – dann wurde das Dokument seit dem Signieren nicht verändert.</p>';
    foreach ($signatures as $i => $signature) {
        $valid = $signature['valid']
            ? '<span class="label label-success">gültig</span>'
            : '<span class="label label-danger">ungültig</span>';
        $trusted = $signature['certificate_trusted']
            ? '<span class="label label-success">vertrauenswürdig</span>'
            : '<span class="label label-warning">nicht vertrauenswürdig</span>';
        $details = [
            'Unterzeichnet von' => rex_escape($signature['signer']) . ('' !== $signature['subject'] ? '<br><small class="text-muted">' . rex_escape($signature['subject']) . '</small>' : ''),
            'Zeitpunkt' => rex_escape($signature['signed_at']),
            'Signatur' => $valid . ('' !== $signature['status'] ? ' <small class="text-muted">' . rex_escape($signature['status']) . '</small>' : ''),
            'Zertifikat' => $trusted . ('' !== $signature['certificate'] ? ' <small class="text-muted">' . rex_escape($signature['certificate']) . '</small>' : ''),
            'Ganzes Dokument signiert' => $signature['whole_document']
                ? 'ja'
                : 'nein <small class="text-muted">(nach dem Signieren wurde etwas angehängt oder nur ein Teil ist abgedeckt)</small>',
            'Hash-Verfahren' => rex_escape($signature['hash']),
            'Signaturtyp' => rex_escape($signature['type']),
            'Feld' => rex_escape($signature['field']),
        ];
        $body .= '<h3 class="h4">Signatur ' . ($i + 1) . '</h3><table class="table table-striped"><tbody>';
        foreach ($details as $title => $html) {
            $body .= '<tr><th scope="row" class="col-sm-3">' . rex_escape($title) . '</th><td>' . $html . '</td></tr>';
        }
        $body .= '</tbody></table>';
    }
}
echo $section('Signaturen', $body, 'info');

// ------------------------------------------------------------------ Text

if ('' === $result['text']) {
    $body = rex_view::warning('Kein Text gefunden – vermutlich ein gescanntes PDF (nur Bilder) ohne Texterkennung. Für Screenreader und Suche ist es dann nicht lesbar.');
} else {
    $body = '<details><summary>Textvorschau anzeigen' . ($result['truncated'] ? ' (erste ' . PdfInspector::TEXT_PREVIEW . ' Zeichen)' : '') . '</summary>'
        . '<pre style="white-space: pre-wrap; max-height: 30em; overflow: auto; margin-top: 10px">' . rex_escape($result['text']) . '</pre></details>';
}
echo $section('Text', $body, 'info');
