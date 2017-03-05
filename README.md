## PDF out

PDF out stellt den "HTML to PDF"-Converter dompdf (http://dompdf.github.io) in REDAXO zur Verfügung.
Mit dompdf können Ausgaben in REDAXO als PDF generiert werden. 
___

Nach der Installation und Aktivierung kann ein PDF wie folgt erzeugt werden:
- Den nachfolgenden Code am Anfang des gewünschten Templates oder als separates Template einsetzen
- Der Aufruf erfolgt dann über die Variable pdf=1 die über die URL übergeben wird. Der aktuelle Artikel kann so dann als PDF ausgegeben werden. 

Sofern dann an eine aufgerufenen URL **?pdf=1** angeängt wird, wird der Inhalt von REX_ARTICLE[] oder REX_TEMPLATE [] als PDF ausgegeben.


	<?php
	  // ?pdf=1
	  $print_pdf = rex_request('pdf', 'int');
	  if ($print_pdf) {
	  	 // Dompdf laden
		 use Dompdf\Dompdf;
	  	 use Dompdf\Options;
		  // Optionen festlegen
		  $pdf_options = new Options();
		  $pdf_options->setDpi(100); // legt die Dpi für das Dokument fest
		  $pdf_options->set('defaultFont', 'Helvetica'); // Standard-Font
		  // PDF erstellen
		  $art_pdf_name =  rex_string::normalize(rex_article::getCurrent()->getValue('name'));
		  header('Content-Type: application/pdf');
		  $dompdf = new Dompdf($pdf_options);
		  $dompdf->loadHtml('REX_ARTICLE[]');
		  // Hinweis: Anstelle von REX_ARTICLE[] kann auch ein gestaltetes Template REX_TEMPLATE[XX] angegeben werden
		  $dompdf->setPaper('A4', 'portrait');
		  $dompdf->render();
		  $dompdf->stream($art_pdf_name ,array('Attachment'=>false)); // bei true wird Download erzwungen
		  die();
		}
	?>



___
## Tipps
- Es empfiehlt sich im verwendeten Template die CSS-Definitionen nicht als externe Dateien sondern inline zu hinterlegen. Dies beschleunigt die Generierung, da keine externen Ressourcen eingelesen werden müssen.
- Auf Bootsstrap CSS oder andere CSS-Frameworks bei der Ausgabe möglichst verzichten, da zuviele Styles abgearbeitet werden müssen. 
- URLs zu Ressourcen sollten ohne / beginnen und vom Webroot aus definiert sein z.B. media/zyz.jpg oder assets/css/pdf_styles.css. Ein Search & Replace per PHP kann hierbei helfen. https://github.com/FriendsOfREDAXO/pdfout/issues/2
- Fixierte Divs können zur Anzeige von Fuß und Kopfzeile verwendet werden. Ideal ist es diese direkt nach dem Bodytag zu integrieren. Dann können auch mittels CSS count z.B. Seitenzahlen ausgegegeben werden.
- Google Fonts zur lokalen Nutzung herunterladen: https://google-webfonts-helper.herokuapp.com/fonts



### Font-Awsome einbinden: 
Font-Awsome fonts werden nicht korrekt dargestellt. 
Folgender Workarround hilft: 
Einbindung z.B. CDN im Template

Zusätzlichen Stil in Style-Tag inline einfügen: 
		
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.6.3/css/font-awesome.min.css">```
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

___
### Credits

- [dompdf](http://dompdf.github.io)
- [FriendsOfREDAXO](https://github.com/FriendsOfREDAXO)

**Projekt-Lead**

[Thomas Skerbis](https://github.com/skerbis)
