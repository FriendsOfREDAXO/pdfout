## PDF out – dompdf für REDAXO

PDF out stellt den "HTML to PDF"-Converter dompdf (http://dompdf.github.io) in REDAXO zur Verfügung.
Mit dompdf können Ausgaben in REDAXO als PDF generiert werden. 
PDF out ist keine "out of the box"-Lösung. Es stellt nur die PHP Class/es bereit.  
___

### Mögliche Anwendung: 

Nach der Installation und Aktivierung kann ein PDF wie folgt erzeugt werden:
- Den nachfolgenden Code am Anfang des gewünschten Templates oder als separates Template einsetzen
- Der Aufruf erfolgt dann über die Variable pdf=1 die über die URL übergeben wird. Der aktuelle Artikel kann so dann als PDF ausgegeben werden. 

Sofern dann an eine aufgerufenen URL **?pdf=1** angehängt wird, wird der Inhalt von REX_ARTICLE[] oder REX_TEMPLATE [] als PDF ausgegeben.

### Beispiel-Code

```php
<?php
  // ?pdf=1
  $print_pdf = rex_request('pdf', 'int');
  if ($print_pdf) {
	  rex_response::cleanOutputBuffers(); // OutputBuffer leeren
	  // Artikel laden oder alternativ ein Template
	  $pdfcontent = 'REX_ARTICLE[]';
	  // Outputfilter auf Inhalt anwenden, sofern erforderlich 
	  // Wenn nicht, wird die Generierung beschleunigt
	  $pdfcontent = rex_extension::registerPoint(new rex_extension_point('OUTPUT_FILTER', $pdfcontent));
	  // Dateiname aus Artikelname erstellen. 
	  $art_pdf_name =  rex_string::normalize(rex_article::getCurrent()->getValue('name'));
	  // PDF erstellen
	  header('Content-Type: application/pdf');
	  $dompdf = new Dompdf\Dompdf();
	  $dompdf->loadHtml($pdfcontent);
	  $dompdf->setPaper('A4', 'portrait');
	  // Optionen festlegen 
	  $dompdf->set_option('defaultFont', 'Helvetica');
	  $dompdf->set_option('dpi', '100');
	  // Rendern des PDF
	  $dompdf->render();
	  // Ausliefern des PDF
	  $dompdf->stream($art_pdf_name ,array('Attachment'=>false)); // bei true wird Download erzwungen
	  die();
	}
?>
```
### Erweitertes Beispiel mit inline-css und Url-Ersetzung
Damit Bilder ausgegeben werden können, müssen die Bild-Urls umgeschrieben werden. MediaManager-Urls können nicht sofort genutzt werden. Die Bilder müssen direkt aus dem media/-Ordner ausgelesen werden oder mit voller URL angegeben werden. (siehe hierzu: https://github.com/FriendsOfREDAXO/pdfout/issues/13)  
Unbedingt die Kommentare beachten.

Externe CSS können im <**head**> eingebunden werden
```php
<?php
$print_pdf = rex_request('pdf', 'int');
// ?pdf=1 
if ($print_pdf) {
        rex_response::cleanOutputBuffers(); // OutputBuffer leeren
	$pdfcontent = 'REX_ARTICLE[]';
	// Outputfilter auf Inhalt anwenden, sofern erforderlich
	// Wenn nicht, wird die Generierung beschleunigt
	$pdfcontent = rex_extension::registerPoint(new rex_extension_point('OUTPUT_FILTER', $pdfcontent));
	// Hier Beispiele für Image-Rewrite
	// Bei der Verwendung von MediaManager-Bildern anpassen    
	$pdfcontent = str_replace("/index.php?rex_media_type=standard&amp;rex_media_file=", "media/", $pdfcontent);
	$pdfcontent = str_replace("index.php?rex_media_type=redactorImage&amp;rex_media_file=", "media/", $pdfcontent);
	$pdfcontent = str_replace("index.php?rex_media_type=redactorImage&rex_media_file=", "media/", $pdfcontent);
	// übliche Links in das Medienverzeichnis    
	$pdfcontent = str_replace("/media/", "media/", $pdfcontent);
	$pdfcontent = str_replace(".media/", "media/", $pdfcontent);

	// Kopfdefinition
	$pre = '
	<head>

	</head>
	<body>
	<img id="titel" src="media/logo.jpg" style="width:100%"; height:auto;" />
	<style>
	/* hier CSS anlegen, Beispiel: */

	body { 
	    padding-left: 80px; 
	    padding-top:10px; 
	    line-height: 1.56em; 
	    }
	a {
	    display: none;
	    } 
	 h1 {font-size: 2.5em; font-color: #990000;}

	</style>
	';
	      // Dateiname 
	      $art_pdf_name =  rex_string::normalize(rex_article::getCurrent()->getValue('name'));
	      header('Content-Type: application/pdf');
	      $options = new Dompdf\Options();
	      $options->set('defaultFont', 'Helvetica');
	      $dompdf = new Dompdf\Dompdf($options);
	      $dompdf->loadHtml($pre.$pdfcontent.'</body>');
	      $dompdf->setPaper('A4', 'portrait');
	      $dompdf->render();
	      $dompdf->stream($art_pdf_name ,array('Attachment'=>false));
	      die();
  }
?>
```
___
## Tipps
- Es empfiehlt sich im verwendeten Template die CSS-Definitionen nicht als externe Dateien sondern inline zu hinterlegen. Dies beschleunigt die Generierung, da keine externen Ressourcen eingelesen werden müssen.
- Auf Bootsstrap CSS oder andere CSS-Frameworks bei der Ausgabe möglichst verzichten, da zuviele Styles abgearbeitet werden müssen. 
- URLs zu Ressourcen sollten ohne / beginnen und vom Webroot aus definiert sein z.B. media/zyz.jpg oder assets/css/pdf_styles.css. Ein Search & Replace per PHP kann hierbei helfen. https://github.com/FriendsOfREDAXO/pdfout/issues/2
- Fixierte Divs können zur Anzeige von Fuß und Kopfzeile verwendet werden. Ideal ist es diese direkt nach dem Bodytag zu integrieren. Dann können auch mittels CSS count z.B. Seitenzahlen ausgegegeben werden.
- Google Fonts zur lokalen Nutzung herunterladen: https://google-webfonts-helper.herokuapp.com/fonts

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
___
### Credits

- [dompdf](http://dompdf.github.io)
- [First release: Oliver Kreischer](https://github.com/olien)
- [FriendsOfREDAXO](https://github.com/FriendsOfREDAXO)
- [Thomas Skerbis](https://github.com/skerbis)

**Projekt-Lead**

[Simon Krull](https://github.com/crydotsnake)
