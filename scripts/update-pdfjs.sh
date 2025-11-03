#!/bin/bash

# REDAXO pdfout - PDF.js Update Script
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

echo -e "${GREEN}🔄 REDAXO pdfout - PDF.js Update Script${NC}"
echo "=================================================="

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo -e "${RED}❌ Node.js is not installed. Please install Node.js first.${NC}"
    exit 1
fi

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo -e "${RED}❌ npm is not installed. Please install npm first.${NC}"
    exit 1
fi

echo -e "${YELLOW}📋 Current setup:${NC}"

# Show current version if package.json exists
if [ -f "package.json" ]; then
    CURRENT_VERSION=$(node -p "require('./node_modules/pdfjs-dist/package.json').version" 2>/dev/null || echo "Not installed")
    echo "   Current PDF.js: ${CURRENT_VERSION}"
else
    echo "   No package.json found - first time setup"
fi

# Check if a specific version was provided
TARGET_VERSION=${1:-"latest"}

if [ "$TARGET_VERSION" = "latest" ]; then
    echo -e "${YELLOW}🔍 Checking for latest PDF.js version...${NC}"
    LATEST_VERSION=$(npm view pdfjs-dist version)
    echo "   Latest available: ${LATEST_VERSION}"
    TARGET_VERSION="^${LATEST_VERSION}"
else
    echo -e "${YELLOW}🎯 Target version: ${TARGET_VERSION}${NC}"
    TARGET_VERSION="^${TARGET_VERSION}"
fi

echo ""
echo -e "${YELLOW}📦 Installing PDF.js ${TARGET_VERSION}...${NC}"

# Update package.json with new version
if [ -f "package.json" ]; then
    # Update existing package.json
    npm install "pdfjs-dist@${TARGET_VERSION}" --save-dev
else
    # First time setup
    echo -e "${YELLOW}🏗️ First time setup - initializing npm...${NC}"
    npm install "pdfjs-dist@${TARGET_VERSION}" --save-dev
fi

echo ""
echo -e "${YELLOW}🔨 Building PDF.js assets...${NC}"
npm run build-pdfjs

FINAL_VERSION=$(node -p "require('./node_modules/pdfjs-dist/package.json').version" 2>/dev/null || echo "unknown")

echo ""
echo -e "${GREEN}✅ Update completed successfully!${NC}"
echo "=================================================="
echo -e "   Updated to PDF.js: ${GREEN}${FINAL_VERSION}${NC}"
echo "   Assets location: assets/vendor/"
echo ""
echo -e "${YELLOW}📝 Next steps:${NC}"
echo "   1. Test the PDF functionality"
echo "   2. Commit the changes to git"
echo "   3. Update any documentation if needed"
echo ""
echo -e "${GREEN}🎉 Happy coding!${NC}"