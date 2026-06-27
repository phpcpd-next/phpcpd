<p align="center">
  <img src="assets/logo.png" alt="phpcpd-next">
</p>
    
# phpcpd-next

**Token-based copy/paste detection for PHP 8.5+ — a maintained successor to phpcpd, with Type-3 and reorder-tolerant detection.**

A maintained, dependency-free successor to the archived
[`sebastianbergmann/phpcpd`](https://github.com/sebastianbergmann/phpcpd). It finds duplicated
code — and, unlike most copy/paste detectors, it ships **three complementary detection engines**
so it can see exact copies, *gapped* near-misses, and even *reordered* clones.

> Drop-in replacement: the command is still `phpcpd`, and the default engine behaves like the
> original. The new capabilities are opt-in flags.

---

## Why another clone detector?

The common wisdom is that phpcpd only finds Type-1/2 (exact / renamed) clones. That was only ever
true of its *default* engine. phpcpd-next exposes and extends the full picture:

| Clone type | Example | Engine |
|------------|---------|--------|
| **Type-1** exact | identical code | `rabin-karp` (default) |
| **Type-2** renamed | same code, different identifiers | any engine, with `--fuzzy` |
| **Type-3** gapped | a statement inserted/deleted/changed | `suffixtree` |
| **Type-3** reordered | statements shuffled | `tokenbag` |

It also flags **inconsistent clones** — near-miss copies that have diverged — which is where
duplication tends to hide bugs (one copy patched, its sibling not).

## Requirements

- PHP **8.5+**
- ext-dom, ext-mbstring

**Zero Composer dependencies.** Nothing from the PHPUnit/sebastian release train at runtime.

## Installation

```bash
# once published to Packagist:
composer require --dev phpcpd-next/phpcpd

# or from source:
git clone https://github.com/phpcpd-next/phpcpd.git
cd phpcpd && composer install
./phpcpd --version
```

## Quick start

```bash
# default: exact (Type-1/2) duplication
phpcpd src/

# rename-insensitive (Type-2)
phpcpd --fuzzy src/

# gapped / near-miss clones, and flag inconsistent ones
phpcpd --algorithm=suffixtree src/

# reordered clones (statements shuffled within a function)
phpcpd --algorithm=tokenbag src/
```

phpcpd-next exits with status **1** when clones are found (or on error) and **0** when none are —
so it works as a CI gate out of the box.

## The three engines

Pick with `--algorithm`:

- **`rabin-karp`** (default) — exact contiguous duplication via a rolling hash. Fast, the classic
  phpcpd behaviour. Add `--fuzzy` for renamed-identifier (Type-2) matches.
- **`suffixtree`** — approximate matching with a configurable edit budget (`--edit-distance`).
  Detects **gapped (Type-3)** clones where statements were inserted, deleted, or changed, and marks
  copies that have **diverged** as `[inconsistent]`. Type-aware: a changed control keyword
  (`if`→`while`) costs more of the edit budget than a renamed identifier.
- **`tokenbag`** — order-invariant overlap (a SourcererCC-style token bag + inverted index).
  Detects clones where statements were **reordered** — which the contiguous engines cannot. Tune
  with `--min-similarity` (default 0.7).

They are complementary, not redundant: the contiguous engines are precise about structure; the
token bag tolerates shuffling.

## Output formats

Console output is human-readable by default. Machine-readable reports are written to a file:

```bash
phpcpd --log-pmd=report.xml      src/   # PMD-CPD XML (Jenkins, SonarQube, ...)
phpcpd --log-json=report.json    src/   # JSON, for scripts and custom dashboards
phpcpd --log-sarif=report.sarif  src/   # SARIF 2.1.0, for GitHub Code Scanning
```

You can request several at once. SARIF maps **inconsistent clones to `warning`** and exact clones
to `note`, so the bug-bearing duplication surfaces at a higher severity.

### GitHub Code Scanning

```yaml
- name: Detect duplicated code
  run: ./phpcpd --algorithm=suffixtree --log-sarif=phpcpd.sarif src/ || true

- name: Upload results
  uses: github/codeql-action/upload-sarif@v3
  with:
    sarif_file: phpcpd.sarif
```

Clones then appear as annotations in the PR and in the repository's Security tab.

## Options

```
Options for selecting files:
  --suffix <suffix>       Include files ending in <suffix> (default: .php; repeatable)
  --exclude <path>        Exclude paths (substring or glob, e.g. '*.blade.php'; repeatable)

Options for analysing files:
  --fuzzy                 Rename-insensitive (Type-2) matching
  --min-lines <N>         Minimum identical lines (default: 5)
  --min-tokens <N>        Minimum identical tokens (default: 70)
  --algorithm <name>      'rabin-karp' (default), 'suffixtree', or 'tokenbag'
  --min-similarity <0-1>  Minimum token-bag overlap (tokenbag only; default: 0.7)
  --edit-distance <N>     Edit budget (suffixtree only; default: 5)
  --head-equality <N>     Exact-match prefix length (suffixtree only; default: 10)
  --verbose               Print the duplicated code for each clone

Options for report generation:
  --log-pmd <file>        PMD-CPD XML
  --log-json <file>       JSON
  --log-sarif <file>      SARIF 2.1.0 (GitHub Code Scanning)

Options for CI integration:
  --cache                 Cache results in '.phpcpd-cache/' for faster re-runs
  --cache-dir <path>      Cache directory (implies --cache; overrides default)
  --incremental           Per-file index: re-tokenize only changed files (rabin-karp)

General:
  -h, --help              Print help
  -v, --version           Print version
```

## Incremental caching (CI)

`--cache` stores the run's results keyed by a fingerprint of the configuration and a manifest of
every scanned file's hash. On a re-run with the **same files and config**, detection is skipped
entirely and the cached result is replayed (the run prints `(cache hit)`). Any changed, added, or
removed file is a miss and triggers a full re-scan. Different algorithm/threshold combinations get
separate cache entries, so they never collide.

Mount the cache directory with `actions/cache` to carry it between CI runs:

```yaml
- uses: actions/cache@v4
  with:
    path: .phpcpd-cache
    key: phpcpd-${{ hashFiles('**/*.php') }}
    restore-keys: phpcpd-
- run: ./phpcpd --cache-dir .phpcpd-cache src/
```

### Per-file incremental index (`--incremental`)

`--cache` is all-or-nothing: a single changed file invalidates the whole run. `--incremental`
(Rabin–Karp only) is finer-grained — it persists each file's tokenization keyed by a content hash,
and on a re-run **re-tokenizes only the files that changed**, replaying the rest straight from the
index. The run prints what it did, e.g. `(incremental index: 412 reused, 3 scanned)`.

The result is identical to a full scan — only the work differs — so it stays correct as files come
and go between runs. Use it on large codebases where most files are untouched between CI runs; mount
the same `.phpcpd-cache` directory with `actions/cache` as above. (Requested with another algorithm,
the flag is ignored and the run falls back to the coarse `--cache`.)

```yaml
- run: ./phpcpd --incremental --cache-dir .phpcpd-cache src/
```

## Lineage and license

phpcpd-next is a fork of `sebastianbergmann/phpcpd`, created by Sebastian Bergmann and archived in
2023. The original copyright is retained throughout; this fork is maintained by Luciano Federico
Pereira. Licensed under **BSD-3-Clause** — see [LICENSE](LICENSE).

The diff-by-diff story of the modernisation and the new detection capabilities lives in
[MODERNIZATION.md](MODERNIZATION.md); the research grounding is in the [paper](paper/token-based-clone-detection-for-php.pdf). Contributions are
welcome under the [Contributor License Agreement](CLA.md) — see [CONTRIBUTING.md](CONTRIBUTING.md).
