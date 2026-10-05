# Changelog

## 11.0.0 – 05.10.2026

pdfout wird zum PDF-Werkzeug für REDAXO: erzeugen, bearbeiten, absichern, prüfen, anzeigen.

### Breaking Changes

- **PHP 8.4** ist Mindestversion
- **poppler-utils** sind Voraussetzung (`pdfinfo`, `pdfsig`, `pdftoppm`, `pdftotext`); die Installation prüft das und nennt die Installationsbefehle. Ordner einstellbar (`poppler_path`)
- **TCPDF und FPDI entfernt**, ersetzt durch **tc-lib-pdf** (Nachfolger von TCPDF). Wer TCPDF/FPDI direkt im eigenen Code nutzt, bindet sie selbst ein oder stellt auf `PdfDocument` um
- Entfernte geschützte Methoden (nur für Unterklassen): `runWithTcpdf()`, `addDigitalSignature()`, `addDigitalSignatureFinal()`, `addPasswordProtection()`, `processTcpdfOutput()`, `drawSignatureArea()`, `addSignatureAreaToFpdi()`, `addCleanSignatureArea()`
- Signaturen nach PAdES (`ETSI.CAdES.detached`, SHA-256) statt `adbe.pkcs7.detached`; Passwortschutz mit AES-256
- Sichtbare Signatur: Position und Größe in Millimetern ab links oben; neue Standardwerte (unten links, 70 × 25 mm)
- Backend-Navigation in vier Bereiche gegliedert: Übersicht, Werkzeuge (Bearbeiten, Editor, Prüfen), Einstellungen (Allgemein, Zertifikate, PDF.js-Toolbar), Hilfe (Dokumentation, API, Best Practices, Demos) – Adressen lauten jetzt z. B. `pdfout/tools/verify`

### Neue Features

- **Verkettete API**: `PdfOut::create()->html()->sign()->protect()->append()->download()` – plus `toString()`, `save()`, `inline()`, `document()`, `with()`
- **`PdfDocument`**: jedes PDF bearbeiten – `fromMedia()`, `fromFile()`, `fromString()`, `fromHtml()`; `append()`, `pages('1-3,-1')`, `stamp()` (Wasserzeichen), `pageNumbers()`, `metadata()`, `sign()`, `protect()`; lesen mit `pageCount()`, `info()`, `text()`, `signatures()`. Ohne Bearbeitung bleibt das PDF byte-identisch
- **`Certificate`** (P12/PFX/PEM, Prüfung von Passwort und Schlüssel), **`SignatureField`**, **`Permission`** (Enum der erlaubten Rechte), **`Poppler`** (ohne Shell aufgerufen)
- **Echte Signaturprüfung**: `validateSignedPdf()` prüft jetzt mit `pdfsig` (bisher wurde immer „gültig“ gemeldet)
- Backend-Seiten **Werkzeuge → Bearbeiten** (zusammenführen, Seiten wählen, stempeln, nummerieren, signieren, schützen; herunterladen oder in den Medienpool), **Werkzeuge → Editor** (PDF.js-Editor: Text, Zeichnen, Unterschrift, Bilder – speichern in den Medienpool), **Werkzeuge → Prüfen** (Metadaten, Signaturen, Text); neue **Übersicht** und **Demos** mit der neuen API
- Dokumentation (README, API-Referenz, Best Practices, PDF.js-Update) neu geschrieben und auf den Stand von 11.0 gebracht
- Schriften für tc-lib-pdf werden mit `scripts/build-fonts.php` erzeugt und unter `fonts/` mitgeliefert (Standard-Schriften)

### Fixes

- **Rechte beim Passwortschutz**: die Liste nennt wie dokumentiert die *erlaubten* Rechte – bisher wurden sie durch die TCPDF-Logik gesperrt (`['print']` verbot das Drucken)
- Rechte `pdfout[tools]`, `pdfout[demo]`, `pdfout[certificates]`, `pdfout[config]` werden jetzt registriert (vorher nur für Admins nutzbar)
- Einstellungen mit CSRF-Schutz
- Seiten-Import übernimmt die Originalgröße jeder Seite (bisher teils fest A4)
- `rex_dir` fehlte als Import (Fehler, wenn der Cache-Ordner nicht existierte)
- sRGB-Profil für Vorschaubilder wird jetzt mitgeliefert (`data/icc/sRGB.icc`, bisher aus TCPDF)


## 10.6.0 – 05.10.2026

### Neue Features

- **Rücksprung-Knopf im Viewer:** `PdfOut::viewer($file, $returnUrl)` bzw. der URL-Parameter `returnUrl` (optional `returnLabel`) zeigt links in der Viewer-Leiste „← Zurück“. Gedacht für den Viewer als eigene Seite, etwa auf iPhone/iPad, wo eingebettete Viewer schlecht scrollen. Nur Adressen derselben Domain; kommt der Besuch von dieser Seite, geht es per Verlauf zurück (Scrollposition bleibt erhalten)


## 10.5.0 – 05.10.2026

### Update

- PDF.js 6.4.299 (vorher 5.6.205) – jetzt als **Legacy-Build**: der Viewer läuft damit auch auf iPhones/iPads mit älterem iOS und in älteren Browsern; die moderne Variante blieb dort leer
- Vendor-Pakete aktualisiert: masterminds/html5 2.11.0, sabberworm/php-css-parser 9.5.0, tecnickcom/tcpdf 6.11.4; `thecodingmachine/safe` entfällt (wird von php-css-parser nicht mehr benötigt)
- Composer-Plattform auf PHP 8.1 festgelegt (entspricht der Mindestanforderung des Addons)

### Fixes

- **Viewer blieb auf manchen Servern leer:** pdf.js-Module werden jetzt als `.js` statt `.mjs` ausgeliefert. Server, die `.mjs` nicht kennen (z. B. nginx unter Plesk, der statische Dateien selbst ausliefert und die `.htaccess` nicht beachtet), senden sonst `application/octet-stream` – Browser führen Module mit diesem Typ nicht aus (abschaltbar über `pdfjs.moduleExtension: "mjs"`)

### Verbesserungen

- Update-Skript: Build-Variante wählbar (`pdfjs.build`: `legacy`/`modern`), eine angegebene Version wird jetzt wirklich geladen (bisher immer die neueste), Zielordner werden vor dem Kopieren geleert
- Cache-Busting (`viewerVersion`, Toolbar-Skript) folgt automatisch der Addon-Version statt einer fest eingetragenen Nummer


## 10.4.0 – 29.07.2026

### Neue Features

- PDF.js Toolbar-Konfigurator um Profilverwaltung erweitert (Profile speichern, aktivieren, löschen)
- Viewer-Aufruf um gezielten Profilmodus ergänzt: `PdfOut::viewerWithProfile($file, $profileName)`

### Verbesserungen

- `PdfOut::viewer()` nutzt aktive Toolbar-Profile und globale Toolbar-Defaults konsistent
- Dokumentation für Toolbar-Builder und Profilnutzung in README und BEST_PRACTICES ergänzt

### Fixes

- Cache-Busting für Viewer-Overlay und Viewer-URLs auf Version `10.4.0` angehoben

## 10.3.3 – 29.07.2026

### Neue Features

- PDF.js Toolbar-Konfigurator mit Presets und Live-Demo ergänzt
- Viewer-Toolbar kann jetzt über eine kleine Overlay-Schicht gezielt reduziert werden

## 10.3.2 – 29.07.2026

### Update

- Vendor-Pakete aktualisiert: dompdf 3.1.6, masterminds/html5 2.10.1, sabberworm/php-css-parser 9.4.0, setasign/fpdi 2.6.8, tecnickcom/tcpdf 6.11.3

## 10.3.1 – 02.04.2026

### Update

- Vendor Pakete aktualisiert (dompdf, fpdf, tcpdf, etc.)
- PDF.js auf Version 5.6.205 aktualisiert

## 10.3.0 – 20.02.2026

### Neue Features

- **PNG als Standardformat**: Das Ausgabeformat wurde von JPEG auf PNG umgestellt, um eine bessere Farberhaltung bei dunklen Farben und Transparenz zu gewährleisten
- **Gamma-Korrektur (optional)**: Neue Einstellung im Media-Manager-Effekt zur Helligkeitsanpassung (Werte 0.8–1.4). Standard: 1.0 (keine Korrektur). Empfohlen: 1.2 für eine Darstellung, die der PDF-Vorschau in macOS Preview entspricht. Nutzt Imagick bevorzugt, `convert` (ImageMagick CLI) als zweiten Fallback, GD als dritten Fallback
- **ICC-Profil-Einbettung (optional)**: sRGB ICC-Profil kann in das Thumbnailbild eingebettet werden, damit Browser und Bildprogramme die Farben korrekt interpretieren. Nutzt automatisch das mitgelieferte TCPDF sRGB-Profil – keine zusätzliche Installation nötig. Unterstützt Imagick und `convert` (ImageMagick CLI) als Fallback

### Verbesserungen

- `PdfThumbnail`: Neue Methoden `setGamma()`, `setEmbedIccProfile()`, `applyGammaCorrection()`, `embedSrgbIccProfile()`, `findSrgbIccProfile()`, `findSrgbIccProfilePath()`, `getIccProfilePaths()`
- `rex_effect_pdf_thumbnail`: Zwei neue Parameter im Media-Manager-Effekt (Gamma-Korrektur, ICC-Farbprofil)
- `checkAvailableTools()` zeigt nun auch `convert` (ImageMagick CLI) Verfügbarkeit an
- Gamma-Korrektur: Dreistufige Fallback-Kette (Imagick → convert CLI → GD)
- ICC-Profil: Zweistufiger Fallback (Imagick → convert CLI)
- Cache-Key berücksichtigt nun auch Gamma- und ICC-Einstellungen
- Sprachdateien: Neue Übersetzungen für Gamma und ICC-Profil (DE/EN)

### Hintergrund

PDF-Viewer wie macOS Preview nutzen Display Color Management (z.B. Display P3), wodurch dunkle Farben satter und heller erscheinen. Browser zeigen Thumbnails ohne dieses Mapping, was insbesondere bei dunklen Grüntönen zu einem nahezu schwarzen Ergebnis führen kann. Der Wechsel auf PNG als Standardformat sowie die optionalen Gamma- und ICC-Features lösen dieses Problem.

## 10.2.0

- Neuer PDF-Thumbnail Media-Manager-Effekt
- Unterstützung für pdftoppm, pdftocairo, Ghostscript und Imagick
- Automatische Tool-Erkennung und Fallback-Kette

## 10.1.1

- TCPDF Update auf 6.10.1
