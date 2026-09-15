# Release checklist: what <!-- [[ $code.release ]] -->2.0.0<!--/--> still needs

**Internal.** This is the maintainer's working checklist, not documentation for
anyone using phpcpd-next. Nothing here is a promise to a user, and a reader who
wants to know what the tool does should be in `README.md` or `docs/` instead.

Current version constant: <!-- [[ $code.version ]] -->2.0.0<!--/-->.

It is the standing answer to "what is left" — not a plan for future work, which
`docs/research/deferred-engine-work.md` holds, but the list of things that must
be true before the tag exists, and the things that are known to be untrue and
are shipping anyway.

## Blocking

This tree is a local working copy with no remote, deliberately. Committing,
tagging and publishing are done by hand by the owner; nothing below assumes an
automated release.

1. **Clean the working tree.** `bin/release.sh` refuses to run on a dirty tree,
   by design.
2. **`CHANGELOG.md` and `src/Version.php` must agree with the tag.** Both say
   <!-- [[ $code.version ]] -->2.0.0<!--/--> and the changelog heading is dated.
   `release.sh` rewrites the constant and prints the remaining steps; dating the
   heading is manual — `## v2.0 — <Month D, YYYY>`, with an em dash, and the
   version string exactly equal to the git tag, because the changelog renderer
   links a release by interpolating that string into the tag URL.
3. **Gates must pass on the final tree**: `composer check` (lint, PHPStan max on
   `src/` and `bench/`, PHPUnit, `sigil --check`), plus
   `php bench/build-tables.php --check` and a paper rebuild.

   `composer check` passes end to end as of this writing — the light
   php-cs-fixer config below is what made its first step runnable at all.

   The gates that must also be green, as commands rather than as the numbers
   they last printed. A standing list of counts in this file is stale the
   moment anything moves, and it was: it claimed a test total two behind the
   suite within an hour of being written. Run them; each one prints its own
   verdict and exits non-zero when it fails.

   ```
   php bench/run-recall.php                       # and --sample=40
   php bench/check-chaining.php
   php bench/check-superset.php bench/corpus/php-parser
   php bench/check-superset.php bench/corpus/phpunit
   php bench/check-determinism.php bench/corpus/php-parser
   php bench/check-incremental.php
   php bench/self-test.php
   php bench/check-provenance.php
   php bench/check-log-equivalence.php
   php bench/check-locales.php
   ```

   Two of these need an argument to mean anything, and say so rather than
   passing: `check-superset` and `check-determinism` over a corpus with no
   duplication in it compare empty reports and call it agreement.
4. **Re-run the measured tables if anything in `src/` changed after they were
   taken.** `bench/check-walltime.php` merges into `bench/results/walltime.tsv`,
   so a corpus can be re-measured on its own — a whole sweep is a quarter of an
   hour and a laptop will throttle inside it. A corpus whose engine and file list
   are unchanged since its row was taken is skipped outright.

   Each corpus's row is written as it is measured, so a sweep interrupted in its
   sixth corpus keeps the five it finished. It refuses to measure on a machine
   that cannot deliver, and re-reads the machine after every corpus: a row is
   kept only if conditions held across the corpus it describes. That is not
   hypothetical — one sweep began at 4,043 MHz on AC and ended at 800 MHz on
   battery, and the only sign in the numbers was the spread widening from
   milliseconds to eight seconds. Check `spread` before trusting a row.

   `bench/collect-facts.php` then ingests it and `bench/build-tables.php`
   renders it.

## The lint config is deliberately light

`.php-cs-fixer.dist.php` holds seventeen rules, all of them mechanical:
encoding and line endings, trailing and blank-line whitespace, short array
syntax, unused imports, and a few array and comparison normalisations. There
is no `@PSR-12`, no `@Symfony`, and no `@PhpCsFixer` — those would reformat
most of the tree in one commit and bury the next real finding in the noise.

The rules were chosen by measuring rather than by taste: each candidate was
run against the tree and kept only where the code already complied, so the
gate landed green and can only ever report a regression. `single_quote` was
dropped for exactly that reason — it wanted to rewrite sixteen files that
were not wrong, only inconsistent with an opinion nobody had stated.

It caught eight dead imports on its first run, two of them left behind by
the refactors in this release. Growing the set is a separate decision, and
the way to make it is the same: add a rule, measure what it wants to change,
and take it only if the change is one somebody would defend.

## The corpus on disk does not match its manifest

`bench/manifest.json` declares a per-corpus `strip` list and `bench/fetch.sh`
never read it — it took `repo` and `sha` and stopped. `bcb_files()` carries a
hardcoded default that happens to cover most of what the strip entries name, so
the omission was invisible until one entry fell outside it: firefly-iii's
`database/migrations`, sixty files, present in the corpus and in every number
taken over it.

`fetch.sh` now applies the list, and warns when a corpus already on disk still
holds something it strips. It does **not** delete anything already fetched: that
would move every published firefly-iii number without saying so. Re-fetching
firefly-iii is a deliberate act and it invalidates the measured tables, so it
belongs before a tag rather than inside one.

Nothing a user ever saw was affected. An ordinary run auto-detects the Laravel
preset, which excludes `database/migrations` by name — "up()/down() boilerplate
is duplicate by design" — so the exposure was to the *benchmark*, which was
measuring a corpus the product would not scan.

`bcb_gate_files()` now asks for the preset the way the product does, and the
manifest no longer restates what the tool already owns: every entry in
firefly-iii's `strip` list was already in the Laravel preset, and every other
corpus's was already in `FileFinder`'s defaults. What is left is
`wp-content/uploads`, which nothing else covers.

**This makes the measured tables stale for firefly-iii**, and only for
firefly-iii: it is the one corpus that detects a preset, and its gate file count
moves. Re-run the tables before the tag — which is item 5 either way, since
`src/` has moved since they were taken.

## Shipping with known defects, deliberately

Both are recorded in `CHANGELOG.md` as untagged entries — the only ones in the
file, since a known defect is not a change — and in full in `docs/release-notes.md`.

- **`bench/check-walltime.php` fails every corpus, with no undetermined case
  left.** It used to fail five of six, with WordPress reading 0.97x on one
  machine and 1.13x on a cooler one — a gap smaller than that corpus's own
  run-to-run spread, so the verdict was undetermined rather than either way.
  Making the whole token stream visible settled it: the signature roughly
  doubled, the unified engine pays that in full, and every corpus now fails
  clearly. The table in `docs/research/deferred-engine-work.md` is rendered
  from `bench/results/walltime.tsv`, so the current ratios are read there
  rather than repeated here.

  The fix is a candidate-generation guard for self-similar input, deferred
  because it changes detection semantics and needs a recall study rather than a
  subsumption gate to clear it. WordPress is no longer a separate question
  about the machine — it is the same question as the other five.
- **A clone's reported extent can overstate by one line at a boundary.** Two
  disjoint token ranges can share one physical line, so a site's last line may
  equal the next site's first. Display only: the coverage union counts such a
  line once, so no total is affected. Quantified now: 42 site pairs on phpunit,
  4 on firefly-iii, 0 on symfony-console — disjoint in tokens and touching on
  one line. Untouched by the Rabin-Karp length fix in this release, which was a
  different mechanism — occurrences borrowing one length — and is fixed rather
  than listed here.

## After the tag

- **Tell Crucible.** It has this project's embedder document at
  `crucible/PHPCPD.md` and a seven-item checklist it cannot verify against
  anything but a released tag; its ⓘ marks stay until it can. The capability
  contract it asked for — `Phpcpd::supports()`, taking a string, recognising
  only what is true in this release — shipped, so the tag is the last thing it
  is waiting on.

## Not blocking, and honest about it

The documentation-facts mechanism covers what it covers, and no more.

- **The fact table holds constants and corpus counts.** Harness *outputs* are
  still prose: the 441-pair winnowing guarantee from `bench/run-recall.php`, the
  κ and precision intervals from `bench/audit-precision.php`, and the per-corpus
  clone counts and duplicated-line percentages — which already sit in
  `bench/results/compare.tsv` and are simply not wired to `sigil`.
- **The paper reads one of its tables from data.** `tab:compare` and
  `fig:compare` are rendered from `bench/results/compare.tsv`; `tab:density`,
  `fig:scatter` and the remaining `addplot` coordinate lists are still typed.
- **The lever for the deferred work is argued from a retired corpus.** Seed
  multiplicity was measured on php-parser's `Php7`/`Php8`, which a default scan
  no longer reads. Nothing emits it as a fact, so it cannot be rendered either.
  It needs re-measuring on `bench/corpus/phpunit/tests/unit/Metadata` before
  anything is built on it — see `docs/research/deferred-engine-work.md`.
- **`sigil --audit` singletons are untriaged.** The gate fails only on a number
  that is exactly a current fact value. Everything else is advisory, and nobody
  has been through the list to decide which entries are facts nobody collected
  yet and which are prose that should stay prose.
- **`CHANGELOG.md` is hand-rolled by definition** and is not under `sigil`. A
  changelog entry is a claim about a release; binding it to live facts would
  rewrite history every time a measurement moved. Same for `docs/release-notes.md`
  and the dated milestone records in `docs/research/unified-engine-plan.md`.
