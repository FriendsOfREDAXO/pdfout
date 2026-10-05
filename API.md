# API-Referenz (pdfout 11)

Alle Klassen liegen im Namespace `FriendsOfRedaxo\PdfOut`.

```php
use FriendsOfRedaxo\PdfOut\{PdfOut, PdfDocument, Certificate, SignatureField, Permission, Poppler};
```

| Klasse | Aufgabe |
| --- | --- |
| `PdfOut` | HTML und REDAXO-Artikel in ein PDF umwandeln (dompdf), Weiterverarbeitung verketten |
| `PdfDocument` | jedes PDF bearbeiten, absichern, ausgeben und lesen |
| `Certificate` | Signatur-Zertifikat laden (P12/PFX oder PEM) |
| `SignatureField` | sichtbares Signaturfeld platzieren |
| `Permission` | erlaubte Rechte bei Passwortschutz (Enum) |
| `Poppler` | PDFs lesen und prüfen (pdfinfo, pdfsig, pdftotext, pdftoppm) |
| `PdfThumbnail` | Vorschaubilder (auch als Media-Manager-Effekt „PDF-Thumbnail“) |

---

## PdfOut

```php
PdfOut::create()                       // neue Instanz mit den Standard-Einstellungen des Addons
```

### Inhalt und Format

| Methode | Beschreibung |
| --- | --- |
| `html(string $html, bool $outputFilter = false)` | HTML setzen (optional durch den OUTPUT_FILTER) |
| `article(int $id, ?int $ctype = null, bool $outputFilter = true)` | Inhalt eines Artikels anhängen |
| `paper(string\|array $size = 'A4', string $orientation = 'portrait')` | Papierformat, z. B. `'A4'`, `'letter'` oder `[0, 0, Breite, Höhe]` in Punkt |
| `font(string $font)` | Grundschrift (dompdf), Standard „Dejavu Sans“ |
| `template(string $template, string $placeholder = '{{CONTENT}}')` | Grundtemplate, der Inhalt ersetzt den Platzhalter |

Im HTML kann `DOMPDF_PAGE_COUNT_PLACEHOLDER` für die Gesamtseitenzahl stehen.

### Weiterverarbeitung

| Methode | Beschreibung |
| --- | --- |
| `sign(?Certificate $c = null, string $name = '', string $reason = '', string $location = '', string $contact = '', ?SignatureField $field = null)` | signieren (ohne Zertifikat: Standard des Addons) |
| `protect(string $userPassword = '', ?string $ownerPassword = null, iterable $allow = [Permission::Print, Permission::PrintHigh])` | Passwortschutz (AES-256) |
| `append(PdfDocument\|string ...$docs)` | PDFs anhängen (Dateipfad, PDF-Daten oder `PdfDocument`) |
| `with(callable $step)` | beliebiger Schritt auf dem `PdfDocument`, z. B. `fn (PdfDocument $d) => $d->stamp('ENTWURF')` |
| `document(): PdfDocument` | fertiges Dokument zum Weiterbearbeiten |

### Ausgabe

| Methode | Rückgabe |
| --- | --- |
| `toString(): string` | PDF-Daten |
| `save(string $path, bool $overwrite = true): string` | Pfad der gespeicherten Datei |
| `inline(?string $filename = null): never` | im Browser anzeigen |
| `download(?string $filename = null): never` | als Download senden |

### Viewer und Medien

| Methode | Beschreibung |
| --- | --- |
| `PdfOut::viewer(string $file, string $returnUrl = '')` | URL des PDF.js-Viewers; `$returnUrl` zeigt einen Knopf „Zurück“ (gleiche Domain) |
| `PdfOut::viewerWithProfile(string $file, string $profile)` | Viewer mit einem bestimmten Toolbar-Profil |
| `PdfOut::mediaUrl(string $type, string $file)` | Media-Manager-URL, beim PDF-Erzeugen absolut |

### Bisherige Methoden

Weiter nutzbar, intern auf `PdfDocument` umgestellt: `setName()`, `setHtml()`, `setPaperSize()`, `setOrientation()`, `setFont()`, `setDpi()`, `setAttachment()`, `setRemoteFiles()`, `setSaveToPath()`, `setSaveAndSend()`, `setBaseTemplate()`, `addArticle()`, `run()`, `enableDigitalSignature()`, `setVisibleSignature()`, `enablePasswordProtection()`, `signExistingPdf()`, `generateSignedPdf()`, `generateProfessionalSignedPdf()`, `generateCleanSignedPdf()`, `validateSignedPdf()`, `createSignedWorkflow()`, `createSignedDocument()`, `createPasswordProtectedWorkflow()`, `createPasswordProtectedDocument()`, `mergePdfs()`, `mergeHtmlToPdf()`, `createWithAppendedPdfs()`, `createDocumentWithAttachments()`. Siehe README, Abschnitt „Umstieg von Version 10“.

---

## PdfDocument

Schritte werden gesammelt und beim Ausgeben in einem Durchgang angewendet. Ohne Schritt bleibt das PDF byte-identisch; beim Bearbeiten werden die Seiten neu aufgebaut (Links, Formularfelder und Lesezeichen gehen verloren).

### Quellen

| Methode | Beschreibung |
| --- | --- |
| `PdfDocument::fromMedia(string $filename)` | Datei aus dem Medienpool |
| `PdfDocument::fromFile(string $path)` | Datei |
| `PdfDocument::fromString(string $pdf, string $filename = 'document.pdf')` | PDF-Daten |
| `PdfDocument::fromHtml(string $html, string $filename = 'document.pdf')` | HTML über `PdfOut` (Einstellungen des Addons) |

### Bearbeiten

| Methode | Beschreibung |
| --- | --- |
| `append(PdfDocument\|string ...$docs)` | weitere PDFs anhängen |
| `pages(array\|string $pages)` | Seiten auswählen und sortieren: `[1, 3]` oder `'1-3,5,-1'` (`-1` = letzte) |
| `stamp(string $text, float $size = 60, string $color = '#b3261e', float $opacity = 0.15, float $angle = 45, string $pages = 'all')` | Stempel/Wasserzeichen; `$pages`: `all`, `first`, `last` oder Auswahl |
| `pageNumbers(string $format = 'Seite {page} von {pages}', string $position = 'bottom-center', float $size = 9, string $color = '#444444', bool $skipFirst = false)` | Seitenzahlen; Position `top-`/`bottom-` + `left`/`center`/`right` |
| `metadata(?string $title, ?string $author, ?string $subject, ?string $keywords, ?string $creator)` | Metadaten (benannte Argumente) |
| `filename(string $filename)` | Dateiname für `inline()`/`download()` |
| `sign(Certificate $c, string $name = '', string $reason = '', string $location = '', string $contact = '', ?SignatureField $field = null)` | Signatur nach PAdES-B-B (SHA-256) |
| `protect(string $userPassword = '', ?string $ownerPassword = null, iterable $allow = [...])` | Passwortschutz (AES-256), `$allow` = erlaubte Rechte |

### Ausgabe und Lesen

| Methode | Rückgabe |
| --- | --- |
| `toString()` | PDF-Daten |
| `save(string $path, bool $overwrite = true)` | Pfad |
| `inline(?string $filename = null)` / `download(...)` | sendet und beendet |
| `pageCount()` | Seitenzahl |
| `info()` | Metadaten (`Pages`, `Title`, `Page size`, `Encrypted` …) |
| `text(bool $layout = false)` | Text |
| `signatures()` | Prüfergebnis je Signatur: `signer`, `signed_at`, `hash`, `type`, `valid`, `status`, `certificate`, `certificate_trusted`, `whole_document` |
| `PdfDocument::resolvePages(array\|string $selection, int $total)` | Seitenauswahl in Seitennummern auflösen |

Ausnahmen: `InvalidArgumentException` bei ungültigen Eingaben (kein PDF, Seite gibt es nicht, Zertifikat/Passwort falsch), `RuntimeException` bei Verarbeitungsfehlern.

---

## Certificate

| Methode | Beschreibung |
| --- | --- |
| `Certificate::fromAddon(string $name = '', ?string $password = null)` | aus `data/addons/pdfout/certificates/`; leer = Standard aus den Einstellungen |
| `Certificate::fromP12(string $file, string $password = '')` | PKCS#12 (.p12/.pfx) |
| `Certificate::fromPem(string $certFile, ?string $keyFile = null, string $password = '')` | PEM, Schlüssel getrennt oder in derselben Datei |
| `Certificate::fromFile(string $file, string $password = '')` | nach Dateiendung |
| `commonName()`, `validTo()` | Angaben aus dem Zertifikat |

Zertifikat und Schlüssel werden beim Laden geprüft (passen sie zusammen?).

## SignatureField

Position in Millimetern ab der linken oberen Ecke, Seite `-1` = letzte.

| Methode | Beschreibung |
| --- | --- |
| `SignatureField::bottomLeft(float $width = 70, float $height = 25)` | unten links (A4) |
| `SignatureField::bottomRight(...)` | unten rechts (A4) |
| `SignatureField::at(float $x, float $y, float $width = 70, float $height = 25, int $page = -1)` | frei |
| `->withoutBox()` | nur das Feld, ohne gezeichneten Kasten |

## Permission

Erlaubte Rechte: `Print`, `PrintHigh`, `Modify`, `Copy`, `Annotate`, `FillForms`, `Extract` (immer erlaubt, Barrierefreiheit), `Assemble`. Strings (`'print'`, `'copy'` …) werden ebenfalls akzeptiert.

## Poppler

| Methode | Beschreibung |
| --- | --- |
| `Poppler::isAvailable()`, `Poppler::missing()`, `Poppler::version()` | Verfügbarkeit |
| `Poppler::info(string $file, string $password = '')` | Metadaten |
| `Poppler::pageCount(string $file, string $password = '')` | Seitenzahl |
| `Poppler::signatures(string $file, string $password = '')` | Signaturprüfung |
| `Poppler::text(string $file, string $password = '', bool $layout = false)` | Text |
| `Poppler::run(string $tool, array $args, int $timeout = 60)` | beliebiges Poppler-Programm ohne Shell |

Der Ordner der Programme lässt sich in den Einstellungen festlegen (`poppler_path`).

---

## Beispiele

```php
// Rechnung: erzeugen, AGB anhängen, signieren, speichern
PdfOut::create()
    ->html($html)
    ->append(rex_path::media('agb.pdf'))
    ->sign(Certificate::fromAddon('firma.p12', $pw), reason: 'Rechnung ' . $nr, field: SignatureField::bottomRight())
    ->save(rex_path::addonData('shop', "rechnungen/$nr.pdf"));

// Medienpool-PDF als Entwurf kennzeichnen und anzeigen
PdfDocument::fromMedia('vertrag.pdf')->stamp('ENTWURF')->pageNumbers()->inline();

// Signatur prüfen
$ok = array_all(PdfDocument::fromFile($pfad)->signatures(), fn (array $s) => $s['valid']);

// Viewer mit Rücksprung
echo '<a href="' . PdfOut::viewer(rex_url::media('preise.pdf'), rex_getUrl()) . '">Preisliste ansehen</a>';
```

## Weiterführend

- [dompdf](https://github.com/dompdf/dompdf/wiki) – HTML/CSS-Unterstützung
- [tc-lib-pdf](https://github.com/tecnickcom/tc-lib-pdf) – PDF-Bearbeitung, Signaturen, Verschlüsselung
- [Poppler](https://poppler.freedesktop.org/) – PDF-Werkzeuge
- [PDF.js](https://mozilla.github.io/pdf.js/) – Viewer
