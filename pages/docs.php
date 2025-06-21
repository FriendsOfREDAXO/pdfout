<?php
/**
 * PDFOut README/Dokumentationsseite
 */

$addon = rex_addon::get('pdfout');

// README-Inhalt laden und parsen
$readmePath = $addon->getPath('README.md');
if (file_exists($readmePath)) {
    $readme = file_get_contents($readmePath);
    
    // Markdown zu HTML konvertieren
    [$readmeToc, $readmeContent] = rex_markdown::factory()->parseWithToc($readme, 1, 3, [
        'html' => true,
        'breaks' => true,
        'linkify' => true,
    ]);
    
    // README-Inhalt anzeigen
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'PDFOut Dokumentation');
    $fragment->setVar('content', $readmeContent, false);
    if ($readmeToc) {
        $fragment->setVar('toc', $readmeToc, false);
    }
    echo $fragment->parse('core/page/docs.php');
    
} else {
    echo rex_view::error('README.md nicht gefunden: ' . $readmePath);
}
?>
