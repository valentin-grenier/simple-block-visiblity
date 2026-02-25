#!/bin/bash

# Exit on error
set -e

# Parse arguments
TEST_MODE=false
if [[ "$1" == "--test" || "$1" == "-t" ]]; then
    TEST_MODE=true
    echo "🧪 Running in TEST MODE - no commits will be made"
fi

# Configuration
PLUGIN_SLUG="simple-block-visibility"
SVN_URL="https://plugins.svn.wordpress.org/$PLUGIN_SLUG"
SVN_DIR="$HOME/dev/plugins/$PLUGIN_SLUG"

# Get the plugin directory (parent of bin/)
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Get current version from main plugin file
CURRENT_VERSION=$(grep -i "Version:" "$PLUGIN_DIR"/*.php | head -1 | awk '{print $3}')

if [ -z "$CURRENT_VERSION" ]; then
    echo "❌ Error: Could not determine plugin version"
    exit 1
fi

echo "📌 Current version: $CURRENT_VERSION"
echo ""
echo "Select release type:"
echo "  1) Patch (bug fix)     - e.g., $CURRENT_VERSION → $(echo $CURRENT_VERSION | awk -F. '{print $1"."$2"."$3+1}')"
echo "  2) Minor (new feature) - e.g., $CURRENT_VERSION → $(echo $CURRENT_VERSION | awk -F. '{print $1"."$2+1".0"}')"
echo "  3) Major (breaking)    - e.g., $CURRENT_VERSION → $(echo $CURRENT_VERSION | awk -F. '{print $1+1".0.0"}')"
echo "  4) Custom version"
echo "  5) Use current version ($CURRENT_VERSION)"
echo ""
read -p "Enter choice (1-5): " -n 1 -r RELEASE_TYPE
echo
echo

case $RELEASE_TYPE in
    1)
        VERSION=$(echo $CURRENT_VERSION | awk -F. '{print $1"."$2"."$3+1}')
        RELEASE_NAME="Patch"
        ;;
    2)
        VERSION=$(echo $CURRENT_VERSION | awk -F. '{print $1"."$2+1".0"}')
        RELEASE_NAME="Minor"
        ;;
    3)
        VERSION=$(echo $CURRENT_VERSION | awk -F. '{print $1+1".0.0"}')
        RELEASE_NAME="Major"
        ;;
    4)
        read -p "Enter custom version (e.g., 1.2.3): " VERSION
        RELEASE_NAME="Custom"
        ;;
    5)
        VERSION=$CURRENT_VERSION
        RELEASE_NAME="Current"
        ;;
    *)
        echo "❌ Invalid choice. Exiting."
        exit 1
        ;;
esac

echo "🚀 Deploying $PLUGIN_SLUG v$VERSION ($RELEASE_NAME release)"

# Function to update version in plugin file
update_version_in_file() {
    local file="$1"
    local new_version="$2"

    sed -i "s/Version:.*$/Version: $new_version/" "$file"
    sed -i "s/define( 'SIMPBLV_VERSION', '[^']*' );/define( 'SIMPBLV_VERSION', '$new_version' );/" "$file"

    echo "✅ Updated version in $file"
}

# Update version in main plugin file if version changed
if [ "$VERSION" != "$CURRENT_VERSION" ]; then
    echo "📝 Updating version in plugin file..."
    MAIN_FILE=$(find "$PLUGIN_DIR" -maxdepth 1 -name "*.php" -type f | head -1)
    update_version_in_file "$MAIN_FILE" "$VERSION"

    if [ -f "$PLUGIN_DIR/readme.txt" ]; then
        sed -i "s/^Stable tag:.*$/Stable tag: $VERSION/" "$PLUGIN_DIR/readme.txt"
        echo "✅ Updated stable tag in readme.txt"
    fi
fi

# Get changelog/commit message
echo ""
echo "📝 Enter changelog for this release:"
echo "   (This will appear in the WordPress.org repository)"
echo "   Tip: Use bullet points with * or -"
echo "   (Press Enter twice when done, or leave empty for default message)"
echo ""

if [ -t 0 ]; then
    COMMIT_MESSAGE=""
    while true; do
        read -r line
        if [ -z "$line" ]; then
            if [ -n "$COMMIT_MESSAGE" ] || [ -z "$COMMIT_MESSAGE" ]; then
                break
            fi
        else
            if [ -z "$COMMIT_MESSAGE" ]; then
                COMMIT_MESSAGE="$line"
            else
                COMMIT_MESSAGE="$COMMIT_MESSAGE"$'\n'"$line"
            fi
        fi
    done

    if [ -z "$COMMIT_MESSAGE" ]; then
        COMMIT_MESSAGE="Update to version $VERSION"
    fi
else
    COMMIT_MESSAGE="Update to version $VERSION"
fi

echo ""
echo "Changelog to be used:"
echo "---"
echo "$COMMIT_MESSAGE"
echo "---"
echo ""

# Function to update changelog in readme.txt
update_readme_changelog() {
    local readme_file="$1"
    local version="$2"
    local changelog="$3"

    if [ ! -f "$readme_file" ]; then
        echo "⚠️  readme.txt not found, skipping changelog update"
        return
    fi

    if ! grep -q "== Changelog ==" "$readme_file"; then
        echo "⚠️  Changelog section not found in readme.txt"
        return
    fi

    local changelog_entry="= $version =\n"

    while IFS= read -r line; do
        if [[ "$line" =~ ^[*-] ]]; then
            changelog_entry+="$line\n"
        else
            if [ -n "$line" ]; then
                changelog_entry+="* $line\n"
            fi
        fi
    done <<< "$changelog"

    local temp_file=$(mktemp)

    awk -v entry="$changelog_entry" '
        /^== Changelog ==/ {
            print $0
            print ""
            printf "%s", entry
            print ""
            next
        }
        { print }
    ' "$readme_file" > "$temp_file"

    mv "$temp_file" "$readme_file"

    echo "✅ Updated Changelog section in readme.txt"
}

if [ -f "$PLUGIN_DIR/readme.txt" ]; then
    update_readme_changelog "$PLUGIN_DIR/readme.txt" "$VERSION" "$COMMIT_MESSAGE"
fi

# Build the project
echo "🔨 Building project..."
cd "$PLUGIN_DIR"

if [ ! -d "$PLUGIN_DIR/node_modules" ]; then
    echo "📦 Installing npm dependencies..."
    npm install
fi

npm run build

if [ $? -ne 0 ]; then
    echo "❌ Build failed"
    exit 1
fi

echo "✅ Build completed"

# Check if .distignore exists
if [ ! -f "$PLUGIN_DIR/.distignore" ]; then
    echo "⚠️  Warning: .distignore file not found"
    read -p "Continue without .distignore? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "❌ Deployment cancelled"
        exit 1
    fi
fi

# Checkout or update SVN
if [ ! -d "$SVN_DIR" ]; then
    echo "📥 Checking out SVN repository..."
    svn co "$SVN_URL" "$SVN_DIR"
else
    echo "🔄 Updating SVN repository..."
    cd "$SVN_DIR" && svn up
fi

# Clean trunk directory
echo "🧹 Cleaning trunk directory..."
if [ -d "$SVN_DIR/trunk" ]; then
    find "$SVN_DIR/trunk" -mindepth 1 -maxdepth 1 ! -name '.svn' -exec rm -rf {} +
fi

# Copy files using rsync, respecting .distignore
echo "📦 Copying files..."
if [ -f "$PLUGIN_DIR/.distignore" ]; then
    rsync -av --delete \
        --exclude-from="$PLUGIN_DIR/.distignore" \
        --exclude='.svn' \
        --exclude="$SVN_DIR" \
        "$PLUGIN_DIR/" "$SVN_DIR/trunk/"
else
    rsync -av --delete \
        --exclude='.svn' \
        --exclude="$SVN_DIR" \
        "$PLUGIN_DIR/" "$SVN_DIR/trunk/"
fi

# Go to SVN directory
cd "$SVN_DIR"

# Handle additions/deletions
echo "📋 Adding new files to SVN..."
svn status | grep '^\?' | awk '{print $2}' | xargs -r -I {} svn add {}
svn status | grep '^\!' | awk '{print $2}' | xargs -r -I {} svn delete {}

# Display changes
echo "📝 Changes to commit:"
svn status

# If test mode, exit here
if [ "$TEST_MODE" = true ]; then
    echo ""
    echo "🧪 TEST MODE - Stopping here"
    echo "✅ Everything looks good! Files are ready in: $SVN_DIR/trunk"
    echo "To deploy for real, run without --test flag"
    exit 0
fi

# Ask for confirmation
read -p "Commit these changes? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "📤 Committing to trunk..."

    SVN_COMMIT_MSG="Version $VERSION - $RELEASE_NAME release

$COMMIT_MESSAGE"

    svn ci -m "$SVN_COMMIT_MSG"

    # Check if tag already exists and auto-increment if needed
    FINAL_VERSION=$VERSION
    if svn ls "$SVN_URL/tags/$VERSION" > /dev/null 2>&1; then
        echo ""
        echo "⚠️  Tag $VERSION already exists!"
        echo "🔄 Auto-incrementing to next patch version..."

        MAJOR=$(echo $VERSION | cut -d. -f1)
        MINOR=$(echo $VERSION | cut -d. -f2)
        PATCH=$(echo $VERSION | cut -d. -f3)

        while svn ls "$SVN_URL/tags/$MAJOR.$MINOR.$PATCH" > /dev/null 2>&1; do
            PATCH=$((PATCH + 1))
        done

        FINAL_VERSION="$MAJOR.$MINOR.$PATCH"
        echo "✅ Using version $FINAL_VERSION instead"
        echo ""
    fi

    echo "🏷️  Creating tag $FINAL_VERSION..."
    TAG_MSG="Tagging version $FINAL_VERSION - $RELEASE_NAME release

$COMMIT_MESSAGE"
    svn cp "$SVN_URL/trunk" "$SVN_URL/tags/$FINAL_VERSION" -m "$TAG_MSG"

    echo ""
    echo "✅ Deployment completed successfully!"
    echo ""
    echo "📦 Summary:"
    echo "   Plugin: $PLUGIN_SLUG"
    echo "   Version: $CURRENT_VERSION → $FINAL_VERSION"
    echo "   Release Type: $RELEASE_NAME"
    echo "   SVN Tag: $SVN_URL/tags/$FINAL_VERSION"
    echo ""
else
    echo "❌ Deployment cancelled"
fi
