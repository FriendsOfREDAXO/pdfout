#!/bin/bash

# REDAXO pdfout - PDF.js GitHub Release Update Script
# Usage: ./scripts/update-pdfjs.sh [version]
# Example: ./scripts/update-pdfjs.sh 5.4.394
# If no version is specified, updates to the latest version

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

cd "${PROJECT_DIR}"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}🔄 REDAXO pdfout - PDF.js GitHub Release Update${NC}"
echo "====================================================="

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo -e "${RED}❌ Node.js is not installed. Please install Node.js first.${NC}"
    exit 1
fi

# Check if unzip is available (for extraction)
if ! command -v unzip &> /dev/null; then
    echo -e "${RED}❌ unzip command is not available. Please install unzip.${NC}"
    exit 1
fi

echo -e "${YELLOW}📋 Current setup:${NC}"

# Show current version from package.json
if [ -f "package.json" ]; then
    CURRENT_VERSION=$(node -p "require('./package.json').pdfjs?.currentVersion || 'Not set'" 2>/dev/null || echo "Not installed")
    echo "   Current PDF.js: ${CURRENT_VERSION}"
else
    echo "   No package.json found - first time setup"
fi

# Check if a specific version was provided
TARGET_VERSION=${1:-""}

if [ -z "$TARGET_VERSION" ]; then
    echo -e "${YELLOW}🔍 Will fetch latest version from GitHub releases...${NC}"
else
    echo -e "${YELLOW}🎯 Target version: ${TARGET_VERSION}${NC}"
fi

echo ""
echo -e "${YELLOW}📦 Updating PDF.js from GitHub releases...${NC}"

# Run the Node.js updater
if [ -z "$TARGET_VERSION" ]; then
    node scripts/update-pdfjs-dist.js
else
    node scripts/update-pdfjs-dist.js "$TARGET_VERSION"
fi

echo ""
echo -e "${GREEN}✅ Update completed successfully!${NC}"
echo "====================================================="

# Show final version
FINAL_VERSION=$(node -p "require('./package.json').pdfjs?.currentVersion || 'unknown'" 2>/dev/null || echo "unknown")
echo -e "   Updated to PDF.js: ${GREEN}${FINAL_VERSION}${NC}"
echo "   Assets location: assets/vendor/"
echo "   Source: GitHub Releases (complete distribution)"

echo ""
echo -e "${YELLOW}📝 Next steps:${NC}"
echo "   1. Test the PDF viewer functionality" 
echo "   2. Commit the changes to git"
echo "   3. Update any documentation if needed"
echo ""
echo -e "${GREEN}🎉 Happy coding!${NC}"