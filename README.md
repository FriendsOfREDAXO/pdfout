
# PdfOut für REDAXO!

PdfOut stellt den "HTML to PDF"-Converter [dompdf](https://github.com/dompdf/dompdf) und [pdf.js](https://github.com/mozilla/pdf.js) in REDAXO zur Verfügung.

## Installation

Die Installation erfolgt über den REDAXO-Installer, alternativ gibt es die aktuellste Beta-Version auf [GitHub](https://github.com/FriendsOfREDAXO/pdfout).


## Was kann PdfOut?

Stellt euch vor, ihr könntet mit ein paar Zeilen Code aus euren REDAXO-Inhalten wunderschöne PDFs zaubern. Genau das ermöglicht euch PdfOut!

- 🌈 Wandelt HTML in PDFs um
- 🎨 Passt Ausrichtung, Schriftart und mehr nach Herzenslust an
- 🖼 Integriert Bilder direkt aus dem REDAXO Media Manager
- 💾 Speichert PDFs ab oder streamt sie direkt an den Browser
- 🔢 Fügt sogar automatisch Seitenzahlen ein
- 🔍 Mit dem integrieren Viewer kann man sich alles ansehem

## Lass uns loslegen!

### Das erste PDF in 3... 2... 1...

```php
$pdf = new PdfOut();
$pdf->setName('mein_erstes_pdf')
    ->setHtml('<h1>Hallo REDAXO-Welt!</h1><p>Mein erstes PDF mit PdfOut. Wie cool ist das denn?</p>')
    ->run();
```

Tadaa! 🎉 Das erste PDF ist erstellt. Einfach oder?

### Artikel-Inhalte als PDF? Kein Problem!

```php
use FriendsOfRedaxo\PDFOut\PdfOut;
$pdf = new PdfOut();
$pdf->setName('artikel_als_pdf')
    ->addArticle(1)  // Hier die ID eures Artikels einsetzen
    ->run();
```

### Schicke Vorlagen PDFs

```php
$meineVorlage = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: "Comic Sans MS", cursive; }
        .kopf { background-color: #ff9900; padding: 10px; }
        .inhalt { margin: 20px; }
    </style>
</head>
<body>
    <div class="kopf">Mein supercooler PDF-Kopf</div>
    <div class="inhalt">{{CONTENT}}</div>
</body>
</html>';

use FriendsOfRedaxo\PDFOut\PdfOut;
$pdf = new PdfOut();
$pdf->setName('stylisches_pdf')
    ->setBaseTemplate($meineVorlage)
    ->setHtml('<h1>Wow!</h1><p>Dieses PDF sieht ja mal richtig schick aus!</p>')
    ->run();
```

### PDFs speichern und verschicken

PDF speichern und gleichzeitig an den Browser senden? So geht's:

```php
use FriendsOfRedaxo\PDFOut\PdfOut;
$pdf = new PdfOut();
$pdf->setName('mein_meisterwerk')
    ->setHtml('<h1>PDF-Kunst</h1>')
    ->setSaveToPath(rex_path::addonCache('pdfout'))
    ->setSaveAndSend(true)  // Speichert und sendet in einem Rutsch
    ->run();
```

### Jetzt wird's ein wenig technischer 

### Systemvoraussetzungen

- DOM-Erweiterung
- MBString-Erweiterung
- `php-font-lib`
- `php-svg-lib`
- `gd-lib` oder ImageMagick

> **Bitte beachten:** Einige Erweiterungen bringen ebenfalls Abhängigkeiten mit sich, z.B. insbesondere `php-svg-lib` erfordert `sabberworm/php-css-parser`.

Zusätzlich empfohlen:

* OPcache (OPcache, XCache, APC, etc.): verbessert die Leistung
* GD (für Bildverarbeitung)
* IMagick- oder GMagick-Erweiterung: verbessert die Bildverarbeitungsleistung
* Besuchen Sie das Wiki für weitere Informationen: <https://github.com/dompdf/dompdf/wiki/Requirements>

### Erste Schritte

Nach der Installation und Aktivierung kann ein PDF wie folgt erzeugt werden:

- Den nachfolgenden in ein Template oder Modul einsetzen
- Der Aufruf erfolgt dann über die Variable pdfout=1 die über die URL übergeben wird. Der aktuelle Artikel kann so dann als PDF ausgegeben werden.

Sofern dann an eine aufgerufenen URL `?pdfout=1` angehängt wird, wird der Inhalt von `REX_ARTICLE[]` oder `REX_TEMPLATE[]` als PDF ausgegeben.

> **Tipp:** [Diese Seite als PDF im REDAXO-Backend aufrufen](index.php?pdftest=1). Der Aufruf klappt nur über das REDAXO Backend. Wenn man hinter die Backend url `?pdftest=1` dranhängt, kommt die README vom Add-on.

## Beispiel-Code

```php
use FriendsOfRedaxo\PDFOut\PdfOut;
$print_pdf = rex_request('pdfout', 'int');
if ($print_pdf) {
    $content = '
<style>
    body {
        font-family: "Helvetica"
    }
</style>
REX_ARTICLE[]
';

    $pdf = new PdfOut();

    $pdf->setName('REX_ARTICLE[field=name]')
        ->setFont('Helvetica')
        ->setHtml($content, true)
        ->setOrientation('portrait')
        ->setAttachment(true)
        ->setRemoteFiles(true)
        ->setDpi(300);

    // Save File to path and don't send File
    $pdf->setSaveToPath('/path/to/save/pdf/')->setSaveAndSend(false);

    // execute and generate
    $pdf->run();
}
```

In diesem Beispiel wird überprüft ob pdfout als Parameter übergeben wurde und der Output von REX_ARTICLE wird als PDF ausgegeben. Möchte man eine gestaltete Ausgabe, kann man ein Template erstellen und alle nötigen Styles dort einbauen und anstelle von REX_ARTICLE[] einsetzen, z.B. REX_TEMPLATE[key=pdf]. 

> Die Abfrage nach einem Request ist optional. Der Aufruf kann überall erfolgen, z.B. auch in einem Extensionpoint oder nach dem Ausfüllen eines Formulars. 

## Eigenschaften

- `$name`: Name der PDF Datei (standardmäßig `'pdf_file'`)
- `$html`: HTML Inhalt, der zu PDF konvertiert werden soll
- `$orientation`: Ausrichtung des PDFs (`'portrait'` oder `'landscape'`)
- `$font`: Standard Schriftart für das PDF (`'Dejavu Sans'`)
- `$attachment`: Ob das PDF als Anhang gesendet werden soll (standardmäßig `false`)
- `$remoteFiles`: Ob das Laden von entfernten Dateien im PDF erlaubt ist (standardmäßig `true`)
- `$saveToPath`: Pfad, auf den die PDF Datei gespeichert werden soll (standardmäßig `''`)
- `$dpi`: DPI der erstellten PDF (standardmäßig `100`)
- `$saveAndSend`: Ob das PDF gespeichert und gesendet werden soll (standardmäßig `true`)

## Methoden

### `setName(string $name)`
Setzt den Namen der PDF Datei.

### `setHtml(string $html, bool $outputfiler = false)`
Setzt den HTML Inhalt, der zu PDF konvertiert werden soll. Wenn $outputfilter auf true gesetzt wird, wird dieser ausgeführt und so z.B. die REDAXO_VARIABLEN verarbeitet.

### `setOrientation(string $orientation)`
Setzt die Ausrichtung des PDFs. Akzeptiert `'portrait'` oder `'landscape'`.

### `setFont(string $font)`
Setzt die Standard-Schriftart für das PDF.

### `setAttachment(bool $attachment)`
Setzt, ob das PDF als Anhang gesendet werden soll.

### `setRemoteFiles(bool $remoteFiles)`
Setzt, ob das Laden von entfernten Dateien im PDF erlaubt ist.

### `setSaveToPath(string $saveToPath)`
Setzt den Pfad, auf den die PDF-Datei gespeichert werden soll.

### `setDpi(int $dpi)`
Setzt das DPI der erstellten PDF.

### `setSaveAndSend(bool $saveAndSend)`
Setzt, ob das PDF gespeichert und gesendet werden soll.

### `run()`
Rendert das PDF und sendet es an den Browser, oder speichert es im angegebenen Pfad.

### `setBaseTemplate(string $template, string $placeholder = '{{CONTENT}}')`
Setzt ein optionales Grundtemplate für das PDF. Der Platzhalter wird durch den Inhalt ersetzt.

Beispiel:
```php
$baseTemplate = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; }
    </style>
</head>
<body>
    <div class="header">Mein Unternehmen</div>
    {{CONTENT}}
    <div class="footer">Seite {PAGENO} von DOMPDF_PAGE_COUNT_PLACEHOLDER</div>
</body>
</html>';

$pdf->setBaseTemplate($baseTemplate);
```

### `addArticle(int $articleId, ?int $ctype = null, bool $applyOutputFilter = true)`
Fügt den Inhalt eines REDAXO-Artikels zum PDF hinzu.

Beispiel:
```php
use FriendsOfRedaxo\PDFOut\PdfOut;
$pdf = new PdfOut();
$pdf->addArticle(1); // Fügt den gesamten Inhalt von Artikel mit ID 1 hinzu
$pdf->addArticle(2, 1, false); // Fügt nur den Inhalt von ctype 1 aus Artikel 2 hinzu, ohne OUTPUT_FILTER
```

### `mediaUrl(string $type, string $file)`
Generiert eine URL für ein Media-Element. Diese statische Methode ist besonders nützlich für die korrekte Einbindung von Bildern in PDFs.

Beispiel:
```php
$imageUrl = PdfOut::mediaUrl('media_type', 'image.jpg');
$html = '<img src="' . $imageUrl . '" alt="Mein Bild">';
$pdf->setHtml($html);
```

### `viewer(string $file = '')`
Generiert eine URL für den PDF-Viewer. Diese statische Methode ist nützlich, um PDFs im Browser anzuzeigen.

Beispiel:
```php
use FriendsOfRedaxo\PDFOut\PdfOut;
$viewerUrl = PdfOut::viewer('/media/document.pdf');
echo '<a href="' . $viewerUrl . '">PDF anzeigen</a>';
```



## Bilder im PDF

Medien die direkt aus dem Medien-Ordner geladen werden, müssen in einem Unterordner des Frontpage-Ordners der Website aufgerufen werden. 

Also z.B.: `media/image.png`

Medien, die über den Mediamanager aufgerufen werden, sollten *immer* über die volle URL aufgerufen werden. 

Also: `https://domain.tld/media/media_type/image.png`

## CSS und Fonts

CSS und Fonts sollten möglichst inline im HTML eingebunden sein. Die Pfade externer Assets können vollständige URls oder Pfade relativ zum des Frontpage-Ordners  haben. 


## Individuelle Einstellung

Es handelt sich hierbei um ein normales domPDF das über den Aufruf `new PdfOut()` instanziert werden kann.  

Mehr dazu bei: [dompdf](http://dompdf.github.io)

Hier ist ein Beispiel dafür, wie man die Optionen für domPDF nach der Instanziierung definieren kann, um isFontSubsettingEnabled auf false zu setzen:

```php
   use FriendsOfRedaxo\PDFOut\PdfOut;
   $pdf = new PdfOut();
   
   $options = $pdf->getOptions();
   $options->set('isFontSubsettingEnabled', false);
    
   $pdf->setName('pdf_file')
       ->setFont('Courier')
       ->setHtml($content, true)
       ->setOrientation('portrait')
       ->setAttachment(false)
       ->setRemoteFiles(true);
       ->run();
```

## Tipps

- Auf die numerische Angabe bei font-weight sollte verzichtet werden.
- Es empfiehlt sich im verwendeten Template die CSS-Definitionen nicht als externe Dateien sondern inline zu hinterlegen. Dies beschleunigt die Generierung, da keine externen Ressourcen eingelesen werden müssen.
- Auf Bootsstrap CSS oder andere CSS-Frameworks bei der Ausgabe möglichst verzichten, da zu viele Styles abgearbeitet werden müssen.
- URLs zu Ressourcen sollten ohne / beginnen und vom Frontpage-Ordner aus definiert sein z.B. media/zyz.jpg oder assets/css/pdf_styles.css. Ein Search & Replace per PHP kann hierbei helfen.
- Fixierte Divs können zur Anzeige von Fuß und Kopfzeile verwendet werden. Ideal ist es diese direkt nach dem Bodytag zu integrieren. Dann können auch mittels CSS count z.B. Seitenzahlen ausgegegeben werden.
- Google Fonts zur lokalen Nutzung herunterladen: <https://google-webfonts-helper.herokuapp.com/fonts>
- Wenn die eingebettete Schrift beim Drucken nicht korrekt dargestellt wird, die Einstellung "isFontSubsettingEnabled" auf "false" zu setzen. 

### Medienfiles umschreiben, 

die direkt aus dem Media-Verzeichnis ausgelesen werden.

```php
$media = rex_url::media($file); // normal
// wenn pdfout = 1
if(rex_request('pdfout', 'int')) { 
// entfernt Slash am Anfang
$media = ltrim(rex_url::media($file),'/'); 
}
```

## Ausgabe eines PDF mit pdf.js

Mit `PdfOut::viewer($file)` erhält man den Link zum aufruf des PDF. Es können auch PdfOut-PDF-Ulrs angegeben werrden. Sie müssen nicht mehr speziell encodet werden. 

Als Link zum Download
```php
<a href="<?=PdfOut::viewer('/media/pdfdatei.pdf')?>" download>PDF anzeigen</a>
```

```php
<iframe src="<?=PdfOut::viewer('/media/pdfdatei.pdf')?>"></iframe>
```


## Support & Credits

### Wo finde ich weitere Hilfe?

Fragen können im [REDAXO-Channel auf Slack](https://friendsofredaxo.slack.com/messages/redaxo/) gestellt werden.

### Autor

**Friends Of REDAXO**
http://www.redaxo.org 
https://github.com/FriendsOfREDAXO 

**Projekt-Lead** 
[Thomas Skerbis](https://github.com/skerbis)

Wir bedanken uns bei...

- [dompdf](http://dompdf.github.io)
- [FriendsOfREDAXO](https://github.com/FriendsOfREDAXO)
- [First release: Oliver Kreischer](https://github.com/olien)

### Lizenz

[MIT-Lizenz](https://github.com/FriendsOfREDAXO/pdfout/blob/master/LICENSE.md) 
