<?php
/**
 * PDFOut Übersichtsseite
 */

$addon = rex_addon::get('pdfout');

// Willkommenstext
$content = '
<div class="row">
    <div class="col-md-8">
        <h2>Willkommen bei PDFOut</h2>
        <p class="lead">Das umfassende PDF-Addon für REDAXO mit erweiterten Funktionen für professionelle PDF-Erstellung.</p>
        
        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h4><i class="fa fa-certificate"></i> Digitale Signierung</h4>
                    </div>
                    <div class="panel-body">
                        <ul class="list-unstyled">
                            <li><i class="fa fa-check text-success"></i> Sichtbare und unsichtbare Signaturen</li>
                            <li><i class="fa fa-check text-success"></i> Standard- und benutzerdefinierte Zertifikate</li>
                            <li><i class="fa fa-check text-success"></i> Nachträgliche Signierung vorhandener PDFs</li>
                            <li><i class="fa fa-check text-success"></i> X.509-Zertifikat-Support</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="panel panel-success">
                    <div class="panel-heading">
                        <h4><i class="fa fa-lock"></i> Passwortschutz</h4>
                    </div>
                    <div class="panel-body">
                        <ul class="list-unstyled">
                            <li><i class="fa fa-check text-success"></i> Benutzer- und Besitzer-Passwörter</li>
                            <li><i class="fa fa-check text-success"></i> Granulare Berechtigungskontrolle</li>
                            <li><i class="fa fa-check text-success"></i> Drucken, Kopieren, Bearbeiten kontrollieren</li>
                            <li><i class="fa fa-check text-success"></i> 128-Bit RC4 Verschlüsselung</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <h4><i class="fa fa-file-pdf-o"></i> PDF-Erzeugung</h4>
                    </div>
                    <div class="panel-body">
                        <ul class="list-unstyled">
                            <li><i class="fa fa-check text-success"></i> HTML zu PDF Konvertierung</li>
                            <li><i class="fa fa-check text-success"></i> CSS-Unterstützung für Layout</li>
                            <li><i class="fa fa-check text-success"></i> Responsive Design Support</li>
                            <li><i class="fa fa-check text-success"></i> Template-basierte Erstellung</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="panel panel-warning">
                    <div class="panel-heading">
                        <h4><i class="fa fa-cogs"></i> Erweiterte Features</h4>
                    </div>
                    <div class="panel-body">
                        <ul class="list-unstyled">
                            <li><i class="fa fa-check text-success"></i> Automatische Engine-Auswahl</li>
                            <li><i class="fa fa-check text-success"></i> Metadaten-Management</li>
                            <li><i class="fa fa-check text-success"></i> Wasserzeichen und Hintergründe</li>
                            <li><i class="fa fa-check text-success"></i> Mehrsprachige Unterstützung</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <h3><i class="fa fa-rocket"></i> Erste Schritte</h3>
        <div class="alert alert-info">
            <ol>
                <li><strong>Konfiguration:</strong> Besuchen Sie die <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/config']) . '" class="alert-link">Konfigurationsseite</a> um Ihre Standard-Einstellungen festzulegen.</li>
                <li><strong>Zertifikat:</strong> Platzieren Sie Ihr .p12-Zertifikat im Ordner <code>' . rex_escape($addon->getDataPath('certificates/')) . '</code></li>
                <li><strong>Demo:</strong> Testen Sie die Features mit unseren <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/demo']) . '" class="alert-link">Demo-Beispielen</a>.</li>
                <li><strong>Dokumentation:</strong> Lesen Sie die vollständige <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/docs']) . '" class="alert-link">Dokumentation</a> mit Code-Beispielen.</li>
            </ol>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><i class="fa fa-code"></i> Schnellstart</h4>
            </div>
            <div class="panel-body">
                <h5>Einfaches PDF:</h5>
                <pre><code>$pdf = new PdfOut();
$pdf->setName(\'dokument\')
    ->setHtml(\'&lt;h1&gt;Hallo Welt!&lt;/h1&gt;\')
    ->run();</code></pre>
                
                <h5>Mit digitaler Signatur:</h5>
                <pre><code>$pdf->enableDigitalSignature()
    ->run();</code></pre>
                
                <h5>Mit Passwortschutz:</h5>
                <pre><code>$pdf->enablePasswordProtection(
    \'user123\', 
    \'owner456\', 
    [\'print\']
);</code></pre>
                
                <div class="text-center" style="margin-top: 15px;">
                    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/demo']) . '" class="btn btn-primary btn-sm">
                        <i class="fa fa-play"></i> Live-Demo starten
                    </a>
                </div>
            </div>
        </div>
        
        <div class="panel panel-info">
            <div class="panel-heading">
                <h4><i class="fa fa-tachometer"></i> Systemstatus</h4>
            </div>
            <div class="panel-body">
                <ul class="list-unstyled">
                    <li><i class="fa fa-folder"></i> Zertifikat-Ordner: ' . (is_dir($addon->getDataPath('certificates')) ? '<span class="text-success"><i class="fa fa-check"></i> Verfügbar</span>' : '<span class="text-danger"><i class="fa fa-times"></i> Fehlt</span>') . '</li>
                    <li><i class="fa fa-cog"></i> PDF-Engine: ' . (class_exists('TCPDF') ? '<span class="text-success"><i class="fa fa-check"></i> Erweitert</span>' : '<span class="text-warning"><i class="fa fa-info-circle"></i> Standard</span>') . '</li>
                    <li><i class="fa fa-database"></i> Cache: ' . (is_dir(rex_path::addonCache('pdfout')) ? '<span class="text-success"><i class="fa fa-check"></i> Bereit</span>' : '<span class="text-danger"><i class="fa fa-times"></i> Fehlt</span>') . '</li>
                    <li><i class="fa fa-key"></i> OpenSSL: ' . (function_exists('openssl_pkcs12_export') ? '<span class="text-success"><i class="fa fa-check"></i> Verfügbar</span>' : '<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> Eingeschränkt</span>') . '</li>
                </ul>
                
                <div class="text-center" style="margin-top: 15px;">
                    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/config']) . '" class="btn btn-info btn-sm">
                        <i class="fa fa-wrench"></i> Konfiguration
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', '<i class="fa fa-home"></i> PDFOut Übersicht');
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// Alle Features Sektion
$features = '
<h3><i class="fa fa-star"></i> Alle Features im Überblick</h3>

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

<div class="row" style="margin-top: 20px;">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><i class="fa fa-lightbulb-o"></i> Verwendungsbeispiele</h4>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-4">
                        <h5><i class="fa fa-file-text"></i> Verträge & Rechnungen</h5>
                        <p>Signieren Sie rechtsgültige Dokumente digital und schützen Sie sie vor unbefugten Änderungen.</p>
                    </div>
                    <div class="col-md-4">
                        <h5><i class="fa fa-graduation-cap"></i> Zertifikate & Diplome</h5>
                        <p>Erstellen Sie fälschungssichere Bildungsnachweise mit digitaler Signatur.</p>
                    </div>
                    <div class="col-md-4">
                        <h5><i class="fa fa-shield"></i> Vertrauliche Berichte</h5>
                        <p>Schützen Sie sensible Informationen mit Passwörtern und Zugriffsbeschränkungen.</p>
                    </div>
                </div>
                
                <div class="text-center" style="margin-top: 20px;">
                    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/docs']) . '" class="btn btn-lg btn-primary">
                        <i class="fa fa-book"></i> Vollständige Dokumentation
                    </a>
                    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/demo']) . '" class="btn btn-lg btn-success">
                        <i class="fa fa-play-circle"></i> Live-Demos starten
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Feature-Übersicht');
$fragment->setVar('body', $features, false);
echo $fragment->parse('core/page/section.php');

// API-Referenz (kompakt)
$api = '
<h3><i class="fa fa-code"></i> API-Schnellreferenz</h3>

<div class="row">
    <div class="col-md-6">
        <h4>Grundlegende Methoden</h4>
        <table class="table table-striped table-condensed">
            <tbody>
                <tr><td><code>setName($name)</code></td><td>PDF-Dateiname festlegen</td></tr>
                <tr><td><code>setHtml($html)</code></td><td>HTML-Inhalt setzen</td></tr>
                <tr><td><code>setCss($css)</code></td><td>CSS-Styles hinzufügen</td></tr>
                <tr><td><code>run()</code></td><td>PDF generieren und ausgeben</td></tr>
            </tbody>
        </table>
    </div>
    
    <div class="col-md-6">
        <h4>Erweiterte Methoden</h4>
        <table class="table table-striped table-condensed">
            <tbody>
                <tr><td><code>enableDigitalSignature()</code></td><td>Digitale Signatur aktivieren</td></tr>
                <tr><td><code>setVisibleSignature()</code></td><td>Sichtbare Signatur positionieren</td></tr>
                <tr><td><code>enablePasswordProtection()</code></td><td>Passwortschutz aktivieren</td></tr>
                <tr><td><code>signExistingPdf()</code></td><td>Vorhandene PDF signieren</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-info">
    <h4><i class="fa fa-info-circle"></i> Tipp</h4>
    <p>Bei Verwendung von <code>enableDigitalSignature()</code> oder <code>enablePasswordProtection()</code> wird automatisch die erweiterte PDF-Engine aktiviert, die alle professionellen Features unterstützt.</p>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', '<i class="fa fa-terminal"></i> API-Referenz');
$fragment->setVar('body', $api, false);
$fragment->setVar('collapse', true);
$fragment->setVar('collapsed', true);
echo $fragment->parse('core/page/section.php');
