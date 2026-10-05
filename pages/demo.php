<?php

/**
 * pdfout – Demos: ausführbare Beispiele der API (Code siehe lib/Backend/Demo.php)
 */

use FriendsOfRedaxo\PdfOut\Backend\Demo;
use FriendsOfRedaxo\PdfOut\Poppler;

$csrf = rex_csrf_token::factory('pdfout_demo');
$demos = Demo::all();
$certificateProblem = Demo::certificateProblem();
$popplerMissing = Poppler::missing();

$run = rex_post('demo_run', 'string', '');
$signatures = null;
$runError = '';

// Ausführen – vor jeder Ausgabe; inline() leert die Ausgabepuffer und beendet die Anfrage
if ('' !== $run && isset($demos[$run])) {
    $demo = $demos[$run];
    if (!$csrf->isValid()) {
        $runError = rex_i18n::msg('csrf_token_invalid');
    } elseif ($demo['certificate'] && null !== $certificateProblem) {
        $runError = 'Kein nutzbares Zertifikat: ' . $certificateProblem;
    } elseif ($demo['poppler'] && [] !== $popplerMissing) {
        $runError = 'Poppler fehlt: ' . implode(', ', $popplerMissing);
    } else {
        try {
            if ('pdf' === $demo['result']) {
                Demo::build($run)->inline($demo['filename']);
            }
            if ('signatures' === $demo['result']) {
                $signatures = Demo::verify();
            }
        } catch (Throwable $e) {
            $runError = $e->getMessage();
        }
    }
}

if ('' !== $runError) {
    echo rex_view::error('Demo „' . rex_escape($demos[$run]['title'] ?? $run) . '“: ' . rex_escape($runError));
}

// ------------------------------------------------------------------ Einleitung

$certificatesUrl = rex_url::backendPage('pdfout/settings/certificates');
$intro = '
<p>Jede Demo zeigt den Code, der beim Klick auf „Ausführen“ läuft. Das Ergebnis öffnet sich in einem neuen Tab.
Am Ende steht jeweils ein Objekt, das mit <code>-&gt;inline(\'name.pdf\')</code> angezeigt wird –
ebenso möglich: <code>-&gt;download()</code>, <code>-&gt;save($pfad)</code> oder <code>-&gt;toString()</code>.</p>
<dl class="dl-horizontal">
    <dt><code>PdfOut</code></dt><dd>HTML oder REDAXO-Artikel → PDF (dompdf), Einstellungen aus der Konfiguration</dd>
    <dt><code>PdfDocument</code></dt><dd>beliebige PDFs bearbeiten: zusammenführen, Seiten wählen, Stempel, Seitenzahlen, Metadaten</dd>
    <dt><code>Certificate</code></dt><dd>Zertifikat zum Signieren, z. B. <code>Certificate::fromAddon()</code> aus der Seite „Zertifikate“</dd>
    <dt><code>SignatureField</code></dt><dd>Position des sichtbaren Signaturfelds (mm ab links oben)</dd>
    <dt><code>Permission</code></dt><dd>erlaubte Rechte bei Passwortschutz (Drucken, Kopieren …)</dd>
    <dt><code>Poppler</code></dt><dd>PDFs prüfen: Infos, Text, Signaturen (optional, benötigt die poppler-utils)</dd>
</dl>
<p>Alle Klassen liegen im Namespace <code>FriendsOfRedaxo\PdfOut</code>.
<a href="' . rex_url::backendPage('pdfout/help/api') . '" style="text-decoration: underline">Zur API-Dokumentation</a></p>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'info', false);
$fragment->setVar('title', 'Bausteine', false);
$fragment->setVar('body', $intro, false);
echo $fragment->parse('core/page/section.php');

// ------------------------------------------------------------------ Demos

$mediaPdf = Demo::firstMediaPdf();
$cards = [];
foreach ($demos as $key => $demo) {
    if ($demo['poppler'] && [] !== $popplerMissing) {
        continue; // Demo benötigt Poppler – ohne die poppler-utils ausgeblendet
    }
    $id = 'pdfout-demo-' . $key;
    $body = '<p>' . rex_escape($demo['description']) . '</p>';
    $body .= '<pre tabindex="0" aria-label="' . rex_escape('Code: ' . $demo['title']) . '"><code>' . rex_escape(Demo::source($demo['method'])) . '</code></pre>';

    $hint = '';
    if ($demo['certificate'] && null !== $certificateProblem) {
        $hint = rex_view::warning('Kein nutzbares Zertifikat (' . rex_escape($certificateProblem) . '). '
            . '<a href="' . $certificatesUrl . '">Zertifikat auf der Seite „Zertifikate“ anlegen oder hochladen</a> und als Standard festlegen.');
    }

    $buttons = '';
    if ('' !== $hint) {
        $body .= $hint;
    } elseif ('viewer' === $demo['result']) {
        if (null === $mediaPdf) {
            $body .= rex_view::info('Im Medienpool liegt noch kein PDF. Nach dem Hochladen eines PDFs erscheint hier der Link zum Viewer.');
        } else {
            $buttons = '<a class="btn btn-primary" href="' . rex_escape(Demo::viewer($mediaPdf)) . '" target="_blank" rel="noopener">'
                . '<i class="rex-icon fa-eye" aria-hidden="true"></i> ' . rex_escape($mediaPdf) . ' im Viewer öffnen</a>';
        }
    } else {
        $target = 'pdf' === $demo['result'] ? ' target="_blank"' : '';
        $action = 'pdf' === $demo['result'] ? rex_url::currentBackendPage() : rex_url::currentBackendPage() . '#' . $id;
        $buttons = '<form action="' . $action . '" method="post"' . $target . '>'
            . $csrf->getHiddenField()
            . '<button type="submit" class="btn btn-primary" name="demo_run" value="' . rex_escape($key) . '">'
            . '<i class="rex-icon fa-play" aria-hidden="true"></i> Ausführen'
            . ('pdf' === $demo['result'] ? '<span class="sr-only"> (öffnet in neuem Tab)</span>' : '')
            . '</button></form>';
    }

    if ('signatures' === $demo['result'] && $run === $key && null !== $signatures) {
        if ([] === $signatures) {
            $body .= rex_view::warning('pdfsig hat keine Signatur gefunden.');
        } else {
            $rows = '';
            foreach ($signatures as $signature) {
                $rows .= '<tr>'
                    . '<td>' . rex_escape($signature['field']) . '</td>'
                    . '<td>' . rex_escape($signature['signer']) . '</td>'
                    . '<td>' . rex_escape($signature['signed_at']) . '</td>'
                    . '<td>' . rex_escape($signature['hash']) . ' / ' . rex_escape($signature['type']) . '</td>'
                    . '<td>' . ($signature['valid'] ? '<span class="text-success"><i class="rex-icon fa-check" aria-hidden="true"></i> gültig</span>' : '<span class="text-danger"><i class="rex-icon fa-times" aria-hidden="true"></i> ungültig</span>')
                    . '<br><small>' . rex_escape($signature['status']) . '</small></td>'
                    . '<td>' . ($signature['certificate_trusted'] ? 'vertrauenswürdig' : 'nicht vertrauenswürdig')
                    . '<br><small>' . rex_escape($signature['certificate']) . '</small></td>'
                    . '<td>' . ($signature['whole_document'] ? 'ja' : 'nein') . '</td>'
                    . '</tr>';
            }
            $body .= '<div class="table-responsive"><table class="table table-striped table-condensed">'
                . '<caption>Ergebnis von pdfsig</caption>'
                . '<thead><tr><th scope="col">Feld</th><th scope="col">Unterzeichner</th><th scope="col">Zeitpunkt</th><th scope="col">Verfahren</th>'
                . '<th scope="col">Signatur</th><th scope="col">Zertifikat</th><th scope="col">ganzes Dokument</th></tr></thead>'
                . '<tbody>' . $rows . '</tbody></table></div>'
                . '<p>Selbst erstellte Test-Zertifikate gelten als „nicht vertrauenswürdig“, die Signatur selbst ist trotzdem gültig.</p>';
        }
    }

    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_escape($demo['title']), false);
    $fragment->setVar('body', $body, false);
    $fragment->setVar('buttons', $buttons, false);
    $cards[] = '<div class="col-lg-6" id="' . $id . '">' . $fragment->parse('core/page/section.php') . '</div>';
}

echo '<style>
.pdfout-demos pre { max-height: 22em; overflow: auto; font-size: 12px; }
.pdfout-demos pre, .pdfout-demos pre code { white-space: pre; word-break: normal; overflow-wrap: normal; word-wrap: normal; }
.pdfout-demos form { display: inline; }
</style>';
echo '<div class="pdfout-demos">';
foreach (array_chunk($cards, 2) as $pair) {
    echo '<div class="row">' . implode('', $pair) . '</div>';
}
echo '</div>';
