<?php
/**
 * PDFOut Übersichtsseite
 */

$addon = rex_addon::get('pdfout');

// Feature-Übersicht (Hauptbereich)
$features = '
<div class="row">
    <div class="col-md-8">
        <h2>PDFOut - Professionelle PDF-Erstellung für REDAXO</h2>
        <p class="lead">Das umfassende PDF-Addon mit erweiterten Funktionen für digitale Signaturen, Passwortschutz und professionelle PDF-Erstellung.</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/demo']) . '" class="btn btn-primary btn-lg">
            <i class="fa fa-play-circle"></i> Live-Demos starten
        </a>
    </div>
</div>

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

// Systemstatus
$content = '
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h4><i class="fa fa-tachometer"></i> Systemstatus</h4>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li><i class="fa fa-folder"></i> Zertifikat-Ordner: ' . (is_dir($addon->getDataPath('certificates')) ? '<span class="text-success"><i class="fa fa-check"></i> Verfügbar</span>' : '<span class="text-danger"><i class="fa fa-times"></i> Fehlt</span>') . '</li>
                            <li><i class="fa fa-cog"></i> PDF-Engine: ' . (class_exists('TCPDF') ? '<span class="text-success"><i class="fa fa-check"></i> Erweitert</span>' : '<span class="text-warning"><i class="fa fa-info-circle"></i> Standard</span>') . '</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li><i class="fa fa-database"></i> Cache: ' . (is_dir(rex_path::addonCache('pdfout')) ? '<span class="text-success"><i class="fa fa-check"></i> Bereit</span>' : '<span class="text-danger"><i class="fa fa-times"></i> Fehlt</span>') . '</li>
                            <li><i class="fa fa-key"></i> OpenSSL: ' . (function_exists('openssl_pkcs12_export') ? '<span class="text-success"><i class="fa fa-check"></i> Verfügbar</span>' : '<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> Eingeschränkt</span>') . '</li>
                        </ul>
                    </div>
                </div>
                
                <div class="text-center" style="margin-top: 15px;">
                    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/config']) . '" class="btn btn-info btn-sm">
                        <i class="fa fa-wrench"></i> Konfiguration
                    </a>
                    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/demo']) . '" class="btn btn-success btn-sm">
                        <i class="fa fa-play"></i> Live-Demos
                    </a>
                    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/docs']) . '" class="btn btn-primary btn-sm">
                        <i class="fa fa-book"></i> Dokumentation
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Systemstatus');
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
