# PdfOut AddOn - API Referenz

## 📚 Übersicht

Die PdfOut-Klasse (`FriendsOfRedaxo\PdfOut\PdfOut`) erweitert Dompdf und bietet eine umfassende API zur PDF-Erstellung in REDAXO mit erweiterten Features wie digitalen Signaturen, Passwortschutz und optimierten Workflows.

## ⚠️ Wichtiger Hinweis: use-Statement

**Für alle Code-Beispiele in dieser Dokumentation gilt:** Am Anfang jeder PHP-Datei muss das use-Statement eingebunden werden:

```php
use FriendsOfRedaxo\PdfOut\PdfOut;
```

Ohne dieses Statement funktionieren die Beispiele nicht!

## 🚀 Schnellstart

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

// Einfachstes Beispiel
$pdf = new PdfOut();
$pdf->setName('mein_pdf')
    ->setHtml('<h1>Hallo Welt!</h1>')
    ->run();
```

## 📋 Klassen-Referenz

### PdfOut-Klasse

Die Hauptklasse für PDF-Erstellung mit erweiterten REDAXO-Features.

#### Konstruktor
```php
$pdf = new PdfOut();
```

## 🔧 Basis-Methoden

### Konfiguration

#### `setName(string $name): self`
Setzt den Namen der PDF-Datei.
```php
$pdf->setName('rechnung_2024');
```

#### `setHtml(string $html, bool $applyOutputFilter = false): self`
Setzt den HTML-Inhalt des PDFs.
```php
$pdf->setHtml('<h1>Mein Content</h1><p>Text...</p>');
$pdf->setHtml($html, true); // Mit REDAXO Output-Filter
```

#### `setPaperSize(string|array $size = 'A4', string $orientation = 'portrait'): self`
Setzt Papierformat und Ausrichtung.
```php
$pdf->setPaperSize('A4', 'portrait');
$pdf->setPaperSize('A3', 'landscape');
$pdf->setPaperSize([595, 842], 'portrait'); // Custom in Points
```

**Verfügbare Formate:** A4, A3, A5, letter, legal, tabloid

#### `setFont(string $font): self`
Setzt die Standard-Schriftart.
```php
$pdf->setFont('Helvetica');
$pdf->setFont('Dejavu Sans'); // Default
```

#### `setDpi(int $dpi): self`
Setzt die DPI-Auflösung.
```php
$pdf->setDpi(300); // Hohe Qualität für Druck
$pdf->setDpi(150); // Standard für Bildschirm
```

#### `setAttachment(bool $attachment): self`
Bestimmt ob PDF als Download oder Vorschau gezeigt wird.
```php
$pdf->setAttachment(true);  // Als Download
$pdf->setAttachment(false); // Inline-Vorschau
```

#### `setRemoteFiles(bool $allow): self`
Erlaubt/verbietet externe Ressourcen (Bilder, CSS).
```php
$pdf->setRemoteFiles(true);  // Erlaubt externe URLs
$pdf->setRemoteFiles(false); // Nur lokale Dateien
```

### Speichern & Ausgabe

#### `setSaveToPath(string $path): self`
Speichert PDF in angegebenen Pfad.
```php
$pdf->setSaveToPath(rex_path::addonCache('pdfout'));
$pdf->setSaveToPath('/pfad/zum/speichern/');
```

#### `setSaveAndSend(bool $saveAndSend): self`
Speichert UND sendet PDF gleichzeitig.
```php
$pdf->setSaveAndSend(true); // Speichern + an Browser senden
```

#### `run(): void`
Führt die PDF-Erstellung aus.
```php
$pdf->run(); // Startet Generierung und Ausgabe
```

## 🎨 Template-System

#### `setBaseTemplate(string $template, string $placeholder = '{{CONTENT}}'): self`
Verwendet Template mit Platzhalter für Inhalt.
```php
$template = '
<!DOCTYPE html>
<html>
<head><title>Mein PDF</title></head>
<body>
    <header>Firmenlogo</header>
    {{CONTENT}}
    <footer>© 2024 Meine Firma</footer>
</body>
</html>';

$pdf->setBaseTemplate($template)
    ->setHtml('<h1>Hauptinhalt</h1>')
    ->run();
```

## 🖼️ REDAXO Integration

#### `addArticle(int $articleId, ?int $ctype = null, bool $applyOutputFilter = true): self`
Fügt REDAXO-Artikel hinzu.
```php
$pdf->addArticle(5);           // Ganzer Artikel
$pdf->addArticle(5, 1);        // Nur ctype=1
$pdf->addArticle(5, null, false); // Ohne Output-Filter
```

#### `mediaUrl(string $type, string $file): string`
Erstellt Media Manager URLs.
```php
$imageUrl = $pdf->mediaUrl('rex_media_large', 'foto.jpg');
$html = '<img src="' . $imageUrl . '" alt="Foto">';
```

#### `viewer(string $file = ''): string`
PDF-Viewer für Frontend.
```php
echo $pdf->viewer('dokument.pdf'); // Zeigt PDF-Viewer
```

## 🔒 Sicherheits-Features

### Passwortschutz

#### `enablePasswordProtection(string $userPassword, string $ownerPassword = '', array $permissions = []): self`
Aktiviert Passwortschutz.
```php
$pdf->enablePasswordProtection(
    'user123',                    // User-Passwort (zum Öffnen)
    'owner456',                   // Owner-Passwort (Vollzugriff)
    ['print', 'copy', 'modify']   // Erlaubte Aktionen
);
```

**Verfügbare Berechtigungen:**
- `print` - Drucken erlaubt
- `modify` - Änderungen erlaubt
- `copy` - Kopieren erlaubt
- `annot-forms` - Anmerkungen/Formulare

### Digitale Signaturen

#### `enableSigning(string $certificatePath, string $password, array $signatureInfo = []): self`
Aktiviert digitale Signierung.
```php
$pdf->enableSigning(
    '/pfad/zu/zertifikat.p12',
    'zertifikat_passwort',
    [
        'Name' => 'Max Mustermann',
        'Location' => 'Berlin, Deutschland',
        'Reason' => 'Dokument signiert',
        'ContactInfo' => 'max@firma.de'
    ]
);
```

#### `setVisibleSignature(array $config): self`
Konfiguriert sichtbare Signatur.
```php
$pdf->setVisibleSignature([
    'enabled' => true,
    'x' => 150,        // X-Position
    'y' => 50,         // Y-Position  
    'width' => 40,     // Breite
    'height' => 20,    // Höhe
    'page' => -1,      // Seite (-1 = letzte)
    'name' => 'Max Mustermann',
    'location' => 'Berlin',
    'reason' => 'Signiert',
    'contact_info' => 'max@firma.de'
]);
```

## 🚀 Workflow-Methoden

### Vereinfachte Workflows (Empfohlen)

#### `createSignedDocument(string $html, string $filename = 'document.pdf', string $saveToPath = '', bool $replaceOriginal = false): void`
Erstellt signiertes PDF mit Standard-Zertifikat.
```php
$pdf = new PdfOut();
$pdf->createSignedDocument($html, 'rechnung.pdf');

// Mit Speicherung
$pdf->createSignedDocument(
    $html, 
    'rechnung.pdf',
    '/speicher/pfad/',  // Speicherpfad
    false               // Original nicht überschreiben
);
```

#### `createSignedWorkflow(string $html, string $certPath, string $certPassword, array $signatureInfo, string $filename, string $cacheDir = '', string $saveToPath = '', bool $replaceOriginal = false): void`
Vollständig konfigurierbarer Signatur-Workflow.
```php
$pdf = new PdfOut();
$pdf->setPaperSize('A4', 'portrait')
    ->setFont('Helvetica')
    ->setDpi(300);

$pdf->createSignedWorkflow(
    $htmlContent,                           // HTML-Inhalt
    '/pfad/zu/certificate.p12',            // Zertifikat
    'zertifikat_passwort',                 // Zertifikat-Passwort
    [                                      // Signatur-Info
        'Name' => 'Max Mustermann',
        'Location' => 'Deutschland',
        'Reason' => 'Rechnung signiert',
        'ContactInfo' => 'max@firma.de'
    ],
    'signierte_rechnung.pdf',              // Dateiname
    '',                                    // Cache (leer = standard)
    '/speicher/ordner/',                   // Speicherpfad  
    false                                  // Original überschreiben
);
```

#### `createPasswordProtectedWorkflow(string $html, string $userPassword, string $ownerPassword, array $permissions, string $filename, string $cacheDir = '', string $saveToPath = '', bool $replaceOriginal = false): void`
Passwortgeschütztes PDF erstellen.
```php
$pdf = new PdfOut();
$pdf->createPasswordProtectedWorkflow(
    $htmlContent,
    'user123',                    // User-Passwort
    'owner456',                   // Owner-Passwort
    ['print', 'copy'],           // Berechtigungen
    'geschuetzt.pdf'             // Dateiname
);
```

### PDF-Anhänge (Beta-Feature)

#### `createDocumentWithAttachments(string $html, array $attachments, string $filename, string $saveToPath = ''): void`
Hauptdokument mit PDF-Anhängen.
```php
$anhaenge = [
    '/pfad/zu/agb.pdf',
    '/pfad/zu/datenschutz.pdf'
];

$pdf = new PdfOut();
$pdf->createDocumentWithAttachments(
    $rechnungHtml,     // Hauptdokument
    $anhaenge,         // PDF-Anhänge
    'rechnung_komplett.pdf'
);
```

## 🛠️ Erweiterte Features

### TCPDF-Integration
Für erweiterte Features wird automatisch TCPDF verwendet:
```php
// Automatischer TCPDF-Modus bei:
$pdf->enableSigning($cert, $pass);           // Signierung
$pdf->enablePasswordProtection($user, $owner); // Passwort
```

### Page Counter
Automatische Seitenzählung:
```php
$html = '<p>Seite {{PAGE_NUM}} von {{PAGE_COUNT}}</p>';
$pdf->setHtml($html)->run(); // Platzhalter werden ersetzt
```

### Cache-System
```php
// Cache-Verzeichnis anpassen
$pdf->setCacheDir('/custom/cache/');

// Cache leeren
PdfOut::clearCache();
```

## 📊 Zertifikats-Management

### Konfigurierte Zertifikate verwenden
```php
// Zertifikat aus AddOn-Konfiguration
$certConfig = rex_addon::get('pdfout')->getConfig('certificates.selected');
if ($certConfig) {
    $pdf->enableSigning(
        $certConfig['path'],
        $certConfig['password']
    );
}
```

## 🔍 Debugging & Logging

### Fehlerbehandlung
```php
try {
    $pdf = new PdfOut();
    $pdf->setHtml($html)->run();
} catch (Exception $e) {
    echo 'PDF-Fehler: ' . $e->getMessage();
}
```

### Logging aktivieren
```php
// In REDAXO Backend: AddOns > PdfOut > Konfiguration
// "PDF-Generierung loggen" aktivieren
```

## 📱 Responsive PDFs

### Media Queries für PDF
```php
$css = '
@media print {
    .no-print { display: none; }
    .page-break { page-break-before: always; }
}
@page {
    margin: 2cm;
    @bottom-right {
        content: "Seite " counter(page);
    }
}
';

$html = '
<style>' . $css . '</style>
<div class="content">PDF-Inhalt</div>
<div class="page-break"></div>
<div class="content">Nächste Seite</div>
';
```

## ⚡ Performance-Tipps

### Optimierung
```php
$pdf = new PdfOut();
$pdf->setDpi(150)              // Niedrigere DPI für kleinere Dateien
    ->setRemoteFiles(false)    // Externe Ressourcen vermeiden
    ->setSaveToPath($cache)    // Zwischenspeichern für Wiederverwendung
    ->run();
```

### Batch-Verarbeitung
```php
// Mehrere PDFs in einem Durchgang
$pdfs = ['rechnung1.html', 'rechnung2.html', 'rechnung3.html'];

foreach ($pdfs as $index => $htmlFile) {
    $pdf = new PdfOut();
    $pdf->setName('batch_pdf_' . $index)
        ->setHtml(file_get_contents($htmlFile))
        ->setSaveToPath('/batch/output/')
        ->run();
}
```

## 🌍 Internationalisierung

### Multi-Language Support
```php
// Deutsche Umlaute und Sonderzeichen
$pdf->setFont('Dejavu Sans'); // Unterstützt Unicode

// RTL-Sprachen (Arabisch, Hebräisch)
// Verwende TCPDF für bessere RTL-Unterstützung
$pdf->enableSigning($cert, $pass); // Aktiviert TCPDF-Modus
```

## 📋 Vollständiges Beispiel

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

// Komplettes Beispiel mit allen Features
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial; margin: 20px; }
        .header { border-bottom: 2px solid #000; padding-bottom: 10px; }
        .footer { position: fixed; bottom: 0; font-size: 10px; color: #666; }
        @page { margin: 2cm; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Rechnung #2024-001</h1>
    </div>
    
    <div class="content">
        <p>Sehr geehrte Damen und Herren,</p>
        <p>hiermit erhalten Sie unsere Rechnung...</p>
        
        <table border="1" cellpadding="5">
            <tr><th>Artikel</th><th>Menge</th><th>Preis</th></tr>
            <tr><td>Beratung</td><td>5h</td><td>500,00 €</td></tr>
        </table>
    </div>
    
    <div class="footer">
        Seite {{PAGE_NUM}} von {{PAGE_COUNT}} | Erstellt: ' . date('d.m.Y') . '
    </div>
</body>
</html>';

try {
    $pdf = new PdfOut();
    
    // Basis-Konfiguration
    $pdf->setName('rechnung_2024_001')
        ->setPaperSize('A4', 'portrait')
        ->setFont('Dejavu Sans')
        ->setDpi(300)
        ->setAttachment(true);
    
    // Erweiterte Features
    $pdf->enablePasswordProtection(
        'user123', 
        'owner456', 
        ['print', 'copy']
    );
    
    $pdf->enableSigning(
        '/pfad/zu/firmen_zertifikat.p12',
        'zertifikat_passwort',
        [
            'Name' => 'Meine Firma GmbH',
            'Location' => 'Deutschland',
            'Reason' => 'Rechnung digital signiert',
            'ContactInfo' => 'info@meinefirma.de'
        ]
    );
    
    // PDF erstellen und speichern
    $pdf->setHtml($html)
        ->setSaveToPath(rex_path::addonData('pdfout', 'rechnungen/'))
        ->setSaveAndSend(true)
        ->run();
        
} catch (Exception $e) {
    rex_logger::factory()->error('PDF-Erstellung fehlgeschlagen', 
        ['error' => $e->getMessage()]);
    echo 'Fehler beim Erstellen der Rechnung: ' . $e->getMessage();
}
```

## 🔗 Weitere Ressourcen

- [REDAXO PdfOut Demo-Seite](../../demo/) - Interaktive Beispiele
- [Best Practices](BEST_PRACTICES.md) - Empfohlene Workflows
- [dompdf Documentation](https://github.com/dompdf/dompdf) - Basis-Library
- [TCPDF Documentation](https://tcpdf.org/docs/) - Erweiterte Features