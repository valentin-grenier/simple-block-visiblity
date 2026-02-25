#!/bin/bash

# =============================================================================
# Simple Block Visibility — Release Preparation & WordPress Compliance Check
# =============================================================================
# Usage:
#   bash bin/prepare-release.sh           # Full check + ZIP
#   bash bin/prepare-release.sh --no-zip  # Skip ZIP creation
#   bash bin/prepare-release.sh --no-build # Skip npm build
# =============================================================================

set -e

# -----------------------------------------------------------------------------
# Options
# -----------------------------------------------------------------------------
CREATE_ZIP=true
RUN_BUILD=true

for arg in "$@"; do
    case $arg in
        --no-zip)    CREATE_ZIP=false ;;
        --no-build)  RUN_BUILD=false ;;
    esac
done

# -----------------------------------------------------------------------------
# Colors & helpers
# -----------------------------------------------------------------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

ERRORS=0
WARNINGS=0

pass()    { echo -e "  ${GREEN}✅ $1${NC}"; }
fail()    { echo -e "  ${RED}❌ $1${NC}"; ((ERRORS++)); }
warn()    { echo -e "  ${YELLOW}⚠️  $1${NC}"; ((WARNINGS++)); }
info()    { echo -e "  ${BLUE}ℹ️  $1${NC}"; }
section() { echo -e "\n${BOLD}── $1 ──${NC}"; }

# -----------------------------------------------------------------------------
# Paths
# -----------------------------------------------------------------------------
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_FILE=$(find "$PLUGIN_DIR" -maxdepth 1 -name "*.php" -type f | head -1)
PLUGIN_SLUG=$(basename "$PLUGIN_DIR")

if [ -z "$PLUGIN_FILE" ]; then
    echo -e "${RED}❌ Could not find main plugin PHP file. Aborting.${NC}"
    exit 1
fi

echo ""
echo -e "${BOLD}============================================${NC}"
echo -e "${BOLD}  WordPress Compliance Check${NC}"
echo -e "${BOLD}  Plugin: $PLUGIN_SLUG${NC}"
echo -e "${BOLD}============================================${NC}"

# =============================================================================
# 1. PLUGIN HEADERS
# =============================================================================
section "Plugin Headers"

required_headers=(
    "Plugin Name"
    "Description"
    "Version"
    "Requires at least"
    "Tested up to"
    "Requires PHP"
    "Author"
    "License"
    "License URI"
    "Text Domain"
)

for header in "${required_headers[@]}"; do
    if grep -q "^ \* $header:" "$PLUGIN_FILE"; then
        value=$(grep "^ \* $header:" "$PLUGIN_FILE" | sed 's/.*: *//')
        pass "$header: $value"
    else
        fail "Missing required header: $header"
    fi
done

# Check license is GPL-compatible
LICENSE=$(grep "^ \* License:" "$PLUGIN_FILE" | sed 's/.*: *//' | head -1)
if echo "$LICENSE" | grep -qi "GPL"; then
    pass "License is GPL-compatible"
else
    fail "License must be GPL-compatible (found: $LICENSE)"
fi

# Check Text Domain matches plugin slug
TEXT_DOMAIN=$(grep "^ \* Text Domain:" "$PLUGIN_FILE" | sed 's/.*: *//' | tr -d '[:space:]')
if [ "$TEXT_DOMAIN" = "$PLUGIN_SLUG" ]; then
    pass "Text Domain matches plugin slug"
else
    warn "Text Domain ($TEXT_DOMAIN) does not match plugin slug ($PLUGIN_SLUG)"
fi

# =============================================================================
# 2. VERSION CONSISTENCY
# =============================================================================
section "Version Consistency"

# Version from PHP header
PHP_HEADER_VERSION=$(grep "^ \* Version:" "$PLUGIN_FILE" | sed 's/.*: *//' | tr -d '[:space:]')

# Version from PHP constant (detect constant name dynamically)
PHP_CONST_VERSION=$(grep -oE "define\( '[A-Z_]+_VERSION', '[0-9]+\.[0-9]+\.[0-9]+' \)" "$PLUGIN_FILE" | grep -oE "'[0-9]+\.[0-9]+\.[0-9]+'" | tr -d "'")

# Version from readme.txt
README_VERSION=""
if [ -f "$PLUGIN_DIR/readme.txt" ]; then
    README_VERSION=$(grep "^Stable tag:" "$PLUGIN_DIR/readme.txt" | sed 's/Stable tag: *//' | tr -d '[:space:]')
fi

# Version from package.json
PKG_VERSION=""
if [ -f "$PLUGIN_DIR/package.json" ]; then
    PKG_VERSION=$(grep '"version"' "$PLUGIN_DIR/package.json" | sed 's/.*: *"//' | tr -d '",[:space:]')
fi

info "PHP header:   $PHP_HEADER_VERSION"
info "PHP constant: ${PHP_CONST_VERSION:-not found}"
info "readme.txt:   ${README_VERSION:-not found}"
info "package.json: ${PKG_VERSION:-not found}"

ALL_MATCH=true

if [ -n "$PHP_CONST_VERSION" ] && [ "$PHP_HEADER_VERSION" != "$PHP_CONST_VERSION" ]; then
    fail "PHP header ($PHP_HEADER_VERSION) ≠ PHP constant ($PHP_CONST_VERSION)"
    ALL_MATCH=false
fi

if [ -n "$README_VERSION" ] && [ "$PHP_HEADER_VERSION" != "$README_VERSION" ]; then
    fail "PHP header ($PHP_HEADER_VERSION) ≠ readme.txt Stable tag ($README_VERSION)"
    ALL_MATCH=false
fi

if [ -n "$PKG_VERSION" ] && [ "$PHP_HEADER_VERSION" != "$PKG_VERSION" ]; then
    warn "PHP header ($PHP_HEADER_VERSION) ≠ package.json version ($PKG_VERSION)"
fi

if [ "$ALL_MATCH" = true ]; then
    pass "All versions are consistent ($PHP_HEADER_VERSION)"
fi

# =============================================================================
# 3. README.TXT COMPLIANCE
# =============================================================================
section "readme.txt"

if [ ! -f "$PLUGIN_DIR/readme.txt" ]; then
    fail "readme.txt is missing (required for WordPress.org)"
else
    pass "readme.txt exists"

    required_sections=("== Description ==" "== Installation ==" "== Changelog ==")
    for section_header in "${required_sections[@]}"; do
        if grep -q "^$section_header" "$PLUGIN_DIR/readme.txt"; then
            pass "Section found: $section_header"
        else
            fail "Missing required section: $section_header"
        fi
    done

    recommended_sections=("== Frequently Asked Questions ==")
    for section_header in "${recommended_sections[@]}"; do
        if grep -q "^$section_header" "$PLUGIN_DIR/readme.txt"; then
            pass "Section found: $section_header"
        else
            warn "Recommended section missing: $section_header"
        fi
    done

    # Check Stable tag is present
    if grep -q "^Stable tag:" "$PLUGIN_DIR/readme.txt"; then
        pass "Stable tag is set"
    else
        fail "readme.txt is missing 'Stable tag:' field"
    fi

    # Check Tested up to is present
    if grep -q "^Tested up to:" "$PLUGIN_DIR/readme.txt"; then
        pass "Tested up to is set"
    else
        warn "readme.txt is missing 'Tested up to:' field"
    fi

    # Check changelog has an entry for current version
    if grep -q "= $PHP_HEADER_VERSION =" "$PLUGIN_DIR/readme.txt"; then
        pass "Changelog has entry for v$PHP_HEADER_VERSION"
    else
        warn "No changelog entry found for v$PHP_HEADER_VERSION in readme.txt"
    fi
fi

# =============================================================================
# 4. SECURITY CHECKS
# =============================================================================
section "Security"

# Check for direct file access protection in main PHP file
if grep -q "defined( 'ABSPATH' )" "$PLUGIN_FILE"; then
    pass "Direct file access protection present"
else
    fail "Missing ABSPATH check in main plugin file"
fi

# Check all PHP files have ABSPATH protection
PHP_FILES_WITHOUT_ABSPATH=0
while IFS= read -r -d '' php_file; do
    if ! grep -q "defined( 'ABSPATH' )" "$php_file" && ! grep -q "defined('ABSPATH')" "$php_file"; then
        warn "Missing ABSPATH check: $(basename $php_file)"
        ((PHP_FILES_WITHOUT_ABSPATH++))
    fi
done < <(find "$PLUGIN_DIR/includes" -name "*.php" -type f -print0 2>/dev/null)

if [ "$PHP_FILES_WITHOUT_ABSPATH" -eq 0 ]; then
    pass "All PHP files have direct access protection"
fi

# Check for unescaped output (basic check for echo without esc_ functions)
UNESCAPED=$(grep -rn "echo \$" "$PLUGIN_DIR/includes/" 2>/dev/null | grep -v "esc_" | grep -v "absint" | grep -v "intval" | grep -v "//" | wc -l | tr -d '[:space:]')
if [ "$UNESCAPED" -gt 0 ]; then
    warn "$UNESCAPED potential unescaped output(s) found in includes/ — review manually"
else
    pass "No obvious unescaped output detected"
fi

# Check for nonce verification on form submissions
if grep -rq "options.php" "$PLUGIN_DIR/includes/"; then
    if grep -rq "settings_fields\|wp_nonce_field\|check_admin_referer" "$PLUGIN_DIR/includes/"; then
        pass "Nonce/settings fields found in form output"
    else
        warn "Form detected but no nonce verification found — review manually"
    fi
fi

# =============================================================================
# 5. I18N CHECK
# =============================================================================
section "Internationalisation"

# Check for hardcoded strings (basic: any string not wrapped in __() or _e())
# Just check for the text domain being used
TEXTDOMAIN_USES=$(grep -r "simple-block-visibility" "$PLUGIN_DIR/includes/" --include="*.php" 2>/dev/null | grep -c "__\|_e\|esc_html__\|esc_attr__" || echo 0)
if [ "$TEXTDOMAIN_USES" -gt 0 ]; then
    pass "Text domain in use ($TEXTDOMAIN_USES occurrences)"
else
    warn "Text domain 'simple-block-visibility' not found in includes/ — check i18n"
fi

# Check languages directory
if [ -d "$PLUGIN_DIR/languages" ]; then
    pass "languages/ directory exists"
    if ls "$PLUGIN_DIR/languages/"*.pot 1>/dev/null 2>&1; then
        POT_FILE=$(ls "$PLUGIN_DIR/languages/"*.pot | head -1)
        pass "POT file found: $(basename $POT_FILE)"
    else
        warn "No .pot file found in languages/"
    fi
else
    fail "languages/ directory is missing"
fi

# =============================================================================
# 6. REQUIRED FILES
# =============================================================================
section "Required Files"

required_files=("readme.txt" "LICENSE")
for file in "${required_files[@]}"; do
    if [ -f "$PLUGIN_DIR/$file" ]; then
        pass "$file exists"
    else
        fail "$file is missing"
    fi
done

recommended_files=(".distignore" "composer.json")
for file in "${recommended_files[@]}"; do
    if [ -f "$PLUGIN_DIR/$file" ]; then
        pass "$file exists"
    else
        warn "$file is missing (recommended)"
    fi
done

# =============================================================================
# 7. BUILD
# =============================================================================
section "Build"

if [ "$RUN_BUILD" = true ]; then
    if [ ! -f "$PLUGIN_DIR/package.json" ]; then
        warn "package.json not found — skipping build"
    else
        if [ ! -d "$PLUGIN_DIR/node_modules" ]; then
            info "Installing npm dependencies..."
            cd "$PLUGIN_DIR" && npm install --silent
        fi

        info "Running npm run build..."
        cd "$PLUGIN_DIR"
        if npm run build --silent 2>&1; then
            pass "Build succeeded"
        else
            fail "Build failed"
        fi
    fi
else
    info "Build skipped (--no-build)"
fi

# Check that build output exists
if [ -d "$PLUGIN_DIR/build" ] && [ "$(ls -A "$PLUGIN_DIR/build" 2>/dev/null)" ]; then
    BUILT_FILES=$(ls "$PLUGIN_DIR/build/" | tr '\n' ' ')
    pass "Build output exists: $BUILT_FILES"
else
    fail "build/ directory is empty or missing — run npm run build"
fi

# =============================================================================
# 8. LINTING
# =============================================================================
section "Linting"

if [ -f "$PLUGIN_DIR/package.json" ] && [ -d "$PLUGIN_DIR/node_modules" ]; then
    cd "$PLUGIN_DIR"

    info "Running JS lint..."
    if npm run lint:js --silent 2>/dev/null; then
        pass "JS lint passed"
    else
        warn "JS lint reported issues — run 'npm run lint:js' to review"
    fi

    info "Running CSS lint..."
    if npm run lint:css --silent 2>/dev/null; then
        pass "CSS lint passed"
    else
        warn "CSS lint reported issues — run 'npm run lint:css' to review"
    fi
else
    warn "node_modules not found — skipping JS/CSS lint"
fi

# PHP CodeSniffer
if [ -f "$PLUGIN_DIR/vendor/bin/phpcs" ]; then
    info "Running PHPCS..."
    cd "$PLUGIN_DIR"
    if vendor/bin/phpcs --standard=WordPress --extensions=php --ignore=vendor,node_modules,build . 2>/dev/null; then
        pass "PHPCS passed"
    else
        warn "PHPCS reported issues — run 'composer run phpcs' to review"
    fi
else
    warn "PHPCS not found — run 'composer install' to enable PHP linting"
fi

# =============================================================================
# 9. I18N — GENERATE POT
# =============================================================================
section "Generate POT File"

if command -v wp &>/dev/null; then
    cd "$PLUGIN_DIR"
    info "Generating POT file with WP-CLI..."
    if wp i18n make-pot . "languages/$PLUGIN_SLUG.pot" \
        --exclude=node_modules,vendor,build \
        --domain="$PLUGIN_SLUG" 2>/dev/null; then
        pass "POT file generated: languages/$PLUGIN_SLUG.pot"
    else
        warn "WP-CLI i18n command failed — POT file may be outdated"
    fi
else
    warn "WP-CLI not found — skipping POT generation (run: npm run i18n)"
fi

# =============================================================================
# 10. CREATE DISTRIBUTION ZIP
# =============================================================================
if [ "$CREATE_ZIP" = true ]; then
    section "Distribution ZIP"

    ZIP_NAME="$PLUGIN_SLUG-$PHP_HEADER_VERSION.zip"
    ZIP_PATH="$PLUGIN_DIR/../$ZIP_NAME"
    TEMP_DIR=$(mktemp -d)
    TEMP_PLUGIN="$TEMP_DIR/$PLUGIN_SLUG"

    info "Creating distribution ZIP..."

    mkdir -p "$TEMP_PLUGIN"

    if [ -f "$PLUGIN_DIR/.distignore" ]; then
        rsync -a \
            --exclude-from="$PLUGIN_DIR/.distignore" \
            --exclude='.svn' \
            "$PLUGIN_DIR/" "$TEMP_PLUGIN/"
    else
        rsync -a \
            --exclude='.git' \
            --exclude='node_modules' \
            --exclude='src' \
            --exclude='.svn' \
            "$PLUGIN_DIR/" "$TEMP_PLUGIN/"
    fi

    cd "$TEMP_DIR"
    if zip -rq "$ZIP_PATH" "$PLUGIN_SLUG/"; then
        ZIP_SIZE=$(du -sh "$ZIP_PATH" | cut -f1)
        pass "ZIP created: $ZIP_NAME ($ZIP_SIZE)"
        info "Location: $ZIP_PATH"
    else
        fail "ZIP creation failed"
    fi

    rm -rf "$TEMP_DIR"
fi

# =============================================================================
# SUMMARY
# =============================================================================
echo ""
echo -e "${BOLD}============================================${NC}"
echo -e "${BOLD}  Summary${NC}"
echo -e "${BOLD}============================================${NC}"

if [ "$ERRORS" -eq 0 ] && [ "$WARNINGS" -eq 0 ]; then
    echo -e "${GREEN}${BOLD}  All checks passed — ready for submission!${NC}"
elif [ "$ERRORS" -eq 0 ]; then
    echo -e "${YELLOW}${BOLD}  $WARNINGS warning(s) — review before submitting${NC}"
else
    echo -e "${RED}${BOLD}  $ERRORS error(s), $WARNINGS warning(s) — fix errors before submitting${NC}"
fi

echo ""

if [ "$ERRORS" -gt 0 ]; then
    exit 1
fi
