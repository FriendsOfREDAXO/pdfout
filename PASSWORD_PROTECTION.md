# Passwortgeschützte PDFs mit PdfOut

## Überblick

Das PdfOut-AddOn unterstützt die Erstellung von passwortgeschützten PDFs über TCPDF. Diese Funktion ist ideal für vertrauliche Dokumente, die nur autorisierten Personen zugänglich sein sollen.

## Warum TCPDF für Passwortschutz?

- **dompdf**: Unterstützt **keine** Passwort-Features
- **TCPDF**: Vollständige Unterstützung für Passwörter und Berechtigungen
- **Workflow**: Für komplexe Layouts dompdf → TCPDF-Nachbearbeitung

## Demo-Implementation

### Grundlegende Passwort-Konfiguration
```php
$pdf = new TCPDF();

// Passwortschutz mit Berechtigungen
$pdf->SetProtection(
    ['print', 'copy'],    // Erlaubte Aktionen
    'user123',            // User-Passwort (zum Öffnen)
    'owner123'            // Owner-Passwort (Vollzugriff)
);

$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 12);
$pdf->writeHTML($content);
$pdf->Output();
```

## Passwort-Arten

### 1. User-Passwort (Öffnen-Passwort)
- **Zweck**: PDF öffnen und lesen
- **Einschränkungen**: Je nach Berechtigungen
- **Demo-Wert**: `user123`

### 2. Owner-Passwort (Master-Passwort)
- **Zweck**: Vollzugriff auf alle Funktionen
- **Berechtigung**: Kann Schutz aufheben
- **Demo-Wert**: `owner123`

## Verfügbare Berechtigungen

| Berechtigung | Beschreibung | Konstante |
|--------------|--------------|-----------|
| `print` | Dokument drucken | `print` |
| `copy` | Text kopieren | `copy` |
| `modify` | Dokument bearbeiten | `modify` |
| `annot-forms` | Kommentare/Formulare | `annot-forms` |
| `fill-forms` | Formulare ausfüllen | `fill-forms` |
| `extract` | Seiten extrahieren | `extract` |
| `assemble` | Seiten zusammenfügen | `assemble` |
| `print-high` | Hochwertig drucken | `print-high` |

### Beispiel-Konfigurationen

#### Nur Lesen (sehr restriktiv)
```php
$pdf->SetProtection(
    [],              // Keine besonderen Berechtigungen
    'read123',       // User-Passwort
    'admin123'       // Owner-Passwort
);
```

#### Standard (Lesen + Drucken)
```php
$pdf->SetProtection(
    ['print'],       // Nur Drucken erlaubt
    'user123',       // User-Passwort
    'admin123'       // Owner-Passwort
);
```

#### Erweitert (Lesen + Drucken + Kopieren)
```php
$pdf->SetProtection(
    ['print', 'copy'],  // Drucken und Kopieren
    'user123',          // User-Passwort
    'admin123'          // Owner-Passwort
);
```

#### Formulare (Interaktive PDFs)
```php
$pdf->SetProtection(
    ['print', 'copy', 'fill-forms'],  // + Formulare ausfüllen
    'user123',                        // User-Passwort
    'admin123'                        // Owner-Passwort
);
```

## Sicherheits-Best-Practices

### ❌ Nicht in Produktion verwenden
```php
// Schwache Demo-Passwörter
$pdf->SetProtection(['print'], 'user123', 'owner123');

// Hardcoded Passwörter
$pdf->SetProtection(['print'], 'password', 'admin');
```

### ✅ Sicher für Produktion
```php
// Starke, zufällige Passwörter
$userPassword = bin2hex(random_bytes(8));
$ownerPassword = bin2hex(random_bytes(12));

// Aus sicherer Konfiguration laden
$userPassword = rex_config::get('pdfout', 'user_password');
$ownerPassword = rex_config::get('pdfout', 'owner_password');

// Aus Umgebungsvariablen
$userPassword = $_ENV['PDF_USER_PASSWORD'];
$ownerPassword = $_ENV['PDF_OWNER_PASSWORD'];

$pdf->SetProtection(['print'], $userPassword, $ownerPassword);
```

## Anwendungsfälle

### 1. Vertrauliche Berichte
- **Passwort**: Nur autorisierte Personen
- **Berechtigungen**: Lesen, Drucken
- **Schutz**: Keine Bearbeitung oder Extraktion

### 2. Personaldokumente
- **Passwort**: Mitarbeiter-spezifisch
- **Berechtigungen**: Lesen, Drucken
- **Schutz**: Schutz sensibler Daten

### 3. Finanzberichte
- **Passwort**: Abteilungs-spezifisch
- **Berechtigungen**: Lesen, eingeschränktes Drucken
- **Schutz**: Compliance-Anforderungen

### 4. Rechtsdokumente
- **Passwort**: Klienten-spezifisch
- **Berechtigungen**: Lesen, kein Kopieren
- **Schutz**: Schutz vor Manipulation

## Integration in REDAXO-Projekte

### Einfache Integration
```php
// In REDAXO-Templates oder Modulen
$pdf = new TCPDF();
$userPassword = rex_session('user_pdf_password', 'string');
$ownerPassword = rex_config::get('pdfout', 'master_password');

$pdf->SetProtection(['print', 'copy'], $userPassword, $ownerPassword);
```

### Mit PdfOut-Workflow (⭐ Empfohlen für REDAXO)
```php
// Neue vereinfachte Workflow-Methoden (empfohlen):

// 1. Einfache Passwortschutz-Methode
$pdf = new PdfOut();
$pdf->createPasswordProtectedDocument(
    $complexHtml,           // HTML mit vollem CSS-Support
    'meinPasswort123',      // User-Passwort
    'geschuetzt.pdf'        // Dateiname
);

// 2. Erweiterte Passwortschutz-Methode
$pdf = new PdfOut();
$pdf->createPasswordProtectedWorkflow(
    $complexHtml,           // HTML-Inhalt
    'userPasswort',         // User-Passwort (zum Öffnen)
    'ownerPasswort',        // Owner-Passwort (Vollzugriff)
    ['print', 'copy'],      // Erlaubte Aktionen
    'vertraulich.pdf'       // Dateiname
);

// 3. Mit benutzerdefinierten dompdf-Settings (⭐ Alle Settings werden übernommen!)
$pdf = new PdfOut();
$pdf->setPaperSize('A4', 'landscape')     // Querformat
    ->setFont('Helvetica')                 // Schriftart
    ->setDpi(300)                         // Hohe Auflösung
    ->setAttachment(false)                // Inline-Anzeige
    ->createPasswordProtectedWorkflow(
        $complexHtml,
        'userPasswort',
        'ownerPasswort',
        ['print', 'copy'],
        'hq_vertraulich.pdf'
    );
```

**Workflow-Vorteile:**
- ✅ **Beste Qualität**: dompdf für perfekte HTML/CSS-Unterstützung
- ✅ **Performance**: Optimierte Zwischenspeicherung  
- ✅ **Sicherheit**: TCPDF für professionellen Passwortschutz
- ✅ **Einfachheit**: Ein Methodenaufruf für komplette Funktionalität
- ✅ **Aufräumen**: Automatische Bereinigung temporärer Dateien

### Manuelle TCPDF-Implementierung
```php
// 1. HTML-PDF mit dompdf erstellen
$originalPdf = new PdfOut();
$originalPdf->setHtml($complexHtml);
$tempFile = 'temp_' . uniqid() . '.pdf';
$originalPdf->setSaveToPath(rex_path::addonCache('pdfout'));
$originalPdf->setName($tempFile);
$originalPdf->setSaveAndSend(false);
$originalPdf->run();

// 2. Mit TCPDF importieren und Passwort hinzufügen
$protectedPdf = new setasign\Fpdi\Tcpdf\Fpdi();
$protectedPdf->SetProtection(['print'], $userPassword, $ownerPassword);
$protectedPdf->setSourceFile($tempFile);
// ... importieren und ausgeben
```

## 🚀 Neue Workflow-Methoden mit Datei-Speicherung

### Vereinfachter Workflow (⭐ Empfohlen)
```php
// 1. Direkte Ausgabe (wie bisher)
$pdf = new PdfOut();
$pdf->createPasswordProtectedDocument($html, 'meinpasswort', 'geschuetzt.pdf');

// 2. Als Datei speichern (⭐ Neu!)
$pdf = new PdfOut();
$savedPath = $pdf->createPasswordProtectedDocument(
    $html, 
    'meinpasswort', 
    'geschuetzt.pdf',
    '/pfad/zu/speicherort/',        // Speicherverzeichnis
    false                           // Original NICHT überschreiben
);
echo "PDF gespeichert unter: " . $savedPath;

// 3. Vorhandene Datei überschreiben (⭐ Neu!)
$pdf = new PdfOut();
$savedPath = $pdf->createPasswordProtectedDocument(
    $html, 
    'meinpasswort', 
    'geschuetzt.pdf',
    '/pfad/zu/speicherort/',        // Speicherverzeichnis  
    true                            // Original überschreiben = JA
);
```

### Erweiterte Workflow-Methode
```php
// Mit allen Settings und Datei-Speicherung
$pdf = new PdfOut();
$pdf->setPaperSize('A4', 'landscape')     
    ->setFont('Helvetica')                
    ->setDpi(300)                         
    ->createPasswordProtectedWorkflow(
        $html,
        'userpasswort',               // User-Passwort
        'ownerpasswort',              // Owner-Passwort
        ['print', 'copy'],            // Berechtigungen
        'dokument.pdf',               // Dateiname
        '',                           // Standard Cache
        '/speicherort/',              // Speicherverzeichnis (⭐ Neu!)
        true                          // Original überschreiben (⭐ Neu!)
    );
```

## Passwort-Übertragung

### Sichere Methoden
1. **Separate E-Mail**: Passwort in separater E-Mail senden
2. **SMS/Telefon**: Passwort über anderen Kanal übermitteln
3. **Persönliche Übergabe**: Passwort persönlich mitteilen
4. **Sichere Messenger**: Verschlüsselte Messaging-Dienste

### ❌ Unsichere Methoden
- Passwort im PDF-Dateinamen
- Passwort in derselben E-Mail wie PDF
- Passwort im PDF-Inhalt selbst
- Standardisierte/vorhersagbare Passwörter

## Fehlerbehebung

### Problem: PDF öffnet sich nicht
**Ursache**: Falsches Passwort oder Zeichenkodierung
**Lösung**: UTF-8-Kodierung prüfen, Passwort verifizieren

### Problem: Berechtigungen funktionieren nicht
**Ursache**: Reader unterstützt Berechtigungen nicht vollständig
**Lösung**: Mit Adobe Reader testen, dokumentieren

### Problem: Passwort wird nicht akzeptiert
**Ursache**: Unterschiedliche Zeichenkodierung
**Lösung**: ASCII-Zeichen verwenden oder UTF-8 explizit setzen

## Demo-Zugriff

Für die Demo-Passwörter:
- **Öffnen**: `user123`
- **Vollzugriff**: `owner123`
- **Berechtigungen**: Drucken und Kopieren erlaubt

**Wichtig**: Diese Passwörter sind nur für Demonstrationszwecke! In produktiven Umgebungen immer starke, einzigartige Passwörter verwenden.
