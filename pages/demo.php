<?php
/**
 * PDFOut Demo-Seite
 */
use FriendsOfRedaxo\PdfOut\PdfOut;
$addon = rex_addon::get('pdfout');

// Demo-Aktionen und Test-Tools verarbeiten
$message = '';
$error = '';

// Verfügbare Zertifikate laden
function getAvailableCertificates($addon) {
    $certificates = [];
    $certificatesDir = $addon->getDataPath('certificates/');
    
    if (is_dir($certificatesDir)) {
        $files = glob($certificatesDir . '*.p12');
        foreach ($files as $file) {
            $filename = basename($file);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            
            // Versuche Zertifikatsdetails zu laden
            $displayName = $name;
            $isDefault = $filename === 'default.p12';
            
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
                            if ($isDefault) {
                                $displayName .= ' (Standard)';
                            }
                        }
                        break;
                    }
                }
            }
            
            $certificates[$filename] = [
                'filename' => $filename,
                'name' => $name,
                'display_name' => $displayName,
                'path' => $file,
                'is_default' => $isDefault
            ];
        }
    }
    
    return $certificates;
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
        
        $message = 'Test-Zertifikat wurde erfolgreich generiert!<br>Pfad: ' . $certPath . '<br>Passwort: ' . $password;
        
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
    'password' => 'redaxo123', // Test-Zertifikatspasswort
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

// Funktion zum Ermitteln des gewählten Zertifikats
function getSelectedCertificate($addon) {
    $certificatesDir = $addon->getDataPath('certificates/');
    
    // 1. Priorität: Gespeicherte Demo-Einstellungen
    $selectedCert = $addon->getConfig('demo_certificate', '');
    $certificatePassword = $addon->getConfig('demo_certificate_password', 'redaxo123');
    
    if (!empty($selectedCert) && file_exists($certificatesDir . $selectedCert)) {
        $certPath = $certificatesDir . $selectedCert;
        $certName = pathinfo($selectedCert, PATHINFO_FILENAME);
    } else {
        // 2. Fallback: Standard-Zertifikat
        $certPath = $certificatesDir . 'default.p12';
        $certName = 'default';
        $certificatePassword = 'redaxo123'; // Standard-Passwort für default.p12
    }
    
    if (!file_exists($certPath)) {
        throw new Exception('Kein Zertifikat verfügbar. Bitte erstellen Sie zunächst ein Zertifikat in der Zertifikatsverwaltung oder generieren Sie ein Standard-Zertifikat.');
    }
    
    return [
        'path' => $certPath,
        'password' => $certificatePassword,
        'name' => $certName
    ];
}

if (rex_post('demo-action')) {
    $action = rex_post('demo-action', 'string');
    
    switch ($action) {
        case 'simple_pdf':
            try {
                // Output-Buffer komplett leeren
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                $pdf = new PdfOut();
                $pdf->setName('einfaches_pdf')
                    ->setHtml('<h1>Einfaches PDF</h1><p>Dies ist ein einfaches PDF ohne erweiterte Features.</p><p>Schnell und unkompliziert erstellt mit REDAXO PdfOut.</p><p>Erstellt am: ' . date('d.m.Y H:i:s') . '</p>')
                    ->run();
                    
            } catch (Exception $e) {
                $error = 'Fehler beim Erstellen des einfachen PDFs: ' . $e->getMessage();
            }
            break;
            
        case 'password_protected_pdf':
            try {
                // Output-Buffer komplett leeren
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Verwende TCPDF für Passwortschutz (dompdf unterstützt keine Passwörter)
                require_once rex_path::addon('pdfout') . 'vendor/tecnickcom/tcpdf/tcpdf.php';
                
                $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                
                // Dokument-Informationen
                $pdf->SetCreator('REDAXO PdfOut Demo');
                $pdf->SetAuthor('REDAXO Demo');
                $pdf->SetTitle('Passwortgeschütztes PDF');
                $pdf->SetSubject('Demo eines passwortgeschützten PDFs');
                
                // Passwortschutz aktivieren
                // User-Passwort: 'user123' (zum Öffnen)
                // Owner-Passwort: 'owner123' (für Vollzugriff)
                // Berechtigungen: Drucken und Kopieren erlaubt
                $pdf->SetProtection(
                    ['print', 'copy'],  // Erlaubte Aktionen
                    'user123',          // User-Passwort (zum Öffnen des PDFs)
                    'owner123'          // Owner-Passwort (für Vollzugriff)
                );
                
                // Seite hinzufügen
                $pdf->AddPage();
                $pdf->SetFont('dejavusans', '', 12);
                
                // Professioneller Inhalt mit Passwort-Informationen
                $html = '
                <style>
                    .header { color: #d32f2f; font-weight: bold; margin-bottom: 20px; }
                    .password-info { background-color: #fff3e0; border: 2px solid #ff9800; padding: 15px; margin: 20px 0; border-radius: 5px; }
                    .security-note { background-color: #e8f5e8; border: 2px solid #4caf50; padding: 15px; margin: 20px 0; border-radius: 5px; }
                    .demo-content { margin: 20px 0; }
                    .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #ccc; font-size: 10px; color: #666; }
                </style>
                
                <div class="header">
                    <h1>🔒 Passwortgeschütztes PDF</h1>
                    <p>Demonstration von PDF-Sicherheitsfeatures mit TCPDF</p>
                </div>
                
                <div class="password-info">
                    <h3>🔑 Passwort-Informationen</h3>
                    <p><strong>User-Passwort (zum Öffnen):</strong> user123</p>
                    <p><strong>Owner-Passwort (Vollzugriff):</strong> owner123</p>
                    <p><strong>Berechtigungen:</strong> Drucken und Kopieren erlaubt</p>
                </div>
                
                <div class="demo-content">
                    <h2>📋 Inhalt des geschützten Dokuments</h2>
                    <p>Dieses PDF demonstriert verschiedene Sicherheitsfeatures:</p>
                    <ul>
                        <li><strong>Passwortschutz:</strong> PDF erfordert Passwort zum Öffnen</li>
                        <li><strong>Berechtigungen:</strong> Kontrollierte Zugriffe auf Funktionen</li>
                        <li><strong>Dokumentenschutz:</strong> Schutz vor unberechtigten Änderungen</li>
                        <li><strong>Compliance:</strong> Erfüllung von Sicherheitsrichtlinien</li>
                    </ul>
                </div>
                
                <div class="security-note">
                    <h3>🛡️ Sicherheitshinweise</h3>
                    <p>In produktiven Umgebungen sollten Sie:</p>
                    <ul>
                        <li>Starke, zufällige Passwörter verwenden</li>
                        <li>Passwörter sicher übertragen (nicht im PDF selbst)</li>
                        <li>Berechtigungen nach Bedarf einschränken</li>
                        <li>Regelmäßige Passwort-Updates durchführen</li>
                    </ul>
                </div>
                
                <div class="demo-content">
                    <h2>💼 Praktische Anwendungsfälle</h2>
                    <p><strong>Vertrauliche Berichte:</strong> Firmeninterne Dokumente mit begrenztem Zugang</p>
                    <p><strong>Personaldaten:</strong> Schutz sensibler Mitarbeiterinformationen</p>
                    <p><strong>Finanzberichte:</strong> Geschützte Übertragung von Finanzdaten</p>
                    <p><strong>Rechtsdokumente:</strong> Schutz vor unbefugten Änderungen</p>
                </div>
                
                <div class="footer">
                    <p><strong>Erstellt am:</strong> ' . date('d.m.Y H:i:s') . '</p>
                    <p><strong>System:</strong> REDAXO PdfOut AddOn</p>
                    <p><strong>Schutz:</strong> TCPDF Password Protection</p>
                    <p><strong>Status:</strong> Demo-Passwörter (nicht für Produktion verwenden!)</p>
                </div>';
                
                $pdf->writeHTML($html, true, false, true, false, '');
                
                // PDF ausgeben mit korrekten Headern
                $pdfContent = $pdf->Output('', 'S');
                
                // Output-Buffer komplett leeren
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Headers setzen
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="passwort_geschuetzt.pdf"');
                header('Content-Length: ' . strlen($pdfContent));
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                echo $pdfContent;
                exit;
                
            } catch (Exception $e) {
                $error = 'Fehler beim Erstellen des passwortgeschützten PDFs: ' . $e->getMessage();
            }
            break;
            
        case 'password_workflow_demo':
            try {
                // Output-Buffer komplett leeren
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Verwende die neue createPasswordProtectedWorkflow-Methode
                $htmlContent = '
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    h1 { color: #d63384; border-bottom: 2px solid #d63384; padding-bottom: 10px; }
                    h2 { color: #0d6efd; margin-top: 30px; }
                    .feature-box { background: #f8f9fa; padding: 15px; border-left: 4px solid #0d6efd; margin: 15px 0; }
                    .security-note { background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 20px 0; }
                    .demo-content { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0; }
                    .footer { border-top: 1px solid #ddd; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #666; }
                    ul { line-height: 1.6; }
                </style>
                
                <h1>🔐 Passwortschutz-Workflow Demo</h1>
                <p>Diese Demo zeigt den optimierten Workflow: <strong>dompdf → Cache → TCPDF-Passwortschutz</strong></p>
                
                <div class="feature-box">
                    <h2>🚀 Workflow-Vorteile</h2>
                    <ul>
                        <li><strong>Beste Qualität:</strong> dompdf für perfekte HTML/CSS-Unterstützung</li>
                        <li><strong>Performance:</strong> Optimierte Zwischenspeicherung</li>
                        <li><strong>Sicherheit:</strong> TCPDF für professionellen Passwortschutz</li>
                        <li><strong>Einfachheit:</strong> Ein Methodenaufruf für komplette Funktionalität</li>
                        <li><strong>Aufräumen:</strong> Automatische Bereinigung temporärer Dateien</li>
                    </ul>
                </div>
                
                <div class="security-note">
                    <h2>🛡️ Passwort-Details für diese Demo</h2>
                    <p><strong>User-Passwort:</strong> demo123 (zum Öffnen der Datei)</p>
                    <p><strong>Owner-Passwort:</strong> demo123_owner (für Vollzugriff)</p>
                    <p><strong>Berechtigungen:</strong> Nur Drucken erlaubt</p>
                    <p><em>Hinweis: Diese Passwörter sind nur für Demo-Zwecke!</em></p>
                </div>
                
                <div class="demo-content">
                    <h2>💼 Verwendung im Code</h2>
                    <pre><code>$pdf = new PdfOut();
$pdf->createPasswordProtectedWorkflow(
    $htmlContent,     // HTML-Inhalt
    "demo123",        // User-Passwort
    "demo123_owner",  // Owner-Passwort  
    ["print"],        // Berechtigungen
    "workflow.pdf"    // Dateiname
);</code></pre>
                </div>
                
                <div class="footer">
                    <p><strong>Erstellt am:</strong> ' . date('d.m.Y H:i:s') . '</p>
                    <p><strong>System:</strong> REDAXO PdfOut AddOn</p>
                    <p><strong>Methode:</strong> createPasswordProtectedWorkflow()</p>
                    <p><strong>Engine:</strong> dompdf → TCPDF Workflow</p>
                </div>';
                
                $pdf = new PdfOut();
                $result = $pdf->createPasswordProtectedWorkflow(
                    $htmlContent,
                    'demo123',           // User-Passwort
                    'demo123_owner',     // Owner-Passwort
                    ['print'],           // Nur Drucken erlaubt
                    'workflow_demo.pdf'  // Dateiname
                );
                
                if (!$result) {
                    throw new Exception('Workflow konnte nicht ausgeführt werden');
                }
                
                exit; // Workflow hat bereits Output gesendet
                
            } catch (Exception $e) {
                $error = 'Fehler beim Passwortschutz-Workflow: ' . $e->getMessage();
            }
            break;
            
        case 'pdf_merge_demo':
            try {
                // Output-Buffer komplett leeren
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Mehrere HTML-Inhalte für Demo
                $htmlContents = [
                    '
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { color: #0d6efd; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; }
                        .info-box { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0; }
                        .footer { border-top: 1px solid #ddd; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #666; }
                    </style>
                    <h1>📄 Dokument 1: Projektübersicht</h1>
                    <div class="info-box">
                        <h2>Projekt: REDAXO PdfOut</h2>
                        <p><strong>Beschreibung:</strong> Professionelle PDF-Erstellung für REDAXO</p>
                        <p><strong>Features:</strong> HTML zu PDF, Digitale Signaturen, Passwortschutz</p>
                        <p><strong>Status:</strong> Release-Ready</p>
                    </div>
                    <div class="footer">
                        <p>Dokument 1 von 3 - Erstellt am: ' . date('d.m.Y H:i:s') . '</p>
                    </div>',
                    
                    '
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { color: #198754; border-bottom: 2px solid #198754; padding-bottom: 10px; }
                        .feature-list { background: #f8f9fa; padding: 15px; border-left: 4px solid #198754; margin: 20px 0; }
                        .footer { border-top: 1px solid #ddd; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #666; }
                        ul { line-height: 1.6; }
                    </style>
                    <h1>✨ Dokument 2: Feature-Liste</h1>
                    <div class="feature-list">
                        <h2>Neue Workflow-Methoden</h2>
                        <ul>
                            <li><strong>createSignedWorkflow():</strong> Signierte PDFs mit einem Aufruf</li>
                            <li><strong>createPasswordProtectedWorkflow():</strong> Passwortgeschützte PDFs</li>
                            <li><strong>mergePdfs():</strong> PDF-Zusammenführung</li>
                            <li><strong>mergeHtmlToPdf():</strong> HTML-Inhalte zu einem PDF</li>
                        </ul>
                    </div>
                    <div class="footer">
                        <p>Dokument 2 von 3 - Erstellt am: ' . date('d.m.Y H:i:s') . '</p>
                    </div>',
                    
                    '
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { color: #dc3545; border-bottom: 2px solid #dc3545; padding-bottom: 10px; }
                        .conclusion { background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 20px 0; }
                        .footer { border-top: 1px solid #ddd; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #666; }
                    </style>
                    <h1>🎉 Dokument 3: Fazit</h1>
                    <div class="conclusion">
                        <h2>PDF-Zusammenführung erfolgreich!</h2>
                        <p>Diese drei separaten HTML-Dokumente wurden automatisch zu einem einzigen PDF zusammengeführt.</p>
                        <p><strong>Vorteile der mergeHtmlToPdf()-Methode:</strong></p>
                        <ul>
                            <li>Alle dompdf-Settings werden berücksichtigt</li>
                            <li>Optimale HTML/CSS-Unterstützung</li>
                            <li>Automatische Zwischenspeicherung und Aufräumen</li>
                            <li>Einfache Verwendung</li>
                        </ul>
                    </div>
                    <div class="footer">
                        <p>Dokument 3 von 3 - Zusammengeführt am: ' . date('d.m.Y H:i:s') . '</p>
                        <p><strong>Methode:</strong> mergeHtmlToPdf() - REDAXO PdfOut AddOn</p>
                    </div>'
                ];
                
                // PDF-Zusammenführung mit aktuellen Settings
                $pdf = new PdfOut();
                $result = $pdf->mergeHtmlToPdf(
                    $htmlContents,
                    'zusammengefuehrtes_dokument.pdf',
                    true // Trennseiten zwischen Dokumenten
                );
                
                if (!$result) {
                    throw new Exception('PDF-Zusammenführung konnte nicht ausgeführt werden');
                }
                
                exit; // Methode hat bereits Output gesendet
                
            } catch (Exception $e) {
                $error = 'Fehler bei der PDF-Zusammenführung: ' . $e->getMessage();
            }
            break;
            
        case 'clean_signature_demo':
            try {
                // Output-Buffer komplett leeren
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Ausgewähltes Zertifikat ermitteln
                $selectedCert = getSelectedCertificate($addon);
                
                // Verwende TCPDF direkt für saubere Signatur
                require_once rex_path::addon('pdfout') . 'vendor/tecnickcom/tcpdf/tcpdf.php';
                
                $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                
                // Dokument-Informationen
                $pdf->SetCreator('REDAXO PdfOut Demo');
                $pdf->SetAuthor('REDAXO Demo');
                $pdf->SetTitle('PDF mit digitaler Signatur');
                $pdf->SetSubject('Demo einer sauberen PDF-Signatur');
                
                // Digitale Signatur konfigurieren mit ausgewähltem Zertifikat
                $pdf->setSignature($selectedCert['path'], $selectedCert['path'], $selectedCert['password'], '', 2, [
                    'Name' => 'REDAXO Clean Signature Demo (' . $selectedCert['name'] . ')',
                    'Location' => 'Demo Environment', 
                    'Reason' => 'Demonstration of clean PDF signature',
                    'ContactInfo' => 'demo@redaxo.org'
                ]);
                
                // Seite hinzufügen
                $pdf->AddPage();
                $pdf->SetFont('helvetica', '', 12);
                
                // Inhalt
                $html = '<h1>PDF mit digitaler Signatur</h1>';
                $html .= '<p>Dies ist eine Demonstration einer sauberen digitalen PDF-Signatur.</p>';
                $html .= '<p><strong>Erstellt am:</strong> ' . date("d.m.Y H:i:s") . '</p>';
                $html .= '<p><strong>System:</strong> REDAXO PdfOut AddOn</p>';
                $html .= '<p><strong>Methode:</strong> Direkte TCPDF-Signierung</p>';
                $html .= '<p>Diese Signatur wird von Standard-Tools als "Total document signed" erkannt.</p>';
                
                $pdf->writeHTML($html, true, false, true, false, '');
                
                // Sichtbare Signatur hinzufügen (optional) - auskommentiert für sauberes Layout
                // $pdf->setSignatureAppearance(15, 250, 80, 20);
                
                // PDF ausgeben mit korrekten Headern
                $pdfContent = $pdf->Output('', 'S');
                
                // Output-Buffer komplett leeren
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Headers setzen
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="clean_signature_demo.pdf"');
                header('Content-Length: ' . strlen($pdfContent));
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                echo $pdfContent;
                exit;
                
            } catch (Exception $e) {
                $error = 'Fehler beim Erstellen der digitalen Signatur: ' . $e->getMessage();
            }
            break;
            
        case 'nachtraegliche_signierung':
            try {
                // Output-Buffer komplett leeren
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Cache-Verzeichnis sicherstellen
                $cacheDir = rex_path::addonCache('pdfout');
                if (!is_dir($cacheDir)) {
                    rex_dir::create($cacheDir);
                }
                
                // 1. Erstelle ein Original-PDF mit PdfOut (bessere HTML-Verarbeitung)
                $originalPdf = new PdfOut();
                $originalHtml = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Original PDF</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        .info { background: #f0f0f0; padding: 10px; border-left: 3px solid #007cba; }
    </style>
</head>
<body>
    <h1>Original PDF für nachträgliche Signierung</h1>
    <div class="info">
        <p><strong>Erstellt am:</strong> ' . date("d.m.Y H:i:s") . '</p>
        <p><strong>Status:</strong> Unsigniert (Original)</p>
        <p><strong>Nächster Schritt:</strong> Digitale Signierung</p>
    </div>
    <p>Dieses PDF wird nachträglich digital signiert, um seine Authentizität und Integrität zu gewährleisten.</p>
    <p>Die nachträgliche Signierung erhält alle Original-Inhalte und fügt eine gültige digitale Signatur hinzu.</p>
</body>
</html>';
                
                // Temporäre Dateien
                $tempOriginal = $cacheDir . 'temp_original_' . uniqid() . '.pdf';
                $tempSigned = $cacheDir . 'temp_signed_' . uniqid() . '.pdf';
                
                // Original PDF erstellen und speichern
                $originalPdf->setHtml($originalHtml);
                $originalPdf->setSaveToPath($cacheDir);
                $originalPdf->setName(basename($tempOriginal, '.pdf'));
                $originalPdf->setSaveAndSend(false);
                $originalPdf->run();
                
                // Prüfen ob Original erstellt wurde
                if (!file_exists($tempOriginal)) {
                    throw new Exception('Original-PDF konnte nicht erstellt werden');
                }
                
                // 2. Nachträgliche Signierung mit FPDI + TCPDF
                require_once rex_path::addon('pdfout') . 'vendor/tecnickcom/tcpdf/tcpdf.php';
                require_once rex_path::addon('pdfout') . 'vendor/setasign/fpdi/src/autoload.php';
                
                // Ausgewähltes Zertifikat ermitteln
                $selectedCert = getSelectedCertificate($addon);
                
                $pdf = new setasign\Fpdi\Tcpdf\Fpdi();
                
                // Digitale Signatur konfigurieren mit ausgewähltem Zertifikat
                $pdf->setSignature($selectedCert['path'], $selectedCert['path'], $selectedCert['password'], '', 2, [
                    'Name' => 'REDAXO Nachträgliche Signierung (' . $selectedCert['name'] . ')',
                    'Location' => 'Demo Environment',
                    'Reason' => 'Nachträgliche Signierung zur Authentifizierung',
                    'ContactInfo' => 'demo@redaxo.org'
                ]);
                
                // Original PDF importieren
                $pageCount = $pdf->setSourceFile($tempOriginal);
                
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $pdf->AddPage();
                    $templateId = $pdf->importPage($pageNo);
                    $pdf->useTemplate($templateId);
                }
                
                // Sichtbare Signatur auf der letzten Seite - auskommentiert für sauberes Layout
                // $pdf->setSignatureAppearance(15, 250, 80, 20);
                
                // Signiertes PDF speichern
                $signedContent = $pdf->Output('', 'S');
                file_put_contents($tempSigned, $signedContent);
                
                if (!file_exists($tempSigned)) {
                    throw new Exception('Signiertes PDF konnte nicht erstellt werden');
                }
                
                // PDF ausgeben mit korrekten Headern
                // Output-Buffer komplett leeren falls noch nicht geschehen
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Headers setzen
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="nachtraeglich_signiert.pdf"');
                header('Content-Length: ' . filesize($tempSigned));
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                readfile($tempSigned);
                
                // Temporäre Dateien aufräumen
                if (file_exists($tempOriginal)) unlink($tempOriginal);
                if (file_exists($tempSigned)) unlink($tempSigned);
                
                exit;
                
            } catch (Exception $e) {
                $error = 'Fehler bei der nachträglichen Signierung: ' . $e->getMessage();
                // Aufräumen auch bei Fehlern
                if (isset($tempOriginal) && file_exists($tempOriginal)) unlink($tempOriginal);
                if (isset($tempSigned) && file_exists($tempSigned)) unlink($tempSigned);
            }
            break;
            
        case 'redaxo_workflow_demo':
            try {
                // Output-Buffer komplett leeren
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Cache-Verzeichnis sicherstellen
                $cacheDir = rex_path::addonCache('pdfout');
                if (!is_dir($cacheDir)) {
                    rex_dir::create($cacheDir);
                }
                
                // 1. Schönes PDF mit dompdf/PdfOut erstellen (bessere HTML/CSS-Unterstützung)
                $originalPdf = new PdfOut();
                
                // UTF-8 Support für Unicode-Zeichen (inkl. Emojis) aktivieren
                $originalPdf->getOptions()->setIsRemoteEnabled(true);
                $originalPdf->getOptions()->setDefaultFont('DejaVu Sans');
                $originalPdf->getOptions()->setChroot($_SERVER['DOCUMENT_ROOT']);
                $originalPdf->getOptions()->setIsHtml5ParserEnabled(true);
                
                // Professionelles HTML mit modernem CSS
                $professionalHtml = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REDAXO Workflow Demo</title>
    <style>
        @page {
            margin: 2cm;
            size: A4;
        }
        
        body {
            font-family: "DejaVu Sans", "Helvetica", "Arial", sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            font-size: 10pt;
        }
        
        .header {
            background: linear-gradient(135deg, #007cba 0%, #005a87 100%);
            color: white;
            padding: 30px;
            margin-bottom: 30px;
            border-radius: 8px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 300;
        }
        
        .header p {
            margin: 0;
            font-size: 16px;
            opacity: 0.9;
        }
        
        .content-section {
            background: #f8f9fa;
            padding: 25px;
            margin: 20px 0;
            border-left: 4px solid #007cba;
            border-radius: 0 8px 8px 0;
        }
        
        .content-section h2 {
            color: #007cba;
            margin: 0 0 15px 0;
            font-size: 20px;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            margin: 20px 0;
        }
        
        .info-item {
            display: table-row;
        }
        
        .info-label {
            display: table-cell;
            font-weight: bold;
            padding: 8px 20px 8px 0;
            width: 30%;
            color: #555;
        }
        
        .info-value {
            display: table-cell;
            padding: 8px 0;
            color: #333;
        }
        
        .highlight-box {
            background: #e3f2fd;
            border: 1px solid #1976d2;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        
        .highlight-box h3 {
            margin: 0 0 10px 0;
            color: #1976d2;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        
        .status-badge {
            background: #4caf50;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .workflow-steps {
            counter-reset: step-counter;
        }
        
        .workflow-step {
            counter-increment: step-counter;
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 6px;
            border-left: 4px solid #4caf50;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .workflow-step::before {
            content: "Schritt " counter(step-counter) ": ";
            font-weight: bold;
            color: #4caf50;
        }
    </style>
</head>
<body>
   
    
    <div class="content-section">
        <h2>→ Typischer REDAXO-Anwendungsfall</h2>
        <p>Diese Demo zeigt den empfohlenen Workflow für PDF-Erstellung in REDAXO-Projekten:</p>
        
        <div class="workflow-steps">
            <div class="workflow-step">
                Erstelle ein professionelles PDF mit <strong>dompdf</strong> (beste HTML/CSS-Unterstützung)
            </div>
            <div class="workflow-step">
                Speichere das PDF zwischen (Cache, Media Manager oder temp. Verzeichnis)
            </div>
            <div class="workflow-step">
                Signiere das fertige PDF nachträglich mit <strong>FPDI + TCPDF</strong>
            </div>
        </div>
    </div>
    
    <div class="info-grid">
        <div class="info-item">
            <div class="info-label">Erstellt am:</div>
            <div class="info-value">' . date("d.m.Y H:i:s") . '</div>
        </div>
        <div class="info-item">
            <div class="info-label">System:</div>
            <div class="info-value">REDAXO ' . rex::getVersion() . '</div>
        </div>
        <div class="info-item">
            <div class="info-label">AddOn:</div>
            <div class="info-value">PdfOut (Workflow Demo)</div>
        </div>
        <div class="info-item">
            <div class="info-label">Verfahren:</div>
            <div class="info-value">dompdf → Cache → Nachträgliche Signierung</div>
        </div>
        <div class="info-item">
            <div class="info-label">Status:</div>
            <div class="info-value"><span class="status-badge">Digital signiert</span></div>
        </div>
    </div>
    <div style="page-break-after: always;"></div>
    <div class="highlight-box">
        <h3>★ Warum dieser Workflow?</h3>
        <ul>
            <li><strong>dompdf</strong> bietet exzellente HTML/CSS-Unterstützung für komplexe Layouts</li>
            <li><strong>Zwischenspeicherung</strong> ermöglicht Wiederverwendung und Performance-Optimierung</li>
            <li><strong>Nachträgliche Signierung</strong> erhält die perfekte Formatierung</li>
            <li><strong>FPDI</strong> importiert vorhandene PDFs verlustfrei</li>
            <li><strong>TCPDF</strong> fügt professionelle digitale Signaturen hinzu</li>
        </ul>
    </div>
    
    <div class="content-section">
        <h2>🔧 Technische Details</h2>
        <p>Dieses PDF wurde mit modernem CSS gestaltet, inklusive:</p>
        <ul>
            <li>Responsive Grid-Layout mit CSS Tables</li>
            <li>Gradients und Schatten</li>
            <li>Custom Counter für Workflow-Schritte</li>
            <li>Professionelle Typografie</li>
            <li>REDAXO Corporate Design Elemente</li>
        </ul>
    </div>
    
    <div class="footer">
        <p>Generiert mit REDAXO PdfOut • ' . date("Y") . ' • Demo für nachträgliche PDF-Signierung</p>
        <p>Diese Signatur ist unsichtbar aber von PDF-Readern erkennbar</p>
    </div>
</body>
</html>';
                
                // Temporäre Dateien definieren
                $tempOriginal = $cacheDir . 'redaxo_workflow_original_' . uniqid() . '.pdf';
                $tempSigned = $cacheDir . 'redaxo_workflow_signed_' . uniqid() . '.pdf';
                
                // Original PDF mit dompdf erstellen und zwischenspeichern
                $originalPdf->setHtml($professionalHtml);
                $originalPdf->setSaveToPath($cacheDir);
                $originalPdf->setName(basename($tempOriginal, '.pdf'));
                $originalPdf->setSaveAndSend(false);
                $originalPdf->run();
                
                // Prüfen ob Original erstellt wurde
                if (!file_exists($tempOriginal)) {
                    throw new Exception('Professionelles PDF konnte nicht mit dompdf erstellt werden');
                }
                
                // 2. Nachträgliche Signierung des zwischengespeicherten PDFs
                require_once rex_path::addon('pdfout') . 'vendor/tecnickcom/tcpdf/tcpdf.php';
                require_once rex_path::addon('pdfout') . 'vendor/setasign/fpdi/src/autoload.php';
                
                $pdf = new setasign\Fpdi\Tcpdf\Fpdi();
                
                // Ausgewähltes Zertifikat ermitteln
                $selectedCert = getSelectedCertificate($addon);
                
                // Digitale Signatur konfigurieren mit ausgewähltem Zertifikat
                $pdf->setSignature($selectedCert['path'], $selectedCert['path'], $selectedCert['password'], '', 2, [
                    'Name' => 'REDAXO Workflow Demo (' . $selectedCert['name'] . ')',
                    'Location' => 'REDAXO CMS Environment',
                    'Reason' => 'Demonstration des typischen REDAXO PDF-Workflows',
                    'ContactInfo' => 'demo@redaxo.org'
                ]);
                
                // Zwischengespeichertes PDF importieren
                $pageCount = $pdf->setSourceFile($tempOriginal);
                
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $pdf->AddPage();
                    $templateId = $pdf->importPage($pageNo);
                    $pdf->useTemplate($templateId);
                }
                
                // Unsichtbare Signatur (für sauberes Layout)
                // $pdf->setSignatureAppearance(15, 250, 80, 20); // Auskommentiert für saubere Darstellung
                
                // Signiertes PDF erstellen
                $signedContent = $pdf->Output('', 'S');
                file_put_contents($tempSigned, $signedContent);
                
                if (!file_exists($tempSigned)) {
                    throw new Exception('Signiertes PDF konnte nicht erstellt werden');
                }
                
                // PDF ausgeben mit korrekten Headern
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="redaxo_workflow_demo.pdf"');
                header('Content-Length: ' . filesize($tempSigned));
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                readfile($tempSigned);
                
                // Aufräumen
                if (file_exists($tempOriginal)) unlink($tempOriginal);
                if (file_exists($tempSigned)) unlink($tempSigned);
                
                exit;
                
            } catch (Exception $e) {
                $error = 'Fehler beim REDAXO Workflow Demo: ' . $e->getMessage();
                // Aufräumen auch bei Fehlern
                if (isset($tempOriginal) && file_exists($tempOriginal)) unlink($tempOriginal);
                if (isset($tempSigned) && file_exists($tempSigned)) unlink($tempSigned);
            }
            break;
            
        case 'redaxo_workflow_demo':
            try {
                $html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>REDAXO Workflow Demo</title>
<style>
@page {
    margin: 12mm;
    size: A4;
}
body {
    font-family: "DejaVu Sans", Arial, sans-serif;
    line-height: 1.3;
    color: #333;
    margin: 0;
    padding: 0;
    font-size: 10px;
}
.header {
    background: linear-gradient(135deg, #007cba 0%, #004d7a 100%);
    color: white;
    padding: 12px;
    margin-bottom: 12px;
    border-radius: 6px;
    text-align: center;
}
.header h1 {
    margin: 0 0 3px 0;
    font-size: 18px;
    font-weight: bold;
}
.header p {
    margin: 0;
    font-size: 11px;
    opacity: 0.9;
}
.two-column {
    display: table;
    width: 100%;
    table-layout: fixed;
}
.column {
    display: table-cell;
    width: 50%;
    vertical-align: top;
    padding-right: 8px;
}
.column:last-child {
    padding-right: 0;
    padding-left: 8px;
}
.section {
    background: #f8f9fa;
    border-left: 3px solid #007cba;
    padding: 6px 10px;
    margin: 8px 0;
    border-radius: 4px;
}
.section h3 {
    margin: 0 0 6px 0;
    color: #007cba;
    font-size: 12px;
}
.section p {
    margin: 4px 0;
    line-height: 1.3;
}
.workflow-steps {
    background: #e7f3ff;
    padding: 8px;
    border-radius: 6px;
    margin: 8px 0;
}
.step {
    margin: 3px 0;
    padding: 4px 6px;
    background: white;
    border-radius: 3px;
    border-left: 2px solid #28a745;
    font-size: 9px;
    line-height: 1.2;
}
.step-number {
    font-weight: bold;
    color: #28a745;
}
.info-table {
    width: 100%;
    border-collapse: collapse;
    margin: 6px 0;
}
.info-table td {
    padding: 2px 4px;
    border-bottom: 1px solid #eee;
    font-size: 9px;
    line-height: 1.2;
}
.info-table td:first-child {
    font-weight: bold;
    width: 40%;
    color: #555;
}
.highlight {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 6px;
    border-radius: 4px;
    margin: 8px 0;
    text-align: center;
    font-size: 9px;
}
.footer {
    margin-top: 12px;
    padding-top: 8px;
    border-top: 1px solid #007cba;
    text-align: center;
    font-size: 8px;
    color: #666;
}
.code-sample {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    padding: 6px;
    font-family: monospace;
    font-size: 8px;
    border-radius: 4px;
    margin: 6px 0;
    line-height: 1.1;
}
</style>
</head>
<body>
<div class="header">
<h1>▶ REDAXO Workflow Demo</h1>
<p>Professionelle PDF-Erstellung: dompdf → Cache → Signierung</p>
</div>
<div class="two-column">
<div class="column">
<div class="section">
<h3>🎯 Empfohlener REDAXO-Workflow</h3>
<p>Dieser Workflow kombiniert die Stärken von dompdf (HTML/CSS) mit TCPDF (Signierung) für optimale Ergebnisse.</p>
<div class="workflow-steps">
<div class="step"><span class="step-number">1.</span> PDF mit dompdf/PdfOut erstellen</div>
<div class="step"><span class="step-number">2.</span> Zwischenspeicherung im Cache</div>
<div class="step"><span class="step-number">3.</span> Nachträgliche Signierung (FPDI+TCPDF)</div>
<div class="step"><span class="step-number">4.</span> Ausgabe & Aufräumen</div>
</div>
</div>
<div class="section">
<h3>📊 Dokument-Informationen</h3>
<table class="info-table">
<tr><td>Erstellt am:</td><td>' . date('d.m.Y H:i:s') . '</td></tr>
<tr><td>System:</td><td>REDAXO ' . (class_exists('rex') ? rex::getVersion() : 'CMS') . '</td></tr>
<tr><td>PDF-Engine:</td><td>dompdf + TCPDF</td></tr>
<tr><td>Workflow:</td><td>Cache → Signierung</td></tr>
<tr><td>Signatur:</td><td>Digital (unsichtbar)</td></tr>
<tr><td>Layout:</td><td>Hochwertig (CSS-Support)</td></tr>
</table>
</div>
<div class="highlight">
<strong>🔒 Digital signiert</strong><br>
Unsichtbare Signatur, von PDF-Readern erkennbar
</div>
</div>
<div class="column">
<div class="section">
<h3>✨ Vorteile des Workflows</h3>
<p><strong>dompdf:</strong> Beste HTML/CSS-Unterstützung für komplexe Layouts</p>
<p><strong>TCPDF:</strong> Professionelle, zuverlässige Signaturen</p>
<p><strong>Cache:</strong> Performance-Optimierung und Wiederverwendung</p>
<p><strong>Flexibilität:</strong> PDF vor Signierung validieren/anpassen</p>
</div>
<div class="section">
<h3>💻 Code-Beispiel (vereinfacht)</h3>
<div class="code-sample">
// Neue Workflow-Methode verwenden:
$pdf = new PdfOut();
$pdf->createSignedDocument($html, \'dokument.pdf\');

// Oder mit erweiterten Optionen:
$pdf->createSignedWorkflow(
    $html,
    $certPath,
    $password,
    [\'Name\' => \'Max Mustermann\'],
    \'rechnung.pdf\'
);
</div>
</div>
<div class="section">
<h3>🔧 Praktische Anwendung</h3>
<p><strong>Rechnungen:</strong> Komplexe Tabellen + rechtsgültige Signatur</p>
<p><strong>Zertifikate:</strong> Design-Layouts + Authentifizierung</p>
<p><strong>Berichte:</strong> Charts/Grafiken + Vertrauensschutz</p>
<p><strong>Verträge:</strong> Formatierung + digitale Unterschrift</p>
</div>
</div>
</div>
<div class="footer">
<p>REDAXO PdfOut • Workflow Demo • ' . date('Y') . ' • Einseitig, kompakt, signiert</p>
</div>
</body>
</html>';
                
                // 2. Neue Workflow-Methode verwenden - nur eine Zeile!
                $pdf = new PdfOut();
                $pdf->createSignedDocument($html, 'redaxo_workflow_demo.pdf');
                
                exit;
                
            } catch (Exception $e) {
                $error = 'Fehler beim REDAXO Workflow Demo: ' . $e->getMessage();
            }
            break;
            
        case 'pdfjs_integration':
            try {
                // JavaScript für Viewer-Weiterleitung ausgeben
                $pdfJsInfoHtml = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>PDF.js Integration Guide</title>
    <style>
        @page { margin: 15mm; size: A4; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; line-height: 1.4; color: #333; margin: 0; padding: 0; font-size: 11px; }
        .header { background: linear-gradient(135deg, #007cba 0%, #004d7a 100%); color: white; padding: 15px; margin-bottom: 20px; border-radius: 8px; text-align: center; }
        .header h1 { margin: 0 0 5px 0; font-size: 20px; font-weight: bold; }
        .header p { margin: 0; font-size: 12px; opacity: 0.9; }
        .section { background: #f8f9fa; border-left: 4px solid #007cba; padding: 12px 15px; margin: 15px 0; border-radius: 6px; }
        .section h2 { margin: 0 0 10px 0; color: #007cba; font-size: 14px; }
        .code-block { background: #f8f9fa; border: 1px solid #e9ecef; padding: 10px; font-family: "Courier New", monospace; font-size: 9px; border-radius: 4px; margin: 10px 0; line-height: 1.3; overflow-wrap: break-word; }
        .highlight { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 8px; border-radius: 4px; margin: 10px 0; font-size: 10px; }
        .step { margin: 8px 0; padding: 6px 10px; background: white; border-radius: 4px; border-left: 3px solid #28a745; font-size: 10px; }
        .step-number { font-weight: bold; color: #28a745; }
        .footer { margin-top: 20px; padding-top: 15px; border-top: 2px solid #007cba; text-align: center; font-size: 9px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>📄 PDF.js Integration Guide</h1>
        <p>Komplette Anleitung zur Einbindung des PDF.js Viewers in REDAXO</p>
    </div>
    
    <div class="section">
        <h2>🚀 Schnellstart mit PdfOut::viewer()</h2>
        <p><strong>Die empfohlene Methode</strong> für PDF.js Integration in REDAXO:</p>
        <div class="code-block">use FriendsOfRedaxo\\PdfOut\\PdfOut;

// PDF-Viewer URL generieren
$viewerUrl = PdfOut::viewer(\'pfad/zu/ihrem/dokument.pdf\');

// Als iFrame einbetten  
echo \'&lt;iframe src="\' . $viewerUrl . \'" width="100%" height="600"&gt;&lt;/iframe&gt;\';

// Als Link verwenden
echo \'&lt;a href="\' . $viewerUrl . \'" target="_blank"&gt;PDF öffnen&lt;/a&gt;\';</div>
    </div>
    
    <div class="section">
        <h2>📋 Schritt-für-Schritt Integration</h2>
        <div class="step"><span class="step-number">1.</span> PDF-Datei in assets/addons/pdfout/vendor/web/ ablegen</div>
        <div class="step"><span class="step-number">2.</span> PdfOut::viewer() Methode mit relativem Pfad aufrufen</div>
        <div class="step"><span class="step-number">3.</span> URL in HTML einbetten (iFrame oder Link)</div>
        <div class="step"><span class="step-number">4.</span> Viewer automatisch geladen - fertig!</div>
    </div>
    
    <div class="section">
        <h2>🎯 Praktische Beispiele</h2>
        <p><strong>REDAXO Template Integration:</strong></p>
        <div class="code-block">// Im Template
$pdfFile = \'berichte/jahresbericht.pdf\';
$viewerUrl = PdfOut::viewer($pdfFile);
?&gt;
&lt;div class="pdf-container"&gt;
    &lt;iframe src="&lt;?= $viewerUrl ?&gt;" style="width:100%; height:80vh; border:none;"&gt;&lt;/iframe&gt;
&lt;/div&gt;</div>
        
        <p><strong>Backend Widget:</strong></p>
        <div class="code-block">class PdfViewerWidget extends rex_form_widget {
    public function formatElement() {
        $viewerUrl = PdfOut::viewer($this->getValue());
        return \'&lt;iframe src="\' . $viewerUrl . \'" style="width:100%; height:400px; border:1px solid #ddd;"&gt;&lt;/iframe&gt;\';
    }
}</div>
    </div>
    
    <div class="highlight">
        <strong>💡 Tipp:</strong> Der PDF.js Viewer unterstützt Volltext-Suche, Navigation, Zoom, Drucken und Download - alles automatisch verfügbar!
    </div>
    
    <div class="footer">
        <p><strong>REDAXO PdfOut AddOn v10.1.0</strong> • PDF.js 5.x • ' . date('d.m.Y H:i:s') . '</p>
        <p>Weitere Informationen: <strong>Backend → AddOns → PdfOut → API Referenz</strong></p>
    </div>
</body>
</html>';
                
                // PDF direkt erstellen und an Browser senden, dann JavaScript für Viewer-Weiterleitung
                $pdf = new PdfOut();
                $pdf->setName('pdfjs_integration_guide')
                    ->setHtml($pdfJsInfoHtml)
                    ->setPaperSize('A4', 'portrait')
                    ->setFont('Dejavu Sans')
                    ->setAttachment(false); // Inline anzeigen
                
                // Output Buffer leeren
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // PDF direkt ausgeben - wird vom Browser direkt im PDF.js Viewer geöffnet
                $pdf->run();
                exit;
                    
            } catch (Exception $e) {
                $error = 'Fehler bei der PDF.js Integration Demo: ' . $e->getMessage();
            }
            break;
            
        default:
            $error = 'Unbekannte Demo-Aktion: ' . $action;
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

// Zertifikat-Status für Demos anzeigen
$availableCertificates = getAvailableCertificates($addon);
$certificateSelection = '';

// Gespeicherte Demo-Einstellungen prüfen
$savedDemoCert = $addon->getConfig('demo_certificate', '');
$savedDemoPassword = $addon->getConfig('demo_certificate_password', 'redaxo123');

// Standard-Zertifikat prüfen falls keine Auswahl gespeichert
$defaultCertExists = file_exists($addon->getDataPath('certificates/default.p12'));

if (!empty($savedDemoCert) && isset($availableCertificates[$savedDemoCert])) {
    // Gespeichertes Zertifikat ist verfügbar
    $selectedCert = $availableCertificates[$savedDemoCert];
    $certificateSelection = '
    <div class="alert alert-success">
        <h4><i class="fa fa-certificate"></i> Zertifikat für Signatur-Demos</h4>
        <p><strong>Aktuell verwendet:</strong> ' . rex_escape($selectedCert['display_name']) . '</p>
        <p><strong>Passwort:</strong> ' . (strlen($savedDemoPassword) > 0 ? str_repeat('*', strlen($savedDemoPassword)) : 'Nicht gesetzt') . '</p>
        <p><small class="text-muted">Konfiguriert in der <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/certificates']) . '" class="alert-link">Zertifikatsverwaltung</a></small></p>
    </div>';
} elseif ($defaultCertExists) {
    // Fallback auf Standard-Zertifikat
    $certificateSelection = '
    <div class="alert alert-info">
        <h4><i class="fa fa-certificate"></i> Zertifikat für Signatur-Demos</h4>
        <p><strong>Verwendet:</strong> Standard-Zertifikat (default.p12)</p>
        <p><strong>Passwort:</strong> redaxo123 (Standard)</p>
        <p><small class="text-muted">Für eine individuelle Auswahl besuchen Sie die <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/certificates']) . '" class="alert-link">Zertifikatsverwaltung</a></small></p>
    </div>';
} else {
    // Keine Zertifikate verfügbar
    $certificateSelection = '
    <div class="alert alert-warning">
        <h4><i class="fa fa-exclamation-triangle"></i> Kein Zertifikat für Signatur-Demos verfügbar</h4>
        <p>Signatur-Demos benötigen ein Zertifikat. Bitte erstellen oder konfigurieren Sie zunächst ein Zertifikat:</p>
        <div style="margin-top: 15px;">
            <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/certificates']) . '" class="btn btn-primary">
                <i class="fa fa-certificate"></i> Zur Zertifikatsverwaltung
            </a>
            <span style="margin: 0 10px;">oder</span>
            <form method="post" style="display: inline;">
                <input type="hidden" name="generate-test-certificate" value="1">
                <button type="submit" class="btn btn-success" onclick="return confirm(\'Standard-Zertifikat für Demos erstellen?\')">
                    <i class="fa fa-plus-circle"></i> Standard-Zertifikat erstellen
                </button>
            </form>
        </div>
    </div>';
}

$fragment = new rex_fragment();
$fragment->setVar('title', 'Zertifikat-Status für Signatur-Demos');
$fragment->setVar('body', $certificateSelection, false);
echo $fragment->parse('core/page/section.php');

// Bereinigte Demo-Definitionen - direkt integriert, funktionierend
$demos = [
    'simple_pdf' => [
        'title' => 'Einfaches PDF <span class="label label-success">dompdf</span>',
        'description' => 'Erstellt ein einfaches PDF ohne erweiterte Features. Ideal für schnelle Dokumente. <strong>Empfohlen für REDAXO</strong> - beste HTML/CSS-Unterstützung.',
        'panel_class' => 'panel-success',
        'btn_class' => 'btn-success',
        'icon' => 'fa-file-pdf-o',
        'code' => '$pdf = new PdfOut();
$pdf->setName(\'einfaches_pdf\')
    ->setHtml(\'<h1>Einfaches PDF</h1><p>Schnell und unkompliziert erstellt.</p>\')
    ->run();'
    ],
    'password_protected_pdf' => [
        'title' => '🔒 Passwortgeschütztes PDF <span class="label label-default">TCPDF</span>',
        'description' => 'Erstellt ein PDF mit Passwortschutz und konfigurierbaren Berechtigungen. <strong>Demo-Passwörter:</strong> Öffnen: <code>user123</code>, Vollzugriff: <code>owner123</code>. Direkte TCPDF-Nutzung.',
        'panel_class' => 'panel-default',
        'btn_class' => 'btn-default',
        'icon' => 'fa-lock',
        'code' => '$pdf = new TCPDF();
$pdf->SetProtection(
    [\'print\', \'copy\'],    // Erlaubte Aktionen
    \'user123\',             // User-Passwort (zum Öffnen)
    \'owner123\'             // Owner-Passwort (Vollzugriff)
);
$pdf->AddPage();
$pdf->SetFont(\'dejavusans\', \'\', 12);
$pdf->writeHTML($content);
$pdf->Output();'
    ],
    'password_workflow_demo' => [
        'title' => '🔐 Passwortschutz-Workflow <span class="label label-success">dompdf→TCPDF</span>',
        'description' => 'Erstellt ein PDF mit dem optimierten Workflow: <strong>dompdf → Cache → TCPDF-Passwortschutz</strong>. <strong>Demo-Passwörter:</strong> Öffnen: <code>demo123</code>, Vollzugriff: <code>demo123_owner</code>. <strong>Empfohlen für REDAXO</strong> - beste Qualität + Sicherheit.',
        'panel_class' => 'panel-success',
        'btn_class' => 'btn-success',
        'icon' => 'fa-shield',
        'code' => '$pdf = new PdfOut();
$pdf->createPasswordProtectedWorkflow(
    $htmlContent,     // HTML-Inhalt
    \'demo123\',        // User-Passwort
    \'demo123_owner\',  // Owner-Passwort  
    [\'print\'],        // Berechtigungen
    \'workflow.pdf\'    // Dateiname
);'
    ],
    'clean_signature_demo' => [
        'title' => 'PDF mit digitaler Signatur <span class="label label-default">TCPDF</span>',
        'description' => 'Erstellt ein vollständig digital signiertes PDF mit TCPDF. Wird von Standard-Tools als "Total document signed" erkannt. <strong>Signatur ist unsichtbar</strong> für sauberes Layout. Direkte TCPDF-Nutzung.',
        'panel_class' => 'panel-default',
        'btn_class' => 'btn-default',
        'icon' => 'fa-certificate',
        'code' => '$pdf = new TCPDF();
$pdf->setSignature($certPath, $certPath, \'password\', \'\', 2, [
    \'Name\' => \'Digitale Signatur\',
    \'Location\' => \'REDAXO System\', 
    \'Reason\' => \'Dokumentenschutz\'
]);
$pdf->AddPage();
$pdf->writeHTML($content);
// Unsichtbare Signatur (keine setSignatureAppearance)
$pdf->Output();'
    ],
    'nachtraegliche_signierung' => [
        'title' => 'Nachträgliche PDF-Signierung <span class="label label-default">FPDI+TCPDF</span>',
        'description' => 'Signiert bereits existierende PDFs nachträglich mit FPDI + TCPDF. Erhält alle Original-Inhalte und fügt eine <strong>unsichtbare</strong> digitale Signatur hinzu für sauberes Layout. Direkte FPDI-Nutzung.',
        'panel_class' => 'panel-default',
        'btn_class' => 'btn-default',
        'icon' => 'fa-edit',
        'code' => '$pdf = new setasign\\Fpdi\\Tcpdf\\Fpdi();
$pdf->setSignature($certPath, $certPath, \'password\', \'\', 2);
$pageCount = $pdf->setSourceFile(\'original.pdf\');
for ($i = 1; $i <= $pageCount; $i++) {
    $pdf->AddPage();
    $template = $pdf->importPage($i);
    $pdf->useTemplate($template);
}
// Unsichtbare Signatur (keine setSignatureAppearance)
$pdf->Output();'
    ],
    'redaxo_workflow_demo' => [
        'title' => '🚀 REDAXO Signatur-Workflow <span class="label label-success">dompdf→TCPDF</span>',
        'description' => '<strong>Empfohlen für REDAXO:</strong> Erstelle hochwertiges PDF mit dompdf/PdfOut (beste HTML/CSS-Unterstützung), speichere zwischen und signiere nachträglich mit FPDI+TCPDF. <strong>Neue Workflow-Methode - nur 2 Zeilen Code!</strong>',
        'panel_class' => 'panel-success',
        'btn_class' => 'btn-success',
        'icon' => 'fa-rocket',
        'code' => '// Neue vereinfachte Workflow-Methode (empfohlen):
$pdf = new PdfOut();
$pdf->createSignedDocument($html, \'dokument.pdf\');

// Oder mit erweiterten Optionen:
$pdf->createSignedWorkflow(
    $html,                          // HTML-Inhalt
    $certificatePath,               // Zertifikatspfad
    $certificatePassword,           // Zertifikatspasswort
    [\'Name\' => \'Max Mustermann\'], // Signatur-Info
    \'rechnung.pdf\'                 // Dateiname
);

// Was passiert intern:
// 1. PDF mit dompdf erstellen
// 2. Zwischenspeicherung im Cache
// 3. Nachträgliche Signierung mit FPDI+TCPDF
// 4. Ausgabe & automatisches Aufräumen'
    ],
    'pdfjs_integration' => [
        'title' => 'PDF.js Integration Guide',
        'description' => 'Erstellt Integration-Anleitung und öffnet sie direkt im PDF.js Viewer',
        'icon' => 'fa-file-pdf-o',
        'type' => 'info',
        'panel_class' => 'panel-info',
        'btn_class' => 'btn-info',
        'code' => '// 1. PdfOut eigene Viewer-Methode verwenden (empfohlen!)
use FriendsOfRedaxo\\PdfOut\\PdfOut;

// PDF-Viewer URL mit eigener Methode generieren
$viewerUrl = PdfOut::viewer(\'compressed.tracemonkey-pldi-09.pdf\');

// 2. HTML iFrame Integration
echo \'<iframe src="\' . $viewerUrl . \'" width="100%" height="600"></iframe>\';

// 3. Link zu externem Viewer  
echo \'<a href="\' . $viewerUrl . \'" target="_blank">PDF im Viewer öffnen</a>\';

// 4. Eigene PDFs einbinden
$myPdfFile = \'assets/addons/pdfout/vendor/web/mein-dokument.pdf\';
$myViewerUrl = PdfOut::viewer($myPdfFile);

// 5. Fragment/Template Integration
$fragment = new rex_fragment();
$fragment->setVar(\'viewer_url\', $myViewerUrl);
$fragment->setVar(\'pdf_title\', \'Mein Dokument\');
echo $fragment->parse(\'pdf_viewer.php\');

// 6. JavaScript API (erweitert)
?><script>
// PDF.js JavaScript Integration
const iframe = document.getElementById(\'pdf-viewer\');
iframe.onload = function() {
    // Zugriff auf PDF.js API im iframe
    const pdfViewer = iframe.contentWindow.PDFViewerApplication;
    
    // Zu Seite springen
    pdfViewer.page = 3;
    
    // Zoom setzen
    pdfViewer.pdfViewer.currentScaleValue = \'page-width\';
    
    // Events abfangen
    iframe.contentWindow.addEventListener(\'pagesinit\', function() {
        console.log(\'PDF geladen, Seiten:\', pdfViewer.pagesCount);
    });
};
</script><?php

// 7. REDAXO Backend Integration
class PdfViewerWidget extends rex_form_widget {
    public function formatElement() {
        $viewerUrl = PdfOut::viewer($this->getValue());
        return \'<iframe src="\' . $viewerUrl . \'" style="width:100%;height:400px;border:1px solid #ddd;"></iframe>\';
    }
}'
    ]
];

// Demo-Kästen generieren
$content = '<div class="row">';
$col_count = 0;
$userCanSign = rex::getUser() && rex::getUser()->hasPerm('pdfout[signature]');

// Prüfen ob Zertifikate für Signatur-Demos verfügbar sind
$certificateAvailable = false;
try {
    $testCert = getSelectedCertificate($addon);
    $certificateAvailable = !empty($testCert);
} catch (Exception $e) {
    $certificateAvailable = false;
}

foreach ($demos as $demo_key => $demo) {
    if ($col_count % 2 == 0 && $col_count > 0) {
        $content .= '</div><div class="row">';
    }
    
    $modal_id = 'modal-code-' . $demo_key;
    $needsSignature = in_array($demo_key, ['clean_signature_demo', 'nachtraegliche_signierung', 'redaxo_workflow_demo']);
    $isDisabled = ($needsSignature && !$userCanSign) || ($needsSignature && !$certificateAvailable);
    
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
        } elseif ($needsSignature && !$certificateAvailable) {
            $content .= '
                <div class="alert alert-warning">
                    <i class="fa fa-certificate"></i> Kein Zertifikat verfügbar. 
                    <a href="' . rex_url::currentBackendPage(['page' => 'pdfout/certificates']) . '" class="btn btn-xs btn-primary">
                        <i class="fa fa-certificate"></i> Zertifikat erstellen
                    </a>
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

// System OpenSSL prüfen
$opensslCheck = shell_exec('which openssl 2>/dev/null');
if ($opensslCheck) {
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
        <li><strong>Digitale Signaturen:</strong> Sind unsichtbar für sauberes PDF-Layout</li>
        <li><strong>Signatur-Erkennung:</strong> Wird trotzdem von PDF-Readern und Tools erkannt</li>
        <li><strong>Sichtbare Signaturen:</strong> Können durch setSignatureAppearance(x, y, width, height) aktiviert werden</li>
        <li><strong>Position:</strong> X=0, Y=0 ist die linke obere Ecke des Dokuments (in Punkten)</li>
        <li><strong>Test-Zertifikat:</strong> Nur für Entwicklung geeignet, Passwort: <code>redaxo123</code></li>
        <li><strong>Gültigkeit:</strong> Unsichtbare Signaturen bieten gleiche Sicherheit wie sichtbare</li>
        <li><strong>Passwort-PDFs:</strong> Demo-Passwörter: Öffnen <code>user123</code>, Vollzugriff <code>owner123</code></li>
        <li><strong>Sicherheit:</strong> In Produktion niemals Demo-Passwörter verwenden!</li>
    </ul>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Wichtige Hinweise');
$fragment->setVar('body', $notes, false);
echo $fragment->parse('core/page/section.php');

// PDF.js Viewer Test
$pdfJsTest = '
<div class="row">
    <div class="col-md-8">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><i class="fa fa-file-pdf-o"></i> PDF.js Viewer Test</h4>
            </div>
            <div class="panel-body">
                <p>Testen Sie den integrierten PDF.js Viewer mit einem Beispiel-PDF:</p>
                <div class="btn-group" style="margin-bottom: 15px;">
                    <a href="' . PdfOut::viewer('assets/addons/pdfout/vendor/web/compressed.tracemonkey-pldi-09.pdf') . '" 
                       target="_blank" 
                       class="btn btn-primary">
                        <i class="fa fa-external-link"></i> PDF.js Viewer öffnen
                    </a>
                    <a href="' . rex_url::addonAssets('pdfout', 'vendor/web/compressed.tracemonkey-pldi-09.pdf') . '" 
                       target="_blank" 
                       class="btn btn-default">
                        <i class="fa fa-download"></i> PDF direkt öffnen
                    </a>
                    <button type="button" 
                            class="btn btn-info" 
                            data-toggle="modal" 
                            data-target="#modal-code-pdfjs_integration">
                        <i class="fa fa-code"></i> Code anzeigen
                    </button>
                </div>
                
                <h5>Was wird getestet?</h5>
                <ul>
                    <li><strong>PDF.js:</strong> Neueste Version mit verbesserter Performance</li>
                    <li><strong>Viewer-Interface:</strong> Navigation, Zoom, Suche, Download</li>
                    <li><strong>Browser-Kompatibilität:</strong> Funktioniert in allen modernen Browsern</li>
                    <li><strong>WebAssembly:</strong> Schnelle PDF-Verarbeitung ohne Plugins</li>
                </ul>
                
                <div class="alert alert-info" style="margin-top: 15px;">
                    <strong>Test-PDF:</strong> "Trace-based Just-in-Time Type Specialization for Dynamic Languages"<br>
                    <strong>Größe:</strong> ~190 KB (komprimiert)<br>
                    <strong>Seiten:</strong> Mehrseitiges wissenschaftliches Dokument<br>
                    <strong>Features:</strong> Text, Formeln, Grafiken, Hyperlinks
                </div>
                
                <!-- Embedded PDF.js Viewer Demo -->
                <div style="margin-top: 20px;">
                    <h5><i class="fa fa-desktop"></i> Eingebetteter Viewer (Live-Demo)</h5>
                    <div class="alert alert-success" style="padding: 8px 12px;">
                        <small><i class="fa fa-info-circle"></i> <strong>Tipp:</strong> Nutzen Sie die Viewer-Kontrollen zum Navigieren, Zoomen und Suchen.</small>
                    </div>
                    <div style="border: 2px solid #ddd; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <iframe 
                            src="' . PdfOut::viewer('assets/addons/pdfout/vendor/web/compressed.tracemonkey-pldi-09.pdf') . '" 
                            width="100%" 
                            height="500" 
                            style="border: none; display: block;"
                            title="PDF.js Viewer Demo">
                        </iframe>
                    </div>
                    <div class="text-muted" style="margin-top: 8px; font-size: 12px;">
                        <i class="fa fa-code"></i> <strong>Integration:</strong> 
                        <code>&lt;iframe src="assets/addons/pdfout/vendor/web/viewer.html?file=ihr-dokument.pdf"&gt;&lt;/iframe&gt;</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h5><i class="fa fa-cogs"></i> PDF.js Features</h5>
            </div>
            <div class="panel-body">
                <h6>Navigation:</h6>
                <ul class="list-unstyled">
                    <li>• Seitenweise blättern</li>
                    <li>• Thumbnail-Übersicht</li>
                    <li>• Lesezeichen (falls vorhanden)</li>
                    <li>• Direktsprung zu Seiten</li>
                </ul>
                
                <h6>Darstellung:</h6>
                <ul class="list-unstyled">
                    <li>• Zoom (25% - 400%)</li>
                    <li>• Vollbild-Modus</li>
                    <li>• Anpassung an Fensterbreite</li>
                    <li>• Hochauflösende Darstellung</li>
                </ul>
                
                <h6>Tools:</h6>
                <ul class="list-unstyled">
                    <li>• Volltext-Suche</li>
                    <li>• Text-Auswahl & Kopieren</li>
                    <li>• PDF-Download</li>
                    <li>• Druckfunktion</li>
                </ul>
                
                <div class="alert alert-success" style="margin-top: 10px; margin-bottom: 0;">
                    <small><strong>Version:</strong> PDF.js 5.x<br>
                    <strong>Engine:</strong> WebAssembly optimiert</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-success">
    <h5><i class="fa fa-check-circle"></i> PDF.js Integration erfolgreich</h5>
    <p>Der PDF.js Viewer ist vollständig integriert und einsatzbereit. Sie können:</p>
    <ul style="margin-bottom: 0;">
        <li><strong>Eigene PDFs anzeigen:</strong> Einfach die URL im Viewer anpassen</li>
        <li><strong>In REDAXO einbetten:</strong> iFrame oder direkte Verlinkung möglich</li>
        <li><strong>Anpassen:</strong> CSS und JavaScript bei Bedarf modifizieren</li>
        <li><strong>Mobile Support:</strong> Responsive Design für alle Geräte</li>
    </ul>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'PDF.js Viewer Test');
$fragment->setVar('body', $pdfJsTest, false);
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
