<?php
/**
 * PDFOut Konfigurationsseite
 */

$addon = rex_addon::get('pdfout');

// Formulardaten verarbeiten
if (rex_post('config-submit', 'bool')) {
    $this->setConfig([
        'default_certificate_path' => rex_post('default_certificate_path', 'string', ''),
        'default_certificate_password' => rex_post('default_certificate_password', 'string', ''),
        'enable_signature_by_default' => rex_post('enable_signature_by_default', 'bool', false),
        'enable_password_protection_by_default' => rex_post('enable_password_protection_by_default', 'bool', false),
        'default_signature_position_x' => rex_post('default_signature_position_x', 'int', 180),
        'default_signature_position_y' => rex_post('default_signature_position_y', 'int', 60),
        'default_signature_width' => rex_post('default_signature_width', 'int', 15),
        'default_signature_height' => rex_post('default_signature_height', 'int', 15),
        'enable_debug_mode' => rex_post('enable_debug_mode', 'bool', false),
        'log_pdf_generation' => rex_post('log_pdf_generation', 'bool', false),
        'temp_file_cleanup' => rex_post('temp_file_cleanup', 'bool', true),
    ]);
    
    echo rex_view::success('Konfiguration wurde gespeichert!');
}

// Test-Zertifikat generieren
if (rex_post('generate-test-certificate', 'bool')) {
    try {
        $certDir = $addon->getDataPath('certificates/');
        $certPath = $certDir . 'default.p12';
        
        // Verzeichnis erstellen falls es nicht existiert
        if (!is_dir($certDir)) {
            rex_dir::create($certDir);
        }
        
        // OpenSSL-Befehle für Zertifikatserstellung
        $privateKeyFile = $certDir . 'temp_private.key';
        $certFile = $certDir . 'temp_cert.crt';
        $password = 'redaxo123';
        
        // 1. Private Key erstellen
        $privateKeyCmd = sprintf(
            'openssl genrsa -out %s 2048',
            escapeshellarg($privateKeyFile)
        );
        
        // 2. Zertifikat erstellen
        $certCmd = sprintf(
            'openssl req -new -x509 -key %s -out %s -days 365 -subj "/C=DE/ST=Test/L=Test/O=REDAXO/OU=PDFOut/CN=Test Certificate/emailAddress=test@redaxo.demo"',
            escapeshellarg($privateKeyFile),
            escapeshellarg($certFile)
        );
        
        // 3. P12 erstellen
        $p12Cmd = sprintf(
            'openssl pkcs12 -export -out %s -inkey %s -in %s -password pass:%s',
            escapeshellarg($certPath),
            escapeshellarg($privateKeyFile),
            escapeshellarg($certFile),
            $password
        );
        
        // Befehle ausführen
        exec($privateKeyCmd, $output1, $return1);
        if ($return1 !== 0) {
            throw new Exception('Fehler beim Erstellen des Private Keys');
        }
        
        exec($certCmd, $output2, $return2);
        if ($return2 !== 0) {
            throw new Exception('Fehler beim Erstellen des Zertifikats');
        }
        
        exec($p12Cmd, $output3, $return3);
        if ($return3 !== 0) {
            throw new Exception('Fehler beim Erstellen der P12-Datei');
        }
        
        // Temporäre Dateien löschen
        if (file_exists($privateKeyFile)) unlink($privateKeyFile);
        if (file_exists($certFile)) unlink($certFile);
        
        // Konfiguration aktualisieren
        $this->setConfig([
            'default_certificate_path' => $certPath,
            'default_certificate_password' => $password,
        ]);
        
        echo rex_view::success('Test-Zertifikat wurde erfolgreich generiert!<br>Pfad: ' . $certPath . '<br>Passwort: ' . $password);
        
    } catch (Exception $e) {
        echo rex_view::error('Fehler beim Generieren des Test-Zertifikats: ' . $e->getMessage() . '<br><br>Stellen Sie sicher, dass OpenSSL auf dem Server installiert und verfügbar ist.');
    }
}

// Test-PDF generieren
if (rex_post('generate-test-pdf', 'bool')) {
    try {
        $pdf = new PdfOut();
        $pdf->setName('config_test_pdf')
            ->setHtml('<h1>Konfiguration Test PDF</h1><p>Dieses PDF wurde von der Konfigurationsseite generiert.</p><p>Erstellungszeit: ' . date('d.m.Y H:i:s') . '</p>')
            ->run();
    } catch (Exception $e) {
        echo rex_view::error('Fehler beim Generieren des Test-PDFs: ' . $e->getMessage());
    }
}

// Aktuelle Konfiguration laden
$config = $this->getConfig();

// ========================================
// ALLGEMEINE KONFIGURATION
// ========================================

$form = '<form action="' . rex_url::currentBackendPage() . '" method="post">';

// Standard-Einstellungen für PDF-Generierung
$formElements = [];

$n = [];
$n['label'] = '<label for="enable_signature_by_default"><i class="fa fa-edit"></i> Digitale Signierung standardmäßig aktivieren</label>';
$n['field'] = '<input type="checkbox" id="enable_signature_by_default" name="enable_signature_by_default" value="1"' . (($config['enable_signature_by_default'] ?? false) ? ' checked="checked"' : '') . '/>';
$n['note'] = 'Wenn aktiviert, werden alle PDFs standardmäßig digital signiert (sofern Berechtigung und Zertifikat vorhanden).';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="enable_password_protection_by_default"><i class="fa fa-shield"></i> Passwortschutz standardmäßig aktivieren</label>';
$n['field'] = '<input type="checkbox" id="enable_password_protection_by_default" name="enable_password_protection_by_default" value="1"' . (($config['enable_password_protection_by_default'] ?? false) ? ' checked="checked"' : '') . '/>';
$n['note'] = 'Wenn aktiviert, sind alle PDFs standardmäßig passwortgeschützt.';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$defaultSettings = $fragment->parse('core/form/form.php');

// Signatur-Position Standard-Einstellungen
$formElements = [];

$n = [];
$n['label'] = '<label for="default_signature_position_x"><i class="fa fa-arrows-h"></i> Standard Signatur Position X (mm)</label>';
$n['field'] = '<input class="form-control" type="number" id="default_signature_position_x" name="default_signature_position_x" value="' . rex_escape($config['default_signature_position_x'] ?? 180) . '" min="0" max="300"/>';
$n['note'] = 'X-Position der sichtbaren Signatur vom linken Rand (in Millimetern).';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="default_signature_position_y"><i class="fa fa-arrows-v"></i> Standard Signatur Position Y (mm)</label>';
$n['field'] = '<input class="form-control" type="number" id="default_signature_position_y" name="default_signature_position_y" value="' . rex_escape($config['default_signature_position_y'] ?? 60) . '" min="0" max="400"/>';
$n['note'] = 'Y-Position der sichtbaren Signatur vom oberen Rand (in Millimetern).';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="default_signature_width"><i class="fa fa-resize-horizontal"></i> Standard Signatur Breite (mm)</label>';
$n['field'] = '<input class="form-control" type="number" id="default_signature_width" name="default_signature_width" value="' . rex_escape($config['default_signature_width'] ?? 15) . '" min="5" max="100"/>';
$n['note'] = 'Breite der sichtbaren Signatur (in Millimetern).';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="default_signature_height"><i class="fa fa-resize-vertical"></i> Standard Signatur Höhe (mm)</label>';
$n['field'] = '<input class="form-control" type="number" id="default_signature_height" name="default_signature_height" value="' . rex_escape($config['default_signature_height'] ?? 15) . '" min="5" max="50"/>';
$n['note'] = 'Höhe der sichtbaren Signatur (in Millimetern).';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$signatureSettings = $fragment->parse('core/form/form.php');

// System-Einstellungen
$formElements = [];

$n = [];
$n['label'] = '<label for="enable_debug_mode"><i class="fa fa-bug"></i> Debug-Modus aktivieren</label>';
$n['field'] = '<input type="checkbox" id="enable_debug_mode" name="enable_debug_mode" value="1"' . (($config['enable_debug_mode'] ?? false) ? ' checked="checked"' : '') . '/>';
$n['note'] = 'Aktiviert erweiterte Fehlerausgaben und Logging für Debugging-Zwecke.';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="log_pdf_generation"><i class="fa fa-list-alt"></i> PDF-Generierung protokollieren</label>';
$n['field'] = '<input type="checkbox" id="log_pdf_generation" name="log_pdf_generation" value="1"' . (($config['log_pdf_generation'] ?? false) ? ' checked="checked"' : '') . '/>';
$n['note'] = 'Protokolliert alle PDF-Generierungen im REDAXO-Log.';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="temp_file_cleanup"><i class="fa fa-trash"></i> Temporäre Dateien automatisch löschen</label>';
$n['field'] = '<input type="checkbox" id="temp_file_cleanup" name="temp_file_cleanup" value="1"' . (($config['temp_file_cleanup'] ?? true) ? ' checked="checked"' : '') . '/>';
$n['note'] = 'Löscht temporäre Dateien automatisch nach der PDF-Generierung.';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$systemSettings = $fragment->parse('core/form/form.php');

// Submit Button
$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="config-submit" value="1"><i class="fa fa-save"></i> Konfiguration speichern</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$submitButton = $fragment->parse('core/form/submit.php');

// Tabs für bessere Übersicht
$generalConfigContent = '
<div class="row">
    <div class="col-md-12">
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation" class="active">
                <a href="#defaults" aria-controls="defaults" role="tab" data-toggle="tab">
                    <i class="fa fa-cog"></i> Standard-Einstellungen
                </a>
            </li>
            <li role="presentation">
                <a href="#signature" aria-controls="signature" role="tab" data-toggle="tab">
                    <i class="fa fa-edit"></i> Signatur-Position
                </a>
            </li>
            <li role="presentation">
                <a href="#system" aria-controls="system" role="tab" data-toggle="tab">
                    <i class="fa fa-server"></i> System
                </a>
            </li>
        </ul>
        
        <div class="tab-content" style="padding-top: 20px;">
            <div role="tabpanel" class="tab-pane active" id="defaults">
                ' . $defaultSettings . '
                <div class="alert alert-success">
                    <strong><i class="fa fa-info-circle"></i> Hinweis:</strong>
                    Diese Einstellungen gelten als Standard für neue PDF-Generierungen. 
                    Sie können in der jeweiligen Implementierung überschrieben werden.
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="signature">
                ' . $signatureSettings . '
                <div class="alert alert-success">
                    <strong><i class="fa fa-info-circle"></i> Positionierungs-Hilfe:</strong>
                    Die Signatur wird relativ zur linken oberen Ecke des Dokuments positioniert. 
                    Übliche Werte: X=180mm, Y=60mm für eine Signatur rechts unten.
                </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="system">
                ' . $systemSettings . '
                <div class="alert alert-warning">
                    <strong><i class="fa fa-exclamation-triangle"></i> Debug-Modus:</strong>
                    Aktivieren Sie den Debug-Modus nur in Entwicklungsumgebungen, da sensible Informationen 
                    ausgegeben werden können.
                </div>
            </div>
        </div>
        
        ' . $submitButton . '
    </div>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Allgemeine Konfiguration');
$fragment->setVar('body', '<form action="' . rex_url::currentBackendPage() . '" method="post">' . $generalConfigContent . '</form>', false);
echo $fragment->parse('core/page/section.php');

// ========================================
// DEMO & TEST EINSTELLUNGEN
// ========================================

$demoTestContent = '
<div class="alert alert-warning">
    <h4><i class="fa fa-flask"></i> Demo & Test Bereich</h4>
    <p><strong>Wichtig:</strong> Dieser Bereich dient ausschließlich Demo- und Testzwecken. 
    Die hier generierten Zertifikate und Einstellungen sind <strong>nicht für produktive Systeme geeignet!</strong></p>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-certificate"></i> Test-Zertifikat Generator</h3>
            </div>
            <div class="panel-body">
                <h5>Aktueller Status:</h5>
                <table class="table table-condensed">
                    <tr>
                        <td><strong>Test-Zertifikat:</strong></td>
                        <td>' . (file_exists($addon->getDataPath('certificates/default.p12')) ? 
                            '<span class="text-success"><i class="fa fa-check"></i> Vorhanden</span>' : 
                            '<span class="text-danger"><i class="fa fa-times"></i> Fehlt</span>') . '</td>
                    </tr>
                    <tr>
                        <td><strong>OpenSSL:</strong></td>
                        <td>' . (exec('which openssl') ? 
                            '<span class="text-success"><i class="fa fa-check"></i> Verfügbar</span>' : 
                            '<span class="text-danger"><i class="fa fa-times"></i> Nicht verfügbar</span>') . '</td>
                    </tr>
                </table>
                
                <form method="post" style="margin-top: 15px;">
                    <input type="hidden" name="generate-test-certificate" value="1">
                    <button type="submit" class="btn btn-primary btn-block" onclick="return confirm(\'Möchten Sie ein neues Test-Zertifikat generieren? Ein vorhandenes wird überschrieben.\')">
                        <i class="fa fa-certificate"></i> Test-Zertifikat generieren
                    </button>
                </form>
                
                <div class="alert alert-warning" style="margin-top: 15px; margin-bottom: 0;">
                    <small>
                        <strong>Parameter:</strong><br>
                        • Passwort: redaxo123<br>
                        • Gültigkeit: 365 Tage<br>
                        • Algorithmus: RSA 2048 Bit<br>
                        <strong>⚠️ Nur für Testzwecke!</strong>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="panel panel-success">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-file-pdf-o"></i> Test-PDF Generator</h3>
            </div>
            <div class="panel-body">
                <p>Erstellen Sie ein einfaches Test-PDF, um die Grundfunktionalität zu prüfen.</p>
                
                <form method="post" target="_blank" style="margin-bottom: 15px;">
                    <input type="hidden" name="generate-test-pdf" value="1">
                    <button type="submit" class="btn btn-success btn-block">
                        <i class="fa fa-download"></i> Test-PDF erstellen
                    </button>
                </form>
                
                <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/demo']) . '" class="btn btn-info btn-block">
                    <i class="fa fa-play"></i> Zur Demo-Seite
                </a>
                
                <div class="alert alert-success" style="margin-top: 15px; margin-bottom: 0;">
                    <small>
                        <strong>Hinweis:</strong> Das Test-PDF wird direkt heruntergeladen und enthält grundlegende Informationen zur Funktionsprüfung.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-wrench"></i> Demo-Zertifikat Konfiguration</h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Aktueller Zertifikatspfad:</h5>
                        <code>' . rex_escape($addon->getDataPath('certificates/default.p12')) . '</code>
                        
                        <h5 style="margin-top: 20px;">Für Demo/Test-Zwecke:</h5>
                        <ul>
                            <li><strong>Pfad:</strong> <code>' . rex_escape($config['default_certificate_path'] ?? $addon->getDataPath('certificates/default.p12')) . '</code></li>
                            <li><strong>Passwort:</strong> <code>redaxo123</code> (Standard für Test-Zertifikat)</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-danger">
                            <h5><i class="fa fa-exclamation-triangle"></i> Produktive Nutzung</h5>
                            <p>Für produktive Systeme:</p>
                            <ul>
                                <li>Verwenden Sie echte CA-Zertifikate</li>
                                <li>Speichern Sie Passwörter in Umgebungsvariablen</li>
                                <li>Setzen Sie sichere Dateiberechtigungen (600)</li>
                                <li>Nutzen Sie Key-Management-Systeme</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Demo & Test Einstellungen');
$fragment->setVar('body', $demoTestContent, false);
echo $fragment->parse('core/page/section.php');
