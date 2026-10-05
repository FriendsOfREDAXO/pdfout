#!/usr/bin/env node

const fs = require('fs').promises;
const path = require('path');
const https = require('https');
const { promisify } = require('util');
const { exec } = require('child_process');
const execAsync = promisify(exec);

/**
 * PDF.js GitHub Release Distribution Manager
 * Downloads and installs PDF.js from GitHub releases (complete distribution)
 */

const GITHUB_API = 'https://api.github.com/repos/mozilla/pdf.js/releases';
const ASSETS_TARGET = path.join(__dirname, '..', 'assets', 'vendor');
const TEMP_DIR = path.join(__dirname, '..', '.tmp');

class PdfJsUpdater {
    constructor() {
        this.currentVersion = this.getCurrentVersion();
        this.currentVariant = this.getCurrentVariant();
    }

    getCurrentVersion() {
        try {
            const packagePath = path.join(__dirname, '..', 'package.json');
            const packageData = require(packagePath);
            return packageData.pdfjs?.currentVersion || null;
        } catch (error) {
            return null;
        }
    }

    /**
     * Build-Variante aus package.json (pdfjs.build): "legacy" (Standard) läuft auch auf älteren
     * Browsern, u. a. Safari/iOS vor Version 18 – die moderne Variante nur in aktuellen Browsern.
     */
    getBuildVariant() {
        try {
            const packageData = require(path.join(__dirname, '..', 'package.json'));
            return packageData.pdfjs?.build === 'modern' ? 'modern' : 'legacy';
        } catch (error) {
            return 'legacy';
        }
    }

    getCurrentVariant() {
        try {
            return require(path.join(__dirname, '..', 'package.json')).pdfjs?.currentBuild || 'modern';
        } catch (error) {
            return 'modern';
        }
    }

    async fetchRelease(version = null) {
        const variant = this.getBuildVariant();
        const url = version ? `${GITHUB_API}/tags/v${version.replace(/^v/, '')}` : `${GITHUB_API}/latest`;
        console.log(version ? `🔍 Fetching PDF.js release ${version} (${variant})...` : `🔍 Checking for latest PDF.js release (${variant})...`);

        return new Promise((resolve, reject) => {
            https.get(url, { headers: { 'User-Agent': 'REDAXO-PdfOut' } }, (res) => {
                let data = '';
                res.on('data', chunk => data += chunk);
                res.on('end', () => {
                    try {
                        const release = JSON.parse(data);
                        if (!release.tag_name) {
                            reject(new Error(`Release not found: ${release.message || url}`));
                            return;
                        }
                        const assets = release.assets || [];
                        const asset = variant === 'legacy'
                            ? assets.find(a => a.name.endsWith('-legacy-dist.zip'))
                            : assets.find(a => a.name.endsWith('-dist.zip') && !a.name.includes('-legacy-'));
                        resolve({
                            version: release.tag_name.replace('v', ''),
                            variant,
                            downloadUrl: asset?.browser_download_url,
                            name: release.name,
                            publishedAt: release.published_at
                        });
                    } catch (error) {
                        reject(new Error(`Failed to parse GitHub API response: ${error.message}`));
                    }
                });
            }).on('error', reject);
        });
    }

    async fetchLatestRelease() {
        return this.fetchRelease(null);
    }

    async downloadFile(url, destinationPath) {
        console.log(`📥 Downloading PDF.js distribution...`);
        
        try {
            // Use curl for more reliable downloads with redirect handling
            await execAsync(`curl -L -o "${destinationPath}" "${url}"`);
            console.log('✓ Download completed');
        } catch (error) {
            throw new Error(`Download failed: ${error.message}`);
        }
    }

    async ensureDirectory(dir) {
        try {
            await fs.mkdir(dir, { recursive: true });
        } catch (error) {
            if (error.code !== 'EEXIST') throw error;
        }
    }

    async extractZip(zipPath, extractPath) {
        console.log('📦 Extracting PDF.js distribution...');
        
        // Verwende unzip command (macOS/Linux) oder alternativ node module
        try {
            await execAsync(`unzip -q "${zipPath}" -d "${extractPath}"`);
            console.log('✓ Extraction completed');
        } catch (error) {
            throw new Error(`Failed to extract ZIP: ${error.message}`);
        }
    }

    async copyDirectory(source, target) {
        await this.ensureDirectory(target);
        
        const items = await fs.readdir(source, { withFileTypes: true });
        
        for (const item of items) {
            const sourcePath = path.join(source, item.name);
            const targetPath = path.join(target, item.name);
            
            if (item.isDirectory()) {
                await this.copyDirectory(sourcePath, targetPath);
            } else {
                await fs.copyFile(sourcePath, targetPath);
            }
        }
    }

    async copyDirectoryWithExclusions(source, target) {
        await this.ensureDirectory(target);
        
        // Get excluded components from package.json
        const packagePath = path.join(__dirname, '..', 'package.json');
        let excludedComponents = [];
        try {
            const packageData = require(packagePath);
            excludedComponents = packageData.pdfjs?.excludeComponents || [];
        } catch (error) {
            console.warn('⚠ Could not read exclusion config, copying all files');
        }
        
        const items = await fs.readdir(source, { withFileTypes: true });
        
        for (const item of items) {
            const sourcePath = path.join(source, item.name);
            const targetPath = path.join(target, item.name);
            
            // Check if this directory should be excluded
            if (excludedComponents.includes(item.name)) {
                console.log(`⏭ Skipped: ${item.name} (excluded)`);
                continue;
            }
            
            if (item.isDirectory()) {
                await this.copyDirectory(sourcePath, targetPath);
            } else {
                await fs.copyFile(sourcePath, targetPath);
            }
        }
    }

    /**
     * Module als .js statt .mjs (package.json: pdfjs.moduleExtension, Standard "js").
     * Viele Server (z. B. nginx unter Plesk, der statische Dateien selbst ausliefert) kennen .mjs nicht und
     * senden "application/octet-stream" – Browser führen Module mit falschem Typ nicht aus, der Viewer bleibt leer.
     */
    async convertModulesToJs() {
        let extension = 'js';
        try {
            extension = require(path.join(__dirname, '..', 'package.json')).pdfjs?.moduleExtension === 'mjs' ? 'mjs' : 'js';
        } catch (error) {
            // Standard
        }
        if (extension === 'mjs') {
            return;
        }
        const rewrite = (code) => code
            // Verweise zwischen den pdf.js-Dateien (Viewer, Bibliothek, Worker, Sandbox, Debugger)
            .replace(/((?:\.\.\/build\/|\.\/)?(?:pdf|pdf\.worker|pdf\.sandbox|viewer|debugger))\.mjs(?=["'`])/g, '$1.js')
            .replace(/sourceMappingURL=([\w.-]+)\.mjs\.map/g, 'sourceMappingURL=$1.js.map');
        for (const dir of ['build', 'web']) {
            const dirPath = path.join(ASSETS_TARGET, dir);
            for (const name of await fs.readdir(dirPath)) {
                const filePath = path.join(dirPath, name);
                if (name.endsWith('.mjs')) {
                    const code = await fs.readFile(filePath, 'utf8');
                    await fs.writeFile(filePath.replace(/\.mjs$/, '.js'), rewrite(code), 'utf8');
                    await fs.unlink(filePath);
                } else if (name.endsWith('.mjs.map')) {
                    await fs.rename(filePath, filePath.replace(/\.mjs\.map$/, '.js.map'));
                } else if (name === 'viewer.html') {
                    await fs.writeFile(filePath, rewrite(await fs.readFile(filePath, 'utf8')), 'utf8');
                }
            }
        }
        console.log('✓ Module als .js gespeichert (Server ohne .mjs-MIME-Typ)');
    }

    async patchViewerHtml() {
        const viewerHtmlPath = path.join(ASSETS_TARGET, 'web', 'viewer.html');

        try {
            let viewerHtml = await fs.readFile(viewerHtmlPath, 'utf8');
            const ymlContent = await fs.readFile(path.join(__dirname, '..', 'package.yml'), 'utf8');
            const addonVersion = (ymlContent.match(/^version:\s*'([^']+)'/m) || [])[1] || Date.now();
            const scriptTag = `  <script src="../../viewer-toolbar.js?v=${addonVersion}"></script>\n`;
            const viewerScript = viewerHtml.includes('src="viewer.js"') ? 'viewer.js' : 'viewer.mjs';

            if (!viewerHtml.includes('viewer-toolbar.js')) {
                viewerHtml = viewerHtml.replace(
                    `  <script src="${viewerScript}" type="module"></script>\n`,
                    `  <script src="${viewerScript}" type="module"></script>\n` + scriptTag,
                );

                await fs.writeFile(viewerHtmlPath, viewerHtml, 'utf8');
                console.log('✓ Patched viewer.html with toolbar overlay');
            }
        } catch (error) {
            console.warn('⚠ Could not patch viewer.html:', error.message);
        }
    }

    async updatePackageVersion(version) {
        const packagePath = path.join(__dirname, '..', 'package.json');
        const packageYmlPath = path.join(__dirname, '..', 'package.yml');
        
        // Update package.json
        try {
            const packageData = require(packagePath);
            packageData.pdfjs = packageData.pdfjs || {};
            packageData.pdfjs.currentVersion = version;
            packageData.pdfjs.currentBuild = this.getBuildVariant();
            
            await fs.writeFile(packagePath, JSON.stringify(packageData, null, 2) + '\n', 'utf8');
            console.log('✓ Updated package.json');
        } catch (error) {
            console.warn('⚠ Failed to update package.json:', error.message);
        }
        
        // Update package.yml
        try {
            let ymlContent = await fs.readFile(packageYmlPath, 'utf8');
            ymlContent = ymlContent.replace(/pdfjs:\s*'[^']*'/, `pdfjs: '${version}'`);
            await fs.writeFile(packageYmlPath, ymlContent, 'utf8');
            console.log('✓ Updated package.yml');
        } catch (error) {
            console.warn('⚠ Failed to update package.yml:', error.message);
        }
    }

    async cleanup() {
        try {
            const { stdout } = await execAsync(`rm -rf "${TEMP_DIR}"`);
            console.log('✓ Cleaned up temporary files');
        } catch (error) {
            console.warn('⚠ Cleanup warning:', error.message);
        }
    }

    async installVersion(targetVersion = null) {
        try {
            console.log('🚀 PDF.js GitHub Distribution Updater');
            console.log('=====================================\n');

            // Get latest or specific version
            const release = await this.fetchRelease(targetVersion);
            const version = release.version;
            
            if (!release.downloadUrl) {
                throw new Error('No distribution ZIP found in latest release');
            }

            console.log(`📦 Target version: ${version}`);
            console.log(`📅 Released: ${new Date(release.publishedAt).toLocaleDateString()}\n`);

            // Check if already up to date
            if (this.currentVersion === version && this.currentVariant === release.variant && !targetVersion) {
                console.log('✓ PDF.js is already up to date!');
                return;
            }

            // Setup directories
            await this.ensureDirectory(TEMP_DIR);
            await this.ensureDirectory(ASSETS_TARGET);

            const zipPath = path.join(TEMP_DIR, `pdfjs-${version}-dist.zip`);
            const extractPath = path.join(TEMP_DIR, 'extracted');

            // Download distribution
            await this.downloadFile(release.downloadUrl, zipPath);

            // Extract
            await this.ensureDirectory(extractPath);
            await this.extractZip(zipPath, extractPath);

            // Find extracted content (may be in subfolder)
            const extractedItems = await fs.readdir(extractPath);
            let sourcePath = extractPath;
            
            // If there's only one directory, that's probably our content
            if (extractedItems.length === 1) {
                const itemPath = path.join(extractPath, extractedItems[0]);
                const stat = await fs.stat(itemPath);
                if (stat.isDirectory()) {
                    sourcePath = itemPath;
                }
            }

            console.log('📁 Installing PDF.js files...');

            // alte Version vollständig entfernen (sonst bleiben verwaiste Dateien liegen)
            await fs.rm(path.join(ASSETS_TARGET, 'build'), { recursive: true, force: true });
            await fs.rm(path.join(ASSETS_TARGET, 'web'), { recursive: true, force: true });

            // Copy build directory
            const buildSource = path.join(sourcePath, 'build');
            const buildTarget = path.join(ASSETS_TARGET, 'build');
            
            try {
                await this.copyDirectory(buildSource, buildTarget);
                console.log('✓ Copied build files');
            } catch (error) {
                console.warn('⚠ Build files not found or failed to copy');
            }

            // Copy web directory with optional exclusions
            const webSource = path.join(sourcePath, 'web');
            const webTarget = path.join(ASSETS_TARGET, 'web');
            
            try {
                await this.copyDirectoryWithExclusions(webSource, webTarget);
                console.log('✓ Copied web files (viewer, CSS, images, locales)');
            } catch (error) {
                console.warn('⚠ Web files not found or failed to copy');
            }

            // Copy LICENSE
            try {
                const licenseSource = path.join(sourcePath, 'LICENSE');
                const licenseTarget = path.join(ASSETS_TARGET, 'LICENSE');
                await fs.copyFile(licenseSource, licenseTarget);
                console.log('✓ Copied LICENSE');
            } catch (error) {
                console.warn('⚠ LICENSE file not found or failed to copy');
            }

            await this.convertModulesToJs();
            await this.patchViewerHtml();

            // Update version info
            await this.updatePackageVersion(version);

            // Cleanup
            await this.cleanup();

            console.log('\n🎉 PDF.js update completed successfully!');
            console.log(`📋 Updated from ${this.currentVersion || 'unknown'} to ${version}`);
            console.log(`📁 Assets location: ${ASSETS_TARGET}`);
            console.log('\n📝 Next steps:');
            console.log('   1. Test the PDF viewer functionality');
            console.log('   2. Commit changes to git');
            console.log('   3. Update documentation if needed');

        } catch (error) {
            console.error('\n❌ Update failed:', error.message);
            await this.cleanup();
            process.exit(1);
        }
    }
}

// CLI interface
if (require.main === module) {
    const targetVersion = process.argv[2];
    const updater = new PdfJsUpdater();
    updater.installVersion(targetVersion);
}

module.exports = PdfJsUpdater;