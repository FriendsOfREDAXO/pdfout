# PDF.js aktualisieren

pdfout liefert den PDF.js-Viewer unter `assets/vendor/` aus. Aktualisiert wird er aus den offiziellen
[GitHub-Releases](https://github.com/mozilla/pdf.js/releases) – mit einem Befehl.

## Aktualisieren

```bash
./scripts/update-pdfjs.sh            # neueste Version
./scripts/update-pdfjs.sh 6.4.299    # bestimmte Version
npm run check-updates                # nur prüfen, ob es eine neue Version gibt
```

Voraussetzungen: Node.js 14 oder neuer, `curl`, `unzip`.

Danach den Viewer testen (Desktop und iPhone/iPad), `CHANGELOG.md` ergänzen und die Addon-Version in
`package.yml` erhöhen – die Version dient dem Viewer als Cache-Busting.

## Was das Skript macht

1. Release ermitteln: neueste oder die angegebene Version
2. ZIP der gewählten Variante herunterladen (`legacy` oder `modern`)
3. `assets/vendor/build` und `assets/vendor/web` leeren und neu befüllen (keine Reste alter Versionen)
4. Module von `.mjs` in `.js` umbenennen und die Verweise anpassen
5. `viewer.html` um das Toolbar-Skript `assets/viewer-toolbar.js` ergänzen
6. Version in `package.json` und `package.yml` (`pdfjs`) eintragen

## Einstellungen (`package.json` → `pdfjs`)

```json
{
  "pdfjs": {
    "build": "legacy",
    "moduleExtension": "js",
    "excludeComponents": ["cmaps", "iccs"],
    "currentVersion": "6.4.299",
    "currentBuild": "legacy"
  }
}
```

| Schlüssel | Bedeutung |
| --- | --- |
| `build` | `legacy` (Standard) läuft auch in älteren Browsern, etwa auf iPhones mit älterem iOS. `modern` ist etwas kleiner, läuft aber nur in aktuellen Browsern. |
| `moduleExtension` | `js` (Standard): Module werden als `.js` ausgeliefert. Viele Server kennen `.mjs` nicht und senden den falschen Dateityp – Browser führen die Module dann nicht aus und der Viewer bleibt leer (z. B. nginx unter Plesk, das statische Dateien selbst ausliefert und `AddType` in der `.htaccess` nicht beachtet). `mjs` behält die Originaldateien. |
| `excludeComponents` | Ordner oder Dateien, die nicht übernommen werden. `cmaps` (Zeichentabellen für chinesische, japanische und koreanische Schriften, ca. 1,6 MB) und `iccs` (Farbprofile für den Druck). Wer CJK-PDFs anzeigt, entfernt `cmaps` aus der Liste. |
| `currentVersion`, `currentBuild` | vom Skript gepflegt |

## Dateien

```
assets/
├── viewer-toolbar.js    # blendet Leisten-Gruppen aus, Rücksprung-Knopf (returnUrl)
├── toolbar-builder.js   # Backend-Seite „PDF.js-Toolbar“
└── vendor/              # PDF.js – wird vom Skript ersetzt, nicht von Hand ändern
    ├── build/           # pdf.js, pdf.worker.js, pdf.sandbox.js
    └── web/             # viewer.html, viewer.js, viewer.css, locale/, images/, …
```

Eigene Anpassungen gehören in `viewer-toolbar.js`, nicht in `assets/vendor/`. Die Leisten-Gruppen werden über
Element-IDs des Viewers ausgeblendet; nach einem größeren Update prüfen, ob die IDs in `viewer.html` noch existieren
(Liste in `viewer-toolbar.js`, `FEATURE_MAP`).

## Fehlersuche

| Meldung | Lösung |
| --- | --- |
| `curl` oder `unzip` nicht gefunden | installieren (`apt install curl unzip`, unter macOS vorhanden) |
| Download fehlgeschlagen | Netzwerk prüfen; die Release-Seite auf GitHub muss erreichbar sein |
| Release nicht gefunden | Versionsnummer ohne „v“ angeben, z. B. `6.4.299` |
| Viewer bleibt nach dem Update leer | Browser-Konsole prüfen: Dateityp der `.js`-Dateien, Fehlermeldungen von PDF.js |
