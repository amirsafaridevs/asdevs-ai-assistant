#!/bin/bash
#
# ASDevs AI Assistant - WordPress.org Release Builder
# Usage: bash build-release.sh
#
# This script:
#   1. Builds the Vue frontend (npm run build)
#   2. Installs production Composer dependencies
#   3. Creates a clean "final/asdevs-ai-assistant" folder ready for WordPress.org submission
#

set -e

# ── Colors ──────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# ── Paths ───────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_SLUG="asdevs-ai-assistant"
FINAL_DIR="$SCRIPT_DIR/final"
BUILD_DIR="$FINAL_DIR/$PLUGIN_SLUG"

echo -e "${CYAN}╔══════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║   ASDevs AI Assistant - Release Builder     ║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════════╝${NC}"
echo ""

# ── Step 1: Clean previous build ────────────────────────
echo -e "${YELLOW}[1/5]${NC} Cleaning previous build..."
rm -rf "$FINAL_DIR"
rm -rf "$SCRIPT_DIR/assets/dist"
echo -e "       ${GREEN}✓${NC} Cleaned"

# ── Step 2: Build frontend ──────────────────────────────
echo -e "${YELLOW}[2/5]${NC} Building Vue frontend..."
cd "$SCRIPT_DIR/frontend"

if [ ! -d "node_modules" ]; then
    echo -e "       Installing npm dependencies..."
    npm install --silent
fi

npm run build
echo -e "       ${GREEN}✓${NC} Frontend built → assets/dist/"

# ── Step 3: Install production Composer deps ────────────
echo -e "${YELLOW}[3/5]${NC} Installing production Composer dependencies..."
cd "$SCRIPT_DIR"
composer install --no-dev --optimize-autoloader --quiet
echo -e "       ${GREEN}✓${NC} Dependencies installed"

# ── Step 4: Create release directory ────────────────────
echo -e "${YELLOW}[4/5]${NC} Assembling release package..."

mkdir -p "$BUILD_DIR"

# Core plugin files
cp "$SCRIPT_DIR/asdevs-ai-assistant.php"  "$BUILD_DIR/"
cp "$SCRIPT_DIR/readme.txt"               "$BUILD_DIR/"
cp "$SCRIPT_DIR/composer.json"            "$BUILD_DIR/"

# PHP source (src/)
cp -r "$SCRIPT_DIR/src"                   "$BUILD_DIR/"

# Vendor (production only)
cp -r "$SCRIPT_DIR/vendor"                "$BUILD_DIR/"

# Assets (CSS + built dist)
mkdir -p "$BUILD_DIR/assets"
cp -r "$SCRIPT_DIR/assets/css"            "$BUILD_DIR/assets/"
cp -r "$SCRIPT_DIR/assets/dist"           "$BUILD_DIR/assets/"

# ── Step 5: Verify & Summary ────────────────────────────
echo -e "${YELLOW}[5/5]${NC} Verifying package..."

# Check required files exist
REQUIRED_FILES=(
    "$BUILD_DIR/asdevs-ai-assistant.php"
    "$BUILD_DIR/readme.txt"
    "$BUILD_DIR/src/App.php"
    "$BUILD_DIR/assets/css/widget.css"
    "$BUILD_DIR/assets/dist/js/main.js"
    "$BUILD_DIR/assets/dist/css/main.css"
)

ALL_OK=true
for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "       ${GREEN}✓${NC} $(basename "$file")"
    else
        echo -e "       ${RED}✗ MISSING:${NC} $file"
        ALL_OK=false
    fi
done

echo ""

if [ "$ALL_OK" = true ]; then
    # Count files and size
    FILE_COUNT=$(find "$BUILD_DIR" -type f | wc -l)
    DIR_SIZE=$(du -sh "$BUILD_DIR" | cut -f1)

    echo -e "${GREEN}╔══════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║          Build Complete! ✓                  ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "  ${CYAN}Package:${NC}  $BUILD_DIR"
    echo -e "  ${CYAN}Files:${NC}    $FILE_COUNT"
    echo -e "  ${CYAN}Size:${NC}     $DIR_SIZE"
    echo ""
    echo -e "  ${YELLOW}Next step:${NC}"
    echo -e "    cd final && zip -r ../$PLUGIN_SLUG.zip $PLUGIN_SLUG"
    echo ""
    echo -e "  Then upload ${CYAN}$PLUGIN_SLUG.zip${NC} to WordPress.org"
    echo ""
else
    echo -e "${RED}Build failed! Some required files are missing.${NC}"
    exit 1
fi
