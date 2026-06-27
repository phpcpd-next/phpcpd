#!/usr/bin/env bash
# Build the academic paper (paper/token-based-clone-detection-for-php.tex) into the matching PDF.
#
# Runs pdflatex three times so the table of contents, cross-references, and
# pgfplots coordinates all settle. The bibliography is inline (thebibliography),
# so no bibtex pass is needed. Intermediate files (.aux/.log/.out/.toc) are
# gitignored — see .gitignore.
#
# Usage: bash bin/build-paper.sh [--clean]
#   --clean  remove intermediate build artifacts after a successful build

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PAPER_DIR="$SCRIPT_DIR/../paper"
JOB="token-based-clone-detection-for-php"

command -v pdflatex >/dev/null 2>&1 || {
  echo "ERROR: pdflatex not found. Install TeX Live (e.g. 'apt install texlive-latex-extra texlive-pictures')." >&2
  exit 1
}

cd "$PAPER_DIR"

echo "Building $JOB.pdf (3 passes)…"
for pass in 1 2 3; do
  echo "  pass $pass/3"
  if ! pdflatex -interaction=nonstopmode -halt-on-error "$JOB.tex" > "/tmp/${JOB}-pass${pass}.log" 2>&1; then
    echo "ERROR: pdflatex failed on pass $pass. Tail of log:" >&2
    tail -n 40 "/tmp/${JOB}-pass${pass}.log" >&2
    exit 1
  fi
done

if [[ "${1:-}" == "--clean" ]]; then
  rm -f "$JOB".aux "$JOB".log "$JOB".out "$JOB".toc
  echo "Cleaned intermediate files."
fi

echo "Done: $PAPER_DIR/$JOB.pdf"
