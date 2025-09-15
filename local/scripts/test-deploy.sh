#!/usr/bin/env bash
#
# Test deployment script - builds locally without pushing
#

# Set this to -ex for verbose output.
set -e

# Set the working directory to the repository root.
PROJECT_ROOT_DIR="$(git rev-parse --show-toplevel)"

SRC_BRANCH="${1:-$(git branch --show-current)}"
SRC_DIR="$PROJECT_ROOT_DIR/local/deploy/src"

UPSTREAM_DIR="$PROJECT_ROOT_DIR/local/deploy/dist"

# Use .distinclude to specify which files to include and exclude from the release.
DIST_FILES="$SRC_DIR/.distinclude"

echo "Testing deployment for branch: $SRC_BRANCH"
echo "Source build directory: $SRC_DIR"
echo "Distribution directory: $UPSTREAM_DIR"

# Clean previous test builds
echo "Cleaning previous test builds..."
rm -rf "$SRC_DIR" "$UPSTREAM_DIR"
mkdir -p "$SRC_DIR" "$UPSTREAM_DIR"

# Copy current working directory (including uncommitted changes)
echo "Copying current working directory..."
rsync --archive --recursive \
    --exclude='.git/' \
    --exclude='node_modules/' \
    --exclude='local/deploy/' \
    --exclude='vendor/' \
    "$PROJECT_ROOT_DIR/" "$SRC_DIR/"

cd "$SRC_DIR"

# Store current commit info (from original repo)
cd "$PROJECT_ROOT_DIR"
SYNCREV=$(git rev-parse HEAD)
LAST_COMMIT_MSG=$(git log -1 --pretty=%B)
cd "$SRC_DIR"

echo "Commit: $SYNCREV"
echo "Message: $LAST_COMMIT_MSG (plus uncommitted changes)"

# Build the release
echo "Building release..."
npm install --ignore-scripts
npm run release

# Copy files to distribution directory (without git operations)
echo "Copying files to distribution directory..."
rsync --archive --recursive --delete --prune-empty-dirs \
	--include-from="$DIST_FILES" \
	"$SRC_DIR/" "$UPSTREAM_DIR/"

# Clean up the source directory (temporary build environment)
echo "Cleaning temporary build environment..."
rm -rf "$SRC_DIR"

# Calculate and display size information
DIST_SIZE=$(du -sh "$UPSTREAM_DIR" | cut -f1)
FILE_COUNT=$(find "$UPSTREAM_DIR" -type f | wc -l | xargs)

echo "Test deployment complete!"
echo ""
echo "Distribution Summary:"
echo "   Size: $DIST_SIZE"
echo "   Files: $FILE_COUNT"
echo ""
echo "You can inspect the built files in:"
echo "   $UPSTREAM_DIR"
echo ""
echo "To see what files were included:"
echo "   ls -la $UPSTREAM_DIR"
echo ""
echo "To see the directory structure:"
echo "   tree $UPSTREAM_DIR"
echo ""
echo "To clean up the test deployment:"
echo "   rm -rf $UPSTREAM_DIR"
