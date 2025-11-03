# PDF.js Update Workflow

Dieses Dokument beschreibt den neuen automatisierten Workflow zum Aktualisieren von PDF.js in diesem REDAXO-Addon.

## 🎯 Überblick

Statt PDF.js manuell herunterzuladen und zu kopieren, verwenden wir jetzt NPM für automatisierte Updates. Dies macht Updates einfacher, sicherer und nachvollziehbarer.

## 🚀 Schnellstart

### Ein-Befehl Update (empfohlen)
```bash
# Update auf die neueste Version
./scripts/update-pdfjs.sh

# Update auf eine spezifische Version
./scripts/update-pdfjs.sh 5.4.394
```

### Manuelle Schritte (falls gewünscht)
```bash
# 1. Abhängigkeiten installieren/aktualisieren  
npm install

# 2. PDF.js Assets bauen
npm run build-pdfjs

# 3. Version in package.yml prüfen
cat package.yml | grep pdfjs
```

## 📋 Verfügbare NPM-Scripts

| Script | Beschreibung |
|--------|-------------|
| `npm run update-pdfjs` | Aktualisiert PDF.js und baut Assets |
| `npm run build-pdfjs` | Kopiert Assets aus node_modules |
| `npm run check-updates` | Zeigt verfügbare Updates an |
| `npm run install-pdfjs` | Installiert und baut (erste Einrichtung) |

## 📁 Dateistruktur

```
├── package.json              # NPM-Konfiguration
├── package-lock.json         # NPM-Lockfile (wird automatisch erstellt)
├── node_modules/             # NPM-Abhängigkeiten (wird ignoriert)
├── scripts/
│   ├── build-pdfjs.js       # Build-Script (Node.js)
│   └── update-pdfjs.sh      # Convenience-Script (Bash)
└── assets/vendor/            # PDF.js Assets (wird aktualisiert)
    ├── build/
    ├── web/
    └── LICENSE
```

## 🔄 Update-Prozess im Detail

1. **Abhängigkeit aktualisieren**: NPM lädt neue PDF.js Version
2. **Assets kopieren**: Build-Script kopiert benötigte Dateien
3. **Version synchronisieren**: `package.yml` wird automatisch aktualisiert
4. **Bereit für Git**: Alle Änderungen können committet werden

## 🛠️ Ersteinrichtung

Wenn du das System zum ersten Mal verwendest:

```bash
# Node.js und npm müssen installiert sein
node --version  # >= 14.0.0
npm --version

# Dann einfach:
./scripts/update-pdfjs.sh
```

## 📝 Was wird aktualisiert?

### Automatisch kopierte Dateien:
- `build/`: Alle .mjs und .map Dateien
- `web/`: Viewer-Dateien und Stylesheets  
- `web/images/`: Icon-Dateien
- `web/locale/`: Übersetzungsdateien
- `web/standard_fonts/`: Font-Dateien
- `LICENSE`: PDF.js Lizenz

### Automatisch aktualisierte Konfiguration:
- `package.yml`: PDF.js Version wird synchronisiert
- `package.json`: NPM-Abhängigkeit wird aktualisiert

## 🔍 Version prüfen

```bash
# Aktuelle Version anzeigen
npm list pdfjs-dist

# Verfügbare Updates prüfen
npm outdated pdfjs-dist

# Installierte Version in package.yml prüfen
grep "pdfjs:" package.yml
```

## 🚨 Troubleshooting

### Problem: "Node.js not found"
```bash
# macOS (mit Homebrew)
brew install node

# Ubuntu/Debian
sudo apt update && sudo apt install nodejs npm

# Windows
# Lade von https://nodejs.org herunter
```

### Problem: "Permission denied"
```bash
# Script ausführbar machen
chmod +x scripts/update-pdfjs.sh
```

### Problem: "Build failed"
```bash
# Abhängigkeiten neu installieren
rm -rf node_modules package-lock.json
npm install
npm run build-pdfjs
```

## 🎯 Vorteile des neuen Workflows

- ✅ **Ein-Befehl Updates**: `./scripts/update-pdfjs.sh`
- ✅ **Versionskontrolle**: Exakte Versionen in package.json
- ✅ **Automatische Synchronisation**: package.yml wird automatisch aktualisiert
- ✅ **Reproduzierbar**: package-lock.json sorgt für konsistente Builds
- ✅ **Sicherheit**: NPM-Registry mit Integritätsprüfung
- ✅ **Rückgängig machen**: Git-History für alle Änderungen

## 🔗 Weiterführende Informationen

- [PDF.js Releases](https://github.com/mozilla/pdf.js/releases)
- [NPM pdfjs-dist Paket](https://www.npmjs.com/package/pdfjs-dist)
- [REDAXO Addon Entwicklung](https://redaxo.org/doku/master/addon-entwicklung)