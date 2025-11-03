# PDF.js Update Workflow

Dieses Dokument beschreibt den automatisierten Workflow zum Aktualisieren von PDF.js 5.x in diesem REDAXO-Addon.

## 🎯 Überblick

Statt PDF.js manuell herunterzuladen, verwenden wir jetzt **GitHub Releases** für automatisierte Updates. Dies stellt sicher, dass wir immer die vollständige Distribution (inkl. Viewer) erhalten und macht Updates einfacher, sicherer und nachvollziehbarer.

## 🚀 Schnellstart

### Ein-Befehl Update (empfohlen)
```bash
# Update auf die neueste Version
./scripts/update-pdfjs.sh

# Update auf eine spezifische Version
./scripts/update-pdfjs.sh 5.4.394
```

### NPM-Scripts
```bash
# 1. Aktuelle Version prüfen und Updates suchen
npm run check-updates

# 2. PDF.js auf neueste Version aktualisieren  
npm run update-pdfjs

# 3. PDF.js installieren (erste Einrichtung)
npm run install-pdfjs
```

## 📋 Verfügbare Scripts

| Script | Beschreibung |
|--------|-------------|
| `npm run update-pdfjs` | Lädt neueste PDF.js Distribution von GitHub |
| `npm run check-updates` | Zeigt verfügbare Updates an |
| `npm run install-pdfjs` | Installiert PDF.js (erste Einrichtung) |
| `./scripts/update-pdfjs.sh` | Shell-Script für manuelle Updates |

## 📁 Dateistruktur

```
├── package.json              # Konfiguration + Exclusion-Liste
├── scripts/
│   ├── update-pdfjs-dist.js  # GitHub Release Downloader
│   ├── check-pdfjs-updates.js # Update-Checker
│   ├── update-pdfjs.sh       # Shell-Wrapper
│   └── build-pdfjs.js        # Legacy-Wrapper (deprecated)
└── assets/vendor/            # PDF.js 5.x Distribution (optimiert)
    ├── build/                # Core PDF.js Library
    │   ├── pdf.mjs
    │   ├── pdf.worker.mjs
    │   └── pdf.sandbox.mjs
    ├── web/                  # Kompletter Viewer
    │   ├── viewer.html       # Hauptviewer
    │   ├── viewer.css
    │   ├── viewer.mjs
    │   ├── debugger.css
    │   ├── debugger.mjs
    │   ├── images/           # Toolbar-Icons
    │   ├── locale/           # Übersetzungen
    │   │   └── locale.json
    │   ├── standard_fonts/   # Embedded Fonts
    │   └── wasm/            # WebAssembly Module
    └── LICENSE

# Ausgeschlossen für europäische PDFs:
# ├── cmaps/              # Character Maps (CJK-Schriften) - 1.6MB gespart
# └── iccs/               # Color Profiles (Druckindustrie) - zusätzlich gespart
```

## 🎛️ Konfiguration

Die Exclusion-Liste in `package.json` steuert, welche Komponenten übersprungen werden:

```json
{
  "pdfjs": {
    "currentVersion": "5.4.394",
    "source": "github-releases",
    "excludeComponents": [
      "cmaps",  // Character Maps für CJK-Schriften (Chinesisch/Japanisch/Koreanisch)
      "iccs"    // ICC Color Profiles für Druckindustrie
    ]
  }
}
```

### Was wird ausgeschlossen?

- **`cmaps/`** (1.6MB): Character Maps für asiatische Schriften - nicht benötigt für deutsche/europäische PDFs
- **`iccs/`** (klein): ICC Color Profiles für professionellen Druck - meist nicht erforderlich

### Exclusions anpassen

Wenn du doch CJK-Unterstützung brauchst, entferne einfach `"cmaps"` aus der Liste:

```json
"excludeComponents": [
  "iccs"  // Nur Color Profiles ausschließen
]
```

## 🔄 Update-Prozess im Detail

1. **GitHub API-Abfrage**: Neueste Release-Version ermitteln
2. **Distribution-Download**: Vollständige ZIP-Datei von GitHub herunterladen
3. **Extraktion**: ZIP in temporäres Verzeichnis entpacken
4. **Asset-Kopie**: Alle benötigten Dateien nach `assets/vendor/` kopieren
5. **Version-Update**: `package.json` und `package.yml` aktualisieren
6. **Aufräumen**: Temporäre Dateien entfernen

## 🆕 Was ist neu in PDF.js 5.x?

### ✅ Neue Features
- **Erweiterte Annotation-Tools**: Neue Editor-Funktionen
- **Verbesserte Performance**: Optimierte Rendering-Engine  
- **WebAssembly-Module**: Bessere Decoder für spezielle Formate
- **Neue Icons**: Aktualisierte Toolbar-Icons
- **Erweiterte Lokalisierung**: Mehr Sprachen unterstützt

### ⚠️ Breaking Changes
- **Neue Dateistruktur**: `pdf_viewer.*` für neue APIs, `viewer.*` für Legacy
- **Entfernte Legacy-Files**: Einige alte Debugger-Dateien nicht mehr verfügbar
- **Geänderte Pfade**: Standard-Fonts jetzt im Root-Verzeichnis
- **Neue WASM-Module**: Zusätzliche WebAssembly-Dateien erforderlich

### 🔧 Automatische Migration
Unser Build-System behandelt alle Breaking Changes automatisch:
- ✅ Erkennt neue vs. alte Dateistrukturen
- ✅ Kopiert Dateien von korrekten Quellen  
- ✅ Behält Backward-Kompatibilität bei
- ✅ Aktualisiert Versionsinformationen

## 🛠️ Systemvoraussetzungen

```bash
# Erforderlich
node --version    # >= 14.0.0
curl --version    # Für Downloads
unzip --version   # Für Extraktion

# Optional (für Shell-Script)
bash --version    # Moderne Shell
```

## 🔍 Version prüfen

```bash
# Aktuelle Version anzeigen
node -p "require('./package.json').pdfjs.currentVersion"

# Oder in package.yml
grep "pdfjs:" package.yml

# Verfügbare Updates prüfen
npm run check-updates
```

## 🚨 Troubleshooting

### Problem: "curl not found"
```bash
# macOS (mit Homebrew)
brew install curl

# Ubuntu/Debian  
sudo apt update && sudo apt install curl

# Windows (Git Bash empfohlen)
# curl ist in Git Bash enthalten
```

### Problem: "unzip not found"
```bash
# macOS
# unzip ist standardmäßig installiert

# Ubuntu/Debian
sudo apt update && sudo apt install unzip

# Windows
# Verwende Git Bash oder installiere 7-Zip
```

### Problem: "Download failed"
```bash
# Netzwerk-Konnektivität prüfen
curl -I https://github.com

# Manuelle GitHub-URL testen
curl -L -I https://github.com/mozilla/pdf.js/releases/latest
```

### Problem: "Node.js version"
```bash
# Node.js Version prüfen
node --version

# Sollte >= 14.0.0 sein
# Neuere Version installieren falls nötig
```

## 🎯 Vorteile des neuen Systems

- ✅ **Komplette Distribution**: Immer vollständiger Viewer mit allen Dateien
- ✅ **Ein-Befehl Updates**: `./scripts/update-pdfjs.sh`
- ✅ **Automatische Erkennung**: Neue vs. alte Dateistrukturen
- ✅ **GitHub Integration**: Direkt von offiziellen Releases
- ✅ **Versionskontrolle**: Exakte Versionen dokumentiert
- ✅ **Zukunftssicher**: Unterstützt alle kommenden PDF.js Versionen
- ✅ **REDAXO-optimiert**: Hält bestehende Asset-Struktur bei
- ✅ **Keine NPM-Dependencies**: Kein node_modules Overhead

## 🔗 Weiterführende Informationen

- [PDF.js GitHub Repository](https://github.com/mozilla/pdf.js)
- [PDF.js Releases](https://github.com/mozilla/pdf.js/releases)
- [PDF.js 5.x Migration Guide](https://github.com/mozilla/pdf.js/wiki/Migration-Guide)
- [REDAXO Addon Entwicklung](https://redaxo.org/doku/master/addon-entwicklung)