#!/bin/bash

# =============================================================================
# Simple Block Visibility — Export & ZIP
# =============================================================================
# Usage:
#   bash bin/export.sh             # Build then ZIP
#   bash bin/export.sh --no-build  # ZIP without rebuilding
# =============================================================================

set -e

# -----------------------------------------------------------------------------
# Options
# -----------------------------------------------------------------------------
RUN_BUILD=true

for arg in "$@"; do
    case $arg in
        --no-build) RUN_BUILD=false ;;
    esac
done

# -----------------------------------------------------------------------------
# Colors & helpers
# -----------------------------------------------------------------------------
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
BOLD='\033[1m'
NC='\033[0m'

info() { echo -e "${BLUE}  $1${NC}"; }
pass() { echo -e "${GREEN}  ✅ $1${NC}"; }
fail() { echo -e "${RED}  ❌ $1${NC}"; exit 1; }

# -----------------------------------------------------------------------------
# Paths
# -----------------------------------------------------------------------------
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG=$(basename "$PLUGIN_DIR")
PLUGIN_FILE=$(find "$PLUGIN_DIR" -maxdepth 1 -name "*.php" -type f | head -1)

VERSION=$(grep "^ \* Version:" "$PLUGIN_FILE" | sed 's/.*: *//' | tr -d '[:space:]')
if [ -z "$VERSION" ]; then
    fail "Could not read version from $PLUGIN_FILE"
fi

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="${PLUGIN_DIR}/../${ZIP_NAME}"

echo ""
echo -e "${BOLD}  Exporting $PLUGIN_SLUG v$VERSION${NC}"
echo ""

# -----------------------------------------------------------------------------
# Build
# -----------------------------------------------------------------------------
if [ "$RUN_BUILD" = true ]; then
    if [ ! -d "$PLUGIN_DIR/node_modules" ]; then
        info "Installing npm dependencies..."
        cd "$PLUGIN_DIR" && npm install --silent
    fi

    info "Building assets..."
    cd "$PLUGIN_DIR"
    if ! npm run build --silent 2>&1; then
        fail "Build failed — aborting export"
    fi
    pass "Build complete"
else
    info "Skipping build (--no-build)"
fi

# Verify build output exists
if [ ! -d "$PLUGIN_DIR/build" ] || [ -z "$(ls -A "$PLUGIN_DIR/build" 2>/dev/null)" ]; then
    fail "build/ is empty — run without --no-build first"
fi

# -----------------------------------------------------------------------------
# Create ZIP
# -----------------------------------------------------------------------------
TEMP_DIR=$(mktemp -d)
TEMP_PLUGIN="$TEMP_DIR/$PLUGIN_SLUG"
mkdir -p "$TEMP_PLUGIN"

info "Copying files..."
if [ -f "$PLUGIN_DIR/.distignore" ]; then
    rsync -a \
        --exclude-from="$PLUGIN_DIR/.distignore" \
        --exclude='.svn' \
        --exclude='bin' \
        --exclude='.*' \
        "$PLUGIN_DIR/" "$TEMP_PLUGIN/"
else
    rsync -a \
        --exclude='.git' \
        --exclude='node_modules' \
        --exclude='src' \
        --exclude='.svn' \
        --exclude='bin' \
        --exclude='.*' \
        "$PLUGIN_DIR/" "$TEMP_PLUGIN/"
fi

info "Creating $ZIP_NAME..."
cd "$TEMP_DIR"
if ! zip -rq "$ZIP_PATH" "$PLUGIN_SLUG/"; then
    rm -rf "$TEMP_DIR"
    fail "ZIP creation failed"
fi

rm -rf "$TEMP_DIR"

ZIP_SIZE=$(du -sh "$ZIP_PATH" | cut -f1)
pass "Exported: $(dirname "$ZIP_PATH")/$ZIP_NAME ($ZIP_SIZE)"
echo ""
