#!/usr/bin/env node

/**
 * Legacy compatibility wrapper
 * Redirects to the new GitHub distribution updater
 */

console.log('🔄 Redirecting to new PDF.js GitHub distribution updater...\n');

const PdfJsUpdater = require('./update-pdfjs-dist.js');
const updater = new PdfJsUpdater();

// Run the update
updater.installVersion().catch(error => {
    console.error('Update failed:', error.message);
    process.exit(1);
});

// Old build-pdfjs.js content is deprecated - using GitHub releases now

const PDFJS_SOURCE = path.join(__dirname, '..', 'node_modules', 'pdfjs-dist');
const ASSETS_TARGET = path.join(__dirname, '..', 'assets', 'vendor');

async function ensureDirectoryExists(dir) {
    try {
        await fs.mkdir(dir, { recursive: true });
        console.log(`✓ Created directory: ${dir}`);
    } catch (error) {
        if (error.code !== 'EEXIST') {
            throw error;
        }
    }
}

async function copyFile(source, target) {
    try {
        await fs.copyFile(source, target);
        console.log(`✓ Copied: ${path.basename(source)}`);
    } catch (error) {
        console.error(`✗ Failed to copy ${path.basename(source)}:`, error.message);
        throw error;
    }
}

async function copyDirectory(source, target) {
    try {
        await ensureDirectoryExists(target);
        
        const items = await fs.readdir(source, { withFileTypes: true });
        
        for (const item of items) {
            const sourcePath = path.join(source, item.name);
            const targetPath = path.join(target, item.name);
            
            if (item.isDirectory()) {
                await copyDirectory(sourcePath, targetPath);
            } else {
                await copyFile(sourcePath, targetPath);
            }
        }
    } catch (error) {
        console.error(`✗ Failed to copy directory ${source}:`, error.message);
        throw error;
    }
}

async function getVersion() {
    try {
        const packagePath = path.join(PDFJS_SOURCE, 'package.json');
        const packageContent = await fs.readFile(packagePath, 'utf8');
        const packageData = JSON.parse(packageContent);
        return packageData.version;
    } catch (error) {
        console.error('✗ Could not read PDF.js version:', error.message);
        return 'unknown';
    }
}

async function updatePackageYml(version) {
    try {
        const packageYmlPath = path.join(__dirname, '..', 'package.yml');
        let content = await fs.readFile(packageYmlPath, 'utf8');
        
        // Update PDF.js version in package.yml
        content = content.replace(/pdfjs:\s*'[^']*'/, `pdfjs: '${version}'`);
        
        await fs.writeFile(packageYmlPath, content, 'utf8');
        console.log(`✓ Updated package.yml with PDF.js version: ${version}`);
    } catch (error) {
        console.error('✗ Failed to update package.yml:', error.message);
    }
}

async function buildPdfjs() {
    try {
        console.log('🔨 Starting PDF.js build process...\n');
        
        // Check if PDF.js source exists
        try {
            await fs.access(PDFJS_SOURCE);
        } catch (error) {
            throw new Error(`PDF.js source not found. Run 'npm install' first.`);
        }
        
        const version = await getVersion();
        console.log(`📦 PDF.js version: ${version}\n`);
        
        // Backup existing LICENSE if it exists and is different
        const licensePath = path.join(ASSETS_TARGET, 'LICENSE');
        let existingLicense = null;
        try {
            existingLicense = await fs.readFile(licensePath, 'utf8');
        } catch (error) {
            // File doesn't exist, that's okay
        }
        
        // Create target directories
        await ensureDirectoryExists(ASSETS_TARGET);
        await ensureDirectoryExists(path.join(ASSETS_TARGET, 'build'));
        await ensureDirectoryExists(path.join(ASSETS_TARGET, 'web'));
        
        console.log('📂 Copying PDF.js files...');
        
        // Copy build files
        const buildFiles = [
            'pdf.mjs',
            'pdf.mjs.map', 
            'pdf.sandbox.mjs',
            'pdf.sandbox.mjs.map',
            'pdf.worker.mjs',
            'pdf.worker.mjs.map'
        ];
        
        for (const file of buildFiles) {
            const source = path.join(PDFJS_SOURCE, 'build', file);
            const target = path.join(ASSETS_TARGET, 'build', file);
            
            try {
                await copyFile(source, target);
            } catch (error) {
                console.warn(`⚠ Warning: Could not copy ${file} (may not exist in this version)`);
            }
        }
        
        // Copy web files (structure changed in PDF.js 5.x)
        const webFiles = [
            'pdf_viewer.css',
            'pdf_viewer.mjs',
            'pdf_viewer.mjs.map'
        ];
        
        for (const file of webFiles) {
            const source = path.join(PDFJS_SOURCE, 'web', file);
            const target = path.join(ASSETS_TARGET, 'web', file);
            
            try {
                await copyFile(source, target);
            } catch (error) {
                console.warn(`⚠ Warning: Could not copy ${file} (may not exist in this version)`);
            }
        }
        
        // Copy full legacy viewer from legacy/build if it exists (PDF.js 5.x has legacy support)
        const legacyBuildDir = path.join(PDFJS_SOURCE, 'legacy', 'build');
        const legacyWebDir = path.join(PDFJS_SOURCE, 'legacy', 'web');
        
        try {
            await fs.access(legacyBuildDir);
            console.log('📂 Found legacy build directory, copying legacy viewer...');
            
            // Copy legacy viewer files 
            const legacyWebFiles = [
                'debugger.css',
                'debugger.mjs', 
                'viewer.css',
                'viewer.html',
                'viewer.mjs',
                'viewer.mjs.map'
            ];
            
            for (const file of legacyWebFiles) {
                const legacySource = path.join(legacyWebDir, file);
                const target = path.join(ASSETS_TARGET, 'web', file);
                
                try {
                    await copyFile(legacySource, target);
                } catch (error) {
                    console.warn(`⚠ Legacy: Could not copy ${file} (may not exist in legacy)`);
                }
            }
            
        } catch (error) {
            console.warn('⚠ No legacy directory found - using new viewer structure only');
        }
        
        // Copy web directories
        const webDirs = ['images'];
        
        // Try to copy from legacy first, then from web
        const otherDirs = ['locale', 'standard_fonts'];
        
        for (const dir of webDirs) {
            const source = path.join(PDFJS_SOURCE, 'web', dir);
            const target = path.join(ASSETS_TARGET, 'web', dir);
            
            try {
                await copyDirectory(source, target);
                console.log(`✓ Copied directory: ${dir}`);
            } catch (error) {
                console.warn(`⚠ Warning: Could not copy directory ${dir}:`, error.message);
            }
        }
        
        // Copy standard_fonts from root directory (structure changed in 5.x)
        for (const dir of otherDirs) {
            let copied = false;
            
            // Try legacy location first
            const legacySource = path.join(PDFJS_SOURCE, 'legacy', 'web', dir);
            const target = path.join(ASSETS_TARGET, 'web', dir);
            
            try {
                await copyDirectory(legacySource, target);
                console.log(`✓ Copied directory (legacy): ${dir}`);
                copied = true;
            } catch (error) {
                // Legacy location doesn't exist, try root or other locations
            }
            
            if (!copied) {
                // Try root directory (for standard_fonts in newer versions)
                const rootSource = path.join(PDFJS_SOURCE, dir);
                
                try {
                    await copyDirectory(rootSource, target);
                    console.log(`✓ Copied directory (root): ${dir}`);
                    copied = true;
                } catch (error) {
                    // Root location doesn't exist either
                }
            }
            
            if (!copied) {
                // Try web directory (original location)
                const webSource = path.join(PDFJS_SOURCE, 'web', dir);
                
                try {
                    await copyDirectory(webSource, target);
                    console.log(`✓ Copied directory (web): ${dir}`);
                } catch (error) {
                    console.warn(`⚠ Warning: Could not copy directory ${dir} from any location`);
                }
            }
        }
        
        // Copy LICENSE
        try {
            const source = path.join(PDFJS_SOURCE, 'LICENSE');
            const target = path.join(ASSETS_TARGET, 'LICENSE');
            await copyFile(source, target);
        } catch (error) {
            console.warn('⚠ Warning: Could not copy LICENSE file');
        }
        
        // Update package.yml with new version
        await updatePackageYml(version);
        
        console.log(`\n🎉 PDF.js build completed successfully!`);
        console.log(`📋 Updated to version: ${version}`);
        console.log(`📁 Assets copied to: ${ASSETS_TARGET}`);
        
    } catch (error) {
        console.error('\n❌ Build failed:', error.message);
        process.exit(1);
    }
}

// Run the build if this script is executed directly
if (require.main === module) {
    buildPdfjs();
}

module.exports = { buildPdfjs };