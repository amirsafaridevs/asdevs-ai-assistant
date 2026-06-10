#!/bin/bash
#
# ASDevs AI Assistant - WordPress.org Release Builder
# Usage: bash build-release.sh
#
# This script:
#   1. Builds the Vue frontend (npm run build)
#   2. Copies only required files into "final/asdevs-ai-assistant"
#   3. Installs production-only Composer deps inside the final folder
#   4. Source code stays COMPLETELY untouched
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
echo -e "  ${YELLOW}⚠${NC}  Source directory is NEVER modified."
echo -e "  ${YELLOW}⚠${NC}  Only the ${CYAN}final/${NC} output folder is touched."
echo ""

# ── Step 1: Clean ONLY the final output folder ──────────
echo -e "${YELLOW}[1/5]${NC} Cleaning previous build (final/ only)..."
rm -rf "$FINAL_DIR"
echo -e "       ${GREEN}✓${NC} Cleaned final/"

# ── Step 2: Build frontend → assets/dist/ ───────────────
echo -e "${YELLOW}[2/5]${NC} Building Vue frontend..."
cd "$SCRIPT_DIR/frontend"

if [ ! -d "node_modules" ]; then
    echo -e "       Installing npm dependencies (frontend only)..."
    npm install --silent
fi

npm run build
echo -e "       ${GREEN}✓${NC} Frontend built → assets/dist/"

# ── Step 3: Assemble release package ────────────────────
echo -e "${YELLOW}[3/5]${NC} Copying files to final/$PLUGIN_SLUG/..."

mkdir -p "$BUILD_DIR"

# --- Core plugin files ---
echo -e "       Copying core files..."
cp "$SCRIPT_DIR/asdevs-ai-assistant.php"  "$BUILD_DIR/"
cp "$SCRIPT_DIR/readme.txt"               "$BUILD_DIR/"

# --- PHP source ---
echo -e "       Copying src/..."
cp -r "$SCRIPT_DIR/src"                   "$BUILD_DIR/"

# --- Assets (custom CSS + built dist) ---
echo -e "       Copying assets/..."
mkdir -p "$BUILD_DIR/assets"
cp -r "$SCRIPT_DIR/assets/css"            "$BUILD_DIR/assets/"
cp -r "$SCRIPT_DIR/assets/dist"           "$BUILD_DIR/assets/"

echo -e "       ${GREEN}✓${NC} Files copied"

# ── Step 4: Composer deps inside final folder ───────────
echo -e "${YELLOW}[4/5]${NC} Installing production Composer deps inside final/..."
cd "$BUILD_DIR"

# Create composer.json inside build (production-only, no dev tools)
cat > "$BUILD_DIR/composer.json" << 'COMPOSEREOF'
{
  "name": "asdevs/ai-assistant",
  "description": "AI-powered WordPress admin assistant - read-only GPS for your site",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": ">=8.2"
  },
  "autoload": {
    "psr-4": {
      "ASDevs\\AIAssistant\\": "src/"
    }
  },
  "config": {
    "optimize-autoloader": true,
    "sort-packages": true
  }
}
COMPOSEREOF

composer install --no-dev --optimize-autoloader --quiet
# Remove composer.json & composer.lock from final package
# (WordPress.org plugins don't distribute these)
rm -f "$BUILD_DIR/composer.json" "$BUILD_DIR/composer.lock"
echo -e "       ${GREEN}✓${NC} Composer deps installed (production only)"

# ── Step 5: Verify & Summary ────────────────────────────
echo -e "${YELLOW}[5/5]${NC} Verifying package..."

REQUIRED_FILES=(
    "$BUILD_DIR/asdevs-ai-assistant.php"
    "$BUILD_DIR/readme.txt"
    "$BUILD_DIR/src/App.php"
    "$BUILD_DIR/vendor/autoload.php"
    "$BUILD_DIR/assets/css/widget.css"
    "$BUILD_DIR/assets/dist/js/main.js"
    "$BUILD_DIR/assets/dist/css/main.css"
)

ALL_OK=true
for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "       ${GREEN}✓${NC} ${file#$BUILD_DIR/}"
    else
        echo -e "       ${RED}✗ MISSING:${NC} ${file#$BUILD_DIR/}"
        ALL_OK=false
    fi
done

# Warn if any unwanted files accidentally made it in
UNWANTED_PATTERNS=(
    "frontend/"
    "node_modules/"
    "plans/"
    ".git/"
    "README.md"
    "build-release.sh"
)
for pattern in "${UNWANTED_PATTERNS[@]}"; do
    if find "$BUILD_DIR" -path "*/$pattern*" -maxdepth 2 | grep -q .; then
        echo -e "       ${YELLOW}⚠ UNEXPECTED:${NC} $pattern found in build"
    fi
done

echo ""

if [ "$ALL_OK" = true ]; then
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
    echo -e "  ${YELLOW}Next step → create zip:${NC}"
    echo -e "    ${CYAN}cd final && zip -r ../$PLUGIN_SLUG.zip $PLUGIN_SLUG${NC}"
    echo ""
    echo -e "  Then upload ${CYAN}$PLUGIN_SLUG.zip${NC} to WordPress.org"
    echo ""
    echo -e "  ${GREEN}✓ Source directory is untouched.${NC}"
    echo ""
else
    echo -e "${RED}Build failed! Some required files are missing.${NC}"
    exit 1
fi
