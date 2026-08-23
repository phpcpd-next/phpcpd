# Changelog

All notable changes to **phpcpd-next** are documented here.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)  
Versioning: [Semantic Versioning](https://semver.org/spec/v2.0.0.html)

> For the complete technical diff against upstream (every changed line with a *Why* explanation),
> see [MODERNIZATION.md](MODERNIZATION.md).

---

## [1.4.0] - 2026-08-23

### Fixed — scanning a cache directory could turn a failing gate green

Measured against a 63k-line project: pointing `--orphans` at the project root scanned
`.phpstan.cache/` along with everything else, and reported **0 orphaned, exit 0**. The correct answer
was **21 orphaned, exit 1**.

A static-analysis result cache embeds the fully-qualified name of every class it analysed as a string
literal, so it satisfied the reference check for 21 symbols that are genuinely unreferenced. It also
made the run 52× slower and used 21× the memory (4m 0.7s / 1100 MB against 4.6s / 51 MB). The cost
was visible; the wrong verdict was not, and nothing in the output hinted that a question had gone
unanswered.

- **Generated and cache trees are now excluded by default** — `vendor`, `node_modules`, `.git`,
  `.phpstan.cache`, `.phpunit.cache`, `.php-cs-fixer.cache`, `.psalm-cache`, `.rector.cache`,
  `var/cache`, `storage/framework`, `bootstrap/cache`, `build`, `dist`, `out`, `coverage`.
  Disable with `--no-default-excludes`.
- Defaults match whole path **segments**, unlike the substring-based `--exclude`, so a default named
  `out` prunes `out/` and never `routes/`.
- A file whose first 2 KB contains `@generated`, `Do not edit`, or `Auto-generated` is skipped
  wherever it lives.
- **Every run states its scope** — `Scanned 764 files (3 directories, 15 exclude patterns applied).`
  A file count wildly out of step with the project is what makes a contaminated run obvious.

### Fixed — the test guard blocked every runner but two

`tests/_guard.php` detected direct execution by asking whether the entry point was *named* `phpunit`
or `pest`. Every other runner — `paratest`, `infection`, a `phpdbg` run, an IDE run configuration,
any PHPUnit-compatible runner — was classified as direct execution and killed at `require_once` time,
before a single test ran. The runner then reported an empty or aborted suite rather than a reason,
which is the failure mode hardest to read: nothing failed, so nothing looks wrong.

The guard now compares `SCRIPT_FILENAME` with its own caller's path. Direct execution is exactly the
case where those are the same file, which is checkable without knowing any runner's name — correct
for every runner that exists and every one that does not exist yet. Same intent, same message. Both
branches are covered by tests.

### Added — suppression rules for structurally-explained symbols

Every finding in the measured run was a false positive with a structural explanation. Six rules now
recognise them. Each keys on a *structural* property — a guard statement, a namespace, a manifest
entry, a fixture path — never a name pattern or a guess about intent:

| Rule | Recognises |
|------|------------|
| `conditional` | Declared inside `if (!function_exists('x'))` and friends — a polyfill or shim, by definition declared for an external caller. |
| `namespace` | Declared outside every `psr-4`/`psr-0` prefix the project's `composer.json` declares. |
| `manifest` | An `autoload.files` entry point, or an FQN under `extra` (Laravel providers/aliases). |
| `config` | Named in a `.neon`, `.yaml`, `.yml`, `.xml`, or `.dist` file. |
| `fixtures` | Under a `Fixtures`/`Stubs` directory inside a test tree. |
| `keep` / `entrypoint` / `planned` | Docblock tags and framework attributes (previously silent). |

**A suppressed symbol is never dropped.** It moves to its own counted tier, always printed as a
census by rule and listed in full with `--explain`. A rule that starts over-firing therefore shows up
as a number that moved — a silent suppression would quietly turn a real orphan into no output at all,
which is the same failure shape as the cache-directory bug above.

`composer.json` is now read as a set of reference roots: `bin` entry points (conventionally
extensionless, so a `.php` suffix filter never saw the one file where top-level wiring lives) are
added to the scan, and a file with no recognised suffix is accepted when its `#!` line names php.

### Added — `@phpcpd-planned`, and reasons on tags

`@phpcpd-keep` asserts *this symbol is reachable, you just can't see it*. That is false for code
written ahead of the work that will wire it, and marking such code with a keep tag means nothing
prompts its removal later. `@phpcpd-planned` makes the opposite claim, and its symbols are reported
as their own group — a staged-work inventory derived from the source rather than from a tracker.

A `@phpcpd-planned` symbol that later becomes referenced is reported as a possible finding: the tag
has served its purpose and should be deleted. `@phpcpd-keep` can never give that prompt.

Both tags take a free-text reason, printed next to the symbol, so a suppression whose stated reason
has gone stale becomes reviewable.

### Added — clone suppression

There was previously **no way to declare a duplication intentional**; the only remedy was `--exclude`
on the whole file, which also hid the duplication worth fixing.

```php
// phpcpd-ignore-start ... // phpcpd-ignore-end
/** @phpcpd-ignore-clone Dispatch table — one arm per block type, by design. */
$x = $y; // phpcpd-ignore-line
```

A clone is dropped when any of its copies intersects a suppressed range. Markers are read only from
files that took part in a clone, so an unmarked codebase pays nothing.

### Added — `phpcpd.ini`

Per-project settings whose keys **are the long option names**, so there is no second vocabulary and
an option is configurable the day it ships. The file is found from the paths being scanned rather
than from the working directory, so `phpcpd ../other-project/src` picks up that project's settings.

Layered, each overriding the last: built-in defaults → `~/.config/phpcpd/phpcpd.ini` → project
`phpcpd.ini` → command line. Single-valued keys are replaced by the closer layer; repeatable ones
(`exclude`, `suffix`) append. `--config <file>` names one explicitly; `--no-config` ignores them all.
Unknown keys and invalid values are rejected by name, exactly as the equivalent flag would be.

### Added — `--show-config`

Layering is only trustworthy if it can be inspected, and a setting no file mentions keeps a built-in
default that appears in no file at all. `--show-config` prints every setting in force and names the
layer that produced it, marking fallbacks with `(*)`. A repeatable setting names every layer that
contributed, since those append rather than replace.

### Fixed — the default run and `--orphans` could disagree about reachability

Composer `bin` entry points were added to the scan in `--orphans` mode but not in the default run, so
a class instantiated only from an extensionless console entry point could appear in the default run's
advisory and not under `--orphans`. Entry points are now resolved once, for both modes.

### Added — findings grouped by cause, with evidence

The report repeated one of two sentences across every entry, so a reader had to re-derive each one by
hand. Findings are now grouped by cause, which makes the group worth reading — the symbols nothing
explains — visible instead of buried. A string-literal demotion cites **where** the name appears:

```text
→ never referenced in code; name appears in a string literal (possible dynamic use)
  ⤷ name appears at src/Support/Registry.php:23
```

### Added — `--fail-on`, `--no-suppress`, `--explain`

Rather than a flag per feature, three knobs named after what the report prints:

- `--no-suppress=<rules>` turns rules off by name (or `all`). A disabled rule's symbols are judged
  normally rather than skipped, which is what makes it a way to audit the rule itself.
- `--fail-on=<tiers>` chooses what gates CI; `dead` alone by default. `--fail-on=dead,planned` makes
  shipping staged, unwired components a conscious decision.
- `--explain` lists suppressed symbols instead of only counting them.

### Fixed — docblock tag matching was substring-based

`str_contains($doc, '@api')` fired on prose: `Unlike @api classes, this one is internal` silently
suppressed a real finding. Tags must now start a docblock line.

### Changed

- `OrphanResult` gained `suppressed()`, `planned()`, `tier()`, `entries()` and `fails()`.
  `all()`, `definite()`, `possible()`, `count()` and `isEmpty()` speak only about findings, so a scan
  that suppresses everything still reads as "no orphans".
- `Orphans::detect()` accepts `noSuppress`, `failOn` and `defaultExcludes`.
- `Symbol` replaces `$suppressed`/`$entrypoint` with `$rule`/`$ruleReason`; `Orphan` gained `$rule`
  and `$evidence`.

## [1.3.0] - 2026-08-18

### Fixed — orphan detection: block-structure tracking and aliased imports

Three unrelated PHP constructs desynced the symbol collector's context stack. A desynced stack
silently corrupted every declaration after it in the same file: methods were recorded as global
functions, and references inside skipped spans were lost — so live code was reported as a
**definite orphan**. Because orphans ride along in the default scan as an advisory, this affected
every run, not only `--orphans`.

- **Closure capture clauses** — `function () use ($x) { ... }` was treated as an import statement and
  skipped to the next `;`, which lands *inside* the closure body. Every reference in that span was
  lost, and because the skip bypassed the closure's own `{`, the stack stayed shallow for the rest
  of the file.
- **Anonymous classes** — `new class { ... }` did not open a *type* body, so its methods were
  recorded as global functions and a `use SomeTrait;` inside it was skipped instead of counted as a
  trait reference.
- **Curly-brace string interpolation** — `"{$var}"` popped a block level that was never pushed:
  `token_get_all()` emits an array `T_CURLY_OPEN` token for the opening brace but a plain `}` string
  token to close it.

A fourth, separate cause was found while re-measuring the survivors:

- **Aliased imports** — `use A\B\Original as Alias;` means the class is only ever written as
  `Alias`, so `Original` was never counted and a class used solely under an alias was reported as a
  definite orphan. Alias pairs are now resolved for single, comma-separated, grouped
  (`use A\{B as C};`) and `use function ... as ...` forms. An import whose alias is never used still
  counts nothing, so an unused import cannot mask a dead class.

Measured against third-party sources: Laravel `Illuminate/Database` went from 2150 symbols scanned
and 538 definite orphans to 249 and 13; `Illuminate/Support` from 494 and 144 to 147 and 21. The
findings that disappeared were phantoms — `__clone` and other methods reported as dead *global
functions* — plus the four grammar classes Laravel imports under an alias.

### Changed — an unreferenced trait is a *possible* orphan, not a definite one

A trait exists to be consumed by *other* classes, so a library ships traits for consumers that are
never part of the scan — Laravel's `HasFactory` and `HasBuilder` are the archetype. Traits now join
interfaces and abstract classes in the contract tier: still reported, but no longer failing the
build. Nothing is hidden — the total finding count is unchanged, only the confidence tier moves.
Across `laravel/framework` this shifts 13 findings, from 48 definite / 32 possible to 35 / 45.

Symbols that a source-only scan genuinely cannot resolve — a service provider discovered through
`composer.json`, a cast class named only in a downstream model — remain out of scope; `@api` /
`@phpcpd-keep` are the escape hatch for those.

Added `SymbolCollectorContextTest`, which pins each construct plus three regression guards (a
brace-delimited namespace import must stay un-referenced; `::class` must not be read as a
declaration; an unused aliased import must credit nothing), and a dogfooding invariant: phpcpd's own
`src/` declares no global functions.

---

## [1.2.0] - 2026-07-19

### Added — orphan detection (dead code)

- **Orphaned symbols** — top-level classes, interfaces, traits, enums, and global functions that
  nothing in the scanned set references. Same token engine, same zero-dependency, no-parser design as
  the clone side.
- **Two run modes**: orphans **ride along in the default scan as an advisory** (reported, but only
  clones set the exit code — safe for framework-heavy code where dynamic dispatch causes false
  positives); **`--orphans`** runs orphans-only and *gates* CI (a definite orphan → non-zero exit,
  exactly like a clone).
- **Explains *why* something is dead**, not just *that* it is:
  - **Whole-file "unwired"** — flags when every symbol declared in a file is itself an orphan (a
    stronger delete signal than one dead class among live ones). This is phpunused's "unreferenced
    file", done at symbol granularity.
  - **"Superseded copy of ..."** — reuses the clone engine: an orphan whose body duplicates a *live*
    symbol is annotated as the stale copy some refactor replaced but left behind. This is the
    orphan × clone synergy unique to phpcpd-next.
- **Two confidence tiers** (harvested from Psalm's `UnusedClass` / `PossiblyUnusedClass` split): a
  **definite** orphan is referenced nowhere and drives the exit code; a **possible** orphan is either
  a contract (interface / abstract class, which an out-of-tree package may implement) or a name that
  only appears in a string literal (a candidate for `new $class` / DI-container lookup) — reported for
  review, but does not fail the build.
- **Entry-point awareness** (harvested from shipmonk/dead-code-detector's usage providers): classes
  wired via framework attributes (`#[Route]`, `#[AsCommand]`, `#[AsEventListener]`, `#[Entity]`,
  `#[Attribute]`, …) and `*Test` classes are recognised as reachable and never flagged.
- **Suppression annotations**: `@api`, `@psalm-api`, `@phpstan-api`, `@phpcpd-keep`, and
  `@phpcpd-ignore-orphan` in a symbol's docblock mark it intentionally public / kept.
- **Better than a grep-based finder** (the phpunused niche, done right): because detection is
  token-based, a name mentioned in a comment or the declaration itself no longer masks a real orphan,
  and a name in a string is scored as a *weak* dynamic signal rather than a hard reference. Reference
  detection is deliberately generous — over-counting hides a real orphan (safe), under-counting would
  tell someone to delete live code (never).
- **Headless API** `LucianoPereira\PhpcpdNext\Orphans::detect()`, mirroring `Phpcpd::detect()`, plus a
  new `LucianoPereira\PhpcpdNext\Orphan\` subsystem (`SymbolCollector`, `OrphanDetector`, `Symbol`,
  `Orphan`, `OrphanResult`, `OrphanTextReport`). Covered by `tests/OrphanDetectorTest.php` (10 tests).
  Dogfooding the tool against `src/` immediately surfaced a genuinely dead exception class
  (`MissingResultException`).

## [1.1.0] - 2026-06-28

### Added — integrations

- **Headless mode** (`LucianoPereira\PhpcpdNext\Phpcpd::detect()`): a one-call, in-process API that
  finds files, runs the same engine the CLI uses, and returns the raw `CodeCloneMap` — no banner, no
  argv parsing, no file I/O. The CLI and all embedders now share a single detection core (`Engine`),
  so they can never disagree about what a clone is.
- **Framework presets** (`--preset=<name>`, and `preset:` in the headless API): a named bundle of
  paths, suffixes, and excludes — pure configuration, no runtime dependency. Ships with a **`laravel`**
  preset (scans `app routes database config`; skips `vendor`, `storage`, `bootstrap/cache`, `public`,
  Blade views, and migration boilerplate). Explicit flags seed-then-override the preset. New presets
  are a single `Preset` entry in `src/Presets.php`.
- **PHPUnit integration** (`integration/phpunit/`): an `AssertNoDuplication` trait and a
  `DuplicationConstraint` that turn copy/paste detection into a regression test, with offending
  locations (and `[inconsistent]` flags) printed on failure. Shipped in the **production**
  autoloader under `LucianoPereira\PhpcpdNext\PHPUnit\`, so it works for any project that requires
  phpcpd-next (even as `--dev`). phpcpd-next dogfoods it — `SelfDryTest` now keeps `src/` clean
  through this exact trait.
- **Laravel via Artisan**: documented (no extra package) by wiring the headless API into a command.

### Packaging & distribution

- **Published to Packagist** as `phpcpd-next/phpcpd`: `composer require --dev phpcpd-next/phpcpd`.
- `composer.json`: added `type`, `keywords`, and a `suggest` for `phpunit/phpunit` (the optional
  PHPUnit integration); moved the `PHPUnit\` namespace into the production autoloader.
- Added `.gitattributes` with `export-ignore` rules so the dist tarball ships only runtime code
  (`src/`, `integration/`, the binary), not tests, benchmarks, or tool configs.

### Tooling

- Committed a `.php-cs-fixer.dist.php` codifying the existing code style, so `composer lint` /
  `composer check` run non-interactively.

### Documentation

- Reworked the README to document the **full** feature surface accurately: the real default
  (Rabin-Karp + TokenBag) and `--rk`, all four output formats, the complete option reference split
  into stable vs. advanced/research flags, presets, headless mode, and the PHPUnit integration.

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

[1.4.0]: https://github.com/phpcpd-next/phpcpd/releases/tag/v1.4
[1.3.0]: https://github.com/phpcpd-next/phpcpd/releases/tag/v1.3
[1.2.0]: https://github.com/phpcpd-next/phpcpd/releases/tag/v1.2
[1.1.0]: https://github.com/phpcpd-next/phpcpd/releases/tag/v1.1
[1.0.0]: https://github.com/phpcpd-next/phpcpd/releases/tag/v1.0
[0.1.0]: https://github.com/phpcpd-next/phpcpd/releases/tag/v0.1
