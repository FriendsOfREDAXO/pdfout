# Demo Fragments

Dieser Ordner enthält die modularen Demo-Definitionen für PDFOut.

## Struktur

Jede Demo wird in einer separaten PHP-Datei definiert, die ein Array mit der Demo-Konfiguration zurückgibt.

## Demo-Datei Format

```php
<?php
/**
 * Demo Name - Demo Configuration
 */

return [
    'title' => 'Demo Titel',
    'description' => 'Beschreibung der Demo-Funktionalität',
    'panel_class' => 'panel-default',  // Bootstrap Panel-Klasse
    'btn_class' => 'btn-default',      // Bootstrap Button-Klasse
    'icon' => 'fa-icon-name',          // FontAwesome Icon
    'code' => '$pdf = new PdfOut();    // PHP-Code für die Demo
$pdf->setName(\'demo_name\')
    ->setHtml(\'<h1>Demo</h1>\')
    ->run();'
];
```

## Neue Demo hinzufügen

1. Erstelle eine neue PHP-Datei in diesem Verzeichnis (z.B. `meine_demo.php`)
2. Verwende das obige Format für die Demo-Konfiguration
3. Der Dateiname (ohne .php) wird als Demo-Key verwendet
4. Die Demo erscheint automatisch auf der Demo-Seite

## Verfügbare Demo-Keys

Die folgenden Demo-Keys werden in den Switch-Cases in `pages/demo.php` behandelt:
- `simple_pdf` - Einfaches PDF
- `signed_pdf` - Digital signiertes PDF
- `password_pdf` - Passwortgeschütztes PDF
- `full_featured_pdf` - Vollausgestattetes PDF

Für neue Demos muss ein entsprechender Case in der Switch-Anweisung hinzugefügt werden.