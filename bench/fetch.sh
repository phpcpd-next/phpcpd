#!/usr/bin/env bash
# BCB-PHP corpus fetch script.
# Clones each corpus at its pinned SHA into bench/corpus/<name>/.
# On first run, records the current HEAD SHA into manifest.json (requires jq).
# On subsequent runs, checks out the pinned SHA for reproducibility.
#
# Usage: bash bench/fetch.sh [--update-shas]
#   --update-shas  re-pin all corpora to their current HEAD (for benchmark updates)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CORPUS_DIR="$SCRIPT_DIR/corpus"
MANIFEST="$SCRIPT_DIR/manifest.json"

UPDATE_SHAS=0
for arg in "$@"; do
  [[ "$arg" == "--update-shas" ]] && UPDATE_SHAS=1
done

require_cmd() { command -v "$1" &>/dev/null || { echo "ERROR: $1 not found"; exit 1; }; }
require_cmd git
require_cmd jq

mkdir -p "$CORPUS_DIR"

fetch_corpus() {
  local name="$1"
  local repo; repo=$(jq -r ".corpora[\"$name\"].repo" "$MANIFEST")
  local pinned_sha; pinned_sha=$(jq -r ".corpora[\"$name\"].sha" "$MANIFEST")
  local dest="$CORPUS_DIR/$name"

  echo ""
  echo "=== $name ==="

  if [[ -d "$dest/.git" ]]; then
    echo "  already cloned — fetching latest"
    git -C "$dest" fetch --quiet origin
  else
    echo "  cloning $repo"
    git clone --quiet "$repo" "$dest"
  fi

  if [[ "$UPDATE_SHAS" == "1" || -z "$pinned_sha" ]]; then
    local head_sha; head_sha=$(git -C "$dest" rev-parse HEAD)
    echo "  pinning SHA: $head_sha"
    local tmp; tmp=$(mktemp)
    jq --arg n "$name" --arg s "$head_sha" \
      '.corpora[$n].sha = $s' "$MANIFEST" > "$tmp" && mv "$tmp" "$MANIFEST"
    pinned_sha="$head_sha"
  fi

  echo "  checking out $pinned_sha"
  git -C "$dest" checkout --quiet "$pinned_sha"

  # Report stats
  local php_count; php_count=$(find "$dest" -name "*.php" -not -path "*/vendor/*" -not -path "*/node_modules/*" | wc -l | tr -d ' ')
  echo "  PHP files: $php_count"
}

CORPORA=(wordpress symfony-string symfony-console phpunit php-parser firefly-iii)
for corpus in "${CORPORA[@]}"; do
  fetch_corpus "$corpus"
done

# Fetch the reference phar used by bench/run-compare.php
PHAR_DIR="$SCRIPT_DIR/vendor"
PHAR="$PHAR_DIR/phpcpd.phar"
PHAR_URL="https://github.com/sebastianbergmann/phpcpd/releases/download/6.0.3/phpcpd.phar"
mkdir -p "$PHAR_DIR"
if [[ ! -f "$PHAR" ]]; then
  echo ""
  echo "=== phpcpd.phar (v6.0.3) ==="
  echo "  downloading from GitHub releases"
  curl -fsSL "$PHAR_URL" -o "$PHAR"
  chmod +x "$PHAR"
  echo "  saved to $PHAR"
else
  echo ""
  echo "=== phpcpd.phar already present, skipping ==="
fi

echo ""
echo "Done. Corpora in $CORPUS_DIR"
echo "Run bench/measure-density.php to plot the type-density × clone-density map."
