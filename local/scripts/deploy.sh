#!/usr/bin/env bash
#
# Accepts upstream git repository URL as the first argument
# and deploy branch as the second argument.
#

# Set this to -ex for verbose output.
set -e

# Set the working directory to the repository root.
PROJECT_DOOR_DIR="$(git rev-parse --show-toplevel)"

SRC_BRANCH="$2"
SRC_DIR="$PROJECT_DOOR_DIR/local/deploy/src"

UPSTREAM_REPO="$1"
UPSTREAM_DIR="$PROJECT_DOOR_DIR/local/deploy/dist"
UPSTREAM_BRANCH="$SRC_BRANCH-built"

# Use .distinclude to specify which files to include and exclude from the release.
DIST_FILES="$SRC_DIR/.distinclude"

if [ -z "$SRC_BRANCH" ] || [ -z "$UPSTREAM_REPO" ]; then
	echo "Please specify the upstream repository and the source branch."
	exit 1
fi

# Ensure we don't corrupt the local development repository.
echo "Copying theme repository to $SRC_DIR for a fresh build"
rm -rf "$SRC_DIR"
mkdir -p "$SRC_DIR"
cp -r "$PROJECT_DOOR_DIR/.git" "$SRC_DIR/"

# Build everything in the source directory.
cd "$SRC_DIR"

# Checkout the branch to deploy.
echo "Checking out Git branch $SRC_BRANCH"
git checkout --force "$SRC_BRANCH"

# Store the current revision hash.
SYNCREV=$(git rev-parse HEAD)

# Build the release.
npm install --ignore-scripts
npm run release

# Clone the upstream repository.
echo "Fetching the latest changes from the VIP Go upstream repository:"

export GIT_DIR="$UPSTREAM_DIR/.git"
export GIT_WORK_TREE="$UPSTREAM_DIR"

# Always start with a fresh upstream to ensure consistency.
rm -rf "$UPSTREAM_DIR"

git clone "$UPSTREAM_REPO" "$UPSTREAM_DIR/.git"
git checkout -B "$UPSTREAM_BRANCH" "origin/$UPSTREAM_BRANCH"

# Ensure we remove everything before copying over the contents
# such as submodule references, etc.
git rm -r --quiet .

echo "Copying files to the VIP Go upstream repository:"

# Sync everything from our clean git directory to our git upstream directory.
rsync --archive --recursive --filter='protect .git' --delete --prune-empty-dirs \
	--include-from="$DIST_FILES" \
	"$SRC_DIR/" "$UPSTREAM_DIR/"

# Stage all changes.
git add --all

# Commit and deploy if changes found.
if ! git diff-index --cached --quiet HEAD --; then
	git commit --message "Deploy $SRC_BRANCH at $SYNCREV"
	git push -u origin "$UPSTREAM_BRANCH"

	echo "Branch $SRC_BRANCH was built and deployed to $UPSTREAM_BRANCH."
else
	echo "Skipping deploy because the build is already in sync with $UPSTREAM_BRANCH."
fi
