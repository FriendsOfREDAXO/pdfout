# REDAXO PdfOut - Demo Fragmente

Dieses Verzeichnis enthält bereinigte Demo-Implementierungen für das PdfOut AddOn.

## Status: Bereinigt ✅

Alle Demo-Implementierungen wurden **direkt in den Demo-Controller** (`pages/demo.php`) integriert.

### Verfügbare Demos:

1. **Einfaches PDF** - Basis-PDF ohne erweiterte Features
2. **PDF mit digitaler Signatur** - TCPDF-basierte Signierung (Total document signed)
3. **Nachträgliche PDF-Signierung** - FPDI+TCPDF für bestehende PDFs

### Architektur-Änderung:

- ❌ **Alt:** Externe Fragment-Dateien mit includes
- ✅ **Neu:** Direkte Integration im Switch-Case von `pages/demo.php`

### Vorteile der neuen Architektur:

- ✅ Keine kaputten Includes mehr
- ✅ Bessere Fehlerbehandlung
- ✅ Zentrale Demo-Logik
- ✅ Robuste PDF-Ausgabe
- ✅ Weniger Dateien zu verwalten

---

**Hinweis:** Sollten in der Zukunft Demo-Fragmente benötigt werden, können diese hier wieder erstellt werden. Die aktuelle Implementierung funktioniert jedoch vollständig ohne externe Fragmente.

1. **Erstelle eine neue PHP-Datei** in diesem Verzeichnis (z.B. `meine_demo.php`)
2. **Verwende das obige Format** für die Demo-Konfiguration
3. **Der Dateiname** (ohne .php) wird als Demo-Key verwendet
4. **Füge die Demo-Logik hinzu** in `pages/demo.php` im Switch-Case Block:

```php
case 'meine_demo':
    try {
        $pdf = new PdfOut();
        $pdf->setName('demo_meine_demo')
            ->setHtml('<h1>Meine Demo</h1><p>Inhalt der Demo.</p>')
            ->run();
    } catch (Exception $e) {
        $error = 'Fehler beim Erstellen der Demo: ' . $e->getMessage();
    }
    break;
```

5. **Die Demo erscheint automatisch** auf der Demo-Seite

## Verfügbare Demo-Keys

Die folgenden Demo-Keys werden in den Switch-Cases in `pages/demo.php` behandelt:
- `simple_pdf` - Einfaches PDF
- `signed_pdf` - Digital signiertes PDF 
- `password_pdf` - Passwortgeschütztes PDF
- `full_featured_pdf` - Vollausgestattetes PDF

## Beispiel: Neue Demo hinzufügen

1. **Erstelle `custom_demo.php`**:
```php
<?php
return [
    'title' => 'Meine Custom Demo',
    'description' => 'Ein Beispiel für eine benutzerdefinierte Demo.',
    'panel_class' => 'panel-success',
    'btn_class' => 'btn-success',
    'icon' => 'fa-magic',
    'code' => '$pdf = new PdfOut();
$pdf->setName(\'custom_demo\')
    ->setHtml(\'<h1>Custom Demo</h1><p>Benutzerdefinierter Inhalt</p>\')
    ->run();'
];
```

2. **Füge in `pages/demo.php` hinzu** (im switch-Block nach Zeile ~180):
```php
case 'custom_demo':
    try {
        $pdf = new PdfOut();
        $pdf->setName('custom_demo')
            ->setHtml('<h1>Custom Demo</h1><p>Benutzerdefinierter Inhalt</p>')
            ->run();
    } catch (Exception $e) {
        $error = 'Fehler beim Erstellen der Custom Demo: ' . $e->getMessage();
    }
    break;
```

Die Demo ist sofort verfügbar!