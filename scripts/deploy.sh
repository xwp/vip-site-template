#!/usr/bin/env bash
#
# Accepts upstream git repository URL as the first argument
# and deploy branch as the second argument.
#

# Set this to -ex for verbose output.
set -e

# Set the working directory to the repository root.
cd "$(dirname "$0")/.."

SRC_BRANCH="$2"
SRC_DIR="$PWD/deploy/src"

UPSTREAM_REPO="$1"
UPSTREAM_DIR="$PWD/deploy/dist"
UPSTREAM_BRANCH="deploy/$SRC_BRANCH-$(date -u +%Y%m%d-%H%M%S)"

# Use .distignore to exclude files from the upstream.
DIST_IGNORE="$SRC_DIR/public/.distignore"

if [ -z "$SRC_BRANCH" ] || [ -z "$UPSTREAM_REPO" ]; then
	echo "Please specify the upstream repository and the source branch."
	exit 1
fi

# Ensure we don't corrupt the local development repository.
echo "Copying theme repository to $SRC_DIR for a fresh build"
rm -rf "$SRC_DIR"
mkdir -p "$SRC_DIR"
cp -r "$PWD/.git" "$SRC_DIR/"

# Build everything in the source directory.
cd "$SRC_DIR"

# Checkout the branch to deploy.
echo "Checking out Git branch $SRC_BRANCH"
git checkout --force "$SRC_BRANCH"

# Store the current revision hash.
SYNCREV=$(git rev-parse HEAD)

# Build the release.
npm install
npm run release

# Clone the upstream repository.
echo "Fetching the latest changes from the VIP Go upstream repository:"

export GIT_DIR="$UPSTREAM_DIR/.git"
export GIT_WORK_TREE="$UPSTREAM_DIR"

# Always start with a fresh upstream to ensure consistency.
rm -rf "$UPSTREAM_DIR"

git clone "$UPSTREAM_REPO" "$UPSTREAM_DIR/.git"
git checkout -b "$UPSTREAM_BRANCH" "origin/$SRC_BRANCH"

echo "Copying files to the VIP Go upstream repository:"

# Sync everything from our clean git directory to our git upstream directory
# but exclude all files matched by .distignore.
rsync --archive --delete --prune-empty-dirs \
	--exclude-from "$DIST_IGNORE" --delete-excluded \
	--filter='protect .git' \
	"$SRC_DIR/public/" "$UPSTREAM_DIR/"

# Now deploy everything from the upstream directory.
cd "$UPSTREAM_DIR"

git add --all
git commit --message "Deploy $SRC_BRANCH at $SYNCREV"
git push -u origin "$UPSTREAM_BRANCH"
