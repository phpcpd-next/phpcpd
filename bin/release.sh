#!/usr/bin/env bash
# Usage: bash bin/release.sh 0.2.0
# Updates the VERSION constant and creates a signed git tag.
set -euo pipefail

VERSION="${1:?Usage: $0 <version>  e.g. 0.2.0}"
TAG="v${VERSION}"
FILE="src/CLI/Application.php"

# Validate SemVer shape (no leading v)
if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$ ]]; then
    echo "Error: version must be SemVer without leading 'v' (e.g. 0.2.0 or 0.2.0-rc.1)"
    exit 1
fi

# Check working tree is clean
if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "Error: working tree is not clean. Commit or stash changes first."
    exit 1
fi

# Update VERSION constant
sed -i '' "s/private const string VERSION *= *'[^']*';/private const string VERSION         = '${VERSION}';/" "$FILE"

# Verify the substitution landed
if ! grep -qF "'${VERSION}'" "$FILE"; then
    echo "Error: sed substitution did not land in ${FILE}. Check the pattern."
    exit 1
fi

echo "Updated ${FILE} → VERSION = '${VERSION}'"
echo ""
echo "Next steps:"
echo "  1. Add a CHANGELOG.md entry for [${VERSION}] under [Unreleased]"
echo "  2. git add ${FILE} CHANGELOG.md"
echo "  3. git commit -m \"release: ${TAG}\""
echo "  4. git tag -s ${TAG} -m \"${TAG}\""
echo "  5. git push origin main ${TAG}"
