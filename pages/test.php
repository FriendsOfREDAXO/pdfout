<?php
/**
 * PDFOut Test-Seite für sichtbare Signaturen
 */

$addon = rex_addon::get('pdfout');

// Test-Aktionen verarbeiten
$message = '';
$error = '';

if (rex_post('test-action')) {
    $action = rex_post('test-action', 'string');
    
    switch ($action) {
        case 'tcpdf_direct_test':
            try {
                // Composer Autoloader sicherstellen
                if (file_exists($addon->getPath('vendor/autoload.php'))) {
                    require_once $addon->getPath('vendor/autoload.php');
                }
                
                $certPath = $addon->getDataPath('certificates/default.p12');
                
                if (!file_exists($certPath)) {
                    throw new Exception('Test-Zertifikat nicht gefunden: ' . $certPath);
                }
                
                if (!class_exists('TCPDF')) {
                    throw new Exception('TCPDF-Klasse nicht verfügbar');
                }
                
                // TCPDF direkt verwenden
                $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
                
                // PDF Metadaten
                $pdf->SetCreator('REDAXO PDFOut Test');
                $pdf->SetAuthor('REDAXO Demo');
                $pdf->SetTitle('Test PDF mit sichtbarer Signatur');
                
                // Seite hinzufügen
                $pdf->AddPage();
                
                // Inhalt
                $pdf->SetFont('helvetica', '', 12);
                $pdf->Cell(0, 10, 'REDAXO PDFOut - Test der sichtbaren Signatur', 0, 1, 'C');
                $pdf->Ln(10);
                $pdf->Cell(0, 10, 'Erstellt am: ' . date('d.m.Y H:i:s'), 0, 1, 'L');
                $pdf->Ln(10);
                
                $pdf->MultiCell(0, 5, 'Dies ist ein direkter TCPDF-Test zur Überprüfung der sichtbaren Signatur-Funktionalität. Die digitale Signatur sollte als Box unten rechts auf dieser Seite sichtbar sein.');
                
                $pdf->Ln(20);
                $pdf->MultiCell(0, 5, 'Technische Details:
- TCPDF Version: ' . TCPDF_STATIC::getTCPDFVersion() . '
- Test-Zertifikat: default.p12
- Signatur-Position: X=120mm, Y=200mm, Breite=70mm, Höhe=30mm');
                
                // Zertifikat laden
                $certificate = file_get_contents($certPath);
                if ($certificate === false) {
                    throw new Exception('Zertifikat konnte nicht gelesen werden');
                }
                
                // Signatur-Informationen
                $info = [
                    'Name' => 'REDAXO Test Signatur',
                    'Location' => 'REDAXO Backend',
                    'Reason' => 'Test der sichtbaren Signatur-Funktionalität',
                    'ContactInfo' => 'admin@redaxo.demo'
                ];
                
                // Digitale Signatur setzen (mit Test-Passwort)
                $pdf->setSignature($certificate, $certificate, 'redaxo123', '', 2, $info);
                
                // Sichtbare Signatur erstellen
                $x = 120;  // X Position in mm
                $y = 200;  // Y Position in mm  
                $w = 70;   // Breite in mm
                $h = 30;   // Höhe in mm
                
                // Signatur-Box zeichnen (schwarzer Rahmen)
                $pdf->SetDrawColor(0, 0, 0);
                $pdf->SetLineWidth(0.5);
                $pdf->Rect($x, $y, $w, $h, 'D');
                
                // Leichter grauer Hintergrund
                $pdf->SetFillColor(245, 245, 245);
                $pdf->Rect($x + 0.5, $y + 0.5, $w - 1, $h - 1, 'F');
                
                // Signatur-Inhalt
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY($x + 2, $y + 2);
                $pdf->Cell($w - 4, 4, 'Digitally signed by:', 0, 1, 'L');
                
                $pdf->SetXY($x + 2, $y + 6);
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell($w - 4, 4, $info['Name'], 0, 1, 'L');
                
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetXY($x + 2, $y + 10);
                $pdf->Cell($w - 4, 4, 'Date: ' . date('Y.m.d H:i:s O'), 0, 1, 'L');
                
                $pdf->SetXY($x + 2, $y + 14);
                $pdf->Cell($w - 4, 4, 'Location: ' . $info['Location'], 0, 1, 'L');
                
                $pdf->SetXY($x + 2, $y + 18);
                $pdf->Cell($w - 4, 4, 'Reason: ' . $info['Reason'], 0, 1, 'L');
                
                // TCPDF Signatur-Appearance setzen
                $pdf->setSignatureAppearance($x, $y, $w, $h);
                
                // Output-Buffer leeren und PDF ausgeben
                rex_response::cleanOutputBuffers();
                header('Content-Type: application/pdf');
                $pdf->Output('redaxo_test_sichtbare_signatur.pdf', 'D');
                exit; // Wichtig: Script beenden nach PDF-Ausgabe
                
            } catch (Exception $e) {
                $error = 'Fehler beim direkten TCPDF-Test: ' . $e->getMessage();
            }
            break;
            
        case 'pdfout_signature_test':
            try {
                $pdf = new PdfOut();
                $pdf->setName('pdfout_signatur_test')
                    ->setHtml('
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            h1 { color: #333; text-align: center; border-bottom: 2px solid #ccc; padding-bottom: 10px; }
                            .info-box { background: #f0f8ff; padding: 15px; border: 1px solid #ccc; margin: 20px 0; }
                            .signature-area { 
                                margin-top: 120px; 
                                border: 2px dotted #999; 
                                padding: 15px; 
                                text-align: center; 
                                background: #fffacd;
                            }
                        </style>
                        <h1>REDAXO PDFOut - Signatur-Test</h1>
                        
                        <div class="info-box">
                            <h2>Test-Informationen</h2>
                            <p><strong>Erstellt:</strong> ' . date('d.m.Y H:i:s') . '</p>
                            <p><strong>System:</strong> REDAXO PDFOut mit TCPDF-Integration</p>
                            <p><strong>Test-Zweck:</strong> Überprüfung der sichtbaren digitalen Signatur</p>
                            <p><strong>Zertifikat:</strong> Test-Zertifikat (default.p12)</p>
                        </div>
                        
                        <h2>Erwartetes Ergebnis</h2>
                        <p>Dieses PDF sollte eine sichtbare digitale Signatur enthalten:</p>
                        <ul>
                            <li>Rechteckige Box mit schwarzem Rahmen</li>
                            <li>Signatur-Informationen (Name, Datum, Ort, Grund)</li>
                            <li>Position: unten rechts auf der Seite</li>
                            <li>Größe: 70mm × 30mm</li>
                        </ul>
                        
                        <div class="signature-area">
                            <h3>Signatur-Bereich</h3>
                            <p><strong>Die sichtbare digitale Signatur sollte unterhalb dieses Bereichs erscheinen!</strong></p>
                            <p>Position: X=120mm, Y=220mm, Breite=70mm, Höhe=30mm</p>
                            <p><em>Falls keine Signatur sichtbar ist, prüfen Sie die TCPDF-Integration und Zertifikatskonfiguration.</em></p>
                        </div>
                    ')
                    ->enableDigitalSignature(
                        '', // Standard-Zertifikat verwenden
                        'redaxo123', // Test-Passwort
                        'REDAXO PDFOut Test',
                        'REDAXO Backend',
                        'Test der PdfOut-Klasse mit sichtbarer Signatur',
                        'admin@redaxo.demo'
                    )
                    ->setVisibleSignature(120, 220, 70, 30, -1)
                    ->run();
                    
            } catch (Exception $e) {
                $error = 'Fehler beim PdfOut-Test: ' . $e->getMessage();
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

// Status-Informationen
$certPath = $addon->getDataPath('certificates/default.p12');
$tcpdfAvailable = class_exists('TCPDF');

$statusContent = '
<h3>System-Status</h3>
<table class="table table-striped">
    <tr>
        <td><strong>Test-Zertifikat (default.p12)</strong></td>
        <td>' . (file_exists($certPath) ? '<span class="text-success">✓ Vorhanden</span>' : '<span class="text-danger">✗ Nicht gefunden</span>') . '</td>
    </tr>
    <tr>
        <td><strong>Zertifikatspfad</strong></td>
        <td><code>' . rex_escape($certPath) . '</code></td>
    </tr>
    <tr>
        <td><strong>TCPDF verfügbar</strong></td>
        <td>' . ($tcpdfAvailable ? '<span class="text-success">✓ Ja</span>' : '<span class="text-danger">✗ Nein</span>') . '</td>
    </tr>';

if ($tcpdfAvailable) {
    $statusContent .= '
    <tr>
        <td><strong>TCPDF Version</strong></td>
        <td>' . TCPDF_STATIC::getTCPDFVersion() . '</td>
    </tr>';
}

$statusContent .= '
    <tr>
        <td><strong>Test-Passwort</strong></td>
        <td><code>redaxo123</code></td>
    </tr>
</table>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'System-Status');
$fragment->setVar('body', $statusContent, false);
echo $fragment->parse('core/page/section.php');

// Test-Buttons
$testContent = '
<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Direkter TCPDF-Test</h3>
            </div>
            <div class="panel-body">
                <p>Testet die sichtbare Signatur direkt mit TCPDF ohne PdfOut-Klasse.</p>
                <p><strong>Empfohlen:</strong> Starten Sie mit diesem Test!</p>
                <form method="post" style="display:inline;" target="_blank">
                    <input type="hidden" name="test-action" value="tcpdf_direct_test">
                    <button type="submit" class="btn btn-primary">Direkten TCPDF-Test starten</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">PdfOut-Klassen-Test</h3>
            </div>
            <div class="panel-body">
                <p>Testet die sichtbare Signatur über die erweiterte PdfOut-Klasse.</p>
                <p><strong>Hinweis:</strong> Nutzt die TCPDF-Integration der PdfOut-Klasse.</p>
                <form method="post" style="display:inline;" target="_blank">
                    <input type="hidden" name="test-action" value="pdfout_signature_test">
                    <button type="submit" class="btn btn-success">PdfOut-Test starten</button>
                </form>
            </div>
        </div>
    </div>
</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Signatur-Tests');
$fragment->setVar('body', $testContent, false);
echo $fragment->parse('core/page/section.php');

// Hinweise
$hinweiseContent = '
<div class="alert alert-info">
    <h4>Verwendung der Tests</h4>
    <ol>
        <li><strong>Direkter TCPDF-Test:</strong> Überprüft die grundlegende TCPDF-Signatur-Funktionalität</li>
        <li><strong>PdfOut-Test:</strong> Testet die Integration in die erweiterte PdfOut-Klasse</li>
    </ol>
</div>

<div class="alert alert-warning">
    <h4>Erwartetes Ergebnis</h4>
    <p>Bei beiden Tests sollte eine sichtbare Signatur-Box erscheinen mit:</p>
    <ul>
        <li>Schwarzem Rahmen um die Signatur-Box</li>
        <li>Leicht grauem Hintergrund</li>
        <li>Text-Informationen: Name, Datum, Ort, Grund</li>
        <li>Position unten rechts auf der PDF-Seite</li>
    </ul>
</div>

<div class="alert alert-success">
    <h4>Fehlerbehebung</h4>
    <p>Falls keine sichtbare Signatur erscheint:</p>
    <ul>
        <li>Prüfen Sie den System-Status oben</li>
        <li>Stellen Sie sicher, dass das Test-Zertifikat vorhanden ist</li>
        <li>Testen Sie zunächst den direkten TCPDF-Test</li>
        <li>Überprüfen Sie, ob TCPDF korrekt installiert ist</li>
    </ul>
</div>';

echo $hinweiseContent;
?>
