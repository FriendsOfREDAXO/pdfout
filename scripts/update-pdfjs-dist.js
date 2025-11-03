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

    async fetchLatestRelease() {
        console.log('🔍 Checking for latest PDF.js release...');
        
        return new Promise((resolve, reject) => {
            https.get(GITHUB_API + '/latest', { headers: { 'User-Agent': 'REDAXO-PdfOut' } }, (res) => {
                let data = '';
                res.on('data', chunk => data += chunk);
                res.on('end', () => {
                    try {
                        const release = JSON.parse(data);
                        resolve({
                            version: release.tag_name.replace('v', ''),
                            downloadUrl: release.assets.find(asset => 
                                asset.name.includes('-dist.zip'))?.browser_download_url,
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
            if (item.isDirectory() && excludedComponents.includes(item.name)) {
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

    async updatePackageVersion(version) {
        const packagePath = path.join(__dirname, '..', 'package.json');
        const packageYmlPath = path.join(__dirname, '..', 'package.yml');
        
        // Update package.json
        try {
            const packageData = require(packagePath);
            packageData.pdfjs = packageData.pdfjs || {};
            packageData.pdfjs.currentVersion = version;
            
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
            const release = await this.fetchLatestRelease();
            const version = targetVersion || release.version;
            
            if (!release.downloadUrl) {
                throw new Error('No distribution ZIP found in latest release');
            }

            console.log(`📦 Target version: ${version}`);
            console.log(`📅 Released: ${new Date(release.publishedAt).toLocaleDateString()}\n`);

            // Check if already up to date
            if (this.currentVersion === version && !targetVersion) {
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