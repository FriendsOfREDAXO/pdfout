# REDAXO PdfOut - Neue Workflow-Methode

## 🚀 Vereinfachter REDAXO-Workflow für signierte PDFs

Die neue `createSignedWorkflow()` Methode in der PdfOut-Klasse automatisiert den kompletten empfohlenen Workflow für signierte PDFs in REDAXO.

### ✨ Neue Features

#### 1. Einfache Verwendung
```php
// Minimaler Code - nur 2 Zeilen!
$pdf = new PdfOut();
$pdf->createSignedDocument($html, 'dokument.pdf');
```

#### 2. Erweiterte Optionen
```php
$pdf = new PdfOut();
$pdf->createSignedWorkflow(
    $html,                          // HTML-Inhalt
    $certificatePath,               // Zertifikatspfad (optional)
    $certificatePassword,           // Zertifikatspasswort (optional)
    [                               // Signatur-Informationen (optional)
        'Name' => 'Max Mustermann',
        'Location' => 'Berlin',
        'Reason' => 'Rechnungsstellung',
        'ContactInfo' => 'max@firma.de'
    ],
    'rechnung.pdf'                  // Dateiname (optional)
);
```

#### 3. Mit benutzerdefinierten dompdf-Settings (⭐ Alle Settings werden übernommen!)
```php
$pdf = new PdfOut();
$pdf->setPaperSize('A4', 'landscape')     // Querformat
    ->setFont('Helvetica')                // Schriftart
    ->setDpi(300)                         // Hohe Auflösung  
    ->setAttachment(false)                // Inline-Anzeige
    ->createSignedWorkflow(
        $html,
        $certificatePath,
        $certificatePassword,
        [
            'Name' => 'Max Mustermann',
            'Location' => 'Berlin',
            'Reason' => 'Hochauflösende Rechnung',
            'ContactInfo' => 'max@firma.de'
        ],
        'hq_rechnung.pdf'
    );
```

### 🔄 Was passiert intern

1. **PDF-Erstellung**: Professionelles PDF mit dompdf (beste HTML/CSS-Unterstützung)
2. **Zwischenspeicherung**: Temporäre Speicherung im Cache-Verzeichnis
3. **Signierung**: Nachträgliche digitale Signierung mit FPDI+TCPDF
4. **Ausgabe**: Direkte PDF-Ausgabe mit korrekten Headern
5. **Aufräumen**: Automatisches Löschen temporärer Dateien

### 📋 Methoden-Parameter

| Parameter | Typ | Standard | Beschreibung |
|-----------|-----|----------|--------------|
| `$html` | string | - | HTML-Inhalt für das PDF (erforderlich) |
| `$certificatePath` | string | `default.p12` | Pfad zum Zertifikat |
| `$certificatePassword` | string | `redaxo123` | Zertifikatspasswort |
| `$signatureInfo` | array | Standard-Info | Signatur-Metadaten |
| `$filename` | string | `signed_document.pdf` | Ausgabe-Dateiname |
| `$cacheDir` | string | AddOn-Cache | Cache-Verzeichnis |
| `$saveToPath` | string | _(leer)_ | **Speicherverzeichnis (leer = direkte Ausgabe)** |
| `$replaceOriginal` | bool | `false` | **Original überschreiben (true/false)** |

### 🎯 Anwendungsbeispiele

#### Einfache Rechnung
```php
$html = '<h1>Rechnung #2024-001</h1>...';
$pdf = new PdfOut();
$pdf->createSignedDocument($html, 'rechnung_2024_001.pdf');
```

#### Hochauflösiges Zertifikat (A3 Querformat)
```php
$html = '<h1>Teilnahmezertifikat</h1>...';
$signatureInfo = [
    'Name' => 'Bildungseinrichtung XY',
    'Location' => 'München', 
    'Reason' => 'Zertifikatsausstellung',
    'ContactInfo' => 'zertifikate@bildung-xy.de'
];

$pdf = new PdfOut();
$pdf->setPaperSize('A3', 'landscape')     // A3 Querformat für Zertifikate
    ->setFont('Times')                    // Elegante Schriftart
    ->setDpi(300)                         // Druckqualität
    ->createSignedWorkflow(
        $html,
        '/path/to/institution_cert.p12',
        'sicheres_passwort',
        $signatureInfo,
        'zertifikat_' . $teilnehmer_id . '.pdf',
        '',                               // Standard Cache-Verzeichnis
        '/pfad/zu/zertifikaten/',         // Speicherort
        true                              // Original überschreiben falls vorhanden
    );
```

#### Kompakter Report (A5 Hochformat)
```php
$html = '<h1>Monatsbericht</h1>...';
$pdf = new PdfOut();
$pdf->setPaperSize('A5', 'portrait')      // Kompaktes Format
    ->setFont('Arial')                    // Standard-Schriftart
    ->setDpi(150)                         // Optimiert für Bildschirm
    ->setAttachment(false)                // Inline-Anzeige
    ->createSignedWorkflow(
        $html,
        '',                               // Standard-Zertifikat
        '',                               // Standard-Passwort
        ['Name' => 'Firma XY'],           // Minimale Signatur-Info
        'monatsbericht_' . date('Y-m') . '.pdf'
    );
```

### 💡 Vorteile

- **Einfachheit**: Von ~80 Zeilen Code auf 2 Zeilen reduziert
- **Robustheit**: Automatisches Error-Handling und Cleanup
- **Flexibilität**: Standardwerte können überschrieben werden, **alle dompdf-Settings werden übernommen**
- **Performance**: Optimierte Zwischenspeicherung
- **Sicherheit**: Automatisches Aufräumen temporärer Dateien
- **💾 Datei-Speicherung**: Optionales Speichern statt nur Ausgabe
- **🔄 Original-Ersetzung**: Kontrolliertes Überschreiben bestehender Dateien

### ⚙️ Verfügbare dompdf-Settings

Alle Standard-PdfOut-Methoden können vor dem Workflow-Aufruf verwendet werden:

| Methode | Beschreibung | Beispiel |
|---------|--------------|----------|
| `setPaperSize()` | Papierformat und Ausrichtung | `setPaperSize('A4', 'landscape')` |
| `setFont()` | Standard-Schriftart | `setFont('Helvetica')` |
| `setDpi()` | Auflösung in DPI | `setDpi(300)` |
| `setAttachment()` | Download vs. Inline-Anzeige | `setAttachment(false)` |
| `setRemoteFiles()` | Externe Ressourcen erlauben | `setRemoteFiles(true)` |

**Workflow-Methoden verwenden immer die konfigurierten Settings der aktuellen PdfOut-Instanz!**

### 🔧 Konfiguration

Standard-Parameter können in der AddOn-Konfiguration gesetzt werden:
- `default_certificate_path`
- `default_certificate_password`
- Signatur-Standardwerte

### 💾 Datei-Speicherung und Originalersetzung (⭐ Neues Feature!)

#### Alle Workflow-Methoden können jetzt Dateien speichern statt nur ausgeben:

```php
// 1. PDF direkt ausgeben (wie bisher)
$pdf = new PdfOut();
$pdf->createSignedDocument($html, 'dokument.pdf');

// 2. PDF als Datei speichern
$pdf = new PdfOut();
$savedPath = $pdf->createSignedDocument(
    $html, 
    'dokument.pdf',
    '/pfad/zu/speicherort/',        // Speicherverzeichnis
    false                           // Original NICHT überschreiben
);
echo "PDF gespeichert unter: " . $savedPath;

// 3. Vorhandene Datei überschreiben
$pdf = new PdfOut();
$savedPath = $pdf->createSignedDocument(
    $html, 
    'dokument.pdf',
    '/pfad/zu/speicherort/',        // Speicherverzeichnis  
    true                            // Original überschreiben = JA
);
```

#### Für alle Workflow-Methoden verfügbar:
- `createSignedWorkflow()` / `createSignedDocument()`
- `createPasswordProtectedWorkflow()` / `createPasswordProtectedDocument()`
- `mergePdfs()` / `mergeHtmlToPdf()`

#### Parameter für Datei-Speicherung:
| Parameter | Typ | Beschreibung |
|-----------|-----|--------------|
| `$saveToPath` | string | Verzeichnis zum Speichern (leer = direkte Ausgabe) |
| `$replaceOriginal` | bool | `true` = überschreiben, `false` = Fehler bei vorhandener Datei |

#### Rückgabewerte:
- **Direkte Ausgabe** (saveToPath leer): `true`
- **Datei gespeichert**: Vollständiger Pfad zur Datei als `string`
- **Fehler**: `Exception` wird geworfen

### 📎 PDF-Anhänge: Hauptdokument + bestehende PDFs (⭐ Neues Feature!)

#### Der häufigste Anwendungsfall: Rechnung mit AGB/Datenschutz anhängen

```php
// 1. Einfaches Beispiel: Rechnung + AGB
$rechnungHtml = '<h1>Rechnung #2024-001</h1><p>Betrag: 1.500€...</p>';
$agbPdfPath = '/pfad/zu/agb.pdf';

$pdf = new PdfOut();
$pdf->createDocumentWithAttachments(
    $rechnungHtml,                    // Hauptdokument (dompdf)
    [$agbPdfPath],                    // Anhänge
    'rechnung_mit_agb.pdf'            // Dateiname
);

// 2. Erweitert: Rechnung + AGB + Datenschutz + Widerrufsbelehrung
$anhaenge = [
    '/pfad/zu/agb.pdf',
    '/pfad/zu/datenschutz.pdf',
    '/pfad/zu/widerruf.pdf'
];

$pdf = new PdfOut();
$pdf->setPaperSize('A4', 'portrait')      // Settings für Hauptdokument
    ->setFont('Helvetica')                // Schriftart für Rechnung
    ->setDpi(300)                         // Hohe Auflösung
    ->createDocumentWithAttachments(
        $rechnungHtml,                    // Hauptdokument
        $anhaenge,                        // Alle Anhänge
        'rechnung_komplett.pdf'           // Dateiname
    );

// 3. Als Datei speichern statt direkt ausgeben
$savedPath = $pdf->createDocumentWithAttachments(
    $rechnungHtml,
    [$agbPdfPath, $datenschutzPdfPath],
    'rechnung_komplett.pdf',
    '/pfad/zum/speichern/',           // Speicherverzeichnis
    true                              // Original überschreiben
);
echo "Rechnung mit Anhängen gespeichert: " . $savedPath;
```

#### Was passiert intern:
1. **Hauptdokument** wird mit dompdf erstellt (perfekte HTML/CSS-Unterstützung)
2. **Zwischenspeicherung** im Cache
3. **FPDI+TCPDF** importiert Hauptdokument und hängt die PDFs an
4. **Ausgabe** als ein zusammenhängendes PDF
5. **Automatisches Aufräumen** der temporären Dateien

#### Praktische Anwendungsfälle:
- **E-Commerce**: Rechnung + AGB + Datenschutz + Widerrufsbelehrung
- **Verträge**: Hauptvertrag + Anhänge + Unterschriftenseiten
- **Reports**: Dynamischer Bericht + statische Anhänge (Grafiken, Zertifikate)
- **Angebote**: Individuelles Angebot + Produktkataloge + Referenzen

### 🎉 Resultat

Die Demo-Seite zeigt jetzt:
- **Kompaktes einseitiges PDF** (optimiertes Layout)
- **Vereinfachter Code** im Quellcode-Modal
- **Praktische Anwendung** der neuen Workflow-Methode

**Die neue Methode macht signierte PDFs in REDAXO so einfach wie nie zuvor! 🚀**
