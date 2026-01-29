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

# Check if gh CLI is available
if ! command -v gh &> /dev/null; then
    error "GitHub CLI (gh) is not installed. Install it from https://cli.github.com"
fi

# Check for uncommitted changes
if [[ -n $(git status --porcelain) ]]; then
    error "Working directory has uncommitted changes. Please commit or stash them first."
fi

# Check if tag already exists
if git rev-parse "$VERSION" >/dev/null 2>&1; then
    error "Tag $VERSION already exists."
fi

# Get previous tag for changelog
PREVIOUS_TAG=$(git describe --tags --abbrev=0 2>/dev/null || echo "")

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

# Generate changelog
info "Generating changelog..."
if [[ -n "$PREVIOUS_TAG" ]]; then
    CHANGELOG=$(git log --pretty=format:"- %s" "$PREVIOUS_TAG"..HEAD --no-merges | grep -v "chore: Bump version")
else
    CHANGELOG=$(git log --pretty=format:"- %s" --no-merges | grep -v "chore: Bump version")
fi

# Create GitHub release
info "Creating GitHub release..."
gh release create "$VERSION" \
    --title "Release $VERSION" \
    --notes "## What's Changed

$CHANGELOG

## Installation

\`\`\`bash
composer require enhancely/enhancely-for-typo3:$VERSION
\`\`\`

**Full Changelog**: https://github.com/dkd-dobberkau/enhancely-typo3/compare/${PREVIOUS_TAG:-HEAD~10}...$VERSION"

success "Release $VERSION completed!"
echo ""
echo "Links:"
echo "  - GitHub: https://github.com/dkd-dobberkau/enhancely-typo3/releases/tag/$VERSION"
echo "  - Packagist: https://packagist.org/packages/enhancely/enhancely-for-typo3#$VERSION"
