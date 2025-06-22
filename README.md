# PdfOut für REDAXO!

PdfOut stellt den "HTML to PDF"-Converter [dompdf](https://github.com/dompdf/dompdf), [TCPDF](https://tcpdf.org/), [FPDI](https://www.setasign.com/products/fpdi/) und [pdf.js](https://github.com/mozilla/pdf.js) in REDAXO zur Verfügung.

## Inhaltsverzeichnis

- [Installation](#installation)
- [Features](#was-kann-pdfout)
- [Quick Start](#lass-uns-loslegen)
- [Passwortschutz](#passwortgeschützte-pdfs)
- [Digitale Signaturen](#digitale-signaturen)
- [REDAXO Workflow](#redaxo-workflow-dompdf--cache--signierung)
- [Erweiterte Methoden](#erweiterte-methoden)
- [Anwendungsfälle](#anwendungsfälle--best-practices)
- [Demo-Seite](#demo-seite)
- [Systemvoraussetzungen](#verwendete-bibliotheken--lizenzen)
- [Support](#support--credits)

## Installation

Die Installation erfolgt über den REDAXO-Installer, alternativ gibt es die aktuellste Beta-Version auf [GitHub](https://github.com/FriendsOfREDAXO/pdfout).

## Was kann PdfOut?

- 🌈 **HTML zu PDF**: Wandelt HTML in hochwertige PDFs um
- 🎨 **Anpassbar**: Ausrichtung, Schriftart, DPI und mehr
- 🖼 **Media Integration**: Bilder direkt aus dem REDAXO Media Manager
- 💾 **Flexibel**: Speichern oder direktes Streaming an Browser
- 🔢 **Automatik**: Seitenzahlen und -zählung automatisch
- 🔍 **Viewer**: Integrierter PDF-Viewer mit pdf.js
- 🔒 **Sicher**: Passwortschutz und Berechtigungen
- ✍️ **Signiert**: Digitale Signaturen für Authentizität
- 🚀 **Workflow**: Optimierter REDAXO-Workflow (dompdf → Cache → Signierung)

## Lass uns loslegen!

### Quick Start: Das erste PDF in 3... 2... 1...

```php
use FriendsOfRedaxo\PdfOut\PdfOut; 
$pdf = new PdfOut();
$pdf->setName('mein_erstes_pdf')
    ->setHtml('<h1>Hallo REDAXO-Welt!</h1><p>Mein erstes PDF mit PdfOut. Wie cool ist das denn?</p>')
    ->run();
```

### Artikel-Inhalte als PDF

```php
use FriendsOfRedaxo\PdfOut\PdfOut;
$pdf = new PdfOut();
$pdf->setName('artikel_als_pdf')
    ->addArticle(1)  // Hier die ID eures Artikels einsetzen
    ->run();
```

### Erweiterte Konfiguration eines PDFs

```php
use FriendsOfRedaxo\PdfOut\PdfOut;
$pdf = new PdfOut();

$pdf->setName('konfiguriertes_pdf')
    ->setPaperSize('A4', 'portrait')      // Setzt Papiergröße und Ausrichtung
    ->setFont('Helvetica')                // Setzt die Standardschriftart
    ->setDpi(300)                         // Setzt die DPI für bessere Qualität
    ->setAttachment(true)                 // Als Download statt Vorschau
    ->setRemoteFiles(true)                // Erlaubt externe Ressourcen
    ->setHtml($content, true)             // HTML mit Output Filter
    ->run();
```

### Schicke Vorlagen für PDFs

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
        .pagenum:before {
		content: counter(page);
        }
</style>

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
    ->setBaseTemplate($meineVorlage)
    ->setHtml('<h1>Wow!</h1><p>Dieses PDF sieht ja mal richtig schick aus!</p>')
    ->run();
```

### PDFs speichern und verschicken

PDF speichern und gleichzeitig an den Browser senden? So geht's:

```php
use FriendsOfRedaxo\PdfOut\PdfOut;
$pdf = new PdfOut();
$pdf->setName('mein_meisterwerk')
    ->setHtml('<h1>PDF-Kunst</h1>')
    ->setSaveToPath(rex_path::addonCache('pdfout'))
    ->setSaveAndSend(true)  // Speichert und sendet in einem Rutsch
    ->run();
```

### Passwortgeschützte PDFs

**Neuer empfohlener Workflow** mit dompdf → Cache → TCPDF-Passwortschutz:

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

// Einfache Passwortschutz-Methode (direkte Ausgabe)
$pdf = new PdfOut();
$pdf->createPasswordProtectedDocument(
    '<h1>Geheimes Dokument</h1><p>Nur mit Passwort zugänglich!</p>',
    'meinPasswort123',
    'geschuetztes_dokument.pdf'
);

// Als Datei speichern (⭐ Neu!)
$pdf = new PdfOut();
$savedPath = $pdf->createPasswordProtectedDocument(
    $htmlContent,
    'meinPasswort123',
    'geschuetztes_dokument.pdf',
    '/pfad/zum/speicherort/',        // Speicherverzeichnis
    true                             // Original überschreiben = ja
);
echo "PDF gespeichert: " . $savedPath;

// Erweiterte Passwortschutz-Methode mit mehr Optionen und Speicherung
$pdf = new PdfOut();
$pdf->setPaperSize('A4', 'landscape')     // Alle dompdf-Settings werden verwendet!
    ->setFont('Helvetica')                // Schriftart
    ->setDpi(300)                         // Hohe Auflösung
    ->createPasswordProtectedWorkflow(
        $htmlContent,                     // HTML-Inhalt
        'userPasswort',                   // User-Passwort (zum Öffnen)
        'ownerPasswort',                  // Owner-Passwort (Vollzugriff)
        ['print', 'copy', 'modify'],      // Erlaubte Aktionen
        'vertraulich.pdf',                // Dateiname
        '',                               // Standard Cache
        '/speicherort/',                  // Speicherverzeichnis (⭐ Neu!)
        false                             // Original NICHT überschreiben (⭐ Neu!)
    );
```

**Traditionelle TCPDF-Methode** (falls direkter Zugriff benötigt):

```php
$pdf = new TCPDF();
$pdf->SetProtection(
    ['print', 'copy'],  // Erlaubte Aktionen
    'user123',          // User-Passwort (zum Öffnen)
    'owner123'          // Owner-Passwort (Vollzugriff)
);

$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 12);
$pdf->writeHTML('<h1>Geheimes Dokument</h1><p>Nur mit Passwort zugänglich!</p>');
$pdf->Output('geschuetzt.pdf', 'I');
```

### Digitale Signaturen

Erstelle rechtsgültige, digital signierte PDFs:

```php
// Einfache Signierung (direkt mit TCPDF)
require_once rex_path::addon('pdfout') . 'vendor/tecnickcom/tcpdf/tcpdf.php';

$pdf = new TCPDF();
$pdf->setSignature(
    $certificatePath,    // Pfad zum Zertifikat (.p12)
    $certificatePath,    // Pfad zum Zertifikat
    'password',          // Zertifikats-Passwort
    '',                  // Private Key (leer wenn in .p12)
    2,                   // Signatur-Typ
    [
        'Name' => 'Max Mustermann',
        'Location' => 'Deutschland',
        'Reason' => 'Dokument-Authentifizierung',
        'ContactInfo' => 'max@example.com'
    ]
);

$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 12);
$pdf->writeHTML('<h1>Signiertes Dokument</h1>');
$pdf->Output('signiert.pdf', 'I');
```

### REDAXO Workflow: dompdf → Cache → Signierung

**Der empfohlene Weg** für komplexe PDFs mit Signierung:

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

// Neue vereinfachte Workflow-Methode (direkte Ausgabe)
$pdf = new PdfOut();
$pdf->createSignedDocument($htmlContent, 'dokument.pdf');

// Als Datei speichern (⭐ Neu!)
$pdf = new PdfOut();
$savedPath = $pdf->createSignedDocument(
    $htmlContent, 
    'dokument.pdf',
    '/pfad/zum/speicherort/',        // Speicherverzeichnis
    true                             // Original überschreiben = ja
);
echo "Signiertes PDF gespeichert: " . $savedPath;

// Oder mit erweiterten Optionen und Speicherung
$pdf->createSignedWorkflow(
    $htmlContent,                    // HTML-Inhalt
    $certificatePath,                // Zertifikatspfad
    $certificatePassword,            // Zertifikatspasswort
    ['Name' => 'Max Mustermann'],    // Signatur-Info
    'rechnung.pdf',                  // Dateiname
    '',                              // Standard Cache
    '/speicherort/',                 // Speicherverzeichnis (⭐ Neu!)
    false                            // Original NICHT überschreiben (⭐ Neu!)
);
```

**Was passiert intern:**
1. **dompdf** erstellt hochwertiges PDF mit perfektem HTML/CSS-Support
2. **Zwischenspeicherung** im Cache für Performance und Wiederverwendung
3. **FPDI + TCPDF** importiert und signiert das PDF nachträglich
4. **Automatisches Aufräumen** der temporären Dateien

### PDF-Zusammenführung

**Neue Workflow-Methoden** für das Zusammenführen von PDFs:

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

// HTML-Inhalte zu einem PDF zusammenführen (empfohlen, direkte Ausgabe)
$htmlContents = [
    '<h1>Dokument 1</h1><p>Projektübersicht...</p>',
    '<h1>Dokument 2</h1><p>Feature-Liste...</p>',
    '<h1>Dokument 3</h1><p>Fazit...</p>'
];

$pdf = new PdfOut();
$pdf->setPaperSize('A4', 'portrait')    // Alle dompdf-Settings werden verwendet
    ->setDpi(300)                       // Hohe Auflösung
    ->mergeHtmlToPdf(
        $htmlContents,                  // Array mit HTML-Inhalten
        'zusammengefuehrtes_dokument.pdf', // Ausgabe-Dateiname
        true                           // Trennseiten zwischen Dokumenten
    );

// HTML-Inhalte zusammenführen und als Datei speichern (⭐ Neu!)
$savedPath = $pdf->mergeHtmlToPdf(
    $htmlContents,                      // Array mit HTML-Inhalten
    'zusammengefuehrtes_dokument.pdf',  // Ausgabe-Dateiname
    true,                              // Trennseiten zwischen Dokumenten
    '/pfad/zum/speicherort/',          // Speicherverzeichnis (⭐ Neu!)
    false                              // Original NICHT überschreiben (⭐ Neu!)
);
echo "Zusammengeführtes PDF gespeichert: " . $savedPath;

// Bestehende PDF-Dateien zusammenführen
$pdfPaths = [
    '/path/to/document1.pdf',
    '/path/to/document2.pdf',
    '/path/to/document3.pdf'
];

// Direkte Ausgabe
$pdf->mergePdfs(
    $pdfPaths,                         // Array mit PDF-Dateipfaden
    'merged_document.pdf',             // Ausgabe-Dateiname
    false                              // Keine Trennseiten
);

// Als Datei speichern (⭐ Neu!)
$savedPath = $pdf->mergePdfs(
    $pdfPaths,                         // Array mit PDF-Dateipfaden
    'merged_document.pdf',             // Ausgabe-Dateiname
    false,                             // Keine Trennseiten
    '',                                // Standard Cache
    '/speicherort/',                   // Speicherverzeichnis (⭐ Neu!)
    true                               // Original überschreiben = ja (⭐ Neu!)
);
```

**Vorteile der PDF-Zusammenführung:**
- ✅ **Alle dompdf-Settings** werden bei HTML-Merge berücksichtigt
- ✅ **Optimale Qualität** durch dompdf für HTML-Rendering
- ✅ **Automatisches Aufräumen** temporärer Dateien
- ✅ **Flexible Optionen** für Trennseiten zwischen Dokumenten

## Erweiterte Methoden

### Basis-Methoden (PdfOut-Klasse)

### `setPaperSize(string|array $size = 'A4', string $orientation = 'portrait')`
Setzt das Papierformat und die Ausrichtung für das PDF. Als `$size` kann entweder ein Standardformat wie 'A4', 'letter' oder ein Array mit [width, height] in Punkten übergeben werden.

```php
$pdf->setPaperSize('A4', 'landscape');  // Querformat A4
$pdf->setPaperSize([841.89, 595.28], 'portrait');  // Benutzerdefinierte Größe
```

### `setBaseTemplate(string $template, string $placeholder = '{{CONTENT}}')`
Setzt ein Grundtemplate für das PDF. Der Platzhalter wird durch den eigentlichen Inhalt ersetzt. Besonders nützlich für einheitliches Layout über mehrere PDFs.

### `addArticle(int $articleId, ?int $ctype = null, bool $applyOutputFilter = true)`
Ermöglicht das Hinzufügen von REDAXO-Artikelinhalten:
- `$articleId`: Die ID des Artikels
- `$ctype`: Optional die ID des Content-Types
- `$applyOutputFilter`: Ob der OUTPUT_FILTER angewendet werden soll

### `mediaUrl(string $type, string $file)`
Generiert korrekte URLs für Media-Manager-Bilder im PDF:

```php
$imageUrl = PdfOut::mediaUrl('media_type', 'bild.jpg');
$html = '<img src="' . $imageUrl . '" alt="Mein Bild">';
```

### `viewer(string $file = '')`
Erzeugt eine URL für den integrierten PDF-Viewer:

```php
// Als Download-Link
echo '<a href="' . PdfOut::viewer('/media/dokument.pdf') . '" download>PDF anzeigen</a>';

// Als iFrame eingebettet
echo '<iframe src="' . PdfOut::viewer('/media/dokument.pdf') . '"></iframe>';
```

### Neue Workflow-Methoden

### `createSignedDocument(string $html, string $filename = 'document.pdf', string $saveToPath = '', bool $replaceOriginal = false)`
**Vereinfachte Methode** für den kompletten Workflow. Erstellt PDF mit dompdf, speichert zwischen und signiert nachträglich.

```php
// Direkte Ausgabe
$pdf = new PdfOut();
$pdf->createSignedDocument($htmlContent, 'rechnung.pdf');

// Als Datei speichern (⭐ Neu!)
$savedPath = $pdf->createSignedDocument(
    $htmlContent, 
    'rechnung.pdf',
    '/speicherort/',     // Speicherverzeichnis 
    true                 // Original überschreiben
);
```

### `createSignedWorkflow(string $html, string $certPath, string $certPassword, array $signatureInfo, string $filename, string $cacheDir, string $saveToPath, bool $replaceOriginal)`
**Erweiterte Workflow-Methode** mit vollständiger Kontrolle über Zertifikat, Signatur-Informationen und Dateispeicherung.

```php
$pdf = new PdfOut();
$pdf->createSignedWorkflow(
    $htmlContent,
    '/path/to/certificate.p12',
    'certificate_password',
    [
        'Name' => 'Max Mustermann',
        'Location' => 'Deutschland', 
        'Reason' => 'Rechnung signiert',
        'ContactInfo' => 'max@firma.de'
    ],
    'signierte_rechnung.pdf'
);
```

### TCPDF-Integration für erweiterte Features

### Passwort-Schutz mit `SetProtection()`
```php
$pdf = new TCPDF();
$pdf->SetProtection(
    $permissions,    // Array mit erlaubten Aktionen
    $userPassword,   // Passwort zum Öffnen
    $ownerPassword   // Master-Passwort
);
```

**Verfügbare Berechtigungen:**
- `'print'` - Drucken erlaubt
- `'copy'` - Text kopieren erlaubt  
- `'modify'` - Dokument bearbeiten
- `'annot-forms'` - Kommentare/Formulare
- `'fill-forms'` - Formulare ausfüllen
- `'extract'` - Seiten extrahieren
- `'assemble'` - Seiten zusammenfügen
- `'print-high'` - Hochwertiges Drucken

### Digitale Signaturen mit `setSignature()`
```php
$pdf = new TCPDF();
$pdf->setSignature(
    $certificate,     // Pfad zum Zertifikat (.p12)
    $certificate,     // Pfad zum Zertifikat (wiederhole für .p12)
    $password,        // Zertifikats-Passwort
    '',              // Private Key (leer für .p12)
    2,               // Signatur-Typ (2 = fortgeschritten)
    $info            // Array mit Signatur-Informationen
);
```

### FPDI für PDF-Import
Für nachträgliche Bearbeitung existierender PDFs:

```php
require_once rex_path::addon('pdfout') . 'vendor/setasign/fpdi/src/autoload.php';

$pdf = new setasign\Fpdi\Tcpdf\Fpdi();
$pageCount = $pdf->setSourceFile('existing.pdf');

for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
    $pdf->AddPage();
    $template = $pdf->importPage($pageNo);
    $pdf->useTemplate($template);
}
```

## Tipps für die Optimierung

### Performance-Optimierung
- CSS inline im HTML definieren statt externe Dateien
- Auf große CSS-Frameworks verzichten
- Bilder in optimierter Größe verwenden
- OPcache für bessere PHP-Performance aktivieren

### Bilder und Media Manager
- Relative Pfade vom Frontend-Ordner: `media/bild.jpg`
- Media Manager URLs immer als absolute URLs
- `setRemoteFiles(true)` für externe Ressourcen

### CSS und Schriftarten
- Numerische font-weight Angaben vermeiden
- Google Fonts lokal einbinden
- Bei Schriftproblemen: `isFontSubsettingEnabled` auf `false` setzen

### Kopf- und Fußzeilen
- Fixierte Divs direkt nach dem body-Tag platzieren
- Seitenzahlen über CSS count oder Platzhalter

## Anwendungsfälle & Best Practices

### Rechnungen und Geschäftsdokumente
```php
// Rechnung mit Signatur für Rechtsgültigkeit
$pdf = new PdfOut();
$pdf->createSignedWorkflow(
    $rechnungHtml,
    $firmenzertifikat,
    $zertifikatPasswort,
    ['Name' => 'Musterfirma GmbH', 'Reason' => 'Rechnung rechtsgültig signiert'],
    'rechnung_' . $rechnungsnummer . '.pdf'
);
```

### Vertrauliche Berichte
```php
// Passwortgeschützter Bericht mit eingeschränkten Rechten
$pdf = new TCPDF();
$pdf->SetProtection(['print'], $benutzerPasswort, $adminPasswort);
$pdf->AddPage();
$pdf->writeHTML($berichtContent);
$pdf->Output('vertraulicher_bericht.pdf', 'I');
```

### Zertifikate und Urkunden
```php
// Hochauflösendes Zertifikat mit Signatur
$pdf = new PdfOut();
$pdf->setDpi(300)  // Hohe Auflösung für Druck
    ->setPaperSize('A4', 'landscape');

$pdf->createSignedDocument($zertifikatHtml, 'zertifikat.pdf');
```

### Formulare zum Ausfüllen
```php
// PDF-Formular mit Schutz vor Strukturänderungen
$pdf = new TCPDF();
$pdf->SetProtection(['fill-forms', 'print'], '', $ownerPassword);
// ... Formularfelder hinzufügen
$pdf->Output('formular.pdf', 'I');
```

### Archivierung und Compliance
```php
// Langzeitarchivierung mit Signatur und Metadaten
$pdf = new TCPDF();
$pdf->SetCreator('REDAXO CMS');
$pdf->SetTitle('Archiviertes Dokument');
$pdf->SetSubject('Compliance-Archiv');
$pdf->SetKeywords('Archiv, Compliance, ' . date('Y'));

$pdf->setSignature($archivZertifikat, $archivZertifikat, $password, '', 2, [
    'Name' => 'Automatisches Archivsystem',
    'Reason' => 'Compliance-Archivierung',
    'Location' => 'Deutschland'
]);
```

## Systemvoraussetzungen

- DOM-Erweiterung
- MBString-Erweiterung
- `php-font-lib`
- `php-svg-lib`
- `gd-lib` oder ImageMagick

Empfohlen:
- OPcache für bessere Performance
- GD oder IMagick/GMagick für Bildverarbeitung
- OpenSSL für digitale Signaturen

## Demo-Seite

PdfOut enthält eine umfassende Demo-Seite mit funktionierenden Beispielen:

- **Einfaches PDF**: Grundlegende PDF-Erstellung mit dompdf
- **Passwortschutz**: Sichere PDFs mit konfigurierbaren Berechtigungen
- **Digitale Signaturen**: Rechtsgültige Signierung mit TCPDF
- **Nachträgliche Signierung**: FPDI-basierte Signierung existierender PDFs
- **REDAXO-Workflow**: Optimaler Workflow für komplexe, signierte PDFs

Die Demo zeigt auch:
- Test-Zertifikat-Generierung
- System-Status-Übersicht
- Sicherheits-Best-Practices
- Code-Beispiele für alle Features

## Verwendete Bibliotheken & Lizenzen

PdfOut baut auf bewährten Open-Source-Bibliotheken auf:

### PDF-Generierung

#### dompdf
- **Homepage**: https://github.com/dompdf/dompdf
- **Lizenz**: LGPL v2.1
- **Zweck**: HTML-zu-PDF-Konvertierung mit excellentem CSS-Support
- **Dokumentation**: https://github.com/dompdf/dompdf/wiki

#### TCPDF
- **Homepage**: https://tcpdf.org/
- **Lizenz**: LGPL v3+
- **Zweck**: Erweiterte PDF-Features (Signaturen, Passwörter, Formulare)
- **Dokumentation**: https://tcpdf.org/docs/

#### FPDI
- **Homepage**: https://www.setasign.com/products/fpdi/
- **Lizenz**: MIT (Community Version)
- **Zweck**: Import und Bearbeitung existierender PDFs
- **Dokumentation**: https://www.setasign.com/products/fpdi/manual/

### CSS- und HTML-Verarbeitung

#### sabberworm/php-css-parser
- **Homepage**: https://github.com/sabberworm/PHP-CSS-Parser
- **Lizenz**: MIT
- **Zweck**: CSS-Parsing für dompdf

#### masterminds/html5
- **Homepage**: https://github.com/Masterminds/html5-php
- **Lizenz**: MIT
- **Zweck**: HTML5-Parser für moderne HTML-Unterstützung

#### php-font-lib & php-svg-lib
- **Homepage**: https://github.com/dompdf/php-font-lib
- **Lizenz**: LGPL v2.1
- **Zweck**: Font-Handling und SVG-Unterstützung

### PDF-Viewer

#### PDF.js
- **Homepage**: https://github.com/mozilla/pdf.js
- **Lizenz**: Apache 2.0
- **Zweck**: Integrierter PDF-Viewer im Browser
- **Dokumentation**: https://mozilla.github.io/pdf.js/

## Lizenzen im Detail

### LGPL (Lesser General Public License)
Die LGPL-lizenzierten Komponenten (dompdf, TCPDF, php-font-lib) erlauben:
- ✅ Kommerzielle Nutzung
- ✅ Einbindung in proprietäre Software
- ✅ Modifikation der Bibliotheken
- ⚠️ Modifikationen an LGPL-Code müssen unter LGPL bleiben

### MIT License
Die MIT-lizenzierten Komponenten erlauben:
- ✅ Vollständig freie Nutzung
- ✅ Kommerzielle Nutzung ohne Einschränkungen
- ✅ Modifikation und Weiterverteilung
- ✅ Einbindung in proprietäre Software

### Apache 2.0 (PDF.js)
- ✅ Kommerzielle Nutzung
- ✅ Patent-Grant (Schutz vor Patent-Klagen)
- ✅ Trademark-Schutz

## Support & Credits

### Wo finde ich Hilfe?

- [REDAXO-Channel auf Slack](https://friendsofredaxo.slack.com/messages/redaxo/)
- [GitHub Issues](https://github.com/FriendsOfREDAXO/pdfout/issues)
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

**PdfOut selbst**: [MIT-Lizenz](https://github.com/FriendsOfREDAXO/pdfout/blob/master/LICENSE.md)

**Verwendete Bibliotheken**:
- dompdf: LGPL v2.1
- TCPDF: LGPL v3+
- FPDI: MIT
- PDF.js: Apache 2.0  
- php-css-parser: MIT
- html5-php: MIT

Alle Lizenzen sind kompatibel und erlauben sowohl private als auch kommerzielle Nutzung.
