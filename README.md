# PdfOut für REDAXO!

PdfOut stellt die "HTML to PDF"-Converter [dompdf](https://github.com/dompdf/dompdf) und [pdf.js](https://github.com/mozilla/pdf.js) in REDAXO zur Verfügung und wurde um leistungsstarke **TCPDF-Features** erweitert.

Es ermöglicht die einfache Umwandlung von HTML-Inhalten (auch REDAXO-Artikel) in PDF-Dateien, deren Anzeige im Browser, Speicherung oder direkten Download sowie fortgeschrittene Funktionen wie digitale Signierung, Passwortschutz und nachträgliche Bearbeitung.

## Wichtige Änderungen in 10.0

### Entfernte Funktionen

Die Datei `deprecated_pdfout.php` wurde entfernt, da sie veraltet war und seit Version 8.5.0 als `@deprecated` markiert wurde. Bitte verwenden Sie stattdessen die Klasse `FriendsOfRedaxo\PdfOut\PdfOut` direkt.

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
- ⚙️ **Flexible Konfiguration:** Umfangreiche Optionen über das Backend-Interface.
- 🎯 **Automatische Erkennung:** Intelligente Auswahl zwischen DomPDF und TCPDF je nach benötigten Features.

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

### Test-Funktionen

Im Addon-Backend stehen Funktionen zum Testen und Debugging bereit:
- **Automatische Zertifikatsgenerierung:** Erstellen Sie Test-Zertifikate direkt in der Konfiguration.
- **Demo-Seiten:** Umfangreiche Beispiele und Tests zur Demonstration der verschiedenen Features.
- **Debugging:** Detaillierte Fehleranalyse und Systemstatus-Informationen.

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



