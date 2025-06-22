# Demo-System für PDFOut

## Übersicht

Das Demo-System von PDFOut ist modular aufgebaut. Jedes Demo ist in einer eigenen PHP-Datei definiert, die in das `pages/demos/` Verzeichnis gehört.

## Neue Demos hinzufügen

Um ein neues Demo hinzuzufügen, erstellen Sie einfach eine neue PHP-Datei im `pages/demos/` Verzeichnis:

### 1. Datei erstellen
```
pages/demos/mein_neues_demo.php
```

### 2. Demo-Konfiguration definieren
```php
<?php
/**
 * Beschreibung des Demos
 */

return [
    'title' => 'Titel des Demos',
    'description' => 'Beschreibung was das Demo macht. HTML ist erlaubt.',
    'panel_class' => 'panel-default', // oder panel-info, panel-warning, panel-danger
    'btn_class' => 'btn-default',     // oder btn-primary, btn-info, btn-warning, btn-danger  
    'icon' => 'fa-file-pdf-o',        // FontAwesome Icon
    'code' => 'PHP Code der angezeigt wird'
];
```

### 3. Verfügbarkeitsprüfung (optional)
Für Demos die spezielle Requirements haben:

```php
return [
    // ... andere Eigenschaften ...
    'availability_check' => '!extension_loaded("some_extension")',
    'availability_message' => 'Extension XYZ ist nicht installiert.',
];
```

## Demo-Reihenfolge

Die Standard-Demos werden in einer festen Reihenfolge geladen:
1. simple_pdf
2. signed_pdf  
3. password_pdf
4. full_featured_pdf
5. zugferd_pdf
6. pdf_import_demo

Alle zusätzlichen Demos werden danach in alphabetischer Reihenfolge angehängt.

## Beispiele

Schauen Sie sich die vorhandenen Demo-Dateien im `pages/demos/` Verzeichnis an:

- `simple_pdf.php` - Einfaches Beispiel
- `signed_pdf.php` - Mit Verfügbarkeitsprüfung  
- `example_watermark_pdf.php` - Beispiel für zusätzliches Demo

## Demo-Loader

Das System verwendet die `loadDemos()` Funktion in `pages/demo.php`, die automatisch alle PHP-Dateien aus dem `demos/` Verzeichnis lädt und in das Demo-System einbindet.