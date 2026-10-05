# pdfout

Das PDF-Werkzeug für REDAXO:

- **Erzeugen** – HTML und REDAXO-Artikel als PDF ([dompdf](https://github.com/dompdf/dompdf))
- **Bearbeiten** – zusammenführen, Seiten auswählen, Stempel, Seitenzahlen, Metadaten ([tc-lib-pdf](https://github.com/tecnickcom/tc-lib-pdf))
- **Absichern** – digitale Signatur nach PAdES, Passwortschutz mit AES-256
- **Prüfen** – Signaturen, Metadaten und Text auslesen ([Poppler](https://poppler.freedesktop.org/))
- **Anzeigen** – [PDF.js](https://github.com/mozilla/pdf.js)-Viewer mit einstellbarer Leiste und Editor
- **Vorschaubilder** – Media-Manager-Effekt für PDF-Thumbnails

## Inhalt

- [Installation und Voraussetzungen](#installation-und-voraussetzungen)
- [Schnellstart](#schnellstart)
- [PDF aus HTML erzeugen](#pdf-aus-html-erzeugen)
- [PDFs bearbeiten](#pdfs-bearbeiten)
- [Signieren](#signieren)
- [Passwortschutz](#passwortschutz)
- [Prüfen und auslesen](#prüfen-und-auslesen)
- [Anzeigen im PDF.js-Viewer](#anzeigen-im-pdfjs-viewer)
- [Vorschaubilder](#vorschaubilder)
- [Backend](#backend)
- [Umstieg von Version 10](#umstieg-von-version-10)
- [Entwicklung](#entwicklung)
- [Bibliotheken und Lizenzen](#bibliotheken-und-lizenzen)

Alle Klassen und Methoden im Detail: [API-Referenz](API.md). Tipps für den Einsatz: [Best Practices](BEST_PRACTICES.md).

## Installation und Voraussetzungen

Installation über den REDAXO-Installer oder von [GitHub](https://github.com/FriendsOfREDAXO/pdfout).

- PHP 8.4 oder neuer mit den Erweiterungen dom, mbstring, gd, openssl, zlib und der Funktion `proc_open()`
- REDAXO 5.15 oder neuer
- **poppler-utils** (`pdfinfo`, `pdfsig`, `pdftoppm`, `pdftotext`)

Fehlt Poppler, bricht die Installation mit einem Hinweis ab.

| System | Befehl |
| --- | --- |
| Debian, Ubuntu | `apt install poppler-utils` |
| RHEL, CentOS, Fedora | `dnf install poppler-utils` |
| Alpine | `apk add poppler-utils` |
| Arch | `pacman -S poppler` |
| openSUSE | `zypper install poppler-tools` |
| macOS | `brew install poppler` |

Liegen die Programme außerhalb des Suchpfads, den Ordner vor der Installation setzen und später unter *Einstellungen → Allgemein* pflegen:

```bash
php bin/console config:set --type=string pdfout poppler_path /pfad/zu/bin
```

## Schnellstart

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

PdfOut::create()
    ->html('<h1>Hallo REDAXO</h1><p>Mein erstes PDF.</p>')
    ->inline('hallo.pdf');
```

Die Beispiele verwenden diese Klassen:

```php
use FriendsOfRedaxo\PdfOut\{PdfOut, PdfDocument, Certificate, SignatureField, Permission};
```

## PDF aus HTML erzeugen

`PdfOut` wandelt HTML oder Artikel mit dompdf in ein PDF um. Papierformat, Schrift und weitere Vorgaben kommen aus den Einstellungen und lassen sich je Aufruf ändern.

```php
PdfOut::create()
    ->article(5)                          // Inhalt eines Artikels (oder ->html($html))
    ->paper('A4', 'landscape')
    ->font('Dejavu Sans')
    ->template($vorlage, '{{CONTENT}}')   // Grundtemplate mit Platzhalter
    ->download('artikel.pdf');
```

Ausgabe:

| Methode | Ergebnis |
| --- | --- |
| `toString()` | PDF-Daten als String |
| `save($pfad, overwrite: true)` | Datei speichern, gibt den Pfad zurück |
| `inline($name)` | im Browser anzeigen |
| `download($name)` | als Download senden |
| `document()` | `PdfDocument` zum Weiterbearbeiten |

### Vorlagen und Seitenzahlen

```php
$vorlage = '<!DOCTYPE html>
<html><head><style>
    @page { margin: 2cm 2cm 2.5cm; }
    body { font-family: "Dejavu Sans", sans-serif; font-size: 11pt; }
    .footer { position: fixed; bottom: -1.5cm; width: 100%; text-align: center; font-size: 9pt; }
    .seite:before { content: counter(page); }
</style></head>
<body>
    <div class="footer">Seite <span class="seite"></span> von DOMPDF_PAGE_COUNT_PLACEHOLDER</div>
    {{CONTENT}}
</body></html>';

PdfOut::create()->template($vorlage)->html($inhalt)->inline('bericht.pdf');
```

`DOMPDF_PAGE_COUNT_PLACEHOLDER` wird durch die Gesamtseitenzahl ersetzt. Alternativ setzt `PdfDocument::pageNumbers()` die Seitenzahlen nachträglich (siehe unten).

### Bilder

dompdf lädt Bilder über ihre Adresse – also absolute Adressen verwenden oder Dateien aus dem Medienpool direkt einbinden:

```php
// Media-Manager-Bild mit absoluter Adresse
$logo = rtrim(rex::getServer(), '/') . rex_media_manager::getUrl('rex_media_medium', 'logo.png');
$html = '<img src="' . rex_escape($logo) . '" alt="Logo">';
```

`PdfOut::mediaUrl($typ, $datei)` liefert die Adresse absolut, wenn die Addon-Eigenschaft `aspdf` gesetzt ist (`rex_addon::get('pdfout')->setProperty('aspdf', true)`) oder die Anfrage `?pdfout=1` enthält – praktisch in Modulen, die sowohl im Browser als auch im PDF ausgegeben werden. Externe Adressen lädt dompdf nur, wenn „entfernte Dateien“ in den Einstellungen erlaubt sind.

## PDFs bearbeiten

`PdfDocument` bearbeitet jedes PDF – frisch erzeugt, aus dem Medienpool oder aus einer Datei. Die Schritte werden gesammelt und beim Ausgeben in einem Durchgang angewendet.

```php
PdfDocument::fromMedia('preisliste.pdf')
    ->append(PdfDocument::fromMedia('agb.pdf'))
    ->pages('1-3,-1')                                 // Seiten 1 bis 3 und die letzte
    ->stamp('ENTWURF', opacity: 0.15)
    ->pageNumbers('Seite {page} von {pages}', position: 'bottom-right')
    ->metadata(title: 'Preisliste 2027', author: 'Hotel')
    ->save(rex_path::addonData('mein_addon', 'preisliste.pdf'));
```

| Quelle | |
| --- | --- |
| `PdfDocument::fromMedia($datei)` | Medienpool |
| `PdfDocument::fromFile($pfad)` | Datei |
| `PdfDocument::fromString($daten)` | PDF-Daten |
| `PdfDocument::fromHtml($html)` | HTML über `PdfOut` |

Direkt aus `PdfOut` heraus:

```php
PdfOut::create()
    ->html($angebot)
    ->append(rex_path::media('agb.pdf'))
    ->with(fn (PdfDocument $doc) => $doc->pageNumbers()->stamp('ENTWURF'))
    ->download('angebot.pdf');
```

Wichtig:

- Ohne Bearbeitungsschritt bleibt das PDF byte-identisch.
- Beim Bearbeiten werden die Seiten neu aufgebaut. Links, Formularfelder, Lesezeichen und eine vorhandene Tag-Struktur (Barrierefreiheit) gehen dabei verloren.

## Signieren

```php
PdfOut::create()
    ->html($rechnung)
    ->sign(
        Certificate::fromAddon('firma.p12', $passwort),
        reason: 'Rechnung 4711',
        location: 'Moers',
        field: SignatureField::bottomRight(),   // sichtbar; ohne field unsichtbar
    )
    ->save($pfad);

// vorhandenes PDF signieren
PdfDocument::fromFile($pfad)->sign(Certificate::fromAddon())->save($ziel);
```

- Signatur nach PAdES-B-B mit SHA-256 (`ETSI.CAdES.detached`)
- Zertifikate: `Certificate::fromAddon()` (verwaltet unter *Einstellungen → Zertifikate*), `fromP12()`, `fromPem()`, `fromFile()`. Passwort und Schlüssel werden beim Laden geprüft.
- `SignatureField::at($x, $y, $breite, $hoehe, $seite)` platziert das Feld frei – Millimeter ab links oben, Seite `-1` ist die letzte. Der Kasten zeigt Name, Datum, Ort und Grund; `->withoutBox()` setzt nur das Feld.
- Selbst ausgestellte Zertifikate erscheinen in PDF-Readern als „Aussteller unbekannt“; die Unversehrtheit des Dokuments ist trotzdem prüfbar. Für vertrauenswürdige Signaturen ein Zertifikat einer anerkannten Stelle verwenden.

## Passwortschutz

```php
PdfOut::create()
    ->html($bericht)
    ->protect('lesen', 'verwalten', allow: [Permission::Print, Permission::Copy])
    ->download('bericht.pdf');
```

- Verschlüsselung mit AES-256
- `allow` nennt die **erlaubten** Rechte: `Print`, `PrintHigh`, `Copy`, `Modify`, `Annotate`, `FillForms`, `Assemble`. Das Auslesen für Screenreader bleibt immer erlaubt.
- Ohne Besitzer-Passwort wird ein zufälliges gesetzt (die Rechte lassen sich dann nicht mehr ändern).
- Leeres Benutzer-Passwort: Das PDF öffnet ohne Passwort, die Rechte gelten trotzdem.

## Prüfen und auslesen

```php
$doc = PdfDocument::fromMedia('vertrag.pdf');

$doc->pageCount();   // 12
$doc->info();        // ['Title' => …, 'Pages' => '12', 'Page size' => '595 x 842 pts (A4)', 'Encrypted' => 'no', …]
$doc->text();        // Text des Dokuments

foreach ($doc->signatures() as $signatur) {
    echo $signatur['signer'], ': ', $signatur['valid'] ? 'gültig' : 'ungültig';
}
```

Die Signaturprüfung nutzt `pdfsig`: Unversehrtheit, Hash-Verfahren, Typ, Zertifikatsstatus und ob die Signatur das ganze Dokument abdeckt. Für einzelne Poppler-Aufrufe gibt es die Klasse `Poppler` (`info()`, `signatures()`, `text()`, `run()`).

## Anzeigen im PDF.js-Viewer

```php
// Viewer-Adresse für eine Datei
$url = PdfOut::viewer(rex_url::media('preisliste.pdf'));

// mit Knopf „Zurück“ (z. B. wenn der Viewer als eigene Seite öffnet)
$url = PdfOut::viewer(rex_url::media('preisliste.pdf'), rex_getUrl());

// mit einem bestimmten Toolbar-Profil
$url = PdfOut::viewerWithProfile(rex_url::media('preisliste.pdf'), 'lesemodus');
```

Die Leiste des Viewers wird unter *Einstellungen → PDF.js-Toolbar* eingestellt: Voreinstellung (vollständig, ausgewogen, kompakt) oder einzelne Gruppen ausblenden, als globaler Standard oder als benanntes Profil. `viewer()` nutzt das aktive Profil, sonst den Standard.

Der Viewer ist der Legacy-Build von PDF.js und läuft damit auch in älteren Browsern, etwa auf älteren iPhones. Die Module liegen als `.js` vor, damit auch Server ohne `.mjs`-Dateityp sie korrekt ausliefern.

## Vorschaubilder

Der Media-Manager-Effekt **„PDF-Thumbnail (pdfout)“** erzeugt Vorschaubilder mit `pdftoppm`. Er ist nicht von der ImageMagick-Sperre für PDFs betroffen, die viele Linux-Systeme setzen.

1. Unter *Media Manager* einen Typ anlegen, z. B. `pdf_thumb`
2. Effekt „PDF-Thumbnail (pdfout)“ hinzufügen, danach z. B. `resize`
3. Ausgeben: `rex_media_manager::getUrl('pdf_thumb', 'dokument.pdf')`

Einstellungen des Effekts: Format (PNG, JPG), Auflösung, Qualität, Seite, Hintergrundfarbe, Gamma (1.2 entspricht etwa der Darstellung in PDF-Viewern) und optional ein eingebettetes sRGB-Profil (benötigt Imagick; ein Profil liegt pdfout bei).

Direkt im Code:

```php
use FriendsOfRedaxo\PdfOut\PdfThumbnail;

$bild = (new PdfThumbnail())
    ->setFormat('png')
    ->setDpi(150)
    ->setPage(1)
    ->setMaxWidth(800)
    ->generate(rex_path::media('dokument.pdf'));   // Dateipfad; auch generateAsString(), generateAsGdImage()
```

Reihenfolge der Werkzeuge: `pdftoppm`, `pdftocairo`, Ghostscript, Imagick.

## Backend

| Bereich | Inhalt |
| --- | --- |
| **Übersicht** | Status (Versionen, Poppler, Zertifikate), Schnellstart |
| **Werkzeuge → Bearbeiten** | PDFs aus dem Medienpool oder per Upload zusammenführen, Seiten auswählen, stempeln, nummerieren, signieren, schützen; herunterladen oder in den Medienpool speichern |
| **Werkzeuge → Editor** | PDF aus dem Medienpool im PDF.js-Editor öffnen: Text, Zeichnungen, Unterschriften, Bilder und Markierungen einfügen; als neue Datei oder als Ersatz speichern. Vorhandener Text und Alt-Texte lassen sich nicht ändern. |
| **Werkzeuge → Prüfen** | Metadaten, Signaturen, Text und Vorschau eines PDFs |
| **Einstellungen** | Allgemein (Vorgaben, Poppler-Ordner), Zertifikate (anlegen, hochladen, Standard), PDF.js-Toolbar |
| **Hilfe** | diese Dokumentation, API-Referenz, Best Practices, Demos mit ausführbaren Beispielen |

Rechte für Redakteure: `pdfout[tools]` (Bearbeiten, Editor), `pdfout[demo]`, `pdfout[certificates]`, `pdfout[config]`. Prüfen und Übersicht stehen allen mit `pdfout[]` offen.

## Umstieg von Version 10

Die bisherigen Methoden bleiben erhalten und nutzen intern die neue Technik.

| bisher | neue Schreibweise |
| --- | --- |
| `setHtml()`, `setName()`, `run()` | `PdfOut::create()->html()->inline()` |
| `enableDigitalSignature()`, `setVisibleSignature()` | `->sign(Certificate::…, field: SignatureField::…)` |
| `enablePasswordProtection()` | `->protect('pw', allow: [Permission::Print])` |
| `signExistingPdf()` | `PdfDocument::fromFile()->sign()->save()` |
| `createSignedWorkflow()`, `createSignedDocument()` | `->sign()->save()` |
| `createPasswordProtectedWorkflow()`, `createPasswordProtectedDocument()` | `->protect()->save()` |
| `mergePdfs()`, `mergeHtmlToPdf()` | `PdfDocument::…->append()` |
| `createWithAppendedPdfs()`, `createDocumentWithAttachments()` | `->append()` |
| `validateSignedPdf()` | `->signatures()` |
| `generateSignedPdf()`, `generateProfessionalSignedPdf()` | `->sign()` |
| `generateCleanSignedPdf()` | `->sign()->toString()` |

Was sich ändert:

- PHP 8.4 ist Mindestversion, Poppler ist Voraussetzung.
- TCPDF und FPDI sind entfernt. Wer sie im eigenen Code direkt nutzt, bindet sie selbst per Composer ein oder stellt auf `PdfDocument` um.
- Entfernte geschützte Methoden (nur für Unterklassen relevant): `runWithTcpdf()`, `addDigitalSignature()`, `addDigitalSignatureFinal()`, `addPasswordProtection()`, `processTcpdfOutput()`, `drawSignatureArea()`, `addSignatureAreaToFpdi()`, `addCleanSignatureArea()`.
- Passwortschutz: Die Liste nennt die erlaubten Rechte, wie dokumentiert. Bisher wurden die genannten Rechte versehentlich gesperrt – mit `['print']` war Drucken verboten.
- `validateSignedPdf()` prüft jetzt wirklich (bisher wurde immer „gültig“ gemeldet).
- Sichtbare Signatur: Position und Größe in Millimetern ab links oben.
- Signaturen nach PAdES (`ETSI.CAdES.detached`) statt `adbe.pkcs7.detached`.
- Backend-Adressen: Seiten liegen jetzt unter `pdfout/tools/…`, `pdfout/settings/…` und `pdfout/help/…`.

## Entwicklung

PDF.js aktualisieren (lädt den Legacy-Build und stellt die Module auf `.js` um):

```bash
./scripts/update-pdfjs.sh            # neueste Version
./scripts/update-pdfjs.sh 6.4.299    # bestimmte Version
```

Einstellungen dafür in `package.json` unter `pdfjs` (`build`: `legacy` oder `modern`, `moduleExtension`: `js` oder `mjs`). Ausführlich: [PDFJS_UPDATE.md](PDFJS_UPDATE.md).

Schriften für tc-lib-pdf erzeugen (nach `composer install`; mitgeliefert werden die Standard-Schriften unter `fonts/`):

```bash
php scripts/build-fonts.php core
```

Composer-Abhängigkeiten werden für PHP 8.4 aufgelöst (`config.platform`), `vendor/` wird mit dem Addon ausgeliefert.

## Bibliotheken und Lizenzen

| Bibliothek | Lizenz | Zweck |
| --- | --- | --- |
| [dompdf](https://github.com/dompdf/dompdf) mit php-font-lib, php-svg-lib, php-css-parser, html5-php | LGPL 2.1 / MIT | HTML zu PDF |
| [tc-lib-pdf](https://github.com/tecnickcom/tc-lib-pdf) | LGPL 3 | Bearbeiten, Signatur, Verschlüsselung |
| [PDF.js](https://github.com/mozilla/pdf.js) | Apache 2.0 | Viewer und Editor |
| [Poppler](https://poppler.freedesktop.org/) | GPL 2 | Lesen, Prüfen, Vorschaubilder – als Programm aufgerufen, nicht mitgeliefert |

pdfout selbst steht unter der [MIT-Lizenz](LICENSE.md).

## Support und Credits

- [GitHub Issues](https://github.com/FriendsOfREDAXO/pdfout/issues)
- [REDAXO-Slack](https://friendsofredaxo.slack.com/)

**Friends Of REDAXO** – https://github.com/FriendsOfREDAXO
Projektleitung: [Thomas Skerbis](https://github.com/skerbis)
Erste Version: [Oliver Kreischer](https://github.com/olien)

Sponsoren Version 10: [Alexander Walther](https://github.com/alxndr-w), [FVN e.V.](https://fvn.de), [WDFV e.V.](https://wdfv.de)
