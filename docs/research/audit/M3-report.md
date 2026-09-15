# M3 — ruling J, ruling F, and the measurement program

Executor: Claude Opus. Plan: `docs/research/unified-engine-plan.md` §2 M3, as amended by the
M2 close audit. Work order: `docs/research/audit/M3-HANDOFF.md`. Builds on M2 (`aab923f`,
`AUDIT: pass`).

Read first, as instructed: the plan's §"Rules for the executing session" and §3 "If a gate
fails", then §1 and §2 M3, then the handoff, then M2's rulings. No constant was changed. Two
gates cannot pass as specified and are reported below with their measurements and, where the
contradiction is provable, the trace that proves it.

**This packet does not claim M3 complete.** It ends awaiting the audit verdict.

---

## 0. Summary

| Item | Status |
|---|---|
| Ruling J — same-file fine-granularity class emission | **implemented**, two commits; residual 66 → **21** items |
| Ruling F — wall-clock closure | **cannot pass**; one design change measured and rejected, one measured and surfaced for a ruling |
| Recall curves from the M0 injectors | **built and run**; the guaranteed-region gate **fails 291/295**, root cause traced |
| Precision — two-rater audit | rater A complete: unified **0.340** vs both baselines **1.000** — the gate **fails**; κ awaits the auditor as rater B (§7) |
| Wall-clock at 60/200/600/2,500 files | **run**; see §8 |
| **New blocking finding** — seed enumeration exhausts memory on a real application corpus | §6 |

Four things need a ruling before M4: ruling F's remaining design choice (§5), the
guaranteed-region gate's two-rule collision (§4), the seed-pair bound (§6), and the precision
result (§7). §6 blocks the plan's own 2,500-file wall-clock size and would block M4's release on
any similar codebase; §7 is the one that should change what M4 ships, because 28 of 35 false
positives are a single shape the project has already confirmed and has an open avenue against.

---

## 1. Ruling J — implemented

### The defect, located

The M2 packet's diagnosis was that "class formation collapses granularity whatever the
candidates look like". **That diagnosis is stale after rulings D and G.** Measured first,
before touching anything: `classes()` was already emitting fine granularity, and the same 15
classes appear whether `MetadataTest.php` is scanned alone or inside the whole corpus. The
defect was upstream of class formation and had a different shape.

Instrumenting the raw view's self-pair through the shipped partition helpers:

```
view=raw  tokens=31388  fingerprints=3162  anchors=1841
clusters=1
  cluster 0: 1841 anchors -> 22 shift bands
    band 1   shift=351     anchors=83    mass=18770  startLine=2980  -> 0 candidates
    band 2   shift=724     anchors=77    mass=14079  startLine=2658  -> 0 candidates
    band 3   shift=1087    anchors=76    mass=12842  startLine=2337  -> 0 candidates
    ...
    band 9   shift=3393    anchors=127   mass=15299  startLine=309   -> 0 candidates
    ...
    band 15  shift=8632    anchors=144   mass=16853  startLine=1215  -> 0 candidates
    band 16  shift=9958    anchors=92    mass=11138  startLine=3160  -> 0 candidates
    band 17  shift=11466   anchors=73    mass=9266   startLine=3308  -> 0 candidates
```

**Twelve of the twenty-two bands produced nothing at all** — and they are the high-mass ones,
including band 1, which carries the file's own repeat period. Tracing band 1's extraction loop
round by round:

```
band 1: 83 anchors, shift=351

round 1  A[53,+13692) B[431,+13678) kind=gapped    tok=13678 selfOverlap=YES shift=378  claim=378/378  anchors 83 -> 82
   REFUSED (self-overlapping)
round 2  A[0,+13745)  B[0,+14109)  kind=gapped    tok=13745 selfOverlap=YES shift=0    claim=0/0      anchors 82 -> 25
   REFUSED (self-overlapping)
round 3  A[0,+13969)  B[0,+13969)  kind=type-1    tok=13969 selfOverlap=YES shift=0    claim=0/0      anchors 25 -> 25
   no progress, stop
```

The chainer finds the periodicity perfectly and the engine throws it away. Round 1's chain
aligns 13,692 tokens against 13,678 at a shift of 378: the two ranges overlap by 13,314 tokens,
so it is refused — correctly, a stretch of code is not a duplicate of a shifted view of itself
— and with it goes every block-against-block duplication inside.

### The mechanism, with its derivation

A chain aligning `[s, s+L)` with `[s+D, s+D+L)` asserts that token *p* matches token *p+D*
everywhere it reaches: the region has **period D**, in the elementary sense (Lothaire,
*Combinatorics on Words* — the definition; no theorem is needed). A region of length `L+D` with
period `D` *is* a run of `⌊(L+D)/D⌋` blocks of `D` tokens that agree pairwise. That is exactly
the "N mutually-similar blocks" ruling J says to report as one class naming every block.

So the chain is restricted to **one period step** — the anchors the extraction loop was already
computing in order to advance — and re-classified. That candidate's two ranges are `D` apart
and at most `D` long, so they are **disjoint by construction**: an ordinary clone pair, judged
by the ordinary `verify()` on its own evidence. The loop's existing claim-narrowing then steps
to the following block, and `classes()`'s union-find closes the consecutive relation
transitively into one class.

**No constant is introduced.** The period is read off the candidate, the block count falls out
of the loop, `minTokens`/`minLines` decide whether the blocks are large enough to report, and
`SITE_OVERLAP` is untouched — as the ruling requires. A shift of zero is a region against
itself at no distance, has no period to decompose, and is refused as before.

### A second defect, which ruling J's work exposed

With the decomposition in place the band emitted **one** candidate, not thirty-six. Round 2's
chain sits at *shift 0* — the identity alignment — and consumes the band's anchors. Its source:

```
extended anchor shifts -> count:
   shift=0        2      <-- manufactured by extension
   shift=351      1
   shift=364      4
   shift=368      15
   ...
```

`AnchorSet` generates a same-file pair only for `posA < posB`, and says why in its own comment:
*"which is what keeps a run from being 'found' against itself at offset zero"*. Gapped
extension did not honour that rule. It recovers runs by direct comparison at a chain's flanks
and inside its gaps, and on a self-pair both sides are the same string, so every one of those
searches matched trivially at zero offset. Chained together, those identity runs formed the
whole-file shift-0 candidate that ended the walk after a single step.

Extension now applies Stage C's own exclusion. This is Stage C's rule applied where it was
missing, not a new rule.

### Result

Self-pair candidates from `MetadataTest.php`'s raw view: **39 → 50** (decomposition) →
**244** (identity exclusion). The engine reports the file's repeat period as ruling J specifies
— one class naming every copy:

```
lines=75 tokens=387 gapped=yes sites=17 :
  MetadataTest.php:2567 :3245 :3319 :3388 :3457 :3654 :3739 :3814 :3877
  :3964 :4115 :4258 :4818 :4887 :5017 :5376 :5444
```

`bench/check-superset.php bench/corpus/phpunit`, the acceptance instrument the handoff names:

| | M2 close | after ruling J |
|---|---:|---:|
| unreported baseline locations | 32 | **1** |
| unexplained pairs | 34 | **20** |
| **total residual items** | **66** | **21** |

`bench/check-superset.php bench/corpus/php-parser` stays **3/3**, as required.

### The residual, itemized rather than patched

The handoff says to report the gate either way and itemize what remains. The gate still fails.
All 21 items were classified by asking, for each residual baseline pair, which unified clones
cover each of its two locations:

```
both locations uncovered: 0 | one uncovered: 0 | grouping difference: 18
```

- **1 item** — `assertArraysHaveEqualValuesIgnoringOrderTest.php:269` is the only location no
  reported clone covers. This is the one finding ruling G added, recorded at M2 close as "one
  70-token pair, at the threshold". Unchanged.
- **1 item** — `ProgressPrinterTest.php:212 ↔ ResultPrinterTest.php:1023`, the pre-existing
  different-alignment shape recorded at M2 close. Unchanged.
- **19 items** — baseline pairs whose **both** locations are reported, each by several clone
  classes, but with no single class covering both. Six of these are pairs the baseline itself
  over-reports (`:996 ↔ :3252`, baseline 224 tokens, source agrees on 74).

That last group is a difference in *grouping*, not in what was found. It still counts against
the engine under Bellon et al.'s pair criterion, which is why the gate is reported as failing
rather than argued away — but it is a materially different residual from M2's, which contained
31 genuinely uncovered locations. **No further rule was improvised**, per the handoff.

### Cost, stated plainly

phpunit goes from 369 to 590 reported clones and from 68,399 to 110,976 duplicated lines;
php-parser 34 → 84; symfony-string 17 → 24. That is the direct consequence of stating the fine
relation, which is strictly stronger than the coarse one: block *i* ≡ block *i+1* implies
half ≡ half transitively, while half ≡ half implies nothing about adjacent blocks. Whether the
volume earns its keep is a precision question (§7).

`MetadataTest.php`'s whole-halves clone, which the ruling requires not be lost, is retained: the
17-site fine class spans lines 2567–5444, crossing the halves boundary, and the coarse relation
follows from it transitively.

### Commits

- `9fba006` — extension must not match a file against itself at offset zero.
- `b558140` — ruling J: a repeated block is one class naming every copy.

Separate commits and separate CHANGELOG entries, per the standing rule; they are two distinct
defects and the second was found only because the first was fixed.

---

## 2. Gates

```
$ vendor/bin/phpunit --do-not-cache-result                     OK (141 tests, 999 assertions)
$ vendor/bin/phpstan analyse                                   [OK] No errors
$ vendor/bin/phpstan analyse -c phpstan-bench.neon             [OK] No errors
$ php bench/self-test.php                                      harness self-test: 24/24 checks passed.
$ php bench/check-determinism.php bench/corpus/php-parser --algorithm=unified
                                                               determinism check: 4/4 checks passed.
$ php bench/check-determinism.php bench/corpus/phpunit --algorithm=unified
                                                               determinism check: 4/4 checks passed.
$ php bench/check-incremental.php bench/corpus/php-parser      incremental check: 8/8 checks passed.
$ php bench/check-superset.php bench/corpus/php-parser         subsumption check: 3/3 checks passed.
```

Determinism was additionally run on **phpunit**, not only php-parser, because ruling J's change
is about self-pairs and phpunit is the corpus that exercises them: 590 clones, byte-identical
across runs and under a reversed file list.

Two new bench files entered `phpstan-bench.neon`'s explicit list, as that file's own comment
requires.

---

## 3. Recall curves — the instrument, and what it shows

`bench/run-recall.php` is new (there was no runner for the density-parameterized families; the
E2 runner covers only the five type operators). It scores every fetched corpus, every engine
side by side, at function granularity, `--min-tokens=50` (the unified engine's floor is 38, and
50 keeps enough functions eligible while staying above it).

```
$ php bench/run-recall.php --sample=40
Recall curves — sample 40 functions per corpus, --min-tokens=50 (S=25, K=16)

  Curve 1 — recall vs edit density (gapped families)

    operator                 edits/100t pairs   unified     rabin-karp  tokenbag    suffixtree
    gapped_insert_d1         1.34       147      97.3%       52.4%       74.1%      100.0%
    gapped_insert_d2         2.60       129      79.8%       33.3%       77.5%      100.0%
    gapped_insert_d3         3.66       97       70.1%       30.9%       81.4%       85.6%
    gapped_delete_d1         1.34       147      56.5%       25.9%       38.8%       38.1%
    gapped_delete_d2         2.60       129      41.1%       20.2%       27.9%       28.7%
    gapped_delete_d3         3.66       97       27.8%       13.4%       15.5%       17.5%
    gapped_substitute_d1     1.34       147      54.4%       24.5%       42.2%       39.5%
    gapped_substitute_d2     2.60       129      34.9%       19.4%       32.6%       27.9%
    gapped_substitute_d3     3.66       97       20.6%       13.4%       17.5%       17.5%

  Curve 2 — recall vs permutation distance

    operator                 distance   pairs   unified     rabin-karp  tokenbag    suffixtree
    permute_adjacent         adjacent   129      73.6%       31.8%       77.5%       55.8%
    permute_distant          distant    97       61.9%       38.1%       81.4%       46.4%
```

**Reading curve 1.** The unified engine leads Rabin-Karp everywhere, by 2–3× at every density,
and leads TokenBag and the suffix tree on the delete and substitute families at every density.
It trails the suffix tree on the insert family — the suffix tree's edit budget bridges pure
insertions, which is the substitution cost recorded in plan §1 D1 (a divergence whose far side
matches only approximately is not crossed) showing up as a number. Recall falls with density in
every engine, which is the structural property the family exists to expose; the fall is
steepest between d=1 and d=2, where the surviving runs first drop under the seed length.

**Reading curve 2, which carries a decision.** The handoff records that this curve decides
whether TokenBag's M4 removal costs anything real. It does: **TokenBag leads the unified engine
on both permutation operators** (77.5% vs 73.6% adjacent, 81.4% vs 61.9% distant), and the gap
*widens* with distance — the opposite direction from every other engine, and exactly what an
order-blind bag should do. This is the sub-K displacement class ruling A put out of contract
for seeded detection, now quantified rather than asserted: on distant permutations the unified
engine loses about a fifth of what a token bag finds.

**This is evidence for M4, not a decision here.** Recorded so the removal is argued against a
number. Note the counterweight: TokenBag reports *that* the material is all still present
without localizing anything, while the unified engine names the displaced block — and the
precision half of that trade is §7's, not this section's.

## 4. The guaranteed-region gate — fails, and why

**The gate (plan §2 M3): "recall curve shows the guaranteed region (≤ 1 divergence, runs ≥ S)
at 100%."**

```
  The guaranteed region — one divergence, surviving run >= S = 25,
  and within the acceptance contract (span >= 50 at similarity >= 0.85)

    unified       98.6%  (291 of 295)
    rabin-karp    49.2%  (145 of 295)
    tokenbag      74.2%  (219 of 295)
    suffixtree    85.8%  (253 of 295)

    unified missed inside the guaranteed region:
      php-parser       gapped_insert_d1         longest run 49, base 51 tokens, similarity 0.962
      php-parser       gapped_insert_d1         longest run 49, base 51 tokens, similarity 0.962
      php-parser       gapped_insert_d1         longest run 49, base 51 tokens, similarity 0.962
      phpunit          gapped_insert_d1         longest run 48, base 50 tokens, similarity 0.962

  PASS  the guaranteed region has members, so the gate below means something — 295 pairs
  FAIL  the unified engine recalls the whole guaranteed region — 291 of 295
```

### First: the gate's wording collides with plan §1's acceptance rule

Read literally — "≤ 1 divergence, runs ≥ S" — the region is **unsatisfiable by design**, and
this is not the failure above. Winnowing guarantees a run of S tokens is *seeded*. Acceptance
is a separate §1 rule: report only at similarity ≥ 1 − RATIO = 0.85. One divergence can be
large enough to leave a well-seeded run on each flank and still take the pair under 0.85 —
measured, deleting a 19-token statement from a 93-token function leaves a **38-token exact run**
(seeded, well over S = 25) at **similarity 0.796**, which the engine then refuses, correctly.

The runner therefore reports the region **split in two**, and puts the gate on the first half:

```
  Seeded but outside the acceptance contract — the divergence is large
  enough to take the pair under similarity 0.85.

    unified      reported  20.3%  (13 of 64)
    rabin-karp   reported   9.4%  (6 of 64)
    tokenbag     reported  14.1%  (9 of 64)
    suffixtree   reported  12.5%  (8 of 64)
```

64 of the 359 seeded pairs sit outside the contract. **Requesting a ruling on the wording:** is
the guaranteed region "seeded", or "seeded and acceptable"? The split is reported both ways so
the choice is the auditor's; the gate above is placed on the reading that does not ask the
engine to contradict its own acceptance rule. Both the surviving-run length and the similarity
that makes the split are recomputed by plain textbook DP over the token signatures, never by
`BandedAligner` — the component that decides whether to report a pair does not get to answer
whether it should have.

### Second: the four real misses, traced

All four are one shape, and the trace is complete:

```
unit#2 base=51 variant=53  anchors=1
  anchor A[0]<->B[0] len=49
  extend: 1 -> 2 runs
    run A[0]<->B[0] len=49
    run A[49]<->B[51] len=2        <-- the trailing material, recovered
  gap-penalized chain keeps 1 of 2
    kept A[0]<->B[0] len=49        <-- and dropped again
  classify -> A[0,+49) B[0,+49) span=49
```

A 51-token function gains one inserted statement. 49 tokens match contiguously; the 2-token
tail is real matched material; similarity is 0.962. The candidate is reported at **span 49**,
one token under `minTokens = 50`, and refused.

**Three separately-derived rules collide, and no one of them is wrong:**

1. `MINIMUM_RECOVERED_RUN = 4` discards the 2-token tail during extension. Its recorded
   rationale is that `);`, `}`, `return $this;` recur everywhere and matching three of them is
   not evidence of duplication — sound for a *gap interior*, where a spurious short run bridges
   two unrelated regions.
2. The **gap-penalized chain** then drops the tail even when extension does recover it: the run
   gains 2 tokens of coverage and its junction costs a 2-token gap, so it exactly ties and is
   not taken.
3. `minTokens` is a hard floor on the resulting span.

**Measured, not asserted.** I implemented the obvious half of the fix — exempt the two *outer
flanks* from `MINIMUM_RECOVERED_RUN`, on the grounds that §1 Stage D1 specifies "longest common
prefix and suffix at each flank" with no minimum attached, and that a flank run cannot bridge
anything because there is nothing beyond it to bridge to. The trace above is **from that
build**: extension does then recover the tail, and rule 2 discards it anyway. Recall stayed at
291/295, unchanged. Corpus effect was small but real (phpunit 590 → 588 clones, residual
unchanged at 21, php-parser still 3/3).

**I reverted it and am surfacing instead.** Shipping half a fix that changes reported output
without fixing anything is worse than reporting the whole diagnosis, and completing the fix
means changing `ChainBuilder`'s tie-breaking — a component M2 verified against a brute-force
oracle over 3,000 anchor sets. Per plan §3 the failure is the design (an interaction of three
§1 rules at a threshold boundary), so it stops here.

**Ruling requested.** The candidate fix is two parts: (a) exempt outer flanks from
`MINIMUM_RECOVERED_RUN`, and (b) break equal-scoring chains toward greater coverage, on the
grounds that the gap penalty exists to choose between competing *readings* and a contiguous
trailing run (gapA = 0) is the same reading extended rather than a competitor. Both are
mechanism changes, not constant changes; (b) needs `ChainBuilder`'s oracle test re-run.

## 5. Ruling F — wall-clock closure

**The gate does not pass, and I could not close it without a change the handoff reserves for a
ruling.** What was measured, and what was tried:

### Where the time goes, re-measured (phpunit, quiet window)

M2's profile is no longer the right map — ruling J changed the workload:

```
candidates(raw)          0.52s   659 candidates
candidates(normalized)   1.29s   2971 candidates
cross-check samePlace    0.04s
alreadyReported          0.10s   3014 candidates total
classes()                0.19s   567 clones
---                      2.14s   (+ encode 0.30s, fingerprint 0.12s outside)
```

Splitting `candidates()` by pair kind:

```
raw view — phpunit
  self-pairs      54 pairs     0.46s     359 candidates
  cross-pairs    659 pairs     0.02s     300 candidates
normalized view — phpunit
  self-pairs     185 pairs     0.56s     859 candidates
  cross-pairs   8883 pairs     0.36s    2112 candidates
```

**Self-pairs are 73% of candidate time from 239 of 9,596 pairs**, and one file
(`MetadataTest.php`, 0.37s) is a quarter of it. That is ruling F's third recorded candidate —
"collapsing a file's internal repetition before pairing it against every other file" — and it
is now the hot spot precisely because ruling J made the periodic walk do real work.

### Candidate 3, implemented and rejected on the evidence

The extraction loop re-chains and re-extends the *whole* band once per block to rediscover a
period it already knows — quadratic in the block count, on a chain whose flanks are file-sized.
I implemented a one-pass decomposition (`periodSteps()`): take the period once, walk its steps,
classify each on its own anchors.

**Rejected, measured:** the residual stayed at 21 items and php-parser at 3/3, but reported
volume *rose* (590 → 650 clones, 110,976 → 116,969 duplicated lines) for no gain in baseline
coverage, because the outer loop still finds further self-overlapping chains in the leftovers
and re-decomposes overlapping extents. More findings, same recall, more work. The per-round
version already in `b558140` is at least as good on output and less redundant. Reverted.

### Candidate 2, measured but **not** shipped

Sampling the two views at different rates. Measured by constructing a `Winnower` at other
window sizes over phpunit's normalized view; the raw view is untouched throughout, which is
ruling B's own acceptance condition:

```
phpunit — 2695 files, minTokens=70, K=16, shipped W=20 (S=35)

  W     guarantee  density   fingerprints anchors    filePairs
  20    35         9.52 %    34198        73108      23748      <- shipped
  27    42         7.14 %    27580        53172      15788
  34    49         5.71 %    23730        42072      11107
  41    56         4.76 %    21137        34453      7530
  55    70         3.57 %    17568        26203      4769
  70    85         2.82 %    15007        19813      2729
```

At W = 55 the normalized guarantee threshold becomes exactly `minTokens` (70): anchors fall
2.8× and file pairs 5×, on the axis the cost demonstrably lives on. **That is a recall trade,
not a speedup**: it keeps the guarantee for *exact* normalized runs at the reporting threshold
and gives up the guarantee for Type-2 clones carrying a divergence. The handoff says this one
"touches the winnowing guarantee, so it needs the theorem restated, not just a number". I have
not restated the theorem and have not shipped it; the measurement is here so a ruling can be
made against numbers.

Candidate 1 (re-opening ruling B's diversity floor, measured at 3) was not pursued: M2 already
measured that raising it moves symfony-string's reported count non-monotonically without
removing the offending pair, so there is no reason to expect a wall-clock answer there either.

### Position

Even zeroing all self-pair cost leaves phpunit at roughly 1.5 s against a default pipeline of
1.0–1.3 s. **The gate cannot be closed by the sound changes available**, and the two that could
close it are both recall trades reserved for a ruling. Per the handoff: stopping and requesting
a ruling with the measurement attached.

## 6. New finding — seed enumeration exhausts memory on a real application corpus

**This is the most serious finding of the milestone and it was not in scope; the precision
audit ran into it.** The unified engine cannot complete the private dogfood corpus at all.

```
$ php bench/audit-precision.php pool <corpus> --out=<outside-repo>
corpus: 3730 files
  unified … PHP Fatal error:  Allowed memory size of 1073741824 bytes exhausted
    in src/Detector/Strategy/Unified/AnchorSet.php on line 97
```

Raised to 3 GB, it still fails, and the shape is clear:

```
  60    files     0.73s  peak     30 MB  136 clones
  200   files    21.39s  peak    566 MB  952 clones
  600   files    FAILED (3 GB exhausted, AnchorSet.php line 123)
```

Counting the seed pairs without materializing them:

| files | view | fingerprints | seed pairs | longest postings | worst single fingerprint |
|---|---|---:|---:|---:|---:|
| 60 | raw | 2,106 | 16,251 | 98 | 4,753 |
| 60 | normalized | 627 | 73,667 | 140 | 9,730 |
| 200 | raw | 12,067 | 358,939 | 486 | 117,855 |
| 200 | normalized | 710 | 2,037,509 | 762 | 289,941 |
| 600 | raw | 48,135 | 2,324,255 | **1000** | **499,500** |
| 600 | normalized | 769 | **14,662,206** | **1000** | **499,500** |

**The derivation.** `AnchorSet::build()` emits one seed pair per *pair* of postings sharing a
fingerprint, so the work and the memory are Θ(n²) in a fingerprint's posting count.
`FingerprintIndex::POSTINGS_CAP = 1000` bounds the posting **list**, not the pairs it yields: at
the cap, one fingerprint alone emits C(1000, 2) = **499,500** seed pairs. At 600 files both
views sit exactly at the cap, and 14.7 million pairs at PHP array cost is the gigabyte.

This is **the same product-vs-sum argument the auditor granted in ruling D**, one level up.
Ruling D established that `F`, a corpus-wide sum, cannot bound per-file-pair anchors because
those are a *product* of per-file counts. The same reasoning shows a posting-list length cannot
bound the seed pairs that list yields, because those are a *product* too. `F`'s docblock calls
it a boilerplate guard; against boilerplate-by-volume it does nothing, because the quantity it
caps is not the quantity that costs.

Ten times the files gives ~140× the raw seed pairs and ~200× the normalized ones. Growth is
quadratic-plus, and the public benchmark corpora hide it: PHPUnit's 2,870 files scan in seconds,
because the blow-up follows a corpus's **internal repetition**, not its file count.

**Not fixed here.** The remedy is to restate the bound on pairs rather than postings — a
constant's derivation, which §3 reserves. Recorded in the CHANGELOG and README (with `--rk`
named as the workaround for large application codebases) and surfaced for a ruling.

**What it blocks:** the plan's own 2,500-file wall-clock size on any corpus of this shape; the
precision audit as specified ("all locations on the dogfood corpus"); and M4's release, since an
engine that exhausts 3 GB on a 600-file application cannot become the default.

## 7. Precision — built, sampled, and one rater short

### What was built

`bench/audit-precision.php`, in two modes. `pool` runs every engine over a corpus, merges
findings that name the same places into one item (so a clone four engines agree on is rated
once, not four times), samples them on an even stride over a sorted pool, and writes a
worksheet. `score` reads one or two filled worksheets and reports Cohen's κ, precision, and
**Wilson** intervals — Wilson because the sample is ~60 and the proportion sits near 1, where
the normal approximation puts a bound outside [0, 1].

### The rubric, written before any rating

Judging the named regions, not the engine — and the worksheet does not say which engine is
under test:

- **Y — duplicated logic.** The regions carry the same behaviour and a change to one would
  plausibly have to be made to the other. Renamed identifiers, reordered independent
  statements, and a diverged copy all still count: the question is whether a maintainer is
  looking at one thing written twice.
- **N — not duplicated logic.** The regions match on shape while sharing no behaviour — two
  unrelated literal tables that normalize to the same token sequence, an import-and-class
  preamble, a run of accessors that merely look alike. Nothing would have to change together.
- **? — cannot tell from the excerpt.** Counted and reported, and excluded from the proportions
  rather than silently resolved either way.

Ruling H is respected: this is the two-rater protocol, and **no span-statistic filter is
proposed**. The three refuted discriminators (anchor multiplicity, tokens-per-line sparseness,
logic share) are not re-proposed anywhere in this milestone.

### Keeping the corpus unnamed

The dogfood corpus is closed source, so the tool enforces the standing rule rather than relying
on the operator remembering it: the corpus directory is an argument and is never defaulted or
recorded; file paths appear only as a **per-run salted digest**, so two findings in one file can
still be seen to be in one file without that file being identifiable; and the worksheet — which
necessarily quotes source — **refuses to be written anywhere inside this repository**. Verified:

```
$ php bench/audit-precision.php pool <corpus> --out=/Users/.../phpcpd-main/leak.tsv
Refusing to write the worksheet inside the repository (/Users/.../phpcpd-main).
It quotes corpus source, which must never be committed. Choose a path outside it.
$ ls leak.tsv
ls: leak.tsv: No such file or directory
```

Only aggregate numbers appear in this packet.

### What could not be done at the time, and how it was resolved

**The audit was one rater short, and the second was not fabricated.** Cohen's κ measures
agreement between two *independent* raters. Two rating passes by the same agent measure
intra-rater consistency; reporting that as κ would be a fabricated statistic, and the number
the plan asks for (κ ≥ 0.7) exists precisely to catch a rubric that only one reader can apply.
`score` therefore refuses to compute κ from a single worksheet, by design.

**Resolved at the rework verdict:** the auditor session rated all 60 findings as rater B,
blinded — rater A's verdict column stripped mechanically before reading, the attribution key
never opened until B's verdicts were locked. The honesty requirement agreed with it stands and is
restated here because it governs any paper text too: **the raters are two independent Claude
sessions** (executor A, auditor B), blinded to attribution and to each other — never described as
human raters.

### The pool — corrected to the rung that was actually rated (close-audit defect 1)

**The transcript this section first carried was the wrong rung of the corpus ladder.** It showed
588 unified clones over a 600-finding pool, which is the *third* rung — entire-file orphans
dropped, preset excludes not yet honoured for the scan corpus. The worksheet the two raters
actually filled in is the *fourth* rung, and says so in its own header:

```
# Precision audit worksheet — 60 of 74 pooled findings
```

and its companion key, which the raters never saw, gives the per-engine denominators the score
output reproduces:

```
$ grep -v '^#' audit.key.tsv | awk -F'\t' '…count engines…'
53 unified
7 tokenbag
2 rabin-karp
```

Scanned on a **slice**, because §6's memory finding made the whole corpus unscannable at the
time, and an audit has to name the slice it actually covered rather than claim the corpus. The
parameters were `--wired-only --preset=laravel --max-files=400 --sample=60
--engines=unified,rabin-karp,tokenbag`, writing outside the repository.

**The corpus-definition ladder, per ruling K.** Every rung is the tool's own machinery, and the
per-engine counts at each:

| corpus definition | unified | rabin-karp | tokenbag | pool |
|---|---:|---:|---:|---:|
| every `.php` on disk, prefix slice | 952 | 65 | 2 | 1009 |
| every `.php` on disk, even stride | 841 | 56 | 5 | 897 |
| + entire-file orphans dropped | 588 | 13 | 3 | 600 |
| **+ preset excludes honoured for the scan corpus (rated)** | **65** | **2** | **9** | **74** |

**A reproducibility limit, recorded rather than smoothed over.** Re-running the fourth rung's
command today does not reproduce it: the same parameters now yield 998 / 31 / 2 over a pool of
1,030. The corpus is a live working tree, and the difference is traceable to one tool-scratch
subtree of it — an even-strided 400-file slice today draws 134 files from a single dumped
static-analysis cache living there, while the rated pool's findings locate to a *backup*
directory in the same subtree (18 of the 60 rated findings) and not to that dump. Neither
directory is named here, for the same reason nothing else about this corpus is. The rated
worksheet and its key, both preserved
outside every repository, remain the record of what was rated; the ladder above is the auditor's,
recorded at the rework verdict. **Two things follow that are the close audit's to weigh**, and
neither is mine to settle:

1. The dogfood corpus cannot be re-pooled to a byte-identical sample, so any re-pooling (the M4
   release-gate option ruling K leaves open) will produce a *new* sample, not a reproduction of
   this one.
2. Roughly 30 % of the rated pool came from a backup directory — saved copies of live
   files. Ruling K's definition does not remove them and cannot: a backup of a live file declares
   the same symbols, so `Orphans::detect()` sees a wired file, and no preset excludes it. Whether
   a backup tree is "program text" is exactly the kind of exclusion ruling K reserves for a
   recorded ruling **before** it is applied, so nothing was excluded here. It is flagged because
   it plausibly moves the precision number, and in the direction that flatters no one: saved
   near-duplicates are where a clone detector reports most.

Three corrections were needed to get a sample worth rating, and each is a finding in itself.

**1. The suffix tree had to be excluded, and the reason is a number.** On this corpus it takes
**199 s for 50 files** against the unified engine's 0.63 s, and does not finish 100 files in ten
minutes. `--engines=` exists so an audit names the engines it pooled instead of hanging.

**2. Taking the first N files sampled one corner of the tree.** The first 200 files of this
corpus in sorted order were **196 files of a single scratch directory** full of saved
near-duplicates, which reported 952 clones — the audit would have been a study of that
directory. The slice is now an even stride over the sorted tree, which is equally reproducible
and spreads over every part of it.

**3. Scanning every `.php` file on disk and calling it "the corpus" was naive, and this
repository already knew better.** `--orphans` exists to say which files nothing references, and
duplication among dead files, generated caches and scratch copies is a different question from
duplication in live code. `--wired-only` now puts the corpus through `Orphans::detect()` first
and drops every entire-file orphan. The effect is out of all proportion to its size:

| | files | unified clones | rabin-karp clones |
|---|---:|---:|---:|
| every `.php` file on disk | 400 | 841 | 56 |
| entire-file orphans dropped first | 400 | **588** | **13** |

**146 of 3,731 files — 3.9% — were entire-file orphans, and removing them removed 30% of the
unified engine's findings and 77% of Rabin-Karp's.** Orphaned files are disproportionately
duplicated, which is what dead and copy-pasted code is. Any precision number taken over the
unfiltered disk would have been measuring the corpus's housekeeping rather than the engine.

**The volume question stands even on wired code.** On 400 wired files the unified engine reports
588 clones against Rabin-Karp's 13 — 45×, against 2.3× on phpunit. That is the number the
two-rater audit exists to interpret, and it is the strongest reason not to report a precision
figure from one rater's verdicts.

### The two raters' verdicts, and the gate

I rated all 60 as rater A, against the rubric above, reading the excerpts; the auditor rated all
60 as rater B, blinded. Both worksheets and the key are preserved outside every repository. The
scored output, verbatim:

```
$ php bench/audit-precision.php score audit.tsv audit-raterB.tsv
rater A: 60 findings, 60 rated
rater B: 60 findings, 60 rated

both rated:        60 findings
observed agreement 0.950
expected agreement 0.511
Cohen's kappa      0.898  (meets the 0.7 the brief requires)

rater A precision over the sampled pool: 25/60 = 0.417
Wilson 95% interval:                     [0.301, 0.543]
unrateable ('?'):                        0

precision per engine over the rated sample (rater A):
  rabin-karp   2/2   = 1.000   Wilson [0.342, 1.000]
  tokenbag     7/7   = 1.000   Wilson [0.646, 1.000]
  unified      18/53  = 0.340   Wilson [0.227, 0.474]

rater B precision over the sampled pool: 26/60 = 0.433
Wilson 95% interval:                     [0.316, 0.559]
unrateable ('?'):                        0

precision per engine over the rated sample (rater B):
  rabin-karp   2/2   = 1.000   Wilson [0.342, 1.000]
  tokenbag     7/7   = 1.000   Wilson [0.646, 1.000]
  unified      19/53  = 0.358   Wilson [0.243, 0.493]

A finding several engines reported counts once for each of them, so the
rows are not disjoint and do not sum to the total above.
```

Producing that output needed a fix to the instrument, committed separately: `score` computed κ
from two worksheets and then reported precision from the first alone, which left the gate's own
comparison resting on one rater while the agreement statistic beside it came from two.

**The gate — "precision not below the better of RK/TokenBag on the audited set" — fails under
both raters, and not marginally.** The unified engine is at 0.340 (A) and 0.358 (B) where both
baselines are at 1.000. The baselines' samples are small (2 and 7 findings) and their intervals
are correspondingly wide, so the honest statement is bounded rather than absolute: unified's
interval upper bound (0.474 under A, 0.493 under B) sits **below TokenBag's lower bound (0.646)**
under either rater, which is a separation the sample supports; against Rabin-Karp's lower bound
(0.342) it is not separated, because two findings cannot separate anything. On this evidence the
gate fails against TokenBag on the interval, and against Rabin-Karp on the point estimate only.

The three disagreements (findings 016, 024, 030) are one rubric edge — parameterized wiring and
micro-tests, where "adapted copy" shades into "boilerplate by design" — recorded as the rubric's
known soft boundary. At κ = 0.898 it does not threaten the instrument. Rater B's false-positive
tally matches rater A's shape table: 28 of B's 34 `N`s are literal data tables, and **26 of the 34
come from two files** (a dumped menu array, an ISO region table). The engine's data-table shape is
not spread thinly across an application; it is a small number of files reported over and over.

**The false positives are one shape, and it is ruling H's.** Of 35 `N` verdicts:

| shape | count | example |
|---|---:|---|
| literal data tables matching each other | 28 | a menu seed array and an ISO region/province table, two windows of one big literal |
| route-declaration lists | 5 | runs of `Route::get(...)->name(...)`, framework boilerplate by design |
| test-fixture idiom across method boundaries | 2 | two unrelated tests sharing `$key = …uniqid(); call; assert` |

Twenty-eight of thirty-five are the **normalized data-table shape M2 confirmed and ruling H
assigned to this protocol** — every literal folds to one token, so "an array of records" matches
"an array of records". On the public corpora that shape produced 3 of symfony-string's 13
findings; on a real application whose seed data and ISO tables are large, it produces most of
what the engine reports.

**The 25 true positives are the capability the engine was built for**, and worth naming so the
number is not read as "the engine is wrong": an identical PDO singleton copied across two files;
`store()` and `update()` sharing a whole validation-and-normalization body; `favicon_meta_tags()`
and `favicon_admin_meta_tags()` carrying the same six-entry table and the same rendering loop;
a 20-line query written twice in one file; a three-method delegation pattern repeated per
metric. Those are findings a maintainer would act on.

**What this means for ruling J.** The fine-granularity emission ruling J required increases
reported volume, and this audit prices that increase: at 0.34 precision on this corpus, more
findings are mostly more data-table matches. That is an argument about the *diversity guard*
(ruling B) and about structural context (ruling H's one open avenue), not against ruling J —
but it belongs in the same ruling, because both change what a user sees.

## 8. Wall-clock at the plan's four sizes — the gate, run

`bench/check-walltime.php` gained `--sizes=`, because the gate reads "at **every size**" and a
whole-corpus number cannot answer that. A size is the first N files of the corpus in sorted
order, so the same N is the same file set on every run and between engines.

phpunit is the corpus used for the sweep: it is the only fetched corpus with more than 2,500
files, and the dogfood corpus — the other candidate — cannot be scanned at that size at all
(§6).

```
$ php bench/check-walltime.php bench/corpus/phpunit --sizes=60,200,600,2500
Wall-clock — 5 runs per configuration, --min-tokens=70

  bench/corpus/phpunit — 60 files
    default (rk+tokenbag)  median   0.015s   spread  0.001s   ...    2 clones
    unified                median   0.018s   spread  0.004s   ...    8 clones
  bench/corpus/phpunit — 200 files
    default (rk+tokenbag)  median   0.031s   spread  0.007s   ...    8 clones
    unified                median   0.054s   spread  0.005s   ...   22 clones
  bench/corpus/phpunit — 600 files
    default (rk+tokenbag)  median   0.128s   spread  0.011s   ...   13 clones
    unified                median   0.218s   spread  0.016s   ...   67 clones
  bench/corpus/phpunit — 2500 files
    default (rk+tokenbag)  median   0.556s   spread  0.087s   ...  127 clones
    unified                median   1.318s   spread  0.209s   ...  298 clones

wall-clock check: 4 of 4 checks FAILED
```

| files | default | unified | ratio | unified clones vs default |
|---:|---:|---:|---:|---|
| 60 | 0.015 s | 0.018 s | **0.83×** | 8 vs 2 |
| 200 | 0.031 s | 0.054 s | **0.58×** | 22 vs 8 |
| 600 | 0.128 s | 0.218 s | **0.59×** | 67 vs 13 |
| 2,500 | 0.556 s | 1.318 s | **0.42×** | 298 vs 127 |

**All four fail, and the ratio degrades with size** — 0.83× at 60 files to 0.42× at 2,500. That
degradation is the finding, not the individual numbers: unified is not merely slower by a
constant factor, it scales worse than the default pipeline over the range the gate covers, and
§6 shows where that curve goes on a corpus with more internal repetition.

Stated fairly: unified is also *reporting* 2.3× as many clones at 2,500 files, and some of that
cost is the cost of finding more. That does not rescue the gate, which is stated as wall-clock
and not as wall-clock per finding.

Spreads are tight (≤ 0.21 s at the largest size), so these are not noise. Earlier readings in
this session taken while the machine was loaded are not reported; the M2 packet's numbers and
these differ by machine conditions as well as by ruling J's added work, so they are not
directly comparable and no delta is claimed between them.

---

## 9. Deviations, and what was measured but not shipped

Recorded rather than silent, per the plan.

1. **A partial fix for §4 was implemented, measured, and reverted** — exempting the outer
   flanks from `MINIMUM_RECOVERED_RUN`. It is a correction of a real gap between §1 Stage D1's
   wording ("longest common prefix and suffix at each flank", no minimum) and the
   implementation, and it does what it claims; it does not fix the gate, because the
   gap-penalized chain then discards the recovered run. Not shipped, because half a fix that
   changes reported output without achieving anything is worse than a complete diagnosis.
2. **Ruling F candidate 3 was implemented, measured, and reverted** — the one-pass period
   decomposition (§5). More reported volume, identical recall, more work.
3. **Ruling F candidate 2 was measured but not implemented** (§5). It is a recall trade and the
   handoff reserves it for a ruling.
4. **`bench/check-walltime.php` and `bench/audit-precision.php` gained options**
   (`--sizes=`, `--max-files=`). Both exist because a gate could not otherwise be asked as the
   plan words it; neither changes a default.
5. **No constant was changed anywhere in `src/`.** `SITE_OVERLAP`, `PER_FILE_CAP`,
   `POSTINGS_CAP`, `RATIO`, `THETA`, `DISPLACED_MASS_FLOOR`, `NORMALIZED_DIVERSITY_FLOOR`,
   `MINIMUM_RECOVERED_RUN` and `GAP_SEARCH_SPAN` all stand at their M2 values.
6. **Provenance.** One row is owed if the auditor accepts §1's periodicity mechanism into the
   plan:

   | Component | Published source | License posture |
   |---|---|---|
   | Periodicity of a self-overlapping alignment (block decomposition) | Lothaire, *Combinatorics on Words*, the definition of a period | classical; no implementation consulted |

   No clone detector's source was opened, and `SuffixTree/` was used only through
   `Engine::strategyFor()` as a scored baseline.

---

## 10. Reproducing

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/phpstan analyse -c phpstan-bench.neon --memory-limit=1G
php bench/self-test.php
php bench/check-chaining.php
php bench/check-determinism.php bench/corpus/php-parser --algorithm=unified
php bench/check-determinism.php bench/corpus/phpunit    --algorithm=unified
php bench/check-incremental.php bench/corpus/php-parser
php bench/check-superset.php bench/corpus/php-parser
php bench/check-superset.php bench/corpus/phpunit
php bench/run-recall.php --sample=40
php bench/check-walltime.php bench/corpus/phpunit --sizes=60,200,600,2500
php bench/audit-precision.php pool <corpus> --max-files=100 --out=<path outside this repo>
php bench/audit-precision.php score <rater-a.tsv> [<rater-b.tsv>]
```

---

## 11. Status

Ruling J is implemented with its derivation and its measurement; the acceptance instrument
moved 66 → 21 items and php-parser held at 3/3. The measurement program is built and run, and
its instruments are in the repository and at level max.

**Four things are open and none of them is mine to close:**

1. **Ruling F cannot be closed** by the sound changes available (§5). Both changes that could
   close it are recall trades the handoff reserves for a ruling; the measurements are attached.
2. **The guaranteed-region gate fails 291/295** (§4), from an interaction of three §1 rules at a
   threshold boundary. Its wording also needs settling: read literally the region is
   unsatisfiable against §1's own acceptance rule, and both readings are reported.
3. **Seed enumeration exhausts memory on a real application corpus** (§6). This was not in M3's
   scope, blocks the plan's own 2,500-file size on such corpora, blocks the precision audit as
   specified, and would block M4. The derivation is the auditor's ruling-D argument one level
   up; the constant is untouched.
4. **The precision gate fails** (§7): unified at 0.340 precision against RK and TokenBag at
   1.000, with 28 of 35 false positives being ruling H's confirmed data-table shape. Rater A's
   verdicts are complete; κ awaits the auditor as rater B, by the project owner's decision.

Awaiting the audit verdict. The executor does not close its own milestone.

---

AUDIT: **rework — rulings K–N recorded, rater B complete, close conditions set.**
2026-09-01, Fable session.

Every gate in this packet was re-run by the auditor before anything below was
written: suite 141/141, phpstan clean on both configs, self-test 24/24,
determinism 4/4 on phpunit, incremental 8/8, superset php-parser 3/3, superset
phpunit failing at exactly the packet's residual (1 location + 20 unexplained =
21 items), recall 291/295 with the same four traced misses, wall-clock 4/4
failing at 0.76× / 0.60× / 0.58× / 0.46× in a quiet window — confirming both the
failure and the degradation-with-size, which is the finding. Ruling J's diff was
spot-read: no constant introduced, the period read off the candidate, the
identity exclusion genuinely Stage C's own rule applied where it was missing.
The provenance row (Lothaire — a definition, no implementation) is accepted and
now in the plan's table.

### Rater B, and the precision result made two-rater

The auditor rated all 60 findings as rater B, blinded: rater A's verdict column
was stripped mechanically before reading, the attribution key was never opened
until B's verdicts were locked, and the packet's aggregate (0.340, "28 of 35 one
shape") was known but no per-finding verdict was.

```
rater B: 26 Y / 34 N / 0 ?
observed agreement 0.950  (57 of 60)
Cohen's kappa      0.898  — the brief requires ≥ 0.7
per-engine, rater B:  unified 19/53 = 0.358  Wilson [0.243, 0.493]
                      rabin-karp 2/2, tokenbag 7/7 = 1.000
```

**The precision gate fails under both raters, and the failure is statistically
real where the sample can say so:** unified's upper bound (0.474 under A, 0.493
under B) sits below TokenBag's lower bound (0.646) under either rater. Against
Rabin-Karp's two findings nothing separates, and the packet was right to say so.

The three disagreements — findings 016, 024, 030 — are all the same rubric edge:
parameterized wiring blocks and micro-tests, where "adapted copy" shades into
"boilerplate by design". Recorded as the rubric's known soft boundary; at κ 0.898
it does not threaten the instrument.

Rater B's independent false-positive tally matches rater A's shape table almost
exactly: 28 of B's 34 N's are literal data tables, and the concentration is
worth stating — **26 of the 34 come from two files** (a dumped menu array at 20
sampled findings, an ISO region table at 6). The engine's data-table shape is
not spread thinly across an application; it is a small number of files reported
over and over. Both raters' Y's are the same capability list the packet names:
copied PDO bootstrap, store()/update() bodies, per-metric delegation trios,
adapted mail handlers and query pipelines. The rubric held; the honesty
requirement stands as agreed: **the packet and any paper text name the raters as
two independent Claude sessions** (executor A, auditor B), blinded to attribution
and to each other — never as human raters.

### Rulings

- **K (corpus definition) — granted and frozen**, now in plan §2 M3. Every rung
  is the tool's own pre-existing machinery (verified: presets in the baseline
  commit), the ladder goes in this packet with per-engine counts, and nothing
  else is excluded without a ruling *first*. The refusal to write the worksheet
  inside the repo, the salted digests, and the suffix-tree exclusion by number
  (199 s / 50 files) are all accepted as recorded.
- **L (guaranteed-region wording) — the region includes the acceptance
  contract.** Winnowing guarantees seeding; the 0.85 floor is a deliberate §1
  precision rule, and a gate that demands reporting below it demands the engine
  contradict its own design. The runner's split reporting becomes the gate's
  permanent shape. Plan amended.
- **M (the four misses) — the two-part fix is granted** with its conditions
  (flank exemption per §1's own wording; equal-score chains break toward
  greater coverage as a total-order completion; oracle extended and re-run;
  recall gate expected 295/295, any remainder re-traced, not tuned). The
  executor's decision to revert the half-fix rather than ship output churn that
  fixes nothing was correct conduct, as was refusing to touch the oracle-tested
  component without a ruling.
- **N (seed-pair bound) — granted as ruling D's argument one level up**, with
  the value derived by ruling D's method on the corpora that exhibit the
  failure. Conditions in the plan. This is the finding that blocks everything
  downstream (the 2,500-file size on real corpora, the full-corpus precision
  audit, M4), and it was correctly surfaced instead of patched.
- **F — re-sequenced after M and N**; if the gate still fails, candidate 2 goes
  to the project owner as a recall-vs-speed decision with the guarantee
  restated in draft. Candidate 3's implement-measure-revert is accepted;
  candidate 1's non-pursuit is accepted on M2's own evidence.
- **Precision — gate recorded failing; the fix is sanctioned and has its
  fixture.** The 57 consensus-labelled findings are the acceptance test for
  ruling H's structural-context avenue, alongside the public-corpus
  regressions. No default flip while the audited-set precision sits below the
  better baseline.
- **The 400-file wired slice is accepted as M3's precision measurement** — a
  named, reproducible slice honestly labelled, which the memory finding forced.
  Re-pooling the full corpus after ruling N is M4 release-gate evidence at the
  owner's option, not an M3 obligation; κ = 0.898 says the instrument works.
- **TokenBag/M4**: curve 2 and the 7/7 rating are the removal decision's
  required citations, per ruling A's reservation. Not decided here.

### Defects found by this audit

1. **§7's pool transcript is stale.** It shows the third rung of the corpus
   ladder (588 unified clones, 600 pooled) while the rated worksheet is "60 of
   74" — the fourth rung (65 / 9 / 2 by engine), which is what the per-engine
   denominators (53 / 7 / 2) confirm. The packet must be corrected to the pool
   that was actually rated, with the full ladder table (1009 → 897 → 600 → 74)
   and per-engine counts at each rung, per ruling K.
2. Minor, recorded without condition: the CHANGELOG entry for `9fba006`'s fix
   rode in `b558140`'s commit. The entries themselves are separate, so the
   one-entry-per-change rule is met; pairing entry with commit is preferred.

### M3 closes when

1. §7 is corrected to the rated pool with the ladder table (defect 1).
2. Ruling M is implemented with its conditions and the recall gate re-run.
3. Ruling N is implemented with its conditions and the 600-file slice scans.
4. The wall-clock sweep re-runs after M and N, honest numbers recorded either
   way; a still-failing gate goes to the owner with candidate 2's draft.
5. CHANGELOG and README match reality after M and N, one entry per change.
6. The final numbers are appended to this packet. Then the close audit runs.

The conduct findings stand on their own: three implement-measure-revert cycles
recorded instead of shipped, a memory blow-up surfaced with its derivation
instead of a tuned constant, a fabricated-κ refusal, and a corpus definition
that got *stronger* every time it was challenged. This is what the process is
for.

---

# Close section — the executor's response to the rework verdict

Written after `02e07fd`, against the six conditions the verdict set. The
rulings' own numbers are here; the close audit's verdict is not mine to write.

## Step 0 — the rater worksheets are preserved

Both filled worksheets and the attribution key were copied out of the session
scratchpad, before anything else, to a durable directory **outside every
repository**, mode 600, checksums verified identical to the originals. They are
the record behind κ = 0.898 and the acceptance fixture ruling H's open avenue
will need. Their location is deliberately not written here, because the path is
of no use to a reader and the files quote closed-source corpus text.

## 1. §7 corrected (defect 1) — done, with one thing surfaced

§7's pool transcript now shows the rung that was actually rated (74 pooled, 60
written, per-engine 65 / 2 / 9, confirmed by the worksheet header and the key's
53 / 7 / 2 denominators), carries the full four-rung ladder with per-engine
counts, and quotes the two-rater `score` output verbatim.

**Surfaced there and repeated here because it is the close audit's to weigh:**
the fourth rung's command does not reproduce on today's tree — the same
parameters now pool 1,030 findings where the record says 74. The dogfood corpus
is a live working tree; the difference traces to one tool-scratch subtree of it,
and the rated pool's findings locate to a backup directory there (18 of 60)
rather than to the dumped static-analysis cache an even-strided slice draws
today. Neither directory is named, here or in §7. Two consequences
are recorded in §7: the sample cannot be reproduced byte-for-byte, and about
30 % of it came from a backup subtree that ruling K's definition neither removes
nor can remove. **No exclusion was added** — ruling K reserves that for a ruling
made first.

## 2. Ruling M — implemented in two commits, and the gate it targets passes

| | |
|---|---|
| `7f6d766` | M(a) — the two outer flanks are exempt from `MINIMUM_RECOVERED_RUN` |
| `35f6760` | M(b) — equal-scoring chains are settled by coverage |

**The recall gate passes: 295 of 295.** The four traced misses are gone, and no
new one appeared:

```
  The guaranteed region — one divergence, surviving run >= S = 25,
  and within the acceptance contract (span >= 50 at similarity >= 0.85)

    unified      100.0%  (295 of 295)
    rabin-karp    49.2%  (145 of 295)
    tokenbag      74.2%  (219 of 295)
    suffixtree    85.8%  (253 of 295)

  PASS  the guaranteed region has members, so the gate below means something — 295 pairs
  PASS  the unified engine recalls the whole guaranteed region — 295 of 295
```

**The oracle condition is met, and made falsifiable.** `bench/check-chaining.php`
is new and committed, so the claim can be re-run rather than taken on trust: it
enumerates every colinear subset of 3,000 seeded anchor sets under both
weightings and compares against the DP — 6,000 comparisons, 0 mismatches, and it
also checks that the returned chain is colinear and weighs what it reports. Two
things were done to keep it from being a test that passes vacuously. The seeded
generator's first form silently collapsed to a constant (an integer overflow to
float), which showed up as **0 sets holding a coverage tie**; fixed, and a second
denser family added, it reports **84 sets** where two chains tie on score and
differ on coverage. And it was run against the *old* tie order, where it fails
with coverage mismatches — an oracle that passes either way tests nothing.

**Superset gates.** php-parser 3/3. phpunit's residual **grew by one item, from
21 to 22**, and the verdict's condition was "not grown *unexplained*", so here is
the explanation, traced to the end and not tuned away.

The new item is
`assertArraysAreEqualIgnoringOrderTest.php:157 ↔ assertArraysAreIdenticalIgnoringOrderTest.php:119`
— 98 tokens the two files genuinely share, which the baseline reports and unified
now does not. Instrumenting the cluster loop on that file pair:

```
M(a):  round type-1  A[34,+193)  B[31,+193)  sim=1.000  accepted   <- reported
       round type-1  A[325,+98)  B[284,+98)  sim=1.000  accepted   <- reported
       round type-1  A[526,+82)  B[502,+82)  sim=1.000  accepted
M(b):  round gapped  A[34,+389)  B[31,+351)  sim<0.85   REFUSED
       round type-1  A[526,+82)  B[502,+82)  sim=1.000  accepted
```

The chosen chain is `[[34,31,193],[305,244,20],[325,284,98]]`: score
`193 + 20 − 78 − 20 + 98 − 0 − 20 = 193`, which ties **exactly** with the lone
193-token run, and covers 311 tokens against 193. Ruling M(b) says take the wider
coverage, so it is taken — and the wider reading spans 389 tokens across 118
tokens of divergence, fails the 0.85 acceptance, and is refused.

**The loss is not the tie rule; it is what happens to a refused candidate.**
`fromCluster()` calls `withoutSpan()` *before* `verify()`, so a candidate refused
on similarity consumes its whole span from the anchor set and the two exact
clones inside it are never found again. M2's `64a4dd4` fixed exactly this, but
only for the self-overlapping case. Returning a similarity-refused candidate's
evidence the way a self-overlapping one's is returned is a mechanism change, so
it is **recorded, not made** — it needs its own ruling.

## 3. Ruling N — implemented, with the derivation, at `fcfe717`

`AnchorSet::SEED_PAIR_CAP = 16000`, bounding **pairs emitted per fingerprint**.

**The value, by ruling D's method.** Dense coverage — the union of reported source
lines restricted to clones at ≥ 4 tokens per line — swept on phpunit:

| cap | 1,000 | 2,000 | 4,000 | 8,000 | **16,000** | 32,000 | 64,000 | uncapped |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| dense | 8,614 | 8,661 | 8,964 | 8,951 | **9,036** | 9,036 | 9,036 | 9,036 |
| clones | 545 | 552 | 561 | 563 | **564** | 564 | 564 | 564 |

16,000 is the knee: the smallest cap that reports every dense line an uncapped run
reports. It was **not** chosen on wall-clock, which it makes worse (3.4 s at 1,000
against 4.2 s uncapped), and **not** on the memory failure, which is checked after.

**On the dogfood corpus, and a conflict the close audit should settle.** Ruling N
says to measure on "the 600-file dogfood slice — the corpus that exhibits the
failure". Under **ruling K's frozen definition** (presets + `--wired-only`), a
600-file slice does *not* exhibit it: uncapped it completes at 946 MB, inside the
default 1 GB. The slice that reproduces §6's failure exactly is the one §6's probe
used — the first 600 files in sorted order under `bcb_files()`'s four-dir prune,
which on this corpus is a dumped static-analysis cache. Both are measured, and the
distinction matters because the naive slice must not be allowed to set a constant:

| cap | §6 slice (naive, exhibits the failure) | ruling-K slice (program text) |
|---|---|---|
| uncapped | **1 GB exhausted, no result** | 946 MB, dense 244 |
| 32,000 | **1 GB exhausted, no result** | 860 MB, dense 244 |
| 16,000 | 744 MB, completes, dense 40 | 860 MB, dense 244 |
| 8,000 | 540 MB, dense 40 | 860 MB, dense 244 |
| 1,000 | 316 MB, dense 40 | 860 MB, dense 244 |

Dense coverage on the dogfood slices is flat at every cap — 244 lines on program
text, 40 on the cache dump — because that corpus's duplication is literal data
tables, not dense code. So it can *confirm* a value but cannot choose one, and the
value comes from phpunit's knee. That the knee also happens to be the largest swept
value at which the failing slice completes in default memory is reported as what it
is: an acceptance result, arrived at afterwards, not the derivation. Had the knee
landed at 32,000 the two would have conflicted and this section would say so.

**Acceptance.** The 600-file slice that exhausted the default limit now peaks at
744 MB and finishes; php-parser superset 3/3; phpunit residual unchanged by N (it
stands at M(b)'s 22). Counted and surfaced like `F` and `C`, through
`pairCappedFingerprintCount()` and `discardedSeedPairCount()`, with two tests
pinning the accounting. Streaming the enumeration was not attempted; the bound
does not depend on it.

**One dissent, recorded and complied with.** `IndexCodec::VERSION` is bumped 5 → 6
as the ruling requires, but unlike versions 4 and 5 the stored bytes are
**unchanged** — the cap applies after the index, to how a posting list is
enumerated, not to which fingerprints a file stores. The bump therefore
invalidates every user's warm cache for no decoding reason. It is made because the
ruling says to; the code comment names the single line to revert if the close audit
judges the cost not worth it.

## 4. Wall-clock, re-run after M and N — still fails

```
$ php bench/check-walltime.php bench/corpus/phpunit --sizes=60,200,600,2500
  60 files     unified 0.017s  vs default 0.013s   0.79x   FAIL
  200 files    unified 0.053s  vs default 0.030s   0.57x   FAIL
  600 files    unified 0.194s  vs default 0.113s   0.58x   FAIL
  2500 files   unified 1.156s  vs default 0.489s   0.42x   FAIL

wall-clock check: 4 of 4 checks FAILED
```

Against the auditor's own re-run at the rework verdict (0.76× / 0.60× / 0.58× /
0.46×) this is unchanged inside the noise, and the degradation with size is
unchanged too. **Ruling N did not buy wall-clock**, which is the honest reading:
it bounds a quantity that blows up on internally repetitive corpora, and phpunit
is not one — its worst fingerprints never reach the cap.

### Candidate 2 — the restated guarantee, drafted for the project owner

**Not implemented.** Ruling F reserves this for the owner as a recall-versus-speed
product decision. The measurement is in §5; the guarantee it would replace is
drafted here so the decision is made against a written contract rather than a
number.

*Current guarantee (both views, W = 20, K = 16).* Any common run of at least
`S = W + K − 1 = 35` tokens is guaranteed to be selected by winnowing, so any pair
of copies sharing such a run is seeded; whether it is then **reported** is decided
separately by the acceptance rule (span ≥ `--min-tokens`, similarity ≥ 0.85).

*Proposed guarantee at W = 55, normalized view only.* The raw view is untouched
and keeps the guarantee above in full. For the normalized view the window becomes
55, so `S = 70`, and the guarantee narrows to:

> Any pair of copies sharing a common **normalized** run of at least
> `S = 70` tokens is guaranteed to be seeded. Where `--min-tokens ≤ 70`, this is
> exactly the guarantee that a Type-2 clone reported as a *single exact normalized
> run* at or above the reporting threshold cannot be missed. It is **not** a
> guarantee for a Type-2 clone carrying a divergence: two renamed copies that
> differ in the middle may leave no single normalized run of 70 tokens, and such a
> pair is then seeded only by chance rather than by theorem.

*What is given up, stated as capability rather than as a number.* Type-3 detection
under renaming — the case where both a rename *and* an edit are present — stops
being guaranteed and becomes best-effort. Type-1 and raw-view Type-3 are unaffected.
Type-2 without divergence is unaffected at any `--min-tokens ≤ 70`; above 70 the
guarantee is strictly weaker than the reporting threshold and the contract should
say so plainly rather than imply coverage it no longer has.

*What is bought.* On phpunit, normalized anchors fall 2.8× and file pairs 5×.
Whether that closes a gate failing at 0.42× is **not established** — §5's own
position is that zeroing all self-pair cost still leaves phpunit at roughly 1.5 s
against a 1.0–1.3 s default pipeline, so candidate 2 should be measured, not
assumed, before it is accepted.

*Recommendation, offered and not taken.* If the owner's aim is M4's default flip,
this trade alone is not evidently sufficient, and it is paid for in the one
capability the unified engine has that no baseline does. Accepting a measured
speed ratio for 2.0.0 while the engine stays opt-in looks like the cheaper option;
that too is the owner's call, not mine.

## 5. Docs

CHANGELOG has one entry per change, each in its own commit — the pairing the
verdict's second defect asked for:

| commit | entry |
|---|---|
| `7f6d766` | flanks recover runs with no minimum (M(a)) |
| `35f6760` | equal-scoring chains settled by coverage (M(b)) |
| `b7721ad` | `score` reports per-engine precision for both raters |
| `fcfe717` | the seed bound counts pairs, not places (N) |
| `23a7de7` | README matched to the engine (documentation of the above; no entry of its own) |

README's three open items are re-stated against what is now measured: the memory
failure moves to a "fixed since" note with its numbers, speed takes its place with
the four ratios, precision joins the list with the two-rater result, the residual
reads 22, and the caps are three rather than two.

## 6. Final numbers

```
vendor/bin/phpunit                                   143/143
vendor/bin/phpstan analyse                           no errors
vendor/bin/phpstan analyse -c phpstan-bench.neon     no errors
php bench/self-test.php                              24/24
php bench/check-chaining.php                         2/2   (6,000 comparisons, 84 tie sets)
php bench/check-determinism.php php-parser           4/4
php bench/check-determinism.php phpunit              4/4
php bench/check-incremental.php php-parser           8/8
php bench/check-superset.php php-parser              3/3
php bench/check-superset.php phpunit                 FAIL — 22 items (1 location + 21 pairs)
php bench/run-recall.php --sample=40                 2/2   — guaranteed region 295/295
php bench/check-walltime.php phpunit                 FAIL — 4/4 sizes, 0.79/0.57/0.58/0.42x
precision (two raters, κ 0.898)                      FAIL — unified 0.340/0.358 vs 1.000
```

Corpus effect of the milestone's changes, for the record: phpunit 590 → 587
clones, php-parser 82 → 81.

**Open and not mine to close** — four, of which two are new:

1. **Wall-clock still fails at all four sizes.** Candidate 2's restated guarantee
   is drafted above for the project owner; it is not implemented.
2. **Precision still fails** under both raters. The fix is ruling H's
   structural-context avenue, out of this close's scope, with its 57-finding
   fixture preserved.
3. **New: a similarity-refused candidate consumes the evidence inside it.**
   Traced in §2 above. It costs two real exact clones on one phpunit file pair and
   is what took the residual from 21 to 22. The fix is a mechanism change and
   needs a ruling.
4. **New: the rated pool cannot be reproduced, and ~30 % of it is a backup
   subtree** that ruling K's definition does not reach. Recorded in §7; no
   exclusion applied, because ruling K reserves that for a ruling made first.

Awaiting the close-audit verdict. The executor does not close its own milestone.

---

AUDIT: **pass — M3 closed. Rulings O, P recorded into M4; dissent upheld and
the version bump reverted (ruling Q).** 2026-09-01, Fable session.

Everything below was verified by re-running it, not by reading it: suite
143/143 · phpstan clean, both configs · self-test 24/24 · chaining oracle 2/2
(6,000 comparisons, 84 genuine tie sets, 0 mismatches) · determinism 4/4 on
both corpora · incremental 8/8 · superset php-parser 3/3 · superset phpunit
failing at exactly the recorded 22 items (1 location + 21 pairs) · **recall
295/295, 2/2 — ruling M's target hit** · wall-clock 4/4 failing at
0.78× / 0.52× / 0.59× / 0.42×, unchanged inside noise. The M(a) and M(b) diffs
were spot-read: the flank exemption is §1's own wording closed against the
implementation with the gap floor intact, and the tie-break is a lexicographic
completion carried at all three choice points with the exchange argument
restated. The oracle was checked for the two failure modes that would make it
vacuous — it counts its tie sets (84, after the generator's overflow was
caught) and it fails against the old order. That is how an oracle condition
should be discharged.

### The six close conditions — all met

1. §7 corrected to the rated rung with the ladder and the verbatim two-rater
   output; the reproducibility limit and the backup-subtree contamination
   surfaced rather than smoothed. Met, and better than met.
2. Ruling M implemented in two commits with the oracle extended, re-run, and
   made falsifiable. Recall 295/295. The residual grew 21 → 22 **explained and
   fully traced** — the condition's own wording ("not grown *unexplained*") is
   met, and the trace correctly locates the loss not in the tie rule but in
   refusal consuming evidence. That is ruling O now (below).
3. Ruling N implemented with the derivation by ruling D's method, the
   accounting counted and tested, and the two-corpora conflict surfaced
   instead of resolved by fiat. The judgement that dense coverage on the
   dogfood slices *confirms but cannot choose* a value — flat at every cap,
   because that corpus's duplication is data tables — is accepted as the
   correct reading of ruling N's intent: the naive cache-dump slice was the
   right place to check the memory acceptance and would have been the wrong
   place to set a constant. The knee coinciding with the passing cap is
   recorded as coincidence, which is the honest form.
4. Wall-clock re-run and recorded failing; candidate 2 drafted as a written
   contract with what is given up stated as capability, not a number, plus a
   recommendation that correctly stops short of deciding. It now sits with
   the project owner, in the plan's M4 decision points.
5. Docs match; entries pair with commits.
6. Final numbers appended.

### Ruling Q — the dissent is upheld, and the audit applied the revert

The executor's dissent on the `IndexCodec::VERSION` 5 → 6 bump is factually
correct and the audit verified it in the code: `SEED_PAIR_CAP` applies in
`AnchorSet`'s enumeration loop, after the index — no stored byte and no
selection rule changes, and the bump's hypothetical benefit (a warm cache
"reporting under a different seeding rule") cannot occur because the seeding
rule is applied at run time whether the cache is warm or cold. The condition
in ruling N that required the bump was the auditor's error, carried by analogy
from ruling D without checking where the new cap acts. The bump is reverted in
this audit's own commit (VERSION back to 5, comment rewritten, ruling N's
CHANGELOG sentence corrected, a revert entry of its own), and the plan's
ruling N text is amended to record the rescission. Suite, phpstan, incremental
and determinism re-run green after the revert. Complying under recorded
dissent, with the revert line named, was exactly right.

### The two new findings — rulings recorded into M4

- **Ruling O** (plan §2 M4): a similarity-refused candidate must not consume
  the evidence inside it — M2's remedy for the self-overlapping case, extended
  to refusal by verification, with the acceptance criteria pinned to the
  traced pair and the residual's return to 21.
- **Ruling P** (plan §2 M4): dogfood measurements come from pinned snapshots
  (the live tree drifted 74 → 1,030 pooled findings between rating and close);
  and ruling K gains a principled rung — **shadowed duplicates**, derived from
  the tool's own symbol table and autoloader mapping, never a path pattern —
  because a backup of a live file declares the same symbols and no existing
  machinery can see that only one of the two is loadable. The worksheet's 57
  consensus labels stay valid as the discriminator fixture; the rated
  per-engine precision is recorded as contaminated as a corpus estimate, and
  M4's release-gate precision evidence re-pools on a pinned snapshot.
- Applying **no** backup-directory exclusion mid-close was the correct call
  under ruling K's freeze, and it is what kept the contamination visible
  enough to rule on.

### M3 is closed

Its scope — the measurement program, plus rulings J, M, N — is complete: the
instruments exist, are self-tested, level-max clean, and falsifiable; the
recall gate passes at 100 %; the two-rater precision protocol ran at κ 0.898.
The two gates that fail — wall-clock (0.78×/0.52×/0.59×/0.42×, degrading with
size) and precision (0.340/0.358 against 1.000) — fail for reasons that are
now measured, traced, and assigned: candidate 2 and the default-flip posture
to the project owner, the structural-context discriminator to ruling H's
avenue with its 57-finding fixture, both recorded as M4 entry conditions. No
milestone owes a gate it has already turned into a decision with evidence
attached.

M4 does not begin until the project owner answers the three decision points in
plan §2 M4. The full-milestone audit trail stands: M0 pass · M1 pass · M2 pass
· M3 pass, every constant in the engine carrying a derivation or a ruling, and
every retreat from one recorded where it happened.

---

## Correction note — 2026-09-01, appended by the M4 executor

**Every phpunit number in §6's final table was measured over a contaminated
file list. Re-run clean, every verdict is unchanged and three figures move.**

The contamination and its fix are described in the M1 packet's correction note
of the same date (commit `8002086`). php-parser and symfony-string were
unaffected, so §3's recall curves, the chaining oracle, and every php-parser
gate stand exactly as recorded.

§6's table, re-run on the clean corpus at M4 open:

```
                                                     recorded      clean
vendor/bin/phpunit                                   143/143       143/143
vendor/bin/phpstan analyse (both configs)            no errors     no errors
php bench/self-test.php                              24/24         24/24
php bench/check-chaining.php                         2/2           2/2
php bench/check-determinism.php php-parser           4/4           4/4
php bench/check-determinism.php phpunit              4/4           4/4
php bench/check-incremental.php php-parser           8/8           8/8
php bench/check-superset.php php-parser              3/3           3/3
php bench/check-superset.php phpunit                 FAIL 22       FAIL 22
php bench/check-walltime.php phpunit                 FAIL 4/4      FAIL 4/4
```

*(The determinism rows are the `--algorithm=unified` invocation, which is the
standing gate. The script's no-argument default runs the three baseline
engines, which fail the reversal check on both corpora and did so before this
milestone — that is the check's teeth working, not a regression.)*

What moved: files scanned 2,870 → 2,695; Rabin-Karp 160 → 156 clones; unified
587 → 564 clones; baseline pairs compared by the subsumption check 242 → 238.
The clones the fix removed were all covered ones — the residual is **the same
22 items with the same composition**: one uncovered location
(`assertArraysHaveEqualValuesIgnoringOrderTest.php:269`) and 21 unexplained
pairs, of which 10 are `MetadataTest.php` against itself and 2 are the pair
ruling O was recorded for.

Wall-clock, clean, in the same quiet-window conditions:

```
  60 files     unified 0.018s  vs default 0.012s   0.66x   FAIL
  200 files    unified 0.055s  vs default 0.028s   0.51x   FAIL
  600 files    unified 0.206s  vs default 0.118s   0.57x   FAIL
  2500 files   unified 1.333s  vs default 0.541s   0.41x   FAIL
```

Against the recorded 0.79 / 0.57 / 0.58 / 0.42 and the close audit's re-run at
0.78 / 0.52 / 0.59 / 0.42, this is unchanged inside the noise, and the
degradation with size is unchanged. **The dump was not the cause of the
wall-clock failure**, and ruling F's position is untouched by the correction.

The precision figures (0.340 / 0.358 against 1.000, κ = 0.898) were measured on
the dogfood corpus, not on phpunit, and are governed by rulings K and P rather
than by this correction. Their recorded contamination is the backup subtree,
which is a separate matter and is M4's step 4.

*Retraction discipline: this note supersedes nothing silently. The packet's own
text is left as it was written.*
