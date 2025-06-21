<?php
/**
 * PDFOut Demo-Seite
 */

$addon = rex_addon::get('pdfout');

// Demo-Aktionen verarbeiten
$message = '';
$error = '';

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
                $pdf = new PdfOut();
                $pdf->setName('demo_signed')
                    ->setHtml('<h1>Signiertes PDF Demo</h1><p>Dies ist ein digital signiertes PDF.</p><p>Signatur-Informationen finden Sie in den PDF-Eigenschaften.</p><p style="margin-top: 50mm;">Die sichtbare Signatur sollte rechts unten auf dieser Seite erscheinen.</p>')
                    ->enableDigitalSignature(
                        '', // Verwendet Standard-Zertifikat
                        'redaxo123', // Korrektes Passwort für Test-Zertifikat
                        'REDAXO Demo',
                        'Demo-Umgebung',
                        'Demo-Signierung',
                        'demo@redaxo.org'
                    )
                    ->setVisibleSignature(120, 200, 70, 30, -1)
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
                $pdf = new PdfOut();
                $pdf->setName('demo_full_featured')
                    ->setHtml('<h1>Vollständig ausgestattetes PDF Demo</h1><p>Dieses PDF kombiniert alle Features:</p><ul><li>Digitale Signierung</li><li>Passwortschutz</li><li>Sichtbare Signatur</li></ul><p><strong>Passwort:</strong> demo123</p><p style="margin-top: 30mm;">Die sichtbare Signatur ist rechts unten positioniert.</p>')
                    ->enableDigitalSignature(
                        '',
                        'redaxo123',
                        'REDAXO Demo',
                        'Demo-Umgebung',
                        'Full-Feature Demo',
                        'demo@redaxo.org'
                    )
                    ->setVisibleSignature(120, 220, 70, 30, -1)
                    ->enablePasswordProtection('demo123', 'owner456', ['print'])
                    ->run();
            } catch (Exception $e) {
                $error = 'Fehler beim Erstellen des vollausgestatteten PDFs: ' . $e->getMessage();
            }
            break;
            
        case 'ticket_pdf':
            try {
                $eventDate = date('d.m.Y', strtotime('+2 weeks'));
                $eventTime = '20:00';
                $ticketNumber = 'TCK-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                
                // QR-Code Inhalt (in echten Anwendungen würde hier eine Verifikations-URL stehen)
                $qrContent = 'https://redaxo.org/verify/' . $ticketNumber;
                $qrCodePlaceholder = '<div style="width: 80px; height: 80px; border: 2px solid #000; display: inline-block; text-align: center; line-height: 76px; font-size: 10px; float: right; margin-left: 20px;">QR-Code<br>' . $ticketNumber . '</div>';
                
                $html = '<style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f8f9fa; }
    .ticket-container { background: white; border: 3px solid #2c3e50; border-radius: 15px; padding: 0; overflow: hidden; max-width: 600px; margin: 0 auto; }
    .ticket-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
    .event-title { font-size: 28px; font-weight: bold; margin: 0 0 10px 0; }
    .event-subtitle { font-size: 16px; opacity: 0.9; margin: 0; }
    .ticket-body { padding: 30px; }
    .event-details { display: table; width: 100%; margin: 20px 0; }
    .detail-row { display: table-row; }
    .detail-label { display: table-cell; font-weight: bold; padding: 8px 20px 8px 0; color: #2c3e50; width: 120px; }
    .detail-value { display: table-cell; padding: 8px 0; color: #495057; }
    .ticket-info { background: #e9ecef; padding: 20px; margin: 20px 0; border-radius: 8px; }
    .ticket-footer { background: #2c3e50; color: white; padding: 20px; text-align: center; }
    .seat-info { font-size: 24px; font-weight: bold; color: #e74c3c; text-align: center; margin: 20px 0; }
    .terms { font-size: 12px; color: #6c757d; margin-top: 20px; line-height: 1.4; }
</style>

<div class="ticket-container">
    <div class="ticket-header">
        <h1 class="event-title">REDAXO Conference 2024</h1>
        <p class="event-subtitle">The Future of Content Management</p>
    </div>
    
    <div class="ticket-body">
        <div style="overflow: hidden;">
            ' . $qrCodePlaceholder . '
            <div style="margin-right: 100px;">
                <div class="event-details">
                    <div class="detail-row">
                        <div class="detail-label">Ticket-Nr.:</div>
                        <div class="detail-value"><strong>' . $ticketNumber . '</strong></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Datum:</div>
                        <div class="detail-value">' . $eventDate . '</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Uhrzeit:</div>
                        <div class="detail-value">' . $eventTime . ' Uhr</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Veranstaltungsort:</div>
                        <div class="detail-value">REDAXO Convention Center<br>Musterstraße 123, 12345 Musterstadt</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Ticketinhaber:</div>
                        <div class="detail-value">Max Mustermann</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="seat-info">
            PLATZ: BLOCK A - REIHE 5 - SITZ 12
        </div>
        
        <div class="ticket-info">
            <h3 style="margin-top: 0; color: #2c3e50;">Programm-Highlights</h3>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li><strong>09:00 - 10:30:</strong> Keynote: "REDAXO 6.0 - Die Zukunft beginnt jetzt"</li>
                <li><strong>11:00 - 12:30:</strong> Workshop: "AddOn-Entwicklung für Profis"</li>
                <li><strong>14:00 - 15:30:</strong> Panel: "Performance-Optimierung in großen REDAXO-Projekten"</li>
                <li><strong>16:00 - 17:30:</strong> Best Practices: "REDAXO in der Enterprise-Umgebung"</li>
            </ul>
        </div>
        
        <div style="background: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;">
            <h4 style="margin-top: 0; color: #856404;">Wichtige Hinweise</h4>
            <ul style="margin: 0; padding-left: 20px; color: #856404;">
                <li>Bitte bringen Sie einen gültigen Lichtbildausweis mit</li>
                <li>Einlass ab 08:30 Uhr</li>
                <li>Dieses Ticket ist nicht übertragbar</li>
                <li>Bei Verlust wenden Sie sich an den Veranstalter</li>
            </ul>
        </div>
        
        <div class="terms">
            <p><strong>Allgemeine Geschäftsbedingungen:</strong> Mit dem Kauf dieses Tickets akzeptieren Sie unsere AGB. 
            Das Ticket berechtigt zum einmaligen Besuch der Veranstaltung. Foto- und Videoaufnahmen sind gestattet. 
            Der Veranstalter haftet nicht für Diebstahl oder Verlust persönlicher Gegenstände.</p>
            
            <p><strong>Kontakt:</strong> REDAXO Events GmbH • info@redaxo-conference.org • +49 123 456789</p>
        </div>
    </div>
    
    <div class="ticket-footer">
        <p style="margin: 0; font-size: 14px;">Wir freuen uns auf Sie! • #REDAXOConf2024</p>
    </div>
</div>';
                
                $pdf = new PdfOut();
                $pdf->setName('demo_event_ticket')
                    ->setHtml($html)
                    ->enableDigitalSignature(
                        '',
                        'redaxo123',
                        'REDAXO Events',
                        'Event Management',
                        'Ticket-Validierung',
                        'events@redaxo.org'
                    )
                    ->setVisibleSignature(20, 260, 60, 20, -1)
                    ->run();
            } catch (Exception $e) {
                $error = 'Fehler beim Erstellen des Event-Tickets: ' . $e->getMessage();
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

// Demo-Buttons
$content = '
<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Einfaches PDF</h3>
            </div>
            <div class="panel-body">
                <p>Erstellt ein einfaches PDF ohne erweiterte Features.</p>
                <form method="post" style="display:inline;" target="_blank">
                    <input type="hidden" name="demo-action" value="simple_pdf">
                    <button type="submit" class="btn btn-primary">PDF erstellen</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="panel panel-success">
            <div class="panel-heading">
                <h3 class="panel-title">Digital signiertes PDF</h3>
            </div>
            <div class="panel-body">
                <p>Erstellt ein digital signiertes PDF mit sichtbarer Signatur.</p>
                <form method="post" style="display:inline;" target="_blank">
                    <input type="hidden" name="demo-action" value="signed_pdf">
                    <button type="submit" class="btn btn-success">PDF erstellen</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-warning">
            <div class="panel-heading">
                <h3 class="panel-title">Passwortgeschütztes PDF</h3>
            </div>
            <div class="panel-body">
                <p>Erstellt ein passwortgeschütztes PDF.<br><strong>Passwort:</strong> demo123</p>
                <form method="post" style="display:inline;" target="_blank">
                    <input type="hidden" name="demo-action" value="password_pdf">
                    <button type="submit" class="btn btn-warning">PDF erstellen</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="panel panel-danger">
            <div class="panel-heading">
                <h3 class="panel-title">Vollausgestattetes PDF</h3>
            </div>
            <div class="panel-body">
                <p>Kombiniert alle Features: Signierung + Passwortschutz.<br><strong>Passwort:</strong> demo123</p>
                <form method="post" style="display:inline;" target="_blank">
                    <input type="hidden" name="demo-action" value="full_featured_pdf">
                    <button type="submit" class="btn btn-danger">PDF erstellen</button>
                </form>
            </div>
        </div>
    </div>
</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'PDF-Demos');
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// Code-Beispiele
$examples = '<h3>Code-Beispiele</h3>'
    . '<h4>1. Einfaches PDF</h4>'
    . '<pre><code>' . htmlspecialchars("\$pdf = new PdfOut();\n\$pdf->setName('demo_simple')\n    ->setHtml('<h1>Einfaches PDF Demo</h1><p>Dies ist ein einfaches PDF.</p>')\n    ->run();") . '</code></pre>'
    . '<h4>2. Digital signiertes PDF</h4>'
    . '<pre><code>' . htmlspecialchars("\$pdf = new PdfOut();\n\$pdf->setName('demo_signed')\n    ->setHtml('<h1>Signiertes PDF Demo</h1><p>Dies ist ein digital signiertes PDF.</p>')\n    ->enableDigitalSignature(\n        '',                // Standard-Zertifikat verwenden\n        'redaxo123',       // Zertifikatspasswort\n        'REDAXO Demo',     // Name\n        'Demo-Umgebung',   // Ort\n        'Demo-Signierung', // Grund\n        'demo@redaxo.org'  // Kontakt\n    )\n    ->setVisibleSignature(120, 200, 70, 30, -1) // X, Y, Breite, Höhe, Seite\n    ->run();") . '</code></pre>'
    . '<h4>3. Passwortgeschütztes PDF</h4>'
    . '<pre><code>' . htmlspecialchars("\$pdf = new PdfOut();\n\$pdf->setName('demo_password')\n    ->setHtml('<h1>Passwortgeschütztes PDF</h1><p>Passwort: demo123</p>')\n    ->enablePasswordProtection(\n        'demo123',    // Benutzer-Passwort\n        'owner456',   // Besitzer-Passwort\n        ['print', 'copy'] // Erlaubte Aktionen\n    )\n    ->run();") . '</code></pre>'
    . '<h4>4. Vollausgestattetes PDF</h4>'
    . '<pre><code>' . htmlspecialchars("\$pdf = new PdfOut();\n\$pdf->setName('demo_full_featured')\n    ->setHtml('<h1>Vollausgestattetes PDF</h1><p>Alle Features kombiniert.</p>')\n    ->enableDigitalSignature('', 'redaxo123', 'REDAXO Demo', 'Demo-Umgebung', 'Full-Feature Demo', 'demo@redaxo.org')\n    ->setVisibleSignature(120, 220, 70, 30, -1)\n    ->enablePasswordProtection('demo123', 'owner456', ['print'])\n    ->run();") . '</code></pre>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Code-Beispiele');
$fragment->setVar('body', $examples, false);
$fragment->setVar('collapse', true);
$fragment->setVar('collapsed', true);
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
