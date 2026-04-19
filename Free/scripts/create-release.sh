#!/bin/bash
# AI Content Agent Plugin - Automated Release Script
# Usage: ./scripts/create-release.sh VERSION DESCRIPTION
# Example: ./scripts/create-release.sh 2.6.5 bug-fixes-and-improvements

set -e  # Exit on any error

VERSION=$1
DESCRIPTION=$2

if [ -z "$VERSION" ] || [ -z "$DESCRIPTION" ]; then
    echo "❌ Error: Missing required parameters"
    echo ""
    echo "Usage: ./scripts/create-release.sh VERSION DESCRIPTION"
    echo ""
    echo "Examples:"
    echo "  ./scripts/create-release.sh 2.6.5 bug-fixes-and-improvements"
    echo "  ./scripts/create-release.sh 2.7.0 new-features-and-ui-updates"
    echo "  ./scripts/create-release.sh 2.6.4 vendor-optimization-and-docs-update"
    echo ""
    exit 1
fi

echo "🚀 AI Content Agent Plugin - Release Creator v1.0.0"
echo "=================================================="
echo "Version: $VERSION"
echo "Description: $DESCRIPTION"
echo ""

# Validate version format
if ! [[ $VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "❌ Error: Version must be in format X.Y.Z (e.g., 2.6.5)"
    exit 1
fi

# Check if we're in the right directory
if [ ! -f "/workspace/plugin/ai-content-agent.php" ]; then
    echo "❌ Error: Plugin main file not found. Are you in the right workspace?"
    exit 1
fi

echo "🔨 Step 1: Building plugin..."
cd /workspace/plugin

# Check if npm is available and package.json exists
if [ ! -f "package.json" ]; then
    echo "❌ Error: package.json not found"
    exit 1
fi

# Build the plugin
echo "   Running npm run build:wp..."
npm run build:wp

# Verify build files were created
if [ ! -f "admin/assets/index-"*.js ] || [ ! -f "admin/js/index.js" ]; then
    echo "❌ Error: Build files not created properly"
    exit 1
fi

echo "✅ Build completed successfully"
echo ""

echo "📦 Step 2: Creating release package..."
cd /workspace

# Generate timestamp
TIMESTAMP=$(date +%Y%m%d_%H%M)
PACKAGE_NAME="ai-content-agent-v${VERSION}-${DESCRIPTION}-${TIMESTAMP}.zip"

echo "   Package name: $PACKAGE_NAME"

# Create release zip with maximum compression
zip -r -9 "releases/${PACKAGE_NAME}" plugin/ \
  -x "*/node_modules/*" "*/.git/*" "*/dist/*" \
     "*/package-lock.json" "*/.gitignore" "*/.DS_Store" \
     "*/tsconfig.json" "*/vite.config.ts" "*/composer.phar" \
     "*/releases/*" "*/scripts/*" > /dev/null

if [ ! -f "releases/${PACKAGE_NAME}" ]; then
    echo "❌ Error: Failed to create release package"
    exit 1
fi

echo "✅ Release package created"
echo ""

echo "📁 Step 3: Archiving previous release..."

# Check if there are any existing releases to archive
EXISTING_RELEASES=$(ls releases/ai-content-agent-v*.zip 2>/dev/null | wc -l)

if [ $EXISTING_RELEASES -gt 1 ]; then
    # Move all releases except the new one to archive
    for file in releases/ai-content-agent-v*.zip; do
        if [ "$file" != "releases/${PACKAGE_NAME}" ]; then
            echo "   Archiving: $(basename "$file")"
            mv "$file" releases/archive/
        fi
    done
    echo "✅ Previous releases archived"
else
    echo "ℹ️  No previous releases to archive"
fi
echo ""

echo "🔍 Step 4: Verifying release..."

# Check package size
PACKAGE_SIZE=$(du -sh "releases/${PACKAGE_NAME}" | cut -f1)
echo "   Package size: $PACKAGE_SIZE"

# Verify package contents (check for main files)
if unzip -l "releases/${PACKAGE_NAME}" | grep -q "ai-content-agent.php"; then
    echo "✅ Package verification passed"
else
    echo "❌ Error: Package verification failed - main plugin file not found"
    exit 1
fi

echo ""
echo "🎉 Release created successfully!"
echo "=================================================="
echo "Package: $PACKAGE_NAME"
echo "Size: $PACKAGE_SIZE"
echo "Location: /workspace/releases/"
echo ""
echo "📋 Next steps:"
echo "1. Test the release package"
echo "2. Update documentation if needed"
echo "3. Create release notes"
echo "4. Upload to distribution channels"
echo ""

# Display current releases status
echo "📊 Current releases status:"
echo "   Active release: $(ls releases/ai-content-agent-v*.zip | wc -l) file(s)"
echo "   Archived releases: $(ls releases/archive/ai-content-agent-v*.zip 2>/dev/null | wc -l) file(s)"
echo ""
echo "✅ Release process completed successfully! 🚀"