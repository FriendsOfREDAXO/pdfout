# PdfOut AddOn - Best Practices

## 🎯 Übersicht

Diese Anleitung enthält bewährte Praktiken, Tipps und Empfehlungen für die optimale Nutzung des PdfOut AddOns in REDAXO-Projekten.

## ⚠️ Wichtiger Hinweis: use-Statement

**Für alle Code-Beispiele in dieser Dokumentation gilt:** Am Anfang jeder PHP-Datei muss das use-Statement eingebunden werden:

```php
use FriendsOfRedaxo\PdfOut\PdfOut;
```

Ohne dieses Statement funktionieren die Beispiele nicht! Dies gilt für alle nachfolgenden Code-Beispiele.

## 🚀 Grundlegende Best Practices

### 1. **Workflow-Wahl**

#### ✅ Empfohlen: Neue Workflow-Methoden
```php
// 👍 Für signierte PDFs - nur 2 Zeilen!
$pdf = new PdfOut();
$pdf->createSignedDocument($html, 'dokument.pdf');

// 👍 Für passwortgeschützte PDFs
$pdf = new PdfOut();
$pdf->createPasswordProtectedWorkflow($html, 'user123', 'owner456', ['print'], 'geschuetzt.pdf');
```

#### ❌ Vermeiden: Manuelle TCPDF-Konfiguration
```php
// 👎 Kompliziert und fehleranfällig
$tcpdf = new TCPDF();
$tcpdf->SetProtection(...);
$tcpdf->setSignature(...);
// ... 80+ Zeilen Code
```

### 2. **HTML/CSS-Optimierung für PDFs**

#### ✅ PDF-freundliches CSS
```css
/* Basis-Styling für PDFs */
@page {
    margin: 2cm;
    size: A4 portrait;
}

body {
    font-family: 'Dejavu Sans', Arial, sans-serif;
    font-size: 12px;
    line-height: 1.4;
    color: #000;
}

/* Seitenumbrüche kontrollieren */
.page-break {
    page-break-before: always;
}

.no-break {
    page-break-inside: avoid;
}

/* Druckspezifische Stile */
@media print {
    .no-print { display: none; }
    a { color: #000; text-decoration: none; }
}

/* Tabellen optimieren */
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}

th, td {
    border: 1px solid #ccc;
    padding: 8px;
    text-align: left;
}
```

#### ❌ Problematische CSS-Eigenschaften
```css
/* 👎 Nicht verwenden in PDFs */
position: fixed;     /* Außer für Header/Footer */
float: left;         /* Kann Layout brechen */
transform: rotate(); /* Wird nicht unterstützt */
box-shadow: ...;     /* Schlechte Performance */
border-radius: ...;  /* Kann unscharf werden */
```

### 3. **Template-System nutzen**

#### ✅ Wiederverwendbare Templates
```php
// Template einmal definieren
$baseTemplate = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        ' . file_get_contents(rex_path::assets('css/pdf-styles.css')) . '
    </style>
</head>
<body>
    <header class="pdf-header">
        <img src="' . rex_url::assets('images/logo.png') . '" alt="Logo">
        <h1>{{DOCUMENT_TITLE}}</h1>
    </header>
    
    <main class="pdf-content">
        {{CONTENT}}
    </main>
    
    <footer class="pdf-footer">
        Seite {{PAGE_NUM}} von {{PAGE_COUNT}} | Erstellt: ' . date('d.m.Y H:i') . '
    </footer>
</body>
</html>';

// Für verschiedene Dokumente verwenden
$pdf = new PdfOut();
$pdf->setBaseTemplate($baseTemplate)
    ->setHtml('<h2>Rechnungsinhalt</h2><p>...</p>')
    ->run();
```

## 🔒 Sicherheits-Best Practices

### 1. **Zertifikats-Management**

#### ✅ Sichere Zertifikat-Verwaltung
```php
// Zertifikate außerhalb des Web-Roots speichern
$certPath = rex_path::addonData('pdfout', 'certificates/firmen_cert.p12');

// Passwörter in REDAXO-Config speichern (verschlüsselt)
$certPassword = rex_addon::get('pdfout')->getConfig('cert_password');

// Zertifikat-Validierung vor Verwendung
if (!file_exists($certPath) || !is_readable($certPath)) {
    throw new Exception('Zertifikat nicht verfügbar');
}
```

#### ❌ Unsichere Praktiken
```php
// 👎 Passwörter im Code
$password = 'geheim123'; // Hart codiert - unsicher!

// 👎 Zertifikate im Web-Root
$certPath = rex_path::frontend('certificates/cert.p12'); // Öffentlich zugänglich!
```

### 2. **Passwort-Strategien**

#### ✅ Starke Passwort-Richtlinien
```php
// Verschiedene Passwörter für verschiedene Zwecke
function generateSecurePassword($type = 'user') {
    switch ($type) {
        case 'user':    // Zum Öffnen des PDFs
            return bin2hex(random_bytes(4)); // 8 Zeichen
        case 'owner':   // Vollzugriff auf PDF
            return bin2hex(random_bytes(8)); // 16 Zeichen
        default:
            return bin2hex(random_bytes(6)); // 12 Zeichen
    }
}

$pdf->enablePasswordProtection(
    generateSecurePassword('user'),
    generateSecurePassword('owner'),
    ['print'] // Minimale Berechtigungen
);
```

## 📊 Performance-Optimierung

### 1. **DPI und Dateigröße**

#### ✅ DPI nach Verwendungszweck wählen
```php
// Bildschirm-Anzeige (kleinere Dateien)
$pdf->setDpi(150);

// Standard-Druck
$pdf->setDpi(200); 

// Hochqualitätsdruck (größere Dateien)
$pdf->setDpi(300);

// Archivierung (Balance zwischen Qualität und Größe)
$pdf->setDpi(180);
```

### 2. **Caching-Strategien**

#### ✅ Intelligentes Caching
```php
// Cache-Key basierend auf Content generieren
$cacheKey = md5($htmlContent . $userId . date('Y-m-d'));
$cacheFile = rex_path::addonCache('pdfout', 'generated/' . $cacheKey . '.pdf');

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
    // Cache ist gültig - verwende gespeicherte Datei
    header('Content-Type: application/pdf');
    readfile($cacheFile);
    exit;
}

// Neu generieren und cachen
$pdf = new PdfOut();
$pdf->setHtml($htmlContent)
    ->setSaveToPath(dirname($cacheFile) . '/')
    ->setName(basename($cacheFile, '.pdf'))
    ->setSaveAndSend(true)
    ->run();
```

### 3. **Ressourcen-Management**

#### ✅ Bilder optimieren
```php
// Media Manager für optimierte Bildgrößen nutzen
$optimizedImage = rex_media_manager::getUrl('pdf_optimized', 'grosses_bild.jpg');

$html = '<img src="' . $optimizedImage . '" style="max-width: 100%; height: auto;">';

// CSS für Bildoptimierung
$css = '
img {
    max-width: 100%;
    height: auto;
    image-rendering: optimizeQuality;
}';
```

## 📋 Anwendungsfall-spezifische Tipps

### 1. **Rechnungen & Geschäftsdokumente**

#### ✅ Professionelle Rechnungen
```php
function createInvoicePdf($invoiceData) {
    $html = generateInvoiceHtml($invoiceData);
    
    $pdf = new PdfOut();
    $pdf->setPaperSize('A4', 'portrait')
        ->setFont('Dejavu Sans')
        ->setDpi(200)
        ->setName('rechnung_' . $invoiceData['number']);
    
    // Signierung für Rechtsgültigkeit
    $pdf->createSignedWorkflow(
        $html,
        getCompanyCertificate(),
        getCompanyCertificatePassword(),
        [
            'Name' => 'Meine Firma GmbH',
            'Location' => 'Deutschland',
            'Reason' => 'Rechnung digital signiert',
            'ContactInfo' => 'buchhaltung@firma.de'
        ],
        'rechnung_' . $invoiceData['number'] . '.pdf',
        '',
        rex_path::addonData('pdfout', 'rechnungen/'),
        false
    );
}
```

### 2. **Zertifikate & Urkunden**

#### ✅ Hochwertige Zertifikate
```php
function createCertificatePdf($recipientData) {
    $pdf = new PdfOut();
    $pdf->setPaperSize('A4', 'landscape') // Querformat für Zertifikate
        ->setFont('Dejavu Sans')
        ->setDpi(300)                     // Hohe Auflösung
        ->setAttachment(false);           // Inline-Anzeige
    
    $html = generateCertificateHtml($recipientData);
    
    // Sichtbare Signatur für Authentizität
    $pdf->enableSigning($cert, $password)
        ->setVisibleSignature([
            'enabled' => true,
            'x' => 200,
            'y' => 50,
            'width' => 50,
            'height' => 25,
            'page' => 1,
            'name' => 'Zertifizierungsstelle',
            'reason' => 'Zertifikat ausgestellt'
        ]);
    
    $pdf->setHtml($html)->run();
}
```

### 3. **Berichte & Dokumentation**

#### ✅ Strukturierte Berichte
```php
function createReportPdf($reportData) {
    // Template mit Inhaltsverzeichnis
    $template = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            .toc { page-break-after: always; }
            .chapter { page-break-before: always; }
            @page { @bottom-right { content: "Seite " counter(page); } }
        </style>
    </head>
    <body>
        <div class="toc">
            <h1>Inhaltsverzeichnis</h1>
            <ul>
                <li>1. Zusammenfassung</li>
                <li>2. Detailanalyse</li>
                <li>3. Empfehlungen</li>
            </ul>
        </div>
        {{CONTENT}}
    </body>
    </html>';
    
    $pdf = new PdfOut();
    $pdf->setBaseTemplate($template)
        ->setPaperSize('A4', 'portrait')
        ->setDpi(150)
        ->setHtml(generateReportContent($reportData))
        ->setSaveToPath(rex_path::addonData('pdfout', 'reports/'))
        ->run();
}
```

## 🛠️ Debugging & Fehlerbehandlung

### 1. **Systematisches Debugging**

#### ✅ Debug-Workflow
```php
function debugPdfGeneration($html) {
    // 1. HTML-Validierung
    if (empty(trim($html))) {
        throw new Exception('HTML-Inhalt ist leer');
    }
    
    // 2. CSS-Validierung (vereinfacht)
    if (strpos($html, 'position: fixed') !== false) {
        rex_logger::factory()->warning('PDF enthält position:fixed - kann Probleme verursachen');
    }
    
    // 3. Ressourcen-Check
    preg_match_all('/src=["\']([^"\']+)["\']/', $html, $matches);
    foreach ($matches[1] as $src) {
        if (!file_exists(rex_path::frontend($src))) {
            rex_logger::factory()->warning('Bild nicht gefunden: ' . $src);
        }
    }
    
    // 4. PDF-Generierung mit Fehlerbehandlung
    try {
        $pdf = new PdfOut();
        $pdf->setHtml($html)
            ->setName('debug_pdf_' . date('Y-m-d_H-i-s'))
            ->setSaveToPath(rex_path::addonCache('pdfout', 'debug/'))
            ->run();
    } catch (Exception $e) {
        rex_logger::factory()->error('PDF-Debug fehlgeschlagen', [
            'error' => $e->getMessage(),
            'html_length' => strlen($html),
            'memory_usage' => memory_get_usage(true)
        ]);
        throw $e;
    }
}
```

### 2. **Häufige Probleme & Lösungen**

#### ❌ Problem: "Undefined array key" Fehler
```php
// 👎 Fehlerhaft
$certInfo = rex_addon::get('pdfout')->getConfig('certificates');
$name = $certInfo['selected']['name']; // Kann undefined sein

// ✅ Sicher
$certInfo = rex_addon::get('pdfout')->getConfig('certificates', []);
$name = $certInfo['selected']['name'] ?? 'Standard-Zertifikat';
```

#### ❌ Problem: Bilder werden nicht angezeigt
```php
// 👎 Relative URLs
$html = '<img src="../media/bild.jpg">';

// ✅ Absolute URLs oder Media Manager
$mediaUrl = rex_media_manager::getUrl('rex_media_medium', 'bild.jpg');
$html = '<img src="' . $mediaUrl . '">';
```

#### ❌ Problem: Langsame PDF-Generierung
```php
// 👎 Hohe DPI für alle PDFs
$pdf->setDpi(300); // Immer langsam

// ✅ Angemessene DPI nach Zweck
$dpi = ($purpose === 'print') ? 300 : 150;
$pdf->setDpi($dpi);
```

## 🔄 Workflow-Empfehlungen

### 1. **Entwicklungsphase**

```php
// Debug-Modus während Entwicklung
if (rex::isDebugMode()) {
    $pdf->setDpi(100)  // Schnellere Generierung
        ->setSaveToPath(rex_path::addonCache('pdfout', 'dev/'))
        ->setSaveAndSend(false); // Nur speichern, nicht senden
}
```

### 2. **Produktionsphase**

```php
// Produktion: Optimiert und gesichert
$pdf = new PdfOut();
$pdf->setDpi(200)
    ->enableSigning($cert, $password)
    ->setSaveToPath(rex_path::addonData('pdfout', 'archive/'))
    ->setSaveAndSend(true);
```

### 3. **Monitoring & Maintenance**

```php
// PDF-Statistiken sammeln
function logPdfStats($name, $size, $generationTime) {
    rex_logger::factory()->info('PDF generiert', [
        'name' => $name,
        'size_kb' => round($size / 1024, 2),
        'generation_time_ms' => round($generationTime * 1000, 2),
        'memory_peak_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2)
    ]);
}

// Cache-Cleanup implementieren
function cleanupPdfCache($maxAge = 86400) { // 24 Stunden
    $cacheDir = rex_path::addonCache('pdfout');
    $files = glob($cacheDir . '*.pdf');
    
    foreach ($files as $file) {
        if (time() - filemtime($file) > $maxAge) {
            unlink($file);
        }
    }
}
```

## 📱 Mobile & Responsive Considerations

### 1. **Mobile-freundliche PDFs**

```php
// Kompakte PDFs für mobile Anzeige
$pdf = new PdfOut();
$pdf->setPaperSize('A4', 'portrait')
    ->setDpi(150)  // Balance zwischen Qualität und Dateigröße
    ->setFont('Dejavu Sans'); // Gut lesbar auf kleinen Bildschirmen

$mobileCss = '
body { font-size: 11px; line-height: 1.3; }
table { font-size: 9px; }
.mobile-hidden { display: none; }
';
```

## 🌍 Internationalisierung

### 1. **Multi-Language Support**

```php
function createMultiLanguagePdf($content, $lang = 'de') {
    $fonts = [
        'de' => 'Dejavu Sans',
        'en' => 'Helvetica', 
        'ar' => 'Dejavu Sans', // Für RTL-Sprachen
        'zh' => 'Dejavu Sans'  // Für asiatische Zeichen
    ];
    
    $pdf = new PdfOut();
    $pdf->setFont($fonts[$lang] ?? 'Dejavu Sans')
        ->setHtml($content)
        ->run();
}
```

## 🎯 Zusammenfassung der wichtigsten Empfehlungen

1. **Nutze die neuen Workflow-Methoden** - `createSignedDocument()` und `createPasswordProtectedWorkflow()`
2. **Optimiere CSS für PDFs** - Vermeide problematische Properties, nutze `@page` Rules
3. **Wähle angemessene DPI** - 150 für Bildschirm, 200-300 für Druck
4. **Implementiere Caching** - Für häufig generierte PDFs
5. **Sichere Zertifikat-Verwaltung** - Außerhalb Web-Root, verschlüsselte Passwörter
6. **Strukturierte Fehlerbehandlung** - Logging und systematisches Debugging
7. **Performance-Monitoring** - Überwache Generierungszeiten und Speicherverbrauch
8. **Template-System nutzen** - Für konsistente Layouts
9. **Mobile-Optimierung** - Kompakte, gut lesbare PDFs
10. **Regelmäßige Wartung** - Cache-Cleanup und Archivierung

Diese Best Practices helfen dabei, professionelle, sichere und performante PDF-Lösungen mit dem PdfOut AddOn zu entwickeln.