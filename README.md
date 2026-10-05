# PdfOut für REDAXO!

Das PDF-Werkzeug für REDAXO: **erzeugen** (HTML und Artikel mit [dompdf](https://github.com/dompdf/dompdf)), **bearbeiten** (zusammenführen, Seiten auswählen, Stempel, Seitenzahlen – mit [tc-lib-pdf](https://github.com/tecnickcom/tc-lib-pdf)), **absichern** (digitale Signatur nach PAdES, Passwortschutz mit AES-256), **prüfen** (Signaturen, Metadaten, Text – mit [Poppler](https://poppler.freedesktop.org/)) und **anzeigen** ([PDF.js](https://github.com/mozilla/pdf.js) mit Editor).

## Inhaltsverzeichnis

- [Installation](#installation)
- [Features](#was-kann-pdfout)
- [Quick Start](#lass-uns-loslegen)
- [PDFs bearbeiten](#pdfs-bearbeiten-pdfdocument)
- [Passwortschutz](#passwortgeschützte-pdfs)
- [Digitale Signaturen](#digitale-signaturen)
- [Signaturen prüfen](#signaturen-prüfen)
- [Zusammenführen und Anhängen](#pdfs-zusammenführen-und-anhängen)
- [Backend-Werkzeuge](#backend-werkzeuge)
- [PDF-Thumbnails](#pdf-thumbnails)
- [Erweiterte Methoden](#erweiterte-methoden)
- [Umstieg von Version 10](#umstieg-von-version-10)
- [Anwendungsfälle](#anwendungsfälle--best-practices)
- [PDF.js Toolbar Builder & Profile nutzen](#pdfjs-toolbar-builder--profile-nutzen)
- [Systemvoraussetzungen](#systemvoraussetzungen)
- [Support](#support--credits)

## Installation

Die Installation erfolgt über den REDAXO-Installer, alternativ gibt es die aktuellste Version auf [GitHub](https://github.com/FriendsOfREDAXO/pdfout).

**Voraussetzungen:** PHP 8.4 oder neuer und die **poppler-utils** auf dem Server (`pdfinfo`, `pdfsig`, `pdftoppm`, `pdftotext`). Fehlen sie, bricht die Installation mit einem Hinweis ab:

```bash
# Debian/Ubuntu
apt install poppler-utils
# macOS (Homebrew)
brew install poppler
```

Liegen die Programme außerhalb des Suchpfads, den Ordner vor der Installation setzen:
`php bin/console config:set --type=string pdfout poppler_path /pfad/zu/bin` (später auch unter *Einstellungen*).

> **Neu in Version 11:** TCPDF und FPDI sind durch **tc-lib-pdf** ersetzt (der Nachfolger von TCPDF). Neue, verkettbare API mit `PdfOut::create()` und `PdfDocument`, Signaturen nach **PAdES**, Passwortschutz mit **AES-256**, echte Signaturprüfung über Poppler und neue Backend-Seiten *Werkzeuge*, *Editor* und *Prüfen*. Die bisherigen Methoden funktionieren weiter – siehe [Umstieg von Version 10](#umstieg-von-version-10).

## Was kann PdfOut?

- 🌈 **HTML zu PDF**: HTML und REDAXO-Artikel in hochwertige PDFs umwandeln (dompdf)
- ✂️ **Bearbeiten**: PDFs zusammenführen, Seiten auswählen und sortieren, Stempel/Wasserzeichen, Seitenzahlen, Metadaten
- ✍️ **Signieren**: digitale Signaturen nach PAdES (SHA-256), sichtbar oder unsichtbar
- 🔒 **Schützen**: Passwortschutz mit AES-256 und fein einstellbaren Rechten
- ✅ **Prüfen**: Signaturen, Metadaten und Text auslesen (Poppler)
- 🔍 **Anzeigen**: PDF.js-Viewer mit konfigurierbarer Toolbar und Rücksprung-Knopf
- 🖊 **Editor**: PDFs im Backend mit Text, Zeichnungen, Unterschriften und Bildern versehen
- 🖼 **Thumbnails**: Vorschaubilder per Media Manager (Poppler)
- 💾 **Ausgabe**: als String, Datei, im Browser oder als Download

## Lass uns loslegen!

### Quick Start: Das erste PDF in 3... 2... 1...

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

PdfOut::create()
    ->html('<h1>Hallo REDAXO-Welt!</h1><p>Mein erstes PDF mit PdfOut.</p>')
    ->inline('mein_erstes_pdf.pdf');   // oder ->download(), ->save($pfad), ->toString()
```

Die bisherige Schreibweise funktioniert weiter:

```php
$pdf = new PdfOut();
$pdf->setName('mein_erstes_pdf')
    ->setHtml('<h1>Hallo REDAXO-Welt!</h1>')
    ->run();
```

### Artikel-Inhalte als PDF

```php
use FriendsOfRedaxo\PdfOut\PdfOut;
$pdf = new PdfOut();
$pdf->setName('artikel_als_pdf')
    ->addArticle(1)  // Hier die ID eures Artikels einsetzen
    ->run();
```

### Erweiterte Konfiguration eines PDFs

```php
use FriendsOfRedaxo\PdfOut\PdfOut;
$pdf = new PdfOut();

$pdf->setName('konfiguriertes_pdf')
    ->setPaperSize('A4', 'portrait')      // Setzt Papiergröße und Ausrichtung
    ->setFont('Helvetica')                // Setzt die Standardschriftart
    ->setDpi(300)                         // Setzt die DPI für bessere Qualität
    ->setAttachment(true)                 // Als Download statt Vorschau
    ->setRemoteFiles(true)                // Erlaubt externe Ressourcen
    ->setHtml($content, true)             // HTML mit Output Filter
    ->run();
```

### Schicke Vorlagen für PDFs

```php
$meineVorlage = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif;}
        .kopf { background-color: #ff9900; padding: 10px; }
        .inhalt { margin: 20px; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; }
        .pagenum:before {
		content: counter(page);
        }
</style>

</style>
</head>
<body>
    <div class="kopf">Mein supercooler PDF-Kopf</div>
    <div class="inhalt">{{CONTENT}}</div>
    <div class="footer">Seite <span class="pagenum"></span> von: DOMPDF_PAGE_COUNT_PLACEHOLDER</div>
</body>
</html>';

use FriendsOfRedaxo\PdfOut\PdfOut;
$pdf = new PdfOut();
$pdf->setName('stylishes_pdf')
    ->setBaseTemplate($meineVorlage)
    ->setHtml('<h1>Wow!</h1><p>Dieses PDF sieht ja mal richtig schick aus!</p>')
    ->run();
```

### PDFs speichern und verschicken

PDF speichern und gleichzeitig an den Browser senden? So geht's:

```php
use FriendsOfRedaxo\PdfOut\PdfOut;
$pdf = new PdfOut();
$pdf->setName('mein_meisterwerk')
    ->setHtml('<h1>PDF-Kunst</h1>')
    ->setSaveToPath(rex_path::addonCache('pdfout'))
    ->setSaveAndSend(true)  // Speichert und sendet in einem Rutsch
    ->run();
```

### PDF-Thumbnails

> **Problem**: Ubuntu/Debian blockiert seit 2018 die PDF-zu-Bild-Konvertierung über ImageMagick/Ghostscript (via `/etc/ImageMagick-6/policy.xml`). Der bisherige Media-Manager-Effekt `convert2img` funktioniert dadurch nicht mehr für PDFs.

**Lösung**: PdfOut liefert einen eigenen Media-Manager-Effekt **„PDF-Thumbnail (pdfout)"**, der `pdftoppm` aus poppler-utils verwendet – **nicht von der ImageMagick-Policy betroffen**.

#### Voraussetzung auf dem Server

Für die PDF-Thumbnail-Funktion wird **poppler-utils** benötigt (liefert `pdftoppm` und `pdftocairo`). Diese Tools sind **nicht** von der ImageMagick-Policy betroffen und funktionieren auf allen aktuellen Linux-Distributionen.

**Ubuntu / Debian:**
```bash
sudo apt install poppler-utils
```

**CentOS / RHEL / Fedora / Amazon Linux:**
```bash
# CentOS/RHEL 7/8
sudo yum install poppler-utils

# Fedora / CentOS Stream 9+
sudo dnf install poppler-utils
```

**Alpine Linux (z.B. in Docker):**
```bash
apk add poppler-utils
```

**Arch Linux / Manjaro:**
```bash
sudo pacman -S poppler
```

**openSUSE:**
```bash
sudo zypper install poppler-tools
```

**macOS (Homebrew):**
```bash
brew install poppler
```

**macOS (MacPorts):**
```bash
sudo port install poppler
```

**Docker (Debian-basiert):**
```dockerfile
RUN apt-get update && apt-get install -y poppler-utils && rm -rf /var/lib/apt/lists/*
```

**Prüfen ob die Installation erfolgreich war:**
```bash
which pdftoppm && pdftoppm -v
# Erwartete Ausgabe: /usr/bin/pdftoppm  +  Versionsnummer
```

> **Fallback-Tools** (optional, falls poppler-utils nicht installierbar):
> - `ghostscript` – Ghostscript direkt, ohne ImageMagick-Umweg: `apt install ghostscript`
> - `php-imagick` – PHP Imagick-Extension, letzter Fallback (evtl. von Policy blockiert): `apt install php-imagick`

#### Media-Manager-Effekt verwenden

1. Im Backend unter **Media Manager** einen neuen Typ anlegen (z.B. `pdf_thumb`)
2. Effekt **„PDF-Thumbnail (pdfout)"** hinzufügen
3. Optional: Anschließend `resize` für einheitliche Größe

Der Effekt zeigt im Backend den Status der verfügbaren Tools an (pdftoppm ✓/✗, gs ✓/✗ usw.).

#### Im Template/Modul verwenden

```php
// PDF als Thumbnail per Media Manager Typ ausgeben
$filename = 'mein_dokument.pdf';
$thumbUrl = rex_media_manager::getUrl('pdf_thumb', $filename);
echo '<img src="' . $thumbUrl . '" alt="PDF-Vorschau">';
```

#### PdfThumbnail-Klasse direkt verwenden

Für erweiterte Anwendungsfälle kann die Klasse auch direkt genutzt werden:

```php
use FriendsOfRedaxo\PdfOut\PdfThumbnail;

$thumb = new PdfThumbnail();
$thumb->setDpi(200)
      ->setFormat('jpg')
      ->setQuality(90)
      ->setPage(1)
      ->setMaxWidth(800);

// Als Dateipfad
$imagePath = $thumb->generate(rex_path::media('dokument.pdf'));

// Als GD-Image
$gdImage = $thumb->generateAsGdImage(rex_path::media('dokument.pdf'));

// Als Binärstring
$imageData = $thumb->generateAsString(rex_path::media('dokument.pdf'));

// Status prüfen
$status = PdfThumbnail::getStatus();
if (!$status['available']) {
    echo 'Bitte poppler-utils installieren: apt install poppler-utils';
}

// Verfügbare Tools prüfen
$tools = PdfThumbnail::checkAvailableTools();
// => ['pdftoppm' => true, 'pdftocairo' => true, 'gs' => false, 'imagick' => false]
```

#### Tool-Priorität

Die Konvertierung probiert folgende Tools in dieser Reihenfolge:

| Priorität | Tool | Paket | Hinweis |
|-----------|------|-------|---------|
| 1 | `pdftoppm` | poppler-utils | ⭐ Empfohlen, schnell, hohe Qualität |
| 2 | `pdftocairo` | poppler-utils | Alternative aus dem gleichen Paket |
| 3 | `gs` | ghostscript | Ghostscript direkt, ohne ImageMagick-Umweg |
| 4 | Imagick | php-imagick | Fallback, evtl. von Policy betroffen |

#### Farbmanagement (Gamma & ICC-Profil)

PDF-Viewer wie macOS Preview nutzen *Display Color Management* (z.B. Display P3), wodurch dunkle Farben satter und heller erscheinen. Browser zeigen Thumbnails ohne dieses Mapping – insbesondere dunkle Grüntöne oder andere gesättigte Farben können dadurch deutlich dunkler wirken als im PDF-Viewer.

**Standardmäßig** werden Thumbnails als **PNG** erzeugt (verlustfrei, bessere Farberhaltung als JPEG). Für die meisten Anwendungsfälle reicht das aus.

##### Optionale Einstellungen im Media-Manager-Effekt

| Parameter | Standard | Beschreibung |
|-----------|----------|--------------|
| Gamma-Korrektur | 1.0 | Werte > 1.0 hellen das Bild auf. Empfohlen: **1.2** für eine Darstellung, die der PDF-Vorschau entspricht |
| ICC-Farbprofil | none | `srgb` bettet ein sRGB-Profil ein, damit Browser die Farben korrekt interpretieren |

##### ICC-Profil: Voraussetzungen

Die ICC-Profil-Einbettung benötigt die **PHP-Extension Imagick** (`php-imagick`).

Ein sRGB-Profil wird automatisch gesucht. **PdfOut liefert bereits ein sRGB-Profil mit** (`data/icc/sRGB.icc`), daher muss in den meisten Fällen nichts zusätzlich installiert werden.

Falls dennoch Probleme auftreten, kann ein System-Profil installiert werden:

**Ubuntu / Debian:**
```bash
# Variante 1: icc-profiles-free (empfohlen)
sudo apt install icc-profiles-free

# Variante 2: colord (liefert ebenfalls sRGB)
sudo apt install colord
```

**CentOS / RHEL / Fedora:**
```bash
sudo dnf install colord
```

**Alpine Linux:**
```bash
apk add colord
```

**openSUSE:**
```bash
sudo zypper install colord
```

**macOS:**
Kein zusätzliches Paket nötig – das sRGB-Profil ist unter `/System/Library/ColorSync/Profiles/sRGB Profile.icc` bereits vorhanden.

**Docker (Debian-basiert):**
```dockerfile
RUN apt-get update && apt-get install -y php-imagick icc-profiles-free && rm -rf /var/lib/apt/lists/*
```

> **Hinweis**: Wenn Ghostscript installiert ist, liefert es ebenfalls ICC-Profile mit. Die Suchreihenfolge für ICC-Profile ist:
> 1. sRGB.icc (im pdfout-Addon enthalten)
> 2. `/usr/share/color/icc/colord/sRGB.icc` (icc-profiles-free/colord)
> 3. `/usr/share/color/icc/sRGB.icc` (icc-profiles-free)
> 4. `/usr/share/color/icc/ghostscript/srgb.icc` (Ghostscript)
> 5. Ghostscript versioniertes Profil
> 6. dompdf sRGB2014.icc (im pdfout-Addon enthalten)
> 7. macOS ColorSync sRGB Profil

##### Gamma-Korrektur direkt verwenden

```php
use FriendsOfRedaxo\PdfOut\PdfThumbnail;

$thumb = new PdfThumbnail();
$thumb->setDpi(150)
      ->setFormat('png')
      ->setGamma(1.2)              // Heller für bessere Farbwiedergabe
      ->setEmbedIccProfile(true);  // sRGB-Profil einbetten

$imagePath = $thumb->generate(rex_path::media('dokument.pdf'));
```

### PDFs bearbeiten (PdfDocument)

`PdfDocument` bearbeitet jedes PDF – frisch erzeugt, aus dem Medienpool oder aus einer Datei. Die Schritte werden gesammelt und beim Ausgeben in einem Durchgang angewendet.

```php
use FriendsOfRedaxo\PdfOut\{PdfDocument, Certificate, Permission, SignatureField};

PdfDocument::fromMedia('preisliste.pdf')
    ->append(PdfDocument::fromMedia('agb.pdf'))          // weitere PDFs anhängen
    ->pages('1-3,-1')                                     // Seiten 1–3 und die letzte
    ->stamp('ENTWURF', opacity: 0.15)                     // Wasserzeichen
    ->pageNumbers('Seite {page} von {pages}')             // Seitenzahlen
    ->metadata(title: 'Preisliste 2027', author: 'Hotel')
    ->save(rex_path::addonData('mein_addon', 'preisliste.pdf'));
```

Quellen: `fromMedia($datei)`, `fromFile($pfad)`, `fromString($pdfDaten)`, `fromHtml($html)`.
Lesen (Poppler): `pageCount()`, `info()`, `text()`, `signatures()`.

> Ohne Bearbeitungsschritt bleibt das PDF byte-identisch. Beim Bearbeiten werden die Seiten neu aufgebaut – Links, Formularfelder und Lesezeichen gehen dabei verloren.

Aus `PdfOut` heraus geht es nahtlos weiter: `->document()` liefert das `PdfDocument`, oder Schritte direkt anhängen:

```php
PdfOut::create()
    ->html($html)
    ->with(fn (PdfDocument $doc) => $doc->pageNumbers()->stamp('ENTWURF'))
    ->download('angebot.pdf');
```

### Passwortgeschützte PDFs

```php
use FriendsOfRedaxo\PdfOut\{PdfOut, Permission};

PdfOut::create()
    ->html($html)
    ->protect('geheim', allow: [Permission::Print, Permission::Copy])
    ->download('vertraulich.pdf');
```

- Verschlüsselung mit **AES-256**.
- `allow` nennt die **erlaubten** Rechte, alles andere ist gesperrt: `Print`, `PrintHigh`, `Copy`, `Modify`, `Annotate`, `FillForms`, `Assemble`. Das Auslesen für Screenreader bleibt immer erlaubt.
- Ohne Besitzer-Passwort wird ein zufälliges gesetzt. Mit leerem Benutzer-Passwort öffnet sich das PDF ohne Passwort, die Rechte gelten trotzdem.

### Digitale Signaturen

```php
use FriendsOfRedaxo\PdfOut\{PdfOut, Certificate, SignatureField};

PdfOut::create()
    ->html($rechnungHtml)
    ->sign(
        Certificate::fromAddon('firma.p12', 'passwort'),   // oder Certificate::fromP12() / ::fromPem()
        reason: 'Rechnung',
        location: 'Moers',
        field: SignatureField::bottomRight(),              // sichtbar; ohne field unsichtbar
    )
    ->save($pfad);
```

- Signaturen nach **PAdES-B-B** mit SHA-256 (`ETSI.CAdES.detached`), lesbar in Acrobat, Poppler und allen gängigen Prüfwerkzeugen.
- Zertifikate verwalten (anlegen, hochladen) unter *pdfout → Zertifikate*; sie liegen in `data/addons/pdfout/certificates/`.
- `SignatureField::at($x, $y, $breite, $hoehe, $seite)` platziert das Feld frei (Millimeter ab links oben, Seite `-1` = letzte).
- Vorhandene PDFs signieren: `PdfDocument::fromFile($pfad)->sign($zertifikat)->save($ziel)`.

> Selbst ausgestellte Zertifikate (z. B. aus der Zertifikats-Seite) zeigen in PDF-Readern „Aussteller unbekannt“ – die Unversehrtheit des Dokuments ist trotzdem prüfbar. Für vertrauenswürdige Signaturen ein Zertifikat einer anerkannten Stelle verwenden.

### Signaturen prüfen

```php
foreach (PdfDocument::fromFile($pfad)->signatures() as $sig) {
    echo $sig['signer'], ': ', $sig['valid'] ? 'gültig' : 'ungültig',
        $sig['whole_document'] ? '' : ' (nachträglich geändert)';
}
```

Geprüft wird mit `pdfsig` (Poppler): Unversehrtheit, Hash-Verfahren, Signatur-Typ, Zertifikatsstatus und ob die Signatur das ganze Dokument abdeckt. `PdfOut::validateSignedPdf($pfad)` liefert das Ergebnis im bisherigen Array-Format.

### PDFs zusammenführen und anhängen

```php
// Rechnung erzeugen und AGB anhängen
PdfOut::create()
    ->html($rechnungHtml)
    ->append(rex_path::media('agb.pdf'), rex_path::media('widerruf.pdf'))
    ->download('rechnung.pdf');

// vorhandene PDFs zusammenführen
PdfDocument::fromMedia('teil1.pdf')
    ->append(PdfDocument::fromMedia('teil2.pdf'))
    ->save($ziel);
```

### Backend-Werkzeuge

- **Werkzeuge**: PDFs aus dem Medienpool oder per Upload zusammenführen, Seiten auswählen, stempeln, nummerieren, signieren und schützen – Ergebnis herunterladen oder in den Medienpool speichern.
- **Editor**: ein PDF aus dem Medienpool im PDF.js-Editor öffnen, Text, Zeichnungen, Unterschriften und Bilder hinzufügen und als neue Datei (oder Ersatz) speichern.
- **Prüfen**: Metadaten, Signaturen und Text eines PDFs anzeigen.
- **Demos**: lauffähige Beispiele zu allen Funktionen mit dem gezeigten Code.

## Erweiterte Methoden

### Basis-Methoden (PdfOut-Klasse)

### `setPaperSize(string|array $size = 'A4', string $orientation = 'portrait')`
Setzt das Papierformat und die Ausrichtung für das PDF. Als `$size` kann entweder ein Standardformat wie 'A4', 'letter' oder ein Array mit [width, height] in Punkten übergeben werden.

```php
$pdf->setPaperSize('A4', 'landscape');  // Querformat A4
$pdf->setPaperSize([841.89, 595.28], 'portrait');  // Benutzerdefinierte Größe
```

### `setBaseTemplate(string $template, string $placeholder = '{{CONTENT}}')`
Setzt ein Grundtemplate für das PDF. Der Platzhalter wird durch den eigentlichen Inhalt ersetzt. Besonders nützlich für einheitliches Layout über mehrere PDFs.

### `addArticle(int $articleId, ?int $ctype = null, bool $applyOutputFilter = true)`
Ermöglicht das Hinzufügen von REDAXO-Artikelinhalten:
- `$articleId`: Die ID des Artikels
- `$ctype`: Optional die ID des Content-Types
- `$applyOutputFilter`: Ob der OUTPUT_FILTER angewendet werden soll

### `mediaUrl(string $type, string $file)`
Generiert korrekte URLs für Media-Manager-Bilder im PDF:

```php
$imageUrl = PdfOut::mediaUrl('media_type', 'bild.jpg');
$html = '<img src="' . $imageUrl . '" alt="Mein Bild">';
```

### `viewer(string $file = '', string $returnUrl = '')`
Erzeugt eine URL für den integrierten PDF-Viewer:

```php
// Als Download-Link
echo '<a href="' . PdfOut::viewer('/media/dokument.pdf') . '" download>PDF anzeigen</a>';

// Als iFrame eingebettet
echo '<iframe src="' . PdfOut::viewer('/media/dokument.pdf') . '"></iframe>';

// Als eigene Seite mit Knopf „← Zurück“ (z. B. für iPhone/iPad, wo eingebettete Viewer schlecht scrollen)
$current = rex_yrewrite::getFullUrlByArticleId(rex_article::getCurrentId());
echo '<a href="' . PdfOut::viewer('/media/dokument.pdf', $current) . '">Speisekarte</a>';
```

Der Rücksprung-Knopf erscheint nur für Adressen derselben Domain. Eine eigene Beschriftung geht per
URL-Parameter `returnUrl=…&returnLabel=Zur%20Speisekarte`. Kommt der Besuch von der Rücksprung-Seite,
führt der Knopf im Verlauf zurück – die Scrollposition bleibt dann erhalten.

## Umstieg von Version 10

Die bisherigen Methoden bleiben erhalten und nutzen intern die neue Technik:

| bisher | weiter nutzbar | neue Schreibweise |
| --- | --- | --- |
| `setHtml()`, `setName()`, `run()` | ✅ | `PdfOut::create()->html()->inline()` |
| `enableDigitalSignature()`, `setVisibleSignature()` | ✅ | `->sign(Certificate::…, field: SignatureField::…)` |
| `enablePasswordProtection()` | ✅ | `->protect('pw', allow: [Permission::Print])` |
| `signExistingPdf()` | ✅ | `PdfDocument::fromFile()->sign()->save()` |
| `createSignedWorkflow()`, `createSignedDocument()` | ✅ | `->sign()->save()` |
| `createPasswordProtectedWorkflow()`, `createPasswordProtectedDocument()` | ✅ | `->protect()->save()` |
| `mergePdfs()`, `mergeHtmlToPdf()` | ✅ | `PdfDocument::…->append()` |
| `createWithAppendedPdfs()`, `createDocumentWithAttachments()` | ✅ | `->append()` |
| `validateSignedPdf()` | ✅ – prüft jetzt wirklich (Poppler) | `->signatures()` |
| `generateSignedPdf()`, `generateProfessionalSignedPdf()` | ✅ | `->sign()` |
| `generateCleanSignedPdf()` | ✅ – erzeugt jetzt aus HTML mit dompdf | `->sign()->toString()` |

**Was sich ändert:**

- **PHP 8.4** ist Mindestversion, **Poppler** ist Voraussetzung.
- TCPDF und FPDI sind entfernt. Wer `new TCPDF()` oder FPDI direkt im eigenen Code nutzt, bindet die Bibliotheken selbst per Composer ein oder stellt auf `PdfDocument` um.
- Entfernte geschützte Methoden (nur für Unterklassen relevant): `runWithTcpdf()`, `addDigitalSignature()`, `addDigitalSignatureFinal()`, `addPasswordProtection()`, `processTcpdfOutput()`, `drawSignatureArea()`, `addSignatureAreaToFpdi()`, `addCleanSignatureArea()`.
- **Rechte beim Passwortschutz:** Die Liste nennt die *erlaubten* Rechte, wie dokumentiert. Bisher wurden sie wegen der TCPDF-Logik versehentlich *gesperrt*: Mit `['print']` war Drucken verboten. Wer sich darauf verlassen hat, prüft die Aufrufe.
- **Sichtbare Signatur:** Position und Größe in Millimetern ab der linken oberen Ecke. Der Kasten zeigt Name, Datum, Ort und Grund.
- Signaturen nach PAdES (`ETSI.CAdES.detached`) statt `adbe.pkcs7.detached`.

## Tipps für die Optimierung

### Performance-Optimierung
- CSS inline im HTML definieren statt externe Dateien
- Auf große CSS-Frameworks verzichten
- Bilder in optimierter Größe verwenden
- OPcache für bessere PHP-Performance aktivieren

### Bilder und Media Manager
- Relative Pfade vom Frontend-Ordner: `media/bild.jpg`
- Media Manager URLs immer als absolute URLs
- `setRemoteFiles(true)` für externe Ressourcen

### CSS und Schriftarten
- Numerische font-weight Angaben vermeiden
- Google Fonts lokal einbinden
- Bei Schriftproblemen: `isFontSubsettingEnabled` auf `false` setzen

### Kopf- und Fußzeilen
- Fixierte Divs direkt nach dem body-Tag platzieren
- Seitenzahlen über CSS count oder Platzhalter

## Anwendungsfälle & Best Practices

### Rechnungen und Geschäftsdokumente
```php
// Rechnung signieren, AGB anhängen, im Archiv ablegen
PdfOut::create()
    ->html($rechnungHtml)
    ->append(rex_path::media('agb.pdf'))
    ->sign(Certificate::fromAddon('firma.p12', $passwort), reason: 'Rechnung ' . $nummer)
    ->save(rex_path::addonData('shop', 'rechnungen/' . $nummer . '.pdf'));
```

### Vertrauliche Berichte
```php
// Passwortgeschützt, nur Drucken erlaubt
PdfOut::create()
    ->html($berichtHtml)
    ->protect($benutzerPasswort, $adminPasswort, allow: [Permission::Print])
    ->download('vertraulicher_bericht.pdf');
```

### Zertifikate und Urkunden
```php
// Hochauflösend im Querformat, sichtbar signiert
PdfOut::create()
    ->html($urkundeHtml)
    ->paper('A4', 'landscape')
    ->sign(Certificate::fromAddon(), field: SignatureField::at(200, 170, 70, 25))
    ->inline('urkunde.pdf');
```

### Entwürfe kennzeichnen
```php
PdfDocument::fromMedia('vertrag.pdf')
    ->stamp('ENTWURF')
    ->pageNumbers('Seite {page}/{pages}', position: 'bottom-right')
    ->inline();
```

### Archivierung und Compliance
```php
// Metadaten setzen und signieren
PdfDocument::fromFile($pfad)
    ->metadata(title: 'Archiviertes Dokument', subject: 'Compliance-Archiv', keywords: 'Archiv, ' . date('Y'))
    ->sign(Certificate::fromAddon('archiv.p12', $passwort), name: 'Archivsystem', reason: 'Archivierung')
    ->save($archivPfad);
```

## PDF.js Toolbar Builder & Profile nutzen

Mit dem Toolbar Builder kannst du die PDF.js-Oberfläche reduzieren und als Profile speichern.

### 1) Konfiguration im Backend

1. Öffne im Backend die Seite **PdfOut → Toolbar**.
2. Wähle ein Preset:
    - **Vollständig**: alle Bedienelemente sichtbar
    - **Ausgewogen**: sinnvolle Standardreduktion
    - **Kompakt**: stark reduzierte Oberfläche
    - **Benutzerdefiniert**: gezielte Auswahl über Checkboxen
3. Aktiviere bei Bedarf einzelne Ausblendungen (z. B. Download, Druck, Editor-Werkzeuge).
4. Speichere entweder:
    - **Toolbar speichern** für globale Standardwerte
    - **Profil speichern** für benannte Varianten wie `lesemodus`, `redaktion`, `kiosk`

### 2) Profile verwalten

- **Profil aktivieren**: Setzt das ausgewählte Profil als aktiven Standard.
- **Profil löschen**: Entfernt ein Profil dauerhaft aus der AddOn-Konfiguration.
- Das aktive Profil wird in der Konfiguration gespeichert und bei der Viewer-URL automatisch berücksichtigt.

### 3) Verwendung im Code

Für die normale Verwendung genügt der Viewer-Aufruf. Das aktive Profil wird automatisch angewendet:

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

$viewerUrl = PdfOut::viewer('mein_dokument.pdf');
```

Der Aufruf ergänzt intern `toolbarPreset` und `toolbarHiddenGroups` basierend auf:

1. aktivem Profil (falls gesetzt)
2. sonst den globalen Toolbar-Defaults

Damit lässt sich die Viewer-Oberfläche zentral im Backend steuern, ohne Template-Code anpassen zu müssen.

### 4) Gezielt ein bestimmtes Profil pro Aufruf nutzen

Wenn du pro Einbindung ein bestimmtes Profil erzwingen willst, ohne das globale aktive Profil umzuschalten, nutze:

```php
use FriendsOfRedaxo\PdfOut\PdfOut;

$viewerUrl = PdfOut::viewerWithProfile('mein_dokument.pdf', 'lesemodus');
```

Verhalten:

1. Existiert das Profil, wird genau dieses Profil verwendet.
2. Existiert das Profil nicht, fällt der Aufruf auf den normalen Viewer-Mechanismus zurück (aktives Profil bzw. globale Defaults).

## Systemvoraussetzungen

- **PHP 8.4** oder neuer mit den Erweiterungen dom, mbstring, gd, openssl, zlib und der Funktion `proc_open()`
- **poppler-utils**: `pdfinfo`, `pdfsig`, `pdftoppm`, `pdftotext`
- REDAXO 5.15 oder neuer

Empfohlen: OPcache, Imagick (alternative Thumbnail-Erzeugung).

## PDF.js Update-System

PdfOut enthält ein automatisiertes Update-System für PDF.js (Legacy-Build, Module als `.js` für Server ohne `.mjs`-MIME-Typ):

### 🚀 Ein-Befehl Updates
```bash
# Update auf neueste PDF.js Version
./scripts/update-pdfjs.sh

# Update auf spezifische Version  
./scripts/update-pdfjs.sh 6.4.299

# Verfügbare Updates prüfen
npm run check-updates
```

### ✨ Update-System
- **Vollständige Distribution**: Kompletter Viewer mit allen Komponenten
- **GitHub-Integration**: Direkte Downloads von offiziellen Releases
- **Optimiert**: Ausschluss von CJK-Character-Maps spart 1.6MB
- **Automatisiert**: Ein Befehl für komplette Updates
- **Zukunftssicher**: Unterstützt alle kommenden PDF.js Versionen

### 📖 Ausführliche Anleitung
Siehe [PDFJS_UPDATE.md](PDFJS_UPDATE.md) für den kompletten Workflow und Konfigurationsmöglichkeiten.

## Demo-Seite

Die Demo-Seite enthält lauffähige Beispiele mit dem jeweils ausgeführten Code: einfaches PDF, Artikel als PDF, Seitenzahlen und Stempel, Passwortschutz, sichtbare Signatur, Rechnung mit angehängten AGB, Seitenauswahl und Zusammenführen, Signaturprüfung und PDF.js-Viewer.

## Verwendete Bibliotheken & Lizenzen

PdfOut baut auf bewährten Open-Source-Bibliotheken auf:

### PDF-Generierung

#### dompdf
- **Homepage**: https://github.com/dompdf/dompdf
- **Lizenz**: LGPL v2.1
- **Zweck**: HTML-zu-PDF-Konvertierung mit excellentem CSS-Support
- **Dokumentation**: https://github.com/dompdf/dompdf/wiki

#### tc-lib-pdf
- **Homepage**: https://github.com/tecnickcom/tc-lib-pdf
- **Lizenz**: LGPL v3+
- **Zweck**: PDFs bearbeiten (Seiten-Import, Stempel, Seitenzahlen), digitale Signaturen (PAdES), Verschlüsselung (AES-256) – der Nachfolger von TCPDF

#### Poppler (Systemprogramme, nicht mitgeliefert)
- **Homepage**: https://poppler.freedesktop.org/
- **Lizenz**: GPL v2+
- **Zweck**: PDFs lesen und prüfen – Seitenzahl, Metadaten, Signaturprüfung, Text, Vorschaubilder

### CSS- und HTML-Verarbeitung

#### sabberworm/php-css-parser
- **Homepage**: https://github.com/sabberworm/PHP-CSS-Parser
- **Lizenz**: MIT
- **Zweck**: CSS-Parsing für dompdf

#### masterminds/html5
- **Homepage**: https://github.com/Masterminds/html5-php
- **Lizenz**: MIT
- **Zweck**: HTML5-Parser für moderne HTML-Unterstützung

#### php-font-lib & php-svg-lib
- **Homepage**: https://github.com/dompdf/php-font-lib
- **Lizenz**: LGPL v2.1
- **Zweck**: Font-Handling und SVG-Unterstützung

### PDF-Viewer

#### PDF.js
- **Homepage**: https://github.com/mozilla/pdf.js
- **Version**: 6.x (Legacy-Build für ältere Browser, automatisch aktualisiert)
- **Lizenz**: Apache 2.0
- **Zweck**: Integrierter PDF-Viewer im Browser mit erweiterten Features
- **Dokumentation**: https://mozilla.github.io/pdf.js/
- **Update-System**: GitHub Releases via `./scripts/update-pdfjs.sh`

## Lizenzen im Detail

### LGPL (Lesser General Public License)
Die LGPL-lizenzierten Komponenten (dompdf, tc-lib-pdf, php-font-lib) erlauben:
- ✅ Kommerzielle Nutzung
- ✅ Einbindung in proprietäre Software
- ✅ Modifikation der Bibliotheken
- ⚠️ Modifikationen an LGPL-Code müssen unter LGPL bleiben

### MIT License
Die MIT-lizenzierten Komponenten erlauben:
- ✅ Vollständig freie Nutzung
- ✅ Kommerzielle Nutzung ohne Einschränkungen
- ✅ Modifikation und Weiterverteilung
- ✅ Einbindung in proprietäre Software

### Apache 2.0 (PDF.js)
- ✅ Kommerzielle Nutzung
- ✅ Patent-Grant (Schutz vor Patent-Klagen)
- ✅ Trademark-Schutz

## Support & Credits

### Wo finde ich Hilfe?

- [REDAXO-Channel auf Slack](https://friendsofredaxo.slack.com/messages/redaxo/)
- [GitHub Issues](https://github.com/FriendsOfREDAXO/pdfout/issues)
- [REDAXO Forum](https://forum.redaxo.org/)

### Team

**Friends Of REDAXO**  
http://www.redaxo.org  
https://github.com/FriendsOfREDAXO

**Projekt-Lead**  
[Thomas Skerbis](https://github.com/skerbis)

### Danke an

- [dompdf](http://dompdf.github.io)
- [FriendsOfREDAXO](https://github.com/FriendsOfREDAXO)
- [First release: Oliver Kreischer](https://github.com/olien)

## Sponsors ##
Version 10.0.0 
- [Alexander Walther](https://github.com/alxndr-w)
- [FVN e.V.](https://fvn.de)
- [WDFV e.V.](https://wdfv.de)

### Lizenz

**PdfOut selbst**: [MIT-Lizenz](https://github.com/FriendsOfREDAXO/pdfout/blob/master/LICENSE.md)

**Verwendete Bibliotheken**:
- dompdf: LGPL v2.1
- tc-lib-pdf: LGPL v3+
- PDF.js 6.x: Apache 2.0 (automatisch aktualisiert)
- Poppler (nicht mitgeliefert, als Programm aufgerufen): GPL v2+
- php-css-parser: MIT
- html5-php: MIT

Alle Lizenzen sind kompatibel und erlauben sowohl private als auch kommerzielle Nutzung.
