#!/usr/bin/env node

const https = require('https');
const path = require('path');

/**
 * PDF.js Update Checker
 * Checks for available updates from GitHub releases
 */

class UpdateChecker {
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
        return new Promise((resolve, reject) => {
            https.get('https://api.github.com/repos/mozilla/pdf.js/releases/latest', {
                headers: { 'User-Agent': 'REDAXO-PdfOut' }
            }, (res) => {
                let data = '';
                res.on('data', chunk => data += chunk);
                res.on('end', () => {
                    try {
                        const release = JSON.parse(data);
                        resolve({
                            version: release.tag_name.replace('v', ''),
                            name: release.name,
                            publishedAt: release.published_at,
                            htmlUrl: release.html_url
                        });
                    } catch (error) {
                        reject(new Error(`Failed to parse GitHub API response: ${error.message}`));
                    }
                });
            }).on('error', reject);
        });
    }

    compareVersions(current, latest) {
        if (!current) return 'unknown';
        
        const currentParts = current.split('.').map(Number);
        const latestParts = latest.split('.').map(Number);
        
        for (let i = 0; i < Math.max(currentParts.length, latestParts.length); i++) {
            const currentPart = currentParts[i] || 0;
            const latestPart = latestParts[i] || 0;
            
            if (latestPart > currentPart) return 'outdated';
            if (latestPart < currentPart) return 'newer';
        }
        
        return 'up-to-date';
    }

    async checkUpdates() {
        try {
            console.log('🔍 PDF.js Update Checker');
            console.log('========================\n');

            console.log(`📦 Current version: ${this.currentVersion || 'Not installed'}`);
            
            const latest = await this.fetchLatestRelease();
            console.log(`📦 Latest version:  ${latest.version}`);
            console.log(`📅 Released:        ${new Date(latest.publishedAt).toLocaleDateString()}`);
            
            const status = this.compareVersions(this.currentVersion, latest.version);
            
            process.stdout.write('📊 Status:          ');
            
            switch (status) {
                case 'up-to-date':
                    console.log('✅ Up to date');
                    break;
                case 'outdated':
                    console.log('🔄 Update available');
                    console.log('\n🚀 To update, run:');
                    console.log('   npm run update-pdfjs');
                    console.log('   # or');
                    console.log('   ./scripts/update-pdfjs.sh');
                    break;
                case 'newer':
                    console.log('🔮 Ahead of latest release');
                    break;
                case 'unknown':
                    console.log('❓ Unknown (not installed)');
                    console.log('\n🚀 To install, run:');
                    console.log('   npm run install-pdfjs');
                    break;
            }
            
            console.log(`\n🔗 Release info: ${latest.htmlUrl}`);
            
        } catch (error) {
            console.error('\n❌ Check failed:', error.message);
            process.exit(1);
        }
    }
}

// Run if called directly
if (require.main === module) {
    const checker = new UpdateChecker();
    checker.checkUpdates();
}

module.exports = UpdateChecker;