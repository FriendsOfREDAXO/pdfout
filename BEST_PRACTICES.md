# Best Practices

Bewährte Vorgehensweisen für pdfout 11. Alle Beispiele setzen voraus:

```php
use FriendsOfRedaxo\PdfOut\{PdfOut, PdfDocument, Certificate, SignatureField, Permission};
```

## Grundsätze

**Eine Kette vom Inhalt bis zur Ausgabe.** Inhalt, Weiterverarbeitung und Ausgabe in einem Ausdruck halten das Vorgehen lesbar:

```php
PdfOut::create()
    ->html($html)
    ->sign(Certificate::fromAddon(), reason: 'Freigabe')
    ->protect('lesen', 'verwalten', allow: [Permission::Print])
    ->download('dokument.pdf');
```

**Vorhandene PDFs mit `PdfDocument` bearbeiten**, nicht neu erzeugen:

```php
PdfDocument::fromMedia('vertrag.pdf')->stamp('ENTWURF')->pageNumbers()->inline();
```

**Nur bearbeiten, was nötig ist.** Ohne Bearbeitungsschritt bleibt ein PDF unverändert. Jeder Schritt baut die Seiten neu auf – dabei gehen Links, Formularfelder, Lesezeichen und eine Tag-Struktur (Barrierefreiheit) verloren. Barrierefreie oder interaktive PDFs daher nicht stempeln oder zusammenführen.

**Bibliotheken nicht direkt ansprechen.** dompdf- und tc-lib-pdf-Interna ändern sich bei Updates. Fehlt eine Funktion, ein Issue anlegen.

**Fehler gezielt behandeln:**

```php
try {
    $pfad = PdfOut::create()
        ->html($html)
        ->sign(Certificate::fromAddon('firma.p12', $passwort))
        ->save($ziel);
} catch (InvalidArgumentException $e) {
    // Eingabe falsch: Zertifikat oder Passwort, Seitenauswahl, kein PDF …
    rex_logger::logException($e);
} catch (RuntimeException $e) {
    // Verarbeitung fehlgeschlagen
    rex_logger::logException($e);
}
```

## HTML und CSS für dompdf

dompdf unterstützt CSS 2.1 und Teile von CSS 3. Bewährt:

```css
@page { margin: 2cm 2cm 2.5cm; size: A4 portrait; }
body { font-family: "Dejavu Sans", sans-serif; font-size: 11pt; line-height: 1.4; }
h1, h2, h3 { page-break-after: avoid; }
table, figure, .zusammen { page-break-inside: avoid; }
.neue-seite { page-break-before: always; }
.footer { position: fixed; bottom: -1.5cm; left: 0; right: 0; text-align: center; font-size: 9pt; }
```

- **Schriften:** „Dejavu Sans“ deckt Umlaute und Sonderzeichen ab. Eigene Schriften per `@font-face` mit absolutem Pfad oder absoluter Adresse.
- **Layout:** Tabellen und Blöcke statt Flexbox und Grid – beides unterstützt dompdf nicht.
- **Bilder:** absolute Adressen oder Pfade, passende Größe vorab über den Media Manager (große Bilder verlangsamen die Erzeugung).
- **Seitenzahlen:** `DOMPDF_PAGE_COUNT_PLACEHOLDER` im HTML oder nachträglich `->with(fn ($d) => $d->pageNumbers())`.

## Vorlagen

Wiederkehrendes Layout (Kopf, Fuß, Schriften) in eine Vorlage mit Platzhalter auslagern, z. B. als Datei im Projekt-Addon:

```php
$vorlage = rex_file::get(rex_path::addon('project', 'pdf/vorlage.html'));

PdfOut::create()
    ->template($vorlage, '{{CONTENT}}')
    ->article($artikelId)
    ->download('artikel.pdf');
```

## Signaturen

- **Zertifikat:** Für Dokumente nach außen ein Zertifikat einer anerkannten Stelle verwenden. Selbst ausgestellte Zertifikate (Seite *Zertifikate*) eignen sich für interne Zwecke und Tests – PDF-Reader zeigen „Aussteller unbekannt“.
- **Ablage:** Zertifikate liegen in `data/addons/pdfout/certificates/`, außerhalb des Web-Roots. Nie im öffentlichen Ordner ablegen.
- **Passwort:** nicht im Code. Umgebungsvariable oder Addon-Konfiguration nutzen – die REDAXO-Konfiguration liegt unverschlüsselt in der Datenbank.
- **Ablauf überwachen:**

```php
$zertifikat = Certificate::fromAddon('firma.p12', getenv('PDF_CERT_PASSWORD') ?: null);
if ($zertifikat->validTo() < new DateTimeImmutable('+30 days')) {
    rex_logger::factory()->warning('Signatur-Zertifikat läuft ab am ' . $zertifikat->validTo()->format('d.m.Y'));
}
```

- **Signieren als letzter Schritt.** Jede spätere Änderung macht die Signatur ungültig – auch Anmerkungen aus dem Editor. `PdfDocument` und `PdfOut` signieren automatisch zuletzt.
- **Prüfen:** Nach dem Erzeugen mit `->signatures()` oder auf der Seite *Werkzeuge → Prüfen* kontrollieren.

## Passwortschutz

- `allow` nennt die erlaubten Rechte. Nur freigeben, was gebraucht wird – meist `Permission::Print`.
- Ein eigenes Besitzer-Passwort setzen, wenn die Rechte später geändert werden sollen; sonst wird ein zufälliges vergeben.
- Passwortschutz ist kein Kopierschutz im strengen Sinn: Rechte werden von seriösen Readern beachtet, lassen sich aber umgehen. Vertrauliches zusätzlich mit einem Benutzer-Passwort verschlüsseln.

## Leistung

- **DPI nach Zweck:** 96–100 für Bildschirm, 150 für Büro-Druck, 300 nur für hochwertigen Druck (Dateigröße und Laufzeit steigen deutlich).
- **Erzeugte PDFs zwischenspeichern**, wenn sich der Inhalt selten ändert:

```php
$datei = rex_path::addonCache('project', 'preisliste-' . $artikel->getUpdatedate() . '.pdf');
if (!is_file($datei)) {
    PdfOut::create()->article($artikel->getId())->save($datei);
}
PdfDocument::fromFile($datei)->inline('preisliste.pdf');
```

- **Entfernte Ressourcen** nur erlauben, wenn nötig (Einstellung „entfernte Dateien“) – jede externe Adresse wird beim Erzeugen geladen.

## Ausgabe

- `inline()` und `download()` senden das PDF und beenden die Anfrage. Davor keine Ausgabe erzeugen; in Modulen besser einen eigenen Endpunkt (z. B. `rex_api_function`) nutzen.
- Dateinamen ohne Pfad und Sonderzeichen übergeben; pdfout bereinigt sie zusätzlich.
- Für die Anzeige im Browser den Viewer nutzen: `PdfOut::viewer(rex_url::media($datei))`. Auf iPhone und iPad den Viewer als eigene Seite öffnen (mit `returnUrl` für den Rückweg), nicht im iframe.

## Typische Probleme

| Problem | Lösung |
| --- | --- |
| Bilder fehlen | Adresse absolut? Datei erreichbar? Bei externen Adressen „entfernte Dateien“ erlauben |
| Umlaute als Fragezeichen | Schrift „Dejavu Sans“ verwenden oder eigene Unicode-Schrift einbinden |
| Layout bricht | Flexbox/Grid durch Tabellen oder Blöcke ersetzen, `position: fixed` nur für Kopf und Fuß |
| Erzeugung langsam | DPI senken, Bilder verkleinern, Ergebnis zwischenspeichern |
| „Poppler-Programm nicht gefunden“ | poppler-utils installieren oder Ordner unter *Einstellungen → Allgemein* eintragen |
| Signatur „ungültig“ | Wurde das PDF nach dem Signieren geändert? Signieren als letzten Schritt |
| Viewer bleibt leer | Server liefert `.js` mit falschem Typ? Browser-Konsole prüfen |

Für die Fehlersuche in den Einstellungen den Debug-Modus und das Protokollieren der Erzeugung aktivieren – nicht im Live-Betrieb.
