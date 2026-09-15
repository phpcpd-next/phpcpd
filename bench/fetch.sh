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

  # The manifest's own `strip` list, applied. It was declared per corpus and
  # read by nothing: this script took `repo` and `sha` and stopped there, and
  # `bcb_files()` happens to carry a hardcoded default that covers most of what
  # the strip entries name. Most, not all — firefly-iii's `database/migrations`
  # is named by the manifest, is not in that default, and so sat in the corpus
  # and in every number taken over it. Sixty files of `up()`/`down()` scaffolding
  # that the shipped Laravel preset excludes by name, for the reason written
  # beside it there: "up()/down() boilerplate is duplicate by design".
  #
  # A corpus that does not match its manifest is not pinned to anything, which
  # is the whole point of pinning one.
  local stripped=0
  while IFS= read -r path; do
    [[ -z "$path" || "$path" == "null" ]] && continue

    if [[ -e "$dest/$path" ]]; then
      rm -rf "${dest:?}/$path"
      stripped=$((stripped + 1))
      echo "  stripped $path"
    fi
  done < <(jq -r ".corpora[\"$name\"].strip // [] | .[]" "$MANIFEST")

  # Report stats
  local php_count; php_count=$(find "$dest" -name "*.php" -not -path "*/vendor/*" -not -path "*/node_modules/*" | wc -l | tr -d ' ')
  echo "  PHP files: $php_count"
}

# Does a corpus already on disk still hold something its manifest strips?
#
# `fetch.sh` strips on checkout, and a corpus fetched before that was written
# keeps whatever it was given. Saying so is cheap; deleting somebody's corpus
# behind their back, and moving every number taken over it, is not.
check_stripped() {
  local name="$1"
  local dest="$CORPUS_DIR/$name"

  [[ -d "$dest" ]] || return 0

  while IFS= read -r path; do
    [[ -z "$path" || "$path" == "null" ]] && continue

    if [[ -e "$dest/$path" ]]; then
      echo "  WARNING: $name still holds $path, which the manifest strips."
      echo "           Every number taken over this corpus includes it."
      echo "           Re-run fetch.sh for $name, or remove it deliberately."
    fi
  done < <(jq -r ".corpora[\"$name\"].strip // [] | .[]" "$MANIFEST")
}

CORPORA=(wordpress symfony-string symfony-console phpunit php-parser firefly-iii)

# Say what is already wrong before changing anything, so a run that fetches
# nothing still reports a corpus that does not match its manifest.
for corpus in "${CORPORA[@]}"; do
  check_stripped "$corpus"
done

for corpus in "${CORPORA[@]}"; do
  fetch_corpus "$corpus"
done

# Fetch the reference phar used by bench/run-compare.php
PHAR_DIR="$SCRIPT_DIR/vendor"
PHAR="$PHAR_DIR/phpcpd.phar"
# phar.phpunit.de, not GitHub releases: sebastianbergmann/phpcpd is archived and
# its release assets are gone, so the old releases/download/6.0.3 URL 404s.
PHAR_URL="https://phar.phpunit.de/phpcpd-6.0.3.phar"
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
