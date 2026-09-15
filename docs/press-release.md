# phpcpd-next 2.0

**Not a new release of an old tool. A new tool under an old name: every file
rewritten, nothing inherited, relicensed MIT — with a third detection engine,
dead-code detection, and output in twenty-eight languages.**

September 13, 2026 — phpcpd-next 2.0 is available on Packagist.

```bash
composer require --dev phpcpd-next/phpcpd
vendor/bin/phpcpd app/
```

PHP's best-known copy/paste detector was archived in 2023. phpcpd-next continues
it: the same idea, the same command, none of the same code.

## Why the major version

Four things changed at once, and each alone would have been a release.

**The code is new.** Not refactored — replaced. Every file was rewritten from
its specification, and each was proved to emit byte-identical output before its
attribution changed. Nothing of the original implementation survives, and a
release gate says so rather than a README: `php bench/check-provenance.php`
reads the header of every file in `src/`, counts the copyright holders it names,
and exits non-zero while one remains inherited. It reports zero.

**The licence is MIT.** That follows from the line above, and could not have
come before it. The project began as a fork under BSD-3-Clause; attribution
alone satisfied that licence, and the package was always correct as it stood.
The relicence repairs nothing. It completes a plan the project set itself: a
tree with no inherited surface left, relicensable in one commit, with the
inventory as its evidence. No BSD text ships, because no code under that licence
does. `NOTICE` credits the origin as ancestry.

**There is a third engine.** Rabin–Karp finds exact copies and a SourcererCC
token bag finds reordered ones; both run by default. `--algorithm=unified` adds
a winnowing engine that finds all four clone types from a single anchor set and
names the divergent token ranges on both sides of a near miss, rather than
flagging it and stopping. The suffix-tree lane it replaces is gone.

**It speaks twenty-eight languages.** Every sentence the tool prints — reports,
the help screen, and every refusal — comes from `locale/`, chosen with
`--language=fr` or a line in `phpcpd.ini`. A translation is one file, registered
by existing, and may be partial: an untranslated key falls back to English, one
key at a time. `php bench/check-locales.php` reports each translation's coverage
and fails on a key English does not have or a placeholder that drifted.

On top of those: dead-code detection as a second mode, confidence ranking, an
acknowledgment ledger, SARIF output, and corpus triage. It is a major version
because a 1.x number would have been a lie about how much moved.

## What a run looks like

On a Laravel application, with no configuration and no flags:

```
$ vendor/bin/phpcpd .
Laravel detected — preset applied (--no-preset to disable)

Triage: files (7), removed (1) · unwired 1
  (--explain lists each file and the evidence for or against it)

Scan root: /var/www/shop
Scanned files (6), roots (1), excludes (19)

Found clones (1), duplicated lines (30), files (2):

  - app/Http/Controllers/InvoiceController.php:6-35 (30 lines) +2.56
    app/Http/Controllers/OrderController.php:6-35
    → Consider extracting the shared lines into a reusable method, class, or trait.

1 asserted, 0 demoted.
33.33% of scanned lines (90) are duplicated code.
Clone lines: average (30), largest (30).

orphaned symbols (1) — advisory, does not affect exit code:

  - Class App\Services\LegacyDiscount
    ./app/Services/LegacyDiscount.php:3
    → never referenced
    ⤷ whole file is unwired — no symbol declared here is referenced

Time: 0.008s, Memory: 4.00 MB — files (6) at 706.9/s
```

Two controllers sharing a filter-and-total block, and a service nothing calls.
Neither needed a flag to find.

## For Laravel projects

The preset applies itself, announces that it did, and names the flag that turns
it off. Detection needs two independent signals: `laravel/framework` in
`require`, and a structural marker such as `artisan` or `bootstrap/app.php`.
`require-dev` is deliberately ignored, because a package that tests against
Laravel is not an application built on it.

The preset is not a list of folders. It encodes what a Laravel scan gets wrong
without it.

- **Blade templates are excluded.** `*.blade.php` is not analysable PHP source.
- **`database/migrations` is excluded.** The `up()` and `down()` boilerplate is
  duplicate by design, and reporting it buries everything else.
- **`_ide_helper.php` and the generated caches are excluded.** A generated file
  is a dense index of the very identifiers an orphan scan searches for, so
  scanning one can turn a failing gate green. This is the failure the default
  excludes exist to prevent.
- **`storage/`, `bootstrap/cache/`, and `public/` are excluded**, while `app`,
  `routes`, `database`, and `config` are scanned.

Run the same project with `--no-preset` and the migrations come back as
findings. That is the difference the preset makes, and it is why it is on by
default.

Two engine behaviours matter more on a framework codebase than anywhere else.
Route files and service providers are supposed to repeat themselves, so a
registration-role file repeating itself is demoted rather than asserted, while
two different route files agreeing is a copy somebody may want to remove and
stays asserted. Data tables are judged the same way. And `--orphans` knows that
framework entry points are invoked by convention rather than by reference, which
is what makes dead-code detection usable on a Laravel app.

## In CI

The exit code is the gate: zero when clean, one when something is asserted.

```yaml
- name: Duplication
  run: vendor/bin/phpcpd --log-sarif=phpcpd.sarif app/

- uses: github/codeql-action/upload-sarif@v3
  with:
    sarif_file: phpcpd.sarif
```

SARIF 2.1.0 puts every finding in the GitHub Code Scanning tab, on the line it
belongs to. PMD-CPD XML and JSON are also available, and `--cache` plus
`--incremental` re-tokenize only the files that changed.

## The rest of what 2.0 brings

**Inconsistent clones are called out.** A near miss where one copy was patched
and its sibling was not is the bug-prone kind, and it is marked
`[inconsistent]`.

**Dead-code detection.** `phpcpd --orphans app/` finds unreferenced classes,
interfaces, traits, enums, and functions using the same token engine: no parser,
no AST, no runtime dependency. Eleven structural suppression rules cover what a
scanner cannot see referenced — framework entry points, config registration,
template references, composer autoload files, and companion classes named by a
trait's suffix. A suppressed symbol is counted and listable (`--explain`), never
silently dropped.

**Findings are ranked, and the rank shows its work.** Each carries a confidence
score and the terms that produced it, so a reader who disagrees with a placement
can argue with the bucket rather than with the number.

**Acknowledged duplication is demoted, never hidden.** Commit a ledger of the
duplication you have decided to live with. It is still reported, still counted,
and still gates the exit code. The key is the hashed content of every side,
never a path and never a line number, so editing either copy expires the entry
and asserts the finding again, while moving the code changes nothing. Stale
entries are reported so they can be deleted.

## The research

The method and its evaluation are written up as a paper, which `bash
bin/build-paper.sh` builds, and the benchmark it rests on, BCB-PHP, lives in the
repository.

The project settles a claim by running something rather than by arguing. Gates
scan six corpora totalling 6,714 files, and each gate prints its own verdict:
recall against a guaranteed region, subsumption of the baseline's findings,
determinism — two runs, and the file list reversed, must be byte-identical — an
incremental index that must agree with a cold run, and wall-clock ratios that
are refused outright on a machine whose CPU is throttling. Thirty byte-for-byte
goldens pin the four reporters, six scenarios in five formats, so a reporter
that drifts fails a gate.

The working record is a lab notebook: dated, written when the work happened, and
never revised afterwards, which is what makes it evidence rather than
recollection. It keeps what failed. The roadmap's second item carries three
refuted discriminators for the one confirmed false-positive shape the precision
audit found, recorded so that a fourth attempt begins from what is already
known.

Numbers in the documentation are rendered rather than typed, by two mechanisms
for two formats. `bench/sigil.php` renders the Markdown from a measured fact
table and `--check` fails when a document disagrees with it; the paper reads
`docs/paper/facts.tex`, generated by the same collector, where an undefined
macro is a build error and there is no stored copy to go stale. Neither is
retrospective: a table the paper typed out by hand instead of reading is how one
of its comparisons came to describe an engine that had moved.

## What it does not do

Token-based by design, and permanently so. It uses no AST and no parser, neither
a dependency nor a from-scratch one, no machine learning, and no LLM. Each was
considered and declined for a stated reason, and `ROADMAP.md` keeps the reasons
so that nobody relitigates them. The package has zero runtime dependencies, runs
deterministically, and needs PHP 8.4 or later.

Known defects ship documented rather than quietly. The unified engine runs
slower than the default pipeline on self-similar input, by a measured and
published ratio, and the fix is deferred because it changes detection semantics
and needs a recall study to clear it.

## Links

- Source and issues: https://github.com/phpcpd-next/phpcpd
- Packagist: `phpcpd-next/phpcpd`
- Licence: MIT
