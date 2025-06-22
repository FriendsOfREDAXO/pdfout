<?php
/**
 * PDFOut Demo-Seite
 */

use FriendsOfRedaxo\PdfOut\PdfOut;

$addon = rex_addon::get('pdfout');

// Demo-Aktionen und Test-Tools verarbeiten
$message = '';
$error = '';

// Test-Zertifikat generieren
if (rex_post('generate-test-certificate', 'bool')) {
    try {
        $certDir = $addon->getDataPath('certificates/');
        $certPath = $certDir . 'default.p12';
        
        // Verzeichnis erstellen falls es nicht existiert
        if (!is_dir($certDir)) {
            rex_dir::create($certDir);
        }
        
        // Sicherheitsprüfung: Verzeichnis validieren
        $realCertDir = realpath($certDir);
        if ($realCertDir === false || !is_writable($realCertDir)) {
            throw new Exception('Zertifikatsverzeichnis nicht verfügbar oder nicht beschreibbar');
        }
        
        // OpenSSL-Befehle für Zertifikatserstellung
        $privateKeyFile = $realCertDir . DIRECTORY_SEPARATOR . 'temp_private.key';
        $certFile = $realCertDir . DIRECTORY_SEPARATOR . 'temp_cert.crt';
        // Sicherheitswarnung: Für Produktionsumgebungen sollten sichere Passwörter verwendet werden
        $password = 'demo_' . uniqid();
        $certPath = $realCertDir . DIRECTORY_SEPARATOR . 'default.p12';
        
        // Prüfe ob OpenSSL verfügbar ist
        $opensslAvailable = false;
        if (function_exists('exec')) {
            $output = [];
            $returnCode = 0;
            @exec('which openssl 2>/dev/null', $output, $returnCode);
            $opensslAvailable = ($returnCode === 0 && !empty($output));
        }
        
        if (!$opensslAvailable) {
            throw new Exception('OpenSSL nicht verfügbar auf diesem System');
        }
        
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
        
        // Passwort für Demo-Zwecke in Config speichern (nur für Demo!)
        $addon->setConfig('demo_cert_password', $password);
        
        $message = 'Test-Zertifikat wurde erfolgreich generiert!<br>Pfad: ' . $certPath . '<br><strong>Wichtig:</strong> Das Passwort wurde in der Konfiguration gespeichert. In Produktionsumgebungen sollten Passwörter sicher verwaltet werden.';
        
    } catch (Exception $e) {
        $error = 'Fehler beim Generieren des Test-Zertifikats: ' . $e->getMessage() . '<br><br>Stellen Sie sicher, dass OpenSSL auf dem Server installiert und verfügbar ist.';
    }
}

// Test-PDF generieren
if (rex_post('generate-test-pdf', 'bool')) {
    try {
        $pdf = new PdfOut();
        $pdf->setName('demo_test_pdf')
            ->setHtml('<h1>Demo Test PDF</h1><p>Dieses PDF wurde von der Demo-Seite generiert.</p><p>Erstellungszeit: ' . date('d.m.Y H:i:s') . '</p>')
            ->run();
    } catch (Exception $e) {
        $error = 'Fehler beim Generieren des Test-PDFs: ' . $e->getMessage();
    }
}

// Gemeinsame Signatur-Konfiguration für alle Demos
$defaultSignatureConfig = [
    'cert_path' => '', // Standard-Zertifikat verwenden
    'password' => rex_config::get('pdfout', 'demo_cert_password', 'demo_changeme'), // Passwort aus Config laden
    'name' => 'REDAXO Demo',
    'location' => 'Demo-Umgebung',
    'reason' => 'Demo-Signierung',
    'contact' => 'demo@redaxo.org'
];

// Hilfsfunktion zum Anwenden der Signatur-Konfiguration
function applySignatureConfig($pdf, $config, $reason = null) {
    return $pdf->enableDigitalSignature(
        $config['cert_path'],
        $config['password'],
        $config['name'],
        $config['location'],
        $reason ?? $config['reason'],
        $config['contact']
    );
}

if (rex_post('demo-action')) {
    $action = rex_post('demo-action', 'string');
    
    switch ($action) {
        case 'simple_pdf':
            try {
                $pdf = new PdfOut();
                $pdf->setName('demo_simple')
                    ->setHtml('<h1>Einfaches PDF Demo</h1><p>Dies ist ein einfaches PDF ohne erweiterte Features.</p><p>Erstellt mit REDAXO PDFOut.</p>')
                    ->run();
            } catch (Exception $e) {
                $error = 'Fehler beim Erstellen des einfachen PDFs: ' . $e->getMessage();
            }
            break;
            
        case 'signed_pdf':
            try {
                // Berechtigungsprüfung für Signatur-Demo
                if (!rex::getUser() || !rex::getUser()->hasPerm('pdfout[signature]')) {
                    $error = 'Keine Berechtigung für digitale Signaturen. Bitte wenden Sie sich an den Administrator.';
                    break;
                }
                
                $pdf = new PdfOut();
                $pdf->setName('demo_signed')
                    ->setHtml('<h1>Signiertes PDF Demo</h1><p>Dies ist ein digital signiertes PDF.</p><p>Signatur-Informationen finden Sie in den PDF-Eigenschaften.</p><p style="margin-top: 50mm;">Die sichtbare Signatur sollte rechts unten auf dieser Seite erscheinen.</p>');
                
                applySignatureConfig($pdf, $defaultSignatureConfig);
                
                $pdf->setVisibleSignature(120, 200, 70, 30, -1)
                    ->run();
            } catch (Exception $e) {
                $error = 'Fehler beim Erstellen des signierten PDFs: ' . $e->getMessage();
            }
            break;
            
        case 'password_pdf':
            try {
                $pdf = new PdfOut();
                $pdf->setName('demo_password')
                    ->setHtml('<h1>Passwortgeschütztes PDF Demo</h1><p>Dieses PDF ist mit einem Passwort geschützt.</p><p><strong>Passwort:</strong> demo123</p>')
                    ->enablePasswordProtection('demo123', 'owner456', ['print', 'copy'])
                    ->run();
            } catch (Exception $e) {
                $error = 'Fehler beim Erstellen des passwortgeschützten PDFs: ' . $e->getMessage();
            }
            break;
            
        case 'full_featured_pdf':
            try {
                // Berechtigungsprüfung für Signatur-Demo
                if (!rex::getUser() || !rex::getUser()->hasPerm('pdfout[signature]')) {
                    $error = 'Keine Berechtigung für digitale Signaturen. Bitte wenden Sie sich an den Administrator.';
                    break;
                }
                
                $pdf = new PdfOut();
                $pdf->setName('demo_full_featured')
                    ->setHtml('<h1>Vollständig ausgestattetes PDF Demo</h1><p>Dieses PDF kombiniert alle Features:</p><ul><li>Digitale Signierung</li><li>Passwortschutz</li><li>Sichtbare Signatur</li></ul><p><strong>Passwort:</strong> demo123</p><p style="margin-top: 30mm;">Die sichtbare Signatur ist rechts unten positioniert.</p>');
                
                applySignatureConfig($pdf, $defaultSignatureConfig, 'Full-Feature Demo');
                
                $pdf->setVisibleSignature(120, 220, 70, 30, -1)
                    ->enablePasswordProtection('demo123', 'owner456', ['print'])
                    ->run();
            } catch (Exception $e) {
                $error = 'Fehler beim Erstellen des vollausgestatteten PDFs: ' . $e->getMessage();
            }
            break;
            
        case 'pdf_import_demo':
            try {
                // Prüfe ob FPDI verfügbar ist
                if (!class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
                    $error = 'FPDI ist nicht installiert. PDF-Import-Funktionalität ist nicht verfügbar.';
                    break;
                }
                
                // Verwende die vorhandene Test-PDF-Datei
                $sourcePdfPath = rex_addon::get('pdfout')->getAssetsPath('vendor/web/compressed.tracemonkey-pldi-09.pdf');
                
                // Prüfe ob die Datei existiert
                if (!file_exists($sourcePdfPath)) {
                    $error = 'Test-PDF-Datei nicht gefunden: ' . $sourcePdfPath;
                    break;
                }
                
                // Verwende PdfOut's importAndExtendPdf mit der echten PDF-Datei
                $pdf = new PdfOut();
                $pdf->setName('imported_tracemonkey_extended')
                    ->importAndExtendPdf(
                        $sourcePdfPath,
                        '<div style="margin: 20px; font-family: Arial, sans-serif;">
                            <h1 style="color: #d63534; border-bottom: 2px solid #d63534; padding-bottom: 10px;">
                                Durch PDFOut erweitert!
                            </h1>
                            <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0;">
                                <h3 style="color: #007bff; margin-top: 0;">PDF-Import Demo erfolgreich</h3>
                                <p><strong>Original-Dokument:</strong> TracemonKey Research Paper (komprimiert)</p>
                                <p><strong>Import-Methode:</strong> FPDI + TCPDF Integration</p>
                                <p><strong>Erweitert am:</strong> ' . date('d.m.Y H:i:s') . '</p>
                            </div>
                            <h3>Features dieser Demo:</h3>
                            <ul style="line-height: 1.6;">
                                <li>✅ <strong>Echte PDF importiert:</strong> Alle Original-Seiten bleiben erhalten</li>
                                <li>✅ <strong>Neue Inhalte hinzugefügt:</strong> Diese Seite wurde nahtlos angefügt</li>
                                <li>✅ <strong>FPDI-Integration:</strong> Professionelle PDF-Verarbeitung</li>
                                <li>✅ <strong>Flexibel einsetzbar:</strong> Funktioniert mit jeder PDF-Datei</li>
                            </ul>
                            <div style="margin-top: 30px; padding: 15px; background: #e7f3ff; border: 1px solid #b3d9ff;">
                                <h4 style="color: #0066cc; margin-top: 0;">Praktische Anwendungen:</h4>
                                <ul>
                                    <li>Anhängen von Deckblättern an bestehende Dokumente</li>
                                    <li>Hinzufügen von Unterschriftsseiten</li>
                                    <li>Zusammenführung mehrerer PDF-Dokumente</li>
                                    <li>Erweitern von Verträgen um neue Klauseln</li>
                                </ul>
                            </div>
                        </div>',
                        true  // Als neue Seite hinzufügen
                    );
                
                
                // Optional: Digitale Signatur hinzufügen
                if (rex::getUser() && rex::getUser()->hasPerm('pdfout[signature]')) {
                    // Signatur-Konfiguration anwenden
                    $certificatePath = $defaultSignatureConfig['cert_path'];
                    if (empty($certificatePath)) {
                        $certificatePath = rex_addon::get('pdfout')->getDataPath('certificates/default.p12');
                    }
                    
                    if (file_exists($certificatePath)) {
                        $info = [
                            'Name' => $defaultSignatureConfig['name'],
                            'Location' => $defaultSignatureConfig['location'],
                            'Reason' => 'PDF-Import Demo',
                            'ContactInfo' => $defaultSignatureConfig['contact']
                        ];
                        
                        $certificateContent = file_get_contents($certificatePath);
                        $pdf->enableDigitalSignature($certificatePath, $defaultSignatureConfig['password'])
                            ->setVisibleSignature(180, 60, 15, 15, -1, $info['Name'], $info['Location'], $info['Reason'], $info['ContactInfo']);
                    }
                }
                
                // PDF ausgeben
                $pdf->run();
                
            } catch (Exception $e) {
                $error = 'Fehler beim PDF-Import-Demo: ' . $e->getMessage();
            }
            break;
    }
}

// Nachrichten anzeigen
if ($error) {
    echo rex_view::error($error);
}
if ($message) {
    echo rex_view::success($message);
}

// Demo & Test Einstellungen Sektion (am Anfang)
$testSettings = '
<div class="row">
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><i class="fa fa-certificate"></i> Test-Zertifikat generieren</h4>
            </div>
            <div class="panel-body">
                <p>Generiert ein selbstsigniertes Test-Zertifikat für Demo-Zwecke.</p>
                
                <div class="alert alert-info">
                    <small><strong>Details:</strong> Passwort: <code>redaxo123</code>, Gültigkeit: 365 Tage</small>
                </div>
                
                <form method="post">
                    <input type="hidden" name="generate-test-certificate" value="1">
                    <button type="submit" class="btn btn-default" onclick="return confirm(\'Test-Zertifikat generieren?\')">
                        <i class="fa fa-plus-circle"></i> Zertifikat erstellen
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><i class="fa fa-file-pdf-o"></i> Test-PDF generieren</h4>
            </div>
            <div class="panel-body">
                <p>Erstellt ein einfaches Test-PDF zur Überprüfung der Grundfunktionalität.</p>
                
                <form method="post" target="_blank">
                    <input type="hidden" name="generate-test-pdf" value="1">
                    <button type="submit" class="btn btn-default">
                        <i class="fa fa-download"></i> Test-PDF erstellen
                    </button>
                </form>
                
                <div class="alert alert-success" style="margin-top: 15px;">
                    <small><strong>Inhalt:</strong> Demo-PDF mit Zeitstempel</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><i class="fa fa-info-circle"></i> System-Status</h4>
            </div>
            <div class="panel-body">
                <table class="table table-condensed">
                    <tr>
                        <td><strong>Zertifikat:</strong></td>
                        <td>' . (file_exists(rex_path::addonData('pdfout', 'certificates/default.p12')) ? 
                            '<span class="text-success"><i class="fa fa-check"></i></span>' : 
                            '<span class="text-danger"><i class="fa fa-times"></i></span>') . '</td>
                    </tr>
                    <tr>
                        <td><strong>OpenSSL:</strong></td>
                        <td>' . (function_exists('openssl_pkcs12_export') ? 
                            '<span class="text-success"><i class="fa fa-check"></i></span>' : 
                            '<span class="text-danger"><i class="fa fa-times"></i></span>') . '</td>
                    </tr>
                    <tr>
                        <td><strong>Schreibrechte:</strong></td>
                        <td>' . (is_writable(rex_path::addonData('pdfout')) ? 
                            '<span class="text-success"><i class="fa fa-check"></i></span>' : 
                            '<span class="text-danger"><i class="fa fa-times"></i></span>') . '</td>
                    </tr>
                </table>
                
                <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#modal-system-details">
                    <i class="fa fa-info"></i> Details anzeigen
                </button>
            </div>
        </div>
    </div>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Demo & Test Einstellungen');
$fragment->setVar('body', $testSettings, false);
echo $fragment->parse('core/page/section.php');

// Demo-Definitionen laden (modular)
function loadDemos() {
    $demos = [];
    $demoDir = __DIR__ . '/demos/';
    
    // Standard-Demos in gewünschter Reihenfolge
    $defaultDemos = [
        'simple_pdf',
        'signed_pdf', 
        'password_pdf',
        'full_featured_pdf',
        'pdf_import_demo'
    ];
    
    // Lade Standard-Demos in korrekter Reihenfolge
    foreach ($defaultDemos as $demoKey) {
        $demoFile = $demoDir . $demoKey . '.php';
        if (file_exists($demoFile)) {
            $demoConfig = include $demoFile;
            if (is_array($demoConfig)) {
                $demos[$demoKey] = $demoConfig;
            }
        }
    }
    
    // Zusätzliche Demos aus dem Verzeichnis laden (falls vorhanden)
    if (is_dir($demoDir)) {
        $files = glob($demoDir . '*.php');
        foreach ($files as $file) {
            $demoKey = basename($file, '.php');
            // Nur laden wenn nicht bereits geladen
            if (!isset($demos[$demoKey])) {
                $demoConfig = include $file;
                if (is_array($demoConfig)) {
                    $demos[$demoKey] = $demoConfig;
                }
            }
        }
    }
    
    return $demos;
}

$demos = loadDemos();

// Demo-Kästen generieren
$content = '<div class="row">';
$col_count = 0;
$userCanSign = rex::getUser() && rex::getUser()->hasPerm('pdfout[signature]');

foreach ($demos as $demo_key => $demo) {
    if ($col_count % 2 == 0 && $col_count > 0) {
        $content .= '</div><div class="row">';
    }
    
    $modal_id = 'modal-code-' . $demo_key;
    $needsSignature = in_array($demo_key, ['signed_pdf', 'full_featured_pdf']);
    $isDisabled = $needsSignature && !$userCanSign;
    
    // Prüfe FPDI-Verfügbarkeit für PDF-Import-Demo
    $needsFpdi = isset($demo['availability_check']);
    $fpdiNotAvailable = $needsFpdi && eval('return ' . $demo['availability_check'] . ';');
    $isDisabled = $isDisabled || $fpdiNotAvailable;
    
    $content .= '
    <div class="col-md-6">
        <div class="panel ' . $demo['panel_class'] . '">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa ' . $demo['icon'] . '"></i> ' . $demo['title'] . '</h3>
            </div>
            <div class="panel-body">
                <p>' . $demo['description'] . '</p>';
                
    if ($isDisabled) {
        if ($needsSignature && !$userCanSign) {
            $content .= '
                <div class="alert alert-warning">
                    <i class="fa fa-exclamation-triangle"></i> Keine Berechtigung für Signatur-Features. 
                    <a href="' . rex_url::currentBackendPage(['page' => 'users/users']) . '" class="btn btn-xs btn-warning">
                        <i class="fa fa-user"></i> Berechtigung anfordern
                    </a>
                </div>';
        }
        
        if ($fpdiNotAvailable) {
            $content .= '
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> ' . ($demo['availability_message'] ?? 'Feature nicht verfügbar') . '
                    <div style="margin-top: 10px;">
                        <code style="background: #f8f9fa; padding: 5px; border-radius: 3px;">
                            composer require setasign/fpdi
                        </code>
                    </div>
                </div>';
        }
    }
    
    $content .= '
                <div class="btn-group">
                    <form method="post" style="display:inline;" target="_blank">
                        <input type="hidden" name="demo-action" value="' . $demo_key . '">
                        <button type="submit" class="btn ' . $demo['btn_class'] . '"' . ($isDisabled ? ' disabled' : '') . '>
                            <i class="fa fa-download"></i> PDF erstellen
                        </button>
                    </form>
                    <button type="button" class="btn btn-default" data-toggle="modal" data-target="#' . $modal_id . '">
                        <i class="fa fa-code"></i> Quellcode
                    </button>';
                    
    if ($needsSignature && $userCanSign) {
        $content .= '
                    <button type="button" class="btn btn-info" data-toggle="modal" data-target="#modal-signature-config">
                        <i class="fa fa-cog"></i> Signatur prüfen
                    </button>';
    }
    
    $content .= '
                </div>
            </div>
        </div>
    </div>';
    
    $col_count++;
}
$content .= '</div>';

// Modal für Signatur-Konfiguration
$certPath = rex_path::addonData('pdfout', 'certificates/default.p12');
$certExists = file_exists($certPath);

// Dateirechte prüfen
$filePerms = '';
$permissionStatus = '';
if ($certExists) {
    $perms = fileperms($certPath);
    $filePerms = substr(sprintf('%o', $perms), -4);
    $permissionStatus = ($filePerms === '0600' || $filePerms === '0644') ? 
        '<span class="text-success"><i class="fa fa-check"></i> Sicher (' . $filePerms . ')</span>' : 
        '<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> Unsicher (' . $filePerms . ')</span>';
}

// Zertifikatsdetails laden
$certDetails = '';
if ($certExists && function_exists('openssl_pkcs12_read')) {
    $certData = file_get_contents($certPath);
    $certs = [];
    if (openssl_pkcs12_read($certData, $certs, 'redaxo123')) {
        $certInfo = openssl_x509_parse($certs['cert']);
        if ($certInfo) {
            $validFrom = date('d.m.Y H:i', $certInfo['validFrom_time_t']);
            $validTo = date('d.m.Y H:i', $certInfo['validTo_time_t']);
            $isExpired = time() > $certInfo['validTo_time_t'];
            $issuer = $certInfo['issuer']['CN'] ?? 'Unbekannt';
            $subject = $certInfo['subject']['CN'] ?? 'Unbekannt';
            
            $certDetails = '
            <h5>Zertifikatsdetails:</h5>
            <table class="table table-striped">
                <tr>
                    <td><strong>Aussteller:</strong></td>
                    <td>' . rex_escape($issuer) . '</td>
                </tr>
                <tr>
                    <td><strong>Subject:</strong></td>
                    <td>' . rex_escape($subject) . '</td>
                </tr>
                <tr>
                    <td><strong>Gültig von:</strong></td>
                    <td>' . $validFrom . '</td>
                </tr>
                <tr>
                    <td><strong>Gültig bis:</strong></td>
                    <td>' . ($isExpired ? 
                        '<span class="text-danger">' . $validTo . ' (Abgelaufen)</span>' : 
                        '<span class="text-success">' . $validTo . '</span>') . '</td>
                </tr>
            </table>';
        }
    }
}

$permissionWarning = '';
if ($certExists && ($filePerms !== '0600' && $filePerms !== '0644')) {
    $permissionWarning = '
    <div class="alert alert-warning">
        <strong><i class="fa fa-exclamation-triangle"></i> Sicherheitswarnung:</strong> 
        Die Zertifikatsdatei hat unsichere Dateirechte (' . $filePerms . '). 
        Empfohlen: 600 (nur Owner lesbar/schreibbar) oder 644 (Owner lesbar/schreibbar, Gruppe/Andere nur lesbar).
    </div>';
}

$content .= '
<div class="modal fade" id="modal-signature-config" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    <i class="fa fa-certificate"></i> Signatur-Konfiguration prüfen
                </h4>
            </div>
            <div class="modal-body">
                <h5>System-Status:</h5>
                <table class="table table-striped">
                    <tr>
                        <td><strong>Zertifikat:</strong></td>
                        <td>' . ($certExists ? 
                            '<span class="text-success"><i class="fa fa-check"></i> Vorhanden</span>' : 
                            '<span class="text-danger"><i class="fa fa-times"></i> Fehlt</span>') . '</td>
                    </tr>' .
                    ($certExists ? '<tr>
                        <td><strong>Dateirechte:</strong></td>
                        <td>' . $permissionStatus . '</td>
                    </tr>' : '') . '
                    <tr>
                        <td><strong>OpenSSL:</strong></td>
                        <td>' . (function_exists('openssl_pkcs12_export') ? 
                            '<span class="text-success"><i class="fa fa-check"></i> Verfügbar</span>' : 
                            '<span class="text-danger"><i class="fa fa-times"></i> Nicht verfügbar</span>') . '</td>
                    </tr>
                    <tr>
                        <td><strong>Berechtigung:</strong></td>
                        <td><span class="text-success"><i class="fa fa-check"></i> Signatur erlaubt</span></td>
                    </tr>
                </table>
                
                ' . $certDetails . '
                ' . $permissionWarning . '
                
                <div class="alert alert-info">
                    <strong>Hinweis:</strong> Diese Demo verwendet ein Test-Zertifikat mit dem Passwort <code>redaxo123</code>. 
                    Für produktive Systeme sollten Sie ein gültiges Zertifikat einer vertrauenswürdigen CA verwenden.
                </div>
            </div>
            <div class="modal-footer">
                <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/config']) . '" class="btn btn-primary">
                    <i class="fa fa-cog"></i> Konfiguration öffnen
                </a>
                <button type="button" class="btn btn-default" data-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>';

// Modal für System-Details
$systemDetailsModal = '
<div class="modal fade" id="modal-system-details" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    <i class="fa fa-info-circle"></i> Detaillierter System-Status
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Zertifikat-Status:</h5>
                        <table class="table table-striped">
                            <tr>
                                <td><strong>Standard-Zertifikat:</strong></td>
                                <td>' . (file_exists(rex_path::addonData('pdfout', 'certificates/default.p12')) ? 
                                    '<span class="text-success"><i class="fa fa-check"></i> Vorhanden</span>' : 
                                    '<span class="text-danger"><i class="fa fa-times"></i> Nicht vorhanden</span>') . '</td>
                            </tr>';

if (file_exists(rex_path::addonData('pdfout', 'certificates/default.p12'))) {
    $certPath = rex_path::addonData('pdfout', 'certificates/default.p12');
    $certSize = filesize($certPath);
    $certModified = date('d.m.Y H:i:s', filemtime($certPath));
    $perms = fileperms($certPath);
    $filePerms = substr(sprintf('%o', $perms), -4);
    
    $systemDetailsModal .= '
                            <tr>
                                <td><strong>Dateigröße:</strong></td>
                                <td>' . number_format($certSize) . ' Bytes</td>
                            </tr>
                            <tr>
                                <td><strong>Letzte Änderung:</strong></td>
                                <td>' . $certModified . '</td>
                            </tr>
                            <tr>
                                <td><strong>Dateirechte:</strong></td>
                                <td>' . (($filePerms === '0600' || $filePerms === '0644') ? 
                                    '<span class="text-success">' . $filePerms . ' (Sicher)</span>' : 
                                    '<span class="text-warning">' . $filePerms . ' (Überprüfen)</span>') . '</td>
                            </tr>';
                            
    // Zertifikatsdetails laden wenn möglich
    if (function_exists('openssl_pkcs12_read')) {
        $certData = file_get_contents($certPath);
        $certs = [];
        if (openssl_pkcs12_read($certData, $certs, 'redaxo123')) {
            $certInfo = openssl_x509_parse($certs['cert']);
            if ($certInfo) {
                $validTo = date('d.m.Y H:i', $certInfo['validTo_time_t']);
                $isExpired = time() > $certInfo['validTo_time_t'];
                $daysLeft = ceil(($certInfo['validTo_time_t'] - time()) / 86400);
                
                $systemDetailsModal .= '
                            <tr>
                                <td><strong>Gültig bis:</strong></td>
                                <td>' . ($isExpired ? 
                                    '<span class="text-danger">' . $validTo . ' (Abgelaufen)</span>' : 
                                    '<span class="text-success">' . $validTo . ' (' . $daysLeft . ' Tage)</span>') . '</td>
                            </tr>';
            }
        }
    }
}

$systemDetailsModal .= '
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h5>System-Voraussetzungen:</h5>
                        <table class="table table-striped">
                            <tr>
                                <td><strong>OpenSSL (PHP):</strong></td>
                                <td>' . (function_exists('openssl_pkcs12_export') ? 
                                    '<span class="text-success"><i class="fa fa-check"></i> Verfügbar</span>' : 
                                    '<span class="text-danger"><i class="fa fa-times"></i> Nicht verfügbar</span>') . '</td>
                            </tr>
                            <tr>
                                <td><strong>OpenSSL (System):</strong></td>
                                <td>';

// System OpenSSL prüfen (sicherer Ansatz)
$opensslAvailable = false;
if (function_exists('exec')) {
    $output = [];
    $returnCode = 0;
    @exec('which openssl 2>/dev/null', $output, $returnCode);
    $opensslAvailable = ($returnCode === 0 && !empty($output));
}

if ($opensslAvailable) {
    $systemDetailsModal .= '<span class="text-success"><i class="fa fa-check"></i> Verfügbar</span>';
} else {
    $systemDetailsModal .= '<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> Nicht gefunden</span>';
}

$systemDetailsModal .= '</td>
                            </tr>
                            <tr>
                                <td><strong>Schreibrechte:</strong></td>
                                <td>' . (is_writable(rex_path::addonData('pdfout')) ? 
                                    '<span class="text-success"><i class="fa fa-check"></i> Data-Ordner beschreibbar</span>' : 
                                    '<span class="text-danger"><i class="fa fa-times"></i> Keine Schreibrechte</span>') . '</td>
                            </tr>
                            <tr>
                                <td><strong>Temp-Verzeichnis:</strong></td>
                                <td>' . (is_writable(sys_get_temp_dir()) ? 
                                    '<span class="text-success"><i class="fa fa-check"></i> Verfügbar</span>' : 
                                    '<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> Nicht beschreibbar</span>') . '</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <h5><i class="fa fa-info-circle"></i> Informationen</h5>
                    <ul class="mb-0">
                        <li><strong>Test-Zertifikate</strong> sind selbstsigniert und nur für Entwicklung geeignet</li>
                        <li><strong>Produktive Systeme</strong> sollten Zertifikate von vertrauenswürdigen CAs verwenden</li>
                        <li><strong>Passwörter</strong> sollten über sichere Methoden (Umgebungsvariablen, verschlüsselte Config) geladen werden</li>
                        <li><strong>REDAXO Properties</strong> können für sichere Speicherung von Secrets verwendet werden</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/config']) . '" class="btn btn-primary">
                    <i class="fa fa-cog"></i> Konfiguration
                </a>
                <button type="button" class="btn btn-default" data-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>';

$content .= $systemDetailsModal;

// Modals für Quellcode generieren
foreach ($demos as $demo_key => $demo) {
    $modal_id = 'modal-code-' . $demo_key;
    $content .= '
    <div class="modal fade" id="' . $modal_id . '" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title">
                        <i class="fa ' . $demo['icon'] . '"></i> ' . $demo['title'] . ' - Quellcode
                    </h4>
                </div>
                <div class="modal-body">
                    <pre><code class="language-php">' . htmlspecialchars($demo['code']) . '</code></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Schließen</button>
                </div>
            </div>
        </div>
    </div>';
}

$fragment = new rex_fragment();
$fragment->setVar('title', 'PDF-Demos');
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// Wichtige Hinweise
$notes = '
<div class="alert alert-info">
    <h4>Wichtige Hinweise</h4>
    <ul>
        <li>Die Position der sichtbaren Signatur wird in Punkten (pt) angegeben</li>
        <li>X=0, Y=0 ist die linke obere Ecke des Dokuments</li>
        <li>Page -1 bedeutet die letzte Seite des Dokuments</li>
        <li>Das verwendete Zertifikat ist nur für Testzwecke geeignet</li>
        <li>Passwort für Test-Zertifikat: <code>redaxo123</code></li>
    </ul>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Wichtige Hinweise');
$fragment->setVar('body', $notes, false);
echo $fragment->parse('core/page/section.php');

// Sicherheitshinweise und Best Practices
$security = '
<div class="alert alert-warning">
    <h4><i class="fa fa-shield"></i> Sicherheitshinweise für produktive Nutzung</h4>
    <p><strong>Die obigen Demos verwenden Testwerte!</strong> Für produktive Systeme beachten Sie folgende Sicherheitsaspekte:</p>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-danger">
            <div class="panel-heading">
                <h4><i class="fa fa-exclamation-triangle"></i> Passwort-Sicherheit</h4>
            </div>
            <div class="panel-body">
                <h5>❌ Nicht in produktiven Systemen:</h5>
                <pre><code>// Hardcoded Passwörter vermeiden!
$pdf->enableDigitalSignature(\'\', \'redaxo123\', ...);</code></pre>
                
                <h5>✅ Sicher für Produktion:</h5>
                <pre><code>// REDAXO Properties verwenden (empfohlen)
$certPassword = rex_property::get(\'cert_password\');
$pdf->enableDigitalSignature(\'\', $certPassword, ...);</code></pre>
                
                <pre><code>// Oder REDAXO Config mit Verschlüsselung
$encryptedPassword = rex_config::get(\'pdfout\', \'cert_password\');
$password = my_decrypt($encryptedPassword);
$pdf->enableDigitalSignature(\'\', $password, ...);</code></pre>
                
                <pre><code>// Alternativ: Umgebungsvariablen
$certPassword = $_ENV[\'CERT_PASSWORD\'];
$pdf->enableDigitalSignature(\'\', $certPassword, ...);</code></pre>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h4><i class="fa fa-certificate"></i> Zertifikat-Management</h4>
            </div>
            <div class="panel-body">
                <h5>Best Practices:</h5>
                <ul>
                    <li><strong>Produktive Zertifikate:</strong> Von vertrauenswürdiger CA</li>
                    <li><strong>Dateiberechtigungen:</strong> 600 (nur Webserver lesbar)</li>
                    <li><strong>Pfad-Validierung:</strong> Existenz vor Verwendung prüfen</li>
                    <li><strong>Ablaufdatum:</strong> Monitoring und rechtzeitige Erneuerung</li>
                </ul>
                
                <pre><code>// Zertifikat-Validierung
$certPath = rex_path::addonData(\'pdfout\', \'certificates/prod.p12\');
if (!file_exists($certPath)) {
    throw new Exception(\'Zertifikat nicht gefunden\');
}
if (fileperms($certPath) & 0044) {
    throw new Exception(\'Zertifikat unsicher (zu offen)\');
}</code></pre>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-success">
            <div class="panel-heading">
                <h4><i class="fa fa-check-circle"></i> Empfohlene Sicherheitsmaßnahmen</h4>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-4">
                        <h5><i class="fa fa-key"></i> Key Management</h5>
                        <ul>
                            <li>Azure Key Vault</li>
                            <li>AWS Secrets Manager</li>
                            <li>HashiCorp Vault</li>
                            <li>REDAXO Addon: crypto</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h5><i class="fa fa-server"></i> Server-Konfiguration</h5>
                        <ul>
                            <li>Umgebungsvariablen für Secrets</li>
                            <li>Restricted Dateiberechtigungen</li>
                            <li>SSL/TLS für Backend-Zugriff</li>
                            <li>Audit-Logging aktivieren</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h5><i class="fa fa-shield"></i> Code-Sicherheit</h5>
                        <ul>
                            <li>Input-Validierung</li>
                            <li>Fehlerbehandlung ohne Preisgabe</li>
                            <li>Sichere Temp-Datei-Erstellung</li>
                            <li>Regular Security Reviews</li>
                        </ul>
                    </div>
                </div>
                
                <div class="alert alert-info" style="margin-top: 15px;">
                    <strong>Tipp:</strong> Erstellen Sie ein separates Config-Addon für produktive Credentials oder verwenden Sie 
                    <code>.env</code>-Dateien mit dem <strong>vlucas/phpdotenv</strong> Package.
                </div>
            </div>
        </div>
    </div>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Sicherheit & Best Practices');
$fragment->setVar('body', $security, false);
echo $fragment->parse('core/page/section.php');
