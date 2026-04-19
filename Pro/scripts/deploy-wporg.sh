#!/usr/bin/env bash
set -euo pipefail

# Usage: ./scripts/deploy-wporg.sh /path/to/svn/checkout 1.0.0
# This script does not commit. It stages trunk, tags, and assets.

if [ $# -lt 2 ]; then
  echo "Usage: $0 <SVN_CHECKOUT_DIR> <VERSION>"
  exit 1
fi

SVN_DIR="$1"
VERSION="$2"
PLUGIN_SLUG="ai-content-agent"
WORK_DIR="/workspace/plugin"
ASSETS_SRC="/workspace/assets/final"
FALLBACK_ASSETS="/workspace/assets/placeholders"

if [ ! -d "$SVN_DIR" ]; then
  echo "SVN directory not found: $SVN_DIR"
  exit 1
fi

# Ensure SVN layout
mkdir -p "$SVN_DIR/trunk" "$SVN_DIR/tags/$VERSION" "$SVN_DIR/assets"

# Rsync plugin to trunk (respect .distignore via rsync filter if present)
rsync -av --delete \
  --exclude-from="$WORK_DIR/.distignore" \
  "$WORK_DIR/" "$SVN_DIR/trunk/"

# Copy assets to SVN assets/ (prefer final/, fallback to placeholders/)
if [ -d "$ASSETS_SRC" ] && [ "$(ls -A "$ASSETS_SRC")" ]; then
  rsync -av --delete "$ASSETS_SRC/" "$SVN_DIR/assets/"
else
  rsync -av --delete "$FALLBACK_ASSETS/" "$SVN_DIR/assets/"
fi

# Tag the release
rsync -av --delete "$SVN_DIR/trunk/" "$SVN_DIR/tags/$VERSION/"

echo "Staged to SVN checkout:"
echo " - trunk/: $SVN_DIR/trunk"
echo " - tags/$VERSION/: $SVN_DIR/tags/$VERSION"
echo " - assets/: $SVN_DIR/assets"

echo "Next steps:"
echo "  cd $SVN_DIR && svn status"
echo "  svn add --force . && svn commit -m 'Release $VERSION'"