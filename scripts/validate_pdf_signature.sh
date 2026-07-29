#!/bin/bash

# PDF Signatur Validator Script
# Dieses Script testet PDF-Signaturen mit verschiedenen Tools

PDF_FILE="$1"

if [ -z "$PDF_FILE" ]; then
    echo "Usage: $0 <pdf-file>"
    echo "Example: $0 test_signed.pdf"
    exit 1
fi

if [ ! -f "$PDF_FILE" ]; then
    echo "Error: PDF file '$PDF_FILE' not found!"
    exit 1
fi

echo "=== PDF Signatur Validation Report ==="
echo "File: $PDF_FILE"
echo "Date: $(date)"
echo "========================================="
echo

echo "1. Basic PDF Info (pdftk/pdfinfo):"
echo "-----------------------------------"
if command -v pdfinfo &> /dev/null; then
    pdfinfo "$PDF_FILE" | grep -E "(Title|Author|Creator|Producer|ModDate|CreationDate)"
else
    echo "pdfinfo not available"
fi
echo

echo "2. Signature Validation (pdfsig):"
echo "----------------------------------"
if command -v pdfsig &> /dev/null; then
    pdfsig -dump "$PDF_FILE"
    echo
    echo "Signature verification:"
    pdfsig -verify "$PDF_FILE"
else
    echo "pdfsig not available"
fi
echo

echo "3. PDF Structure Analysis:"
echo "--------------------------"
echo "File size: $(stat -c%s "$PDF_FILE" 2>/dev/null || stat -f%z "$PDF_FILE") bytes"
echo "PDF Version: $(head -c 8 "$PDF_FILE")"
echo

echo "4. Digital Signature Details:"
echo "------------------------------"
if command -v openssl &> /dev/null; then
    # Extract signatures if possible
    echo "Checking for embedded certificates..."
    strings "$PDF_FILE" | grep -i "certificate\|x509\|rsa" | head -5
else
    echo "openssl not available"
fi
echo

echo "5. Incremental Updates Check:"
echo "-----------------------------"
# Check for multiple xref tables (indicates incremental updates)
XREF_COUNT=$(grep -c "xref" "$PDF_FILE")
echo "Number of xref tables: $XREF_COUNT"
if [ "$XREF_COUNT" -gt 1 ]; then
    echo "⚠️  WARNING: Multiple xref tables found - indicates incremental updates after signing"
else
    echo "✅ Single xref table - good for signature validation"
fi
echo

echo "6. Recommendation:"
echo "------------------"
if [ "$XREF_COUNT" -gt 1 ]; then
    echo "❌ PDF appears to have been modified after signing"
    echo "   This invalidates the digital signature"
    echo "   Solution: Ensure no modifications after final signature"
else
    echo "✅ PDF structure looks good for signature validation"
fi

echo
echo "=== End of Report ==="
