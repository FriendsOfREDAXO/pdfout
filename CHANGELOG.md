# Changelog

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
