# PdfOut für REDAXO!

PdfOut stellt die "HTML to PDF"-Converter [dompdf](https://github.com/dompdf/dompdf) und [pdf.js](https://github.com/mozilla/pdf.js) in REDAXO zur Verfügung und wurde um leistungsstarke **TCPDF-Features** erweitert.

Es ermöglicht die einfache Umwandlung von HTML-Inhalten (auch REDAXO-Artikel) in PDF-Dateien, deren Anzeige im Browser, Speicherung oder direkten Download sowie fortgeschrittene Funktionen wie digitale Signierung, Passwortschutz und nachträgliche Bearbeitung.

## Key Features

### Standard-Features (basierend auf DomPDF/pdf.js)
- 🌈 Wandelt HTML in PDFs um
- 🎨 Passt Ausrichtung, Schriftart und mehr an
- 🖼 Integriert Bilder direkt aus dem REDAXO Media Manager
- 💾 Speichert PDFs ab oder streamt sie direkt an den Browser
- 🔢 Fügt automatisch Seitenzahlen ein
- 🔍 Integrierter Viewer zur Vorschau

### Erweiterte TCPDF-Features
- ✍️ **Digitale Signierung:** Signieren von PDFs mit .p12-Zertifikaten, sichtbare und unsichtbare Signaturen, nachträgliche Signierung.
- 🔒 **Passwortschutz & Sicherheit:** Benutzer- und Besitzer-Passwörter mit granularen Berechtigungen (Drucken, Kopieren, etc.).
- 📋 **ZUGFeRD/Factur-X Support:** Erstellen Sie hybride Rechnungs-PDFs mit eingebettetem XML nach EN 16931 Standard für die automatische Verarbeitung in Buchhaltungssoftware.
- ⚙️ **Flexible Konfiguration:** Umfangreiche Optionen über das Backend-Interface.
- 🎯 **Automatische Erkennung:** Intelligente Auswahl zwischen DomPDF und TCPDF je nach benötigten Features.

### ZUGFeRD/Factur-X Highlights
- 🏦 **Vollständige EN 16931 Kompatibilität** - Standard-konforme elektronische Rechnungen
- 🤖 **Automatische XML-Generierung** - Strukturierte Rechnungsdaten aus PHP-Arrays
- 📄 **Hybride PDFs** - Menschen- und maschinenlesbare Rechnungen in einer Datei
- 💼 **Professionelle Demo** - Realistische Muster-Rechnung mit allen Firmendetails
- 🔄 **Multiple Profile** - Unterstützung für MINIMUM, BASIC, COMFORT und EXTENDED Profile

## Installation

Die Installation erfolgt über den REDAXO-Installer. Alternativ gibt es die aktuellste Beta-Version auf [GitHub](https://github.com/FriendsOfREDAXO/pdfout).

## Erste Schritte (Quick Start)

Das Erstellen eines einfachen PDFs ist kinderleicht:

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

$pdf = new PdfOut();
$pdf->setName('mein_erstes_pdf') // Dateiname für den Download
    ->setHtml('<h1>Hallo REDAXO-Welt!</h1><p>Mein erstes PDF mit PdfOut. Wie cool ist das denn?</p>') // HTML-Inhalt
    ->run(); // PDF erstellen und an den Browser senden
```

## Anwendungsbeispiele

### Artikel-Inhalte als PDF

Wandeln Sie den Inhalt eines REDAXO-Artikels (ggf. mit spezifischem CType) in ein PDF um:

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

$pdf = new PdfOut();
$pdf->setName('artikel_als_pdf')
    ->addArticle(1, null, true) // Artikel-ID 1, alle CTypes, Output Filter anwenden
    ->run();
```

### Erweiterte Konfiguration eines PDFs

Passen Sie Papierformat, Schriftart, DPI und weitere Optionen an:

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

$pdf = new PdfOut();
$pdf->setName('konfiguriertes_pdf')
    ->setPaperSize('A4', 'portrait')      // Setzt Papiergröße und Ausrichtung
    ->setFont('Helvetica')                // Setzt die Standardschriftart
    ->setDpi(300)                         // Setzt die DPI für bessere Qualität
    ->setAttachment(true)                 // PDF als Download anbieten (statt Vorschau)
    ->setRemoteFiles(true)                // Erlaubt das Laden externer Ressourcen (Bilder, CSS)
    ->setHtml($content, true)             // HTML mit Output Filter
    ->run();
```
*Hinweis:* `setHtml` mit `true` als zweitem Parameter wendet den REDAXO OUTPUT_FILTER an.

### Schicke Vorlagen für PDFs

Definieren Sie ein HTML-Template mit Platzhaltern für Kopf-, Fußbereich und Inhalt:

```php
$meineVorlage = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif;}
        .kopf { background-color: #ff9900; padding: 10px; }
        .inhalt { margin: 20px; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; }
        /* Seitenzahlen mit CSS - DOMPDF spezifisch */
        .pagenum:before {
            content: counter(page);
        }
        /* Alternativ: DOMPDF_PAGE_COUNT_PLACEHOLDER im HTML nutzen */
    </style>
</head>
<body>
    <div class="kopf">Mein supercooler PDF-Kopf</div>
    <div class="inhalt">{{CONTENT}}</div>
    <div class="footer">Seite <span class="pagenum"></span> von: DOMPDF_PAGE_COUNT_PLACEHOLDER</div>
</body>
</html>';

use FriendsOfRedaxo\PdfOut\PdfOut;
$pdf = new PdfOut();
$pdf->setName('stylishes_pdf')
    ->setBaseTemplate($meineVorlage, '{{CONTENT}}') // Template und Platzhalter definieren
    ->setHtml('<h1>Wow!</h1><p>Dieses PDF sieht ja mal richtig schick aus!</p>') // Inhalt einfügen
    ->run();
```

### PDFs speichern und verschicken

Speichern Sie das erzeugte PDF auf dem Server oder senden Sie es direkt an den Browser:

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

$pdf = new PdfOut();
$pdf->setName('mein_meisterwerk')
    ->setHtml('<h1>PDF-Kunst</h1><p>Dieses PDF wird gespeichert und gesendet.</p>')
    ->setSaveToPath(rex_path::addonCache('pdfout', 'mein_meisterwerk.pdf')) // Pfad zum Speichern
    ->setSaveAndSend(true) // Speichert UND sendet in einem Rutsch
    ->run();

// Nur speichern, nicht senden
$pdfOnlySave = new PdfOut();
$pdfOnlySave->setName('nur_gespeichert')
    ->setHtml('<h1>Wird nur gespeichert</h1>')
    ->setSaveToPath(rex_path::addonCache('pdfout', 'nur_gespeichert.pdf'))
    ->run(); // run() erstellt und speichert, sendet aber nichts, wenn setSaveToPath gesetzt und setSaveAndSend false ist
```

## Fortgeschrittene TCPDF-Features

Diese Funktionen nutzen die erweiterten Fähigkeiten von TCPDF und erfordern ggf. eine spezifische Konfiguration im Addon-Backend.

### Digitale Signierung

Signieren Sie Ihre PDFs digital mit einem .p12-Zertifikat.

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

$pdf = new PdfOut();
$pdf->setName('signiertes_dokument')
    ->setHtml('<h1>Signiertes PDF</h1><p>Dieses Dokument ist digital signiert.</p>')
    ->enableDigitalSignature(
        '/path/to/certificate.p12',  // Pfad zum .p12 Zertifikat (oder leer lassen, wenn im Backend konfiguriert)
        'certificate_password',      // Passwort des Zertifikats
        'Max Mustermann',            // Name des Signierers
        'Musterstadt',               // Ort der Signierung
        'Dokument-Freigabe',         // Grund der Signierung
        'max@example.com'            // Kontaktinformationen (optional)
    )
    ->setVisibleSignature(150, 250, 40, 20, -1)  // Position und Größe der sichtbaren Signatur (x, y, width, height, page: -1 = letzte Seite)
    ->run();
```

#### ⚠️ Sicherheitshinweise für produktive Umgebungen

**Hardcoded Passwörter vermeiden:**
```php
// ❌ NICHT in produktiven Systemen verwenden
$pdf->enableDigitalSignature('', 'hardcoded_password', ...);

// ✅ Empfohlene sichere Methoden:

// REDAXO Properties verwenden (empfohlen für REDAXO)
$certPassword = rex_property::get('cert_password');
$pdf->enableDigitalSignature('', $certPassword, ...);

// REDAXO Config mit Verschlüsselung
$encryptedPassword = rex_config::get('pdfout', 'cert_password');
$password = my_decrypt($encryptedPassword);
$pdf->enableDigitalSignature('', $password, ...);

// Umgebungsvariablen (alternative Lösung)
$certPassword = $_ENV['CERT_PASSWORD'];
$pdf->enableDigitalSignature('', $certPassword, ...);
```

**Best Practices für Zertifikate:**
- **Produktive Zertifikate:** Nur von vertrauenswürdigen CAs verwenden
- **Dateiberechtigungen:** 600 (nur Webserver lesbar) setzen
- **Pfad-Sicherheit:** Zertifikate außerhalb des Web-Root speichern
- **Ablaufmonitoring:** Rechtzeitige Erneuerung vor Ablauf
- **Backup:** Sichere Aufbewahrung von Zertifikaten und Passwörtern
        'Dokument-Freigabe',         // Grund der Signierung
        'max@example.com'            // Kontaktinformationen (optional)
    )
    ->setVisibleSignature(150, 250, 40, 20, -1)  // Position und Größe der sichtbaren Signatur (x, y, width, height, page: -1 = letzte Seite)
    ->run();
```
**Nachträgliche Signierung:** Signieren Sie eine bereits vorhandene PDF-Datei.

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

$pdf = new PdfOut();
$success = $pdf->signExistingPdf(
    '/path/to/input.pdf',           // Pfad zur Quelldatei
    '/path/to/output_signed.pdf',   // Pfad zur Ausgabedatei
    '/path/to/certificate.p12',     // Pfad zum Zertifikat (oder leer lassen)
    'certificate_password',         // Passwort
    [                               // Optionen für die Signatur
        'Name' => 'Max Mustermann',
        'Location' => 'Musterstadt',
        'Reason' => 'Nachträgliche Signierung',
        'visible' => true,         // Sichtbare Signatur?
        'x' => 180,                // Position x
        'y' => 60,                 // Position y
        'width' => 15,             // Breite
        'height' => 15,            // Höhe
        'page' => 1                // Seite (Standard ist die letzte Seite)
    ]
);

if ($success) {
    echo "PDF erfolgreich signiert und gespeichert.";
} else {
    echo "Fehler beim Signieren des PDFs.";
}
```

### Passwortschutz und Sicherheit

Schützen Sie Ihre PDFs mit Passwörtern und definieren Sie Benutzerberechtigungen.

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

$pdf = new PdfOut();
$pdf->setName('geschuetztes_dokument')
    ->setHtml('<h1>Geschütztes PDF</h1><p>Dieses PDF ist passwortgeschützt.</p>')
    ->enablePasswordProtection(
        'benutzer_passwort',     // Benutzer-Passwort (zum Öffnen des Dokuments)
        'besitzer_passwort',     // Besitzer-Passwort (zum Ändern von Berechtigungen, optional)
        ['print', 'copy']        // Erlaubte Aktionen (Array von 'print', 'copy', 'modify', 'annot', 'fill', 'extract', 'assemble', 'print-high')
    )
    ->run();
```

### ZUGFeRD/Factur-X Hybride Rechnungen

ZUGFeRD (Zentraler User Guide des Forums elektronische Rechnung Deutschland) ist ein hybrides Rechnungsformat, das sowohl menschen- als auch maschinenlesbar ist. Es ermöglicht die automatische Verarbeitung in Buchhaltungssoftware und entspricht dem EU-Standard EN 16931.

#### Schnellstart: ZUGFeRD-Demo ausprobieren

Die einfachste Methode, ZUGFeRD kennenzulernen, ist die integrierte Demo:

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

// Vollständige Demo-Rechnung mit realistischen Daten
$demoData = PdfOut::getExampleZugferdData();
$pdf = new PdfOut();
$pdf->setName('zugferd_demo_rechnung')
    ->setHtml('<h1>Demo Rechnung</h1><p>Diese Rechnung wurde automatisch generiert.</p>')
    ->enableZugferd($demoData, 'BASIC', 'factur-x.xml')
    ->run();
```

> **Tipp:** Besuchen Sie die Demo-Seite im AddOn-Backend (`AddOns → PDFOut → Demo`) für eine vollständige, professionell gestaltete ZUGFeRD-Musterrechnung.

#### Eigene ZUGFeRD-Rechnung erstellen

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

// Schritt 1: Rechnungsdaten strukturieren
$invoiceData = [
    'invoice_number' => 'RE-' . date('Y') . '-001',
    'issue_date' => date('Y-m-d'),
    'currency' => 'EUR',
    'default_tax_rate' => 19.0,
    
    // Verkäufer-Informationen (Ihr Unternehmen)
    'seller' => [
        'name' => 'Ihre Firma GmbH',
        'id' => 'VERKAUFER-001',
        'tax_number' => 'DE123456789',
        'vat_id' => 'DE987654321',
        'company_register' => 'HRB 12345 AG München',
        'address' => [
            'line1' => 'Firmenstraße 123',
            'line2' => 'Gebäude A, 2. OG',
            'postcode' => '80331',
            'city' => 'München',
            'country' => 'DE'
        ],
        'contact' => [
            'phone' => '+49 89 1234567',
            'email' => 'rechnung@ihre-firma.de',
            'web' => 'https://www.ihre-firma.de'
        ],
        'bank' => [
            'name' => 'Ihre Hausbank',
            'iban' => 'DE89 1234 5678 9012 3456 78',
            'bic' => 'GENODED1XXX'
        ],
        'management' => 'Geschäftsführer: Max Mustermann'
    ],
    
    // Käufer-Informationen (Kunde)
    'buyer' => [
        'name' => 'Kunde AG',
        'id' => 'KUNDE-2024-042',
        'address' => [
            'line1' => 'Kundenstraße 456',
            'line2' => 'Abteilung: Einkauf',
            'postcode' => '10115',
            'city' => 'Berlin',
            'country' => 'DE'
        ]
    ],
    
    // Rechnungspositionen
    'line_items' => [
        [
            'name' => 'REDAXO CMS Enterprise Lizenz',
            'description' => 'Erweiterte CMS-Lizenz für kommerzielle Nutzung inkl. Support',
            'seller_assigned_id' => 'REDAXO-ENT-001',
            'quantity' => 1.0,
            'unit' => 'STK',
            'unit_price' => 1190.00,  // Bruttopreis
            'net_unit_price' => 1000.00,  // Nettopreis
            'tax_rate' => 19.0
        ],
        [
            'name' => 'Setup & Konfiguration',
            'description' => 'Professionelle Einrichtung und Anpassung des Systems',
            'seller_assigned_id' => 'SERVICE-001',
            'quantity' => 8.0,
            'unit' => 'STD',
            'unit_price' => 119.00,
            'net_unit_price' => 100.00,
            'tax_rate' => 19.0
        ]
    ],
    
    // Rechnungssummen
    'totals' => [
        'net_amount' => 1800.00,
        'tax_amount' => 342.00,
        'gross_amount' => 2142.00,
        'tax_breakdown' => [
            [
                'tax_rate' => 19.0,
                'taxable_amount' => 1800.00,
                'tax_amount' => 342.00
            ]
        ]
    ],
    
    // Zahlungsbedingungen
    'payment_terms' => [
        'description' => 'Zahlbar innerhalb 14 Tagen ohne Abzug. Bei Zahlung nach 14 Tagen werden 2% Verzugszinsen berechnet.',
        'due_date' => date('Y-m-d', strtotime('+14 days'))
    ],
    
    // Projekt-Details (optional)
    'project_details' => [
        'project_name' => 'REDAXO CMS Implementation',
        'project_number' => 'PROJ-2024-042',
        'delivery_time' => '2-3 Wochen ab Auftragserteilung',
        'warranty' => '12 Monate Gewährleistung'
    ]
];

// Schritt 2: Professionelles HTML-Layout erstellen
$rechnungsHtml = '
<div style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto;">
    <h1 style="color: #d63333;">Rechnung ' . htmlspecialchars($invoiceData['invoice_number']) . '</h1>
    
    <div style="margin: 20px 0;">
        <strong>Rechnungsdatum:</strong> ' . date('d.m.Y', strtotime($invoiceData['issue_date'])) . '<br>
        <strong>Fällig am:</strong> ' . date('d.m.Y', strtotime($invoiceData['payment_terms']['due_date'])) . '
    </div>
    
    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 10px; border: 1px solid #ddd;">Beschreibung</th>
                <th style="padding: 10px; border: 1px solid #ddd;">Menge</th>
                <th style="padding: 10px; border: 1px solid #ddd;">Einzelpreis</th>
                <th style="padding: 10px; border: 1px solid #ddd;">Gesamt</th>
            </tr>
        </thead>
        <tbody>';

foreach ($invoiceData['line_items'] as $item) {
    $total = $item['net_unit_price'] * $item['quantity'];
    $rechnungsHtml .= '
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;">
                    <strong>' . htmlspecialchars($item['name']) . '</strong><br>
                    <small>' . htmlspecialchars($item['description']) . '</small>
                </td>
                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . $item['quantity'] . ' ' . $item['unit'] . '</td>
                <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . number_format($item['net_unit_price'], 2, ',', '.') . ' €</td>
                <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . number_format($total, 2, ',', '.') . ' €</td>
            </tr>';
}

$rechnungsHtml .= '
        </tbody>
    </table>
    
    <div style="text-align: right; margin-top: 20px;">
        <table style="margin-left: auto; border-collapse: collapse;">
            <tr>
                <td style="padding: 5px 10px;">Nettobetrag:</td>
                <td style="padding: 5px 10px; text-align: right; font-weight: bold;">' . number_format($invoiceData['totals']['net_amount'], 2, ',', '.') . ' €</td>
            </tr>
            <tr>
                <td style="padding: 5px 10px;">zzgl. 19% MwSt.:</td>
                <td style="padding: 5px 10px; text-align: right;">' . number_format($invoiceData['totals']['tax_amount'], 2, ',', '.') . ' €</td>
            </tr>
            <tr style="border-top: 2px solid #333;">
                <td style="padding: 10px 10px; font-weight: bold; font-size: 16px;">Rechnungsbetrag:</td>
                <td style="padding: 10px 10px; text-align: right; font-weight: bold; font-size: 16px;">' . number_format($invoiceData['totals']['gross_amount'], 2, ',', '.') . ' €</td>
            </tr>
        </table>
    </div>
    
    <div style="margin-top: 40px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
        <h4>Zahlungsinformationen</h4>
        <p>' . htmlspecialchars($invoiceData['payment_terms']['description']) . '</p>
        <p><strong>Bankverbindung:</strong><br>
        ' . htmlspecialchars($invoiceData['seller']['bank']['name']) . '<br>
        IBAN: ' . htmlspecialchars($invoiceData['seller']['bank']['iban']) . '<br>
        BIC: ' . htmlspecialchars($invoiceData['seller']['bank']['bic']) . '</p>
    </div>
</div>';

// Schritt 3: ZUGFeRD-PDF erstellen
$pdf = new PdfOut();
$pdf->setName('zugferd_rechnung_' . $invoiceData['invoice_number'])
    ->setHtml($rechnungsHtml)
    ->enableZugferd($invoiceData, 'BASIC', 'factur-x.xml')
    ->run();
```

#### Verfügbare ZUGFeRD-Profile

```php
// MINIMUM - Grundlegende Pflichtfelder
$pdf->enableZugferd($invoiceData, 'MINIMUM');

// BASIC - Empfohlen für die meisten Anwendungen (Standard)
$pdf->enableZugferd($invoiceData, 'BASIC');

// COMFORT - Erweiterte Funktionen mit zusätzlichen Feldern
$pdf->enableZugferd($invoiceData, 'COMFORT');

// EXTENDED - Vollständige Datenübertragung (komplexeste Version)
$pdf->enableZugferd($invoiceData, 'EXTENDED');
```

#### ZUGFeRD mit anderen Features kombinieren

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

// ZUGFeRD + Digitale Signierung + Passwortschutz
$pdf = new PdfOut();
$pdf->setName('vollausgestattete_zugferd_rechnung')
    ->setHtml($rechnungsHtml)
    ->enableZugferd($invoiceData, 'BASIC', 'factur-x.xml')
    ->enableDigitalSignature('', 'zertifikat_passwort', 'Ihre Firma GmbH', 'München', 'ZUGFeRD-Rechnung')
    ->enablePasswordProtection('kunden_passwort', 'admin_passwort', ['print', 'copy'])
    ->run();
```

#### ZUGFeRD in REDAXO-Projekten integrieren

**Beispiel 1: ZUGFeRD-Rechnung aus REDAXO-Artikeldaten**

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

// Rechnungsdaten aus REDAXO-Datenbank laden
$sql = rex_sql::factory();
$sql->setQuery('SELECT * FROM rex_invoices WHERE id = ?', [42]);

if ($sql->getRows() > 0) {
    $invoiceData = [
        'invoice_number' => $sql->getValue('invoice_number'),
        'issue_date' => $sql->getValue('issue_date'),
        'currency' => 'EUR',
        'default_tax_rate' => 19.0,
        // ... weitere Daten aus der Datenbank
    ];
    
    // Artikel-Inhalt als HTML-Template verwenden
    $article = rex_article::get($sql->getValue('template_article_id'));
    $articleContent = new rex_article_content($article->getId());
    $rechnungsHtml = $articleContent->getArticle();
    
    // ZUGFeRD-PDF erstellen
    $pdf = new PdfOut();
    $pdf->setName('rechnung_' . $invoiceData['invoice_number'])
        ->setHtml($rechnungsHtml, true) // Mit OUTPUT_FILTER
        ->enableZugferd($invoiceData, 'BASIC')
        ->run();
}
```

**Beispiel 2: Automatische ZUGFeRD-Generierung per EP (Extension Point)**

```php
// In der boot.php Ihres Projekts oder AddOns
rex_extension::register('PACKAGES_INCLUDED', function() {
    // Extension Point für automatische ZUGFeRD-Generierung
    rex_extension::register('INVOICE_CREATED', function($ep) {
        $invoiceId = $ep->getParam('invoice_id');
        $invoiceData = $ep->getParam('invoice_data');
        
        if (!empty($invoiceData) && !empty($invoiceId)) {
            try {
                $pdf = new \FriendsOfRedaxo\PdfOut\PdfOut();
                $pdf->setName('auto_zugferd_' . $invoiceId)
                    ->setHtml($ep->getParam('invoice_html'))
                    ->enableZugferd($invoiceData, 'BASIC')
                    ->setSaveToPath(rex_path::addonData('invoices', 'zugferd_' . $invoiceId . '.pdf'))
                    ->setSaveAndSend(false) // Nur speichern
                    ->run();
                    
                // Optional: E-Mail mit ZUGFeRD-PDF versenden
                // rex_mailer::factory()->sendInvoice($invoiceData, $pdf_path);
                
            } catch (Exception $e) {
                rex_logger::factory()->error('ZUGFeRD Auto-Generation failed: ' . $e->getMessage());
            }
        }
    });
});
```

**Beispiel 3: ZUGFeRD-Download aus dem Frontend**

```php
// In einem Frontend-Controller oder Modul
if (rex_get('action') === 'download_zugferd' && rex_get('invoice_id', 'int') > 0) {
    $invoiceId = rex_get('invoice_id', 'int');
    
    // Berechtigungsprüfung
    if (!user_can_access_invoice($invoiceId)) {
        rex_response::sendRedirect(rex_url::frontend());
    }
    
    // Rechnungsdaten laden
    $invoiceData = load_invoice_data($invoiceId);
    $invoiceHtml = generate_invoice_html($invoiceData);
    
    // ZUGFeRD-PDF generieren und ausliefern
    $pdf = new \FriendsOfRedaxo\PdfOut\PdfOut();
    $pdf->setName('rechnung_' . $invoiceData['invoice_number'])
        ->setHtml($invoiceHtml)
        ->enableZugferd($invoiceData, 'BASIC', 'factur-x.xml')
        ->setAttachment(true) // Als Download
        ->run();
}
```

**Beispiel 4: ZUGFeRD-Konfiguration über Backend-Formulare**

```php
// AddOn-spezifische ZUGFeRD-Konfiguration
rex_extension::register('OUTPUT_FILTER', function($ep) {
    if (strpos($ep->getSubject(), '###ZUGFERD_INVOICE###') !== false) {
        $invoiceData = rex_session('zugferd_invoice_data');
        
        if (!empty($invoiceData)) {
            // ZUGFeRD-HTML generieren
            $zugferdHtml = '
            <div class="zugferd-notice">
                <p><strong>Diese Rechnung ist ZUGFeRD/Factur-X konform</strong></p>
                <p>Das PDF enthält strukturierte Daten für die automatische Buchung.</p>
            </div>';
            
            return str_replace('###ZUGFERD_INVOICE###', $zugferdHtml, $ep->getSubject());
        }
    }
    
    return $ep->getSubject();
});
```

#### ZUGFeRD-Validierung und Debugging

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

// Beispieldaten für Tests verwenden
$testData = PdfOut::getExampleZugferdData();

// Debug-Modus aktivieren für detaillierte Fehlermeldungen
rex_addon::get('pdfout')->setConfig('enable_debug_mode', true);

try {
    $pdf = new PdfOut();
    $pdf->setName('zugferd_test')
        ->setHtml('<h1>ZUGFeRD Test</h1>')
        ->enableZugferd($testData, 'BASIC')
        ->run();
        
    echo "ZUGFeRD-PDF erfolgreich erstellt!";
    
} catch (Exception $e) {
    // Detaillierte Fehleranalyse
    error_log('ZUGFeRD Error: ' . $e->getMessage());
    echo "Fehler beim Erstellen der ZUGFeRD-Rechnung: " . $e->getMessage();
}
```

#### Technische Details und Anforderungen

**Systemanforderungen für ZUGFeRD:**
- PHP 7.4+ (empfohlen: 8.0+)
- Composer-Abhängigkeit: `horstoeko/zugferd`
- TCPDF-Unterstützung für PDF-Attachment
- Ausreichend Speicher für XML-Generierung

**Unterstützte ZUGFeRD-Standards:**
- EN 16931 (EU-Standard für elektronische Rechnungen)
- ZUGFeRD 2.0 (Deutschland)
- Factur-X 1.0 (Frankreich)
- XRechnung (Deutschland, öffentlicher Sektor)

**XML-Struktur:**
Das generierte XML entspricht dem UN/CEFACT Cross Industry Invoice Standard und wird automatisch als Attachment in das PDF eingebettet.

#### Vorteile von ZUGFeRD

- ✅ **Automatische Verarbeitung:** Rechnungen können direkt in Buchhaltungssoftware importiert werden
- ✅ **Fehlerreduzierung:** Keine manuelle Eingabe notwendig
- ✅ **EU-konform:** Entspricht dem Standard EN 16931
- ✅ **Hybrid:** Sowohl PDF (für Menschen) als auch XML (für Maschinen) in einer Datei
- ✅ **Workflow-Beschleunigung:** Deutlich schnellere Rechnungsverarbeitung

### Test-Funktionen

Im Addon-Backend stehen Funktionen zum Testen und Debugging bereit:
- **Automatische Zertifikatsgenerierung:** Erstellen Sie Test-Zertifikate direkt in der Konfiguration.
- **Demo-Seiten:** Umfangreiche Beispiele und Tests zur Demonstration der verschiedenen Features.
- **Debugging:** Detaillierte Fehleranalyse und Systemstatus-Informationen.

## Quick Reference

### Wichtigste Methoden im Überblick

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

$pdf = new PdfOut();

// Basis-Konfiguration
$pdf->setName('dateiname')                    // PDF-Dateiname
    ->setHtml($html, $outputFilter)           // HTML-Inhalt (mit/ohne OUTPUT_FILTER)
    ->addArticle($id, $ctype, $outputFilter) // REDAXO-Artikel hinzufügen
    ->setBaseTemplate($template, $placeholder); // HTML-Template mit Platzhalter

// PDF-Eigenschaften
$pdf->setPaperSize('A4', 'portrait')         // Papierformat und Ausrichtung
    ->setFont('Arial')                       // Schriftart
    ->setDpi(300)                           // Auflösung
    ->setAttachment(true)                   // Als Download (true) oder Vorschau (false)
    ->setRemoteFiles(true);                 // Externe Ressourcen erlauben

// Speichern
$pdf->setSaveToPath($path)                  // Pfad zum Speichern
    ->setSaveAndSend(true);                 // Speichern UND senden

// Erweiterte Features
$pdf->enableDigitalSignature($cert, $pass, $name, $location, $reason, $contact)
    ->setVisibleSignature($x, $y, $width, $height, $page)
    ->enablePasswordProtection($userPass, $ownerPass, $permissions)
    ->enableZugferd($invoiceData, $profile, $xmlFilename);

// PDF erstellen
$pdf->run();
```

### ZUGFeRD Quick Start

```php
// 1. Demo ausprobieren
$demoData = PdfOut::getExampleZugferdData();
$pdf = new PdfOut();
$pdf->setName('zugferd_demo')
    ->setHtml('<h1>Demo Rechnung</h1>')
    ->enableZugferd($demoData, 'BASIC')
    ->run();

// 2. Eigene Daten verwenden
$invoiceData = [
    'invoice_number' => 'RE-2024-001',
    'issue_date' => '2024-06-22',
    'currency' => 'EUR',
    'seller' => [...],  // Verkäufer-Daten
    'buyer' => [...],   // Käufer-Daten
    'line_items' => [...], // Rechnungspositionen
    'totals' => [...],  // Summen
    'payment_terms' => [...] // Zahlungsbedingungen
];

$pdf = new PdfOut();
$pdf->setName('meine_zugferd_rechnung')
    ->setHtml($rechnungsHtml)
    ->enableZugferd($invoiceData, 'BASIC', 'factur-x.xml')
    ->run();
```

### Häufige Anwendungsfälle

| Anwendungsfall | Code-Beispiel |
|---|---|
| **Einfaches PDF** | `$pdf->setHtml($html)->run();` |
| **Artikel als PDF** | `$pdf->addArticle(1)->run();` |
| **Mit Template** | `$pdf->setBaseTemplate($template)->setHtml($content)->run();` |
| **Signiertes PDF** | `$pdf->setHtml($html)->enableDigitalSignature()->run();` |
| **Geschütztes PDF** | `$pdf->setHtml($html)->enablePasswordProtection('pass123')->run();` |
| **ZUGFeRD-Rechnung** | `$pdf->setHtml($html)->enableZugferd($data, 'BASIC')->run();` |
| **Alles kombiniert** | `$pdf->setHtml($html)->enableZugferd($data)->enableDigitalSignature()->enablePasswordProtection('pass')->run();` |

### Backend-Navigation

- **Konfiguration:** `AddOns → PDFOut → Konfiguration`
- **Demo & Tests:** `AddOns → PDFOut → Demo`  
- **System-Info:** `AddOns → PDFOut → Info`

> **Tipp:** Nutzen Sie die Demo-Seite für erste Tests und als Vorlage für eigene Implementierungen!

## Detailed API Reference

Eine Auswahl der wichtigsten Methoden der `PdfOut`-Klasse:

- `setName(string $name)`: Setzt den Dateinamen für den Download oder die Speicherung.
- `setHtml(string $html, bool $applyOutputFilter = false)`: Setzt den HTML-Inhalt. Optionaler Parameter, um den REDAXO OUTPUT_FILTER anzuwenden.
- `run()`: Erzeugt das PDF basierend auf der aktuellen Konfiguration. Sendet an den Browser oder speichert, je nach Einstellungen.
- `setPaperSize(string|array $size = 'A4', string $orientation = 'portrait')`: Setzt das Papierformat ('A4', 'letter', etc. oder [width, height] in Punkten) und die Ausrichtung ('portrait', 'landscape').
- `setBaseTemplate(string $template, string $placeholder = '{{CONTENT}}')`: Setzt ein Grund-HTML-Template, in das der Inhalt (`setHtml` oder `addArticle`) eingefügt wird.
- `addArticle(int $articleId, ?int $ctype = null, bool $applyOutputFilter = true)`: Fügt den gerenderten Inhalt eines REDAXO-Artikels hinzu.
- `setAttachment(bool $attachment = true)`: Steuert, ob das PDF als Download ('true') oder zur direkten Anzeige im Browser ('false') gesendet wird.
- `setRemoteFiles(bool $enabled = true)`: Erlaubt das Laden externer Ressourcen wie Bilder oder CSS-Dateien über URLs.
- `setDpi(int $dpi)`: Setzt die DPI für die Bilddarstellung (relevant für DomPDF).
- `setFont(string $font)`: Setzt die Standardschriftart.
- `setSaveToPath(string $path)`: Legt den vollständigen Pfad fest, unter dem das PDF gespeichert werden soll.
- `setSaveAndSend(bool $saveAndSend = true)`: Steuert, ob das PDF nach dem Speichern auch an den Browser gesendet werden soll (relevant, wenn `setSaveToPath` gesetzt ist).
- `mediaUrl(string $type, string $file)`: Statische Methode. Generiert eine korrekte, absolute URL für ein Bild aus dem Media Manager, die in PDFs funktioniert.
- `viewer(string $file = '')`: Statische Methode. Erzeugt eine URL zum integrierten PDF-Viewer für eine gegebene PDF-Datei (relativer oder absoluter Pfad).

*Hinweis:* Methoden für digitale Signatur und Passwortschutz sind unter "Fortgeschrittene TCPDF-Features" mit Beispielen dokumentiert.

## Tipps für die Optimierung

### Performance-Optimierung
- CSS inline im HTML definieren oder in `<style>`-Tags statt externer `<link>`-Dateien.
- Auf große CSS-Frameworks verzichten.
- Bilder in optimierter Größe und Auflösung verwenden.
- OPcache für bessere PHP-Performance aktivieren.

### Bilder und Media Manager
- Für lokale Bilder im HTML am besten absolute Pfade verwenden.
- Media Manager URLs sollten immer als absolute URLs generiert werden (nutzen Sie `PdfOut::mediaUrl`).
- `setRemoteFiles(true)` ist notwendig, wenn Bilder oder CSS über HTTP(S)-URLs geladen werden.

### CSS und Schriftarten
- Numerische `font-weight`-Angaben können manchmal Probleme bereiten; `normal`, `bold` sind sicherer.
- Google Fonts oder andere externe Schriftarten sollten lokal heruntergeladen und eingebunden werden.
- Bei Schriftproblemen kann es helfen, `PdfOut` mitzuteilen, dass Font-Subsetting deaktiviert werden soll (kann je nach Konfiguration im Backend oder ggf. über eine Methode erfolgen, ist in der Original-API nicht explizit gelistet, daher nicht im Codebeispiel).

### Kopf- und Fußzeilen
- Können oft am besten direkt im HTML-Template mit festen Positionierungen (`position: fixed;`) realisiert werden.
- Seitenzahlen können über CSS-Counter (`.pagenum:before { content: counter(page); }`) oder durch Platzhalter wie `DOMPDF_PAGE_COUNT_PLACEHOLDER` im Template eingefügt werden.

## Systemvoraussetzungen

- PHP mit folgenden Erweiterungen:
    - DOM
    - MBString
    - `php-font-lib`
    - `php-svg-lib`
    - `gd-lib` oder ImageMagick (für Bildverarbeitung)

Empfohlen:
- OPcache für bessere Performance
- GD oder IMagick/GMagick für optimierte Bildverarbeitung

## Support & Credits

### Wo finde ich Hilfe?

- [REDAXO-Channel auf Slack](https://friendsofredaxo.slack.com/messages/redaxo/) (Suche nach dem Addon-Namen)
- [GitHub Issues](https://github.com/FriendsOfREDAXO/pdfout/issues) (für Bug Reports und Feature Requests)
- [REDAXO Forum](https://forum.redaxo.org/)

### Team

**Friends Of REDAXO**  
http://www.redaxo.org  
https://github.com/FriendsOfREDAXO

**Projekt-Lead**  
[Thomas Skerbis](https://github.com/skerbis)

### Danke an

- [dompdf](http://dompdf.github.io)
- [FriendsOfREDAXO](https://github.com/FriendsOfREDAXO)
- [First release: Oliver Kreischer](https://github.com/olien)

### Lizenz

Dieses Addon ist unter der [MIT-Lizenz](https://github.com/FriendsOfREDAXO/pdfout/blob/master/LICENSE.md) lizenziert.

