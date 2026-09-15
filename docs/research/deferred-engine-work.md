# Deferred engine work

Three tasks left out of <!-- [[ $code.release ]] -->2.0.0<!--/-->
deliberately. Each is characterised, each has a reproducible benchmark, and
each changes detection semantics — which is why none landed in a release
whose recall claim is already settled.

§1 and §3 are about cost. §2 is about **accuracy** — a false positive the
tool still emits — and is the one to read first if the goal is a better
engine rather than a faster one. It also carries a worked example of getting
the analysis wrong, which is cheaper to read than to repeat.

Measurements below were taken on a ThinkPad T480 (i7-8650U) on AC power with
`energy_performance_preference=performance`. On battery under `power` the
same machine reports a control whose true ratio is exactly 1.00 as anywhere
from 0.91 to 1.15, so nothing timed there is worth recording. State the
machine and its power state beside any wall-clock number, or do not publish
the number.

That rule is now enforced rather than remembered: `bench/power.php` reads
the power state before any timing, and `bench/check-walltime.php` declines
to measure — asserting nothing, exiting 0 — on a machine that cannot
deliver. The counts in this document are rendered from
`bench/results/facts.toml` by `bench/sigil.php`, so `php bench/sigil.php
--check` fails if any of them drifts from what the code actually does.

## 1. Candidate generation on self-similar input

`bench/check-walltime.php` asks the unified engine to run at or below the
default pipeline. Across six corpora, measured in one sitting on a machine
that passed `bench/power.php`'s check — the table is inserted from
`bench/results/walltime.tsv`, which that runner writes itself:

<!-- [[ @paths.walltime_table ]] -->
    corpus              files   default   unified    ratio
    symfony-string         33    0.212s    2.221s   10.46x
    php-parser            340    0.390s    3.149s    8.08x
    symfony-console       359    0.951s    8.858s    9.32x
    firefly-iii          1291    4.606s   53.319s   11.58x
    wordpress            1848   29.041s   99.889s    3.44x
    phpunit              2689    3.719s  118.650s   31.90x
<!--/-->

The ratio is not a function of size: WordPress is the closest of the six at
<!-- [[ $scan.wordpress_files ]] -->1848<!--/--> files and PHPUnit is the
furthest with 45% more. What the cost tracks is self-similar content.

**WordPress was the undetermined case, and it is one no longer.** It had been
measured at 0.97x and then, on a cooler machine, at 1.13x — two measurements
disagreeing about which pipeline was faster, which is two measurements that had
not answered the question. Making the whole token stream visible answered it.
The ratio there is now
<!-- [[ $walltime.wordpress_ratio ]] -->3.44<!--/-->x, outside any reading of a
run-to-run spread, and the gate now fails on all six corpora rather than five
with one undetermined.

The cause is not the unified engine becoming worse at what it does. Recording
the single-character tokens roughly doubled the signature, and the unified
engine pays that in full, while the default pipeline pays less of it because a
higher token threshold prunes more candidates before any alignment runs — on
this corpus the default pipeline came out *faster* across the same change. Two
effects pulling opposite ways, and the gap widened on every corpus rather than
only this one.

The spread columns stay and still earn their place: `unifiedspread` here is
<!-- [[ $walltime.wordpress_unifiedspread ]] -->3.894<!--/-->s against a
median of <!-- [[ $walltime.wordpress_unified ]] -->99.889<!--/-->s, the
loosest row in the table. But no reading of that noise returns a
<!-- [[ $walltime.wordpress_ratio ]] -->3.44<!--/-->x gap to parity, so what
this corpus needed was never a quieter machine — it was a token stream that
said what the code does.

What survives is a weaker claim than the one this section used to make: on the
easiest corpus the unified engine is within a small multiple of the pipeline it
would replace, and on the hardest it is more than an order of magnitude away.
No corpus is within noise of parity any more. The bar is not absurd — it is
simply not met anywhere, and now that is established rather than undetermined.

On php-parser it was two files — 18% of the corpus's lines, 82% of its
runtime, 2,900-line near copies of each other — and they are gone from the
measurement now, for a reason that had nothing to do with the engine
(below). PHPUnit's cost concentrates the same way and does not go anywhere:
11.83s of its 38.601s inside `tests/unit/Metadata`, some ninety
near-identical test methods.

### php-parser's two files were generated, and <!-- [[ $code.release ]] -->2.0.0<!--/--> did not notice

`Php7.php` and `Php8.php` carry a banner — at line 13, below the import
block:

    /* This is an automatically GENERATED file, which should not be manually edited.
     * Instead edit one of the following:
     *  * the grammar file grammar/php.y

`FileFinder::isGenerated()` reads and tokenizes the first 2 KiB, so it
reached that comment and let both files through anyway. Two independent
misses:

  - `GENERATED_MARKERS` held `@generated`, `do not edit`, `auto-generated`.
    The banner says "automatically GENERATED" and "should not be manually
    edited"; none of the three substrings occurs in it.
  - Adding `automatically generated` to the list does not fix it. The marker
    sits four words in, behind "This is an", so `GENERATED_ANCHOR` rejects
    it — and an anchor widened to admit a lead-in makes the phrase fire on
    prose, because unlike the three markers it is ordinary English. That
    variant drops WordPress's `post-excerpt.php` on `* automatically
    generated and user-created excerpts.`, which is a live source file
    leaving the scan.

**Fixed**, by matching the phrase as a banner *form* rather than as a
marker: it counts only where it announces the file, introduced by "this is
a" / "this file is" / "this file was", or naming the artifact it produced.
Across the six corpora — <!-- [[ $scan.corpus_files_total ]] -->6714<!--/-->
files — that adds exactly these two and nothing else. A default scan of
`bench/corpus/php-parser` now finds <!-- [[ $scan.php_parser_files ]] -->340<!--/--> files, skipping
<!-- [[ $scan.php_parser_skipped ]] -->2<!--/--> as generated; WordPress still
finds <!-- [[ $scan.wordpress_files ]] -->1848<!--/--> and keeps
`post-excerpt.php`.

**What that did to this benchmark.** Default excludes are on by default, in
the CLI and in `bcb_gate_files()`, so those two files no longer reach the
engine at all — and they were 82% of php-parser's unified runtime.
Re-measured after the fix, php-parser's ratio fell from 12.74x to
<!-- [[ $walltime.php_parser_ratio ]] -->8.08<!--/-->x on
<!-- [[ $walltime.php_parser_files ]] -->340<!--/--> files. The table above is
that re-measurement; the pre-fix figures survive only in `git log`.

That does not retire task (1) — PHPUnit still fails on hand-written test
code that no exclusion will ever remove. It does move the workload: see
*Benchmarks to use* below, where php-parser is retired and PHPUnit becomes
the primary case.

### What was tried and does not work

**Masking seeds inside literal tables.** The obvious reading — that the cost
is php-parser's generated `array(...)` tables — is wrong. `RegionStructure`
does recognise them (26.4% of `Php7.php`'s windows sit wholly inside one),
and dropping their seeds before indexing was measured, interleaved, four
runs per arm: median 5.169s to 4.875s, **1.06x**, at a cost of five
findings. Within noise. A single-run comparison of the same prototype read
1.46x, which is what made it look promising; it was noise, and interleaving
is not optional on this machine. The literal tables are 26.4% of those
files' tokens and incidental. What is expensive is the near-duplication
between the two whole files.

### The lever that looks right

Seed multiplicity separates pathological files from ordinary code: on
php-parser the worst seed recurred an order of magnitude more often inside
`Php7`/`Php8` than anywhere else in that corpus, and it was the only
measured signal that told the two apart before any anchoring was paid for.

**That evidence is not usable as it stands.** It was measured on php-parser,
which is retired as a workload — those files are no longer scanned at all —
so the lever is currently argued from a corpus nobody designs against.
Nothing in the tree emits seed multiplicity as a fact, so the figures cannot
be rendered here either. **Before building anything on this, measure it on
`bench/corpus/phpunit/tests/unit/Metadata`**, which is the primary case, and
have the instrumentation write a fact rather than a log line.

`FingerprintIndex::PER_FILE_CAP` is <!-- [[ $code.per_file_cap ]] -->32<!--/-->, so one seed contributes up to <!-- [[ $code.per_file_cap ]] -->32<!--/--> positions per file — <!-- [[ $code.per_file_cap ]] -->32<!--/--> x <!-- [[ $code.per_file_cap ]] -->32<!--/--> = 1,024 anchor
pairs from a single seed, and those two files share about 1,079 seeds.
Anchor pairs scale as the square of the cap, so making the cap adaptive —
<!-- [[ $code.per_file_cap ]] -->32<!--/--> for ordinary files, 4 to 8 for
files whose seed multiplicity is far above the corpus norm — is a quadratic
reduction applied only where it is needed. Computing the multiplicity costs
a fraction of what the analysis costs — also measured on php-parser, and
also needing to be retaken on the primary case.

**The gate this needs is recall, not subsumption**, and `FingerprintIndex`
says so itself: *"Subsumption against Rabin-Karp cannot arbitrate any of
this: that gate only asks whether the baseline's exact contiguous clones are
covered, and is blind to the Type-3 and same-file classes a cap on
repetition is most likely to break."* Measure with `bench/run-recall.php`
against the 710-pair winnowing guarantee. If the cap costs recall there it
is not shippable, however fast.

Do not move `check-walltime.php`'s bar to accommodate this. It is met on
WordPress, and the failure is an input class handled at the wrong end of the
pipeline: `withoutSelfRepeatingTables()` filters candidates *after* every
anchor, chain and classification has been paid for, so it cleans the report
and saves no time.

## 2. The confirmed false positive, and why the obvious discriminator still fails

The precision audit found exactly one confirmed false-positive shape:
**normalized data-table matching**. Under identifier normalization every
string literal folds to one token, so an array of string pairs matches an
array of string pairs and no duplicated logic exists. It is still live — a
default unified scan of `bench/corpus/symfony-string` reports
`AbstractUnicodeTestCase.php:326` against `SpanishInflectorTest.php:20`, 85
lines, and both sides are data-provider literals.

Three discriminators were measured against it and all three refuted. The
third, **logic share** (the fraction of a span's tokens that are not
literals), separated perfectly on symfony-string and died on php-parser,
where the flagship true positive `Php7.php` against `Php8.php` scored
*below* ordinary code: any threshold removing the data tables removed it
too.

**That counterexample is no longer in a default scan.** Those two files are
generated, carry a banner, and the file finder now reads it. Re-measured
over all six corpora with `bench/measure-logic-share.php`:

<!-- [[ @paths.logic_share_table ]] -->
    corpus               <0.15   0.15-0.40   >=0.40    total
    firefly-iii              0           0     1667     1667
    php-parser               0           0      170      170
    phpunit                  0           0     1643     1643
    symfony-console          0           0      473      473
    symfony-string           0           0      104      104
    wordpress                0           0     2657     2657
<!--/-->

The shape of that table is the argument, and it is a shorter argument than it
used to be: **every finding on every corpus scores at or above 0.40.** There is
no low population to cut at, on any corpus, at any threshold.

That is a different result from the one this section recorded before, and the
difference is a defect in the instrument rather than a change in the engine.
`bcb_logic_share()` computed the statistic over the tokens `token_get_all()`
returns as arrays, which drops every single-character one — `; { } ( ) , = + -
* /` and every operator, about half the program text and very nearly all of it
non-literal. "The fraction of a span's tokens that are not literals", measured
while discarding most of what makes a span not literal, put data tables far
lower than they belong. It also took a line range, so a clone beginning partway
through a line was scored over every token on it.

Both are fixed: the statistic is now asked of `Facts\FileStatements`, over the
occurrence's own token range, and the numbers above are what it says.

On a synthetic pair — one table of string rows, one block of arithmetic — the
corrected statistic reads 0.636 and 0.926, against 0.385 and 0.833 as it was
measured before. The separation shrinks from 0.449 to 0.290, and the table's
floor rises above any cut that would leave ordinary code alone. A data table is
mostly brackets, commas, `=>` and semicolons, and those are not literals.

**So logic share alone is refuted, and more plainly than before.** It was
refuted for failing to separate the flagship true positive from the data
tables; it is refuted now for not separating anything. The statistic measures
how literal a span is, and cannot see whether two literal spans are the *same*
table — which is the only thing dividing a clone from a coincidence of shape.

**What answered it was the baseline — and that answer went with the band.**

What follows, to the paragraph before the lessons at the end of this section,
was written against the superseded measurement and is kept because the reasoning
is worth reading, not because the numbers are. It ran Rabin-Karp subsumption
over the low band, found twelve findings there, and proposed a conjunction
rather than a threshold:

> refuse a finding when its logic share is below the band AND the baseline does
> not report it.

**That rule cannot fire.** Its first conjunct asks for a population the
corrected statistic does not produce: nothing on any of the six corpora scores
below 0.40, so there is no band, and a conjunction whose first term is never
true removes nothing. The twelve findings it was measured on were twelve
findings of a statistic that was dropping half the tokens.

What survives is the shape of the argument rather than its threshold. The
property separating a duplicated table from a coincidence of shape is not a
property of one span, so no span statistic finds it — that much the M2 report
said, and correcting the statistic made it more true, not less. Adjudicating
with the baseline is still the idea worth keeping; it just has nothing to
adjudicate *within*, because the statistic no longer nominates candidates.

A replacement has to nominate them some other way. That is open.

**Still not shippable, and the remaining gap is precision, not mechanism.**
None of the twelve has been in front of a rater. The rule agrees with the
rubric on all twelve by construction, which is exactly why an independent
rating is the evidence that would count — a rule validated against the
reasoning that produced it has been checked for consistency, not for truth.
That is M3's two-rater apparatus, and it is the one thing here that cannot
be run without people.

**How this was nearly got wrong, recorded because the next reader will be
tempted the same way.** The re-measurement was first written up as a lead:
the band is clean, the population below it is all literal tables, ship a
threshold after rating. Every one of those statements is true. The
conclusion did not follow, because "literal table" and "false positive" are
different predicates and the band contains both. It took reading the actual
spans — not the file names, not the statistic — to see that two of them are
sodium_compat carrying one table in two implementations.

Three cheap checks would have caught it earlier, and are worth doing first
next time:

  - open the source of the outliers rather than inferring from the path;
  - split any candidate band by same-file versus cross-file before drawing a
    conclusion, because ruling H already assigns those different verdicts;
  - run Rabin-Karp subsumption over the band. An exact match on both sides
    is the rubric's own evidence that a table was duplicated rather than
    merely resembled. Doing that turned the analysis around, and it cost one
    flag on a script that already existed.

The numbers here are not comparable to M2's — that definition excluded
brackets as well as literals, which compresses every score toward zero. The
statistic only means anything against others computed the same way, which is
why the script states its definition beside the value.

## 2b. The accessor family, tried and refuted

Rating a precision sample by hand named four residual false-positive families.
Three are answered: registration DSL by `FileRole`, seeder tables by the table
stratum's second spelling, and migrations by the Laravel preset, which excluded
them all along. The fourth was *accessor runs* — Eloquent relation methods,
`public function notes(): MorphMany { return $this->morphMany(Note::class,
'noteable'); }` and four more like it, matching a different model's relations on
shape alone.

**The candidate rule.** A method whose body is exactly one `return` is a
declaration rather than logic, and a span made only of those is a declaration
list. It has the shape ruling 5 asks for: a membership test, no constant, and it
does not touch `routeBinder()` — the static route-model-binding method copied
across every model, which has branches, assignments and a throw, and which is
*genuine* duplication a reader wants asserted.

**It separates nothing.** Measured over firefly-iii's 50 Eloquent models, where
the unified engine reports 31 findings: **zero** of the 31 are spans made only of
one-return methods. The reason is not the rule, it is the spans. They are large —
`Account.php:60-236` is 176 lines — and they stitch declarations together with
whatever sits between them. Finding 001's own span is a hundred lines of relation
methods with `setVirtualBalanceAttribute` in the middle of it, which has an `if`
and an assignment and is not a declaration by any reading.

A "mostly declarations" rule would reach it, and that is a threshold on a
statistic of a span — the shape M2 refuted three times and M5 refuted a fourth.
So this is recorded as tried rather than left as an idea somebody has again:
the family is real, the rule is not, and what stands in the way is the extent of
the reported span rather than the test applied to it.

Which points at §3 below rather than at a new discriminator.

## 2c. Two more, tried and refuted — 2026-09-14

Both were measured against the first rated audit this repository has
(`bench/audit-precision.php`, php-parser, 60 findings, one rater), which is also
why both refutations carry the same caveat at the end.

**Shares no literal at all.** Under normalization every string folds to one
token, so a table of string pairs matches a table of string pairs — and if the
two tables are unrelated, *every* literal in them differs. "The two copies share
no literal value" is a membership test, not a fraction, so ruling 5 would have
allowed it, which is more than the three above can say.

It separates perfectly at both ends on symfony-string, read rather than assumed:
`SpanishInflectorTest.php:25` against `:72` scores 1.00 and is two
data-provider tables, singularize against pluralize; `AbstractString.php:553`
against `:587` scores 0.00 and is `trimPrefix()` against `trimSuffix()`, the
same `is_array || Traversable` walk written twice.

**Refuted on php-parser**, where it fires on four findings rated Y:
`Standard.php:258/292`, `:451/479`, `:487/510` and `DifferTest.php:27/48`. The
pretty-printer delegators differ in every operator string — `' += '` against
`' -= '` — and the two Differ tests differ in every expected string. Any cut
that removes the inflector tables removes those. The same failure as logic
share, on a different statistic.

**Contains no branch or loop.** The false positives read from their matched
tokens are declaration surfaces — class header, property declarations, a
constructor that only assigns parameters to properties — so "no branching or
looping anywhere in the match" looks like the shape test that names them.

**It anti-discriminates.** Rated Y: 21 of 28 are branchless. Rated N: 5 of 9.
The true positives in this corpus are delegator runs, assertion blocks and
provider tables, which are exactly as branchless as the false positives.

**The caveat both share.** Those four counterexamples are the borderline
`Standard.php` delegator findings, rated Y on the grounds that all of them call
the same helper, so a change to `pPrefixOp`'s contract changes every one. A
second rater calling them N would let the first rule survive. That is one more
thing gated on the second rater rather than on a new idea.

**What did separate.** Not shape — scope. Findings whose sites are all in one
file rate 21Y/3N = 0.88; findings spanning two or more files rate 20Y/16N =
0.56. The confidence model already carries a `scope` term, so the lever for
false positives is refitting that model on a current worksheet, not a sixth
discriminator. Five have now been tried.

## 3. Reporting a self-similar region as one finding

Those two files produce 64 clones a reader can do nothing with. The
periodicity decomposition already in `fromCluster()` (M2 ruling J) has the
right shape for collapsing a run of mutually-similar blocks into one class
naming every block; it is not reached for cross-file near-duplication. Worth
doing with (1), since both concern the same input class and would be
measured together.

## Benchmarks to use

- `bench/corpus/phpunit/tests/unit/Metadata` — 11.83s, 87 clones. The
  primary case: hand-written test code with no banner, on
  <!-- [[ $scan.phpunit_files ]] -->2689<!--/--> files against the
  <!-- [[ $scan.wordpress_files ]] -->1848<!--/--> of the corpus that passes.
  Nothing file discovery can do will excuse it.
- `tests/fixtures/probes/tandem_repeat.php` — the same shape as the above,
  one method written ten times, small enough to iterate on.
- `bench/corpus/php-parser` — **retired.** Its cost was `Php7.php` +
  `Php8.php`, which a default scan no longer reads at all (see above). Do
  not quote it, and do not design against it: an unrelated fix to file
  discovery removed most of the workload, which is what disqualifies it as a
  target.
- `bench/corpus/wordpress` — the control: unified already beats the default
  pipeline here, and must still do so afterwards.
