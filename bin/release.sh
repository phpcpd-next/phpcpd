#!/usr/bin/env bash
# Usage: bash bin/release.sh 1.2.0
# Bumps the version constant and prints the remaining release steps. It does not
# commit, tag, or push — those stay in your hands.
set -euo pipefail

VERSION="${1:?Usage: $0 <version>  e.g. 1.2.0}"
FILE="src/Version.php"

# The version constant is always full SemVer; the tag drops a `.0` patch to
# match the published tags (1.3.0 → v1.3). A non-zero patch keeps it, so a
# fix release stays distinct from the minor it patches (1.2.1 → v1.2.1), and a
# pre-release suffix is never stripped (1.3.0-rc.1 → v1.3.0-rc.1).
TAG="v${VERSION%.0}"

# Validate SemVer shape (no leading v)
if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$ ]]; then
    echo "Error: version must be SemVer without leading 'v' (e.g. 1.2.0 or 1.2.0-rc.1)"
    exit 1
fi

# Check working tree is clean
if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "Error: working tree is not clean. Commit or stash changes first."
    exit 1
fi

# Update the NUMBER constant.
# Portable in-place edit: BSD/macOS sed wants `-i ''` while GNU/Linux sed wants
# `-i` with no argument, so avoid `-i` entirely. Writing through a temp file and
# `cat`-ing back preserves the original file's permissions and inode.
tmp="${FILE}.tmp"
sed "s/const string NUMBER *= *'[^']*';/const string NUMBER = '${VERSION}';/" "$FILE" > "$tmp"
cat "$tmp" > "$FILE"
rm -f "$tmp"

# Verify the substitution landed
if ! grep -qF "'${VERSION}'" "$FILE"; then
    echo "Error: sed substitution did not land in ${FILE}. Check the pattern."
    exit 1
fi

echo "Updated ${FILE} → NUMBER = '${VERSION}'"
echo ""
echo "Next steps:"
echo "  1. Move the CHANGELOG.md [Unreleased] section under a [${VERSION}] heading"
echo "  2. git add ${FILE} CHANGELOG.md"
echo "  3. git commit -m \"release: ${TAG}\""
echo "  4. git tag -s ${TAG} -m \"${TAG}\""
echo "  5. git push origin main ${TAG}"
