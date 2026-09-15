# Implementation plan — the `unified` engine

Authored with Claude Fable 5; written to be **executed by a Claude Opus
session**, with **Fable retained as the auditor**. Paste this file's content
(or point the session at it) from the repo root. It is self-contained.

**Division of labor (fixed):**

- **Opus (executor):** implements milestone by milestone, runs the gates,
  and at the end of each milestone writes an audit packet to
  `docs/research/audit/M<n>-report.md`: gates run with exact commands and
  verbatim outputs, diffs summary, open concerns, and any deviation from
  this plan (there should be none without a recorded reason).
- **Fable (auditor):** reviews each audit packet against this plan before
  the next milestone starts — checks the gates were the *specified* gates
  (not weakened), spot-reads the new code against the provenance and
  licensing rules, and challenges any constant or classification change. **A
  milestone is not done until the audit verdict is recorded** at the bottom
  of its report (`AUDIT: pass` or `AUDIT: rework` with the findings). The
  executor never audits its own milestone.

**Rules for the executing session — read first:**

- When an instruction meets a fact it did not anticipate, follow
  `docs/research/interpretation.md` (owner-authored, auditor-amended at the
  M4 close): no named mismatch means apply the instruction as written; every
  interpretation leaves its one-paragraph record in the packet's
  Interpretations section. Its second half is the design checklist new code
  answers to.

- The design below is **decided**. Do not re-litigate the algorithm choice,
  the constants, or the knob eliminations; their derivations are recorded
  here and in `docs/research/unified-engine-prompt.md` (Rev 2). If
  implementation uncovers a genuine contradiction (a gate that cannot pass
  as specified), stop and report — do not silently redesign.
- Work **one milestone at a time**, in order. Every milestone ends with its
  gates green before the next begins. A gate is a command with an expected
  result, not a judgment call.
- After every milestone: PHPStan **level max, zero errors**; full test suite
  green; the determinism check (below) passes; CHANGELOG.md and README.md
  updated to match reality.
- **Licensing discipline:** write every component from the published
  description cited in the provenance table. Never open, port, or paraphrase
  source code of any other clone detector (ConQAT, CCFinder, NiCad,
  SourcererCC, NIL, MOSS, PMD-CPD) or of this repo's own `SuffixTree/`
  directory beyond its public interfaces — nor, from ruling R forward, the
  in-tree inherited TokenBag strategy; and during a ruling-S replacement,
  not the inherited file being replaced. New files are BSD-3-Clause, `(c)
  2026 Luciano Federico Pereira` (the ruling-S endgame relicenses the tree
  MIT once the inherited inventory reaches zero).
- The private dogfood corpus (2,540 files / ~390 k LOC) may be scanned for
  numbers, but its name, paths, and source strings must never appear in
  code, fixtures, docs, or commit messages.
- Deterministic code only: no wall clocks, no randomness, no hash-order
  dependence (PHP arrays iterate in insertion order — make insertion order
  deterministic and sort all output by a total order anyway).

---

## A note on the numbers in this file

Two kinds, treated differently.

The **parameters** in §1 are live: `K`, `C` and `F` are rendered from
`bench/results/facts.toml` by `bench/sigil.php`, so if a constant moves in
the code this file fails `php bench/sigil.php --check` rather than going
quietly wrong.

Everything under **§2's milestones is a dated record** — κ values, precision
intervals, the caps that were tried and rejected, the curves an audit ran.
Those numbers are what was measured at that milestone on that tree, and
binding them to today's facts would rewrite history every time a measurement
moved. They are deliberately not bound, for the same reason `CHANGELOG.md`
is not: a record of what was true then must not be edited into a claim about
now.

So a number here that disagrees with the current code is not necessarily a
bug in the document. Check which of the two it is before correcting it.

## 1. The design (locked)

One engine, four stages, **two public knobs** (`--min-tokens`,
`--min-lines`). Everything corpus-sized runs inside C primitives
(`hash('xxh3')`, `substr`, `substr_compare`, native hash tables);
interpreted PHP touches only candidates.

### Stage A — Encode (reuse, do not rewrite)

Reuse `DefaultStrategy::tokenize()`'s 5-byte-per-token signature string
(type byte + 4-byte crc32 of token text) via the existing `FileTokens` value
object and the incremental cache. Produce **two views per file**:

1. **raw** — tokens as written;
2. **normalized** — through the existing `TokenNormalizer` **with
   type-anchoring always on**.

There is no `--fuzzy` and no `--type-anchored` on the unified engine: the
paper's E2 result (type-anchored Pareto-dominates name-blind fuzzing: equal
recall, +10.9 to +45.3 specificity) makes name-blind fuzzing a dominated
configuration, and running both views makes the normalized-only matches *be*
the Type-2 report.

**Normalized-seed diversity guard (M2 audit ruling B).** E2's dominance
result covers function-shaped code; it does not cover literal-dense data,
where normalization is not a refinement but a different detector (measured:
two unrelated generated Unicode tables share 1 raw token and 583 normalized
tokens — every literal folds to `NUM` — driving symfony/string to 28.75 %
reported duplication vs Rabin-Karp's 0.33 %, and inflating the candidate
space 10–270×). The fix is at the index, where it also removes the cost: a
**normalized k-gram whose distinct-symbol count is below a floor is not
fingerprinted** (it carries too little information to attest anything — a
data table's k-grams draw on an alphabet of 3–4 symbols; function-shaped
code, even normalized, draws on far more), and a normalized-only *match*
below the same diversity floor is rejected at verification. The floor is
**derived by measurement, not tuned**: the executor measures the
distinct-symbol distribution of normalized k-grams over real function bodies
vs. generated/data files on the corpora, sets the floor at the separation,
and records the measurement in the audit packet as the derivation.
Acceptance: Type-2 mutation-injection recall (M0's `type2` operators,
function-shaped) is unchanged by the guard; the Unicode-table pair produces
no report; the raw view is untouched.

### Stage B — Winnowed fingerprint index

Per file, per view: fingerprint every k-gram of the signature (`hash('xxh3',
substr($sig, $i * 5, K * 5))`), then winnow (Schleimer et al. 2003): in
every window of W consecutive fingerprints keep the minimum, rightmost on
ties. Insert selected fingerprints into a global map `fp → list<(fileId,
tokenPos)>`.

**Constants (derived, internal — not flags):**

| Constant | Value | Derivation |
|---|---|---|
| `K` (seed length, tokens) | <!-- [[ $code.seed_length ]] -->16<!--/--> | head-equality guarantee stronger than the old default (10); 80 signature bytes makes hash-collision seeds negligible |
| `S` (guarantee threshold) | `⌈minTokens / 2⌉` | pigeonhole: a clone of `minTokens` with ≤ 1 divergence contains an exact run ≥ S |
| `W` (winnow window) | `S − K + 1` | winnowing theorem: every exact common run ≥ W + K − 1 = S shares a selected fingerprint |
| `F` (postings cap per fp) | <!-- [[ $code.postings_cap ]] -->1000<!--/--> | boilerplate guard; when hit, count it and report the count in verbose output — never silently |
| `C` (per-file occurrence cap per fp) | <!-- [[ $code.per_file_cap ]] -->32<!--/--> | M2 audit ruling D. Anchors per file pair are the *product* of a fingerprint's per-file counts, so `F` (a corpus-wide sum) cannot bound the work: one phpunit fingerprint at 706 occurrences/file generates 99.4 % of the normalized view's anchor pairs, and php-parser's worst sits in two files at 160 each, invisible to any document-frequency rule. Same class of counted recall trade as `F`; never silent. <!-- [[ frozen: the M2/M4 derivation as argued at the time — it reasons ABOUT the value, naming caps of 8, 24, 48, 64 and 128 that were tried; the live constant is the cell at the head of this row ]] -->~~Value chosen at the dense-coverage knee (2,741 lines ≥ 4 tok/line vs 2,296 uncapped — *not* by wall-clock, which it worsens vs C=8), with the C=8 losses (`Assert.php`, `BuilderTest.php` classes destroyed) recorded as the floor argument.~~ **Struck 2026-09-01 (M4 audit, ruling on request 1): the dense-coverage knee no longer exists.** On the post-M3 engine that metric peaks at C=8 and rises monotonically from 16 to uncapped, so read literally it selects 8. Cause is this engine's M3/M4 changes, not the cap and not corpus contamination (dirty and clean curves have the same shape); the standing *hypothesis* — recorded as hypothesis — is that ruling O's evidence-return supplies from refused candidates what capping used to rescue. **Restated derivation: 32 is the smallest cap preserving both of ruling D's pre-registered exhibits** (`Assert.php`'s 572-line class, absent below 24; `BuilderTest.php`'s 974-line class, absent below 32). Not unique — 48, 128 and uncapped also preserve both — so the argument is minimality against steeply rising cost. Preservation is *not monotonic*: C=64 loses the `BuilderTest.php` class that 32, 48 and 128 keep. The restatement is **engine-state-dependent**: any change making the exhibits cap-insensitive re-opens the constant. Full curves in the M4 packet<!--/--> |
| floor | refuse `minTokens < 2·K + 6` (= <!-- [[ $code.minimum_min_tokens ]] -->38<!--/-->) with a clear error | below this, W < 4 and the guarantee degenerates |

Density is 2/(W+1) ≈ 9.5 % of positions at the default `minTokens` 70.

### Stage C — Anchors

For every pair of positions sharing a fingerprint (skip same-file self-pairs
at identical positions): extend left and right to the locally maximal exact
match using `substr_compare` on the signature strings (chunked memcmp, then
binary-search the boundary). Deduplicate by `(filePairId, diagonal = posB −
posA, endA)`. Output anchors `(fileA, posA, fileB, posB, len)`.

Gate property (restated by M1 audit ruling): every location Rabin-Karp
reports is reported by the unified engine, and for every pair the unified
length is at least the length the two files **actually share**, recomputed
independently of both engines (a raw token walk over the signatures). RK's
own reported length is not authoritative — it keys its hash table on the
first file to register a window, so it can attribute a run's full length to
a pair that does not share it (measured: 90 of 242 pairs on phpunit).

### Stage D — Banded extension, chaining, classification, verification

Group anchors by file pair (candidate-sized from here on).

1. **Gapped extension** (mechanism restated by M2 audit ruling on D1): from
   each anchor, extension recovers **exact runs directly** — longest common
   prefix and suffix at each flank, and a bounded longest-common-run search
   inside each gap (classical DP; provenance row recorded) — feeding
   recovered runs back to be re-chained. Extension must produce
   *boundaries*, and a cost-only DP would need a full traceback matrix to
   re-derive the very runs direct comparison finds outright. The **banded
   Levenshtein DP over tokens** — the *same* band-sufficiency lemma proved
   in the project paper (§2: `D[i,j] ≥ |i−j|`), Ukkonen band-doubling so
   cost is O(L·d) in the actual distance — is Stage D4's verifier, checked
   against an independent `levenshtein()` oracle. Stated cost of the
   substitution, accepted: a divergence whose far side matches only
   approximately (never exactly for ≥ 4 tokens) is not crossed where a
   DP-with-traceback would cross it; probe 3 is where this bites and probe 3
   is a documented miss regardless.
2. **Chaining:** merge colinear anchors/extensions (sort by A-position;
   sparse DP, O(m log m), Eppstein et al. 1992) so multi-anchor evidence
   becomes one candidate.
3. **Classify:**
   - single gapless span → **Type-1** (raw view) / **Type-2** (normalized
     view only);
   - span with internal gaps → **Type-3 gapped**, `gapped: true`, and report
     the **exact divergent token ranges on both sides** (from the chain
     geometry — the material between chained exact runs; strictly more than
     the suffix tree's boolean);
   - **bounded edge divergence** (added by M2 audit ruling C, for the
     one-copy-extended shape the E3 pair
     `CurrencyController`↔`TagController` exemplifies): a maximal span whose
     continuation differs on the two sides is `gapped: true` with edge
     ranges **only under an asymmetry bound** — one side's remainder beyond
     the span (to its file end) is ≤ `⌈RATIO·L⌉` tokens while the other
     side's is larger; the ranges are the short side's tail (possibly empty)
     and the long side's tail truncated at `⌈RATIO·L⌉`. The bound is what
     keeps this sound: without it every clone "diverges" into the rest of
     both files. The executor measures the real E3 pair first and may
     substitute a differently-derived bounded mechanism if this one does not
     fit the measured shape — with the derivation recorded in the packet.
     Probe 1 is re-pinned to the new contract and gains a paired negative:
     two clones that merely continue differently (both remainders large)
     must NOT be flagged;
   - anchor coverage of both spans ≥ `THETA = 0.7` and **displaced mass ≥
     K** → **Type-3 reordered**, with the displaced segments named.
     Displaced mass = total anchor coverage − longest-increasing-subsequence
     colinear coverage (Fredman 1975, patience sorting). *Restated by M2
     audit ruling A: the original criterion (colinear < half of total) is
     provably unsatisfiable for any two-block swap — colinear = |C|+max,
     total = |C|+max+min, so it demands |C|+max < min. The floor K is
     derived, not tuned: K exact tokens matched at crossed positions is the
     smallest displacement a seeded method can attest, and any crossing
     anchor is ≥ K by construction. Displacements below K (statement-level
     micro-swaps, e.g. fixture r1's 7-token statements) are out of contract
     for any seeded method — documented as a capability the removed TokenBag
     engine retains; M3's permutation recall curve measures the loss and M4
     revisits TokenBag's removal if it is material.*
4. **Verify (acceptance):** token-level similarity from the banded
   alignment; accept iff `similarity ≥ 1 − RATIO` (= 0.85) and span ≥
   `minTokens` and lines ≥ `minLines`. A chain is never reported unverified.

**Knob → constant mapping (record in README):** `editDistance` → `RATIO`
(relative, not absolute); `minSimilarity` → `1 − RATIO`; `headEquality` →
`K`; `fuzzy` → removed (dominated); `typeAnchored` → always-on second view.

### Provenance table (extend as components land)

| Component | Published source | License posture |
|---|---|---|
| Winnowing + guarantee | Schleimer, Wilkerson & Aiken, SIGMOD 2003 | algorithm from paper; no MOSS code exists publicly to copy |
| Seed–extend | Altschul et al. 1990/1997 (BLAST) | paradigm from papers |
| Sparse chaining | Eppstein, Galil, Giancarlo & Italiano, JACM 1992; Ohlebusch textbook | from paper/textbook |
| LIS | Fredman 1975 | classical |
| Banded DP + band lemma | this project's own paper, §2 | own work |
| θ = 0.7 overlap | Sajnani et al., ICSE 2016 (SourcererCC) | a threshold value, not expression |
| Longest common substring (gap-interior recovery) | classical textbook dynamic programming | classical; no implementation consulted |
| Periodicity of a self-overlapping alignment (block decomposition, M3 ruling J) | Lothaire, *Combinatorics on Words* — the definition of a period | classical; no implementation consulted |
| Order-free complement pass (M4 ruling R): bag model + overlap filtering | Sajnani et al., ICSE 2016 (SourcererCC) | from paper; no implementation consulted — the in-tree TokenBag is on the never-open list |
| Shingling (small-k n-gram sets, ruling R) | Broder 1997, *On the Resemblance and Containment of Documents* | classical; from paper |
| Naive Bayes with Laplace smoothing (Stage 0 triage, ruling T) | classical (Duda & Hart 1973; POPFile lineage for the binary-filter design) | classical; implementation original to this project |
| Prefix/position filtering for set-similarity joins (ruling R's bag channel) | Chaudhuri, Ganti & Kaushik, ICDE 2006; Xiao, Wang, Lin & Yu, PPJoin, TODS 2011 | from papers; no implementation consulted |
| xxh3 | PHP core `ext-hash` | runtime, not a dependency |

### File layout

```
src/Detector/Strategy/Unified/
    UnifiedStrategy.php      (extends AbstractStrategy; orchestrates A–D)
    FingerprintIndex.php     (Stage B; per-file winnowing + global postings)
    Winnower.php             (pure: signature string → selected fingerprints)
    AnchorSet.php            (Stage C; extension + dedupe)
    BandedAligner.php        (Stage D4 verifier; the banded DP)
    ChainBuilder.php         (Stage D2; sparse DP + LIS)
    CloneClassifier.php      (Stage D1+D3; extension, classification, emission)
    ShingleBags.php          (M4 ruling R: the third seed channel — per-function
                              shingle bags, prefix/position filtering, bijective
                              coverage, and order-based displacement)
src/CloneDivergence.php      (value object: one divergence — file, line range,
                              token range; added by M2 audit ruling on D3)
src/Facts/RegionStructure.php (M4: the facts layer — what a region of a file *is*.
                              Literal-array frames for the span discriminator
                              (ruling H) and function-body ranges for ruling R's
                              bag channel, computed once per file)
```

Wire `'unified'` into the `--algorithm` match in `src/CLI/Application.php`.
The incremental cache stores per-file *selected fingerprints* next to
`FileTokens` (they are a pure function of file content + config, so the
paper's incremental-equivalence proposition carries over with the same proof
shape).

---

## 2. Milestones and gates

### M0 — Harness self-test + BCB-PHP extension (no engine code yet)

The project was once burned by a benchmark whose `timeout` never existed on
macOS. So, first:

- A harness self-test that (a) deliberately times out and records it as a
  timeout, (b) deliberately fails and records a failure, (c) asserts
  wall-clock deltas are measured in-process. It must pass before any number
  is recorded anywhere.
- If `bench/` is absent from the working tree, restore it from
  `https://github.com/phpcpd-next/phpcpd` (`bench/` on `main`). Do not
  rewrite it; its published results are established and are **not** re-run.
- Extend `bench/injectors.php` with two operator families, at function
  granularity, parameterized by density: **gapped edits**
  (insert/delete/substitute a statement, d ∈ {1, 2, 3} edits per clone at
  controlled spacing) and **statement permutations** (adjacent and distant
  swaps). Manifests record every injection.

**Gates:** self-test green; injectors produce manifests that a checker
script can verify against the mutated files byte-for-byte.

### M1 — Stages A–C behind `--algorithm unified`

Encode (reuse), index, anchors; report only gapless anchors ≥ thresholds
(i.e., the Rabin-Karp-equivalent subset), so this milestone is testable
alone.

**Gates:**
- On the existing fixture suite and on one public corpus from the paper's
  six: the unified report is a **superset of Rabin-Karp's** — every RK
  location appears, and every pair at a length ≥ what the two files actually
  share, adjudicated by an independent token walk, not by either engine
  (wording restated by M1 audit ruling; RK's reported length can be
  misattributed). Differences listed and each explained.
- Determinism: two consecutive runs produce byte-identical output; then
  again with the file list fed in reversed order — still byte-identical.
- Incremental: cold run vs. warm run after touching one file — identical
  output; warm run re-fingerprints only the touched file (assert via
  counter).
- PHPStan level max clean; wall-clock on the corpus ≤ the current default
  pipeline.

### M2 — Stage D: extension, chaining, classification

Scope additions from audit rulings: (a) the **pairs-to-classes post-pass**
with its rules specified — M1 measured the cost of its absence (84,556
reported duplicated lines vs a 13,735-line union on phpunit; blocks M4); (b)
the **normalized second view** of Stage A, deferred from M1 by ruling; (c)
port `bench/run-e3.php` to the 2.0 API (M0 ruling C3); (d) invalidate or
run-scope the `CodeClone` line cache so an embedder that edits a file and
rescans in one process cannot read stale content (M1 audit).

**Gates:**
- The suffix-tree Type-3 fixtures report `gapped: true` **with divergent
  ranges** that a property test verifies: reported ranges differ, flanking
  anchors match.
- The reorder classifier fires on a fixture whose displaced blocks are ≥ K
  tokens (the probes' 24/49-token swap), under the restated displaced-mass
  rule. The TokenBag fixture r1 (7-token statements, below any seedable
  displacement) is **out of contract** for seeded detection — documented,
  not gamed; M3's permutation recall curve quantifies the class. *(Gate
  restated by M2 audit ruling A.)*
- ~~M1's wall-clock gate re-passes at M2 close~~ — *restated by M2 audit
  ruling F (2026-08-31): the premise ("the diversity guard restores most of
  it") was empirically wrong, and the gate already exists in M3 ("unified ≥
  default-pipeline speed at every size"), which owns the measurement. M2
  closes with the honest numbers recorded as failing; holding a correctness
  milestone open on a performance number invites constant-tuning drift,
  which the M2 packet's process section documents happening once.*
- The pairs-to-classes site rule is symmetric (M2 audit ruling G): two
  ranges are one site iff they share ≥ SITE_OVERLAP of the **longer** —
  "same place" is an equivalence and containment is not identity. The
  phpunit subsumption gate is re-run under this rule and the per-file cap
  together; a residual failure is measured and reported, not patched.
  *Scope, ruled at M2 close: site identity only (`classes()`). The two
  covering questions — "already reported by the other view?" and "same
  duplication the other view saw?" — legitimately keep the containment rule
  (`subsumes()`): symmetry there was measured, breaks probe 4's documented
  clone count, and re-reports the same material on a second alignment.*
- **Probe fixtures — must exist and pass (these attack the design's weakest
  claims):**
  1. divergence at the clone's left edge and at its right edge (no flanking
     anchor on one side);
  2. twin gaps ~20 tokens apart (middle run shorter than S — single-anchor
     extension must cross both or the case is documented as out of
     contract);
  3. an edit every ~12 tokens (denser than the guarantee — expected miss;
     the test asserts the *documented* behavior, whichever way it lands).
- **E3 replication:** run the paper's E3 protocol shape (gapped clones,
  dedupe by file pair, git staleness) on the same Firefly III slice with the
  unified engine. Both manually verified diverged-copy pairs must be
  recovered with divergent ranges. Every difference from the suffix tree's
  68-clone list is listed with a one-line explanation. The paper's numbers
  themselves are **not** re-litigated.

### M3 — Measurement (new engine only; baselines stand)

Scope additions from the M2 close audit, **sequenced before the measurement
passes** — whatever lands here changes the engine the raters rate and the
curves measure, so it goes first:

- **Same-file fine-granularity class emission (M2 close ruling J).** The
  phpunit subsumption residual — 63 of 66 items `MetadataTest.php` against
  itself — is a genuine recall loss under the field's own pair-matching
  criterion (Bellon et al. 2007), and it blocks M4's release gate ("every
  location the 1.4 default reports, unified reports"), so it cannot be
  documented away. The target output is already identified and endorsed:
  where a file holds N mutually-similar blocks, report **one clone class
  naming every block** at the fine period — not the coarse half-against-half
  clone, and not the baseline's quadratic pair list. The mechanism must come
  with a derivation; the shift-band structure (the fine period is already
  isolated at 139 anchors on shift 351) is the evidence to build from, and
  `SITE_OVERLAP` changes only under a recorded ruling.
- **Wall-clock closure (ruling F).** The known cost is the normalized view's
  anchor volume; the three candidate design changes are recorded at the end
  of the M2 packet, and none is tuning.

- Recall curves from the M0 injectors: recall vs. edits-per-100-tokens and
  vs. permutation distance, per corpus across the type-density gradient.
- Precision: pool all locations reported by any engine on the dogfood corpus
  (~≤ 60 total), exhaustive two-rater audit per the brief (rubric, Cohen's κ
  ≥ 0.7, Wilson intervals). Corpus stays unnamed everywhere.
- Wall-clock at 60 / 200 / 600 / 2,500 files, ≥ 5 runs, median + spread,
  through the self-tested harness only.

**Gates:** unified ≥ default-pipeline speed at every size; recall curve
shows the guaranteed region at 100 % — *the region is "≤ 1 divergence, runs
≥ S, **and within the acceptance contract** (span ≥ minTokens at similarity
≥ 1 − RATIO)", per M3 audit ruling L: winnowing guarantees seeding, and
acceptance is a deliberate §1 precision floor, so a pair the divergence
takes under 0.85 is out of contract by design — the seeded-but-unacceptable
half is reported alongside, never folded into the gate*; precision not below
the better of RK/TokenBag on the audited set.

**Audit-corpus definition (M3, ruling K — frozen).** The precision corpus is
program text as the tool itself defines it: the shipped default excludes and
presets, plus entire-file orphans dropped (`--wired-only` through
`Orphans::detect()`). Every rung of that definition predates M3 (baseline
machinery, verified) — no hand-written exclude list, and no exclusion added
by looking at findings. The corpus-definition ladder (every-`.php` → even
stride → orphans dropped → presets honoured) is recorded in the M3 packet
with per-engine counts at each rung; whatever survives the frozen definition
is rated as it stands, and any further exclusion needs a recorded ruling
*before* it is applied.

**Rulings recorded at the M3 audit (2026-09-01):**

- **M — the guaranteed-region misses (291/295).** The two-part mechanism fix
  is granted: (a) the two *outer flanks* are exempt from
  `MINIMUM_RECOVERED_RUN` — §1 Stage D1 says "longest common prefix and
  suffix at each flank" with no minimum, and a flank run has nothing beyond
  it to bridge to; (b) `ChainBuilder` breaks equal-score chains toward
  **greater coverage** — score is already evidence minus gap cost, and
  between equal scores the current pick is an iteration-order accident, not
  a rule; the refinement completes the total order and introduces no
  constant. Conditions: the brute-force oracle is extended to the same tie
  order and re-run over its full set; the recall gate re-runs (expected
  295/295 — any remainder is re-traced, not tuned); both superset gates
  re-run; two commits, two CHANGELOG entries.
- **N — the seed-pair bound.** `POSTINGS_CAP` bounds a posting *list* while
  `AnchorSet` pays for the *pairs* it yields — Θ(n²), C(<!-- [[ $code.postings_cap ]] -->1000<!--/-->,2) = <!-- [[ $code.postings_pairs |
  thousands ]] -->499,500<!--/--> from one capped fingerprint, 3 GB
  exhausted at 600 files of a real application. Ruling D's product-vs-sum
  argument one level up, and the same class of counted recall trade, already
  admitted. Granted: the bound is restated on **pairs emitted per
  fingerprint**, with the value chosen by ruling D's method — the
  dense-coverage knee, measured on phpunit *and* the 600-file dogfood slice
  (the corpus that exhibits the failure), never on wall-clock or a failing
  gate. Counted and surfaced like `F` and `C`; own CHANGELOG entry.
  Acceptance: the 600-file slice completes within the default memory limit;
  php-parser superset stays 3/3; the phpunit residual does not grow
  unexplained. Streaming the enumeration (an implementation change) may
  accompany it, measured, but does not replace the bound. *The original
  condition also required an `IndexCodec::VERSION` bump; the executor
  complied under recorded dissent and the close audit upheld the dissent
  (ruling Q): the cap applies at enumeration time and changes no stored byte
  and no selection rule, so the bump bought nothing and is reverted — the
  version stands at 5.*
- **F — wall-clock, re-sequenced after N.** The measured hot spots
  (self-pairs, runaway fingerprints) are exactly what ruling N bounds, so
  the sweep re-runs after M and N land. If the gate still fails, candidate 2
  (normalized-view window at W = 55, guarantee narrowing to exact normalized
  runs ≥ minTokens; raw view untouched) goes to the **project owner** as a
  recall-vs-speed product decision with the restated guarantee drafted — a
  recall trade is not the auditor's to take unilaterally. Either way M4 does
  not flip the default while this gate fails, absent the owner's recorded
  acceptance of the measured ratio.
- **Precision gate — failed under both raters, and the fixture for fixing it
  exists.** κ = 0.898 over 60 findings; unified 0.340/0.358 (A/B) against
  TokenBag 1.000 with non-overlapping intervals. The 57 consensus-labelled
  findings become the acceptance fixture for ruling H's one open avenue
  (structural context: data-provider / literal-array / dumped-table
  discrimination): the discriminator must silence the consensus data-table
  N's without losing a single consensus Y, and must keep the public-corpus
  regressions (the symfony-string Unicode pair silent, the `Php7.php` action
  table retained). No default flip in M4 while precision on the audited set
  sits below the better baseline. The three refuted discriminators stay
  dead.
- **TokenBag's M4 removal** now has its numbers: it leads unified on both
  permutation operators (77.5 % vs 73.6 % adjacent, 81.4 % vs 61.9 %
  distant, gap widening with distance) and rated 7/7 on the audited set. The
  M4 decision cites these, per ruling A's reservation.

### M4 — Land it

Scope additions from the M3 close audit (2026-09-01), **before the release
gate is attempted**:

- **Ruling O — a refused candidate must not consume the evidence inside
  it.** `fromCluster()` narrows the anchor set (`withoutSpan()`) before
  `verify()` runs, so a candidate refused on similarity takes its whole
  span's anchors with it, and exact clones inside the refused reading are
  never found. M2's fix returned a *self-overlapping* refusal's evidence;
  the same must hold for a similarity refusal. Mechanism is the executor's
  to derive (the refused reading must not simply be rebuilt next round — bar
  it or decompose to its best accepted sub-readings), with the derivation
  recorded. Acceptance: the traced phpunit pair
  (`assertArraysAreEqualIgnoringOrderTest.php:157 ↔
  assertArraysAreIdenticalIgnoringOrderTest.php:119`) reports its two exact
  clones again; the phpunit superset residual returns to 21; recall stays
  295/295; the chaining oracle stays 2/2; no constant.
- **Ruling P — measurement snapshots, and the backup-tree gap in ruling K.**
  (i) Any dogfood number destined for a packet or the paper is taken from a
  **pinned snapshot** of the corpus (a frozen copy or recorded content-hash
  list, kept outside every repository, never named) — the live tree drifted
  74 → 1,030 pooled findings between rating and close, so live-tree numbers
  are not reproducible and are not cited as if they were. (ii) Ruling K
  gains one principled, tool-level rung: **shadowed duplicates** — a file
  whose declared symbols are all also declared by another wired file, where
  the autoloader can map only one of the two, is not program text; derive it
  from the tool's own symbol table and autoloader mapping, never from a path
  pattern. The 60-finding worksheet's labels remain valid as the
  discriminator's acceptance fixture (they judge code, not wiring), but the
  rated per-engine precision is recorded as contaminated as a *corpus*
  estimate (~30 % of the pool was a backup subtree), and M4's release-gate
  precision evidence comes from a fresh pool on a pinned snapshot under K +
  P.
- **Owner decision points, carried from M3 with their evidence:** candidate
  2 (normalized W = 55) with the drafted narrowed guarantee and the
  executor's recommendation; and the default flip, which requires the
  wall-clock and precision gates passing or the owner's recorded acceptance
  of the measured numbers. *Decision point 2 — TokenBag's removal — is
  **resolved** (project owner, 2026-09-01): TokenBag is **replaced, not
  merely removed**, by the ruling-R pass below.*

- **Ruling R — the order-free complement pass (recorded 2026-09-01; own
  work, replaces TokenBag).** A pass *inside* the unified engine — not a
  fourth `--algorithm` — covering the one class seeds provably cannot see
  (sub-K displacement, ruling A's algebra) and nothing else. Written from
  published descriptions only: the bag model and overlap threshold from
  Sajnani et al. (ICSE 2016), shingling from Broder (1997); provenance rows
  in §1. **The inherited TokenBag source joins the never-open list from this
  ruling forward** — the successor is built against the recall curves and
  fixtures, never against the old implementation.

  Design constraints, each a recorded M2/M3 lesson rather than a preference:

  1. **Bags of small-k raw shingles, not token unigrams.** k sits below
     statement length so a statement swap barely perturbs the multiset while
     unrelated same-vocabulary functions share almost nothing. k needs a
     derivation: measured statement-length distribution across the corpora
     (fixture r1's swapped statements are 7 tokens; the permutation families
     are the instrument), recorded in the packet. Raw view only — unrelated
     data tables differ in literals, so their shingles differ.
  2. **Diversity floor at the bag index** (ruling B's machinery, reused):
     shingles below the distinct-symbol floor are not indexed.
  3. **Bijective coverage ≥ θ as the verifier** (the M2 lesson that
     one-sided coverage scored a repetitive file as a reorder of itself); θ
     = 0.7 carries its existing provenance row.
  4. **Complement gating:** the pass fires only where the seeded engine is
     silent — candidates covered by unified's report are excluded through
     the existing `subsumes()` machinery. Its entire finding surface is the
     marginal class, which is the false-positive budget's structural bound.
  5. **Localization:** shingles carry positions; matched-run alignment names
     the moved statements through the existing displaced-block reporting.
     The pass never emits an unlocalized "material is all present" finding.

  Acceptance instruments, all pre-existing: the permutation recall curve at
  or above TokenBag's measured 77.5 % / 81.4 % (beating it is the target —
  the successor also sees renamed and localized permutations); r1 back in
  contract; the wall-clock sweep re-run with the pass counted (its work is
  bounded: Stage A encodings reused, only unclaimed pairs with bag evidence
  compared); precision through the two-rater protocol on a pinned snapshot
  (ruling P); and the merged-default superset gate (`--baseline=default`,
  the harness extension recorded at M2 close) passing its TokenBag half —
  the concrete targets being the 8 / 137 / 311 locations its first run
  measured. Any new constant needs its derivation; θ and the diversity floor
  are inherited with theirs.

  *Integrated shape (amended 2026-09-01, before step 6 was reached).* The
  capability is built into the engine's existing spine, not as a sibling
  pass: (1) a **third seed channel** — per-function shingle bags with
  prefix/position filtering (Chaudhuri, Ganti & Kaushik ICDE 2006; Xiao et
  al., PPJoin, TODS 2011; provenance row added) — promoting anchor-less
  bag-overlapping function pairs into Stage D as ordinary candidates; and
  (2) a **second verdict in the existing verifier** — order-free bijective
  equivalence, asked of evidence colinear alignment refuses, through ruling
  O's refusal taxonomy (an aligner refusal says the gaps cost too much *in
  order*; the order-free question is the next question for the same
  evidence). Both consume the Stage 0/A facts layer (wiring, role,
  region-structure annotations computed once). Findings classify into the
  one lattice — exact / gapped / reordered / permuted — through the same
  `classes()`, dedupe, and report path, so determinism, counting, and every
  gate keep their shape. The five constraints and all acceptance targets
  above carry over unchanged. Corroborating architecture in the literature:
  NIL (Nakagawa, Higo & Kusumoto, ESEC/FSE 2021) — cheap n-gram seeding
  before expensive verification is the published shape of both speed and
  large-gap recall in this tool class.

- **Ruling S — the provenance endgame: a 100 % original tree, relicensable
  MIT (recorded 2026-09-01, owner-directed).** After M4 removes the
  ConQAT-derived `SuffixTree/` (with its NOTICE entry) and ruling R replaces
  TokenBag, the remaining inherited surface is inventoried — a measured list
  of files and lines descending from upstream phpcpd (expected: the
  tokenizer behind Stage A, the Rabin-Karp strategy, parts of
  `CodeClone`/`CodeCloneMap`, CLI scaffolding — much already rewritten in
  1.5's own work; the inventory says how much). Each is replaced under one
  standard: **written from the PHP manual and this project's own specs,
  without opening the inherited implementation during the rewrite, and
  proven behavior-identical by the gates** — the E1 equivalence protocol for
  RK, the superset and determinism gates, the full suite. The honest caveat
  is recorded rather than glossed: BSD-3-Clause already permits derived work
  with attribution, and sessions across these milestones have read the
  inherited files, so the enforceable standard is the no-open discipline
  plus gate-proven equivalence, recorded per file in the provenance table —
  not a legal clean-room claim. Relicensing to MIT happens in one commit,
  only when the inventory reaches zero, with the final inventory as its
  evidence and the LICENSE/NOTICE/CHANGELOG change together.

- **Ruling T — Stage 0, corpus triage: orphans before DRY, and a Bayesian
  fishiness score (owner-directed 2026-09-01, auditor-specified).**
  Analyzing unwired code is measuring a corpus's housekeeping, not its
  duplication — M3 measured it (entire-file orphans were 3.9 % of files and
  77 % of Rabin-Karp's findings; the rated pool's false positives
  concentrated in *files*, not spans). Detection therefore gains a triage
  stage that runs **before** any view is fingerprinted, shared by the
  engine, the bench walker, and the precision-audit pool so there is exactly
  one definition of "the corpus":

  1. **Wiring first:** the existing symbol table (`Orphans::detect()`) marks
     entire-file orphans; ruling P's shadowed-duplicate rung lives here too.
  2. **A naive-Bayes fishiness classifier** — POPFile-style: binary
     fishy/program classes, Laplace smoothing, categorical/bucketed
     features, log-odds inspection, an asymmetric margin set toward
     *keeping* (a false discard costs a real clone; a false keep costs one
     noisy finding). Original PHP, no dependencies, deterministic given its
     counts. Features are **content- and wiring-derived only**
     (declares-symbols, referenced-by-others, literal-mass bucket,
     `var_export` shape, comment density, function count,
     longest-literal-array bucket, …) — **never path patterns**, which
     ruling K already bans.
  3. **The model's parameters are counts over a pinned, recorded label set**
     — that is the derivation §3 requires, and the labels already exist at
     zero new rating cost: the M3 corpus ladder's kept-vs-excluded files
     (rung 1 → rung 4), phpunit's `tools/.phpstan` dump vs its real tree,
     the 57-consensus worksheet's files, the public corpora as clean priors.
     Trained on the label set, **never on a gate being passed** (the ruling
     D lesson); the training set and per-feature log-odds table go in the
     packet as the derivation.
  4. **Never silent:** triaged-out files are counted and surfaced exactly as
     the caps are, with their top log-odds features in verbose output; an
     escape flag includes them. Default posture (discard vs demote-and-tag)
     is the owner's release decision, recorded before the flip.

  Acceptance: pointed at the raw disk tree of the dogfood snapshot, Stage 0
  approximately reproduces ruling K's frozen definition (the ladder's fourth
  rung) from rung one — the ladder is the fixture; no file containing a
  consensus-Y finding is discarded; public-corpus gates unchanged or every
  change explained; determinism holds. **Scope note:** triage removes the
  dump/backup/trash FP *mass*, but a wired literal table (a seeder, a
  data-provider) is program text and stays — ruling H's span-level
  structural work remains for those, and the 57-label fixture arbitrates the
  two tiers together. Provenance row: naive Bayes with Laplace smoothing —
  classical (Duda & Hart; POPFile lineage); implementation original to this
  project.

- **Ruling U — the release bars, restated as outcomes (project owner,
  2026-09-01; auditor-recorded with the literature grounding).** Three
  numbers decide the flip, each calibrated against what the published field
  actually achieves (BigCloneBench-era evaluations: SourcererCC ICSE 2016
  ~86 % precision, T2 ~98 % recall; NiCad ~80–90 % precision; NIL ESEC/FSE
  2021 ~90 % precision with best-in-class large-gap recall; Oreo ESEC/FSE
  2018 and CloneCognition for learned validation precedent):

  1. **Detection:** the merged-default location gate closes (the measured 8
     / 137 / 311), permutation recall ≥ 77.5 % / 81.4 %, r1 in contract.
     Unchanged from ruling R; restated here as a release bar.
  2. **Precision:** two-rater precision **≥ 0.80** for unified on the
     audited set (wired program text, pinned snapshot, κ ≥ 0.7) — the
     shippable band the field reports, measured by a protocol stricter than
     the field's single-rater sampling. **Tracking metric between rating
     rounds:** the family-subtraction re-scoring of the preserved 60-finding
     worksheet — after each tier lands (Stage 0 triage; the facts/role
     tier), re-score the worksheet with that tier applied and report the
     projected precision; the M3 arithmetic projects ~0.83–0.93 with both
     families silenced and every consensus Y held. A projection is never a
     substitute for the final two-rater pass; it is the between-rounds
     instrument.
  3. **Speed:** unified ≤ **1.5×** the default pipeline's wall-clock at
     every size, **and the ratio flat-or-better as size grows** (the
     degradation with size, not the multiple, was always the red flag). This
     replaces parity as the flip criterion — an owner product decision,
     grounded in the field: no published Type-3-capable detector reports
     parity with an exact-only baseline; the field's claim is linear scaling
     (NIL's headline mechanism — cheap seeding before expensive verification
     — is this plan's own architecture). The M3 parity gate remains as a
     reported instrument, and candidate 2 becomes optional: taken only if
     this band is missed, since it trades guaranteed capability for a target
     the field itself does not meet.

- **Ruling V — derived artifacts cannot witness wiring (recorded
  2026-09-02).** Stage 0's wiring rungs draw their evidence only from
  **rung-2 survivors** — the files left after the product's own default
  excludes. The defect this closes was found by confirming the cache-tree
  ruling (M4 packet §4.10): Stage 0 triages `ladder[1]`, the raw tree, so
  its symbol and reference graph was built over files the product itself
  would never scan, and a file whose only referents lived inside
  `storage/framework` was judged **wired** on the strength of a compiled
  cache blob. The unwired rung tracked cache state precisely (156 → 157 →
  156 across deletion and regeneration) while the frozen rung 4 did not move
  at all. Two runs over one tree still agreed; two runs over the same
  *project* in different cache states did not, which is the property a user
  has. The separation the ruling draws is between **what Stage 0 reads and
  what Stage 0 believes**: it is still pointed at rung 1 and still triages
  every file there, because ruling T's acceptance requires it to reproduce
  rung 4 from rung one; what it may not do is *count* a derived artifact as
  evidence that something else is alive. Derived artifacts are discarded
  under their own named rung, counted and explained like every other discard
  — never silently. The rung-2 boundary is the shipped default excludes,
  which the cache-tree ruling already classified as a product convention
  like `vendor/` and explicitly **not** the ruling-K path-pattern ban; Stage
  0 consumes that convention from its caller exactly as it consumes the
  manifest, and does not re-derive it.

- **Ruling T amended — role features (recorded 2026-09-02).** The fishiness
  classifier's feature set gains **role features**: what a file *is* within
  the project, asked of content and wiring only. Ruling T's original list
  ends in an ellipsis and its constraint is unchanged — content- and
  wiring-derived only, **never path patterns** — so the feature class is
  authorized here and the specific features remain the executor's to derive,
  with their per-feature log-odds in the packet as the derivation, as for
  every other feature. This is the same facts layer ruling R's integrated
  shape consumes ("wiring, role, region-structure annotations computed
  once"), so role is computed once and read by both tiers rather than twice
  under two definitions.

- **The posture-relative gate rule (auditor, 2026-09-02, granting M4 packet
  ruling request 4(b)); and request 4(a) granted by the project owner.**
  4(a): Stage 0 is wired into `src/` with **both postures implemented and
  selectable**, `discard` left as the default, and nothing else flipped —
  this is the instrument the release decision needs, not the decision. 4(b):
  a gate states **one corpus per comparison**, and **posture differences are
  corpus lines** rather than pass/fail bars. The 204-file retention
  shortfall was scored against a posture that had not been chosen and
  against a 95 % bar that was the executor's own operationalization of
  ruling T's *"approximately reproduces"*; under demote-and-tag retention is
  100 % by construction and the question is the tag's correctness, while
  under discard the retention percentage *is* the coverage cost. Reported as
  the number the owner weighs at the flip, it is evidence; reported as a
  self-imposed pass/fail, it was neither the ruling's bar nor a decidable
  one. The executor does not restate the bar; the comparison states its
  corpus and its posture, and the two postures appear as two lines.

- **Framework auto-detection (project owner, 2026-09-02).** A framework the
  project itself declares is detected, and its preset is **applied by
  default**, **announced** in the run's output, and disabled with
  **`--no-preset`**. The reasoning is the cache-tree ruling's, one step on:
  knowledge the tool already ships about what is not the programmer's source
  should not require a flag to obtain, or the default invocation reports
  noise the tool already knows how to name. Detection is evidence-based, not
  a path guess — the project's own `composer.json` `require` section naming
  the framework package, **and** a structural marker in that manifest's
  directory — and it seeds the preset's **excludes only**, never its scan
  paths, because a preset asked for by name is the user choosing a layout
  while detection has only established what the project is. Announcement is
  not optional: a default that changes what is scanned says so, and names
  the flag that turns it off.

- **Release gate for 2.0.0 (project owner, recorded by M2 audit):** before
  the tag, `bench/check-superset.php` passes with the **merged default
  pipeline** (RK+TokenBag — what 1.4.0 ships) as baseline, not only
  Rabin-Karp. "Detection better than 1.4" means every location the 1.4
  default reports is reported, plus named divergent ranges, named displaced
  blocks, and always-on Type-2 — each shown by a fixture or gate.
- Flip the default to `unified`; keep the old engines selectable one release
  as deprecated, then remove — including the ConQAT-derived `SuffixTree/`
  code and its Apache-2.0 attribution burden (the NOTICE entry goes with the
  code, in the same commit, with a CHANGELOG entry explaining both).
  TokenBag's removal is ruling R's replacement: its capability moves into
  the unified engine's complement pass before the selectable engine is
  dropped, so no detection class is retired with it.
- Docs: README (knob mapping table, guarantee statement), CHANGELOG,
  MODERNIZATION note.
- **Paper revision** (required deliverable, per the project owner): a new
  revision of `docs/paper/token-based-clone-detection-for-php.tex` — the unified
  engine as the resolution of the paper's future-work direction on
  candidate-enumeration cost; the design + provenance; the M3 evaluation
  across the type-density gradient; the E3 replication; the three engines
  repositioned as measured baselines. Established results (rk–phar
  equivalence, E2 lift, E3 findings) carry forward unchanged. Build with
  `bin/build-paper.sh`; keep the paper's style conventions (see the comment
  block at the top of the `.tex`).

---

### After M4 — owner decisions for 2.0.0 (recorded 2026-09-02) and the M5 gate

- **(a) The flip is held.** 2.0.0 ships with the merged pipeline as default
  and `unified` selectable; ruling U bar 2 failed at 0.468/0.574 against
  0.80 and the owner declined the override — when a pre-registered bar
  fails, the default is not to flip. The flip happens when a two-rater pass
  reads ≥ 0.80.
- **(b) Triage ships off by default** — a flag that changes which files are
  reported is itself a flip, held to the same standard.
- **(c) The fishiness classifier ships as a tag**; its retirement is decided
  at the next rating round.
- (d) TokenBag: decided by ruling 6 — selectable-deprecated, 96 adjudicated
  survivors. (e) Candidate 2: moot; the 1.5× band is met at scale.

**M5 opens only on a passing pre-commitment experiment.** Before any engine
change, the two candidate precision rules are prototyped as post-hoc
measurement filters in `bench/` (no `src/` change, no product behaviour
change) and tested against the recorded evidence:

1. **Rule 8 candidate — order-independence:** a span whose statements are
   pairwise dataflow-independent single call-expressions is configuration
   written as statements, not duplicated logic.
2. **Rule 9 candidate — literal-overlap relation:** two shape-matched
   statement-free tables are the same data only if their literal values
   substantially overlap; the floor is **derived** in the experiment from
   the 117 consensus labels (both worksheets), recorded as the measurement.

Pre-registered success criteria, written before the prototype runs:
projected two-rater precision ≥ 0.80 on the M4 worksheet with **zero**
consensus-Y silenced on either worksheet; Php7 ↔ Php8 and every
genuinely-copied-table fixture survive rule 9; a paired negative per rule.
Pass → M5 is the integration milestone (rules 8 and 9 into the engine
through rulings, ruling 7(b), fresh pool on a pinned snapshot, two-rater
re-rate, flip decided by arithmetic). Fail → the residual is
re-characterized and no milestone is built on a dead projection.

### M5 — Stratified assertion, and the flip decided by arithmetic

**Owner decision (2026-09-02), on the M5 experiment's evidence: the
stratified bar is adopted.** The experiment proved the flat bar's noise is
not removable by any rule derivable from the labels, but is *identifiable*
at zero cost to anything the raters valued. Ruling U bar 2 is therefore
restated — same severity, same protocol, aimed at each claim the tool
actually makes: **the 0.80 two-rater bar applies to the ASSERTED stratum;
the demoted stratum is rated in the same pass and its precision is published
beside it, always.** Posture-follows-epistemics at the report layer:
assertions are held to the bar, labels carry their own number. The guards
that keep stratification a lens: strata defined **mechanically and
pre-registered before the pool is drawn** — never by rater hindsight; both
strata rated; nothing suppressed.

Scope, in order:

1. **The strata, pre-registered.** Demoted = (a) statement-free table pairs
   (the dead rule 9's scope, reused whole — zero of 56 consensus-Y findings
   sit in it, so demoting the class costs nothing the labels valued; no
   floor, no constant); (b) registration-role files — a content-derived
   membership definition (a file whose top-level statements are
   predominantly framework registration expressions), never a path pattern,
   with a paired negative (a dataflow-coupled file that must stay asserted);
   (c) classifier fishiness tags (already shipping). Everything else is
   asserted.
2. **The presentation tier.** Inline per-format demote tags (closing the
   file-list-granularity gap §7.3 recorded); **confidence ranking** — rank,
   never filter — derived as counts over the recorded 120-label corpus with
   log-odds printed per finding (ruling T's derivation standard); the
   **acknowledgment ledger** — committed baseline file keyed to content
   hashes of both sides, demote-never-suppress, self-expiring on drift,
   stale entries themselves reported, counted always, no in-code suppression
   annotation ever.
3. **Ruling 7(b)** as granted at the M4 close: attach → verify → back out,
   aligner holds the last word, probe1 re-derived with its derivation
   recorded, ChainBuilder and its oracle untouched, recall 441/441 at both
   sample sizes, corpus effect measured and explained.
4. **The rating round.** Fresh pinned snapshot; fresh pool under the frozen
   corpus definition; executor rates as A; hard stop for the auditor's B
   pass; κ reported. **Pre-registered flip criterion, exactly:** both
   raters' asserted-stratum point estimates ≥ 0.80 (Wilson intervals
   published; the demoted stratum's numbers published beside them). Meets it
   → the default flips in the next release with the owner's recorded
   acceptance of the published demoted-stratum number. Misses it → the flip
   stays held, the residual is characterized, and the bar does not move
   again.

**Gates:** every standing gate green; the posture-relative wall-clock sweep
re-run with the presentation tier counted; hygiene grep at every stop; no
constant without a derivation (the ranking model's parameters are counts
over the recorded label set; the role membership is a definition; the ledger
has no constants); interpretation.md in force with records in the packet's
Interpretations section. Packet to `docs/research/audit/M5-report.md`; the
executor does not close the milestone.

### After M5 — the 2.0.0 landing decisions and the M6 charter (owner, 2026-09-02)

**Release decisions, recorded:**

- **The recall-versus-speed trade is accepted.** 441/441 guaranteed recall
  at both sample sizes ships; the ≤1.5× level reads 1.37–1.86× at the
  largest sizes with the ratio still improving in size (ruling U's own
  red-flag criterion). The release notes state both halves.
- **The fishiness classifier is retired**, per its own pre-registered
  condition ("decided at the next rating round") on that round's evidence:
  every demotion it got right was already produced by the table stratum's
  proof test, and both of its exclusive demotions were consensus-genuine
  findings. Ruling T's lifecycle completes: the estimator scouted, its
  features became proof rungs, it retires. The model, trainer, margin and
  the `fishy` stratum are deleted; the label set is preserved outside the
  repository. Stage 0 becomes all-proof.
- **The SuffixTree is removed in 2.0.0**, not 1.6. The deprecation-release
  convention protects users of defaults and public APIs; an opt-in research
  engine needing 199 s for 50 files protects nobody. Removal takes the
  NOTICE entry and the Apache-2.0 attribution in the same commit, with the
  honest limitation note: the tree still led unified on the pure-insert
  recall family (100/100/86 vs 97/80/70), recorded, not hidden. Its
  `[inconsistent]` capability is superseded by unified's named ranges.
- **TokenBag stays**, per ruling 6 — 69 adjudicated-genuine locations and
  half the shipping default.

**Four process amendments, adopted from the close-audit review:**

1. **Third rater on contested findings.** Every future rating round adds a
   blinded tie-breaking pass over only the findings where A and B disagree,
   producing majority labels. The contested boundary is the binding
   constraint on every precision number; this converts it into fixture data
   at near-zero cost.
2. **Pure-label triage posture, on by default.** A new Stage 0 posture that
   scans everything and only stamps files (orphaned / generated / shadowed)
   — zero findings changed, zero files dropped, zero exit-code effect — so
   it passes the owner's own flip test, which discard and the
   coverage-costing demote posture do not. Discard and demote remain
   selectable; the flip test continues to govern any posture that changes
   reported findings.
3. **Instrument-per-bar.** The M5 charter's freeze stands unamended: the
   flat asserted-stratum 0.80 bar was missed and is never re-litigated. What
   is adopted is the principle that a *new, genuinely different instrument*
   may be pre-registered — never a softer number on an old scale. The first
   such instrument is chartered as M6 below.
4. **SuffixTree timing** — recorded above; the convention distinction
   (defaults and public APIs get deprecation cycles; unusable opt-in
   research flags do not) is the rule going forward.

**M6 charter — ranked precision-at-K, pre-registered before any
measurement.** The flat bar measured every asserted finding equally; a user
experiences a *ranked* report. The new instrument, fixed now: on a fresh
pinned pool under the frozen corpus definition, the **top K = 20 findings by
the shipped confidence ranking** are rated by two blinded raters plus the
third-rater pass on disagreements; the flip criterion is **majority-label
precision ≥ 0.90 over those 20**, with the full-pool numbers published
beside it as always. K = 20 is one report screen — a definition, not a
tuning (recorded here before any count exists; if the pool yields fewer than
20 unified findings, the instrument reports over what exists and says so).
Meets it → the default flips in 1.6 with the owner's recorded acceptance of
the published full-pool numbers. Misses it → the flip stays held under this
instrument too, and any further instrument needs this same pre-registration
discipline. M6's build scope before the rating round: the pure-label posture
(amendment 2), the classifier retirement and SuffixTree removal landing in
2.0.0 first, and ruling 7(b)'s numbers carried as the baseline.

## 3. If a gate fails

Report: which gate, the exact command and output, and which design claim
(§1) it contradicts. Fix within the design if the failure is implementation;
**stop and surface** if the failure is the design (e.g., probe fixture 2
cannot pass and the case is judged in-contract). Never weaken a gate to pass
it, and never adjust a constant to make a specific fixture pass — constants
change only with a recorded derivation.
