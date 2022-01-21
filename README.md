# PdfOut – PDF Generator(dompdf)  & Viewer (pdf.js) 

PdfOut stellt den "HTML to PDF"-Converter [dompdf](http://dompdf.github.io) und [pdf.js](https://github.com/mozilla/pdf.js) in REDAXO zur Verfügung.

Mit dompdf können Ausgaben in REDAXO als PDF generiert werden und mittels pdf.js angezeigt werden. 

## Installation

Die Installation erfolgt über den REDAXO-Installer, alternativ gibt es die aktuellste Beta-Version auf [GitHub](https://github.com/FriendsOfREDAXO/pdfout).

### Systemvoraussetzungen

- DOM-Erweiterung
- MBString-Erweiterung
- php-font-lib
- php-svg-lib

> Bitte beachten: Einige Erweiterungen bringen ebenfalls Abhängigkeiten mit sich, z.B. insbesondere `php-svg-lib` erfordert `sabberworm/php-css-parser`.

Zusätzlich empfohlen:

* OPcache (OPcache, XCache, APC, etc.): verbessert die Leistung
* GD (für Bildverarbeitung)
* IMagick- oder GMagick-Erweiterung: verbessert die Bildverarbeitungsleistung
* Besuchen Sie das Wiki für weitere Informationen: <https://github.com/dompdf/dompdf/wiki/Requirements>

### Erste Schritte

Nach der Installation und Aktivierung kann ein PDF wie folgt erzeugt werden:

- Den nachfolgenden Code am Anfang des gewünschten Templates oder als separates Template einsetzen
- Der Aufruf erfolgt dann über die Variable pdf=1 die über die URL übergeben wird. Der aktuelle Artikel kann so dann als PDF ausgegeben werden.

Sofern dann an eine aufgerufenen URL **?pdf=1** angehängt wird, wird der Inhalt von REX_ARTICLE[] oder REX_TEMPLATE [] als PDF ausgegeben.

> **Tipp:** [Diese Seite als PDF im REDAXO-Backend aufrufen](index.php?pdftest=1). Der Aufruf klappt nur über das REDAXO Backend. Wenn man hinter die Backend url `?pdftest=1` dranhängt, kommt die README vom Addon.

## Beispiel-Code

```php
$print_pdf = rex_request('pdfout', 'int');
if ($print_pdf) {
  $pdfcontent = 'REX_ARTICLE[]';
  // Outputfilter auf Inhalt anwenden, sofern erforderlich, z.B. wenn Template genutzt wird. 
  // Wenn nicht verwendet, wird die Generierung beschleunigt
  $pdfcontent = rex_extension::registerPoint(new rex_extension_point('OUTPUT_FILTER', $pdfcontent));
  PdfOut::sendPdf('Dateiname_ohne_endung', $pdfcontent);
}
```

In diesem Beispiel wird überprüft ob pdfout als Parameter übergeben wurde und der Output von REX_ARTICLE wird als PDF ausgegeben. Möchte man eine gestaltete Ausgabe, kann man ein Template erstellen und alle nötigen Styles dort einbauen und anstelle von REX_ARTICLE[] einsetzen, z.B. REX_TEMPLATE[key=pdf]. 

> Die Abfrage nach einem Request ist optional. Der Aufruf kann überall erfolgen, z.B. auch in einem Extensionpoint oder nach dem Ausfüllen eines Formulars. 


## Die Methode sendPdf

Mit sendPDF kann schnell ein PDF erzeugt werden. Folgende Optionen stehen zur Verfügung 

- $name = 'Dateiname ohne Endung'
- $html = Das HTML das übergen werden soll 
- $orientation = 'portrait' oder 'landscape'
- $defaultFont = 'Courier'
- $attachment = false 
- $remoteFiles = true oder false - true wird benötigt wen MediaManager-Dateien gekaden werden sollen. Der übergebene HTML-Code sollte ggf. überprüft werden. 

```php
PdfOut::sendPdf($name = 'pdf_file', $html = '', $orientation = 'portrait', $defaultFont ='Courier', $attachment = false, $remoteFiles = true)
```

## Bilder im PDF

Medien die direkt aus dem Medien-Ordner geladen werden, müssen relativ zum Root der Website aufgerufen werden. 

Also: `media/image.png`

Medien, die über den Mediamanager aufgerufen werden, sollten immer über die volle URL aufgerufen werden. 

Also: `https://domain.tld/media/media_type/image.png`

## CSS und Fonts

CSS und Fonts sollten möglichst inline im HTML eingebunden sein. Die Pfade externer Assets können vollständige URls oder Pfade relativ zum Root haben. 

## Individuelle Einstellung
Es handelt sich hierbei immer noch um das reguläre domPDF das über den Aufruf `new PdfOut()` instanziert werden kann. 

Mehr dazu bei: [dompdf](http://dompdf.github.io)


## Tipps

- Auf die numerische Angabe bei font-weight sollte verzichtet werden.
- Es empfiehlt sich im verwendeten Template die CSS-Definitionen nicht als externe Dateien sondern inline zu hinterlegen. Dies beschleunigt die Generierung, da keine externen Ressourcen eingelesen werden müssen.
- Auf Bootsstrap CSS oder andere CSS-Frameworks bei der Ausgabe möglichst verzichten, da zuviele Styles abgearbeitet werden müssen.
- URLs zu Ressourcen sollten ohne / beginnen und vom Webroot aus definiert sein z.B. media/zyz.jpg oder assets/css/pdf_styles.css. Ein Search & Replace per PHP kann hierbei helfen.
- Fixierte Divs können zur Anzeige von Fuß und Kopfzeile verwendet werden. Ideal ist es diese direkt nach dem Bodytag zu integrieren. Dann können auch mittels CSS count z.B. Seitenzahlen ausgegegeben werden.
- Google Fonts zur lokalen Nutzung herunterladen: <https://google-webfonts-helper.herokuapp.com/fonts>

### Medienfiles umschreiben 

Die direkt aus dem Media-Verzeichnis ausgelesen werden.

```php
$media = rex_url::media($file); // normal
// wenn pdfout = 1
if(rex_request('pdfout', 'int')) { 
// entfernt Slash am Anfang
$media = ltrim(rex_url::media($file),'/'); 
}
```

### Font-Awsome 4.x einbinden:

Font-Awsome fonts werden nicht korrekt dargestellt.
Folgender Workaround hilft:
Einbindung z.B. CDN im Template

Zusätzlichen Stil in Style-Tag inline einfügen:

```html 
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.6.3/css/font-awesome.min.css">
 <style>
 .fa {
     display: inline;
     font-style: normal;
     font-variant: normal;
     font-weight: normal;
     font-size: 14px
     line-height: 1;
     color: #2F2ABD;
     font-family: FontAwesome;
     font-size: inherit;
     text-rendering: auto;
     -webkit-font-smoothing: antialiased;
     -moz-osx-font-smoothing: grayscale;
   }
 </style>  
```

## Ausgabe eines PDF mit pdf.js

Mit `PdfOut::viewer($file)` erhält man den Link zum aufruf des PDF. Es können auch PdfOut-PDF-Ulrs angegeben werrden. Sie müssen nicht mehr speziell encodet werden. 

```php
<a href="<?=PdfOut::viewer('/media/pdfdatei.pdf')?>">PDF anzeigen</a>
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
- [Simon Krull](https://github.com/crydotsnake)
- [Alexander Walther](https://github.com/alexplusde)

### Lizenz

[MIT-Lizenz](https://github.com/FriendsOfREDAXO/pdfout/blob/master/LICENSE.md) 
