<?php

/**
 * pdfout – Übersicht: Status, Schnellstart, Bereiche
 */

use Composer\InstalledVersions;
use FriendsOfRedaxo\PdfOut\Poppler;

$addon = rex_addon::get('pdfout');
$user = rex::requireUser();

$packageVersion = static function (string $package): string {
    try {
        return InstalledVersions::isInstalled($package) ? (string) InstalledVersions::getPrettyVersion($package) : '–';
    } catch (Throwable) {
        return '–';
    }
};

// Zertifikate im Addon-Ordner
$certificateCount = 0;
$certificateDir = $addon->getDataPath('certificates/');
foreach (is_dir($certificateDir) ? (scandir($certificateDir) ?: []) : [] as $entry) {
    if (in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), ['p12', 'pfx', 'pem'], true) && is_file($certificateDir . $entry)) {
        ++$certificateCount;
    }
}

$popplerMissing = Poppler::missing();
$popplerVersion = [] === $popplerMissing ? Poppler::version() : null;

// ------------------------------------------------------------------ Status

$status = [
    'pdfout' => $addon->getVersion(),
    'dompdf (HTML → PDF)' => $packageVersion('dompdf/dompdf'),
    'tc-lib-pdf (Bearbeiten, Signatur)' => $packageVersion('tecnickcom/tc-lib-pdf'),
    'pdf.js (Viewer)' => (string) $addon->getProperty('pdfjs', '–'),
    'Poppler (Prüfen, Vorschau)' => [] === $popplerMissing
        ? '<span class="text-success"><i class="rex-icon fa-check" aria-hidden="true"></i> ' . rex_escape($popplerVersion ?? 'verfügbar') . '</span>'
        : '<span class="text-danger"><i class="rex-icon fa-times" aria-hidden="true"></i> fehlt: ' . rex_escape(implode(', ', $popplerMissing)) . '</span>',
    'Zertifikate' => $certificateCount > 0
        ? (string) $certificateCount
        : '<span class="text-warning">keine</span>',
    'PHP' => PHP_VERSION,
];
$rawStatus = ['Poppler (Prüfen, Vorschau)', 'Zertifikate'];

$rows = '';
foreach ($status as $label => $value) {
    $rows .= '<tr><th scope="row">' . rex_escape($label) . '</th><td>' . (in_array($label, $rawStatus, true) ? $value : rex_escape($value)) . '</td></tr>';
}
$body = '<table class="table table-condensed pdfout-status"><tbody>' . $rows . '</tbody></table>';

if ([] !== $popplerMissing) {
    $body .= rex_view::warning(
        'Die poppler-utils fehlen (' . rex_escape(implode(', ', $popplerMissing)) . '). Ohne sie funktionieren Prüfen, Seitenvorschau und Thumbnails nicht. '
        . 'Installation z. B. mit <code>apt install poppler-utils</code> oder <code>brew install poppler</code>; '
        . 'liegen die Programme in einem eigenen Ordner, den Pfad in den <a href="' . rex_url::backendPage('pdfout/settings/general') . '">Einstellungen</a> eintragen.',
    );
}
if (0 === $certificateCount) {
    $body .= '<p>Zum Signieren wird ein Zertifikat benötigt: <a href="' . rex_url::backendPage('pdfout/settings/certificates') . '" style="text-decoration: underline">Zertifikate verwalten</a>.</p>';
}

$statusFragment = new rex_fragment();
$statusFragment->setVar('title', 'Status', false);
$statusFragment->setVar('body', $body, false);
$statusHtml = $statusFragment->parse('core/page/section.php');

// ------------------------------------------------------------------ Schnellstart

$examples = [
    'HTML → PDF' => <<<'PHP'
        use FriendsOfRedaxo\PdfOut\PdfOut;

        PdfOut::create()
            ->html('<h1>Hallo</h1>')   // oder ->article(5)
            ->inline('hallo.pdf');      // oder ->download(), ->save($pfad)
        PHP,
    'PDF bearbeiten' => <<<'PHP'
        use FriendsOfRedaxo\PdfOut\PdfDocument;

        PdfDocument::fromMedia('preisliste.pdf')
            ->append(PdfDocument::fromMedia('agb.pdf'))
            ->pages('1-3,-1')
            ->stamp('ENTWURF')
            ->pageNumbers()
            ->download('preisliste.pdf');
        PHP,
    'Signieren und schützen' => <<<'PHP'
        use FriendsOfRedaxo\PdfOut\{PdfOut, Certificate, SignatureField, Permission};

        PdfOut::create()
            ->html($html)
            ->sign(Certificate::fromAddon(), reason: 'Freigabe', field: SignatureField::bottomRight())
            ->protect('geheim', allow: [Permission::Print])
            ->save(rex_path::addonData('mein_addon', 'vertrag.pdf'));
        PHP,
];

$quick = '';
foreach ($examples as $title => $code) {
    $quick .= '<h2 class="h5"><strong>' . rex_escape($title) . '</strong></h2>'
        . '<pre tabindex="0" aria-label="' . rex_escape('Beispiel: ' . $title) . '"><code>' . rex_escape($code) . '</code></pre>';
}
$quick .= '<p><a href="' . rex_url::backendPage('pdfout/help/api') . '">API-Dokumentation</a> · <a href="' . rex_url::backendPage('pdfout/help/docs') . '">Handbuch</a></p>';

$quickFragment = new rex_fragment();
$quickFragment->setVar('title', 'Schnellstart', false);
$quickFragment->setVar('body', $quick, false);
$quickHtml = $quickFragment->parse('core/page/section.php');

// ------------------------------------------------------------------ Bereiche

$tiles = [
    ['pdfout/tools/edit', 'fa-wrench', 'Bearbeiten', 'Zusammenführen, Seiten wählen, Stempel, Seitenzahlen, signieren, schützen.', 'pdfout[tools]'],
    ['pdfout/tools/editor', 'fa-pencil', 'Editor', 'Text, Zeichnungen, Unterschriften und Bilder in ein PDF einfügen.', 'pdfout[tools]'],
    ['pdfout/tools/verify', 'fa-check-circle', 'Prüfen', 'Signaturen, Verschlüsselung und Metadaten anzeigen.', 'pdfout[]'],
    ['pdfout/help/demo', 'fa-play-circle', 'Demos', 'Ausführbare Beispiele mit Code zum Kopieren.', 'pdfout[demo]'],
    ['pdfout/settings/certificates', 'fa-certificate', 'Zertifikate', 'Zertifikate hochladen, erzeugen, Standard festlegen.', 'pdfout[certificates]'],
    ['pdfout/settings/toolbar', 'fa-sliders', 'PDF.js-Toolbar', 'Funktionen des Viewers ein- und ausblenden.', 'pdfout[config]'],
    ['pdfout/settings/general', 'fa-cog', 'Einstellungen', 'Papierformat, Schrift, Signatur- und Poppler-Vorgaben.', 'pdfout[config]'],
];

$tileHtml = '';
foreach ($tiles as [$page, $icon, $title, $text, $perm]) {
    if (!$user->isAdmin() && !$user->hasPerm($perm)) {
        continue;
    }
    $tileHtml .= '<div class="col-sm-6 col-md-4 col-lg-3">'
        . '<a class="pdfout-tile" href="' . rex_url::backendPage($page) . '">'
        . '<i class="rex-icon ' . $icon . '" aria-hidden="true"></i>'
        . '<strong>' . rex_escape($title) . '</strong>'
        . '<span>' . rex_escape($text) . '</span>'
        . '</a></div>';
}

$tilesFragment = new rex_fragment();
$tilesFragment->setVar('title', 'Bereiche', false);
$tilesFragment->setVar('body', '<div class="row pdfout-tiles">' . $tileHtml . '</div>', false);
$tilesHtml = $tilesFragment->parse('core/page/section.php');

echo '<style>
.pdfout-tiles > div { margin-bottom: 15px; }
.pdfout-tile { display: block; height: 100%; min-height: 110px; padding: 12px 14px; border: 1px solid rgba(128,128,128,.35); border-radius: 4px; color: inherit; }
.pdfout-tile:hover, .pdfout-tile:focus { text-decoration: none; border-color: currentColor; }
.pdfout-tile .rex-icon { float: left; font-size: 22px; margin: 2px 12px 0 0; }
.pdfout-tile strong { display: block; margin-bottom: 4px; }
.pdfout-tile span { display: block; font-size: 12px; }
.pdfout-status th { width: 45%; font-weight: normal; }
.pdfout-overview pre { overflow: auto; font-size: 12px; }
.pdfout-overview pre, .pdfout-overview pre code { white-space: pre; word-break: normal; overflow-wrap: normal; word-wrap: normal; }
</style>';

echo '<div class="pdfout-overview">';
echo $tilesHtml;
echo '<div class="row"><div class="col-md-5">' . $statusHtml . '</div><div class="col-md-7">' . $quickHtml . '</div></div>';
echo '</div>';
