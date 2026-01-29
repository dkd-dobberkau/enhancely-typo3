#!/bin/bash
#
# Release script for Enhancely TYPO3 Extension
# Usage: ./release.sh <version>
# Example: ./release.sh 1.0.0
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
error() { echo -e "${RED}Error: $1${NC}" >&2; exit 1; }
success() { echo -e "${GREEN}$1${NC}"; }
info() { echo -e "${YELLOW}$1${NC}"; }

# Check argument
VERSION="$1"
if [[ -z "$VERSION" ]]; then
    error "Usage: ./release.sh <version>\nExample: ./release.sh 1.0.0"
fi

# Validate semantic version format
if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$ ]]; then
    error "Invalid version format. Use semantic versioning (e.g., 1.0.0, 2.1.0-beta.1)"
fi

# Check for uncommitted changes
if [[ -n $(git status --porcelain) ]]; then
    error "Working directory has uncommitted changes. Please commit or stash them first."
fi

# Check if tag already exists
if git rev-parse "v$VERSION" >/dev/null 2>&1 || git rev-parse "$VERSION" >/dev/null 2>&1; then
    error "Tag $VERSION or v$VERSION already exists."
fi

# Update version in ext_emconf.php
info "Updating version in ext_emconf.php..."
if [[ -f "ext_emconf.php" ]]; then
    sed -i '' "s/'version' => '[^']*'/'version' => '$VERSION'/" ext_emconf.php
    success "Updated ext_emconf.php to version $VERSION"
else
    error "ext_emconf.php not found"
fi

# Commit version change
info "Committing version update..."
git add ext_emconf.php
git commit -m "chore: Bump version to $VERSION"

# Create annotated tag
info "Creating tag $VERSION..."
git tag -a "$VERSION" -m "Release $VERSION"

# Push commit and tag
info "Pushing to remote..."
git push origin main
git push origin "$VERSION"

success "Release $VERSION completed!"
echo ""
echo "Next steps:"
echo "  1. Packagist will auto-update via webhook (if configured)"
echo "  2. Create GitHub release: https://github.com/dkd-dobberkau/enhancely-typo3/releases/new?tag=$VERSION"
