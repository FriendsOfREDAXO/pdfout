<?php
/**
 * PDFOut Zertifikatsverwaltung
 */

$addon = rex_addon::get('pdfout');

// Zertifikate-Verzeichnis sicherstellen
$certificatesDir = $addon->getDataPath('certificates/');
if (!is_dir($certificatesDir)) {
    rex_dir::create($certificatesDir);
}

// Verarbeitung der Formulardaten
$message = '';
$error = '';

// Zertifikat hochladen
if (rex_post('upload-certificate', 'bool')) {
    try {
        if (isset($_FILES['certificate_file']) && $_FILES['certificate_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['certificate_file'];
            $allowedExtensions = ['p12', 'pfx'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            
            // Dateiname und Extension prüfen
            $originalName = $uploadedFile['name'];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            
            if (!in_array($extension, $allowedExtensions)) {
                $error = 'Nur .p12 und .pfx Dateien sind erlaubt.';
            } elseif ($uploadedFile['size'] > $maxFileSize) {
                $error = 'Datei ist zu groß. Maximum: 5MB.';
            } else {
                $filename = rex_post('certificate_name', 'string', '');
                if (empty($filename)) {
                    $filename = pathinfo($originalName, PATHINFO_FILENAME);
                }
                
                // Sauberen Dateinamen erstellen
                $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
                $targetFile = $certificatesDir . $filename . '.p12';
                
                // Datei verschieben
                if (move_uploaded_file($uploadedFile['tmp_name'], $targetFile)) {
                    // Zertifikat validieren
                    $password = rex_post('certificate_password', 'string', '');
                    if (!empty($password) && function_exists('openssl_pkcs12_read')) {
                        $certData = file_get_contents($targetFile);
                        $certs = [];
                        if (openssl_pkcs12_read($certData, $certs, $password)) {
                            $message = 'Zertifikat "' . $filename . '" wurde erfolgreich hochgeladen und validiert.';
                        } else {
                            unlink($targetFile);
                            $error = 'Zertifikat konnte nicht validiert werden. Bitte überprüfen Sie das Passwort.';
                        }
                    } else {
                        $message = 'Zertifikat "' . $filename . '" wurde hochgeladen. Bitte testen Sie es mit dem korrekten Passwort.';
                    }
                } else {
                    $error = 'Fehler beim Speichern des Zertifikats.';
                }
            }
        } else {
            $error = 'Keine Datei hochgeladen oder Upload-Fehler.';
        }
    } catch (Exception $e) {
        $error = 'Fehler beim Hochladen des Zertifikats: ' . $e->getMessage();
    }
}

// Test-Zertifikat generieren
if (rex_post('generate-test-certificate', 'bool')) {
    try {
        $certName = rex_post('test_cert_name', 'string', 'test_certificate');
        $certName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $certName);
        $certPath = $certificatesDir . $certName . '.p12';
        
        // Prüfen ob bereits existiert
        if (file_exists($certPath)) {
            $error = 'Ein Zertifikat mit diesem Namen existiert bereits.';
        } else {
            // OpenSSL-Befehle für Zertifikatserstellung
            $privateKeyFile = $certificatesDir . 'temp_private_' . uniqid() . '.key';
            $certFile = $certificatesDir . 'temp_cert_' . uniqid() . '.crt';
            $password = rex_post('test_cert_password', 'string', 'redaxo123');
            
            // Zertifikatsdaten
            $commonName = rex_post('test_cert_cn', 'string', 'REDAXO Test Certificate');
            $organization = rex_post('test_cert_org', 'string', 'REDAXO CMS');
            $email = rex_post('test_cert_email', 'string', 'test@redaxo.local');
            
            // 1. Private Key erstellen
            $privateKeyCmd = sprintf(
                'openssl genrsa -out %s 2048 2>/dev/null',
                escapeshellarg($privateKeyFile)
            );
            
            // 2. Zertifikat erstellen
            $certCmd = sprintf(
                'openssl req -new -x509 -key %s -out %s -days 365 -subj "/C=DE/ST=Test/L=Test/O=%s/OU=PDFOut/CN=%s/emailAddress=%s" 2>/dev/null',
                escapeshellarg($privateKeyFile),
                escapeshellarg($certFile),
                escapeshellarg($organization),
                escapeshellarg($commonName),
                escapeshellarg($email)
            );
            
            // 3. P12 erstellen
            $p12Cmd = sprintf(
                'openssl pkcs12 -export -out %s -inkey %s -in %s -password pass:%s 2>/dev/null',
                escapeshellarg($certPath),
                escapeshellarg($privateKeyFile),
                escapeshellarg($certFile),
                $password
            );
            
            // Befehle ausführen
            exec($privateKeyCmd, $output1, $return1);
            if ($return1 === 0) {
                exec($certCmd, $output2, $return2);
                if ($return2 === 0) {
                    exec($p12Cmd, $output3, $return3);
                    if ($return3 === 0) {
                        $message = 'Test-Zertifikat "' . $certName . '" wurde erfolgreich generiert.<br>Passwort: ' . $password;
                    } else {
                        $error = 'Fehler beim Erstellen der P12-Datei.';
                    }
                } else {
                    $error = 'Fehler beim Erstellen des Zertifikats.';
                }
            } else {
                $error = 'Fehler beim Erstellen des Private Keys.';
            }
            
            // Temporäre Dateien löschen
            if (file_exists($privateKeyFile)) unlink($privateKeyFile);
            if (file_exists($certFile)) unlink($certFile);
        }
        
    } catch (Exception $e) {
        $error = 'Fehler beim Generieren des Test-Zertifikats: ' . $e->getMessage();
    }
}

// Zertifikat löschen
if (rex_post('delete-certificate', 'bool')) {
    $certToDelete = rex_post('certificate_to_delete', 'string', '');
    if (!empty($certToDelete)) {
        $certPath = $certificatesDir . $certToDelete;
        if (file_exists($certPath) && unlink($certPath)) {
            $message = 'Zertifikat wurde erfolgreich gelöscht.';
        } else {
            $error = 'Fehler beim Löschen des Zertifikats.';
        }
    }
}

// Demo-Zertifikat-Auswahl speichern
if (rex_post('save-demo-certificate', 'bool')) {
    $demoCertificate = rex_post('demo_certificate', 'string', '');
    $demoCertificatePassword = rex_post('demo_certificate_password', 'string', '');
    
    // In AddOn-Konfiguration speichern (einzeln, um bestehende Config nicht zu überschreiben)
    $addon->setConfig('demo_certificate', $demoCertificate);
    $addon->setConfig('demo_certificate_password', $demoCertificatePassword);
    
    $message = 'Demo-Zertifikat-Auswahl wurde gespeichert.';
}

// Als Standard-Zertifikat festlegen
if (rex_post('set-default', 'bool')) {
    $certToSetDefault = rex_post('certificate_to_set_default', 'string', '');
    if (!empty($certToSetDefault)) {
        $sourcePath = $certificatesDir . $certToSetDefault;
        $defaultPath = $certificatesDir . 'default.p12';
        
        if (file_exists($sourcePath)) {
            if (copy($sourcePath, $defaultPath)) {
                $message = 'Zertifikat wurde als Standard festgelegt.';
            } else {
                $error = 'Fehler beim Festlegen als Standard-Zertifikat.';
            }
        }
    }
}

// Standard-Zertifikat generieren (für Demo-Zwecke)
if (rex_post('generate-default-certificate', 'bool')) {
    try {
        $defaultPath = $certificatesDir . 'default.p12';
        
        // Überschreiben bestätigen
        if (file_exists($defaultPath) && !rex_post('confirm_overwrite', 'bool')) {
            $error = 'Standard-Zertifikat existiert bereits. Bestätigen Sie das Überschreiben.';
        } else {
            // OpenSSL-Befehle für Standard-Zertifikatserstellung
            $privateKeyFile = $certificatesDir . 'temp_default_private_' . uniqid() . '.key';
            $certFile = $certificatesDir . 'temp_default_cert_' . uniqid() . '.crt';
            $password = 'redaxo123';
            
            // Standard-Zertifikatsdaten
            $commonName = 'REDAXO PDFOut Standard Certificate';
            $organization = 'REDAXO CMS';
            $email = 'pdfout@redaxo.local';
            
            // 1. Private Key erstellen
            $privateKeyCmd = sprintf(
                'openssl genrsa -out %s 2048 2>/dev/null',
                escapeshellarg($privateKeyFile)
            );
            
            // 2. Zertifikat erstellen
            $certCmd = sprintf(
                'openssl req -new -x509 -key %s -out %s -days 365 -subj "/C=DE/ST=Demo/L=Demo/O=%s/OU=PDFOut/CN=%s/emailAddress=%s" 2>/dev/null',
                escapeshellarg($privateKeyFile),
                escapeshellarg($certFile),
                escapeshellarg($organization),
                escapeshellarg($commonName),
                escapeshellarg($email)
            );
            
            // 3. P12 erstellen
            $p12Cmd = sprintf(
                'openssl pkcs12 -export -out %s -inkey %s -in %s -password pass:%s 2>/dev/null',
                escapeshellarg($defaultPath),
                escapeshellarg($privateKeyFile),
                escapeshellarg($certFile),
                $password
            );
            
            // Befehle ausführen
            exec($privateKeyCmd, $output1, $return1);
            if ($return1 === 0) {
                exec($certCmd, $output2, $return2);
                if ($return2 === 0) {
                    exec($p12Cmd, $output3, $return3);
                    if ($return3 === 0) {
                        $message = 'Standard-Zertifikat wurde erfolgreich generiert.<br>Passwort: ' . $password;
                    } else {
                        $error = 'Fehler beim Erstellen der Standard-P12-Datei.';
                    }
                } else {
                    $error = 'Fehler beim Erstellen des Standard-Zertifikats.';
                }
            } else {
                $error = 'Fehler beim Erstellen des Standard-Private-Keys.';
            }
            
            // Temporäre Dateien löschen
            if (file_exists($privateKeyFile)) unlink($privateKeyFile);
            if (file_exists($certFile)) unlink($certFile);
        }
        
    } catch (Exception $e) {
        $error = 'Fehler beim Generieren des Standard-Zertifikats: ' . $e->getMessage();
    }
}

// Nachrichten anzeigen
if ($error) {
    echo rex_view::error($error);
}
if ($message) {
    echo rex_view::success($message);
}

// Vorhandene Zertifikate auflisten
$certificates = [];
if (is_dir($certificatesDir)) {
    $files = glob($certificatesDir . '*.p12');
    
    foreach ($files as $file) {
        $filename = basename($file);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $isDefault = $filename === 'default.p12';
        
        // Display-Name mit Zertifikatsdetails falls verfügbar
        $displayName = $name;
        if (function_exists('openssl_pkcs12_read')) {
            $certData = file_get_contents($file);
            $certs = [];
            // Versuche mit häufigen Test-Passwörtern
            $testPasswords = ['redaxo123', '', 'test', 'password'];
            foreach ($testPasswords as $testPassword) {
                if (openssl_pkcs12_read($certData, $certs, $testPassword)) {
                    $certInfo = openssl_x509_parse($certs['cert']);
                    if ($certInfo && isset($certInfo['subject']['CN'])) {
                        $displayName = $certInfo['subject']['CN'];
                        break;
                    }
                }
            }
        }
        
        if ($isDefault) {
            $displayName .= ' (Standard)';
        }
        
        $certificates[$filename] = [
            'filename' => $filename,
            'path' => $file,
            'name' => $name,
            'display_name' => $displayName,
            'size' => filesize($file),
            'modified' => filemtime($file),
            'is_default' => $isDefault
        ];
    }
}

// Zertifikat-Upload-Formular
$uploadForm = '
<form action="' . rex_url::currentBackendPage() . '" method="post" enctype="multipart/form-data">
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="certificate_file">Zertifikatsdatei (.p12/.pfx)</label>
                <input type="file" class="form-control" id="certificate_file" name="certificate_file" accept=".p12,.pfx" required>
                <small class="form-text text-muted">Maximale Dateigröße: 5MB</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="certificate_name">Name (optional)</label>
                <input type="text" class="form-control" id="certificate_name" name="certificate_name" placeholder="Wird aus Dateiname generiert">
                <small class="form-text text-muted">Nur Buchstaben, Zahlen, Bindestrich und Unterstrich</small>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="certificate_password">Passwort (zur Validierung)</label>
                <input type="password" class="form-control" id="certificate_password" name="certificate_password" placeholder="Optional für Validierung">
                <small class="form-text text-muted">Wird nur zur Validierung verwendet, nicht gespeichert</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit" name="upload-certificate" value="1" class="btn btn-primary form-control">
                    <i class="fa fa-upload"></i> Zertifikat hochladen
                </button>
            </div>
        </div>
    </div>
</form>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Zertifikat hochladen');
$fragment->setVar('body', $uploadForm, false);
echo $fragment->parse('core/page/section.php');

// Test-Zertifikat generieren
$testCertForm = '
<form action="' . rex_url::currentBackendPage() . '" method="post">
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                <label for="test_cert_name">Zertifikatsname</label>
                <input type="text" class="form-control" id="test_cert_name" name="test_cert_name" value="test_certificate" required>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label for="test_cert_password">Passwort</label>
                <input type="text" class="form-control" id="test_cert_password" name="test_cert_password" value="redaxo123" required>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label for="test_cert_cn">Common Name</label>
                <input type="text" class="form-control" id="test_cert_cn" name="test_cert_cn" value="REDAXO Test Certificate">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit" name="generate-test-certificate" value="1" class="btn btn-success form-control">
                    <i class="fa fa-plus-circle"></i> Test-Zertifikat generieren
                </button>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="test_cert_org">Organisation</label>
                <input type="text" class="form-control" id="test_cert_org" name="test_cert_org" value="REDAXO CMS">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="test_cert_email">E-Mail</label>
                <input type="email" class="form-control" id="test_cert_email" name="test_cert_email" value="test@redaxo.local">
            </div>
        </div>
    </div>
    
    <div class="alert alert-info">
        <strong><i class="fa fa-info-circle"></i> Hinweis:</strong>
        Test-Zertifikate sind selbstsigniert und nur für Entwicklungszwecke geeignet. 
        Sie werden nicht von Browsern oder E-Mail-Clients als vertrauenswürdig anerkannt.
    </div>
</form>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Test-Zertifikat generieren');
$fragment->setVar('body', $testCertForm, false);
echo $fragment->parse('core/page/section.php');

// Standard-Zertifikat für Demos
$hasDefault = file_exists($certificatesDir . 'default.p12');

$defaultCertForm = '
<div class="alert alert-info">
    <h4><i class="fa fa-info-circle"></i> Standard-Zertifikat für Demos</h4>
    <p>Das Standard-Zertifikat wird automatisch von den Demos verwendet, wenn kein anderes Zertifikat ausgewählt ist.</p>
    <p><strong>Status:</strong> ' . ($hasDefault ? 
        '<span class="text-success"><i class="fa fa-check"></i> Vorhanden</span>' : 
        '<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> Nicht vorhanden</span>') . '</p>
</div>

<form action="' . rex_url::currentBackendPage() . '" method="post">
    <div class="row">
        <div class="col-md-8">
            <p><strong>Automatische Generierung:</strong> Erstellt ein Standard-Zertifikat namens "default.p12" mit dem Passwort "redaxo123".</p>';

if ($hasDefault) {
    $defaultCertForm .= '
            <div class="form-group">
                <label>
                    <input type="checkbox" name="confirm_overwrite" value="1"> 
                    Vorhandenes Standard-Zertifikat überschreiben
                </label>
            </div>';
}

$defaultCertForm .= '
        </div>
        <div class="col-md-4 text-right">
            <button type="submit" name="generate-default-certificate" value="1" class="btn ' . ($hasDefault ? 'btn-warning' : 'btn-success') . '"' . 
                ($hasDefault ? ' onclick="return confirm(\'Standard-Zertifikat überschreiben?\')"' : '') . '>
                <i class="fa fa-' . ($hasDefault ? 'refresh' : 'plus-circle') . '"></i> ' . ($hasDefault ? 'Standard erneuern' : 'Standard erstellen') . '
            </button>
        </div>
    </div>
</form>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Standard-Zertifikat für Demos');
$fragment->setVar('body', $defaultCertForm, false);
echo $fragment->parse('core/page/section.php');

// Demo-Zertifikat-Auswahl Formular
$currentDemoCert = $addon->getConfig('demo_certificate', '');
$currentDemoPassword = $addon->getConfig('demo_certificate_password', 'redaxo123');

if (!empty($certificates)) {
    $demoCertForm = '
    <div class="alert alert-info">
        <h4><i class="fa fa-cogs"></i> Standard-Auswahl für Demo-Seite</h4>
        <p>Legen Sie fest, welches Zertifikat standardmäßig für die Signatur-Demos verwendet werden soll.</p>
    </div>
    
    <form action="' . rex_url::currentBackendPage() . '" method="post">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="demo_certificate">Standard-Demo-Zertifikat:</label>
                    <select class="form-control" id="demo_certificate" name="demo_certificate">
                        <option value="">Automatische Auswahl (default.p12 falls vorhanden)</option>';
    
    foreach ($certificates as $filename => $cert) {
        $selected = ($cert['filename'] === $currentDemoCert) ? ' selected' : '';
        $demoCertForm .= '<option value="' . rex_escape($cert['filename']) . '"' . $selected . '>' . rex_escape($cert['display_name']) . '</option>';
    }
    
    $demoCertForm .= '
                    </select>
                    <small class="form-text text-muted">Wird als Vorauswahl in den Demos verwendet</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="demo_certificate_password">Standard-Passwort:</label>
                    <input type="password" class="form-control" id="demo_certificate_password" name="demo_certificate_password" 
                           value="' . rex_escape($currentDemoPassword) . '" placeholder="Zertifikatspasswort">
                    <small class="form-text text-muted">Wird als Vorauswahl verwendet</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" name="save-demo-certificate" value="1" class="btn btn-primary form-control">
                        <i class="fa fa-save"></i> Speichern
                    </button>
                </div>
            </div>
        </div>
        
        <div class="alert alert-success">
            <strong><i class="fa fa-info-circle"></i> Aktuell gespeichert:</strong><br>
            <strong>Zertifikat:</strong> ' . (!empty($currentDemoCert) ? rex_escape($currentDemoCert) : 'Automatische Auswahl') . '<br>
            <strong>Passwort:</strong> ' . (strlen($currentDemoPassword) > 0 ? str_repeat('*', strlen($currentDemoPassword)) : 'Nicht gesetzt') . '
        </div>
    </form>';
    
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Demo-Zertifikat-Einstellungen');
    $fragment->setVar('body', $demoCertForm, false);
    echo $fragment->parse('core/page/section.php');
} else {
    // Fallback: Anzeige wenn keine Zertifikate vorhanden sind
    $noCertMessage = '
    <div class="alert alert-warning">
        <h4><i class="fa fa-exclamation-triangle"></i> Keine Zertifikate für Demo-Konfiguration verfügbar</h4>
        <p>Laden Sie zunächst Zertifikate hoch oder erstellen Sie Test-Zertifikate, um Demo-Einstellungen zu konfigurieren.</p>
    </div>';
    
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Demo-Zertifikat-Einstellungen');
    $fragment->setVar('body', $noCertMessage, false);
    echo $fragment->parse('core/page/section.php');
}

// Zertifikate-Liste
if (!empty($certificates)) {
    $certificatesList = '<div class="table-responsive"><table class="table table-striped">';
    $certificatesList .= '<thead><tr>';
    $certificatesList .= '<th>Name</th>';
    $certificatesList .= '<th>Größe</th>';
    $certificatesList .= '<th>Erstellt</th>';
    $certificatesList .= '<th>Details</th>';
    $certificatesList .= '<th>Status</th>';
    $certificatesList .= '<th>Aktionen</th>';
    $certificatesList .= '</tr></thead><tbody>';
    
    foreach ($certificates as $filename => $cert) {
        $certificatesList .= '<tr>';
        $certificatesList .= '<td><strong>' . rex_escape($cert['name']) . '</strong>';
        if ($cert['is_default']) {
            $certificatesList .= ' <span class="label label-success">Standard</span>';
        }
        $certificatesList .= '</td>';
        $certificatesList .= '<td>' . number_format($cert['size'] / 1024, 1) . ' KB</td>';
        $certificatesList .= '<td>' . date('d.m.Y H:i', $cert['modified']) . '</td>';
        
        // Zertifikatsdetails laden
        $details = 'Nicht verfügbar';
        if (function_exists('openssl_pkcs12_read')) {
            $details = '<button type="button" class="btn btn-xs btn-info" data-toggle="modal" data-target="#modal-cert-' . md5($filename) . '">
                <i class="fa fa-info-circle"></i> Details
            </button>';
        }
        $certificatesList .= '<td>' . $details . '</td>';
        
        // Status
        $status = '<span class="label label-default">Unbekannt</span>';
        if (function_exists('openssl_pkcs12_read')) {
            $status = '<span class="label label-success">Gültig</span>';
        }
        $certificatesList .= '<td>' . $status . '</td>';
        
        // Aktionen
        $actions = '<div class="btn-group">';
        if (!$cert['is_default']) {
            $actions .= '<form method="post" style="display:inline;">
                <input type="hidden" name="certificate_to_set_default" value="' . rex_escape($filename) . '">
                <button type="submit" name="set-default" value="1" class="btn btn-xs btn-success" title="Als Standard festlegen">
                    <i class="fa fa-star"></i>
                </button>
            </form>';
        }
        
        $actions .= '<form method="post" style="display:inline;">
            <input type="hidden" name="certificate_to_delete" value="' . rex_escape($filename) . '">
            <button type="submit" name="delete-certificate" value="1" class="btn btn-xs btn-danger" 
                    onclick="return confirm(\'Zertifikat wirklich löschen?\')" title="Löschen">
                <i class="fa fa-trash"></i>
            </button>
        </form>';
        $actions .= '</div>';
        
        $certificatesList .= '<td>' . $actions . '</td>';
        $certificatesList .= '</tr>';
    }
    
    $certificatesList .= '</tbody></table></div>';
    
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Vorhandene Zertifikate (' . count($certificates) . ')');
    $fragment->setVar('body', $certificatesList, false);
    echo $fragment->parse('core/page/section.php');
    
    // Modals für Zertifikatsdetails
    foreach ($certificates as $filename => $cert) {
        if (function_exists('openssl_pkcs12_read')) {
            $modalId = 'modal-cert-' . md5($filename);
            $certData = file_get_contents($cert['path']);
            $certs = [];
            $certInfo = null;
            
            // Versuche mit häufigen Passwörtern zu öffnen (nur für Demo)
            $testPasswords = ['', 'redaxo123', 'test', 'password', '123456'];
            foreach ($testPasswords as $testPassword) {
                if (openssl_pkcs12_read($certData, $certs, $testPassword)) {
                    $certInfo = openssl_x509_parse($certs['cert']);
                    break;
                }
            }
            
            $modalContent = '<div class="modal fade" id="' . $modalId . '" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                            <h4 class="modal-title">
                                <i class="fa fa-certificate"></i> Zertifikatsdetails: ' . rex_escape($cert['name']) . '
                            </h4>
                        </div>
                        <div class="modal-body">';
            
            if ($certInfo) {
                $validFrom = date('d.m.Y H:i', $certInfo['validFrom_time_t']);
                $validTo = date('d.m.Y H:i', $certInfo['validTo_time_t']);
                $isExpired = time() > $certInfo['validTo_time_t'];
                $daysLeft = ceil(($certInfo['validTo_time_t'] - time()) / 86400);
                
                $modalContent .= '
                <div class="row">
                    <div class="col-md-6">
                        <h5>Zertifikatsinformationen</h5>
                        <table class="table table-condensed">
                            <tr><td><strong>Subject:</strong></td><td>' . rex_escape($certInfo['subject']['CN'] ?? 'Unbekannt') . '</td></tr>
                            <tr><td><strong>Aussteller:</strong></td><td>' . rex_escape($certInfo['issuer']['CN'] ?? 'Unbekannt') . '</td></tr>
                            <tr><td><strong>Organisation:</strong></td><td>' . rex_escape($certInfo['subject']['O'] ?? 'Nicht angegeben') . '</td></tr>
                            <tr><td><strong>E-Mail:</strong></td><td>' . rex_escape($certInfo['subject']['emailAddress'] ?? 'Nicht angegeben') . '</td></tr>
                            <tr><td><strong>Land:</strong></td><td>' . rex_escape($certInfo['subject']['C'] ?? 'Nicht angegeben') . '</td></tr>';
                
                if (isset($certInfo['extensions']['subjectAltName'])) {
                    $modalContent .= '<tr><td><strong>Alt. Namen:</strong></td><td>' . rex_escape($certInfo['extensions']['subjectAltName']) . '</td></tr>';
                }
                
                $modalContent .= '</table>
                    </div>
                    <div class="col-md-6">
                        <h5>Gültigkeit</h5>
                        <table class="table table-condensed">
                            <tr><td><strong>Gültig von:</strong></td><td>' . $validFrom . '</td></tr>
                            <tr><td><strong>Gültig bis:</strong></td><td>' . ($isExpired ? 
                                '<span class="text-danger">' . $validTo . ' (Abgelaufen)</span>' : 
                                '<span class="text-success">' . $validTo . '</span>') . '</td></tr>';
                
                if (!$isExpired) {
                    $modalContent .= '<tr><td><strong>Verbleibend:</strong></td><td>' . $daysLeft . ' Tage</td></tr>';
                }
                
                $modalContent .= '<tr><td><strong>Status:</strong></td><td>' . ($isExpired ? 
                    '<span class="label label-danger">Abgelaufen</span>' : 
                    '<span class="label label-success">Gültig</span>') . '</td></tr>
                        </table>
                        
                        <h5>Dateiinformationen</h5>
                        <table class="table table-condensed">
                            <tr><td><strong>Dateiname:</strong></td><td>' . rex_escape($filename) . '</td></tr>
                            <tr><td><strong>Dateigröße:</strong></td><td>' . number_format($cert['size']) . ' Bytes</td></tr>
                            <tr><td><strong>Erstellt:</strong></td><td>' . date('d.m.Y H:i:s', $cert['modified']) . '</td></tr>
                        </table>
                    </div>
                </div>';
            } else {
                $modalContent .= '<div class="alert alert-warning">
                    <strong>Warnung:</strong> Zertifikatsdetails konnten nicht geladen werden. 
                    Möglicherweise ist ein Passwort erforderlich oder das Zertifikat ist beschädigt.
                </div>';
            }
            
            $modalContent .= '</div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Schließen</button>
                        </div>
                    </div>
                </div>
            </div>';
            
            echo $modalContent;
        }
    }
}

// Systemvoraussetzungen anzeigen
$systemInfo = '<div class="row">
    <div class="col-md-6">
        <h4>Systemvoraussetzungen</h4>
        <table class="table table-condensed">
            <tr>
                <td><strong>OpenSSL (PHP):</strong></td>
                <td>' . (function_exists('openssl_pkcs12_export') ? 
                    '<span class="text-success"><i class="fa fa-check"></i> Verfügbar</span>' : 
                    '<span class="text-danger"><i class="fa fa-times"></i> Nicht verfügbar</span>') . '</td>
            </tr>
            <tr>
                <td><strong>OpenSSL (System):</strong></td>
                <td>';

// System OpenSSL prüfen
$opensslCheck = shell_exec('which openssl 2>/dev/null');
if ($opensslCheck) {
    $systemInfo .= '<span class="text-success"><i class="fa fa-check"></i> Verfügbar</span>';
} else {
    $systemInfo .= '<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> Nicht gefunden</span>';
}

$systemInfo .= '</td>
            </tr>
            <tr>
                <td><strong>Schreibrechte:</strong></td>
                <td>' . (is_writable($certificatesDir) ? 
                    '<span class="text-success"><i class="fa fa-check"></i> Data-Ordner beschreibbar</span>' : 
                    '<span class="text-danger"><i class="fa fa-times"></i> Keine Schreibrechte</span>') . '</td>
            </tr>
            <tr>
                <td><strong>Upload-Limit:</strong></td>
                <td>' . ini_get('upload_max_filesize') . ' (PHP)</td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h4>Sicherheitshinweise</h4>
        <div class="alert alert-info">
            <ul class="mb-0">
                <li><strong>Passwörter:</strong> Werden nicht in der Datenbank gespeichert</li>
                <li><strong>Zertifikate:</strong> Sicher im data/addons/pdfout/certificates/ Ordner</li>
                <li><strong>Berechtigungen:</strong> Nur Administrator können Zertifikate verwalten</li>
                <li><strong>Test-Zertifikate:</strong> Nur für Entwicklung verwenden</li>
                <li><strong>Produktive Zertifikate:</strong> Von vertrauenswürdigen CAs beziehen</li>
            </ul>
        </div>
    </div>
</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'System & Sicherheit');
$fragment->setVar('body', $systemInfo, false);
echo $fragment->parse('core/page/section.php');

?>