# REDAXO PdfOut - Datei-Speicherung und Original-Ersetzung

## 🚀 Neue Features in allen Workflow-Methoden

Alle Workflow-Methoden unterstützen jetzt:
- **Datei-Speicherung** statt nur direkter Ausgabe
- **Kontrollierte Original-Ersetzung** mit `replaceOriginal` Parameter
- **Flexible Rückgabewerte** (Dateipfad bei Speicherung, `true` bei direkter Ausgabe)

## 📁 Workflow-Methoden mit Datei-Speicherung

### ✅ Passwortgeschützte PDFs
```php
// Vereinfacht - als Datei speichern
$savedPath = $pdf->createPasswordProtectedDocument(
    $html, 
    'meinpasswort', 
    'dokument.pdf',
    '/speicherort/',     // Speicherverzeichnis
    true                 // Original überschreiben = ja
);

// Erweitert - mit allen Optionen
$savedPath = $pdf->createPasswordProtectedWorkflow(
    $html,
    'userpasswort',      // User-Passwort  
    'ownerpasswort',     // Owner-Passwort
    ['print', 'copy'],   // Berechtigungen
    'dokument.pdf',      // Dateiname
    '',                  // Standard Cache
    '/speicherort/',     // Speicherverzeichnis
    false                // Original NICHT überschreiben
);
```

### ✅ Signierte PDFs
```php
// Vereinfacht - als Datei speichern
$savedPath = $pdf->createSignedDocument(
    $html, 
    'dokument.pdf',
    '/speicherort/',     // Speicherverzeichnis
    true                 // Original überschreiben = ja
);

// Erweitert - mit allen Optionen
$savedPath = $pdf->createSignedWorkflow(
    $html,
    '/pfad/zum/zertifikat.p12',  // Zertifikat
    'zertifikatspasswort',       // Passwort
    ['Name' => 'Max Mustermann'], // Signatur-Info
    'dokument.pdf',              // Dateiname
    '',                          // Standard Cache
    '/speicherort/',             // Speicherverzeichnis
    false                        // Original NICHT überschreiben
);
```

### ✅ PDF-Zusammenführung
```php
// HTML-zu-PDF Merge - als Datei speichern
$savedPath = $pdf->mergeHtmlToPdf(
    $htmlContents,       // Array mit HTML-Inhalten
    'zusammen.pdf',      // Dateiname
    true,                // Trennseiten
    '/speicherort/',     // Speicherverzeichnis
    true                 // Original überschreiben = ja
);

// PDF-Dateien zusammenführen - als Datei speichern
$savedPath = $pdf->mergePdfs(
    $pdfPaths,           // Array mit PDF-Pfaden
    'merged.pdf',        // Dateiname
    false,               // Keine Trennseiten
    '',                  // Standard Cache
    '/speicherort/',     // Speicherverzeichnis
    false                // Original NICHT überschreiben
);
```

### ✅ PDF-Anhänge: Hauptdokument + bestehende PDFs
```php
// Rechnung mit AGB und Datenschutz anhängen  
$pdf = new PdfOut();
$pdf->setPaperSize('A4', 'portrait')      // Settings für Hauptdokument
    ->setFont('Arial')                    // Schriftart
    ->setDpi(300);                        // Hohe Auflösung

// Direkte Ausgabe
$pdf->createDocumentWithAttachments(
    $rechnungHtml,                        // Hauptdokument (HTML)
    ['/pfad/zu/agb.pdf', '/pfad/zu/datenschutz.pdf'], // Anhänge
    'rechnung_komplett.pdf'               // Dateiname
);

// Als Datei speichern
$savedPath = $pdf->createDocumentWithAttachments(
    $rechnungHtml,                        // Hauptdokument (HTML)
    ['/pfad/zu/agb.pdf', '/pfad/zu/datenschutz.pdf'], // Anhänge
    'rechnung_komplett.pdf',              // Dateiname
    '/speicherort/',                      // Speicherverzeichnis
    true                                  // Original überschreiben = ja
);
```

## 🎯 Praktisches Beispiel: Workflow mit Settings-Übernahme und Dateispeicherung

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

// PDF-Instanz mit benutzerdefinierten Settings
$pdf = new PdfOut();
$pdf->setPaperSize('A4', 'landscape')     // Querformat
    ->setFont('Times')                    // Elegante Schriftart  
    ->setDpi(300)                         // Druckqualität
    ->setAttachment(false);               // Inline-Anzeige

// 1. Passwortgeschütztes PDF erstellen und speichern
$protectedPath = $pdf->createPasswordProtectedDocument(
    $rechnungHtml,
    'kunde2024',
    'rechnung_001.pdf',
    '/var/rechnungen/',
    true                    // Überschreibe falls vorhanden
);
echo "Geschützte Rechnung: " . $protectedPath;

// 2. Signiertes Zertifikat erstellen (gleiche Settings!)
$signedPath = $pdf->createSignedDocument(
    $zertifikatHtml,
    'zertifikat_max.pdf',
    '/var/zertifikate/',
    false                   // Fehler falls bereits vorhanden
);
echo "Signiertes Zertifikat: " . $signedPath;

// 3. Zusammenführung (gleiche Settings!)
$mergedPath = $pdf->mergeHtmlToPdf(
    [$projektHtml, $budgetHtml, $zeitplanHtml],
    'projektdokumentation.pdf',
    true,                   // Trennseiten
    '/var/projekte/',
    true                    // Überschreibe falls vorhanden
);
echo "Projekt-Doku: " . $mergedPath;
```

## 💡 Vorteile

✅ **Einheitliche API**: Alle Workflow-Methoden haben die gleichen Speicher-Parameter  
✅ **Settings-Übernahme**: Alle dompdf-Settings (Schriftart, DPI, Format) werden verwendet  
✅ **Sichere Speicherung**: Kontrollierte Original-Ersetzung verhindert versehentliches Überschreiben  
✅ **Flexible Rückgabe**: `true` bei Ausgabe, Dateipfad bei Speicherung  
✅ **Error-Handling**: Exception bei Fehlern (z.B. Datei existiert bereits)  

## 🔧 Parameter-Referenz

| Parameter | Typ | Standard | Beschreibung |
|-----------|-----|----------|--------------|
| `$saveToPath` | string | `''` (leer) | Verzeichnis zum Speichern. Leer = direkte Ausgabe |
| `$replaceOriginal` | bool | `false` | `true` = überschreiben, `false` = Fehler bei vorhandener Datei |

**Rückgabewerte:**
- Direkte Ausgabe (`$saveToPath` leer): `true`
- Datei gespeichert: Vollständiger Dateipfad als `string`
- Fehler: `Exception` wird geworfen

**Die Workflow-Methoden sind jetzt maximal flexibel! 🚀**
