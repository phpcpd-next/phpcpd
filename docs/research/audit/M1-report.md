# M1 — Stages A–C behind `--algorithm unified`

Executor: Claude Opus. Plan: `docs/research/unified-engine-plan.md` §2, milestone M1.
Builds on M0 (`12c8a31`), audited pass.

**Scope, as specified:** encode (reuse), index, anchors — reporting only gapless anchors
at or above the thresholds, i.e. the Rabin-Karp-equivalent subset, so the milestone is
testable on its own.

**Gates:**
1. on the fixture suite and one public corpus from the paper's six, the unified report is
   a superset of Rabin-Karp's (every RK clone present, length ≥ RK's), differences listed
   and explained;
2. determinism — two consecutive runs byte-identical, then again with the file list
   reversed;
3. incremental — cold vs. warm after touching one file, identical output, and the warm run
   re-fingerprints only the touched file (assert via counter);
4. PHPStan level max clean; wall-clock on the corpus ≤ the current default pipeline.

---

## 1. What was built

```
src/Detector/Strategy/Unified/
    Winnower.php          Stage B core — winnowed k-gram selection (240 lines)
    FingerprintIndex.php  Stage B — global postings, frequency cap (150 lines)
    AnchorSet.php         Stage C — seed extension and diagonal dedup (250 lines)
    UnifiedStrategy.php   orchestration A–C (280 lines)
```

Wired as `'unified'` in `Engine::strategyFor()` and in the `--algorithm` allowed values.
The plan names `src/CLI/Application.php` for that match; in this tree the match lives in
`src/Engine.php` (the 2.0.0 refactor moved it) and the allowed-value list in
`src/CLI/Options.php`. Both were updated; no third location exists.

**Constants, all derived and none exposed**, exactly as the plan's table specifies:
K = 16 (`Winnower::SEED_LENGTH`), S = ⌈minTokens/2⌉, W = S − K + 1, F = 1000
(`FingerprintIndex::POSTINGS_CAP`), floor = 2K + 6 = 38 (`Winnower::MINIMUM_MIN_TOKENS`).
`--min-tokens` below 38 is refused through `InvalidStrategyException`, which the CLI
already prints cleanly and exits 1 on.

Fingerprints are the raw 8 bytes of `xxh3`, ordered by `strcmp`. The ordering only has to
be *some* total order for the guarantee to hold, and `strcmp` is one memcmp per comparison
— and, unlike `<`, immune to PHP's numeric-string comparison, which would otherwise make
`"12345678" < "9"` true and quietly destroy the total order the theorem rests on.

The winnowing selection is a monotonic deque (the classical O(n) sliding-window minimum)
rather than a per-window rescan; popping on **≥** is what makes the front the *rightmost*
minimum, which is the plan's tie-break rule.

---

## 2. Gates

### Gate 1 — subsumption of Rabin-Karp

Run on the fixture suite and on **three** corpora, two of them from the paper's six at
their pinned SHAs (the gate asks for one):

| Corpus | Files | RK clones | Unified clones | Locations covered | Pairs |
|---|---:|---:|---:|---|---|
| `tests/fixtures` (`--min-tokens=50`) | 59 | 3 | 6 | all | 2 same, 1 shorter |
| `symfony/string` @ `afd5944` | 33 | 1 | 1 | all | 1 same |
| `nikic/PHP-Parser` @ `50f0d9c` | 342 | 15 | 42 | all | 13 same, 1 longer, 1 shorter |
| `sebastianbergmann/phpunit` @ `c88a159` | 2,870 | 160 | 1,685 | all | 127 same, 25 longer, 90 shorter |

```
$ php bench/check-superset.php bench/corpus/phpunit
  rabin-karp    160 clones,   10295 duplicated lines, 0.513s
  unified      1685 clones,   84556 duplicated lines, 0.357s
  PASS  the baseline found clones, so subsumption is being checked against something — 160 rabin-karp clones
  PASS  every location the baseline reports is reported by the unified engine — all locations covered
  PASS  every pair the baseline reports is reported at a length the source agrees with — 242 pairs — 127 same length, 25 longer, 90 shorter (90 of those over-reported by the baseline), 0 unexplained
```

**Every difference is explained, and the explanation is not "trust the new engine".**
Three kinds of difference appear, and the third needed adjudication:

1. **Longer** (25 pairs on phpunit, 1 on PHP-Parser) — maximal extension recovers the
   full match where Rabin-Karp truncates at window granularity. Expected by design.
2. **Different clone *classes*** — both engines merge equal clones into classes and merge
   them differently. Five near-identical files are one class of five to one engine and
   several overlapping pairs to the other. The check therefore compares **locations and
   pairs**, not class membership; comparing classes reported regrouping as a lost clone,
   which was a defect in an earlier version of the check, not in the engine.
3. **Shorter** (90 pairs on phpunit, 1 each on PHP-Parser and the fixtures) — **the
   baseline over-reports.** This is the one that had to be settled by something that is
   not either engine, so `bench/check-superset.php` recomputes what the two files actually
   share: a straight token-by-token walk over both signatures, no hashing, no windows, no
   sampling. In **all 92 cases** the source agrees with the unified engine exactly, and
   **0 are unexplained**.

The mechanism is structural in Rabin-Karp and worth stating plainly, because it means the
baseline is not a ceiling: RK keys its hash table on the *first* file to register a window,
so a run of matching windows that continues only because a *third* file matches further is
still attributed to that first file at the run's full length. Verified independently on the
type-3 fixtures, by direct token comparison outside both engines:

```
  clone_base vs clone_wide_gap  = 58 tokens   (rabin-karp reports 62)
  clone_gapped vs clone_wide_gap = 62 tokens  (the run that 62 really belongs to)
```

and on PHP-Parser:

```
    OVER-REPORT  Php7.php:1156 ↔ Php8.php:1143 — baseline 189, unified 110, actually shared 110
```

This contradicts the plan's §1 Stage C wording — *"every Rabin-Karp clone is one anchor of
at least its reported length"* — but not the design. The claim is false as literally
written because RK's reported length is sometimes not a length that pair shares at all, so
no anchor can have it. **A ruling is requested** on restating that gate property as: *every
location Rabin-Karp reports is reported by the unified engine, and for every pair, the
unified length is at least the length the two files actually share.* That is what is
checked and passed here, and it is a stronger statement than the original in every case
where the baseline is correct.

### Gate 2 — determinism

```
$ php bench/check-determinism.php bench/corpus/phpunit --algorithm=unified
Determinism check — 2870 files, algorithms: unified

  unified — 1685 clones in 0.368s
  PASS  unified: two runs over the same file list are byte-identical
  PASS  unified: reversing the file list does not change a byte of the report
  PASS  unified: two CLI runs write byte-identical JSON — 1365310 bytes
  PASS  the corpus actually contains clones, so the comparisons above mean something — 1685 clones compared

determinism check: 4/4 checks passed.
```

Passes at both surfaces the auditor's C1 ruling requires — the CLI and `Engine::detect()`
with an unsorted list. It holds by construction rather than by luck: `postProcess()`
assigns file ids from the **sorted** path list, so nothing downstream can observe the order
the caller used. Also asserted in the suite
(`UnifiedEngineTest::reversing_the_file_list_does_not_change_the_report`).

The three incumbents still fail this, as recorded in M0. They are unchanged.

### Gate 3 — incremental

```
$ php bench/check-incremental.php bench/corpus/phpunit --algorithm=unified
Incremental check — unified, 2868 files staged in a scratch copy

  cold        0 reused, 2868 computed, 1684 clones
  PASS  a cold run computes every file and reuses nothing — 0 reused, 2868 computed, 2868 files
  PASS  the corpus contains clones, so the comparisons below mean something — 1684 clones
  PASS  the cold indexed run reports exactly what the plain engine reports — 1684 vs 1684 clones
  warm        2868 reused, 0 computed, 1684 clones
  PASS  an untouched warm run recomputes nothing at all — 2868 reused, 0 computed
  PASS  the warm run reports byte-identically to the cold run
  warm+edit   2867 reused, 1 computed, 1684 clones   (_tests_end-to-end__files_BeforeTestMethodWithAttributeTest.php)
  PASS  touching one file recomputes exactly one file — encoding and fingerprints — 1 computed, 2867 reused
  PASS  every other file is still served from the index — 2867 reused of 2867 others
  cold+edit   0 reused, 2868 computed, 1684 clones

  PASS  after an edit, the warm run reports byte-identically to a cold run on the same corpus — 1684 vs 1684 clones

incremental check: 8/8 checks passed.
```

The counter the gate asks for is `IndexResult::scanned`, and it counts files whose encoding
**and fingerprints** were computed — the plan's requirement that the cache store per-file
selected fingerprints next to `FileTokens` is implemented, not approximated. `IndexCodec`
gained a version-2 layout (fingerprint count, raw 8-byte fingerprints, delta-varint
positions); a version-1 index decodes to empty and degrades to a full re-scan, which is the
format's documented behaviour for anything it cannot read.

The fourth run is what makes this mean something: without a cold run over the *edited*
corpus, runs 1–3 only show the cache being used, not that using it was harmless.

The Rabin-Karp incremental path was regression-checked with the same script
(`--algorithm=rabin-karp`, 8/8) and is unchanged in behaviour.

### Gate 4 — PHPStan and wall-clock

```
$ vendor/bin/phpstan analyse --memory-limit=1G --no-progress
Note: Using configuration file /Users/studiox/Downloads/phpcpd-main/phpstan.neon.

 [OK] No errors

$ vendor/bin/phpstan analyse -c phpstan-bench.neon --memory-limit=1G --no-progress

 [OK] No errors
```

```
$ php bench/check-walltime.php bench/corpus/phpunit bench/corpus/php-parser bench/corpus/symfony-string --runs=5
Wall-clock — 5 runs per configuration, --min-tokens=70

  bench/corpus/phpunit — 2870 files
    default (rk+tokenbag)  median   0.864s   spread  0.044s   min  0.834s   max  0.877s   379 clones
    rabin-karp             median   0.366s   spread  0.020s   min  0.363s   max  0.384s   160 clones
    tokenbag               median   0.505s   spread  0.005s   min  0.501s   max  0.506s   219 clones
    unified                median   0.382s   spread  0.024s   min  0.363s   max  0.387s   1685 clones
  PASS  phpunit: unified is at or below the default pipeline — unified 0.382s vs default 0.864s (2.26x)
```

Full sweep: **2.26× on phpunit** (2,870 files), 1.44–1.6× on PHP-Parser, 2.40× on Symfony,
3.0× on symfony/string. All medians of 5 runs through the M0 harness, in-process monotonic
clock, spread reported.

**This gate failed on the first measurement and was fixed, not restated.** Unified came in
at 1.583s against the default pipeline's 1.576s on phpunit — a 0.4% miss. Profiling the
stages separated detection from reporting:

```
encode         0.382s
fingerprint    0.047s          (Stage B)
anchors        0.075s          (Stage C — 13,453 anchors from 1,235 shared fingerprints)
emit clones    1.121s          ← 69% of the run
```

Detection (A–C) was 0.504s, already three times faster than the pipeline. The overage was
entirely in `CodeClone` construction: a clone's identity is the md5 of its own text, so
building one reads its first file — and **6,961 of the 7,115 emitted clones named the same
5,782-line file first**, so that file was read from disk 6,961 times. A two-entry cache of
file contents in `CodeClone::readLines()` (reports arrive grouped by first file, so two
entries suffice) took clone construction from 1.09s to 0.03s.

That is a change to shared code, so it was checked for behavioural neutrality rather than
assumed: every engine's clone count, duplicated-line count and percentage on three corpora
are identical before and after, and the whole suite is green. The default pipeline gained
from it too (phpunit 1.58s → 0.86s), which is why the ratio above is 2.26× rather than the
larger number the unified engine alone would have shown.

### Standing gates

```
$ vendor/bin/phpunit --do-not-cache-result
OK (112 tests, 884 assertions)
```

82 → 112 tests. The 30 added are `UnifiedEngineTest` (26) and `CodeCloneTest` (4).

---

## 3. Evidence beyond the gates

**The winnowing guarantee is tested as a theorem, not on fixtures.** For each of five
thresholds, 40 synthetic pairs are generated with a shared run of exactly the guaranteed
length planted at deliberately different offsets in each copy, and the test asserts the two
copies select a common fingerprint at the same in-run offset. 200 cases, no violations. A
separate 1,500-case sweep during development, at the same five thresholds with random run
lengths, also found none.

Selection density matches the published 2/(W+1) bound closely enough to confirm the
implementation is the algorithm and not something adjacent to it:

| minTokens | W | measured density | 2/(W+1) |
|---:|---:|---:|---:|
| 38 | 4 | 0.3988 | 0.4000 |
| 50 | 10 | 0.1820 | 0.1818 |
| 70 | 20 | **0.0951** | **0.0952** |
| 120 | 45 | 0.0440 | 0.0435 |
| 200 | 85 | 0.0225 | 0.0233 |

The plan predicts "≈ 9.5 % of positions at the default `minTokens` 70". Measured: 9.51%.

**Every reported clone is verified to be a real exact match**
(`UnifiedEngineTest::every_reported_clone_is_a_real_exact_match`) — a property test over
the engine's own output, since Stage C can only report exact matches and anything else
would mean the extension arithmetic is wrong.

**The frequency cap is not currently costing anything.** On the 2,870-file phpunit corpus
— framework test code, the boilerplate-heaviest realistic case, and exactly where the
brief predicted the cap would bite — `cappedFingerprintCount()` is **0** and
`discardedPostingCount()` is **0**. The cap's accounting is unit-tested separately by
driving 1,250 postings into one fingerprint and asserting both counters.

---

## 4. Deviations from the plan

**D1 — Stage A's second (normalized) view is not computed in M1.** Plan §1 Stage A
specifies two views per file, raw and type-anchored-normalized. M1 builds only the raw
view. The normalized view exists to make normalized-only matches *be* the Type-2 report,
which is Stage D3 classification — M2 work. Computing it in M1 would double the encode
cost (the largest single cost in the engine, 0.382s of 0.504s) to produce nothing this
milestone reports, and M1 carries a wall-clock gate. `UnifiedStrategy::rawView()` is the
seam it will be added at. Flagged rather than assumed: if the auditor reads Stage A as
owed in full by M1, it is a contained addition.

**D2 — the plan names `src/CLI/Application.php` for the `--algorithm` match.** In this
tree that match is `Engine::strategyFor()`; the plan was written against the pre-2.0
layout. Both the match and the allowed-value list in `Options.php` were updated.

**D3 — `CodeClone` was modified** (the read cache above). Outside the plan's file layout,
but it is what made Gate 4 pass, it changes no output, and the alternative was to report a
0.4% gate failure caused by a file being read seven thousand times.

**D4 — `tests/fixtures/` was restored from the published repository.** Gate 1 names "the
existing fixture suite", which was absent from the working tree exactly as `bench/` was in
M0. 61 files, unmodified.

No constant was tuned, no gate weakened, no fixture made to pass by adjusting a threshold.
Where a check disagreed with the engine, the check was re-derived from what the property
actually is (Gate 1, twice) or the engine's answer was verified against the source outside
both engines — never resolved by preferring the newer code.

---

## 5. Open concerns

**C1 — the duplicated-line percentage is inflated, and this blocks M4.** On phpunit the
unified engine reports 15.08% duplicated lines against Rabin-Karp's 1.84%. That is not a
detection difference. Measured directly:

| Engine | Clones | Reported duplicated lines | **Union of covered lines** | Ratio |
|---|---:|---:|---:|---:|
| rabin-karp | 160 | 10,295 | 13,725 | 0.8× |
| unified | 1,685 | 84,556 | **13,735** | **6.2×** |

**The two engines cover the same lines** (13,735 vs 13,725). The unified engine reports 6.2×
more because it emits every maximal *pair* and `CodeCloneMap`'s extra-copies accounting adds
each one; where N near-identical files exist it emits N(N−1)/2 pairs where the incumbents
collapse them. This is precisely the gap the brief names as required work — *"Pairs-to-classes
is a post-pass, and its rules must be specified"* — and it is not in M1's scope, which is why
M1 still passes. It is also the same root cause as the Gate 4 performance problem. It must be
resolved before the default flips in M4, and the README now warns against using
`--algorithm=unified` for a percentage anyone acts on. **Recommendation:** specify and build
the pairs-to-classes post-pass in M2, where the classifier is being written anyway.

**C2 — 1,522 of 1,685 unified clones on phpunit have no baseline counterpart.** Expected
for a corpus of near-identical assertion tests, and every one is a verified exact match of
≥ 70 tokens, so none is a false positive in the Type-1 sense. But their *usefulness* is
unmeasured, and M3's precision audit is where that gets settled. Recorded so the number is
not mistaken for a recall win.

**C3 — `--verbose` has never indented its excerpts.** `Log\Text` calls `$clone->lines('    ')`,
but `lines()` memoizes and the constructor already called it with no indent to compute the
clone's id, so the indent argument has never had any effect. Pre-existing, unrelated to this
milestone, and deliberately not fixed here: the fix changes `--verbose` output for every
engine and belongs with reporting work. Recorded as
`CodeCloneTest::the_indent_argument_of_lines_never_takes_effect`, which asserts the actual
behaviour and names the defect, so a future fix has to come past it.

**C4 — incremental runs skip suppression filtering.** `IncrementalIndex` builds its map
directly instead of going through `Detector`, so `CloneSuppressions` is not applied on
`--incremental` runs. Pre-existing on the Rabin-Karp path; the unified path was written to
match it rather than to diverge silently. Worth fixing, but it changes `--incremental`
results for an existing engine and so is not an M1 change.

**C5 — the seed-pair loop is quadratic in a fingerprint's postings.** 108,056 candidate
pairs came from 1,235 shared fingerprints on phpunit, and the cost is bounded only by
F = 1000, which would be 500k pairs for a single fingerprint. Nothing near that occurred on
any corpus tested (Stage C: 0.075s), but the bound is worth remembering when M3 measures at
2,500 files.

---

## 6. Reproducing every gate

```bash
bash bench/fetch.sh                                   # or clone the three corpora at their pinned SHAs
php bench/check-superset.php tests/fixtures --min-tokens=50
php bench/check-superset.php bench/corpus/symfony-string
php bench/check-superset.php bench/corpus/php-parser
php bench/check-superset.php bench/corpus/phpunit
php bench/check-determinism.php bench/corpus/phpunit --algorithm=unified
php bench/check-incremental.php bench/corpus/phpunit --algorithm=unified
php bench/check-walltime.php bench/corpus/phpunit bench/corpus/php-parser bench/corpus/symfony-string --runs=5
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/phpstan analyse -c phpstan-bench.neon --memory-limit=1G
vendor/bin/phpunit
composer bench:verify
```

---

AUDIT: **pass** — recorded 2026-08-28 by the authoring (Fable) session.

Independently re-run by the auditor: superset on fixtures and on phpunit
(242 pairs, 0 unexplained), determinism 4/4, incremental 8/8, both PHPStan
configs clean, 112 tests / 884 assertions green, wall-clock 2.77× on a fresh
3-run measurement. Code-level claims verified by reading: constants derived
exactly per the plan's table with nothing exposed; the winnowing deque pops on
`>=` (rightmost-minimum tie-break as specified); fingerprints ordered by
`strcmp` with the numeric-string hazard correctly reasoned; extension is
`substr_compare`; and — decisive for Gate 1 — `superset_true_match_length()`
in `bench/check-superset.php` is a raw 5-byte `substr` walk over both
signatures, genuinely independent of both engines. The adjudicator is sound.

Rulings:

- **Gate 1 restatement — granted.** The plan's original wording assumed RK's
  reported length is a property of the reported pair; M1 proved it sometimes
  belongs to a different pair entirely. The property is restated in plan §1
  Stage C and §2 M1 (every RK location covered; every pair at ≥ the
  independently recomputed shared length). This is the stronger claim
  everywhere the baseline is correct, and the executor was right not to
  restate it unilaterally.
- **C1 — recommendation adopted as a plan amendment:** the pairs-to-classes
  post-pass is M2 scope, with its rules specified, and remains an M4 blocker.
  The README warning stands until then.
- **D1 — accepted; the normalized view is now owed by M2** (recorded in the
  amended M2 scope). The deferral reasoning (double encode cost against an M1
  wall-clock gate, for output M1 cannot report) is sound.
- **D2, D4 — accepted** (plan file-name drift; fixture restoration mirrors
  M0's bench/ restoration).
- **D3 — accepted, with one required follow-up:** the `CodeClone` line cache
  is static and never invalidated. Safe for the CLI (one run per process) and
  neutral on all measured output, but an embedder that edits a file and
  rescans in the same process can read stale content into clone identities.
  M2 must run-scope or content-key the cache (added to M2 scope). Not
  rework: no current gate exercises the hazard, and the fix is contained.
- **C2 — agreed:** the 1,522 baseline-less clones are verified exact matches,
  and their usefulness is M3's precision-audit question. The number must not
  be quoted as a recall win before then.
- **C3 — accepted as documented-defect-with-test;** fix belongs to reporting
  work, at latest M4's characterization pass.
- **C4 — accepted as pre-existing and out of milestone scope;** flagged to the
  project owner as a standalone task (suppression filtering must apply on
  incremental runs for all engines).
- **C5 — noted as an M3 obligation:** the wall-clock milestone must include a
  pathological-postings stress case (one fingerprint near the F=1000 cap), not
  only natural corpora.

Beyond the gates, the theorem-style winnowing test (200 planted-run cases, 0
violations; measured density 9.51% vs 2/(W+1) = 9.52%) is exactly the standard
M0 set — evidence the implementation is the published algorithm and not
something adjacent to it.

M1 may be committed as one milestone commit. M2 is cleared to start with the
amended scope: Stage D + pairs-to-classes + normalized view + run-e3 port +
line-cache invalidation.

---

## Correction note — 2026-09-01, appended by the M4 executor

**Every phpunit number in this packet was measured over a contaminated file
list, and none of its verdicts changes.**

The four checks that record numbers — subsumption, wall-clock, determinism,
incremental — asked `FileFinder` for their file list with default excludes
**off** (`find($dirs, ['.php'], [], false)`) and no excludes of their own. On
`bench/corpus/phpunit` that scanned 2,870 files where the product scans 2,695:
117 under `tools/.phpstan` (a dumped static-analysis tool tree), 35 vendor
stubs inside end-to-end test fixtures, 12 other vendor files, 11 build scripts.
Fixed at `8002086`, where the checks were routed through one walker applying
the tool's own definition of program text.

`bench/corpus/php-parser` and `bench/corpus/symfony-string` were unaffected
(0-file delta), so **every php-parser number in this packet stands as
recorded**. On phpunit, re-run on the clean corpus at M4 open with the
*current* engine — which is several rulings ahead of M1's, so these are not
like-for-like restatements of M1's own figures, only evidence about the
direction of the contamination:

| | dirty | clean |
|---|---:|---:|
| files scanned | 2,870 | 2,695 |
| Rabin-Karp clones | 160 | 156 |
| unified clones | 587 | 564 |
| subsumption residual | 22 items | **22 items, same composition** |

The M1 gate verdicts are unchanged: superset php-parser passed and still
passes; determinism and incremental pass under `--algorithm=unified` on both
corpora; the wall-clock comparison fails on phpunit at every size, before and
after. No M1 conclusion rested on a file the fix removed.

*Retraction discipline: this note supersedes nothing silently. The numbers
above are the correction; the packet's own text is left as it was written.*
