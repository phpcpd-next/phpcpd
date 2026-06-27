# Changelog

All notable changes to **phpcpd-next** are documented here.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)  
Versioning: [Semantic Versioning](https://semver.org/spec/v2.0.0.html)

> For the complete technical diff against upstream (every changed line with a *Why* explanation),
> see [MODERNIZATION.md](MODERNIZATION.md).

---

## [1.0.0] - 2026-06-27

### Performance

- **Banded edit-distance DP in the suffix-tree engine.** Profiling showed the approximate-matching
  DP — not construction — dominated `findClones` and grew super-linearly with `--edit-distance`. Since
  a cell `(i,j)` with `|i−j| > maxErrors` can never lie on a sub-threshold path, the DP is restricted
  to the diagonal band of width `2·maxErrors+1` (Ukkonen cutoff), turning the per-clone cost from
  `O(L²)` to `O(L·maxErrors)`. Measured **~3.5× faster** at every edit distance on a Firefly III slice,
  with **byte-identical** clone output.

### Fixed

- **Degenerate zero-line clones** are no longer reported by the suffix-tree engine. A clone whose
  in-file span collapsed to zero lines (its matched run lay almost entirely beyond a file boundary) was
  emitted as meaningless `(0 lines)` noise; such clones are now skipped.

### Added — detection

- **Type-2 detection on every engine** via `--fuzzy`: a shared `TokenNormalizer` abstracts
  identifiers and literals to type classes (previously `--fuzzy` only touched variables, and only
  in the default engine — the suffix tree had no Type-2 at all).
- **Inconsistent-clone reporting**: gapped (Type-3) clones are distinguished from exact copies
  (`CodeClone::isGapped()`), marked `[inconsistent]` in console output and surfaced as `warning`
  severity in SARIF.
- **Type-aware edit weights** in the suffix-tree engine: a changed control keyword (`if`→`while`)
  costs more of the `--edit-distance` budget than a renamed identifier.
- **New `tokenbag` engine** (`--algorithm=tokenbag`): a SourcererCC-style order-invariant token
  bag + inverted index that detects **reordered** clones the contiguous engines miss. Threshold
  via `--min-similarity` (default 0.7).

### Added — CI

- **Incremental result cache** (`--cache` / `--cache-dir`): keyed by a fingerprint of the
  configuration and a manifest of file hashes; a re-run on unchanged files skips detection
  entirely and prints `(cache hit)`. Designed to be mounted with `actions/cache`.
- **Per-file incremental index** (`--incremental`, Rabin–Karp only): Hummel-style index that
  persists each file's tokenization and re-tokenizes **only the files that changed**, replaying the
  rest from the index. Finer-grained than `--cache` (one edit no longer invalidates the whole run)
  and provably equivalent to a full scan. Prints `(incremental index: N reused, M scanned)`.

### Added — output

- **JSON** report (`--log-json`) and **SARIF 2.1.0** report (`--log-sarif`, for GitHub Code
  Scanning), alongside the existing PMD-CPD XML. A shared `Log\Logger` contract unifies them.

### Changed

- **Zero runtime Composer dependencies**: `sebastian/version`, `sebastian/cli-parser`,
  `phpunit/php-file-iterator`, and `phpunit/php-timer` were removed — replaced with owned,
  improved code (a declarative self-documenting CLI parser with value validation; a file finder
  that prunes excluded directories and supports glob excludes; a timer that reports throughput).
- Namespace migrated to `LucianoPereira\PhpcpdNext`; autoloading switched from classmap to PSR-4.
- PMD XML logger simplified to use DOM-native escaping.

---

## [0.1.0] — 2026-06-26

First release of **phpcpd-next**. Picks up where Sebastian Bergmann's archived
`sebastianbergmann/phpcpd` (7.0-dev) left off and brings the tool forward to PHP 8.5.

### Platform

- Requires PHP **≥ 8.5** (upstream required ≥ 8.1)
- `composer.json` platform locked to `8.5.0`

### Fixed

- **`sebastian/version` v4 API break** — `getVersion()` renamed to `asString()` in v4;
  the banner was crashing silently on import.
- **`empty($object)` always false** — `SuffixTreeStrategy` used `empty($this->result)` on
  a `CodeCloneMap` object; `empty()` on any object always returns `false`. Fixed to
  `=== null`.
- **Division by zero** — `CodeCloneMap::averageSize()` divided by `count()` without
  guarding the empty case. Fixed with an early `return 0.0`.
- **`current()` returning `false`** — `CodeClone::lines()` called `current()` on an
  associative array and used the result as a `CodeCloneFile`; `current()` returns `false`
  on an empty array. Replaced with `array_values($this->files)[0]` which is guaranteed safe
  after the existing non-empty guard.
- **`file_get_contents()` false return** — both `DefaultStrategy` and `SuffixTreeStrategy`
  passed the raw `string|false` return directly into tokenisation. Added `if ($buffer === false) { return; }` guards.
- **`file()` returning `false`** — `CodeClone::lines()` called `file()` without checking
  the return. Fixed with `?: []` fallback.
- **`mb_convert_encoding()` returning `false`** — `AbstractXmlLogger` did not check the
  return of `mb_convert_encoding()`, which returns `false` on encoding failure. Fixed with
  an explicit false-check and fallback to the original string.
- **`preg_replace()` returning `null`** — `AbstractXmlLogger::toUtf8String()` could return
  `string|null` from `preg_replace`. Fixed with `?? $string` fallback.

### Changed

- **Banner** updated to credit both the original author and the fork:
  `phpcpd 0.1.0 by Luciano Federico Pereira based on phpcpd 7.0-dev by Sebastian Bergmann.`

### Modernised (PHP 8.0 – 8.5)

- `readonly class` applied to `Arguments`, `CodeCloneFile`, `StrategyConfiguration`,
  `CloneInfo` — immutability enforced at the class level.
- Constructor property promotion on all eligible classes — eliminates boilerplate
  `$this->x = $x` assignments.
- `#[\Override]` attribute on every method that implements or overrides a contract.
- Typed class constants (`private const string`, `private const int`) throughout.
- `foreach ($array as $item)` replaces `foreach (array_keys($array) as $k)` where the key
  was never used.
- `$result === null` replaces `empty($result)` wherever the variable is an object or
  nullable type.

### Improved

- **Duplicate code eliminated** — `DefaultStrategy::processFile()` contained two identical
  17-line blocks that built and recorded a `CodeClone`. Extracted to
  `recordCloneIfValid()`. Running the tool on its own source now reports zero clones.
- PHPDoc generics (`list<T>`, `@template`, `@implements`) on all collection classes.

### Toolchain (new files)

- **PHPStan level 9** — zero errors. `phpstan.neon` + `phpstan-stubs.php` for the
  untyped `sebastian/cli-parser` return.
- **PHP-CS-Fixer** — `@PER-CS2.0` + risky fixers (`declare_strict_types`,
  `native_function_invocation`, `strict_param`, …).
- **Rector** — `php85` set, `CODE_QUALITY`, `TYPE_DECLARATION`.
- **PHPUnit 12** — `phpunit.xml` wired; test writing is the next milestone.
- **GitHub Actions CI** — `audit → lint → analyse → test` on every push.
- **`.editorconfig`** — consistent whitespace before any tool runs.
- **Composer scripts** — `lint`, `lint:fix`, `analyse`, `test`, `check`.

[1.0.0]: https://github.com/phpcpd-next/phpcpd/releases/tag/v1.0.0
[0.1.0]: https://github.com/phpcpd-next/phpcpd/releases/tag/v0.1.0
