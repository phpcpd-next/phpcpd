# M4 audit packet — the executor's record

Written against `docs/research/audit/M4-HANDOFF.md` and plan §2 M4. M3 closed
`AUDIT: pass` at `23613ed`; M4 opened at `a0803a6`. The close verdict is not
mine to write.

Sections appear in the handoff's order of work. Each records the gates run with
their exact commands and verbatim outputs, the derivations behind anything that
changed, and the ruling requests raised at the stop points where they arose.

**Status: in progress.** Steps 1–3 are complete; both rulings raised at the
first stop point were granted and are applied here.

---

## 1. Instrument hygiene — the contamination was real, entered elsewhere, and cost less than expected

### 1.1 What was actually wrong

The handoff locates the defect in `bcb_files()`: "prunes only `vendor`,
`node_modules`, `storage`, `bootstrap/cache`". That reading is of the function's
default `$exclude` argument, and it is incomplete — `bcb_files()` calls
`FileFinder::find()` with the fourth argument left at its default, so the
product's own default excludes are **on**, and `.phpstan` is in that set:

```
$ php -r '... bcb_files("bench/corpus/phpunit") ...'
total: 2695
phpstan: 0
```

`bcb_files()` was already clean and needed no fix. The contamination entered
through four scripts that bypass it entirely:

```
bench/check-superset.php:88     find($dirs,  ['.php'], [], false)
bench/check-determinism.php:72  find($dirs,  ['.php'], [], false)
bench/check-walltime.php:93     find([$dir], ['.php'], [], false)
bench/check-incremental.php:77  find([$from],['.php'], [], false)
```

— default excludes **off**, and no excludes of their own. These are precisely
the four checks that record numbers. `check-determinism.php` also passed
`--no-default-excludes` to the binary in its CLI half, so that check's two
halves compared two different corpora.

The scripts were added at M0/M1 (`12c8a31`, `63d57ff`); `FileFinder`'s default
excludes predate them (baseline `fe6d585`). So the numbers have been measured
this way since M1, which matches the handoff's finding exactly — only the
mechanism is different from the one named.

### 1.2 The contamination inventory, measured

```
$ php -r '... find([$root],[".php"],[],false) vs find([$root],[".php"],[],true) ...'
php-parser       raw=  342  default-excludes=  342  delta=   0
phpunit          raw= 2870  default-excludes= 2695  delta= 175
symfony-string   raw=   33  default-excludes=   33  delta=   0
firefly-iii      raw= 1463  default-excludes= 1447  delta=  16
```

phpunit's 175, by category:

```
  117  tools/.phpstan (a dumped static-analysis tool tree)
   35  tests/end-to-end/**/vendor (vendor stubs inside test fixtures)
   12  other vendor
   11  other — build/scripts/*.php, build/config/php-scoper.php, build/test-extension/
```

The handoff's count of 115 for `tools/.phpstan` is a `find(1)` count of files
under that path; the walker's own count is 117, the difference being two files
the shell glob did not reach. The category is the same and the correction is
immaterial. php-parser and symfony-string are confirmed clean, as recorded.
firefly-iii's 16 are not `config/cache.php` (an ordinary config file, correctly
identified at M4 open as a false hit) but its own vendor and cache trees.

### 1.3 The fix — `8002086`

One walker for every check that records a number:

```php
function bcb_gate_files(array $dirs): array
{
    $files = (new FileFinder())->find($dirs, ['.php'], []);
    sort($files);

    return $files;
}
```

This is the handoff's stated principle — the tool already knows what is not
program text, so the benchmark asks it rather than maintaining a hand list —
implemented as a call rather than as a longer list. `bcb_files()` is untouched:
it was not the defect, and its corpus-shaped `storage` exclude is a separate
concern belonging to the E3 runner and the precision pool.

The justification the checks carried for turning the defaults off was checked
before being removed, and it is false:

> "…so the check can be pointed at any directory — including one under
> vendor/, which the tool's own defaults would prune and leave this check
> comparing two empty reports."

`find()` prunes descendants of the roots it is given, never the roots
themselves:

```
root=vendor/phpunit    raw=1039  defaults=1039
root=vendor/sebastian  raw= 106  defaults= 106
```

The escape hatch protected nothing and cost four gates their corpus definition.

### 1.4 Every standing gate re-run, dirty against clean

Suite and static analysis first, to establish the tree was green before
anything moved: `143/143`, `no errors` on both configs.

| gate | dirty (recorded) | clean (M4) |
|---|---|---|
| `check-superset.php phpunit` | FAIL — 22 items | **FAIL — 22 items, same composition** |
| `check-superset.php php-parser` | 3/3 | 3/3 |
| `check-determinism.php phpunit --algorithm=unified` | 4/4 | 4/4 |
| `check-determinism.php php-parser --algorithm=unified` | 4/4 | 4/4 |
| `check-incremental.php php-parser` | 8/8 | 8/8 |
| `check-chaining.php` | 2/2 | 2/2 |
| `self-test.php` | 24/24 | 24/24 |
| `check-walltime.php phpunit` | FAIL 4/4 | FAIL 4/4 |

What moved on phpunit: files 2,870 → 2,695; Rabin-Karp 160 → 156 clones;
unified 587 → 564 clones; baseline pairs compared 242 → 238.

**The residual did not move.** Verbatim, clean:

```
Subsumption check — 2695 files, --min-tokens=70 --min-lines=5

  rabin-karp    156 clones,    9221 duplicated lines, 0.370s
  unified       564 clones,  109250 duplicated lines, 2.652s

  PASS  the baseline found clones, so subsumption is being checked against something — 156 rabin-karp clones
  FAIL  every location the baseline reports is reported by the unified engine — 1 locations NOT reported
    MISSING  assertArraysHaveEqualValuesIgnoringOrderTest.php:269  (from baseline clone assertArraysHaveEqualValuesIgnoringOrderTest.php:269 + assertArraysHaveIdenticalValuesIgnoringOrderTest.php:344)
  FAIL  every pair the baseline reports is reported at a length the source agrees with — 238 pairs — 11 same length, 203 longer, 24 shorter (3 of those over-reported by the baseline), 21 unexplained
```

Same one uncovered location, same 21 unexplained pairs, of which 10 are
`MetadataTest.php` against itself and 2 are the pair ruling O was recorded for.
The four baseline pairs the fix removed were all exact-length covered pairs
from the dump. **21 remains the number ruling O must restore.**

Wall-clock, clean, quiet window:

```
  60 files     unified 0.018s  vs default 0.012s   0.66x   FAIL
  200 files    unified 0.055s  vs default 0.028s   0.51x   FAIL
  600 files    unified 0.206s  vs default 0.118s   0.57x   FAIL
  2500 files   unified 1.333s  vs default 0.541s   0.41x   FAIL
```

Against the recorded 0.79/0.57/0.58/0.42 and the close audit's 0.78/0.52/
0.59/0.42: unchanged inside noise, degradation with size unchanged. **The dump
was not the cause of the wall-clock failure.**

One thing worth stating because it looks alarming and is not: run with no
`--algorithm` argument, `check-determinism.php` fails 3 of 10 checks on both
corpora — the three baseline engines do not survive file-list reversal. This
was verified to predate the fix (checked by `git stash`) and is the check's
teeth working on engines that never sorted their output. The standing gate is
the `--algorithm=unified` invocation, which passes 4/4.

### 1.5 The runaway fingerprint — not the dump, and unchanged to the digit

M2 recorded phpunit's worst normalized-view fingerprint at 9,179 occurrences
across 13 files (706 per file), generating 99.4 % of the normalized view's
anchor pairs. Re-measured over both file lists:

```
dirty (2,870 files)  #1  9179 occurrences across 13 files (706 per file) — 0 under tools/.phpstan
      8950  tests/unit/Metadata/MetadataTest.php
       130  tests/unit/Framework/TestStatusTest.php
        31  tests/unit/TextUI/Configuration/MergerTest.php
        26  tests/unit/TextUI/Configuration/ConfigurationTest.php

clean (2,695 files)  #1  9179 occurrences across 13 files (706 per file) — 0 under tools/.phpstan
      (identical spread)
```

The answer to the handoff's question is **no**: none of the 13 files is under
the dump. The fingerprint is `MetadataTest.php`'s — 8,950 of 9,179 occurrences
in that one file, which is the same file at the centre of ruling J's fine-period
work and of the subsumption residual. The fact stands as recorded and needs no
re-derivation. The top five fingerprints are identical on both lists.

### 1.6 `SEED_PAIR_CAP` re-derived clean — the knee holds

Ruling N's method, on the clean corpus, current engine:

```
SEED_PAIR_CAP=2000       clones=552  dense=8661  all=74081  2.45s
SEED_PAIR_CAP=4000       clones=561  dense=8964  all=74456  2.65s
SEED_PAIR_CAP=8000       clones=563  dense=8951  all=74498  2.71s
SEED_PAIR_CAP=16000      clones=564  dense=9036  all=71962  2.75s
SEED_PAIR_CAP=32000      clones=564  dense=9036  all=71957  2.78s
SEED_PAIR_CAP=uncapped   clones=564  dense=9036  all=71957  2.91s
```

16,000 is the saturation point: coverage is flat from there to uncapped
(identical clone count and identical dense coverage; the cap still fires
somewhere, costing 5 lines of non-dense coverage against uncapped), and falls
below it. **The knee holds at the shipped value.** The constant is unchanged,
`IndexCodec::VERSION` stays at 5, and this confirms in its strongest form the
established fact that phpunit never meaningfully reaches this cap.

### 1.7 `PER_FILE_CAP` re-derived clean — the knee does *not* hold, and the dump is not why

Ruling D's method — the union of reported source lines restricted to clones of
at least 4 tokens per line — on the clean corpus, current engine:

| per-file cap | 8 | 16 | 32 | 64 | none |
|---|---:|---:|---:|---:|---:|
| **dense, clean** | **13,410** | 8,638 | 9,036 | 9,644 | 9,777 |
| dense, dirty | 13,450 | 8,678 | 9,076 | 9,684 | 9,817 |
| all lines, clean | 69,354 | 66,759 | 71,962 | 76,409 | 76,383 |
| clones, clean | 428 | 522 | 564 | 562 | 563 |
| wall-clock, clean | 1.55s | 1.90s | 2.67s | 7.13s | 5.08s |

M2's recorded row, for comparison: 1,116 / 1,097 / **2,741** / 2,254 / 2,296.

Two readings follow, and only the second is a finding.

**The contamination is not the cause.** The dirty and clean curves have the
same shape at every cap — the dump adds roughly 40 dense lines and 4,200 total
lines uniformly and moves no knee. Re-deriving on the clean corpus, which is
what the handoff asked for, changes nothing about this constant.

**The derivation no longer reproduces, because the engine changed.** The metric
that selected 32 — "the dense-coverage knee, 2,741 lines, above uncapped's
2,296" — now peaks at **C = 8** (13,410, half again the best of any other cap)
and then rises monotonically from 16 toward uncapped. On this metric, on this
engine, the argument recorded in the constant's own doc comment and in the
plan's §1 constants table selects 8, not 32. The cause is the M3 engine changes
(rulings J, M, N): ruling J's fine-period same-file emission and ruling M's
chaining changes altered which clones survive at each cap, and dense coverage
was always a proxy measured on engine output rather than a property of the cap.

**Ruling D's floor argument, which is what actually rejected C = 8, survives.**
It was a capability claim, not a number, so it was re-checked directly:

```
C=8   Assert.php           3 classes: 3 sites x 58 lines; 2 sites x 98 lines; 2 sites x 49 lines
C=16  Assert.php          11 classes: … 2 sites x 132 lines …
C=32  Assert.php          12 classes: … 2 sites x 132 lines …
C=64  Assert.php          12 classes: (same as 32)

C=8   Cli/BuilderTest.php 27 classes: largest 2 sites x 203 lines (526 tok)
C=16  Cli/BuilderTest.php 28 classes: largest 2 sites x 263 lines (688 tok)
C=32  Cli/BuilderTest.php 35 classes: 2 sites x 982 lines (2517 tok); 2 sites x 512 lines
C=64  Cli/BuilderTest.php 34 classes: 2 sites x 564 lines — the 982-line class is LOST
```

M2's literal wording no longer holds — at C = 8 `Assert.php` does not vanish
entirely, it collapses from 12 classes to 3 — but the phenomenon is the same
one, and `BuilderTest.php` is starker than it was: the 982-line, 2,517-token
eight-site class exists **only at C = 32**, fragmenting to 203 lines at C = 8
and lost again at C = 64, which also costs 2.7× the wall-clock. Read on
capability rather than on the dense proxy, 32 is still the value, and it is
still chosen knowing it makes the wall-clock number worse.

I have **not** changed the constant. Plan §3 forbids adjusting a constant to
make a fixture pass, and it equally forbids me quietly re-writing a granted
ruling's derivation to keep its conclusion. The value is right and its stated
reason is stale; which of those to fix is the auditor's call.

---

## Ruling request 1 — `PER_FILE_CAP`'s recorded derivation no longer reproduces, though its value is still the right one

**The situation.** Ruling D (M2 audit) granted `PER_FILE_CAP = 32` on the
dense-coverage knee, and that table is now recorded in three places: the
constant's doc comment, plan §1's constants table, and the M2 packet. On the
current engine the table inverts (§1.7): dense coverage peaks at C = 8 and
rises monotonically from 16 to uncapped, so the recorded reasoning, applied to
today's measurement, selects 8.

**What is not in question.** The value. Ruling D's floor argument — the
capability evidence that rejected C = 8 — was re-checked and holds more
strongly than it did at M2: `BuilderTest.php`'s 982-line eight-site class
exists at C = 32 and at no other cap tested. Neither is the cause: the dirty
and clean curves are the same shape, so this is not a contamination artefact
and the clean re-derivation the handoff ordered has been done.

**What I am asking for.** A ruling on which of these the packet and the code
should record, since I decline to pick between them silently:

- **(a) Restate the derivation on the capability evidence.** `PER_FILE_CAP = 32`
  keeps its value; its doc comment, plan §1, and the plan's ruling-D text are
  amended to say the value is chosen on the multi-site-class evidence, with the
  dense-coverage table demoted to a superseded M2 measurement and the M4 table
  recorded beside it. This is my recommendation: it is what the constant is
  actually justified by today, and it removes a number from the record that a
  future session would re-run and be misled by.
- **(b) Re-derive on a metric that still discriminates.** Dense coverage was a
  proxy chosen at M2 to make the cap arbitrable; if the auditor wants a
  numeric derivation rather than a capability one, the metric needs replacing,
  and that is a new derivation requiring a ruling of its own before it is
  measured — not something I should design after seeing which value it picks,
  which is the ruling-D lesson stated in ruling T.
- **(c) Neither — the value stands on ruling D as granted**, and §1.7 is
  recorded as a measurement note only.

I have proceeded to steps 2–3 in the meantime: the constant is unchanged under
all three options, so nothing downstream depends on the answer.

### Ruling: option (a) granted with four conditions — applied here

The auditor granted (a): the derivation is restated on the capability evidence,
legitimate because the exhibits were **pre-registered** — recorded at M2 and
verified by inspection before anyone knew the knee would invert. The value does
not move. The four conditions are discharged below.

#### Condition (i) — both exhibits, the full cap sweep, and which caps preserve which

The sweep is over the whole corpus, reporting for each exhibit file the number of
clone classes touching it and its largest class. "Preserved" is the exhibit's
large class being reported at all — a number, not a judgement.

```
cap        Assert.php                              Cli/BuilderTest.php
           classes  largest (lines/sites/tok)      classes  largest (lines/sites/tok)   most-sites
  4          3       58 /  2 /   71                  14      105 /  2 /   268             5
  8          3       98 /  2 /  118                  27      357 /  2 /   901             7
 16         13      132 /  2 /  150                  31      263 /  2 /   688             9
 24         13    **572** /  2 /  941                37      419 /  2 /  1081            11
 32         14    **572** /  2 /  941                35    **982** /  2 /  2517          12
 48         14    **572** /  2 /  941                40    **982** /  2 /  2517          14
 64         14    **572** /  2 /  941                47      510 /  3 /  1252            13
128         14    **572** /  2 /  941                46    **974** /  2 /  2505          22
  ∞         14    **572** /  2 /  941                46    **974** /  2 /  2505          22
```

| exhibit | preserved at |
|---|---|
| `Assert.php`'s 572-line class | 24, 32, 48, 64, 128, ∞ |
| `BuilderTest.php`'s ~974-line class | **32, 48, 128, ∞** |
| **both** | **32, 48, 128, ∞** |

**32 is not the unique value preserving both — it is the smallest.** The ruling
anticipated uniqueness ("if 32 is the unique value preserving both, that
uniqueness is the derivation"); the measurement does not support that, and the
weaker true statement is recorded instead. The derivation is therefore
**minimality against a steeply rising cost**: 32 preserves both exhibits, 48 and
128 preserve them at 2.6× and more of the wall-clock, and everything below 32
destroys at least one. That still lands on the shipped value without moving it,
and it still costs speed relative to C = 8 — the opposite of tuning to pass.

Two things the sweep shows that the M2 record did not, both recorded rather than
smoothed:

- **`Assert.php`'s floor is 24, not 32.** M2's "disappears entirely at C = 8" is
  correct but understated the range: the class is absent at 4, 8 and 16 and
  present from 24 up. It is `BuilderTest.php`, not `Assert.php`, that puts the
  floor at 32.
- **Preservation is not monotonic in the cap.** C = 64 *loses* the
  `BuilderTest.php` class that 32, 48 and 128 all keep (largest falls to 510
  lines). A larger cap admitting more occurrences can change which chain wins and
  destroy a class a smaller cap kept. This is a further reason to treat any
  single-number reading of this constant as evidence about a particular engine
  state, which condition (iv) is about.

The exhibits' *shapes* have also shifted since M2 — `Assert.php`'s 572-line class
is now reported at 2 sites rather than 3, and `BuilderTest.php`'s at 2 sites
rather than 8, with up to 22 sites appearing in other classes at high caps.
Rulings J, M and O all changed grouping, so this is expected; the constraint is
stated on the property that is still checkable (the class is reported at that
size) rather than on a site count the engine no longer produces.

#### Condition (ii) — the dead knee struck in the plan, dated, with cause and hypothesis

Done in `docs/research/unified-engine-plan.md` §1's constants table: the
dense-coverage sentence is struck through with `~~…~~`, dated 2026-09-01, with
the cause recorded as this engine's M3/M4 changes (contamination explicitly
excluded — dirty and clean curves have the same shape) and the mechanism
recorded **as hypothesis**: that ruling O's evidence-return supplies from refused
candidates what capping used to rescue. The same restatement is in the
constant's own doc comment.

#### Condition (iii) — the full dense-coverage curve, so the metric question stays open on evidence

Clean corpus, current engine, every cap measured:

| cap | 4 | 8 | 16 | 24 | 32 | 48 | 64 | 128 | ∞ |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| dense lines | — | **13,410** | 8,638 | — | 9,036 | — | 9,644 | — | 9,777 |
| all lines | — | 69,354 | 66,759 | — | 71,962 | — | 76,409 | — | 76,383 |
| clones | — | 428 | 522 | — | 564 | — | 562 | — | 563 |
| seconds | — | 1.55 | 1.90 | — | 2.67 | — | 7.13 | — | 5.08 |

(Dirty-corpus row, for the contamination question: 13,450 / 8,678 / 9,076 /
9,684 / 9,817 — the same shape offset by roughly 40 lines.)

The shape is the finding: a peak at the tightest cap, a trough at 16, then a
monotone rise to uncapped. A metric that both peaks at the value the floor
argument destroys *and* rises toward no cap at all is not discriminating between
caps in the way ruling D read it. Whether a successor metric exists is left open
on this evidence rather than settled here; designing one after seeing which value
it picks is the ruling-D lesson that ruling T restates, so it would need its own
ruling first.

#### Condition (iv) — the restatement declares its own dependence

Recorded in both the plan and the doc comment, in those words: the derivation
rests on two exhibits behaving differently at different caps, and **any future
change that makes them cap-insensitive — as the dense metric became — re-opens
this constant** and requires a fresh derivation rather than an appeal to the
comment.

No CHANGELOG entry: the value, the stored bytes and the selection rule are all
unchanged, so there is no change for an entry to describe. `IndexCodec::VERSION`
stays at 5 (ruling Q).

---

## 2. Ruling O — a refused candidate no longer consumes the evidence inside it

Landed at `f4bc0a7`. It clears its acceptance criteria and clears one of them
by more than was asked.

### 2.1 The defect, traced rather than assumed

`fromCluster()` mines a cluster by taking the best chain, removing the anchors
it consumed, and asking again. The removal (`$remaining = $left`) happens
**before** `verify()` runs, in both branches. So a candidate refused by the
aligner has already taken its whole span's anchors out of the cluster, and
whatever exact duplication sat inside the refused reading is gone.

Reproduced in isolation on the pair the ruling names:

```
$ php -r '... Engine(unified)->detect([assertArraysAreEqualIgnoringOrderTest.php,
                                        assertArraysAreIdenticalIgnoringOrderTest.php]) ...'
unified: 1 clones
  assertArraysAreEqualIgnoringOrderTest.php:388 + …IdenticalIgnoringOrderTest.php:391  62 lines 82 tok
rabin-karp: 3 clones
  …EqualIgnoringOrderTest.php:27  + …IdenticalIgnoringOrderTest.php:24   78 lines 193 tok
  …EqualIgnoringOrderTest.php:157 + …IdenticalIgnoringOrderTest.php:119 103 lines  98 tok
  …EqualIgnoringOrderTest.php:388 + …IdenticalIgnoringOrderTest.php:391  62 lines  82 tok
```

Instrumented, the cluster's rounds are:

```
ROUND kind=gapped A=[34,+389) B=[31,+351) tok=389 sim=0.000 acc=N reason=similarity below 0.85 remaining=2
      gap A=[227,+78) B=[224,+20)
      gap A=[325,+0)  B=[264,+20)
ROUND kind=type-1 A=[526,+82) B=[502,+82) tok=82  sim=1.000 acc=Y reason=exact           remaining=1
ROUND kind=type-1 A=[458,+33) B=[469,+33) tok=33  sim=0.000 acc=N reason=below thresholds remaining=0
```

The refused chain's exact runs are `[34,227)` — **193 tokens, Rabin-Karp's
first clone** — then a 78-token gap, a 20-token run, a 20-token insertion in B,
and `[325,423)` — **98 tokens, Rabin-Karp's second clone**. Both lost clones
are exact runs *inside* the refused reading, and `remaining` drops to 2 in one
round. The engine found all three clones for this pair and reported one.

### 2.2 The mechanism, and why this one

The derivation starts from what the refusal actually asserts. Of the three ways
`verify()` can refuse:

| reason | inherited by every sub-span? |
|---|---|
| below `minTokens` / `minLines` | **yes** — a sub-span is shorter still |
| below the normalized diversity floor | **yes** — a sub-span draws on no larger an alphabet |
| refused by the aligner | **no** |

Only the aligner's refusal says nothing about the parts. It is a verdict on the
span *taken as one alignment*, and the chain underneath it is made of exact
runs: the refusal means the gaps between them cost more than acceptance allows,
not that the runs are not clones. So this is the one refusal whose evidence must
come back — which is precisely the distinction ruling O draws.

Returning it whole would rebuild the identical chain next round and spin, which
is why the ruling says "bar it or decompose". The reading is decomposed, because
it is wrong in a *locatable* way: it bridges its widest gap, and that gap is the
largest single contributor to the cost that refused it. Cutting there yields the
two readings the chain conflated.

Applied to the trace above: the widest interior gap is `A=[227,+78)`, and the
split gives `[34,227)` — the 193-token clone — and `[305,423)`, which is
re-classified, refused again at its own 20-token gap against a 118-token span,
split again, and yields the 98-token clone. **The decomposition converges to the
exact runs**, which is the right endpoint.

Three properties, none of them requiring a new constant:

- **The split point is read off the candidate**, exactly as ruling J reads the
  period off the candidate. Only gaps interior to the aligned span count,
  matching the filter `verify()` already applies when totalling their cost —
  an edge divergence (ruling C) sits outside the alignment and did not
  contribute to the refusal.
- **The refused chain cannot be rebuilt.** An anchor straddling the gap on
  either side is evidence *for* the bridge and survives into neither half.
- **Termination is unconditional, not argued.** Each half excludes at least the
  straddling anchors, and the code additionally refuses any half equal in size
  to the set it came from. A worklist replaces recursion so a decomposition
  chain as long as the cluster has gaps costs no stack.

Implementation is in `UnifiedStrategy::splitAtWidestGap()` with the loop turned
into a worklist; `CloneClassifier::refusedOnSimilarity()` names the one refusal
that qualifies, so the trigger is an identity rather than a string search
scattered at the call site.

### 2.3 Acceptance — met, and the residual beats its target

| criterion | required | measured |
|---|---|---|
| the traced pair reports its two exact clones | yes | **yes — 1 → 3 clones on the pair** |
| phpunit superset residual | returns to 21 | **19** |
| recall | stays 295/295 | 295/295, 2/2 |
| chaining oracle | stays 2/2 | 2/2 |
| no constant | none | none |

The residual's composition was checked rather than inferred from the count. The
check's output list is capped at 12 items, so the naive before/after diff
appeared to add two `MetadataTest.php` pairs; lifting the cap to 200 and
diffing the complete lists shows what actually changed:

```
before = 21 unexplained, after = 19 unexplained
20,21d19
< UNEXPLAINED  assertArraysAreEqualIgnoringOrderTest.php:157 ↔ …IdenticalIgnoringOrderTest.php:119 — baseline 98, unified 0, actually shared 98
< UNEXPLAINED  assertArraysHaveEqualValuesIgnoringOrderTest.php:269 ↔ …IdenticalValuesIgnoringOrderTest.php:344 — baseline 70, unified 0, actually shared 70
```

**Exactly the two pairs ruling O names, both removed; nothing else in the list
changed and nothing new appeared.** Separately, the one uncovered location
(`assertArraysHaveEqualValuesIgnoringOrderTest.php:269`) is now reported, so the
subsumption check's *location* half passes outright for the first time — the
gate goes from 2 of 3 failing to 1 of 3. What remains is entirely a grouping
difference, which is a change in kind worth stating, not only in count.

### 2.4 Full gate state after ruling O

```
vendor/bin/phpunit                                   143/143
vendor/bin/phpstan analyse                           no errors
vendor/bin/phpstan analyse -c phpstan-bench.neon     no errors
php bench/self-test.php                              24/24
php bench/check-chaining.php                         2/2
php bench/check-determinism.php php-parser  --algorithm=unified   4/4
php bench/check-determinism.php phpunit     --algorithm=unified   4/4
php bench/check-incremental.php php-parser           8/8
php bench/check-superset.php php-parser              3/3
php bench/check-superset.php phpunit                 FAIL — 19 items (0 locations + 19 pairs)
php bench/run-recall.php --sample=40                 2/2 — guaranteed region 295/295
php bench/check-walltime.php phpunit                 FAIL — 4/4 sizes
```

Corpus effect: phpunit 564 → 599 clones, php-parser 81 → 82.

**The cost, recorded rather than buried.** Refused candidates now do work where
they used to stop, and the wall-clock gate — already failing — gets worse:

```
                 before O   after O
  60 files        0.66x      0.62x
  200 files       0.51x      0.47x
  600 files       0.57x      0.55x
  2500 files      0.41x      0.36x
```

Roughly 8 % at the largest size. This is a correctness fix landing against a
performance gate that ruling F owns and that the project owner holds a decision
on; it is recorded here as a debit to that decision, not as a reason to have
declined the fix.

README updated at `8755a9e` (residual 19, the grouping-only characterisation,
the new ratios).

---

## 3. The merged-default baseline gate — built, run for the first time, and failing exactly where predicted

Built at `bb19d78`. `bench/check-superset.php` gains `--baseline=`, taking
`rabin-karp` (the default, unchanged), `default` — the merged pipeline the
`Engine` builds when given no algorithm, which is what 1.4.0 ships — and
`tokenbag`, which exists to attribute a failure rather than merely record one.

### 3.1 The result

| corpus | vs `rabin-karp` | vs `default` (rk+tokenbag) | vs `tokenbag` alone |
|---|---|---|---|
| symfony-string | pass | **3/3 pass** | — |
| php-parser | 3/3 pass | FAIL — 8 locations, 6 unexplained pairs | 8 locations |
| phpunit | FAIL — 0 locations, 19 pairs | FAIL — 137 locations, 38,884 pairs | 137 locations |
| firefly-iii | FAIL — 0 locations, 7 pairs | FAIL — 311 locations, 1,941 pairs | 311 locations |

*(The pair columns here are the **pre-ruling** figures, kept as measured. §3.3
records what they resolve to once order-free findings are reported as
inapplicable: 23 on phpunit and 7 on firefly-iii, with php-parser's pairs half
passing. The location column is unaffected by that ruling and is the release
gate.)*

Verbatim, phpunit:

```
$ php bench/check-superset.php bench/corpus/phpunit --baseline=default
Subsumption check — 2695 files, --min-tokens=70 --min-lines=5

  default (rk+tokenbag)   358 clones,   20076 duplicated lines, 0.891s
  unified                 599 clones,  111007 duplicated lines, 3.025s

subsumption check: 2 of 3 checks FAILED:
  - every location the baseline reports is reported by the unified engine — 137 locations NOT reported
  - every pair the baseline reports is reported at a length the source agrees with — 100419 pairs — 14 same length, 61329 longer, 39076 shorter (192 of those over-reported by the baseline), 38884 unexplained
```

**The attribution is exact on every corpus.** The uncovered-location count under
`--baseline=default` equals the count under `--baseline=tokenbag` — 8 = 8,
137 = 137, 311 = 311 — while the Rabin-Karp location half passes everywhere
(phpunit's did so from ruling O, firefly's and php-parser's already did). So the
unified engine covers **everything Rabin-Karp reports and nothing the token bag
reports alone**. There is no third category, and the failure is not diffuse: it
is precisely the class ruling A's algebra says a seeded method cannot see, which
is what ruling R exists to close. Reported as a number, not patched, exactly as
the handoff directed.

Firefly-iii is worth noting because it had not previously been run against the
Rabin-Karp baseline either: it passes the location half there (0 uncovered) with
7 unexplained pairs, which is a new clean data point for the RK gate.

### 3.2 One caveat, because the output invites a wrong reading

The check settles length disagreements with an independent walk over the two
token streams — deliberately trusting neither engine — and that walk measures a
**contiguous** run. A token-bag finding is by construction not contiguous. On
php-parser:

```
UNEXPLAINED  NodeTraverser.php:93 ↔ NodeTraverser.php:181 — baseline 140, unified 0, actually shared 2
UNEXPLAINED  CompatibilityTest.php:16 ↔ CompatibilityTest.php:44 — baseline 69, unified 0, actually shared 7
UNEXPLAINED  ClassConstTest.php:168 ↔ ParamTest.php:33 — baseline 45, unified 0, actually shared 9
```

"Actually shared 2" does not mean the token bag over-reported by 138; it means
the adjudicator is the wrong instrument for an order-free finding. The
consequence is that **the pair-length half of this check cannot arbitrate a
token-bag baseline**, and its 38,884 / 1,941 / 6 "unexplained" counts against
one are not evidence about either engine. They are also inflated by shape: a
token-bag class naming N sites contributes N(N−1)/2 pairs, which is how phpunit
reaches 100,419 pairs from 358 baseline clones.

The **location** half is unaffected by both problems, and it is the half the
owner's gate is written in ("every location the 1.4 default reports is
reported"). So the numbers in §3.1's location column are the release-gate
evidence; the pair columns are recorded for completeness and should not be read
as a second failure.

I have not changed the adjudicator. Making it order-free would be a new
mechanism for measuring a baseline this project intends to replace, and ruling R
is about to make the question moot. If the auditor wants the pairs half reported
as *inapplicable* rather than *failing* when the baseline includes the token
bag, that is a one-line change to the check's summary and I will make it on
request — I have not made it unasked, because suppressing a failing count is
exactly the shape of thing that needs a ruling rather than an executor's
judgement.

### 3.3 Applied: the ruling on the pairs half — `inapplicable`, not a pass

Granted at the M4 stop point and implemented at `03c9e59`. A pair whose baseline
attribution is the token bag is reported **inapplicable** — counted, listed, and
never folded into a pass line — because the adjudicator recomputes a contiguous
run and that is not the claim an order-free finding makes. Attribution is per
*pair*, against a Rabin-Karp reference run: a pair is Rabin-Karp's if some
Rabin-Karp clone covers both its locations, the token bag's otherwise.
`--baseline=rabin-karp` is unaffected — re-run to confirm: phpunit still 19
unexplained, 0 inapplicable.

What the gate says about itself changes substantially, and in the direction that
says the old number was noise:

| corpus | locations | pairs before | pairs after |
|---|---:|---|---|
| symfony-string | **pass** | 0 unexplained | 0 unexplained + 3 inapplicable — **3/3 pass** |
| php-parser | 8 | 6 unexplained | **0 unexplained** + 6 inapplicable — pairs half passes |
| phpunit | 137 | 38,884 unexplained | **23 unexplained** + 99,854 inapplicable |
| firefly-iii | 311 | 1,941 unexplained | **7 unexplained** + 4,871 inapplicable |

So the genuinely adjudicable pair residual against the merged default is 23 on
phpunit and 7 on firefly-iii, against 38,884 and 1,941 reported before. The rest
was the category error, at the scale the ruling anticipated.

One number to be honest about rather than pass over: phpunit's adjudicable
residual against the *merged* baseline is 23, where against Rabin-Karp alone it
is 19. The merged pipeline groups its clones differently, so four additional
contiguous pairs are formed that no single unified class covers. They are the
same grouping class as the existing 19 — the `MetadataTest.php` family — not a
new failure mode, but they are counted and not explained away.

The deferred condition is recorded in the check's own source and enters ruling
R's acceptance: when the complement pass lands, the pairs half becomes
applicable to order-free findings again through a bag-appropriate independent
adjudicator — a textbook bijective-coverage recompute over the two spans,
written independently of the engine's own code, exactly as the location walk is
independent today.

### 3.4 What this gate now owes ruling R

Ruling R's acceptance includes "the merged-default superset gate passing its
TokenBag half". This section pins the target: **8 locations on php-parser, 137
on phpunit, 311 on firefly-iii, and symfony-string already passing**, plus the
bag-appropriate adjudicator that §3.3 defers into this ruling's acceptance. Those are
the numbers the complement pass has to close, and they are now measured on the
clean corpus with ruling O landed, so they are the right baseline to build
against.

---

---

## 4. Stage 0 — corpus triage (rulings P and T)

In progress. The snapshot is pinned, the ladder is measured, and the
shadowed-duplicate rung has been measured before being built — which turned up
a finding that changes what the M3 precision evidence can be asked to support.

### 4.1 (i) The pinned snapshot — done

`bench/pin-snapshot.php`, added at `4ea494e` and amended at `9d9e20b`. It
records every `.php` file under a root with its SHA-256 and size; `verify`
reports what has since been added, removed or changed.

```
$ php bench/pin-snapshot.php pin <corpus> --out=<outside-every-repo>
pinned  4813 files
digest  78f7f1049958bf41df8316a2f89cd7f2ab597f02d2dba2100c7bfe11f05ba802
```

The manifest lives outside every repository at mode 600, alongside the M3 rater
worksheets. It names nothing: paths are stored relative to the corpus root, the
root itself never appears, and the tool refuses to write inside this repository
— the guard lifted from `bench/audit-precision.php`, which already applies it to
worksheets. **Packets cite the digest above and nothing else.**

Two design choices, both recorded because both could have gone the other way:

- **A hash list, not a frozen copy.** Ruling P allows either. The copy answers
  "what did it look like?"; the hash list answers the question the ruling was
  actually recorded for — "is this still the tree the number came from?" — and a
  copy silently fails to ask it. The list also needs only read access, which is
  the access this corpus is available under.
- **Dependency trees are never pinned.** `vendor/` and `node_modules/` are
  pruned unconditionally. They are not the project's own text, so dropping them
  begs no triage question, and they churn on every `composer install`, which
  would make `verify` fail for reasons unrelated to the corpus drifting.
  Everything else stays in the pinned rung — caches, build output, scratch and
  backup trees, generated tables — because that is precisely the material Stage 0
  must learn to triage, and pinning a tree with it already removed would pin the
  answer along with the question.

### 4.2 The corpus ladder, in files, on the pinned snapshot

M3 recorded the ladder in *findings* (1,009 → 897 → 600 → 74). Ruling T needs it
in **files**: its training label set is the ladder's kept-vs-excluded files, and
its acceptance is that Stage 0, pointed at rung one, approximately reproduces
rung four. Every rung is the tool's own machinery, per ruling K — no hand list:

| rung | definition | files | Δ |
|---|---|---:|---:|
| 1 | the project's own `.php`, dependencies pruned | 4,813 | — |
| 2 | + the product's default excludes (caches, build, generated) | 3,733 | −1,080 |
| 3 | + the shipped `laravel` preset's excludes | 3,198 | −535 |
| 4 | + entire-file orphans dropped (`Orphans::detect()`) | **3,052** | −146 |

**Stage 0's target is therefore 3,052 from 4,813 — a 37 % reduction**, and that
is the acceptance fixture. One consistency check worth recording: 146 orphans of
3,733 is 3.9 %, which reproduces M3's recorded "entire-file orphans were 3.9 %
of files" exactly, on a tree pinned five months of drift later. The ladder's
machinery is stable even where its corpus is not.

### 4.3 (ii) The shadowed-duplicate rung — measured first, and the backup subtree is gone

Ruling P (ii) asks for a rung deriving "a file whose declared symbols are all
also declared by another wired file" from the tool's own symbol table, never a
path pattern, and asks it to be verified against the backup subtree that
contaminated ~30 % of the M3 pool.

The first half of the rule — files whose *entire* declared symbol set is also
declared elsewhere — was measured from `SymbolCollector` before anything was
built, because a rung should be designed against a number:

| rung | files | declaring a symbol | sharing ≥1 | **all symbols shared** |
|---|---:|---:|---:|---:|
| 1 (4,813) | 4,813 | 1,890 | 50 | **50** |
| 2 (3,733) | 3,733 | 1,888 | 50 | **50** |
| 3 (3,198) | 3,198 | 1,888 | 50 | **50** |
| 4 (3,052) | 3,052 | 1,742 | 50 | **50** |

Fifty candidates, all under one top-level directory, and — importantly — the
count is *unchanged* by rungs 2, 3 and 4, which is the ruling's own premise
confirmed: no existing rung of ruling K's definition can see them. That is
exactly why the rung is needed.

**But they are not the M3 contamination, and the M3 contamination is no longer
in the tree.** The candidates' share of what the engines actually report, on
rung 4:

```
unified      findings  4457   touching a candidate     2 (0.0%)
rabin-karp   findings   867   touching a candidate     0 (0.0%)
tokenbag     findings    77   touching a candidate     3 (3.9%)
```

Two findings of 4,457. M3 recorded 18 of 60 rated findings locating to a backup
directory — 30 % — and that subtree does not appear in this snapshot at all.

Before concluding drift, the obvious confound was checked and does not apply:
M3's 30 % is a share of *findings* while the 50 is a count of *files*, and a
small backup tree can carry a large share of findings (M3 itself recorded 26 of
34 false positives coming from two files). So the two numbers could have been
consistent. The finding-share measurement is what rules that out — the
candidates carry no findings, so the backup material is genuinely absent rather
than merely concentrated.

**What follows, and it is the auditor's to weigh.** Ruling P's verification
condition — "verify it removes the backup subtree that contaminated ~30 % of the
M3 pool" — **cannot be discharged as written.** The tree it refers to is gone,
and by ruling P's own reasoning the live tree was never reproducible, which is
what the ruling was recorded to fix. This is the ruling validating itself at the
cost of its own acceptance test.

The rung is still worth building, and I intend to build it: its premise is
confirmed (50 files invisible to every existing rung), the mechanism is
principled, and it will matter on the next tree that has a backup directory in
it. But it will be accepted against a **constructed fixture** — two files
declaring the same symbols where the autoloader maps one — rather than against
the M3 subtree, and the honest statement is that its effect on *this* corpus's
precision number is approximately zero. I am recording that before building it
rather than after, so the fixture cannot be chosen to flatter the result.

A consequence for the release gate: the M3 precision figures were recorded as
"contaminated as a corpus estimate", and the fresh pool under K + P will be run
on a tree that no longer holds the contaminant. The re-pooled number will
therefore be a **different measurement**, not a corrected one — and the M3
figures cannot be recovered or re-derived, only superseded.

### 4.4 The rung, built — and ruling P (i) amended by the project owner

The rung is `src/Triage/ShadowedDuplicates.php`, landed at `31dd54c` with
`Orphan/AutoloadRule.php` (the resolver) and the pre-registered fixture. The rule
is the ruling's, in two conjuncts: **every** name the file declares is also
declared elsewhere, **and** for every one of those names the autoloader maps the
other file and not this one. `ComposerManifest::pathsFor()` answers the second
from the project's own psr-4/psr-0 map — a manifest is checkable evidence, and a
directory called `backup` is not.

Five ways the question can fail to have an answer, and all five keep the file:
no manifest; a namespace the map does not cover; a prefix mapping two
directories, so the autoloader reaches both copies (this repository's own
manifest maps three, which is why the case is a fixture and not a hypothetical);
a file declaring one shadowed name and one of its own; a file declaring nothing,
whose "every symbol is declared elsewhere" is vacuously true. Each is a test.

Measured where it can be measured:

```
php-parser       files=  342  shadowed=0
phpunit          files= 2695  shadowed=0
symfony-string   files=   33  shadowed=0
firefly-iii      files= 1447  shadowed=0
this repository  files= 4715  shadowed=0
```

Zero on every tree under version control, which is the expected answer and the
paired negative the fixture cannot supply.

**On the private corpus it fires, and on material no other rung sees.** 25 files,
all one vendored package's `baseline/src/` tree duplicating its own `src/`, with
the same fully-qualified names and no autoload route. The count is identical at
rung 1 and at rung 4, so the ruling's premise — that no existing rung of the
frozen definition can see them — is confirmed exactly. Their share of what the
engines report, on rung 4:

```
files 3067, shadowed 25
unified      findings  4449   touching a shadowed file    2 (0.0%)
rabin-karp   findings   868   touching a shadowed file    0 (0.0%)
tokenbag     findings    79   touching a shadowed file    3 (3.8%)
```

Two findings of 4,449. That is the number §4.3 predicted **before** the rung was
built, recorded then so it could not be chosen afterwards, and it holds: this
rung is right in principle and worth approximately nothing on this corpus's
precision. What is worth something on this corpus is §4.5.

#### Ruling P (i) — amended by the project owner (2026-09-01)

The pinned snapshot of §4.1 (`78f7f104…`, 4,813 files, pinned at 16:04) no longer
verified two hours later:

```
Snapshot verify — 4813 files pinned, 4895 present
  FAIL  no files have appeared since the snapshot — 83 added
  FAIL  no files have disappeared since the snapshot — 1 removed
  FAIL  no pinned file has changed content — 19 changed
```

83 added / 1 removed / 19 changed, of which 24 additions and all 19 changes are
program text. The file count moved again *between two measurements taken minutes
apart in this session* (4,898 → 4,801). I put the options to the project owner —
freeze a copy (ruling P's other permitted form), re-pin per measurement, or use
the stale pin — and the answer was:

> "[the corpus] is a live repo will never be frozen; dogfood is not a static point"

So ruling P (i) is amended at its premise. **Pinning is not a freeze; it is a
stamp.** A dogfood number is not "taken from a pinned snapshot" — it is *taken
over a window*, and the pin says which window. The working protocol that follows,
and which the rest of this packet uses:

- re-pin immediately before a measurement round, cite that digest, and verify
  immediately after so drift *during* the round is reported beside the number
  rather than discovered later;
- the precision worksheet already embeds the source excerpts a rater judges
  (`bench/audit-precision.php` writes up to 25 lines per site), so a rating stays
  anchored to the code it was made against even after the tree moves. This was
  checked rather than assumed, and it is what makes the two-rater protocol
  survive a live corpus at all;
- a number is never compared across pins without saying so.

This round's pin: **`f887203c1d2ebbf624976743da00bded4a217d6f032d0f7618644b9457bd0ae9`**,
4,895 files. Every number in §4.4 and §4.5 is from that window.

The ladder, re-measured on it, against §4.2's on the superseded pin:

| rung | definition | files (new pin) | files (§4.2) |
|---|---|---:|---:|
| 1 | the project's own `.php`, dependencies pruned | 4,895 | 4,813 |
| 2 | + the product's default excludes | 3,757 | 3,733 |
| 3 | + the shipped `laravel` preset's excludes | 3,222 | 3,198 |
| 4 | + entire-file orphans dropped | **3,067** | 3,052 |

The shape is stable under drift, which is worth recording: the ladder's rungs are
machinery, and machinery does not move when the corpus does.

#### The M3 worksheet, relocated

`bench/audit-precision.php` digests its paths behind a **per-run salt that is
never stored**, so the M3 worksheet cannot be read back to files — which blocks
both ruling T's acceptance ("no file containing a consensus-Y finding is
discarded") and ruling U's tracking metric.

It is recoverable, because the worksheet embeds source and source locates itself.
Each site's most distinctive excerpt line is searched across the corpus; a site
is relocated when that line is found. On this pin:

```
worksheet: 60 findings; corpus: 4801 files
sites 131 — located in one file 86, found in several 6, not found 39
findings relocated: 47 of 60
  consensus Y: 24, relocated 24
  consensus N: 33, relocated 20
```

24 Y + 33 N = **57 consensus labels, matching the M3 record exactly**, and *all
24 consensus-Y findings still exist in the tree*. 13 of the 33 consensus-N
findings do not — consistent with M3's record that 18 of 60 rated findings sat in
a backup subtree that has since been deleted. The relocated worksheet is written
outside every repository at mode 600 and is the acceptance fixture for both
ruling T and step 5.

---

## 4.5 Stage 0 — built, and stopped at its label set

The stage is written and green: `src/Triage/Stage0.php` runs the two wiring
rungs first (entire-file orphans through the existing `OrphanDetector` with every
suppression rule it already carries, then §4.4's shadowed rung) and puts only the
residual to `FishinessClassifier` — naive Bayes with Laplace smoothing over the
bucketed, content-and-wiring-only features of `FileFeatures`, with every verdict
decomposable into per-feature log-odds so a discard names its own evidence.
`bench/triage.php train` counts the model from labels and regenerates
`src/Triage/FishinessModel.php`; `bench/triage.php check` runs the ladder
acceptance.

**The model ships untrained, and deliberately.** Training it as ruling T
specifies produces a classifier that cannot work, for two reasons that are
findings rather than implementation faults. Both are below, and both need a
ruling before a number is written into a constant.

### 4.5.1 The program label is contaminated — 33.7 % of rung 4 is a dumped cache

Ruling T trains "program" on the ladder's fourth rung. On this corpus that rung
holds **1,033 files under one trash directory**, and 1,024 of them are a PHPStan
result cache:

```
<?php declare(strict_types = 1);

// osfsl-…/SupportsBasicAuth.php-presentSymbols
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-efc0d2b7…-8.5.9',
   'data' => array ( 'classes' => array ( 'illuminate\\…\\supportsbasicauth' => true, ), … ),
```

That is `var_export()` output — the exact artefact class ruling T names on the
**fishy** side when it cites phpunit's dump. It survives every rung of the frozen
definition: the default excludes know `.phpstan` and `var/cache` but this tree's
cache directory is spelled differently; the preset does not cover it; and it is
not an orphan, because it declares nothing to be orphaned.

Three consequences, in increasing order of how much they matter:

1. **Trained as specified, the classifier learns that a cache dump is program
   text.** The two score distributions overlap almost completely (fishy 1,204
   files above 10, program 1,072), the derived zero-loss margin degenerates, and
   at it the model discards 2.2 % of the fishy class while losing 0.70 % of the
   program class. It is useless.
2. **The acceptance is inverted.** "Stage 0 approximately reproduces rung 4" now
   *requires* keeping 1,033 files of dumped cache. A Stage 0 that removed them
   would fail its own acceptance test.
3. **A fresh precision pool under K + P would be ~34 % contaminated by file
   count.** §4.3 reported the M3 backup subtree gone and concluded the corpus was
   clean of that class; that conclusion was about the wrong subtree. The
   contamination did not go away — it moved, and it grew. This is the third
   distinct shape of it (M3's backup tree, M4 step 1's `tools/.phpstan`, this),
   which is itself the argument for ruling T's stage existing.

Relabelled — the trash tree moved to the fishy side by
`--fishy-under=`, a **training** argument that never becomes a feature — the
model separates sharply:

```
Score distribution (bin edges -20 -10 -5 -2 -1 0 1 2 5 10 20)
  fishy        0   187    66    15    10     3     6     3    20   309  1728   557
  program      0  4450   277    49     9     9    13     3    46    83   172     8

  returns_data  yes    2064 fishy    178 program   +3.012
  referenced    n/a    2639 fishy    361 program   +2.553
  size          large   190 fishy     46 program   +1.968
```

`returns_data` — the `var_export` shape ruling T names — becomes the strongest
single term in the table, which is the ruling's own hypothesis confirmed on
measurement.

### 4.5.2 The fishy label conflates three different claims, and one of them is not decidable from content

Ruling T's label set names two fishy sources. They are not the same kind of
thing, and a third hides inside the ladder:

| notion | example in the label set | decidable from content + wiring? |
|---|---|---|
| not code at all | a `var_export` cache dump | **yes** — this is what the features see |
| someone else's code, vendored in | phpunit's `tools/.phpstan` (117 files) | **no** — it *is* program text; it is a manifest question |
| our code we choose not to scan | the preset's `*.blade.php` (488), `database/migrations` (47) | no — it is a policy, and a user-facing knob |

Measured: trained on the second notion alone (phpunit's dump against its own
tree, plus the public corpora), every log-odds term collapses toward zero — 109
of the 117 "fishy" files declare exactly one class, like the program class — and
the model discards 1 file of 117. That is not a tuning failure. A vendored tool
tree is indistinguishable from program text by content **because it is program
text**, and no feature set can separate it.

The composition of the ladder's excluded set, for the record:

```
rung 2  default excludes      1042    caches, build, dist, coverage — notion 1
rung 3  laravel preset         538    488 blade, 47 migrations, 3 public — notion 3
rung 4  entire-file orphan     153    — already removed by Stage 0's first rung
```

So of the 1,733 files ruling T labels fishy, 1,042 are notion 1, 538 are notion 3,
and 153 are material the wiring rung has already taken before the classifier is
asked anything.

---

## Ruling request 2 — ruling T's label set, at both ends

**What I am not asking about.** The stage, the features, the never-silent
reporting and the wiring-first order are built as specified and are not in
question. Neither is the ban on path features: the classifier reads content and
wiring only, and will continue to.

**The two defects, restated in one line each.** The program label (rung 4) is
33.7 % dumped cache on this corpus, so it teaches the opposite of the ruling's
intent; and the fishy label mixes a content-decidable notion with one that is
provably not content-decidable, so a single binary class cannot be learned.

**What I propose, and have implemented but not enabled:**

- **(a) The classifier's fishy class is notion 1 only** — machine-emitted data
  files. That is the class the features can decide, and it is the class M3
  measured the false-positive mass into.
- **(b) Notion 2 moves to the wiring rungs, where it is decidable.** A file
  declaring names in a namespace the project's manifest does not own is not this
  project's text — `ComposerManifest::owns()` already exists and the orphan rule
  already uses it. That is a fourth rung of proof rather than an estimate, and it
  covers phpunit's `tools/.phpstan` exactly.
- **(c) Notion 3 stays where it is**, in the preset's exclude list, as the
  user-facing policy it already is. It is not triage.
- **(d) The training labels gain the dogfood trash tree on the fishy side**, as a
  structural claim of the same kind as phpunit's dump — a *label*, argued for by
  the `var_export` signature quoted above, and never a feature.
- **(e) The acceptance is restated.** "Stage 0 approximately reproduces rung 4"
  becomes "Stage 0 reproduces rung 4 **minus the labelled dump mass**, and the
  difference is measured and reported both ways". As it stands the acceptance
  requires keeping 1,033 files of cache, which no correct Stage 0 can do.

I have implemented (a)–(d) as an option behind an argument, run it, and recorded
the result above; nothing is enabled and no constant is written. (e) is the part
I cannot take myself: it changes a granted ruling's acceptance test.

---

## Ruling request 3 — the margin has no separation to be set at

Ruling T requires "an asymmetric margin set toward keeping" and §3 requires a
derivation. Ruling B's method — measure two distributions, set the threshold at
the separation — is the project's precedent, and I applied it. **There is no
separation.** Even with the labels corrected, the program class has a genuine
dump-shaped tail: `lang/en/*.php` and Laravel `config/*.php` are literally
`return [ … ];`, they are program text by the frozen definition, and they sit
inside the fishy class's score range.

The measured trade, on the corrected labels:

| margin | fishy discarded | program lost |
|---:|---:|---:|
| 5 | 2,614 of 2,904 (90.0 %) | 309 of 5,119 (6.0 %) |
| 10 | 2,285 (78.7 %) | 180 (3.5 %) |
| 20 | 557 (19.2 %) | 8 (0.16 %) |
| 21.0 — the zero-loss point | 74 (2.5 %) | 0 (0.00 %) |

The zero-loss point is set by a handful of program outliers and buys almost
nothing; margin 10 removes four fifths of the dump mass at a 3.5 % file-level
false discard.

This is a precision-versus-recall product decision of exactly the species ruling
T already assigns to the project owner ("the default posture — discard versus
demote-and-tag — is the owner's release decision"), and the same species as
candidate 2. So I am not choosing it. **My recommendation is margin 10 with the
posture set to demote-and-tag rather than discard**, which makes the 3.5 %
false-discard rate cost nothing at all: a demoted file's findings are reported
and marked, so a wrong demotion is visible and reversible, and the release
decision on the posture can then be taken on a measured report instead of in
advance. The curve above is the evidence either way.

---

## 4.6 State at pause (2026-09-01, `aac08f1`)

### What the two granted rulings turned into, and what the owner's third direction replaced

Requests 2 and 3 were granted as proposed. Applying them changed the shape of
the stage more than the numbers: **(b) moved a whole class of file out of the
estimator and into a rung of proof**, and a second such rung followed from the
same reasoning once the first one's measurement was in.

- **(b) foreign** — no `composer.json` above the file claims its namespace and
  none wires its directory. Its first run condemned a monorepo package's own
  test suite (`Shared\Cache\Tests\Unit`, declared only by that package's own
  manifest), which is why the rung consults the whole manifest chain rather than
  the root: this corpus has 33 package manifests under one root.
- **loaded by path** — not in any ruling; it follows from ruling T's own scope
  note ("a wired literal table — a seeder, a data-provider — is program text and
  stays"). The classifier was discarding `database/seeders/data/*.php`, which
  that sentence protects. Adding the signal as a *feature* did not work and the
  failure is worth recording: at −2.86 nats it was outvoted by eight features
  scoring +18 in total, because naive Bayes multiplies independent evidence.
  Made a rung instead, it settles the case outright — 11 seeder-data drops fell
  to 3. **Proof must not be a vote.**
- **(d) is superseded, and by something better.** The project owner's direction
  — *"a programmer is intelligent enough not to add thousands of files that
  should be ignored… a hidden dot-folder is not app code… that is the reasonable
  default, and there is a switch"* — replaced the hand label with a product rule:
  a directory whose name begins with a dot is not scanned by default (`91b1720`).
  That rule also retires the hand list of ten specific tool-cache directory
  names, which could never be complete because the names are user-configured.
  The trash tree now leaves the corpus at rung 2, by the product's own
  definition, and `--fishy-under` is no longer needed for the training labels:
  the ladder labels them.

### The numbers, on the final pin of this session

Pin `65799015ce0f93053a166b1b8d5dae00be89980520fdd7112c97c82106fd602c`,
4,847 files.

```
  rung 1 (raw)                      4847 files
  rung 4 (frozen K)                 2045 files      ← was 3,082 before the dot rule
  Stage 0, from rung 1              2431 files

  discarded unwired     156
  discarded shadowed     25
  discarded foreign      12
  discarded fishy      2223

  agreement with the target         1841
  dropped that the target keeps      204   (10.0 %)
  kept that the target drops         590   (the preset's policy excludes)

  FAIL  Stage 0 keeps at least 95% of the frozen definition — 204 of 2045 dropped
  PASS  no file holding a consensus-Y finding is discarded
  PASS  two runs over one tree agree
```

The classifier's trade at the owner's margin of 10: **97.3 % of the fishy class
discarded at a 3.19 % rate on the program class** (2,062 of 2,120 against 162 of
5,074).

Two of ruling T's three acceptance conditions pass. The third is the 95 % bar,
and **that bar is mine, not the ruling's** — ruling T says "approximately
reproduces". The 204 files are dominated by `config/*.php` and `lang/*/*.php`:
returned data arrays, which are the same shape as a dumped table by every
content feature there is. Under the granted demote-and-tag posture their findings
would still be reported and marked, so they are not lost; but the posture is not
plumbed through the reporting path yet, so the disagreement stands as measured
rather than as resolved. It is not weakened here, and it is not a number I should
restate on my own after seeing which way it landed.

### Also measured, and carried forward

- **The wall-clock gate has a much worse case than phpunit shows.** On 3,067
  files of this corpus: unified 267.6 s against Rabin-Karp 3.9 s and the token
  bag 2.4 s — roughly 42× the merged default, where the phpunit sweep at 2,500
  files reads 2.8×. Ruling U's bar is "≤ 1.5× at every size, flat-or-better with
  size", and the sweep it is measured on does not contain a case like this. This
  belongs to step 7 and is recorded now so it is not discovered there.
- The M3 worksheet's relocation (§4.4) is the fixture for ruling U's tracking
  metric and for step 5; it is on disk beside the snapshots, outside every repo.

### The exact next action

**Re-measure first, before any new code**, because the corpus moved four times
during this session and every number above is from a different pin than the one
before it:

1. `php bench/pin-snapshot.php pin <corpus> --out=<outside>` — then
   `php bench/triage.php train --dogfood=<corpus> --margin=10 --write` and
   `php bench/triage.php check --dogfood=<corpus> --worksheet=<relocated>`.
   Confirm the four rung counts and the 97.3 % / 3.19 % trade reproduce; if they
   do not, the drift is the finding.
2. Then the open item this session did not reach: `bench/triage.php check`'s
   third gate. Either the demote-and-tag posture is plumbed through
   `src/Triage/TriageResult.php` → the engine → the loggers so a demoted file's
   findings are reported and marked (which makes the 204 cost nothing and the
   bar meaningful), or the "approximately reproduces" bar is restated by ruling.
   The first is a real change and belongs in the landing sequence; the second is
   the auditor's.
3. Step 5 (the span-level discriminator) is then sized against what triage
   actually leaves, which is what the handoff asks for and is why it comes after.

Nothing in steps 6–8 has been started.

---

## 4.7 Resume (2026-09-02) — the baseline is intact, and the next action is gated on two inputs

The pause note in §4.6 was present and is followed exactly; nothing had to be
reconstructed from git log.

**State verified before any new work**, at `d78c61f`, working tree clean:

```
  vendor/bin/phpunit --no-coverage          OK (149 tests, 1034 assertions)
  vendor/bin/phpstan -c phpstan.neon        [OK] No errors
  vendor/bin/phpstan -c phpstan-bench.neon  [OK] No errors
```

So the session resumes on the same footing it paused on, and §4.6's numbers are
the last measured state rather than a state that has since decayed.

### Why no measurement has been taken yet

§4.6's exact next action is *"re-measure first, before any new code"* — the
corpus moved four times during the previous session, so every count in §4.6 is
from a different pin than the one before it. Both commands that action names
take the corpus root as an argument:

```
  php bench/pin-snapshot.php pin <corpus> --out=<outside>
  php bench/triage.php train --dogfood=<corpus> --margin=10 --write
  php bench/triage.php check --dogfood=<corpus> --worksheet=<relocated>
```

`bench/triage.php` documents the reason it cannot be defaulted (l. 49): *"The
dogfood root is an argument, never defaulted and never recorded."* That is
ruling P's discipline working as designed — the pin's location is not
discoverable from inside the repository, so it is asked for rather than guessed.
**Requested from the project owner; no dogfood number is taken until it is
given.** The public-corpus and in-repo gates above are unaffected and were run.

The already-trained model was deliberately *not* re-run without `--dogfood`:
training on the public priors alone would silently produce a different model
than the one §4.6 measured, which is a drift the re-measure exists to detect,
not to cause.

### The second gated input — §4.6's third-gate fork, measured before it is asked

§4.6 left `bench/triage.php check`'s third gate failing at 204 files and named
the fork: either demote-and-tag is plumbed through `TriageResult` → the engine →
the loggers, or the *"approximately reproduces"* bar is restated by ruling.
Before surfacing it, the size of the first branch was measured rather than
assumed:

```
  grep -rn --include='*.php' -e TriageResult -e 'new Stage0' src bench tests
    bench/triage.php:499
    bench/triage.php:585
```

**Stage 0 today has exactly one consumer, and it is the bench harness.** Nothing
in `src/` calls it; the engine and the loggers have never seen a `TriageResult`.
So branch one is not plumbing an existing posture through an existing path — it
is the whole of wiring Stage 0 into the product, which the handoff's step 4 does
place inside M4 ("the engine, the bench walker, and the pool all consume the same
stage, so step 1's walker fix is the interim and Stage 0 is its replacement"),
but whose *default posture* ruling T reserves: "Default posture (discard vs
demote-and-tag) is the owner's release decision, recorded before the flip."

That is the tension, and it is why this is asked rather than decided: the third
gate cannot be honestly scored without knowing which posture the number is
scored against, and the posture is not the executor's to pick.

## Ruling request 4 — the third gate is scored against a posture that has not been chosen, and the bar it fails is mine

**The situation.** Stage 0 drops 204 of the 2,045 files ruling K's frozen
definition keeps (10.0 %), against a 95 % retention bar. The 204 are dominated
by `config/*.php` and `lang/*/*.php` — returned data arrays, which are the same
shape as a dumped table under every content feature there is. Two facts about
that number, both recorded in §4.6 before the ask:

1. **The 95 % bar is the executor's, not the ruling's.** Ruling T's wording is
   *"approximately reproduces ruling K's frozen definition"*. 95 % was this
   session's own operationalization of "approximately", written before the
   measurement. It is not restated here after seeing where it landed — that is
   precisely the move §4.6 refused to make alone.
2. **Under demote-and-tag the 204 cost nothing.** A demoted file's findings are
   still reported and still marked; the retention question dissolves. Under
   discard they are 204 files of real coverage loss. The same measurement means
   two different things depending on a decision ruling T reserves to the owner.

**What is asked.** Not for the bar to be lowered. Three things, separable:

- **(a) To the project owner —** may Stage 0 be wired into `src/` with *both*
  postures implemented and selectable, the current `discard` behaviour left as
  the default and nothing flipped? This is preparation, not the release
  decision: it builds the instrument the decision needs, and step 4 requires it
  regardless, since the engine and the pool must consume the same stage as the
  bench walker. The flip itself stays the owner's, at release, on ruling U's
  evidence.
- **(b) To the auditor —** restate the third gate in posture-relative terms, so
  it measures something true under either choice: under demote-and-tag,
  retention is 100 % by construction and the gate is the tag's correctness;
  under discard, the retention percentage *is* the coverage cost, reported as
  the number the owner weighs at the flip rather than as a pass/fail the
  executor set for itself. That converts a failing self-imposed bar into the
  evidence the posture decision actually needs.
- **(c) If (b) is declined** and a hard retention bar is wanted under the
  discard posture, the auditor sets its value. The executor does not, having
  now seen the measurement.

**Recommendation:** (a) and (b). The honest reading of the 204 is that Stage 0's
classifier cannot separate a wired config table from a dumped one on content
alone — which is not a defect to be tuned away but exactly the case ruling T's
scope note hands to step 5's span-level tier ("a wired literal table — a seeder,
a data-provider — is program text and stays"). Demote-and-tag is the posture
that lets both tiers do their own job without the first one destroying evidence
the second one needs.

**Status: open. Awaiting the project owner on (a) and the auditor on (b)/(c).**

---

## 4.8 The re-measure (2026-09-02) — the pin is intact, the tree is not, and the drift is the finding

The project owner supplied the corpus root and one constraint with it: the
private tree is a **live project, read-only, and not to be treated as something
pinnable**. That constraint is honoured literally below, and it turned out to
change the answer rather than merely the procedure.

### What the pin instrument actually does to a live tree

`bench/pin-snapshot.php` is the hash-list half of ruling P, and its own header
records why that is the better half (l. 24–29): *"A frozen copy needs write
access to somewhere and answers 'what did it look like?'; a hash list needs only
reads and answers the question ruling P was actually recorded for — 'is this
still the tree the number came from?'"* Verified against the source rather than
assumed:

```
  grep -n "file_put_contents|copy(|mkdir(|unlink(|rename(" bench/pin-snapshot.php
    239:    if (file_put_contents($out, $header . $body) === false) {
```

One write in the whole tool, and it is the manifest — written outside this
repository, `chmod 0600`, refusing to be written inside it. **The corpus is
never read anything but read-only.** So the owner's constraint and ruling P are
not in conflict: no new pin was taken, and none was needed, because four
manifests from the previous session were already on disk beside the worksheets.

### The four pins, and which one §4.6 cites

```
  M4-snapshot.tsv        4813 files   78f7f104…   the ladder pin (the handoff's 4,813)
  M4-stage0.tsv          4895 files   f887203c…
  M4-stage0-accept.tsv   4857 files   81cd7623…
  M4-stage0-final.tsv    4847 files   65799015…   ← §4.6's pin
```

That is §4.6's "the corpus moved four times during this session", now visible as
four digests rather than a sentence.

### The live tree against §4.6's pin

```
  php bench/pin-snapshot.php verify <corpus> <M4-stage0-final.tsv>
    Snapshot verify — 4847 files pinned, 4810 present
      PASS  no files have appeared since the snapshot   — 0 added
      FAIL  no files have disappeared since the snapshot — 37 removed
      PASS  no pinned file has changed content          — 0 changed
    exit 1
```

Zero added, zero changed, thirty-seven removed — not the signature of a project
under development, and worth characterising rather than reporting as "drift":

```
  36  storage/framework/…
   1  bootstrap/cache/…
```

**Every removed file is regenerable framework cache**, in precisely the two
directories the ladder's rung 2 prunes under default excludes. The live tree
lost nothing that is program text.

### The four rung counts, reproduced

`check`, run against the committed model — the non-destructive half first, on
purpose, so the model that produced §4.6 was still in place while the tree was
being re-measured:

```
                                  §4.6 (4,847 pin)   now (live, 4,810)   delta
  rung 1 (raw)                          4847               4810           −37
  rung 4 (frozen K)                     2045               2045             0
  Stage 0, from rung 1                  2431               2394           −37

  discarded unwired                      156                157            +1
  discarded shadowed                      25                 25             0
  discarded foreign                       12                 12             0
  discarded fishy                       2223               2222            −1

  agreement with the target             1841               1840            −1
  dropped that the target keeps          204                205            +1
  kept that the target drops             590                554           −36
```

**Rung 4 is identical to the file.** The frozen definition — the thing every
acceptance claim is made against — did not move, which is the confirmation
§4.6's next action asked for. The 37 cache deletions land exactly where theory
says they must: on rung 1, on Stage 0's input, and on "kept that the target
drops" (Stage 0 was keeping those cache files; the preset was excluding them;
now they are gone from both, −36).

The one non-mechanical movement is **+1 unwired**, which carries the −1
agreement and the +1 dropped. It has a mechanism rather than a shrug: deleting
36 cache files removed references, and a file whose only referents were inside
that cache is, correctly, unwired now. The rung is doing what it is for.

### Why the model was *not* retrained, though §4.6's next action said to

`train --margin=10` was run **without** `--write`, and the trade it reports is
where the re-measure stops being a formality:

```
                    §4.6                     now
  fishy discarded   2062 of 2120  (97.3 %)   2061 of 2083  (98.9 %)
  program lost       162 of 5074  ( 3.19 %)   154 of 5074  ( 3.04 %)
```

Both numbers improved. **Neither improvement is real, and the second is
dangerous.** The fishy label set lost 37 members — the deleted cache files — and
the arithmetic says which ones:

```
  before:  2062 discarded of 2120   →  58 fishy files the classifier missed
  now:     2061 discarded of 2083   →  22 fishy files the classifier missed
```

Thirty-six of the thirty-seven departed files were among the **58 the classifier
was failing to catch**. The fishy-recall figure did not rise because the model
got better; it rose because the hardest examples of the class left the tree.
Retraining with `--write` would then bake counts learned from a corpus stripped
of its hardest positives into `FishinessModel.php`, and every metric would report
the instrument as improved while it was made weaker on exactly the class it
exists to catch. That is the ruling D lesson wearing new clothes, and §3's
standing prohibition — *"never weaken a gate to pass it"* — reads on it directly
even though nothing here was done deliberately.

The resolution is already in ruling T's own wording: the model's parameters are
counts over **a pinned, recorded label set**. The pinned label set is the 4,847
pin, which still exists and still contains those 36 files. The live tree is not
the label set and was never promoted to it. So:

- `src/Triage/FishinessModel.php` **stands unchanged**, trained on the pin.
- The live tree is verified against that pin and its delta declared, which is
  the whole of what ruling P asks and needs no new pin — honouring the owner's
  constraint exactly.
- No dogfood number in this packet is taken from a tree presented as pinned when
  it is not.

**The drift is the finding, and the finding is that it must not be trained on.**

---

## 4.9 The tracking metric for the Stage 0 tier — and what it says about step 5

Ruling U 2 requires the preserved 60-finding worksheet to be re-scored after
each tier lands. Stage 0 is a landed tier, so the metric is due, and it is not
gated on ruling request 4. Built as `bench/triage.php check --key=<file>`
(`3da0c4d`), a printed report rather than a `bcb_check()` gate — ruling U's own
sentence is that a projection *"is never a substitute for the final two-rater
pass"*, and a projection inside the gate summary is a number a session can move
by changing the tier that produced it.

### The instrument validates against a number it was not given

```
  before Stage 0    Y  17   N  33    precision 0.340
  after  Stage 0    Y  17   N  32    precision 0.347

  silenced by Stage 0     1 N  and    0 Y   (no consensus Y lost)
```

The **before** figure is `0.340`. M3's recorded unified precision, taken by a
different route on a different day, is `0.340`. The metric reconstructs it from
the worksheet alone, which is the strongest evidence available that the
re-scoring is measuring the thing it claims to.

### The honest reading: Stage 0 moves precision by 0.007

Stage 0 silences **one** false positive on this worksheet. That is not a
disappointment; it is the prediction §4.3 made in advance, arriving on schedule.
The dump/backup/trash mass Stage 0 exists to remove has **already left this
corpus** — the backup subtree was deleted before the session began, and the dot
rule (`91b1720`) took the cache trees out by the product's own definition. A
tier cannot subtract a family that is no longer present. The M3 arithmetic's
projected 0.83–0.93 was *"with both families silenced"*, and Stage 0 is the tier
for the family that is already gone.

### What is actually left, sized

The 33 consensus-N findings unified reports, by family:

```
  14   every file under database/seeders    the wired literal table
   6   other wired program text
  13   never relocated to a file            (all 13 are consensus N)
```

And the 17 consensus-Y findings, by where they live:

```
   all 17 under packages/*, spread across 7 packages (2-7 findings each)
```

*(The package names are deliberately not listed: a breakdown naming seven of a
closed tree's internal modules is a structural fingerprint of it, and the
analytic point below survives without them.)*

**Every consensus Y is in `packages/*`. Not one touches `database/seeders`.**
The dominant surviving false-positive family and the entire true-positive set do
not overlap at all — so the family is separable in principle, which is exactly
what step 5 needs to be true before it starts.

This is the sizing the handoff asked for ("measure after Stage 0 lands, so this
tier is sized against what triage actually leaves"), and it says step 5 carries
essentially the whole distance to ruling U's 0.80 bar. Projecting forward, and
labelled as projection rather than measurement:

```
  now                                                       0.347
  + step 5 silences the seeder-data family (14 N, 0 Y)      0.486
  + the 13 unrelocated N resolved and silenced too          0.773
```

Two things follow, both of which belong to step 5 rather than here:

1. **The 13 unrelocated findings are suppressing the projection, and all of
   them are N.** They are held as surviving by the conservative convention, so
   every one of them counts against precision on the strength of a relocation
   failure rather than a judgement. Relocating them is cheap measurement work
   and it moves the projected number more than any single discriminator will.
2. **Even with the seeder family fully silenced and every unrelocated finding
   resolved, the projection lands near 0.77, not above 0.80.** The remaining
   6 "other wired" N findings are then the margin. This is stated now, before
   step 5 is built, so the tier is not later credited with a bar it was never
   going to clear alone — and so that if the two-rater pass lands below 0.80,
   it was predicted rather than explained afterwards.

---

## 4.10 Owner ruling on the cache trees — already satisfied, and the check exposed a real defect

**Ruling (project owner, 2026-09-02):** `bootstrap/cache` and `storage/framework`
are ignored by default **at the product level**, not only under a preset or the
bench walker, with the same escape switch as the hidden-directory rule. Two
clarifications recorded with it: shipped default excludes are a product
convention like `vendor/` — overridable and documented — and are **not** the
ruling-K path-pattern ban, which governs classifier features and
measurement-time exclusions; and the packet is to record whether any ladder rung
count moves.

### Where they are pruned today — checked, not assumed

```
  src/Util/FileFinder.php:77   DEFAULT_EXCLUDED_PATHS = ['var/cache',
                                 'storage/framework', 'bootstrap/cache']
  src/Presets.php:36           the laravel preset additionally excludes all of 'storage'
  bench/lib.php:83             bcb_files() passes a hand list, with FileFinder's
                                 defaults ON underneath it
```

**Both named trees are already in the product's global default excludes**, and
the escape switch is already the shared one (`--no-default-excludes`,
`src/CLI/Options.php:66`). Demonstrated on the tree rather than read off the
constant:

```
  defaults ON :  2727 files, of which cache-tree  0
  defaults OFF: 17748 files, of which cache-tree 38
```

So the ruling is satisfied as shipped and **no code change is made** — a
CHANGELOG entry is not written for a change that does not exist. The one
divergence is noted rather than closed: the preset excludes the whole of
`storage`, the global default excludes only `storage/framework`, so
`storage/app` and `storage/logs` remain scanned outside the preset. That matches
the ruling as worded, which names `storage/framework` specifically.

### The rung counts — and the defect the confirmation found

The ruling asked for one line confirming the rungs do not move. The line is
better than that, because the live tree's cache regenerated between the two runs
and turned the confirmation into an experiment:

```
                          §4.6 (pin)   §4.8 (cache deleted)   §4.10 (cache regrown)
  rung 1 (raw)               4847            4810                    4840
  rung 4 (frozen K)          2045            2045                    2045
  discarded unwired           156             157                     156
  agreement                  1841            1840                    1841
  dropped that target keeps   204             205                     204
```

**Rung 4 is 2,045 in all three cache states.** The frozen definition is fully
insulated, exactly as the ruling predicted.

But the *unwired* rung is not, and the round trip proves the mechanism §4.8 could
only hypothesise: 156 → 157 → 156, tracking cache deletion and regeneration
precisely. The cause is an ordering defect:

> Stage 0 triages `ladder[1]`, the **raw** tree, so its symbol and reference
> graph is built over files the product itself would never scan — including the
> compiled cache blobs. A file whose only referents live inside
> `storage/framework` is therefore judged **wired**, on evidence drawn from a
> tree triage is about to discard.

The consequence is that one file's verdict depends on whether the developer
happened to have a warm cache. `two runs over one tree agree` still passes —
this is not non-determinism over a fixed tree — but two runs over the same
*project* in different cache states disagree, which is the property that
actually matters to a user.

This is the "proof must not be a vote" lesson in its ordering form: **evidence
must not be taken from files the stage itself discards.** The fix is not
obviously "start from rung 2" — ruling T's acceptance requires Stage 0 to be
pointed at the raw tree and reproduce rung 4 from rung 1, so it must see the
cache in order to triage it. What it must not do is *count* it as wiring. The
separation wanted is between what Stage 0 reads and what Stage 0 believes.

**Not fixed here.** It changes which files the wiring rung condemns, so it is a
design question under §3 rather than an implementation slip, and it is surfaced
rather than taken. It is small — one file on this corpus — and it will not stay
small on a corpus with a large warm cache.

---

## 4.11 Owner ruling — framework auto-detection (`7ad0891`)

**Ruling (project owner, 2026-09-02):** when the scanned tree is a Laravel app,
the preset's excludes apply by default — the user should not need
`--preset=laravel` to get the rule the tool already ships. Detection from the
tool's own evidence, not path guesses: `composer.json` requiring
`laravel/framework`, corroborated by artisan/bootstrap markers. Never silent;
`--no-preset` disables; explicit `--preset=` still overrides. Generalises to
other shipped presets where detection is equally unambiguous.

### The evidence rule, and the two halves held apart by fixture

`src/PresetDetection.php`. Both halves required, neither trusted alone:

1. a `composer.json` at or above the scan root **requires** the framework
   package — `require` only, never `require-dev`, because a package that *tests
   against* a framework is not built on it; and
2. that manifest's directory carries a structural marker (`artisan`,
   `bootstrap/app.php`).

`tests/fixtures/preset-detection/` is constructed to separate them: `app/`
declares and carries, `library/` declares in `require-dev` only, `marker-only/`
carries a file named `artisan` while requiring nothing. Only the first detects.
The walk-up case (`phpcpd app/` inside a project) is pinned too.

### The one design decision inside the ruling, and why it went this way

The ruling says *the preset's excludes* apply. The preset also carries `paths`
(`app routes database config`), and detection deliberately does **not** seed
them. Detection has established what the project *is*, which justifies skipping
the framework's scratch trees; it has not established that the user meant to
scan four directories instead of the one they typed. Silently narrowing a scan
to a quarter of a monorepo on the strength of an inferred framework is the kind
of default that makes a tool untrustworthy. `--preset=laravel` remains how a
user asks for the full treatment, and it still seeds paths.

The asymmetry is recorded rather than hidden: **detected laravel ≠
`--preset=laravel`**. The first is the excludes; the second is the whole preset.

### The confirming line the ruling asked for

Ruling K's frozen definition already included the preset (the ladder's rung 3),
so no rung may move. Re-run after the change, against the run in §4.10:

```
  rung 1 (raw) 4840 · rung 4 (frozen K) 2045 · Stage 0 2425
  unwired 156 · shadowed 25 · foreign 12 · fishy 2222
  agreement 1841 · dropped that the target keeps 204 · kept that the target drops 584
```

**Every number is identical to the pre-change run.** The ladder builds its rungs
through the bench walker rather than through `Settings::resolve()`, so detection
cannot reach it — the invariance is structural, and now measured as well.

### The posture note, for the release comparisons

What changes is the **bare-invocation default**, which under the posture-relative
gate rule is a posture note rather than a gate movement:

```
  bench/corpus/firefly-iii   bare invocation  1445 → 1291 files  (−154, −10.7 %)
  bench/corpus/phpunit       unchanged (no manifest evidence: not a Laravel app)
  bench/corpus/php-parser    unchanged
  this repository            unchanged (does not detect)
```

firefly-iii is the only public corpus that detects. Standing bench gates read
their file lists from `bcb_gate_files()`, which calls `FileFinder` directly and
never resolves settings, so **no recorded gate number moves**. But any *future*
comparison that shells out to the CLI on firefly-iii will see 1,291 files where
an earlier one saw 1,445, and that is the release-comparison hazard this note
exists to pre-empt.

### On the standing rules

Recorded because the ruling anticipated the collision: shipped default excludes
are a product convention like `vendor/` — overridable, documented, announced —
and are **not** ruling K's path-pattern ban, which governs classifier features
and measurement-time exclusions. Nothing in `src/Triage/` gained a path pattern,
and the fishiness classifier's feature set is untouched.


---

## 4.12 Resume (2026-09-02, second) — no pause note, and what the reconstruction found

### The pause note was absent, and this is the record of that

The handoff's resume procedure is to find the packet's "state at pause" note and
continue from the exact next action it names, reconstructing from `git log` if it
is absent **and recording that it was absent**. It was absent. §4.6 is a state-at-
pause note, but it belongs to the *previous* pause and §4.7 already consumed it
("The pause note in §4.6 was present and is followed exactly"). The session that
wrote §4.8–§4.11 stopped without writing one.

Reconstructed from `git log`, and verified against the working tree rather than
inferred from commit subjects:

```
  49e9e0c  M4 packet: the cache-tree ruling …          §4.10, the last packet section
  7ad0891  A framework preset applies itself …         the auto-detection ruling, implemented
  2809d71  M4 packet: the framework-detection ruling…  §4.11
```

**Two of those three commits landed after this session had already started.** At
09:39 the working tree held `src/PresetDetection.php` untracked and three modified
`src/CLI/` files, with no tests, CHANGELOG or README; by 09:47 the same work was
committed complete, with fixtures, a nine-case test, a CHANGELOG entry and §4.11.
Recorded because it is the kind of thing that silently invalidates a reconstruction:
the state this session reasoned about for its first eight minutes is not the state
it is now working in. `git status` is clean, `git reflog` accounts for every commit
in order, and nothing has changed since. Whatever produced those commits — the prior
session flushing, or the owner — is not this session, and the packet should not
pretend otherwise.

One consequence, since it would otherwise look like an unexplained number: the test
suite reads **149 → 162 tests**, not through any regression, but as
149 + 9 (`PresetDetectionTest`, arriving with `7ad0891`) + 4 (ruling V, below).
The 1,034 → 1,035 assertion step on the *unchanged* suite is the `--no-preset`
option definition passing through `SettingsTest`'s drift guard, which folds every
option `Options` defines; it is one assertion per option and not a behaviour change.

### State verified before any new work

At `49e9e0c`, and again at `2809d71` once the concurrent commits had landed:

```
  vendor/bin/phpunit --no-coverage                    OK (162 tests, 1062 assertions)
  vendor/bin/phpstan analyse -c phpstan.neon          [OK] No errors
  vendor/bin/phpstan analyse -c phpstan-bench.neon    [OK] No errors
```

### Four rulings were in force and none of them was written down

The resume briefing names ruling V, ruling T's role-feature amendment, the
posture-relative gate rule, and the framework auto-detection ruling as recorded in
the plan's §2 M4. Three were in neither the plan nor the packet, and ruling T's
amendment was not in the plan's ruling T. `grep` over both files returns nothing
for "Ruling V", "posture-relative", "auto-detect" or "role feature", and no commit
between M4 open and `49e9e0c` adds them.

They are now recorded in the plan (`3157a2a`), following `1fa8ec6` — the commit
that wrote ruling U and ruling R's integrated shape into the plan — as the closest
precedent for how a ruling reaches the durable record. **The wording is this
executor's transcription of the briefing, not a verbatim ruling text, and is
flagged as such in the plan and here so the auditor can correct it where it
drifts.** The alternative was to execute against four rulings a later session could
not find, which is the failure interpretation.md's rule 8 names: an interpretation
nobody can find is a precedent nobody can follow.

### Interpretations

Recorded under interpretation.md rule 8. Rule 0's gate was applied first in every
case: where an instruction met no contradicting fact, it was applied as written and
appears nowhere below.

> **The governing document was not at the path that governs.** The instruction was
> to read `docs/research/interpretation.md` first; no such file exists in the
> repository, in its history, or in any stash. The plain reading fails on a false
> premise. The system reading resolves it: the milestone's own record says the file
> is committed *after* M4 closes, so during M4 it is expected to live outside the
> repository, and a copy was found at a working path outside every repo. Rule 2's
> first clause — purpose beats words when the words assumed a fact that is false —
> and the instruction's purpose is that the document govern the session, which
> reading the copy satisfies. Cheap either way and fully reversible; the risk of
> reading the wrong copy is bounded by the file being self-describing. No precedent
> existed; this one is now it.

> **The owner chose to re-pin a tree an earlier owner constraint called
> unpinnable.** Asked where dogfood numbers should come from, the owner selected
> "re-pin from the live tree first". §4.8 records an owner constraint from the same
> day: the private tree is *"a live project, read-only, and not to be treated as
> something pinnable"*, and §4.8 honoured it by taking no new pin. Named mismatch,
> so the gate opens. It closes on a fact §4.8 itself established rather than on a
> preference: `bench/pin-snapshot.php` is the hash-list half of ruling P and
> performs exactly one write in the whole tool — the manifest, outside this
> repository. The constraint was written against *freezing or copying* a live
> project, which the tool does not do; §4.8's own words are that no new pin "was
> needed", not that none was permitted. Words and purpose therefore agree once the
> instrument is read, and the readings do not conflict. Cost asymmetry, stated as
> rule 5 requires: a superfluous pin costs one manifest outside every repo that can
> be deleted, while measuring against a pin the tree has already diverged from
> (37 files gone at §4.8) puts a known mismatch under every number. Precedent
> followed: §4.8's reading of the same constraint, distinguished only in that it
> asked whether a pin was *needed* and this asks whether one is *allowed*.

> **Ruling T's role features arrived without a feature list.** The briefing names
> role features as ruling T's amendment; ruling T in the plan has no such features,
> and no ruling text specifies them. Reading the amendment as authorizing a
> *feature class* rather than a named list is the system reading: ruling T's
> existing list ends in an ellipsis, its binding constraint is "content- and
> wiring-derived only, never path patterns", and every other feature in the stage
> was derived by the executor with its log-odds recorded in the packet as the
> derivation §3 requires. Read that way the amendment adds no new authority and
> takes none away, which is why it is not escalated under rule 7: the ruling
> decides what kind of evidence is admissible, and the executor derives and records
> the rest, exactly as for the eight features already shipped.

### Ruling V, landed (`3e95615`)

The §4.10 defect is closed. The mechanism is the ordering, not a filter after the
fact: non-witnesses are removed **before** any evidence is gathered, so the symbol
collector, the include index and the orphan detector never see them at all.

The acceptance is a constructed fixture rather than the corpus, and deliberately
so — the corpus reproduces the defect only when its cache happens to be warm,
which is the whole complaint. `tests/fixtures/derived/project/` holds one class
named by nothing but a compiled container blob under `storage/framework`, one
class referenced from program text, and one bootstrap. Measured both ways on the
same tree, with the wiring rungs only (`classify: false`, so a proof about
evidence does not depend on a trained model):

```
  witnesses = null (the defect)      kept: CachedOnly, Wired, container.php
  witnesses = rung 2 (ruling V)      kept: Wired
                                     drop: container.php  [derived]
                                           CachedOnly     [unwired]
                                           Boot           [unwired]
```

`Boot` is unwired under both, which is the control: the rung still behaves
normally, and only the cache-witnessed verdict moves.

**Not yet measured on the corpus.** Ruling V changes which files the wiring rung
condemns, so the four rung counts and the 97.3 % / 3.19 % trade must be re-taken
before any of §4.6's numbers are cited again, and the specific thing to check is
that the 156 → 157 → 156 oscillation is gone under cache deletion and
regeneration. That measurement is gated on the corpus root, which ruling P forbids
defaulting or recording; it is requested from the owner and no dogfood number is
taken until it is given. The public-corpus gates and the full suite are unaffected
and were run above.
---

## 4.12 Rulings V, (b) and (a) — acceptance measured, and one correction

Ruling V landed as `3e95615` (mechanism and fixture) on top of `3157a2a`. This
section records its acceptance on the real corpus, a prediction of mine that was
wrong, and a consequence nobody asked for that is the most interesting number in
the milestone.

### A prediction I put on record and got wrong

Before measuring I predicted the unwired rung would "settle at 157 in every cache
state" — reasoning that the file wired only by a cache blob would join the 156.
The measured value is **153**. The reasoning was wrong about *which set the rung
counts over*: under ruling V the unwired rung runs over rung 2, so rung-1-only
files are never evaluated for orphanhood at all — they are already discarded as
derived, and ruling V's own words are that they "are read solely for their own
classification". Three files I expected to be counted are no longer candidates.
The direction was right and the number was not.

### Acceptance (i) — the three-state experiment, run without touching the tree

The owner's tree cannot be put into a cold state to order, so the experiment is
run as a projection over the same pinned reading: Stage 0 over rung 1 as it
stands ("warm"), and over rung 1 with every framework-cache file removed
("cold" — what a fresh checkout is), both with witnesses restricted to rung 2.

```
  warm  rung1=4840  kept=2377  derived=2113  unwired=153  shadowed=25  foreign=11  fishy=161
  cold  rung1=4802  kept=2377  derived=2075  unwired=153  shadowed=25  foreign=11  fishy=161
```

`rung1` and `derived` differ by exactly the 38 cache files. **Every rung that
judges is identical, and so is the kept set.** The 156 → 157 → 156 oscillation
§4.10 demonstrated is gone, and it is gone structurally rather than by
coincidence: a cache file is removed before the graph is built, so it cannot be
evidence whatever state it is in. Acceptance (ii) — the constructed fixture —
landed with the ruling and pins this permanently; (iii) two-runs-agree still
passes.

### The consequence nobody asked for: the classifier's share collapsed

```
                       before ruling V     after
  discarded derived            —            2113
  discarded unwired           156            153
  discarded shadowed           25             25
  discarded foreign            12             11
  discarded fishy            2222            161      ← −93 %
```

**The fishiness classifier was doing the default excludes' job.** 2,222 discards
fell to 161 once a rung that *proves* its case ran first: roughly 93 % of what
the estimator was condemning is now condemned by the product's own definition of
program text, with a reason a reader can check, and the estimator is left with
the 161-file residual that is genuinely its question.

This is the ordering principle of §4.6 arriving a third time and paying the most
it has paid yet — first "proof must not be a vote" (the loaded-by-path rung),
then "evidence must not come from what you discard" (ruling V), and now the
measurement that says how much of the estimator's apparent competence was really
a proof tier that had not been written. It also means ruling T's margin-10 trade
(97.3 % / 3.19 %) is now a statement about a much smaller and much harder
population, and should not be carried forward as if it described the same task.
That re-derivation belongs with the label set, not here.

### Ruling (b) — the posture-relative gate rule, recorded

Ruling request 4 (b) is granted, in a stronger and more general form than asked:

> Gates compare engines over **one stated corpus**; posture differences are
> corpus lines, never speed or detection deltas. The 1.5× wall-clock bar binds on
> the **same-set** comparison — both pipelines over the shipped posture's triaged
> set — with the untriaged same-set ratio and the as-shipped end-to-end
> comparison reported alongside, labelled. The detection bar runs over the
> triaged corpus; any 1.4-reported location that triage excluded is counted and
> listed as **"excluded by triage, recoverable via the flag"** — release
> evidence, not a detection miss.

Three things this settles, recorded so step 7 does not relitigate them:

1. **§4.6's third gate is no longer a pass/fail the executor set for itself.**
   The 205 files it names are a corpus line under this rule. They are reported as
   what they are — the coverage difference between the frozen definition and the
   shipped posture — and the owner weighs them at the flip.
2. **The 42× number from §4.6 is not the wall-clock bar's measurement.** That
   figure compared unified over one set against the merged default over another;
   under this rule the bar binds on both pipelines over the *same* triaged set,
   with the other two ratios reported beside it and labelled. Step 7 measures all
   three rather than choosing the flattering one.
3. **The firefly posture note (§4.11) is exactly a "corpus line"** and is already
   in the form this rule requires.

### Ruling (a) — granted

Stage 0 is to be wired into `src/` with both postures selectable, `discard` the
default, nothing else flipped. That is the next work in this session and is
recorded here before it starts, so the packet shows the authority it was done
under rather than asserting it afterwards.

### Ruling (a) as landed, and the owner's grant on the classifier

`4533c9a` wired Stage 0 into `src/`; `b94129e` corrected what it runs by default.

```bash
phpcpd --triage                 # the rungs that prove
phpcpd --triage-classifier      # also the rung that estimates
phpcpd --triage-posture=demote  # scan it anyway, and say what triage doubts
```

Measured on firefly-iii, the only public corpus with a framework's shape:

```
  bare run                        1291 files   398 findings   ← unchanged
  --triage-posture=demote         1291 files   398 findings   + the triage report
  --triage                         850 files   168 findings   (441 removed, all unwired)
  --triage --triage-classifier     816 files   166 findings   (475 removed)
```

Two decisions inside the grant, both recorded because neither was spelled out:

1. **Triage is off by default.** The grant says "wire it in, both postures
   selectable, discard as default, nothing else flipped". Turning triage *on* is
   itself a flip — it takes a third of the files out of a scan — so it is not
   taken. `discard` is the default *posture*, which is what the grant fixes; the
   stage still has to be asked for.
2. **The classifier is opt-in, per the owner's grant on 4(a).** Four rungs prove
   their case from a manifest, a symbol table, or the project's own default
   excludes. The fishiness classifier estimates, at ~3 % loss on the program
   class. The measurement in §4.12 is what makes withholding it cheap: with the
   proof rungs running first it accounts for 34 of 475 removals and 2 of 168
   findings here — nearly all of its apparent value was the proof tier's.

**Recorded rather than implied:** `demote`'s tag is at file-list granularity. The
demoted files are named with their evidence under `--explain`, but individual
findings are not yet marked inline in each output format. That is the remaining
piece of the demote-and-tag posture, and it is the piece ruling request 4 said
would make the 205-file disagreement cost nothing.

Ruling V is honoured at the seam: an ordinary run's file list *is* the witness
set, but under `--no-default-excludes` it is not, so the witness set is walked
separately rather than letting a compiled cache vouch for a dead class.

### A standing-rule breach found and fixed

`git grep` for the corpus name across the tree returned one hit: the packet
quoted the project owner naming it, in a line introduced by `da0c808`
(2026-09-01, an earlier session). The quote is redacted to `[the corpus]`, which
costs the sentence nothing — its content is about liveness, not identity.

**The name remains in git history at `da0c808`.** Removing it means rewriting
published history, which is destructive and is the owner's call, not the
executor's. Surfaced here rather than done. A `git grep` over the working tree is
now clean, and this check is worth adding to the stop-point routine: the rule has
been in force all milestone and a violation still survived a day.

---

## 4.13 The classifier re-derived on the post-rung population — and the case for dropping it

**Ruling (project owner, 2026-09-02), replacing the opt-in decision:** posture
follows epistemics. Proof rungs discard by default. The classifier runs by
default **in demote mode** — findings in files it scores fishy are tagged with
the score and top log-odds features, counted, never removed; discard-by-
classifier is opt-in. Re-derive the trade on the post-rung population; the stale
margin-10 numbers are not to be cited. Pre-register graduation criteria. If the
estimator buys ~nothing even as a label, surface "drop it, ship proof rungs
only" as an option, with the numbers.

Implemented in `49fe386`; the re-derivation instrument in `4de6936`.

### The re-derivation, and why the old curve had to go

The label set defines *fishy* as rung 1 minus rung 2. Ruling V's derived rung now
discards exactly that set, by proof, before the classifier is consulted. So the
old curve was measured over a population the classifier is no longer shown.
Measured rather than argued:

```
  proof-rung survivors                      2538
  of those, labelled fishy                     0   (was 2113 before the rungs ran)
  of those, labelled program                1972   (was 5074)
  of those, carrying no label at all         566
```

**Not one fishy-labelled file survives the proof rungs.** There is no trade to
re-derive, because there are no true positives left to trade against. On the
labelled part of its own population the classifier cannot make a correct
discard — every discard it makes there is a false one, and the only question is
how many. The margin-10 figures (97.3 % / 3.19 %, and the later 98.9 % / 3.04 %)
describe the pre-rung world and **are not cited again in this packet**.

### What it actually discards, characterised

The 161 files it condemns on the post-rung population:

```
  inside the preset's scanned corpus (rung 3)   161   (100 %)
  outside it, policy-excluded anyway              0

  where they live:
    packages/…/src        116        lang/en              4
    packages/…/Config       7        packages/…/config    4
    packages/…/Resources    5        database/seeders     3
```

**All 161 are inside the corpus the tool would actually scan, and 116 are under a
package's `src/`.** Read against §4.9 — where every consensus-Y finding lives in
`packages/*` and none in `database/seeders` — this is the estimator reaching
into precisely the tree the true positives come from, with no label in its
training set supporting a single one of those calls.

### Pre-registered graduation criteria

Recorded now, before any attempt to meet them, so they cannot be restated
afterwards. `--triage-classifier-discard` may become a default only when both
hold on a pinned snapshot:

1. **zero** discards of files carrying a consensus-Y finding; and
2. a measured **~zero** false-discard rate on wired program text at the shipped
   margin.

Neither is met today. Criterion 2 is not merely unmet but unmeasurable in the
supporting direction: with zero fishy labels in the population, every measurable
discard falls on program-labelled or unlabelled files.

### The option the ruling asked for: drop it, ship proof rungs only

Surfaced as instructed, with the numbers, as an option and not a decision.

```
  posture                              firefly-iii files   findings
  bare run                                        1291        398
  proof rungs only (today's default)               850        168
  proof rungs + classifier discarding              816        166
```

**The estimator's entire marginal contribution is 34 files and 2 findings** —
and on the private corpus, 161 files it cannot justify from its labels. The
argument for dropping it outright:

- it has **no true positives left to find**: its whole positive class is now
  removed by proof, upstream of it;
- what it removes instead is program text under `src/`, which is where the
  true positives are;
- the four proof rungs each cite evidence a reader can check; the classifier is
  the only part of Stage 0 that cannot be argued with on its merits, and it is
  now also the only part with nothing left to do;
- removing it deletes a trained model, its label set, its margin, and the
  ruling-T machinery that maintains them — a large reduction in what the release
  has to justify, and it retires the last constant in the stage.

The argument against, stated fairly: as a **tag** it costs nothing and it is
pointing at a real class — `config/*.php` and `lang/*.php`, the same class as
§4.6's 204/205 disagreement. A tag that says "this is a data table" is useful to
a reader even when removing the file would not be. That is an argument for
keeping it as a *label*, which is exactly what the current posture does, and it
is a weaker claim than the one the model was built to support.

**Recommendation: keep it as a tag for now, and put its removal on the release
agenda rather than deciding it here.** The tag posture makes it harmless, the
next measurement that matters is step 5's, and step 5 targets the same class from
the span side — if the span-level tier names a data-provider return correctly,
the file-level estimator has no remaining claim at all. That is the natural
moment to drop it, with better evidence than exists today.

---

## 4.14 History rewritten to remove the corpus name (owner-authorised)

§4.13's breach was redacted in the working tree; the name still sat in history at
`323965e`. The owner's condition was a single fact — had anything at or after
that commit reached a remote?

```
  git remote -v                      (no output — no remote is configured)
  git branch -r --contains 323965e   (no output)
  .git/refs/remotes                  does not exist
```

**Nothing has ever been pushed.** So the rewrite was taken now, as instructed,
at the only price it will ever be this low: no force-push, no coordination, no
other clone to reconcile.

### What was rewritten, and what was checked first

The name occurred **once**, on line 916 of this packet, inside a quoted owner
statement. Established before touching anything:

```
  in any commit message?                 no (0 matches across all messages)
  in any other tracked file, ever?       no (git log --all -S: only this file)
  commits in range 323965e^..HEAD        21
  bench/corpus tracked?                  no (0 files — the rewrite is cheap)
```

`git filter-branch --index-filter` over `323965e^..HEAD`, substituting the name
for `[the corpus]` in this file alone. An `--index-filter` rather than a
`--tree-filter` because it rewrites the blob without checking out 21 trees, and
because it touches exactly one path and can affect nothing else.

Then the parts that actually make a name *gone* rather than merely unreferenced:

```
  rm -rf .git/refs/original
  git reflog expire --expire=now --all
  git gc --prune=now
```

**No backup ref or tag was kept**, deliberately: a backup of this history is a
copy of the name, and would have defeated the exercise. The change is a pure
one-token substitution in one file, so there is nothing to restore that the
rewrite did not preserve.

### Verification

```
  git log --all -S"<name>"                    (no output)
  every reachable blob, scanned for the name   0 matches
  git rev-list --count HEAD                    83 (unchanged)
  vendor/bin/phpunit                           OK (166 tests, 1076 assertions)
  working tree                                 clean
```

### The hash mapping

Every commit from the breach forward has a new hash. The packet's own citations
have been rewritten to the new ones; this table is what makes that auditable, and
what any external note referring to an old hash needs.

```
  323965e  ->  da0c808
  e6ca7c8  ->  91b1720
  c8f5de5  ->  aac08f1
  ac6ae04  ->  d78c61f
  8c7d750  ->  28d1bfd
  0b4120f  ->  3da0c4d
  73968e4  ->  ccbec05
  607e6a3  ->  f235456
  08aa66c  ->  49e9e0c
  4f350e1  ->  7ad0891
  b17449c  ->  2809d71
  4af8604  ->  3157a2a
  07759c3  ->  3e95615
  8f28c3b  ->  c3823e6
  4018e34  ->  5b5300c
  a3835c1  ->  4533c9a
  73c14f0  ->  b94129e
  3779bbd  ->  98e04a7
  802cd10  ->  4de6936
  3c0d20d  ->  49fe386
  9b47d86  ->  41b25f5
```

Commits before `323965e` are untouched and keep their hashes (`1a861ca` and
earlier). Citations inside the *rewritten* history still name pre-rewrite hashes,
which is unavoidable and is what this table is for.

### The process note

The rule was in force for the whole milestone and the breach still survived a
day, in a packet written to record discipline. The check that found it —
`git grep` for the corpus name across the tree — cost nothing and is now part of
the stop-point routine. A rule with no cheap test is a rule that gets checked
when someone remembers to.


---

## 5. Step 5 — the span-level discriminator

### 5.1 The 13 unscorable findings, relocated — and the disposition record

Step 5 opens on §4.9's first observation: 13 of the 50 consensus-labelled unified
findings had never been relocated to a file, were held as surviving by the
conservative convention, and were therefore counting against precision *on the
strength of a relocation failure rather than a judgement*. That is measurement
debt, and it is paid before any discriminator is designed.

**The method is matching, never judgement**, and it is now a committed instrument
(`bench/relocate-worksheet.php`) rather than a scratch script, because the
acceptance fixture for both ruling T and step 5 depends on it and an auditor has
to be able to re-run it. Two rules, in order:

1. **Block match.** `bench/audit-precision.php` writes each site's excerpt as a
   *contiguous run of source lines beginning at a recorded start line*. So the
   run is searched for in the pinned tree: found at the recorded line it is
   `exact`, found at another offset it is `moved`.
2. **Digest propagation.** The path salt is fixed for the length of one pooling
   run, so within one worksheet two sites carrying the same digest are two sites
   *in the same file*. A digest that any of its sites resolved therefore resolves
   the rest — applied only where every resolved site of that digest agrees on one
   file, a disagreement being a digest collision and reported rather than
   resolved. No collision occurred.

Candidates are ruling P's manifest entries that are still present **and still
hash to the pinned value**; relocating into a drifted tree would answer a
question about today's corpus with verdicts rated against a different one.

#### The pin, checked first

```
  php bench/pin-snapshot.php verify <corpus> <the §4.6 pin>
    4847 files pinned, 4842 present
    FAIL  2 added   FAIL  7 removed   PASS  0 changed
```

Every one of the nine is regenerable framework cache — six compiled views and one
config cache removed, two compiled views added. **Zero pinned files changed
content**, so the tree the worksheet is being relocated into is, in program text,
the tree it was rated against. (The two remaining manifest lines that this
session's own diff showed as absent are extensionless CLI entry points that a
`.php`-only walk does not see; both are present.)

#### The result

```
  pinned tree: 4840 files usable (7 missing, 0 changed — excluded)
  sites 131 — exact 126, moved 3, several 0, unmatched 2; digest-propagated 1
  sites still unresolved: 1  (finding 040, site 1)
  findings relocated: 60 of 60
  consensus: Y 24, N 33
```

**All 60 findings now name files, against 47 before**; 24 Y + 33 N = 57 consensus
labels, matching the M3 record to the label. The single unresolved site belongs
to a finding its other site relocates.

Why the earlier pass stalled is worth recording, because it is a lesson about the
instrument rather than about the corpus. §4.4's method searched for *a site's most
distinctive excerpt line*. In a file that is one enormous literal table, no line
is distinctive — the same row shape recurs hundreds of times — so 39 sites came
back ambiguous or unfound. Matching the whole excerpt run at its recorded offset
has no such failure mode, and it is also strictly more conservative.

#### What relocation flipped: nothing, and that is the finding

Ruling U's tracking metric, re-run on the relocated worksheet against the same
Stage 0:

```
                        before relocation      after relocation
  unscorable                    13                      0
  before Stage 0        Y 17  N 33  0.340      Y 17  N 33  0.340
  after  Stage 0        Y 17  N 32  0.347      Y 17  N 32  0.347
  silenced                1 N, 0 Y               1 N (040), 0 Y
```

**Not one finding changed disposition.** All 13 relocate to a single seeder data
table, which Stage 0 correctly keeps — it is wired program text, exactly as
ruling T's scope note says it must be. So they were surviving before by
convention and are surviving now by measurement, which is the same number resting
on a very different footing: the 13 are no longer a hole in the instrument, they
are 13 members of the family step 5 exists to address.

Four already-relocated findings had their file sets *narrowed* rather than
changed — 011, 012, 022 and 040 previously listed every file in which their
excerpt's distinctive line occurred, which for a clone of five sibling classes is
five files for a two-site finding. Block matching attributes each site to the one
file whose lines it actually is. Dispositions again unchanged; the earlier lists
were over-attribution, not error.

#### The residual, re-sized on the relocated worksheet

```
  17  consensus Y            all outside the data-table families
  27  consensus N            wired literal tables under database/seeders/*
   6  consensus N            other wired program text (1 of which, 040, Stage 0 already silences)
```

The seeder family is now **27 of the 32 surviving false positives**, up from 14
before relocation, and the projection §4.9 recorded reconstructs exactly:
silencing that family alone leaves 17 Y against 5 N — **0.773**, the number §4.9
predicted before the relocation was done. That is a projection confirming a
projection, not a measurement; its value is that step 5's target is now sized
precisely and its ceiling stated in advance.

### 5.2 The discriminator — what a span *is*, and why identity rather than literalness

Ruling H left one avenue and closed three. The closed three (anchor multiplicity,
tokens-per-line sparseness, logic share) were all **statistics of a span**, each
needing a threshold, and all three died on the same counter-example: php-parser's
`Php7.php` / `Php8.php` action tables are as literal as any config file *and* are
the corpus's flagship true positive, because whatever regenerates one regenerates
the other. Any cut on "how literal is this span" removes them along with the noise.

The surviving question is not how literal a span is but **what it is**:

> Are these two spans two runs of elements of *one and the same* pure literal array?

Identity is what carries the separation, and it is what the counter-example
actually turns on. Two *different* tables in two files are a real finding — a
change to one has to be made to the other, which is the rating rubric's own
definition — and the rule never touches them. A table matching **itself** is the
opposite: its second run is not a copy anyone could delete, it is the regularity
that makes the file a table.

**Both conditions are membership tests, so no constant enters.** A frame is a
literal array when it was opened by `array(` or by a `[` in value position; it is
*pure* when every token inside it, at any depth, is data — a number, a quoted
string, a bare name, `=>`, a sign, or the punctuation that holds an array
together. A variable, a `::`, a `->`, an interpolation, a closure or a **call
parenthesis** disqualifies it, and the disqualification propagates outward.
"Contains no computation" is a definition of *literal*, not a cut chosen on a
curve — which is precisely the difference between this and the three that were
refuted. If it were a threshold it would need a derivation, and the instruction
was to stop and surface rather than invent one.

**Where it lives.** `src/Facts/RegionStructure.php` — the facts layer ruling R's
integrated shape also names ("wiring, role, region-structure annotations computed
once"), so the annotation is computed once and read by both tiers rather than
twice under two definitions. The engine consults it in `postProcess()`, before
`classes()`, and reads only files that carry a same-file candidate.

**The one property that had to be pinned by test.** The class describes positions
in the encoder's *significant-token* numbering, which it reproduces from its own
tokenizer pass. An off-by-one there would not fail loudly — it would shift every
span in the file by one token and answer confidently — so
`RegionStructureTest::itNumbersTokensExactlyAsTheEncoderDoes` asserts the two
counts agree over every file in `src/`, and the count was checked over ~4,200
files across all five corpora while the rule was being developed: zero
misalignments.

### 5.3 Acceptance, item by item

```
  every consensus data-table N silenced      27 of 27      (001 002 005 006 009 013 028
                                                            031 032 033 034 037 041 043
                                                            045 047 048 049 050 051 053
                                                            054 055 056 057 059 060)
  zero consensus Y lost                       0 of 24 lost — and 0 of the 17 unified Y
  symfony-string's Unicode pair silent        silent (unchanged; ruling B's floor still owns it)
  Php7.php's action table reported            Php7.php:381 ↔ Php8.php:383, 2,536 lines, reported
  superset gates unchanged or explained       php-parser 3/3, phpunit 19 unmet items — both unchanged
```

The 27 are exactly the seeder-family findings §5.1 sized, and the 24 include the
seven that only the token bag reported, so the "zero lost" claim is not scoped to
the engine under test.

Every other standing gate, re-run:

```
  vendor/bin/phpunit                                   248/248  (166 + the new tier's)
  vendor/bin/phpstan analyse                           no errors
  vendor/bin/phpstan analyse -c phpstan-bench.neon     no errors
  php bench/self-test.php                              24/24
  php bench/check-chaining.php                         2/2
  php bench/check-determinism.php php-parser --algorithm=unified   4/4
  php bench/check-incremental.php php-parser           8/8
  php bench/check-superset.php php-parser              3/3
  php bench/check-superset.php phpunit                 FAIL — 19 items (unchanged, §2.4)
  php bench/run-recall.php --sample=40                 2/2 — guaranteed region 295/295
```

**Clone-count effects, each one opened and named rather than reported as a
delta:**

```
  php-parser        82 →  82    no change
  symfony/string    24 →  23    an inflector's rule table matching itself
  phpunit          599 → 597    a const table of deprecated ini settings, twice
  firefly-iii      604 → 594    all ten in one config table, matching itself
```

Four files, four literal tables repeating themselves. Nothing else moved.

The tracking metric, both tiers, on the pinned corpus:

```
  before Stage 0    Y  17   N  33    precision 0.340
  after  Stage 0    Y  17   N  32    precision 0.347
  + span tier       Y  17   N   5    precision 0.773

  silenced by Stage 0                            1 N (040), 0 Y
  silenced by the span tier, on top of Stage 0  27 N,       0 Y
```

**0.773 is the number §4.9 projected before the tier existed**, including its
prediction that the tier would land *below* ruling U's 0.80 bar and that the five
remaining false positives would be the margin. Both halves arrived. This is a
projection, not a measurement; step 7's two-rater pass is what decides the bar.

### 5.4 The classifier-removal evidence — and it points the other way

§4.13 recorded the expectation plainly: *"if the span-level tier names a
data-provider return correctly, the file-level estimator has no remaining claim at
all."* Measured, that expectation is **wrong**, and the measurement is cheap
enough to be worth stating precisely.

On the rated worksheet the two tiers are **disjoint**. Stage 0 silences finding
040 and nothing else; the span tier silences 27 findings, and 040 is not among
them. Neither set contains the other.

On firefly-iii, where the classifier tags 34 files (every `config/*.php` and
`resources/lang/en_US/*.php` — exactly the class it was built for):

```
                                        before the span tier   after
  findings lying entirely inside tagged files        41          31
    of those, a file matching itself                 35          25
    of those, two different tagged files              6           6
```

The span tier removes ten, and the 25 that survive are **all in one file**:
`config/firefly.php`, which carries 28 `env(...)` calls. A Laravel config file is
a data table by any reader's judgement and is *not* a literal array by this rule's
definition, because its values compute. The six cross-file matches survive by
design — two different tables are the case the rule must never silence.

So the two tiers cover different populations: the file-level estimator claims
*wired files that are data*, the span tier claims *a table repeating itself*, and
on the evidence available neither subsumes the other. **The recommendation to the
project owner changes accordingly: the classifier's removal is not warranted by
this evidence.** §4.13's case for dropping it stands on its other leg — that it
has no true positives left to find, and that what it removes under the discard
posture is program text — but "the span tier has made it redundant" is now a
measured non-fact and should not be cited at the release decision.

---

## Ruling request 5 — the span rule's definition of *literal* excludes a config table that computes its defaults

**The mismatch.** The handoff specifies the tier as "structural context only:
what a span *is* (a data-provider return, a literal array)". `config/firefly.php`
is a data table by that description and by any reader's judgement; under the
implemented definition of *literal* — no computation anywhere inside the frame —
it is not one, because its values are `env('KEY', 'default')`. The instruction and
the observed fact disagree about one concrete file, so the reading is recorded
rather than taken.

**Readings considered.**

*(a) Leave it. "Literal" means no computation, full stop.* Acceptance is fully met
without the widening: 27 of 27 rated data-table N silenced, 0 Y lost. The 25
surviving self-matches in `config/firefly.php` remain reported, and the file-level
classifier keeps its claim on them. **Taken, provisionally, and implemented.**

*(b) Allow a call whose arguments are themselves all literals* — `env('X','y')` as
"a literal with a default". Silences the 25. But `strtoupper('first')` has exactly
that shape, and so does `new Money(1, 'EUR')`; the rule stops being a membership
test and becomes a small rule system, with no rated label asking for it.

*(c) Ask the file's role instead of the frame's purity*: a file whose top level is
a single `return [...]` is a data-table file, and a self-match inside it is
silenced whatever it computes. Measured on the rated worksheet before being
proposed: **silences 26 N and 0 Y** — one *fewer* than the landed rule (it misses
050, a table local to a seeder's method), so on the rated evidence it is not a
replacement but a possible addition, and its only measured gain is
`config/firefly.php`'s 25.

**Precedents.** Ruling D's lesson (never fit to a gate) and the plan's standing
rule that an extension needs a derivation rather than a plausible story; ruling
H's own instruction that the three refuted discriminators died of *extending past
what the labels supported*. The rated corpus cannot decide between (a) and (c),
because both score identically on it.

**Recommendation: (a), unchanged, until a rated label demands otherwise.** (c) is
recorded with its measurement so that the next rating round can decide it on
evidence rather than on appeal. If the auditor rules for (c), the change is small
and local to `RegionStructure`, and the revert line is this paragraph.

---

## 5.5 A standing gate is under-powered, and a larger sample of it fails

Found while re-running the gates, not caused by this tier — confirmed by running
the same command at the previous commit, where it fails identically.

`bench/run-recall.php` is recorded in §2.4 at `--sample=40`, where it passes
2/2 with the guaranteed region at 295/295. At the tool's **default** `--sample=60`
the same instrument reports:

```
  the guaranteed region has members            441 pairs
  the unified engine recalls it whole          439 of 441   FAIL

  unified missed inside the guaranteed region:
    php-parser   gapped_delete_d1       longest run 40, base 51 tokens, similarity 0.882
    php-parser   gapped_substitute_d1   longest run 40, base 51 tokens, similarity 0.882
```

Both pairs sit **inside** the guarantee (surviving run 40, well above S = 25) and
**inside** the acceptance contract (span 51 ≥ 50, similarity 0.882 ≥ 0.85), so
this is not a boundary artefact of the region's definition. The seeding guarantee
is about being *seeded*; what these two show is a pair that is seeded and then not
reported, which makes it a verification question rather than a sampling one.

Two things follow, and neither is a fix taken here:

1. **The recorded gate is weaker than the instrument it is drawn from.** A gate
   that passes at one sample size and fails at the tool's own default is reported
   at both sizes from now on, or it is not a gate.
2. **It belongs to step 6.** Ruling R's integrated shape reopens the verifier
   through ruling O's refusal taxonomy, which is exactly the layer that would
   refuse a 51-token pair with one deletion. It is recorded here so that it is
   inherited deliberately rather than rediscovered.

---

## 6. Step 6 — ruling R, in the engine's own spine

### 6.1 k, derived — and a unit error in the ruling's own floor case

Ruling R constraint 1 fixes the shape (bags of small-k raw shingles, not
unigrams) and hands the executor one number with an instruction: *"k needs a
derivation: measured statement-length distribution across the corpora (fixture
r1's swapped statements are 7 tokens; the permutation families are the
instrument)"*.

The derivation is forced once the question is stated correctly. A shingle of k
tokens either lies wholly inside a statement or straddles a boundary; only the
straddling ones change when two statements are swapped. A statement **shorter
than k contributes none**, so a swap of two such statements perturbs everything
locally and constraint 5 then forbids reporting it at all — there is nothing to
localize. So the shortest statement that must survive a swap is a hard ceiling
on k, and among the values under it, larger is more discriminating.

`bench/measure-statements.php` (new) measures the distribution over the top-level
statements of every function body — `bcb_function_statements()`, the same
segmentation the permutation injectors edit, so these are the units that actually
get moved — counted by the engine's own encoder:

```
  corpus            stmts    p1   p5  p10  p25  p50  p90
  php-parser         1619     1    2    2    2    4   12
  symfony-string     1138     2    2    2    4    7   23
  phpunit            1346     2    4    4    4    4   12
  firefly-iii        4577     1    3    3    4    6   21
  ALL                8680     1    2    3    4    5   18
  r1 (the floor case)  22     3    3    3    3    3    3

  k    statements with at least one interior shingle
   3   90.36 %
   4   82.60 %
   5   57.48 %
```

**The floor case is 3 tokens, not 7.** The ruling's "7" is a count of *source*
tokens; `--min-tokens`, K = 16, every span in this engine and every number in the
table above are counts of **significant** tokens, and the encoder drops
single-character tokens entirely — so `$delta = $alpha + $bravo;` is six source
tokens, three significant ones. The unit matters exactly once in this ruling, at
its floor case, so it is stated rather than assumed.

**k = 3**, and the correction was not academic. k = 4 was implemented first, on
the "7", and measured on r1: bag coverage **0.775** — above θ — with **zero**
localizable displacement, because no 4-shingle fits inside a 3-token statement.
The fixture whose entire purpose is to be the hard case would have been placed
permanently out of contract by a derivation that looked reasonable. It was the
acceptance target ("r1 back in contract") that caught it, which is the argument
for stating acceptance in fixtures rather than in aggregates.

### 6.2 Displacement is a change of order, not of offset

The first implementation called a run "displaced" when its A→B offset differed
from the pair's dominant offset. That is wrong in a way a permutation corpus
would not show: **an insertion shifts every later run to a new offset without
changing any run's order**. Probe 2 — twin *insertions*, a fixture that exists to
be gapped and not reordered — was reported as a reorder, and its test caught it.

The displaced set is now the complement of the heaviest order-preserving
subsequence of matched runs, which is the same construction
`CloneClassifier::displacedAnchors()` already used on anchors. One notion of
"displaced" in the engine, not two.

### 6.3 The order-free verdict must not consume evidence either — ruling O, again

The verdict was first written *inside* `verify()`, converting an aligner refusal
into an acceptance. That is the shape ruling O exists to forbid, and it broke
ruling O's own acceptance immediately:

```
  phpunit, --baseline=rabin-karp
    before                1 of 3 failing, 0 locations uncovered, 19 unexplained pairs
    verdict replacing     2 of 3 failing, 1 location uncovered,   2 unexplained pairs
                          ^ the location ruling O had recovered, lost again
```

The mechanism is exactly the old defect: an accepted candidate is not refused, so
`refusedOnSimilarity()` is false, so `splitAtWidestGap()` never runs, so the exact
clones inside the refused reading are consumed. **The verdict is now asked beside
the refusal rather than instead of it** — the refusal stays a refusal, the
decomposition still runs, and an order-free acceptance is emitted as an
*additional* candidate.

A second application needed no such care: an *accepted* gapped candidate can be
renamed a reorder without changing its extent or its verdict, so it consumes
nothing. That is what brings r1 into contract — the pair was always reported, but
as a substitution, because nothing seeded could say otherwise.

### 6.4 Acceptance

```
  permutation recall, adjacent   >= 77.5 %   measured 89.1 %   (token bag 77.5 %)
  permutation recall, distant    >= 81.4 %   measured 89.7 %   (token bag 81.4 %)
  fixture r1 in contract         yes         reordered, with the moved block named
  guaranteed recall region       295/295     295/295
  chaining oracle                2/2         2/2
  determinism (php-parser,phpunit) 4/4       4/4 each
  incremental                    8/8         8/8
  superset vs rabin-karp, php-parser  3/3    3/3
  superset vs rabin-karp, phpunit     unchanged  location half passes; pairs 19 -> 1
  phpunit                        166+ green  251 tests, 1241 assertions
  phpstan, both configs          clean       clean
  merged-default location half   8/137/311   8/134/311   <-- NOT met; see request 6
  bag-appropriate adjudicator    required    landed (§6.5)
```

Everything the ruling names is met or beaten except the merged-default location
half, which is §6.6's ruling request.

**Cost, measured rather than estimated.** On phpunit's 2,694 files:

```
  unified before ruling R      3.24 s
  unified after                4.31 s        (+33 %)
    of which the facts layer   0.45 s        (one structural pass over 2,861 files)
    the rest                   ~0.6 s        (shingling, the prefix index, verification)
  default pipeline             0.81 s
```

Ruling U's bar is ≤ 1.5×; this is 5.3×. The gap was already 4.0× before this
step. It is step 7's to report and the owner's to weigh; recorded here as a debit
against that decision, not as a reason to have declined the capability.

### 6.5 §3.3's deferred condition, discharged

§3.3 deferred one thing into this ruling: *"when the complement pass lands, the
pairs half becomes applicable to order-free findings again through a
bag-appropriate independent adjudicator — a textbook bijective-coverage recompute
over the two spans, written independently of the engine's own code, exactly as
the location walk is independent today."*

Landed. Three-token shingles, multiset intersection matched one occurrence to one
occurrence, over the baseline's own claimed span, at the **baseline's own**
`--min-similarity` rather than any engine constant — written inside the check,
sharing nothing with `ShingleBags`.

It changes what the gate can say, and the first thing it says is about the
baseline:

```
  php-parser, --baseline=default — the four uncovered token-bag findings
    NodeTraverser.php:93 ↔ :181           bag coverage 0.55   OVER-REPORT
    CompatibilityTest.php:16 ↔ :44        bag coverage 0.43   OVER-REPORT
    NodeTraverserTest.php:180 ↔ :215      bag coverage 0.55   OVER-REPORT
    ClassConstTest.php:168 ↔ ParamTest.php:33   0.72          unexplained (a real gap)
```

Three of the four are **over-reports by an independent order-free measure**: the
token bag claimed 0.70 overlap and an independent bijective recompute finds
0.43–0.55. The cause is not a bug in either engine, it is the bag granularity —
a bag of token *unigrams* overlaps far more readily than a bag of shingles, which
is exactly why ruling R's constraint 1 forbids unigrams. The fourth clears the
threshold and is a genuine gap: 45 tokens, below the run's own `--min-tokens`, so
unified cannot report it at this configuration whatever it finds.

For completeness, the pairs half on the two large corpora, which the class→pairs
expansion §3.2 identified still dominates (a token-bag class naming N sites
contributes N(N−1)/2 pairs):

```
  phpunit      100,419 pairs — 40,341 shorter, 2,409 baseline over-reports, 37,932 unexplained
  firefly-iii    5,017 pairs —  1,982 shorter,  1,812 baseline over-reports,   170 unexplained
```

These were 99,854 and 4,871 `inapplicable` before. The gate is now saying
something rather than declining to; what it says is that on the pair level the
token bag's classes are largely supported by an order-free measure and unified
does not report them, which is the pair-level form of the same 134/311.

---

## Ruling request 6 — ruling R's constraints and ruling R's acceptance target disagree, and the target is the part that does not survive measurement

**The mismatch, named.** Ruling R constraint 1 requires *"bags of small-k raw
shingles, **not token unigrams**"*, with the stated reason that unigrams make
"unrelated same-vocabulary functions" match. Ruling R's acceptance requires *"the
merged-default superset gate passing its TokenBag half — the concrete targets
being the 8 / 137 / 311 locations its first run measured"*. Those two cannot both
hold: the 8 / 137 / 311 are precisely the findings a unigram bag makes and a
shingle bag does not, and constraint 1 exists to not make them.

**Measured, not argued.** The four php-parser cases, by an adjudicator written
independently of the engine (§6.5): bijective 3-shingle coverage 0.55, 0.43,
0.55, 0.72 against the 0.70 the baseline claimed. Their *contiguous* shared runs,
by the older independent walk, are 2, 7 and 9 tokens. Three of the four are
baseline over-reports on the baseline's own threshold.

**Readings considered.**

*(a) Report the target as unmet, with the attribution.* The capability half of
ruling R — the class ruling A says seeds cannot see — is not merely met but
beaten (89.1 % / 89.7 % against 77.5 % / 81.4 %), and the location half is
unmet for a reason that is now measured rather than asserted. TokenBag stays
selectable, which the plan already requires until R "demonstrably covers it".
**Recommended, and what is implemented.**

*(b) Lower θ or k until the locations are covered.* This would pass the gate by
making the successor as undiscriminating as the thing it replaces, and it is
adjusting a constant to make a fixture pass — the one move the plan's §3 forbids
by name. Both constants also carry inherited provenance (θ from SourcererCC, the
diversity floor from ruling B), so the change would be untethered as well as
forbidden.

*(c) Add a unigram channel beside the shingle channel.* Reproduces the target
exactly and contradicts constraint 1 verbatim. It would also import the
over-reports: three of php-parser's four are findings an independent measure
rejects, so the gate would be passed by adopting an error.

**Precedents.** Ruling D (never fit a constant to a gate). The M2 audit's own
treatment of the pairs half — where the answer to "the gate reports a failure the
instrument cannot support" was to fix the instrument's applicability, not to
weaken the engine. And §3.2's standing caveat that a token-bag baseline is
measured by an instrument that was built for a different claim.

**What the auditor is asked to decide.** Whether ruling R's acceptance is
amended to (a) — the location half reported with its attribution and TokenBag
retained as selectable pending the release decision — or whether the 8 / 134 /
311 are to be closed by some fourth route this executor has not seen. The
consequence for step 8 is concrete either way: **TokenBag cannot be removed
under (a)**, because ruling R has not demonstrably covered its whole finding
set, and the plan makes coverage the precondition for removal. The deprecation
sequence in step 8 therefore removes `SuffixTree/` only.

---

## 7. Step 7 — re-measured, under the posture-relative rule

Every comparison below states **one corpus** and **one posture**, per the
auditor's rule of 2026-09-02. Numbers from different corpus lines are not
subtracted from each other, and where an earlier packet number was taken on a
different line that is said rather than glossed.

### 7.1 Wall-clock, and the disappearance of the case §4.6 warned about

Posture: **bare run** — no triage, which is the shipped default. Medians of 5
runs (3 on the private corpus), `--min-tokens=70`.

```
  corpus                files    default   unified    unified ÷ default
  symfony-string           33     0.031s    0.058s      1.9x
  phpunit                  60     0.012s    0.027s      2.3x
  phpunit                 200     0.029s    0.079s      2.7x
  php-parser              342     0.189s    0.780s      4.1x
  phpunit                 600     0.120s    0.292s      2.4x
  phpunit                2500     0.499s    1.535s      3.1x
  phpunit                2694     0.847s    4.341s      5.1x
  firefly-iii             200     0.104s    0.355s      3.4x
  firefly-iii             600     0.633s    1.084s      1.7x
  firefly-iii            1200     1.361s    2.466s      1.8x
  firefly-iii            1445     2.426s    2.681s      1.1x
  the private corpus      200     0.430s    2.320s      5.4x
  the private corpus      600     0.682s    2.623s      3.8x
  the private corpus     1200     1.311s    3.076s      2.3x
  the private corpus     2400     3.163s    4.183s      1.3x
  the private corpus     2735     3.231s    4.458s      1.4x
```

Ruling U's bar has two halves, and they now answer differently.

**Shape — met, and inverted.** The ruling's own sentence is that *"the
degradation with size, not the multiple, was always the red flag"*. On both
large corpora the ratio **improves monotonically with size**: the private corpus
goes 5.4× → 3.8× → 2.3× → 1.3× → 1.4×, firefly-iii 3.4× → 1.7× → 1.8× → 1.1×.
The M3 measurement that motivated the bar degraded in the other direction
(1.6× → 2.8× on phpunit). The red flag is gone.

**Level — missed, except at the two largest sizes measured.** ≤ 1.5× holds on
firefly-iii at 1,445 files (1.1×) and on the private corpus at 2,400 and 2,735
files (1.3×, 1.4×). It is missed everywhere else, worst on small corpora, where
unified pays a fixed per-file cost against a pipeline that pays almost none.

**phpunit is the exception to the shape, and it is one file.** The step from
2,500 to 2,694 files takes unified from 1.54 s to 4.34 s while the default
pipeline moves 0.50 s → 0.85 s. Attributed by removing files and re-measuring:

```
  phpunit 2,694 files, triage posture           default 0.807s   unified 4.216s
    minus the three vendored tool binaries      default 0.776s   unified 4.229s   (no effect)
    minus tests/unit/Metadata/MetadataTest.php  default 0.661s   unified 2.544s   (-40 %)
```

**One 327 KB test file is 40 % of the engine's whole runtime on phpunit.** It is
the same file the packet already knows: nine alignments of 12,000–25,000 tokens
(§2's verify() note), 19 of the 24 raw-view shift bands (§ruling J's note), and
the entire grouping residual. A hypothesis that the vendored tool binaries in
`tools/` were the cause was measured and refuted before being written down.

#### §4.6's "42×" case, re-measured — and it was the corpus

§4.6 recorded, as a warning to this step: *"On 3,067 files of this corpus:
unified 267.6 s against Rabin-Karp 3.9 s and the token bag 2.4 s — roughly 42×
the merged default … the sweep it is measured on does not contain a case like
this."*

That file set is the previous session's stored rung 4. Compared against the
product's own walk today:

```
  rung 4 as stored          3,067 files
  the product's own walk    2,736 files
  in rung 4, not in the walk  1,037 — of which 1,033 are one `.claude/trash` subtree
```

The stored rung 4 predates the dot rule (`91b1720`), so **a third of the file
set §4.6 timed is a trash tree** — precisely the dump/backup mass ruling K and
Stage 0 exist to remove. Re-timed on the same list with that one subtree
removed:

```
  rung 4 minus the trash subtree, 2,034 files    default 2.493s   unified 4.027s   1.6x
```

The 42× case is not a scaling property of the engine; it is a corpus the product
does not scan. This is the posture-relative rule earning its keep: §4.6's number
and this one are different corpus lines, so the right report is both lines and
their difference explained, not a subtraction.

### 7.2 The fresh precision pool — built, rated as rater A, and stopped

Corpus line: **the pinned snapshot, Stage 0's shipped posture, `laravel` preset,
an even stride of 1,200 files over the sorted tree.** The pin was verified first:
4,847 files pinned, 0 changed content, and every one of the nine differences a
regenerable framework cache.

`bench/audit-precision.php pool --triage` — the pool now consumes Stage 0 rather
than its own inline ladder, which is ruling T's "exactly one definition of the
corpus" applied to the last consumer that still had a private one.

```
  triage (Stage 0, shipped posture): 190 of 2,201 files removed, 2,011 left
  corpus: 1,200 files, sampled on an even stride
  unified 190 clones · rabin-karp 17 · tokenbag 24
  pooled 224 distinct findings, wrote 60
```

**Rated as rater A, and this is a hard stop.** The worksheet is at mode 600
outside every repository. κ is **not** computed and must not be, from one rater's
verdicts; the two-rater precision ruling U's bar is written in does not exist
until the auditor's rater-B pass is done.

What can honestly be said from one rater's sheet, labelled as such:

```
  rater A, 60 of 60 rated, 0 unrateable
    overall            34/60 = 0.567   Wilson [0.441, 0.684]
    unified            22/47 = 0.468   Wilson [0.333, 0.608]
    rabin-karp          3/4  = 0.750
    tokenbag           10/10 = 1.000
```

**This is not ruling U's number** and it is not comparable to M3's 0.340 without
saying what changed: a different sample, on a different corpus definition, after
three tiers landed. It is reported so the auditor's pass has a stated prior.

#### What rater A's false positives are, by family — and a specific, evidenced improvement

The 26 findings rater A called N fall into four families:

```
   7  route-definition files (one file's route table against another region of it, or another file's)
   9  literal tables — lang files, config, model `$fillable`/`$casts`, two dictionary files
   5  class preambles and lookalike accessors (`use` blocks, promoted constructors, migration headers)
   4  thin or sparse cross-file matches
```

Four of the nine literal-table cases are **one lang file matching itself** — the
`'create'` and `'edit'` blocks of one translation table, which the span
discriminator did not silence. The reason is exact and fixable: the rule asks for
the *smallest* pure literal frame containing each span, and those two runs sit in
two different sibling sub-arrays, so the frames differ. Asking for the
**outermost** pure frame instead would silence them — both runs are elements of
the file's one top-level table — and it would not touch the counter-example,
because `Php7.php` and `Php8.php` are two files and their tables are separate
top-level property initializers either way.

**Not changed, deliberately.** Altering the detector between rater A and rater B
would leave the worksheet describing an engine that no longer exists, and the
whole value of the two-rater protocol is that both raters judge the same
artifact. It is recorded here, with its evidence, as the first change to make
after the pass — and as a prediction that can be checked: it should move four
findings and no others.

### 7.3 The owner's release decisions — prepared with fresh numbers, none taken

Five decisions are the project owner's. Each is stated with the evidence as it
stands after step 6, an executor recommendation where the plan asks for one, and
what would change the answer.

#### (1) Candidate 2 — the normalized view sampled at W = 55

*Trigger:* ruling U makes this optional, *"taken only if this band is missed,
since it trades guaranteed capability for a target the field itself does not
meet"*. **The band is missed**, so the decision is live.

*The trade, from M3 §5, unchanged — it is a property of the winnowing algebra,
not of this milestone's work:* at W = 55 the normalized guarantee threshold
becomes exactly `minTokens = 70`, normalized fingerprints fall 34,198 → 17,568
and candidate file pairs 23,748 → 4,769 on phpunit. The raw view is untouched.
The cost is the guarantee: a renamed clone shorter than 70 tokens stops being
*guaranteed* to be seeded, where today the floor is 35.

*What is new since the draft, and it argues against taking it:* the shape half
of the bar is now met without it. The ratio improves with size on both large
corpora, and phpunit's exception is one file rather than a scaling property. A
change that trades a written guarantee for speed is worth most when speed
degrades with size, which is no longer what the measurements show.

**Recommendation: do not take it now.** Reconsider only if the owner wants the
≤ 1.5× level at *every* size rather than at scale, and record that the guarantee
is what is being sold for it.

#### (2) The default flip to `unified`

*What the plan requires:* the wall-clock and precision gates passing, **or** the
owner's recorded acceptance of the measured numbers.

*State:* speed as §7.1 — level missed except at the two largest sizes, shape met.
Precision **not yet measurable**: rater B has not run, and one rater's number is
not ruling U's bar. **The flip cannot be prepared beyond this point in this
milestone**, and that is the honest position rather than a hedge.

#### (3) The triage posture at release

*Corpus lines, not a bar (the posture-relative rule):*

```
  the private corpus, Stage 0's target definition
    discard posture    2,382 kept   90.0 % retained   206 files of coverage cost
    demote posture     2,543 kept   97.8 % retained    45 files of coverage cost

  firefly-iii, bare run vs triage
    bare                1,291 files   398 findings
    proof rungs only      850 files   168 findings
```

*What the numbers do not say:* whether the 206 files the discard posture drops
matter to a user. That is a product judgement and it is the owner's.

**Recommendation: ship triage off by default for 2.0.0, as it is today.** A flag
that changes which files are reported is a flip, and the evidence for flipping it
is not yet a two-rater precision number.

#### (4) The fishiness classifier

*§4.13 offered its removal on the argument that it has no true positives left.
§5.4 removed the other leg of that case*: the span tier does **not** cover the
class the classifier tags — on the rated worksheet the two tiers silence disjoint
sets, and on firefly-iii the span tier leaves 25 of 35 self-matching findings
inside tagged files, all in one config file whose values compute.

**Recommendation: keep it as a tag, and drop the "the span tier made it
redundant" argument from the release agenda** — it is now a measured non-fact.
The remaining case for removal (no true positives left; it removes program text
under the discard posture) stands on its own and is the owner's to weigh.

#### (5) TokenBag's removal

*The plan's precondition is explicit:* TokenBag is removed only after ruling R's
work *"demonstrably covers it"*. It does not, on the merged-default location gate:
8 / 134 / 311 locations, which is ruling request 6.

**TokenBag therefore stays**, and step 8's deprecation sequence removes
`SuffixTree/` only. This is not a recommendation but a consequence: the
precondition is written into the plan and it is unmet.

---

## 8. Step 8 — the landing sequence

### 8.1 The owner's release gate, run for the record

The gate the project owner recorded at M2 close: *"before the tag,
`bench/check-superset.php` passes with the merged default pipeline (RK+TokenBag —
what 1.4.0 ships) as baseline, not only Rabin-Karp."* Run on all four corpora,
both baselines, at the head of this milestone:

```
                   --baseline=rabin-karp        --baseline=default
  symfony-string   3/3 pass                     3/3 pass
  php-parser       3/3 pass                     8 locations uncovered
  phpunit          locations pass, 1 pair       134 locations uncovered
  firefly-iii      locations pass, 7 pairs      311 locations uncovered
```

**Every location Rabin-Karp reports is reported, on every corpus** — php-parser and
symfony-string pass outright, and phpunit's adjudicable pair residual is down from
19 to **1**. The merged-default half is ruling request 6: the uncovered locations
are the token bag's, and three of the four on php-parser are over-reports by an
independent order-free adjudicator (§6.5).

The capability demonstrations the gate asks for, each by fixture or gate:

```
  every 1.4 default location reported   RK half: yes, all four corpora
                                        TokenBag half: no — request 6
  named divergent ranges                CloneDivergence, probes 1-3, edgeext/edgeneg fixtures
  named displaced blocks                probe 4; fixture r1 (ruling R), tests/ShingleBagsTest
  always-on Type-2                      the normalized view; recall 114/114 with the floor on and off
```

Every standing gate at the head of this milestone:

```
  vendor/bin/phpunit                                   OK (251 tests, 1241 assertions)
  vendor/bin/phpstan analyse                           no errors
  vendor/bin/phpstan analyse -c phpstan-bench.neon     no errors
  php bench/self-test.php                              24/24
  php bench/check-chaining.php                         2/2
  php bench/check-determinism.php php-parser --algorithm=unified   4/4
  php bench/check-determinism.php phpunit    --algorithm=unified   4/4
  php bench/check-incremental.php php-parser           8/8
  php bench/run-recall.php --sample=40                 2/2 — guaranteed region 295/295
  php bench/check-walltime.php                         FAIL at the 1.5x level; see §7.1
  php bench/triage.php check                           2/2
```

### 8.2 Deprecation — SuffixTree only, and why TokenBag is not touched

The plan's sequence is *"flip the default to `unified`; keep the old engines
selectable one release as deprecated, then remove"*. Two facts constrain what
this step may do.

The **flip has not happened**, and cannot be prepared past §7.3(2) — the precision
half of its evidence does not exist until rater B has run. The deprecation clock
the plan starts at the flip therefore has not started, and removing an engine
before the release that deprecates it would run the sequence backwards.

**TokenBag's removal has an explicit written precondition** — ruling R's work must
*demonstrably cover* it — and §6.4 records that it does not.

So this step does the one thing that is in sequence and reversible:
**`--algorithm=suffixtree` is deprecated**, announced on stdout when selected,
recorded in the CHANGELOG and README with the reason. The removal commit, which
takes the `NOTICE` section and the Apache-2.0 clause in `LICENSE` with it, is
drafted but not made.

*Interpretation recorded, per the discipline.* **Mismatch:** the handoff's step 8
says "the deprecation/removal sequence (SuffixTree with its NOTICE entry in the
same commit)", while the plan sequences removal *after* a deprecation release that
is anchored on a flip the measurements do not support. **Reading taken:** deprecate
now, remove when the flip lands, keep NOTICE while the code is present.
**Precedent:** the plan's own ordering, and the standing rule that release
decisions are the owner's. **Revert line:** if the auditor rules that the removal
belongs in M4, it is one commit — the engine, its tests, the NOTICE section, the
LICENSE clause and a CHANGELOG entry — and nothing else in the milestone depends
on the outcome.

### 8.3 Ruling S — the inherited-code inventory

Written as `MODERNIZATION.md`, which both the README and the CHANGELOG already
linked and which did not exist. Measured over `src/`, 94 files and 18,298 lines:

```
  original to this project                75 files   15,448 lines   84.4 %
  carrying an upstream attribution        19 files    2,850 lines   15.6 %
    of which ConQAT / Apache-2.0           9 files    1,080 lines   the deprecated suffix tree
    of which upstream PHPCPD / BSD-3      10 files    1,770 lines
```

The BSD-3 remainder is what ruling S predicted: `CLI/Application.php` (523),
`CodeClone.php` (263), `DefaultStrategy.php` (223 — the tokenizer and the
Rabin-Karp strategy), `CodeCloneMap.php` (188), `Log/Text.php` (119), and five
smaller files of iteration, scan-loop, strategy-base and reporter scaffolding.

**The unit is files still carrying the attribution, not surviving lines**, and the
document says why: a header is an attribution claim rather than a measurement, and
computing a line-level diff would require reading the inherited implementation —
which is the one discipline ruling S's replacement standard actually enforces. It
is also the unit that has to reach zero before the MIT relicense.

Ruling S's honest caveat is carried into the document rather than left in the
plan: BSD-3-Clause already permits derived work with attribution, and sessions
across these milestones have read these files, so the enforceable standard is the
no-open discipline plus gate-proven equivalence — not a clean-room claim, and the
document makes none.

### 8.4 The paper revision

`paper/token-based-clone-detection-for-php.tex` gains §"The Unified Engine": the
design and provenance, the two additions that make it more than seed-and-extend,
and the two structural precision tiers. The first revision's third future-work
direction — the residual cost of candidate enumeration — is marked answered and
points at it; the three earlier engines are repositioned in the conclusion as the
measured baselines for the fourth. Five bibliography entries were added for work
the engine builds on and the paper had not cited.

Two passages exist because the project owes them.

**The two-corpus-states precision story, superseded and never corrected.** The
paper now states that the 0.34 figure was drawn from a pool taken on a live tree
that drifted between the rating pass and the close — 74 → 1,030 pooled findings,
with about 30 % of the rated pool inside a backup subtree that no longer exists —
so the two numbers describe two different corpora and neither is recoverable from
the other. It records the two rules that followed (pinned snapshots; one corpus
per comparison) and notes that applying the second retired a second number as
well, the "42×" wall-clock case of §7.1. **It prints no replacement figure**,
because only one rater has rated the fresh pool.

**The classifier's life cycle**, as a method rather than an anecdote: train a
guesser for a question the tool cannot answer from first principles, read its
log-odds to find which features are *provable* rather than merely predictive,
promote those to rungs that discard on checkable evidence, and let the guesser
shrink toward the residue. On this corpus the positive class was removed from
underneath it entirely — which is the strongest possible outcome of the procedure
and the reason it is worth writing down.

**The PDF is not rebuilt.** `pdflatex` is not installed in this environment and
`bin/build-paper.sh` refuses rather than emitting a stale artifact. The `.tex` was
checked for balanced environments, dangling `\ref`s and undefined citations — all
clean — and the PDF must be rebuilt where TeX Live is available before the tag.
This is a gap in the deliverable, stated rather than skipped.

---

## 9. M4 close — state, interpretations, and what is outstanding

### 9.1 Interpretations recorded

The standing discipline is that nothing is interpreted without a **named mismatch**
between an instruction and an observed fact, and that every interpretation taken is
recorded with its reading and its precedent. Three arose in this session.

**(i) The posture-relative rule against a live gate.** *Mismatch:* ruling 4(b)
states that posture differences are corpus lines rather than pass/fail bars, and
that the executor does not restate the 95 % retention bar; `bench/triage.php check`
still asserted that bar and printed `FAIL`. *Reading:* the ruling is a clear
instruction and was applied as written — the bar is retired and the comparison
prints two posture lines. *Precedent:* the ruling names the bar as "the executor's
own operationalization", so removing it restores the ruling's own text rather than
extending it. This is compliance rather than interpretation, and is recorded only
because it changed a gate's output.

**(ii) Ruling R's floor case is stated in a different token unit than the engine
uses.** *Mismatch:* the ruling derives `k` from "fixture r1's swapped statements
are 7 tokens"; measured in the engine's own significant-token numbering they are
**3**. *Reading:* the derivation is done in the unit every other quantity in this
engine uses — `--min-tokens`, K, and every span — because a `k` derived in source
tokens would put the ruling's own floor fixture permanently out of contract, which
was measured happening at k = 4. *Precedent:* §6.1, and the plan's rule that a
constant carries a derivation rather than a citation. *Revert line:* one constant
in `ShingleBags`, and the r1 test states the contract either way.

**(iii) The deprecation sequence.** Recorded in full at §8.2: the handoff's step 8
reads as removing `SuffixTree/` now, while the plan sequences removal after a
deprecation release anchored on a default flip the measurements do not support.
Deprecated, not removed; revert line and cost stated there.

### 9.2 Ruling requests outstanding

- **Request 5** (§5.4 tail) — the span rule's definition of *literal* excludes a
  config table that computes its defaults. Three readings, each measured;
  recommendation (a), implemented, with (c)'s numbers recorded so the next rating
  round can decide it on evidence.
- **Request 6** (§6.6) — ruling R's constraint 1 forbids unigram bags and its
  acceptance target is the set of findings only a unigram bag makes. Three
  readings; recommendation (a). **Its answer decides whether TokenBag can be
  removed**, and therefore what step 8's deprecation sequence may contain.

Both carry their mismatch, their readings and their precedents. Neither is an open
question handed to the auditor without an executor position.

### 9.3 The hard stop

**The re-rate is stopped at rater A, as instructed.** The blinded worksheet is
generated on the pinned snapshot under Stage 0's own corpus definition, rated by
this session as rater A, and is at mode 600 beside the M3 worksheets outside every
repository. **No κ has been computed and none may be from one rater's verdicts.**
Ruling U 2's precision bar does not have a value until the auditor's rater-B pass
exists.

One prediction is on record for that pass, at §7.2: the four lang-table findings
the span rule misses would be silenced by asking for the *outermost* pure literal
frame rather than the smallest, and that change should move those four and no
others. It was deliberately **not** made, because changing the detector between the
two raters would leave the worksheet describing an engine that no longer exists.

### 9.4 State at close

Head `fbe8b79`, working tree clean, 18 commits in this session, each with its own
CHANGELOG entry. The hygiene grep — a pattern file kept outside the repository, run
over the whole working tree rather than over tracked files alone — is clean, and it
found and closed one real gap on the way in (§the `.gitignore` entry: the corpus
path was kept out of commits only by the *user's global* gitignore).

```
  vendor/bin/phpunit                                   OK (251 tests, 1241 assertions)
  vendor/bin/phpstan analyse                           no errors
  vendor/bin/phpstan analyse -c phpstan-bench.neon     no errors
  php bench/self-test.php                              24/24
  php bench/check-chaining.php                         2/2
  php bench/check-determinism.php × 2 (unified)        4/4 each
  php bench/check-incremental.php php-parser           8/8
  php bench/run-recall.php --sample=40                 2/2 — 295/295
  php bench/check-superset.php (rabin-karp) × 4        every location covered on all four
  php bench/triage.php check                           2/2
  IndexCodec::VERSION                                  5, unchanged (no stored byte, no selection rule moved)
  constants introduced                                 one: ShingleBags::SHINGLE_LENGTH = 3, derived at §6.1
```

Known failing, each with its section: the merged-default location half
(8 / 134 / 311 — request 6); the wall-clock level bar (§7.1, shape met, level met
only at the two largest sizes); and `bench/run-recall.php` at the tool's default
`--sample=60`, which is under-powered relative to the recorded gate and fails on
two pairs inside both the guarantee and the contract (§5.5, inherited into the
verifier work and not closed there).

### 9.5 Not closed here

This milestone is **not closed by its executor**. The packet ends awaiting the
auditor's verdict, and the two ruling requests above are the first things it has to
answer, because request 6 decides what the deprecation sequence may contain and
request 5 decides whether the span rule widens before the next rating round.

**AUDIT: pending** — *and then the record continued.* The auditor's rater-B pass
arrived on 2026-09-02 and this session resumed against it. Nothing above this line
has been rewritten; §10 ff. carry the work done after the stop was lifted, and the
operative close is §15.

---

## 10. Resume at rater B — the two-rater score, and ruling U bar 2 recorded failing

The stop at §9.3 is lifted: the auditor's filled worksheet arrived beside rater A's,
at mode 600 outside every repository. Both sheets describe **the same artifact** —
no detector change was made between them, which was the point of §7.2's deliberate
non-fix.

### 10.1 The score, verbatim

```
$ php bench/audit-precision.php score <rater-a.tsv> <rater-b.tsv>
rater A: 60 findings, 60 rated
rater B: 60 findings, 60 rated

both rated:        60 findings
observed agreement 0.867
expected agreement 0.522
Cohen's kappa      0.721  (meets the 0.7 the brief requires)

rater A precision over the sampled pool: 34/60 = 0.567
Wilson 95% interval:                     [0.441, 0.684]
unrateable ('?'):                        0

precision per engine over the rated sample (rater A):
  rabin-karp   3/4   = 0.750   Wilson [0.301, 0.954]
  tokenbag     10/10  = 1.000   Wilson [0.722, 1.000]
  unified      22/47  = 0.468   Wilson [0.333, 0.608]

rater B precision over the sampled pool: 40/60 = 0.667
Wilson 95% interval:                     [0.541, 0.773]
unrateable ('?'):                        0

precision per engine over the rated sample (rater B):
  rabin-karp   4/4   = 1.000   Wilson [0.510, 1.000]
  tokenbag     10/10  = 1.000   Wilson [0.722, 1.000]
  unified      27/47  = 0.574   Wilson [0.433, 0.705]

A finding several engines reported counts once for each of them, so the
rows are not disjoint and do not sum to the total above.
```

κ = **0.721** on 52 of 60 agreements. The unified engine's precision is
**0.468 [0.333, 0.608]** under rater A and **0.574 [0.433, 0.705]** under rater B;
the two intervals overlap over most of their length and neither excludes the other's
point estimate. TokenBag is **10/10 under both raters** — the M3 bar it set (7/7)
survives a second, larger, independently-rated sample.

### 10.2 The κ drop from 0.898 is the pool, not the instrument — the auditor's reading, recorded

M3's two-rater κ was 0.898; this pool's is 0.721. The auditor's reading, recorded
here as the operative one:

> the drop is the pool getting harder, not the instrument getting worse. Stage 0 and
> the span tier removed the *easy consensus* — the dump trees, the backup subtrees,
> the pure literal frames that both raters called N without hesitating — and what is
> left concentrates the sample on the adapted-skeleton vs boilerplate boundary.

The sheets bear this out: **all eight disagreements sit on that one boundary**, and
none of them is a case a discriminator has a rule for.

```
  id   engine       A  B   what it is
  001  unified      N  Y   1012 lines: a zip-import flow copied into a second controller and adapted
  011  unified      N  Y   a dispatch map, keys → handler method names
  016  rabin-karp   N  Y   two blocks of one lang table (create/edit)
  017  unified      Y  N   two runs of one test class's per-case scaffolding
  029  unified      N  Y   a controller preamble: trait use, promoted constructor, doc block
  034  unified      N  Y   a lang table against another region of a lang table
  041  unified      N  Y   a stitched resource test — same assertions, different resource
  052  unified      N  Y   lookalike accessors over a status-label map
```

Seven of the eight run A = N, B = Y: rater B reads an adapted copy as duplicated
logic more readily than rater A does. That is a **calibration difference on one
axis**, not a scatter — a κ of 0.721 with a single systematic axis is a more
tractable instrument than the same κ spread across unrelated families would be. The
axis is also exactly the one no span-level rule reaches: *is a second copy that was
edited still one thing?* is a judgement about intent, and the plan's standing rule
against extending a discriminator past what the labels support (ruling H's lesson)
applies to it in full.

### 10.3 Ruling U bar 2 — recorded as failing, under both raters

Ruling U's bar 2 asks for **≥ 0.80** precision. It fails, and is recorded failing:

```
  ruling U bar 2   ≥ 0.80
    rater A        0.468  [0.333, 0.608]   FAIL — bar outside the interval
    rater B        0.574  [0.433, 0.705]   FAIL — bar outside the interval
```

Under rater B, the more generous of the two raters, the bar sits above the upper
Wilson bound. There is no reading of this sample on which bar 2 passes, and none is
offered.

**The residual, characterized.** Rater B's 20 N verdicts — every one of them a
`unified` finding — are what a next tier would have to reach:

```
  11  cross-file data/config tables   lang tables, model $fillable/$casts, config arrays,
                                      two generated dictionary files, a menu/attribute table
   7  route lists                     one route file's Route:: block against another's
   1  stitched test                   (017) two runs of one test class's per-case scaffolding
   1  migration scaffold preamble     (046) the three Migration/Blueprint/Schema imports plus
                                      the Schema::create frame, two migrations
```

The auditor's characterization was "~11 cross-file data/config tables, ~7 route
lists, ~2 stitched tests"; the first two counts land exactly, and the last two
findings resolve as **one** stitched test and **one** migration scaffold preamble
rather than two tests. The difference is recorded rather than smoothed, because it
matters to what a tier would have to be: a test-shape rule reaches one finding here,
not two.

Read against §11's grant, 11 of 20 are in the class ruling 5 addresses and 7 are
route lists — a class no rule in this engine currently names.

---

## 11. Ruling 5, granted as a structural extension — *literal* means statement-free

The auditor granted request 5 not as reading (a), (b) or (c) but as a **structural
extension**: *literal* means **statement-free**. An array whose elements are records
built from expressions — including leaf defaulting calls like `env()` / `trans()` /
`route()` with literal arguments — is a data table under the span rule; an array any
of whose elements contains a closure or a statement is logic-bearing and is never
silenced. A membership test, no constant.

This is a better answer than the three readings the executor put up, and it is worth
saying why rather than merely recording that it was taken. Reading (b) — "allow a call
whose arguments are all literals" — was refused here for the right reason: it turns a
membership test into a rule system, because `strtoupper('first')` and `new Money(1,
'EUR')` have exactly that shape. The granted rule sidesteps that entirely by *not
caring* how an element computes. It asks one question the language already answers:
**is there a statement in here?** An array element is an expression; the only way a
statement gets inside an array literal is a closure body. So the test stays a
membership test over one token class, and the class named is `T_FUNCTION`, `T_FN`, `;`.

### 11.1 As implemented

`RegionStructure`'s `DATA` whitelist is struck and replaced by a `LOGIC` blacklist of
three tokens; the propagation is unchanged (a closure anywhere inside makes every
enclosing frame logic-bearing); the identity half of the rule — *same* frame — is
untouched. The struck docblock is replaced by one that states the ruling, its two
worked examples, and why the kind of test did not change.

**One boundary deliberately not crossed, and recorded as such.** `match` is *not* in
the token class. The ruling names "a closure or statement"; a `match` is an
expression; extending past the ruling's words to catch it would be the executor
widening a granted rule on its own judgement. Measured either way on the pool, it
changes nothing. The revert line is one entry in `RegionStructure::LOGIC`.

### 11.2 Acceptance, item by item

**(i) Computed-defaults config self-matches silenced.** The named file was
`config/firefly.php`, whose values are `env('KEY', 'default')`. Its self-matching
findings fall from **24 to 17**: ten silenced outright, three re-formed at shorter
spans (§11.4 says why those three survive).

**(ii) Zero consensus-Y lost on BOTH worksheets.** Measured with the same instrument
§5.3 used — the rule object the engine uses, replayed over each finding's own sites —
on the relocated M3 worksheet and on a freshly relocated M4 worksheet carrying both
raters' verdicts:

```
  M3 worksheet (relocated v2, 50 unified findings with a consensus label)
    before ruling 5   span tier silences 27 N, 0 Y   → Y 17  N  5   0.773
    after  ruling 5   span tier silences 27 N, 0 Y   → Y 17  N  5   0.773
    silenced set identical, finding for finding

  M4 worksheet (relocated, 40 unified findings with a consensus label; 6 never
                relocated and held as surviving)
    before ruling 5   Stage 0 silences 4 N, 0 Y; span tier 0 N, 0 Y  → Y 21  N 15
    after  ruling 5   Stage 0 silences 4 N, 0 Y; span tier 0 N, 0 Y  → Y 21  N 15
```

Zero consensus Y lost on both, and the M3 silenced set does not move by one finding.

Two honest limits on this measurement, stated rather than glossed:

- **The M4 sheet cannot show ruling 5's gain, by construction.** That pool was drawn
  from an engine that already had the pre-ruling-5 span tier, so anything the tier
  silences never entered the sample. The M4 sheet is therefore evidence about *losses
  only* — which is the acceptance criterion it was named for — and the gain has to be
  measured on the corpora, below.
- **Relocation covered 50 of 60 M4 findings.** Ruling P's manifest admits only files
  whose content still hashes to the pinned value, and the live tree has drifted since
  the pin; ten findings' excerpts could not be placed. Seven digest collisions were
  reported rather than resolved (a duplicated block that occurs in two files makes the
  block match ambiguous — the tool declines rather than guessing). The 6 unrelocated
  unified findings are held as *surviving*, which is the conservative direction.

**(iii) Every cross-file different-table finding, and Php7/Php8, stay reported.**
Cross-file findings are immune **by construction**, not by measurement: the filter only
inspects candidates with `fileA === fileB`, and `sameLiteralTable` requires one
`RegionStructure` and one frame id. Verified anyway on the flagship counter-example —
php-parser's clone count does not move at all, and the pair is reported at full length:

```
  Php7.php:381 ↔ Php8.php:383   2,536 lines   reported   (and 17 further Php7/Php8 classes, all intact)
```

**(iv) The paired negative fixture.** `RegionStructureTest` now carries two fixtures
with **identical token layout**, differing only in closure-versus-call:

```
  aRowThatComputesItsDefaultIsStillARow        env('FIRST_DRIVER', 'mysql')      silenced
  oneClosureInsideTheTableIsEnoughToDisqualifyIt   function () { return 'mysql'; }   NOT silenced
```

The negative test also asserts the two fixtures have equal token counts, so the pair
cannot drift into differing in something other than the thing the ruling names. The
struck test — `oneCallInsideTheTableIsEnoughToDisqualifyIt`, which asserted that
`strtoupper('first')` disqualified a table — is removed, because the granted ruling
says the opposite and a test asserting the struck reading would be a second, silent
definition of *literal*.

### 11.3 What it silences on the corpora, opened and named

Every clone-count effect, per the standing rule that a delta is not a report:

```
  php-parser        83 →  83   no change
  symfony/string    23 →  22   AbstractAsciiTestCase's truncate() data provider, whose rows
                               carry `TruncateMode::` enum constants
  phpunit          590 → 581   SourceFilterTest (5) and ResultPrinterTest (2) data-provider
                               returns; .php-cs-fixer.dist.php's rule table (2)
  firefly-iii      597 → 573   config/firefly.php (10 gone, 3 re-formed shorter), the
                               NavigationAddPeriodTest and CalculatorProvider
                               data providers (6 each), Transaction/StoreRequest (4)
                               and PiggyBankTransformer (1) mapping tables
```

Thirty-seven findings silenced, three re-formed, net 34. **Every one of the 37 is a
data-provider return, a config table, or a mapping table** — which is precisely the
class the handoff's step 5 named as its target ("a data-provider return, a literal
array, a dumped table"). Nothing outside that class moved.

The widening does reach further than `config/firefly.php`: the **mapping table** family
(`'key' => $this->convert($row['key'])`, repeated) was not in the ruling request and is
silenced by it. That is stated here rather than left to be discovered, because it is the
largest behavioural consequence of the grant. It is the same shape by the ruling's own
test — records built from expressions, no statement anywhere — and both raters' N
verdicts on the analogous findings in their own sheets say they read it the same way.

### 11.4 The three `config/firefly.php` survivors are the *outermost-frame* case, not a ruling-5 residue

Ten of `config/firefly.php`'s 24 self-findings go outright and three re-form at
shorter spans, leaving 17.
The reason is exact, and it is **not** about literalness: the rule asks for the
*smallest* statement-free frame containing each run, and these three have their two
runs in two different sibling sub-tables of the file's one top-level table, so the
frames differ and the identity test correctly declines.

This is the same mechanism as §7.2's recorded prediction about the four lang-table
findings, and it is **still unmade**: asking for the outermost statement-free frame
rather than the smallest is a separate change, with its own prediction and its own
acceptance, and folding it into ruling 5's commit would make ruling 5's measurement
unreadable. It is measured in §14.4 and not landed.

---

## 12. Ruling 6, granted — the TokenBag-only locations, adjudicated

Request 6 is granted at reading (a), with the adjudication made binding rather
than merely recommended: **a baseline finding whose independently recomputed
bijective coverage falls below TokenBag's own θ = 0.7 is a baseline over-report** —
counted and listed in the release evidence, not a miss. M1's RK-length precedent
one level up: neither engine is believed, the source decides, no new constant.

### 12.1 What was built

`bench/check-superset.php`'s **location** half now adjudicates before it accuses.
The pairs half already did this (§6.5); the location half — *the half the owner's
release gate is written in* — had been binding without ever asking whether the
locations it bound were real.

The recompute is the same instrument §6.5 landed: three-token shingles, multiset
intersection matched one occurrence to one occurrence, divided by the larger bag,
written inside the check and sharing nothing with `ShingleBags`. **θ is read from
the run's own configuration** — `--min-similarity`, the number the baseline itself
used to make the claim — so no constant is introduced and none can be tuned to
change a verdict.

Three conservatisms are built in, and all three point the same way, toward calling
a location a genuine miss, which is the direction that costs this engine:

1. **The best pair wins.** A class of N sites is adjudicated at its *strongest*
   supporting pair, never its weakest and never its average.
2. **Unlocatable is a miss.** A span the recompute cannot place stays a miss.
3. **Contiguous claims are never adjudicated by a bag measure.** If Rabin-Karp also
   reports the location together with another site of the class, the claim is
   contiguous, this adjudicator has no standing, and it stays a miss whatever a
   shingle bag thinks. `--baseline=rabin-karp` is therefore untouched, and was
   re-run to confirm it: php-parser and symfony/string still 3/3.

### 12.2 Survivor counts, per corpus

```
  php bench/check-superset.php --baseline=default bench/corpus/<c>

                    was     survivors            baseline over-reports
                            entries  distinct    entries  distinct
    php-parser        8        2       1            6       6
    symfony/string    0        0       0            0       0
    phpunit         134       10       8          124      21
    firefly-iii     311       84      60          227      93
                  -----     ----     ---         ----     ---
    total           453       96      69          357     120
```

Both bases are printed because both are needed and neither substitutes for the
other: **entries** is the unit the 8 / 134 / 311 were counted in and the only basis
on which the before-and-after can be compared, and **distinct locations** is what
"a class of real findings dies with the token bag" actually means.

**357 of the 453 — 79 % — were never supported by the baseline's own measure.**
That is the headline, and it is a statement about the instrument, not a
congratulation: the release gate has been reporting a number four fifths of which
was the granularity artefact ruling R's constraint 1 exists to avoid. A bag of
token *unigrams* overlaps far more readily than a bag of shingles; the gate had been
charging this engine for the difference.

**96 survivors remain, and they are real.** The gate still fails, and it should.
The php-parser survivor is instructive and was already known from §6.5: the
`ClassConstTest.php` / `ParamTest.php` / `PropertyTest.php` class, coverage 0.72,
**45 tokens — below the run's own `--min-tokens` of 70**, so unified cannot report
it at this configuration whatever it finds. The phpunit and firefly-iii survivors
are the order-free class proper: `DispatchingEmitterTest`'s repeated emitter
scaffolds, `TestSuiteTest`, firefly's `ListController` / `BillController` family.

### 12.3 The consequence for step 8 — TokenBag ships selectable-deprecated

Ruling 6's second clause decides the deprecation sequence: *TokenBag is not removed
while a genuine surviving location class dies with it; survivors uncovered by the
shingle channel means TokenBag ships selectable-deprecated and its removal waits on
a ruled successor.*

Every one of the 96 survivors is, by the definition of a survivor, uncovered by the
unified engine — the shingle channel included, since the shingle channel is part of
it. So the condition is met and the action is taken: `--algorithm=tokenbag` now
prints a deprecation notice, and the notice **states the gap rather than only
recommending a replacement**, because a user whose pipeline depends on that class
needs to know it is still uncovered:

```
  (--algorithm=tokenbag is deprecated; use --algorithm=unified — which does not
   yet report every location the token bag does, so this stays selectable)
```

The merged **default** pipeline is untouched: it still runs Rabin-Karp and the token
bag together, and nothing about that is deprecated here. §8.2's sequence is amended
accordingly — SuffixTree deprecated-and-to-be-removed, TokenBag
deprecated-and-retained — and the removal of TokenBag is not in this release's
scope, nor is it the owner's decision (5) to take yet, which §15 restates against
these numbers.

---

## 13. The `--sample=60` recall failure, traced to the end

§5.5 recorded this and deliberately did not chase it. Chased here, to the M3
four-miss standard: the pair identified, the mechanism located in one line of one
component, the candidate fix implemented and measured, and — because the
measurement refutes it — reverted and surfaced rather than shipped.

### 13.1 The pair

Both failing pairs are **one function under two operators**:
`php-parser/lib/PhpParser/Node/Scalar/String_.php`, `String_::fromString()`, unit
#53 of the 60 sampled from php-parser. It is unit #53, which is why `--sample=40`
never saw it — this is not a flake and not a sampling artefact, it is one function
that enters the pool between 40 and 60.

```
  base    51 significant tokens
  variant 45 (gapped_delete_d1)   /  47 (gapped_substitute_d1)

  shared prefix   base[0..39]  ↔  variant[0..39]      40 tokens
  divergence      base[40..45] — `$string = self::parse($str, $parseUnicodeEscape);`
                                 deleted, or replaced by `$_bcb_sub_1 = null;`
  shared suffix   base[46..50] ↔ variant[40..44]       5 tokens
                                 — `return new self($string, $attributes);`
```

Inside the guarantee: the surviving run is 40 ≥ S = 25, so the pair is provably
**seeded**. Inside the acceptance contract: span 51 ≥ `--min-tokens` 50, similarity
1 − 6/51 = **0.882** ≥ 0.85. Nothing about the region definition is doing this.

### 13.2 The mechanism, traced

The trace, in the M3 format, produced by driving `CloneClassifier::extend()` and
`ChainBuilder::chain()` directly on the two signatures:

```
unit#53 base=51 variant=45  anchors=1
  anchor A[0]<->B[0] len=40
  extend: 1 -> 2 runs
    run A[0]<->B[0]   len=40
    run A[46]<->B[40] len=5        <-- the trailing material, recovered
  gap-penalized chain keeps 1 of 2
    kept A[0]<->B[0]  len=40       <-- and dropped again
  unpenalized chain keeps 2 of 2   score=45 covered=45
  classify -> span=40  →  40 < minTokens 50, refused
```

Confirmed against the engine end to end: the pair is reported at
`--min-tokens=40` — **at exactly 40 tokens, the prefix run alone** — and at nothing
above it.

**Ruling M's half (a) is working.** The flank exemption recovers the 5-token tail;
extension does its job. The loss is entirely at the junction, and the arithmetic is
one subtraction:

```
  ChainBuilder score:  length + score(predecessor) − gapA − gapB

  chain {A1}       40
  chain {A1, A2}   5 + 40 − 6 − 0 = 39        ← loses by one point
```

The deletion costs `gapA = 6` on the base side and `gapB = 0` on the variant side;
the tail repays 5. **A trailing run is joined only when it is longer than the
divergence it follows**, whatever that divergence is worth relative to the whole
clone.

**Why ruling M (b) does not reach it.** M3's four misses tied *exactly* — the run
gained 2 tokens across a 2-token gap — and ruling M (b) broke that tie toward
coverage. This one loses by one, so a tie-break cannot see it. Same family, one
step further out.

### 13.3 The disagreement this exposes, named

The chain scorer and the acceptance contract measure the same thing in two
different units, and they disagree in a specific band:

- the **scorer** is local and absolute: a junction pays for itself if
  `run > gapA + gapB`;
- the **contract** is global and proportional: a pair is a clone if
  `distance / span ≤ RATIO` — here 6/51 = 0.118, comfortably inside 0.15.

`ChainBuilder`'s own docblock says the score "only *ranks* candidates in any case:
whether a chain is reported is decided later". In this band it does not rank, it
**decides** — it truncates the candidate to 40 tokens, and the min-tokens floor
then refuses a pair the aligner would have accepted. That is the defect, stated
without a fix attached to it.

### 13.4 The candidate fix, implemented and measured — and it is wrong

The obvious candidate: **re-attach outer-flank runs after the penalized chain**, on
ruling M's own argument — the gap penalty exists to arbitrate between competing
*readings*, and a flank run has nothing beyond it to bridge to, so it cannot be a
competitor. Implemented as a private `reattachFlanks()` in `CloneClassifier`,
touching neither `ChainBuilder` nor its brute-force oracle.

It does what it was meant to:

```
  the traced pair          reported at 51 tokens (was: refused)
  run-recall --sample=60   441 of 441   PASS   (was 439 of 441)
```

**And it is refuted by the existing suite.** Four tests fail, and two of them are
statements of design rather than regressions:

```
  every_gapped_clone_has_verified_similarity_at_least_the_threshold
      a gapped clone is emitted at similarity 0.838 — under the 0.85 the engine
      promises. The invariant is broken, not bent.
  probe1_edge_divergence_at_both_ends_is_not_flagged
      84 tokens reported where 60 is the specified answer — the re-attachment
      walks out into the edge divergence the probe exists to keep out.
  probe5_negative_two_copies_that_merely_continue_differently_are_not_flagged
  probe5_one_copy_extended_is_flagged_with_a_bounded_range
      both produce no clone where one is specified.
```

The corpus effect is correspondingly large, and in the wrong direction:

```
  php-parser        83 →  67 clones   (12,086 → 7,218 duplicated lines, 33 → 44 files)
  symfony/string    22 →  16
  phpunit          581 → 589          (111,778 → 127,900 lines, 478 → 495 files)
  firefly-iii      573 → 569          ( 54,753 →  45,200 lines, 443 → 491 files)
```

**Reverted, and the reason is the finding.** An unconditional flank re-attachment
is not a completion of ruling M's argument — it is a different rule, because a
flank run *can* pull the candidate past the acceptance ratio, which is exactly what
`every_gapped_clone_has_verified_similarity` caught it doing. A correct version has
to re-verify after re-attaching and back the attachment out when verification
fails, which changes the order of `classify()`'s decide-then-verify pipeline. That
is a mechanism change beyond what the instruction to *trace, not tune* permits.

---

## Ruling request 7 — the chain scorer decides where its own contract says it only ranks

**The mismatch, named.** `ChainBuilder`'s recorded contract is that its score
"only *ranks* candidates in any case: whether a chain is reported is decided
later" by `BandedAligner` and the `minTokens` floor. On the traced pair it does not
rank — it truncates a 51-token candidate to 40, which is what the floor then
refuses. The observed fact and the component's own stated role disagree.

**The band is narrow and it is describable.** It is exactly: a divergence `d`
followed by a trailing run `r` with `r ≤ d`, where the whole pair is inside the
acceptance ratio (`d / span ≤ 0.15`) and `span − r < minTokens ≤ span`. Both traced
misses sit in it. It is not a large class, and no claim is made here that it is —
what it is, is a class that the guarantee promises and the engine does not deliver.

**Readings considered.**

*(a) Report the gate at both sample sizes and leave the mechanism alone.* §5.5's own
rule — "a gate that passes at one sample size and fails at the tool's own default is
reported at both sizes from now on, or it is not a gate". Costs nothing, fixes
nothing, and leaves 439/441 standing as the honest number at the tool's default.
**Recommended as the floor**, and done regardless of how the rest is ruled.

*(b) Re-attach outer flanks, then re-verify, and back out on failure.* The candidate
of §13.4 with the defect it exposed repaired: attach, run `verify()`, and drop the
attachment if the aligner refuses. This keeps every invariant the suite pins,
because the aligner gets the last word rather than the chain scorer. It is a
change to the order of `classify()`'s pipeline — decide, verify, re-decide — and
`probe1`'s specified answer has to be re-derived under it rather than assumed to
survive. **This is the executor's recommendation if a mechanism change is granted**,
and its cost is honestly stated: one more aligner call per candidate that has an
unattached flank, and four probe tests to re-derive rather than re-run.

*(c) Make the junction penalty proportional rather than absolute* — charge a
junction only when the accumulated divergence would exceed
`BandedAligner::budgetFor(span)`. This makes the scorer agree with the contract by
construction, which is the cleanest statement of the fix. It is also the largest
change: `ChainBuilder` is the component M2 verified against a brute-force oracle
over 3,000 anchor sets, the score becomes span-dependent and therefore no longer a
pure function of the anchor set, and the oracle would have to be re-derived before
the change could be believed. Not recommended without a separate milestone.

**Precedents.** M3 ruling M itself — the same collision, one step less severe, and
the same handling: implement, measure, revert, surface rather than ship half a fix.
Plan §3's rule that a failure of the *design* stops and reports rather than being
worked around.

**What the auditor is asked to decide.** Whether (a) alone stands for this release —
the gate reported at both sizes with the band documented — or whether (b) is
granted, in which case `probe1`'s expected span is re-derived under the new pipeline
and the change ships with its own trace. Nothing is taken here either way; the tree
is at (a).

---

## 14. Every standing gate, re-run after §§11–13 landed

Two product changes (ruling 5, the TokenBag deprecation) and one instrument change
(ruling 6's location adjudicator) landed in this stretch. Every standing gate was
re-run against the result, and both halves of the subsumption check are reported
rather than the location half alone — §5.5's own rule, applied to the other gate it
turns out to govern.

### 14.1 Green

```
  vendor/bin/phpunit                                   OK (252 tests, 1243 assertions)
  vendor/bin/phpstan analyse                           no errors
  vendor/bin/phpstan analyse -c phpstan-bench.neon     no errors
  php bench/self-test.php                              24/24
  php bench/check-chaining.php                         2/2
  php bench/check-determinism.php × 2 (unified)        4/4 each
  php bench/check-incremental.php php-parser           8/8
  php bench/run-recall.php --sample=40                 2/2 — guaranteed region 295/295
  php bench/triage.php check                           1/1
  IndexCodec::VERSION                                  5, unchanged — no stored byte and no
                                                       selection rule moved (ruling Q)
  constants introduced                                 none
```

The test count moved 251 → 252: ruling 5 removed one test asserting the struck
reading and added two — the computed-defaults positive and its paired
closure-bearing negative.

### 14.2 Subsumption, both halves, both baselines

```
  --baseline=rabin-karp
                    locations                       pairs
    php-parser      all covered            3/3      15 pairs, 0 unexplained
    symfony/string  all covered            3/3       1 pair,  0 unexplained
    phpunit         all covered            FAIL     238 pairs, 1 unexplained
    firefly-iii     all covered            FAIL     107 pairs, 7 unexplained

  --baseline=default (the owner's release gate, adjudicated under ruling 6)
                    survivors  distinct    over-reports  distinct
    php-parser          2         1             6           6
    symfony/string      0         0             0           0
    phpunit            10         8           124          21
    firefly-iii        84        60           227          93
```

**The pairs-half failures against the Rabin-Karp baseline are not new and are not
this session's.** They were measured identical at `fbe8b79`, the commit this session
resumed from, in a worktree at that commit. They are recorded here because §9.4's
closing summary reported that gate's **location** half only — "every location
covered on all four" — which is true and is not the whole gate. Both halves from now
on. The eight unexplained pairs are the same shape on both corpora: a Rabin-Karp
pair the source agrees with at full length that unified reports shorter or not at
all — the phpunit one at 153 tokens, firefly's at 76–90 — and they are the class §13
just traced one instance of, at the other end of the size range.

### 14.3 Hygiene

The hygiene grep — the pattern file outside the repository, run over the whole
working tree rather than over tracked files alone — was run before each of the four
commit batches in this stretch and is clean at each. No worksheet, snapshot or
corpus path is staged, and the corpus is not named in any committed file.

### 14.4 The outermost-frame change, measured and not landed

§7.2 recorded a prediction and §11.4 owes it an answer: asking for the **outermost**
statement-free frame rather than the smallest would silence the lang-table and
`config/firefly.php` self-matches whose two runs sit in two different sibling
sub-tables. Implemented as a five-line change to `RegionStructure::literalTable()`,
measured, and reverted.

```
  php-parser        83 →  83     no change (Php7 ↔ Php8 intact)
  symfony/string    22 →  22     no change
  phpunit          581 → 581     no change
  firefly-iii      573 → 554     21 findings gone, 2 re-formed
  vendor/bin/phpunit             252/252, unchanged
  both worksheets                no movement at all — see the limit below
```

**Two reasons it is not landed, and the second is the substantive one.**

1. *The worksheets cannot arbitrate it.* On the M3 sheet the silenced set does not
   move by one finding; on the M4 sheet the span tier still silences nothing,
   because that pool was drawn from an engine that already had the tier. Worse, the
   four lang-table findings §7.2 named are among the ten the relocation could not
   place (034 and 056 in particular), so the sheet is blind to exactly the prediction
   it was supposed to check. The prediction is therefore **neither confirmed nor
   refuted** by the rated evidence, and saying so is the honest result.
2. *It silences cross-file material.* Two of the 21 removed findings name sites in
   more than one file:

```
  firefly.php:612 | webhooks.php:42 | webhooks.php:74            26 lines
  AccountRepository.php:486 | firefly.php:479 | :503 | :508 | webhooks.php:78   18 lines
```

   The filter itself only ever inspects same-file candidates, so this is not the
   rule reaching across files — it is a clone *class* that named cross-file sites
   dissolving when its same-file pairs are removed from underneath it. Ruling 5's
   own removals had no such case; this change has two. A precision tier that
   silences cross-file findings as a side effect of a grouping interaction is a
   different object from one that does not, and it needs its own ruling rather than
   inheriting ruling 5's.

Recorded with its measurement so the next rating round can decide it on evidence,
which is the same disposition §5.4 gave reading (c).

---

## 15. The owner's release decision package — prepared against the final numbers, none taken

§7.3 prepared five decisions before rater B existed and before rulings 5 and 6.
Three of them have moved. This section replaces §7.3 for the release meeting;
§7.3 stands as the record of what was preparable at that point.

**Nothing here is taken.** Where the plan asks the executor for a recommendation
there is one; where the decision is the owner's judgement rather than a reading of
a number, the numbers are laid out and no preference is expressed.

### 15.1 The audited scorecard — the three axes, as they finally stand

**Detection: unified dominates both 1.4 engines.** This is no longer a projection.

```
  every location Rabin-Karp reports is reported by unified
    php-parser · symfony/string · phpunit · firefly-iii      all four, no exceptions

  permutation recall — the class seeding provably cannot reach
                       sample 40            sample 60
    adjacent    unified 89.1 %  bag 77.5 %  |  89.6 %  77.5 %
    distant     unified 89.7 %  bag 81.4 %  |  91.0 %  80.6 %

  and it localizes: a displaced range is named, where the bag returns a score
```

The one thing the merged 1.4 default still reports that unified does not is
**96 location entries at 69 distinct locations** (§12.2), which is decision (d).

**Speed: the 1.5× bar is met at the sizes that matter, and the ratio improves with
size.** Ruling U's own framing was that *"the degradation with size, not the
multiple, was always the red flag"*:

```
  firefly-iii       200 → 600 → 1200 → 1445 files    3.4x → 1.7x → 1.8x → 1.1x
  the private corpus 200 → 600 → 1200 → 2400 → 2735  5.4x → 3.8x → 2.3x → 1.3x → 1.4x
```

≤ 1.5× holds at 1,445 · 2,400 · 2,735 files and is missed below that, worst on
small corpora where unified pays a fixed per-file cost against a pipeline that pays
almost none. phpunit's outlier is one 327 KB test file, not a scaling property, and
§4.6's "42×" case was a trash tree the product does not scan.

**Precision: improved, and under the bar.**

```
  M3, one corpus definition, κ = 0.898        unified 0.340 / 0.358
  M4, Stage 0 + span tier, κ = 0.721          unified 0.468 [0.333, 0.608] rater A
                                                      0.574 [0.433, 0.705] rater B
  ruling U bar 2                              ≥ 0.80 — FAILS under both raters
```

The two pools are two corpus definitions and the improvement is not a subtraction
of one number from another; what can be said is that the same instrument, applied
after two tiers landed, returns 0.47–0.57 where it returned 0.34, and that the bar
is outside both intervals. TokenBag is 10/10 and Rabin-Karp 3/4 and 4/4 on the same
sample — small denominators, and the honest statement stays the interval one.

### 15.2 (a) The default flip — two defensible postures, laid out

§7.3 could not prepare this because precision was not measurable. It is now, and
the answer is that **the flip is a judgement, not a reading**: one gate half is met
and improving, one is met at scale, one fails. Both postures below are defensible
on the same evidence, and no recommendation is made between them.

**Posture 1 — flip with tags.** Make `unified` the default and ship findings in
data-table and route files **demoted rather than suppressed**. The machinery is
already present and shipping: Stage 0's demote posture retains 97.8 % at 45 files
of coverage cost against the discard posture's 90.0 % at 206, and the fishiness
classifier already tags exactly the file class in question.

```
  the case                                            the cost
  detection dominates 1.4 on every axis measured      precision ships at 0.47–0.57,
  speed meets the bar where corpora are large           under a bar written at 0.80
  the residual is one shape and it is tagged:          a user who reads tags as
    11 data/config tables + 7 route lists of            noise sees the same 0.47
    rater B's 20 N — 18 of 20 in tagged classes         they would see untagged
  demotion is reversible per-run; a flip is not
```

**Posture 2 — hold the flip one more precision tier.** Keep the merged pipeline as
the default for 2.0.0 and ship `unified` selectable, as today.

```
  the case                                            the cost
  0.80 is the bar the project wrote for itself and    a release whose headline
    it is outside both raters' intervals                capability is not the default
  the residual has a named next tier: route lists     the flip is deferred to a
    (7 of 20) have no rule in this engine at all        milestone that must first
  κ = 0.721 says the boundary the next tier must        find and rate a new pool
    reach is the one both raters find hardest
```

**What would decide it on evidence rather than judgement:** a precision pass ≥ 0.80,
or the owner's recorded acceptance of 0.47–0.57 as the release number. The plan
allows the second explicitly. **Neither is taken here.**

### 15.3 (b) The triage-enable default

*Unchanged from §7.3 in substance; restated with the final numbers.*

```
  the private corpus, Stage 0's target definition
    discard posture    2,386 kept   90.0 % retained   206 files of coverage cost
    demote posture     2,547 kept   97.8 % retained    45 files of coverage cost

  firefly-iii, bare run vs triage
    bare                1,291 files   398 findings
    proof rungs only      850 files   168 findings
```

Triage is **off** by default today. What the numbers do not say is whether the 206
files the discard posture drops matter to a user; that is a product judgement.

**Recommendation: ship triage off by default for 2.0.0.** A flag that changes which
files are reported is itself a flip, and the evidence for flipping it is the same
precision number that has not cleared its bar. Note the interaction with 15.2: if
posture 1 is taken, the demote posture is the natural companion and this decision
becomes part of that one rather than separate.

### 15.4 (c) The fishiness classifier — keep as a tag, or drop

**§5.4's disjointness measurement is re-run after ruling 5, and it has moved.**
That matters, because §5.4's conclusion — "the two tiers cover different
populations, so the classifier's removal is not warranted" — was measured on the
pre-ruling-5 span rule.

On firefly-iii, where the classifier tags 34 of 1,445 files (every `config/*.php`
and `resources/lang/en_US/*.php` — exactly the class it was built for):

```
  findings lying entirely inside tagged files
                                    before ruling 5   after ruling 5   + outermost frame
    total                                 30               23                 6
      a file matching itself              24               17                 0
      two or more different tagged files   6                6                 6
```

Read across that row:

- **Ruling 5 took seven of the classifier's exclusive self-match claims** — the
  `config/firefly.php` findings whose rows are `env()` calls, which §5.4 named as
  the whole of the classifier's remaining ground.
- **The 17 that survive are all in `config/firefly.php`, and they are the
  outermost-frame case** (§14.4). Under that change — measured, not landed — the
  classifier's self-match claim on this corpus goes to **zero**.
- **The 6 cross-file findings are not the classifier's to claim either.** They are
  *different* lang tables in *different* files, which is the case the span rule must
  never silence by design — the Php7/Php8 counter-example in another costume. A
  file-level tag that silences them would be silencing true positives.

On the rated worksheet the two tiers remain disjoint exactly as §5.4 found: Stage 0
silences finding 040 and the span tier silences 27, and neither set contains the
other. That is unchanged by ruling 5.

**So the evidence has turned since §7.3, and the recommendation turns with it.**
§7.3 said "keep it as a tag, and drop the redundancy argument". The redundancy
argument is no longer a non-fact: after ruling 5 the span tier covers most of what
the classifier claimed, and a ruled outermost-frame change would cover the rest of
the *silenceable* part. What is left is 6 findings a correct span rule must leave
alone.

**Recommendation: the classifier's remaining case is not precision.** It has no
measured exclusive precision claim left on this corpus that a span-level rule
should not take instead. If it is kept, it should be kept for the reason §4.13
gave — that it is a tag, and a tag is cheap and reversible — and not because it
catches something the tiers miss. If it is dropped, the measured cost on this
corpus is zero findings that a ruled span rule would not also reach. **The
disjointness argument for keeping it is retired.**

*One honest limit:* this is one corpus. The rated worksheet cannot arbitrate it
(the two tiers are disjoint there on one finding), and the outermost-frame column
is a measurement of an unlanded change.

### 15.5 (d) TokenBag — decided by ruling 6, not by this meeting

Ruling 6 makes this a consequence rather than a choice, and it has already been
taken as such (§12.3):

```
  merged-default location gate, adjudicated
    survivors      96 entries at 69 distinct locations   — real, uncovered by unified
    over-reports  357 entries at 120 distinct locations  — never supported by the
                                                            baseline's own measure
```

TokenBag ships **selectable-deprecated** in 2.0.0. Its removal waits on a successor
that demonstrably covers those 69 locations. What remains for the owner is only
whether to say more about it in the release notes than the CLI notice already says.

*What would change it:* a ruled successor covering the survivor class. §12.2 sizes
what that successor has to reach — the sub-`--min-tokens` case on php-parser is
structurally out of reach at the default configuration, and the phpunit and
firefly-iii survivors are the order-free class proper.

### 15.6 (e) Candidate 2 — live only if the 1.5× band is judged unmet

*Trigger:* ruling U makes this optional, *"taken only if this band is missed, since
it trades guaranteed capability for a target the field itself does not meet"*.
Whether the band is missed is now itself a judgement, because the two halves of the
bar answer differently: **shape met, level met at scale only**.

*The trade, unchanged — it is a property of the winnowing algebra:* at W = 55 the
normalized guarantee threshold becomes exactly `minTokens = 70`, normalized
fingerprints fall 34,198 → 17,568 and candidate file pairs 23,748 → 4,769 on
phpunit. The raw view is untouched. The cost is the guarantee: a renamed clone
shorter than 70 tokens stops being *guaranteed* to be seeded, where today the floor
is 35.

**Recommendation: do not take it.** A change that sells a written guarantee for
speed is worth most when speed degrades with size, and it no longer does. If the
owner wants ≤ 1.5× at *every* size rather than at scale, the recommendation
reverses — and the thing being sold should be named in the release notes.

### 15.7 What each decision would need to be re-opened

```
  (a) the flip          a precision pass >= 0.80, or the owner's recorded
                        acceptance of 0.47-0.57 as the release number
  (b) triage default    subsumed into (a) if posture 1 is taken
  (c) the classifier    a corpus where it tags a class no span rule reaches;
                        this one is not that corpus
  (d) TokenBag          a ruled successor covering the 69 surviving locations
  (e) candidate 2       the owner judging the 1.5x band unmet at every size
```

---

## 16. Close of the rater-B stretch — state, interpretations, and what is outstanding

### 16.1 The final numbers

```
  precision (ruling U bar 2, >= 0.80)                   FAILS under both raters
    Cohen's kappa                                       0.721   (52 of 60 agreements)
    unified, rater A                                    0.468   [0.333, 0.608]
    unified, rater B                                    0.574   [0.433, 0.705]
    tokenbag, both raters                               1.000   (10/10)
    rabin-karp                                          0.750 / 1.000  (3/4, 4/4)
    residual, rater B's 20 N                            11 data/config tables · 7 route lists
                                                        · 1 stitched test · 1 migration preamble

  detection
    every Rabin-Karp location covered                   all four bench corpora
    permutation recall, adjacent / distant
      sample 40   unified 89.1 / 89.7   token bag 77.5 / 81.4
      sample 60   unified 89.6 / 91.0   token bag 77.5 / 80.6
    merged-default locations, adjudicated (ruling 6)    96 survivors at 69 distinct locations
                                                        357 over-reports at 120 distinct

  speed (ruling U, <= 1.5x)                             shape met; level met at scale only
    firefly-iii   200 / 600 / 1200 / 1445               3.4x / 1.7x / 1.8x / 1.1x
    private corpus 200 / 600 / 1200 / 2400 / 2735       5.4x / 3.8x / 2.3x / 1.3x / 1.4x

  standing gates
    vendor/bin/phpunit                                  OK (252 tests, 1243 assertions)
    vendor/bin/phpstan analyse                          no errors
    vendor/bin/phpstan analyse -c phpstan-bench.neon    no errors
    php bench/self-test.php                             24/24
    php bench/check-chaining.php                        2/2
    php bench/check-determinism.php x 2 (unified)       4/4 each
    php bench/check-incremental.php php-parser          8/8
    php bench/run-recall.php --sample=40                2/2 — 295/295
    php bench/run-recall.php --sample=60                FAIL — 439/441 (traced, §13)
    php bench/check-superset.php x 4 (rabin-karp)       locations: all covered on all four
                                                        pairs: 0 / 0 / 1 / 7 unexplained
    php bench/triage.php check                          1/1
    IndexCodec::VERSION                                 5, unchanged (ruling Q)
    constants introduced                                none

  commits                                               9, each with its own CHANGELOG entry
                                                        where it changed the product or an
                                                        instrument; 5 are packet-only
  hygiene grep                                          clean before every commit batch
  working tree                                          clean at HEAD
```

### 16.2 Interpretations recorded

Three arose, each with a named mismatch, a reading and a precedent.

**(i) "A closure or a statement" is not a token class PHP exposes.** *Mismatch:*
ruling 5 names a syntactic category — a statement — and `token_get_all()` has no
token for one. *Reading:* the class is `T_FUNCTION`, `T_FN`, `;`. An array element
is an expression, so the only route a statement takes into an array literal is a
closure body; `;` is a total guard for anything that route might carry. *Precedent:*
the ruling's own requirement that this stay a membership test with no constant — a
rule system deciding what counts as a statement would be reading (b) in another
form. *Revert line:* `RegionStructure::LOGIC`, three entries.

**(ii) `match` is an expression, and was left out.** *Mismatch:* a `match` inside a
table is control flow by any reader's judgement, and the ruling's words do not
reach it. *Reading:* applied as written — a `match` is an expression and is not
excluded. Extending past the ruling's words on the executor's own judgement is the
move ruling H's three refuted discriminators died of. *Measured:* it changes nothing
on the pool either way. *Revert line:* one entry in the same constant.

**(iii) "TokenBag-only locations" under a merged baseline.** *Mismatch:* ruling 6
says to adjudicate the *TokenBag-only* locations, and `--baseline=default` produces
locations whose class may be partly Rabin-Karp's. *Reading:* a location is
TokenBag-only when no Rabin-Karp clone reports it together with another site of its
class; where one does, the claim is contiguous, a bag measure has no standing, and
it stays a miss. *Precedent:* §6.5's own attribution rule for the pairs half, which
this reuses rather than re-invents. *A second reading inside the same ruling:* the
ruling speaks of a *finding*'s coverage and the gate counts *locations*, so a
location is adjudicated at the strongest supporting pair of the class that named it
— the reading most generous to the baseline, and therefore the one that costs this
engine.

### 16.3 Outstanding ruling requests

- **Request 7** (§13) — the chain scorer decides where its own contract says it
  only ranks. Three readings, each measured; (a) recommended as the floor and
  already done, (b) recommended if a mechanism change is granted, with the naive
  version's refutation attached. **This is the only request outstanding**; 5 and 6
  are granted and landed.

### 16.4 One observation, recorded and not acted on

`bench/run-recall.php --sample=60` emits PHP warnings from
`src/Detector/Strategy/SuffixTreeStrategy.php:96–97` — `Undefined array key`, then
`Attempt to read property "file" on null`. The loop walking back from
`position + length - 1` starts beyond the end of `$this->word` and recovers on the
next iteration, so the reported numbers are unaffected (suffixtree scores 369/441
correctly), but the gate's output is polluted.

Pre-existing: the file is unchanged since `fe6d585`, the baseline import. It is in
the engine deprecated for removal, and it is outside every step of this stretch's
scope, so it is **recorded rather than fixed** — a one-line clamp on `$lastIndex`
would silence it, and that is the owner's call to schedule, not the executor's to
take on the way past.

### 16.5 What is not closed here

Known failing, each with its section: `run-recall --sample=60` at 439/441
(§13, request 7); the merged-default location half at 96 survivors (§12, ruling 6 —
reported with its attribution, and the reason TokenBag ships selectable-deprecated);
the pairs half against Rabin-Karp on phpunit and firefly-iii (§14.2, pre-existing and
measured identical at the resume commit); ruling U bar 2 at 0.468/0.574 against 0.80
(§10.3). None of these is presented as passing and none has been worked around.

Two changes were implemented, measured and reverted rather than shipped: the
flank re-attachment (§13.4, refuted by the suite's own similarity invariant) and the
outermost-frame rule (§14.4, silences cross-file material as a grouping side effect).
Both are recorded with their measurements so they can be decided on evidence.

**This milestone is not closed by its executor.** The packet ends awaiting the
close-audit verdict, with request 7 as the first thing it has to answer and the
release decision package at §15 prepared for the owner, not taken.

---

AUDIT: **pass — M4 closed. Ruling 7 decided: (a) stands for 2.0.0, (b) is the
designated fix for the next stretch.** 2026-09-02, Fable session.

Verified by re-running, not by reading: suite 252/252 · phpstan clean, both
configs · self-test 24/24 · chaining oracle 2/2 · determinism 4/4 · incremental
8/8 · superset php-parser 3/3 · superset phpunit at **1 unexplained pair** with
the location half passing — the residual that stood at 366 items at M2's close
is now one pair · recall 295/295 at sample 40 and 439/441 at 60, exactly as
recorded · the adjudicated release gate reproducing §12.2's survivor structure ·
wall-clock parity failing at 0.32–0.44× on the phpunit size sweep, which
reconciles with §15.1 rather than contradicting it: the parity instrument
carries the known 327 KB-file outlier, and ruling U's bar is evaluated on the
large-corpus posture-relative measurement, where ≤ 1.5× holds at 1,445 / 2,400 /
2,735 files with the ratio improving in size — the red flag ruling U named is
gone on the corpora that are the point.

**Ruling 7 — decided as the request framed it.**

- **(a) stands for 2.0.0.** The gate reports at both sample sizes; 439/441 at
  the tool's default is the honest number; the band is documented as a limit in
  the release notes' own terms: a trailing run no longer than the divergence
  before it, at a span straddling the min-tokens floor, can be truncated below
  the floor by the chain scorer. Narrow, precise, and stated.
- **(b) is granted as the designated fix, sequenced after the release** — not
  into this close, because it reorders `classify()`'s pipeline and re-derives
  four probe expectations at the exact moment the release package's numbers
  were frozen. Conditions when it lands: attach → verify → back out, so the
  aligner has the last word and the 0.85 invariant never bends; `probe1`'s
  expected span re-derived under the new pipeline with the derivation recorded,
  not assumed; `ChainBuilder` and its oracle untouched — the change lives in
  the consumer; recall 441/441 at both sample sizes; the corpus effect measured
  and each movement explained (§13.4's attach-without-verify numbers are the
  cautionary baseline). The executor's implement-measure-revert on §13.4 —
  catching that unconditional re-attachment is a *different rule* because a
  flank can pull a candidate past the acceptance ratio — is the reason (b) can
  be granted at all: the failure mode is now known and named.
- **(c) is rejected without its own milestone**, as the request itself
  recommended: a span-dependent score is a different theorem, and the oracle
  would have to be re-derived before any result could be believed.

**The rest of the close, ratified item by item:** ruling 5's boundary
discipline (leaving `match` outside the class because widening it would be the
executor's judgement, not the ruling's — correct, and the kind of restraint
rule 0 exists to produce); ruling 6's adjudication with four-fifths of the gate
number exposed as unigram-granularity artefact and the 96 survivors honestly
keeping TokenBag selectable-deprecated; the §14.4 revert (a tier that silences
cross-file findings as a grouping side effect is a different object needing its
own ruling — deferred to the next rating round, which is the only instrument
that can arbitrate it, since both worksheets are measurably blind to the
prediction); SuffixTree deprecated-not-removed with the interpretation recorded
(the plan sequenced removal behind a deprecation release anchored on a flip the
numbers do not yet support); MODERNIZATION.md as ruling S's inventory at 84.4 %
original — the MIT endgame correctly waits on zero; the §16.4 warning flagged
with a task chip rather than fixed out of scope. Nine commits, entries paired,
hygiene clean, no constants, VERSION at 5.

**M4 is closed.** The audit trail stands: M0 pass · M1 pass · M2 pass · M3
pass · M4 pass. Every constant carries a derivation or a ruling; every retreat
from one is recorded where it happened; two implemented-measured-reverted
changes wait on evidence rather than shipping on hope. The release decision
package at §15 now goes to the project owner: (a) the flip posture, (b) the
triage default (executor's recommendation to ship off is endorsed), (c) the
classifier's disposition with the disjointness argument retired, (d) TokenBag —
already decided by ruling 6's survivors, (e) candidate 2 — moot unless the
owner judges the 1.5× band unmet, which §15.1's numbers do not suggest. The
2.0.0 tag is the owner's to make once (a)–(c) are answered; the PDF rebuild
waits only on a machine with pdflatex.
