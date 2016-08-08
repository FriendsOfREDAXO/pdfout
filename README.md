## PDF out

Dieses Addon stellt den HTML to PDF converter DOMpdf (http://dompdf.github.io) zur Verfügung.

--

Nach der Installation und Aktivierung kann ein PDF wie folgt erzeugt werden. 
- Den nachfolgenden Code am Anfang des gewünschten Templates setzen 
- Der Aufruf erfolgt dann über die Variable pdf=1 die über die URL übergeben wird. 

Sofern dann an eine aufgerufenen URL **?pdf=1** angeängt wird wird der Inhalt von REX_ARTICLE[] oder REX_TEMPLATE [] als PDF ausgegeben.

	<?php
	  // ?pdf=1
	  $print_pdf = rex_request('pdf', 'int');
	  use Dompdf\Dompdf;
	  use Dompdf\Options;	
	  if ($print_pdf) {
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
### Tipps
- Es empfiehlt sich im verwendeten Template die CSS-Definitionen im Template zu hinterlegen. Dies beschleunigt die Generierung, da keine externen Ressourcen eingelesen werden müssen. 
- Auf Bootsstrap CSS oder andere CSS-Frameworks bei der Ausgabe möglichst verzichten, da zuviele Styles abgearbeitet werden müssen
- URLs zu Ressourcen sollten ohne / beginnen und vom Webroot aus definiert sein z.B. media/zyz.jpg oder assets/css/pdf_styles.css. Ein Search & Replace per PHP kann hierbei helfen. https://github.com/FriendsOfREDAXO/pdfout/issues/2
- Fixierte Divs können zur Anzeige von Fuß und Kopfzeile verwendet werden. Ideal ist es diese direkt nach dem Bodytag zu integrieren. Dann können auch mittels CSS count z.B. Seitenzahlen ausgegegeben werden. 


___
### ToDo

siehe [ISSUES](https://github.com/FriendsOfREDAXO/pdfout/issues/)

___
### Changelog

siehe [CHANGELOG.md](CHANGELOG.md)

___
### Lizenz

siehe [LICENSE.md](LICENSE.md)
