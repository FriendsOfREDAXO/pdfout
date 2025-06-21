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

// Aktuelle Konfiguration laden
$config = $this->getConfig();

// Formular erstellen
$content = '';

// Certificate Configuration Section
$formElements = [];

$n = [];
$n['label'] = '<label for="default_certificate_path">Standard-Zertifikatspfad (.p12)</label>';
$n['field'] = '<input class="form-control" type="text" id="default_certificate_path" name="default_certificate_path" value="' . rex_escape($config['default_certificate_path'] ?? '') . '" placeholder="' . rex_escape($addon->getDataPath('certificates/default.p12')) . '"/>';
$n['note'] = 'Vollständiger Pfad zum Standard-Zertifikat für die digitale Signierung. Leer lassen für Standardpfad: ' . $addon->getDataPath('certificates/default.p12');
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="default_certificate_password">Standard-Zertifikatspasswort</label>';
$n['field'] = '<input class="form-control" type="password" id="default_certificate_password" name="default_certificate_password" value="' . rex_escape($config['default_certificate_password'] ?? '') . '"/>';
$n['note'] = 'Passwort für das Standard-Zertifikat';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="enable_signature_by_default">Digitale Signierung standardmäßig aktivieren</label>';
$n['field'] = '<input type="checkbox" id="enable_signature_by_default" name="enable_signature_by_default" value="1"' . (($config['enable_signature_by_default'] ?? false) ? ' checked="checked"' : '') . '/>';
$n['note'] = 'Wenn aktiviert, werden alle PDFs standardmäßig digital signiert';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="enable_password_protection_by_default">Passwortschutz standardmäßig aktivieren</label>';
$n['field'] = '<input type="checkbox" id="enable_password_protection_by_default" name="enable_password_protection_by_default" value="1"' . (($config['enable_password_protection_by_default'] ?? false) ? ' checked="checked"' : '') . '/>';
$n['note'] = 'Wenn aktiviert, sind alle PDFs standardmäßig passwortgeschützt';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

// Test-Zertifikat Generator Sektion
$testCertContent = '
<div class="alert alert-info">
    <h4>Test-Zertifikat Generator</h4>
    <p>Für Demo- und Testzwecke können Sie hier automatisch ein Test-Zertifikat generieren lassen.</p>
    <p><strong>Hinweis:</strong> Dieses Zertifikat ist nur für Testzwecke geeignet und sollte nicht in Produktionsumgebungen verwendet werden!</p>
</div>

<div class="row">
    <div class="col-md-6">
        <h5>Aktueller Status</h5>
        <table class="table table-striped">
            <tr>
                <td><strong>Test-Zertifikat vorhanden:</strong></td>
                <td>' . (file_exists($addon->getDataPath('certificates/default.p12')) ? '<span class="text-success">✓ Ja</span>' : '<span class="text-danger">✗ Nein</span>') . '</td>
            </tr>
            <tr>
                <td><strong>OpenSSL verfügbar:</strong></td>
                <td>' . (exec('which openssl') ? '<span class="text-success">✓ Ja</span>' : '<span class="text-danger">✗ Nein</span>') . '</td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h5>Test-Zertifikat erstellen</h5>
        <form method="post">
            <input type="hidden" name="generate-test-certificate" value="1">
            <button type="submit" class="btn btn-primary" onclick="return confirm(\'Möchten Sie ein neues Test-Zertifikat generieren? Ein vorhandenes Zertifikat wird überschrieben.\')">
                <i class="rex-icon fa-certificate"></i> Test-Zertifikat generieren
            </button>
        </form>
        <p class="help-block">
            <strong>Parameter:</strong><br>
            - Passwort: redaxo123<br>
            - Gültigkeitsdauer: 365 Tage<br>
            - Algorithmus: RSA 2048 Bit
        </p>
    </div>
</div>
';

$content .= $testCertContent;

// Signature Position Section
$formElements = [];

$n = [];
$n['label'] = '<label for="default_signature_position_x">Standard Signatur Position X</label>';
$n['field'] = '<input class="form-control" type="number" id="default_signature_position_x" name="default_signature_position_x" value="' . rex_escape($config['default_signature_position_x'] ?? 180) . '"/>';
$n['note'] = 'X-Position der sichtbaren Signatur (in mm)';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="default_signature_position_y">Standard Signatur Position Y</label>';
$n['field'] = '<input class="form-control" type="number" id="default_signature_position_y" name="default_signature_position_y" value="' . rex_escape($config['default_signature_position_y'] ?? 60) . '"/>';
$n['note'] = 'Y-Position der sichtbaren Signatur (in mm)';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="default_signature_width">Standard Signatur Breite</label>';
$n['field'] = '<input class="form-control" type="number" id="default_signature_width" name="default_signature_width" value="' . rex_escape($config['default_signature_width'] ?? 15) . '"/>';
$n['note'] = 'Breite der sichtbaren Signatur (in mm)';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="default_signature_height">Standard Signatur Höhe</label>';
$n['field'] = '<input class="form-control" type="number" id="default_signature_height" name="default_signature_height" value="' . rex_escape($config['default_signature_height'] ?? 15) . '"/>';
$n['note'] = 'Höhe der sichtbaren Signatur (in mm)';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

// Submit Button
$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="config-submit" value="1">Konfiguration speichern</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/submit.php');

// Formular-Wrapper
$form = '
<form action="' . rex_url::currentBackendPage() . '" method="post">
    ' . $content . '
</form>';

// Ausgabe der Seite
$fragment = new rex_fragment();
$fragment->setVar('title', 'TCPDF Konfiguration');
$fragment->setVar('body', $form, false);
echo $fragment->parse('core/page/section.php');

// Information Section
$info = '
<h3>Digitale Signierung</h3>
<p>Für die digitale Signierung benötigen Sie ein gültiges .p12-Zertifikat. Dieses können Sie:</p>
<ul>
    <li>Von einer Zertifizierungsstelle (CA) erwerben</li>
    <li>Selbst erstellen (nur für Testzwecke geeignet)</li>
</ul>

<p><strong>Zertifikat-Ordner:</strong> <code>' . rex_escape($addon->getDataPath('certificates/')) . '</code></p>

<h3>Verwendung in der PDFOut-Klasse</h3>
<pre><code>// Digitale Signierung aktivieren
$pdf->enableDigitalSignature(
    \'/path/to/certificate.p12\',  // Zertifikatspfad (optional, verwendet Standard)
    \'password\',                    // Zertifikatspasswort
    \'Max Mustermann\',             // Name des Signierers
    \'Musterstadt\',                // Ort
    \'Dokument-Freigabe\',          // Grund
    \'max@example.com\'             // Kontakt
);

// Sichtbare Signatur positionieren
$pdf->setVisibleSignature(180, 60, 15, 15, -1);

// Passwortschutz aktivieren
$pdf->enablePasswordProtection(\'user_password\', \'owner_password\', [\'print\', \'copy\']);

// PDF erstellen
$pdf->run();</code></pre>

<h3>Nachträgliche Signierung</h3>
<pre><code>// Existierendes PDF signieren
$pdf = new PdfOut();
$success = $pdf->signExistingPdf(
    \'/path/to/input.pdf\',
    \'/path/to/output_signed.pdf\',
    \'/path/to/certificate.p12\',
    \'password\',
    [
        \'Name\' => \'Max Mustermann\',
        \'Location\' => \'Musterstadt\',
        \'Reason\' => \'Nachträgliche Signierung\',
        \'visible\' => true,
        \'x\' => 180,
        \'y\' => 60,
        \'width\' => 15,
        \'height\' => 15
    ]
);</code></pre>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Dokumentation');
$fragment->setVar('body', $info, false);
$fragment->setVar('collapse', true);
$fragment->setVar('collapsed', true);
echo $fragment->parse('core/page/section.php');
