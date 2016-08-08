## PDF out

Dieses Addon stellt den HTML to PDF converter DOMpdf (http://dompdf.github.io) zur Verfügung.

--

Nach der Installation und Aktivierung des Addons kann folgendes am Anfang des
Ausgabe Templates eingegeben werden:

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
		  $dompdf->setPaper('A4', 'portrait');
		  $dompdf->render();
		  $dompdf->stream($art_pdf_name ,array('Attachment'=>false)); // bei true wird Download erzwungen
		  die();
		}
	?>

Sofern dann an eine aufgerufenen URL **?pdf=1** angeängt wird wird der Inhalt von REX_ARTICLE[] als PDF ausgegeben.


___
### ToDo

siehe [ISSUES](https://github.com/FriendsOfREDAXO/pdfout/issues/)

___
### Changelog

siehe [CHANGELOG.md](CHANGELOG.md)

___
### Lizenz

siehe [LICENSE.md](LICENSE.md)
