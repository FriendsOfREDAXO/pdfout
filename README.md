# PdfOut für REDAXO!

PdfOut stellt den "HTML to PDF"-Converter [dompdf](https://github.com/dompdf/dompdf) und [pdf.js](https://github.com/mozilla/pdf.js) in REDAXO zur Verfügung.

## Installation

Die Installation erfolgt über den REDAXO-Installer, alternativ gibt es die aktuellste Beta-Version auf [GitHub](https://github.com/FriendsOfREDAXO/pdfout).

## Was kann PdfOut?

- 🌈 Wandelt HTML in PDFs um
- 🎨 Passt Ausrichtung, Schriftart und mehr nach Herzenslust an
- 🖼 Integriert Bilder direkt aus dem REDAXO Media Manager
- 💾 Speichert PDFs ab oder streamt sie direkt an den Browser
- 🔢 Fügt sogar automatisch die Gesamt-Seitenzahlen ein
- 🔍 Mit dem integrieren Viewer kann man sich alles ansehen

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

## Erweiterte Methoden

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

## Systemvoraussetzungen

- DOM-Erweiterung
- MBString-Erweiterung
- `php-font-lib`
- `php-svg-lib`
- `gd-lib` oder ImageMagick

Empfohlen:
- OPcache für bessere Performance
- GD oder IMagick/GMagick für Bildverarbeitung

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

[MIT-Lizenz](https://github.com/FriendsOfREDAXO/pdfout/blob/master/LICENSE.md)
