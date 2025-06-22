<?php
/**
 * PDFOut Übersichtsseite
 */

$addon = rex_addon::get('pdfout');

// Header mit Willkommenstext
$header = '
<div class="row">
    <div class="col-md-12 text-center">
        <h1><i class="fa fa-file-pdf-o"></i> PDFOut</h1>
        <p class="lead">Das umfassende PDF-Addon für REDAXO mit erweiterten Funktionen für professionelle PDF-Erstellung.</p>
    </div>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('body', $header, false);
echo $fragment->parse('core/page/section.php');

// Feature-Übersicht (die bessere Version an den Anfang)
$features = '
<div class="row">
    <div class="col-md-3">
        <div class="panel panel-primary">
            <div class="panel-heading text-center">
                <i class="fa fa-certificate fa-2x"></i>
                <h4>Digitale Signierung</h4>
            </div>
            <div class="panel-body">
                <ul class="list-unstyled">
                    <li><i class="fa fa-check-circle text-success"></i> Sichtbare Signaturen</li>
                    <li><i class="fa fa-check-circle text-success"></i> Unsichtbare Signaturen</li>
                    <li><i class="fa fa-check-circle text-success"></i> X.509 Zertifikate</li>
                    <li><i class="fa fa-check-circle text-success"></i> Nachträgliche Signierung</li>
                    <li><i class="fa fa-check-circle text-success"></i> Mehrfach-Signaturen</li>
                </ul>
                <div class="text-center">
                    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/demo']) . '" class="btn btn-primary btn-sm">
                        <i class="fa fa-play"></i> Demo
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="panel panel-success">
            <div class="panel-heading text-center">
                <i class="fa fa-lock fa-2x"></i>
                <h4>Passwortschutz</h4>
            </div>
            <div class="panel-body">
                <ul class="list-unstyled">
                    <li><i class="fa fa-check-circle text-success"></i> Benutzer-Passwort</li>
                    <li><i class="fa fa-check-circle text-success"></i> Besitzer-Passwort</li>
                    <li><i class="fa fa-check-circle text-success"></i> Druck-Kontrolle</li>
                    <li><i class="fa fa-check-circle text-success"></i> Kopier-Schutz</li>
                    <li><i class="fa fa-check-circle text-success"></i> Bearbeitungs-Schutz</li>
                </ul>
                <div class="text-center">
                    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/demo']) . '" class="btn btn-success btn-sm">
                        <i class="fa fa-play"></i> Demo
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="panel panel-info">
            <div class="panel-heading text-center">
                <i class="fa fa-file-pdf-o fa-2x"></i>
                <h4>PDF-Erzeugung</h4>
            </div>
            <div class="panel-body">
                <ul class="list-unstyled">
                    <li><i class="fa fa-check-circle text-success"></i> HTML zu PDF</li>
                    <li><i class="fa fa-check-circle text-success"></i> CSS Unterstützung</li>
                    <li><i class="fa fa-check-circle text-success"></i> Responsive Design</li>
                    <li><i class="fa fa-check-circle text-success"></i> Template Support</li>
                    <li><i class="fa fa-check-circle text-success"></i> Metadaten</li>
                </ul>
                <div class="text-center">
                    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/demo']) . '" class="btn btn-info btn-sm">
                        <i class="fa fa-play"></i> Demo
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="panel panel-warning">
            <div class="panel-heading text-center">
                <i class="fa fa-cogs fa-2x"></i>
                <h4>Erweiterte Features</h4>
            </div>
            <div class="panel-body">
                <ul class="list-unstyled">
                    <li><i class="fa fa-check-circle text-success"></i> Auto-Engine-Wahl</li>
                    <li><i class="fa fa-check-circle text-success"></i> Wasserzeichen</li>
                    <li><i class="fa fa-check-circle text-success"></i> Hintergründe</li>
                    <li><i class="fa fa-check-circle text-success"></i> Mehrsprachig</li>
                    <li><i class="fa fa-check-circle text-success"></i> API-Integration</li>
                </ul>
                <div class="text-center">
                    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/config']) . '" class="btn btn-warning btn-sm">
                        <i class="fa fa-cog"></i> Setup
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Alle Features im Überblick');
$fragment->setVar('body', $features, false);
echo $fragment->parse('core/page/section.php');

// Schnellstart und System-Status in zwei Spalten
$quickstart = '
<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><i class="fa fa-rocket"></i> Schnellstart</h4>
            </div>
            <div class="panel-body">
                <h5>Einfaches PDF:</h5>
                <pre><code>$pdf = new PdfOut();
$pdf->setName(\'dokument\')
    ->setHtml(\'&lt;h1&gt;Hallo Welt!&lt;/h1&gt;\')
    ->run();</code></pre>
                
                <h5>Mit digitaler Signatur:</h5>
                <pre><code>$pdf->enableDigitalSignature()
    ->setVisibleSignature(120, 200, 70, 30)
    ->run();</code></pre>
                
                <h5>Mit Passwortschutz:</h5>
                <pre><code>$pdf->enablePasswordProtection(
    \'user123\',     // Benutzer-Passwort
    \'owner456\',    // Besitzer-Passwort
    [\'print\']      // Erlaubte Aktionen
);</code></pre>
                
                <div class="text-center" style="margin-top: 15px;">
                    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/demo']) . '" class="btn btn-primary">
                        <i class="fa fa-play"></i> Live-Demo starten
                    </a>
                    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/docs']) . '" class="btn btn-default">
                        <i class="fa fa-book"></i> Dokumentation
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h4><i class="fa fa-tachometer"></i> System-Status</h4>
            </div>
            <div class="panel-body">
                <table class="table table-condensed">
                    <tr>
                        <td><i class="fa fa-folder"></i> Zertifikat-Ordner:</td>
                        <td>' . (is_dir($addon->getDataPath('certificates')) ? '<span class="text-success"><i class="fa fa-check"></i> Verfügbar</span>' : '<span class="text-danger"><i class="fa fa-times"></i> Fehlt</span>') . '</td>
                    </tr>
                    <tr>
                        <td><i class="fa fa-cog"></i> PDF-Engine:</td>
                        <td>' . (class_exists('TCPDF') ? '<span class="text-success"><i class="fa fa-check"></i> Erweitert</span>' : '<span class="text-warning"><i class="fa fa-info-circle"></i> Standard</span>') . '</td>
                    </tr>
                    <tr>
                        <td><i class="fa fa-database"></i> Cache:</td>
                        <td>' . (is_dir(rex_path::addonCache('pdfout')) ? '<span class="text-success"><i class="fa fa-check"></i> Bereit</span>' : '<span class="text-danger"><i class="fa fa-times"></i> Fehlt</span>') . '</td>
                    </tr>
                    <tr>
                        <td><i class="fa fa-key"></i> OpenSSL:</td>
                        <td>' . (function_exists('openssl_pkcs12_export') ? '<span class="text-success"><i class="fa fa-check"></i> Verfügbar</span>' : '<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> Eingeschränkt</span>') . '</td>
                    </tr>
                </table>
                
                <div class="alert alert-info">
                    <strong><i class="fa fa-info-circle"></i> Erste Schritte:</strong>
                    <ol class="mb-0">
                        <li>Konfiguration in den <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/config']) . '" class="alert-link">Einstellungen</a> vornehmen</li>
                        <li>Zertifikat in <code>data/addons/pdfout/certificates/</code> ablegen</li>
                        <li>Features mit den <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/demo']) . '" class="alert-link">Demos</a> testen</li>
                    </ol>
                </div>
                
                <div class="text-center">
                    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/config']) . '" class="btn btn-info">
                        <i class="fa fa-wrench"></i> Konfiguration
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Schnellstart & System');
$fragment->setVar('body', $quickstart, false);
echo $fragment->parse('core/page/section.php');

// Verwendungsbeispiele
$usecases = '
<div class="row">
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading text-center">
                <i class="fa fa-file-text fa-2x text-primary"></i>
                <h4>Verträge & Rechnungen</h4>
            </div>
            <div class="panel-body">
                <p>Signieren Sie rechtsgültige Dokumente digital und schützen Sie sie vor unbefugten Änderungen.</p>
                <ul class="list-unstyled">
                    <li><i class="fa fa-check text-success"></i> Rechtsgültige digitale Signaturen</li>
                    <li><i class="fa fa-check text-success"></i> Fälschungsschutz</li>
                    <li><i class="fa fa-check text-success"></i> Automatische Archivierung</li>
                    <li><i class="fa fa-check text-success"></i> Compliance-konform</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading text-center">
                <i class="fa fa-graduation-cap fa-2x text-success"></i>
                <h4>Zertifikate & Diplome</h4>
            </div>
            <div class="panel-body">
                <p>Erstellen Sie fälschungssichere Bildungsnachweise mit digitaler Signatur.</p>
                <ul class="list-unstyled">
                    <li><i class="fa fa-check text-success"></i> Fälschungssichere Zertifikate</li>
                    <li><i class="fa fa-check text-success"></i> Digitale Verifikation</li>
                    <li><i class="fa fa-check text-success"></i> Professionelle Layouts</li>
                    <li><i class="fa fa-check text-success"></i> Internationaler Standard</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading text-center">
                <i class="fa fa-shield fa-2x text-warning"></i>
                <h4>Vertrauliche Berichte</h4>
            </div>
            <div class="panel-body">
                <p>Schützen Sie sensible Informationen mit Passwörtern und Zugriffsbeschränkungen.</p>
                <ul class="list-unstyled">
                    <li><i class="fa fa-check text-success"></i> Passwort-geschützt</li>
                    <li><i class="fa fa-check text-success"></i> Kontrollierte Berechtigungen</li>
                    <li><i class="fa fa-check text-success"></i> Sichere Übertragung</li>
                    <li><i class="fa fa-check text-success"></i> Audit-Trail</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="text-center" style="margin-top: 30px;">
    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/demo']) . '" class="btn btn-lg btn-success">
        <i class="fa fa-play-circle"></i> Live-Demos ausprobieren
    </a>
    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/docs']) . '" class="btn btn-lg btn-primary">
        <i class="fa fa-book"></i> Vollständige Dokumentation
    </a>
    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/config']) . '" class="btn btn-lg btn-info">
        <i class="fa fa-cog"></i> Konfiguration starten
    </a>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Praxis-Anwendungen');
$fragment->setVar('body', $usecases, false);
echo $fragment->parse('core/page/section.php');
