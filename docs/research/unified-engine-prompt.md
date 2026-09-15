# Research brief — one owned detection engine to replace three (Rev 2)

Paste this into a clean context. It is self-contained; do not assume prior
conversation.

Rev 2 supersedes Rev 1 (git history holds it). What changed and why:

- Rev 1 proposed "suffix array + LCP, SA-IS is linear therefore fast" and
  asked whether that was right for PHP. Rev 2 answers that question with a
  cost model instead of leaving it open: **asymptotics chosen for C do not
  survive contact with a bytecode interpreter.** The plan below is chosen so
  that every hot inner loop is a C-speed primitive PHP already ships
  (`hash('xxh3')`, `substr_compare`, native hash tables), and pure-PHP code
  runs only over *candidates*, whose count is output-sized, not
  corpus-sized.
- Rev 1's seed stage was "enumerate maximal exact matches from SA + LCP".
  Rev 2 replaces it with **winnowed k-gram fingerprinting** (Schleimer,
  Wilkerson & Aiken 2003), which carries a *provable* no-miss guarantee at
  the tool's own `minTokens` threshold and a provable index-density bound —
  the guarantee Rev 1's design had to get from the index structure now comes
  from a theorem about the sampling scheme.
- Rev 2 adds a **verification stage** (bit-parallel edit distance, Myers
  1999), because chaining alone answers "where" but a heuristic chain must
  never be the last word on "whether" — this is the direct answer to Rev 1's
  open question "where does chaining introduce false positives".
- The validation plan now specifies *how* precision is hand-audited (two
  raters, agreement statistic, confidence intervals) and *how* recall is
  measured without circular fixtures (mutation injection), instead of just
  demanding it.

---

## Task

Design (and then implement) a **single token-based clone-detection
algorithm** for a PHP static-analysis tool that subsumes the three engines
the tool currently ships, is **faster than all of them**, and is **100%
original work** — written from published algorithm descriptions, deriving
from no existing implementation.

Deliverable of this first pass is a **design document plus a validation
plan**, not code.

## Current state — what the three engines do and cost

The tool tokenizes PHP with `token_get_all()`, drops noise tokens, and runs
one or more of these over the resulting token stream:

| Engine | Method | Finds | Measured cost |
|---|---|---|---|
| `rabin-karp` | rolling hash over fixed-width token windows | Type-1 exact contiguous | fast; 2,540 files in ~3.7 s |
| `tokenbag` | SourcererCC-style token multiset + inverted index, overlap ≥ 0.7 | Type-3 reordered | fast; ~2.9 s |
| `suffixtree` | suffix tree + bounded edit-distance approximate matching | Type-3 gapped, and **classifies divergence** | **does not scale** |

Rabin-Karp and TokenBag run merged by default. The suffix tree is opt-in.

**Suffix-tree scaling, measured (warm cache, wall clock, real corpus):**

| files | default (RK + TokenBag) | suffixtree |
|---:|---:|---:|
| 67 | 0.10 s | 1.58 s |
| 208 | 3.60 s | >300 s, killed |
| 571 | 0.50 s | >300 s, killed |

Cause (from reading the code, not profiling): it concatenates every token of
every file into one array, builds a single global suffix tree, then runs
bounded-edit-distance search over it. The blow-up is in the approximate
search, not the construction.

**Published, author-verified record — treat as established fact, do not
re-derive:** the project ships a paper,
`docs/paper/token-based-clone-detection-for-php.tex` (built by
`bin/build-paper.sh`; Pereira, *Token-Based Clone Detection for PHP: An
Empirical Evaluation of Methods and a Labelled Benchmark*). Its measured
results are the baseline this design is evaluated against, not hypotheses to
retest:

- the `rk+tb` default was chosen empirically on six pinned corpora; `rk`
  matches the original phpcpd 6.0.3 phar to two decimals on every corpus;
- the suffix tree's edit-distance DP was already banded (Ukkonen cutoff,
  justified by a band-sufficiency lemma), cutting `findClones` ~3.5× with
  byte-identical output — the **residual, unsolved cost is candidate
  enumeration**, named in the paper's future work as the natural next
  optimization. *The unified engine is that future work.*
- type-anchored normalization lifts specificity by +10.9 to +45.3 points at
  zero recall cost on same-shape-different-type pairs (a Pareto dominance
  result);
- the ecological study (E3, Firefly III, 200-file slice) found 68
  inconsistent clones and two manually verified diverged-copy pairs via the
  suffix tree's `gapped` signal plus a git *staleness gap* metric — the
  concrete bar any replacement's divergence classification must clear.

**One implementation fact the design must exploit:** the default engine
already encodes each file as a packed binary string of **5 bytes per token**
— one byte of token type plus 4 bytes of `crc32` over the (optionally
normalized) token text. This signature string is what the incremental cache
persists. It is byte-comparable, `substr`-sliceable, and hashable at memcpy
speed. The unified engine should treat this string — not a PHP array of
tokens — as its primary data structure.

## What each engine uniquely contributes — the capability that must survive

1. **Exact contiguous duplication** (Rabin-Karp). Precise, no false
   positives by construction.
2. **Reordered duplication** (TokenBag). Statements shuffled within a block.
   Order invariance is what buys this — and what costs it position
   information.
3. **Gapped duplication with divergence classification** (suffix tree). This
   is the subtle one and the reason the engine was kept: it reports `gapped:
   true` / `[inconsistent]` when two copies have *diverged* — one patched,
   its sibling not. That is the most bug-predictive clone signal in the
   literature (Juergens et al., ICSE 2009). TokenBag finds the same *region*
   on gapped fixtures but reports `gapped: false`: it sees overlap and
   cannot see divergence.

**Any replacement must produce the divergence classification.** Matching
spans is not sufficient. The unified engine should aim to do strictly
better: report the *exact divergent token ranges*, which the suffix tree
cannot (it only flags).

## Why Rev 1's index choice is rejected: the PHP cost model

Any design for this tool must be priced in the PHP interpreter's currency,
not in the RAM model. The constants that matter:

- **Interpreted opcodes**: roughly 10⁷–10⁸ simple operations/second. Every
  `$a[$i]`, every `+`, every function call in a hot loop costs ~10–100 ns.
- **Native hash tables**: PHP arrays are C hash tables; insert/lookup is one
  opcode's dispatch plus C work. This is the single fastest data structure
  PHP exposes.
- **String primitives**: `substr_compare`, `strcmp`, `hash('xxh3', ...)`,
  `substr` run at C memcmp/xxHash throughput — GB/s, i.e. 100–1000× faster
  per byte than interpreted loops.
- **Memory**: a PHP list of small ints costs ~16 B/element packed, but any
  non-list-shaped array pays ~36+ B/element plus bucket overhead. A binary
  string costs exactly its bytes.

Price SA-IS + Kasai under this model, for the 2,540-file corpus (~2.5 M
significant tokens):

- SA-IS is linear, but with an interpreter constant of ~30–60 opcodes per
  input symbol across its recursion levels: **≥ 10⁸ opcodes ≈ several
  seconds to tens of seconds** before a single clone is found — slower than
  the entire current default pipeline.
- Memory: suffix array + LCP + rank + type/bucket arrays ≈ 4–6 int arrays ×
  2.5 M elements ≈ **160–400 MB** of PHP arrays. `SplFixedArray` halves it
  at the cost of slower element access (method dispatch per read). A
  `pack('N*')`-encoded string SA costs 10 MB but turns every access into
  `unpack`/`substr` calls — trading memory for more opcodes.
- Every downstream pass (LCP intervals, MEM enumeration) is again a pure-PHP
  loop over all n positions.

Conclusion: **a suffix array is the right structure in C and the wrong one
in PHP.** The same argument disqualifies suffix automata and FM-indexes
(even more per-symbol interpreted work). The design principle that follows,
and that the rest of this brief is built on:

> **Corpus-sized work must happen inside C primitives; interpreted PHP may
> only touch candidate-sized data.**

## The proposed design (challenge it, do not assume it)

A four-stage **fingerprint → extend → chain → verify** pipeline.
Sequence-alignment practitioners will recognize it as BLAST-shaped (Altschul
et al. 1990); in the clone literature it is closest to NIL (Nakagawa et al.,
ESEC/FSE 2021: N-gram seeds, candidate filtering, LCS verification), which
demonstrated that this shape reaches suffix-tree-class recall on large-gap
clones at industrial scale. No stage reads any of those implementations;
each derives from the published description cited with it.

### Stage 0 — Tokenize and encode (exists already)

Reuse the current tokenizer and the 5-byte-per-token signature string, in
two variants: raw (Type-1/3) and normalized via the existing
`TokenNormalizer` (Type-2). Both variants are already what the incremental
cache stores.

*Collision math to state in the design doc:* the 4-byte crc32 folds distinct
token texts; with T distinct texts in a corpus (typically T ≤ 10⁵), the
probability of any crc collision is ≈ T²/2³³ ≈ 0.1% — and a collision only
matters if the two texts also share a token type and appear in
otherwise-identical windows. Acceptable; record it, and note that swapping
crc32 for the low 4 bytes of xxh3 is a free improvement.

### Stage 1 — Winnowed fingerprint index (replaces Rabin-Karp's window scan)

Slide a k-gram window over the signature string; fingerprint each k-gram
with `hash('xxh3', substr($sig, $i * 5, $k * 5))`. Then **winnow**
(Schleimer, Wilkerson & Aiken, SIGMOD 2003): over every window of w
consecutive fingerprints keep the minimum (rightmost on ties), and index
only the kept ones in a PHP hash table `fingerprint → list of (file,
tokenPos)`.

The two theorems that make this the load-bearing stage:

- **Guarantee:** any common token substring of length ≥ w + k − 1 shares at
  least one *selected* fingerprint in both copies. Choose k and w so that
  **w + k − 1 ≤ minTokens** (default 70): e.g. k = 35, w = 36. Then no clone
  the tool is contracted to find can be missed by sampling — recall loss is
  impossible at this stage, by construction rather than by tuning.
- **Density:** the expected fraction of positions selected is 2/(w + 1) ≈
  **5.4%** of the corpus. The index for 2.5 M tokens holds ~135 k entries —
  a few tens of MB of PHP arrays — versus the 100% density of Rabin-Karp's
  per-window hash table today.

Anti-pathology guard (from SourcererCC's and NIL's filtering, restated): a
fingerprint occurring in more than F positions (generated code, boilerplate)
is demoted — its postings are capped and the cap is *counted and reported*,
never silent.

### Stage 2 — Seed extension (pure C-speed)

For each pair of positions sharing a fingerprint, extend the match left and
right to its locally maximal extent with `substr_compare` on the signature
strings — a memcmp, not a PHP loop; binary-search the mismatch point in
O(log L) memcmps, or step by fixed chunks. Output: **anchors** `(fileA,
posA, fileB, posB, len)`, deduplicated by (pair, diagonal `posB − posA`, end
position) so overlapping seeds of one maximal match collapse to one anchor.

This stage subsumes Rabin-Karp exactly: a Type-1 clone of length ≥ minTokens
is one anchor (guaranteed to be seeded, by the winnowing theorem) whose
extension recovers the *full* maximal length — strictly better than today's
fixed-window report, which truncates at window granularity.

### Stage 3 — Per-pair anchor chaining (the classifier)

Group anchors by file pair — candidate-sized data, now allowed in
interpreted PHP. Within a pair, sort anchors by position in A and run
**sparse-DP colinear chaining** (Eppstein, Galil, Giancarlo & Italiano, JACM
1992; textbook form in Ohlebusch, *Bioinformatics Algorithms*, ch.
"Chaining"): O(m log m) with a binary-indexed tree over B-coordinates, m =
anchors in the pair. Score = covered tokens − affine gap penalty. Then
classify, and this is where all three capabilities land in one place:

- **Type-1 / Type-2**: a chain that is a single anchor (or gapless chain) of
  ≥ minTokens. Type attribution comes from which signature variant (raw vs
  normalized) produced it.
- **Type-3 gapped + divergence**: a chain of ≥ 2 anchors on nearly
  consistent diagonals (|Δdiagonal| ≤ editDistance budget) with internal
  gaps. **The gaps between chained anchors are the divergence.** The engine
  reports `gapped: true` and the exact divergent token ranges on both sides
  — the `[inconsistent]` signal falls out of the chain geometry instead of
  an edit-distance search over a global tree. Gap acceptance uses the
  existing knobs: per-gap size bound from `editDistance`, chain must start
  with ≥ `headEquality` exact tokens, total coverage ≥ `minSimilarity`.
- **Type-3 reordered**: the same anchor set, judged twice. If total anchor
  coverage of the two spans is ≥ minSimilarity but the *best colinear chain*
  covers materially less, the anchors are permuted: compute the longest
  increasing subsequence of B-positions in A-order (O(m log m), patience
  sorting per Fredman 1975); a low LIS-coverage ratio with high total
  coverage *is* the reorder signal — position-aware, unlike TokenBag's
  order-blind bag overlap, and it localizes *which* statements moved.

### Stage 4 — Verification (the precision gate)

A chain is a candidate, never a report. For every candidate pair of spans,
verify with **bit-parallel edit distance** (Myers, JACM 1999) over the
signature alphabet, using 62-bit machine words in native PHP ints — O(⌈L/62⌉
· L) per candidate, microseconds at clone scale. Accept iff similarity ≥ the
configured threshold; the verified alignment also tightens the reported
divergent ranges. Hunt–Szymanski LCS (CACM 1977) is the fallback where token
multiplicity makes it cheaper.

This stage exists because Rev 1's honest open question — "where does
chaining introduce false positives that the current engines do not have?" —
has a structural answer: *wherever two spans share sampled fingerprints but
not enough actual content.* Rather than hope the chain heuristic never does
that, every report passes an exact similarity computation on candidate-sized
data.

### Complexity and memory summary

| Stage | Time | Space | Interpreted or C? |
|---|---|---|---|
| Encode | O(n) | 5n bytes ≈ 12.5 MB | existing, cached |
| Fingerprint + winnow | O(n) xxh3 calls | 2n/(w+1) postings ≈ tens of MB | C hash + C xxh3, thin PHP loop |
| Extend | O(occ · log L) memcmps | O(#anchors) | C memcmp |
| Chain + classify | O(m log m) per pair | O(m) | PHP, candidate-sized |
| Verify | O(⌈L/62⌉·L) per candidate | O(L/62) ints | PHP ints, candidate-sized |

The only corpus-sized interpreted loop is the fingerprint scan, and its body
is two opcodes around a C hash call. Target: beat 3.7 s on the 2,540-file
corpus.

**Questions to answer, with evidence (updated from Rev 1):**

- Does chain-gap classification catch every divergence the suffix tree's
  bounded edit distance catches? Construct discriminating fixtures:
  divergence at clone *edges* (no flanking anchor on one side), divergence
  larger than editDistance but smaller than the gap the chain tolerates, and
  multiple small interleaved edits that fragment anchors below k = 35 — the
  known weak spot of any seeded method.
- Is k = 35 / w = 36 the right point on the density–fragility curve? Smaller
  k finds clones with denser edits but raises index density and false-seed
  rate; sweep k on the ground-truth corpus and report the
  recall/precision/time surface, not one point.
- Does the frequency cap F measurably cost recall on real PHP (framework
  boilerplate is exactly where duplication lives)? Report clones lost per F
  value.
- Interleaved reordering *across* function boundaries: TokenBag scopes bags
  to extracted blocks; the anchor pipeline is block-agnostic. Verify the
  reorder classifier does not need block extraction to match TokenBag's
  recall — or bound where it does.

## Hard constraints

- **PHP 8.5+**, zero runtime Composer dependencies, `ext-dom` and
  `ext-mbstring` only. (`hash('xxh3')` is core since 8.1; confirm no
  polyfill path is needed.)
- **Deterministic** — identical input must give byte-identical output every
  run. PHP hash tables iterate in insertion order, so determinism holds iff
  insertion order is derived from sorted file lists; additionally sort all
  emitted clones by a total order before output. No `Date`, no randomness,
  ties in winnowing broken by rule (rightmost minimum), ties in chaining
  broken by position.
- **PHPStan level max** clean, with precise array shapes and generics.
- **Licensing: BSD-3-Clause only.** The implementation must be written from
  *published algorithm descriptions* (papers, textbooks, pseudocode). It
  must **not** be a port, translation, or transliteration of any existing
  implementation — in particular not of ConQAT (Apache-2.0), CCFinder,
  NiCad, Deckard, SourcererCC, NIL, MOSS, PMD-CPD, or the current
  suffix-tree code. Algorithms are not copyrightable; specific expression
  is. The design doc must contain a **per-component provenance table**:
  component → paper/textbook → what was taken (the algorithm) → what was not
  (any code).
- The tool must keep working on a **2,540-file / ~390 k-LOC** corpus in
  seconds, not minutes, on a CI-class machine (≤ 1 GB peak memory).

## What the unified engine will be worse at — say so up front

- **Clones shorter than w + k − 1 tokens** are only guaranteed when
  minTokens is lowered *and* k/w are re-derived from it; the engine must
  recompute k and w from minTokens at startup, and refuse (loudly)
  configurations where k would drop below a floor (~8 tokens) that makes
  seeds meaningless.
- **Densely edited clones** (an edit every < k tokens) fragment all seeds
  and are invisible despite high global similarity. The suffix tree, at its
  unusable cost, could find some of these. Quantify the loss on
  mutation-generated fixtures instead of hand-waving it.
- **Cross-file-boundary aggregation** (one clone class spanning many files)
  requires a union-find pass over pairwise reports; the global suffix tree
  got clone *classes* for free. Pairs-to-classes is a post-pass, and its
  rules must be specified.
- **Two passes for Type-2** (raw + normalized signature) instead of one
  index — a constant-factor cost Rev 1's single normalized index avoided,
  accepted here because reporting *which* type a clone is requires both
  views anyway.

## Validation plan required in the deliverable

This project has been burned twice: by a benchmark that never ran (`timeout`
does not exist on macOS; every run failed instantly with exit 127 and was
recorded as a timeout), and by an engine-removal decision justified by three
hand-written fixtures. So:

- **Every benchmark must verify its own harness first** — assert the timeout
  mechanism works, assert the process actually ran and produced parseable
  output, prefer wall-clock deltas measured in-process over external tools.
  A harness self-test that deliberately times out and deliberately fails
  must pass before any number is recorded.
- Benchmark on **real corpora at several sizes** (60 / 200 / 600 / 2,500
  files), warm cache, ≥ 5 repeated runs, report median and spread — never a
  single run.
- **Precision, hand-audited:** on the current tool, Rabin-Karp reports 16
  clones and TokenBag 24 on the same 2,540-file corpus, agreeing on only 4
  locations — and nobody has checked which are real. Protocol: pool every
  location reported by any engine (old three + new one), stratify by
  engine-agreement cell, have **two raters** independently label each pair
  (clone / not / borderline, with a written rubric), report inter-rater
  agreement (Cohen's κ, target ≥ 0.7; adjudicate disagreements), and report
  per-engine precision with 95% Wilson intervals. The corpus is small enough
  (≤ ~60 locations) to audit *exhaustively* — do it.
- **Recall, without circular fixtures:** ground truth authored by the
  proposer proves nothing. **BCB-PHP already exists — extend it, do not
  rebuild it.** The project's labelled benchmark (paper §6; harness in
  `bench/` of the phpcpd-next/phpcpd repo: `fetch.sh` + pinned SHAs +
  `inject.php`/`injectors.php` + `manifest.json`) is a mutation-injection
  framework over a six-corpus type-density gradient, from WordPress (13.3%
  typed, the untyped control) to PHPUnit (92.7%). Its published results
  stand; new measurement is needed only for the new engine. Extend the
  injector set with the operators this design is weakest against: statement
  insertions/deletions/edits at *controlled densities* (Type-3 gapped) and
  statement permutations (Type-3 reordered), then measure recall per
  operator and per edit density across the gradient. This yields the recall
  *curve* (recall vs. edits-per-100-tokens), which is where the
  k-fragmentation weakness will show — publish the curve, not a summary
  number.
- **Differential testing** against the three existing engines on identical
  inputs: clones found by each, the intersection, and *every* disagreement
  explained by reference to the algorithms (not adjudicated by whichever
  engine is newer).
- **Divergence-classification audit:** for every `gapped: true` report, the
  divergent ranges must be checkable — a test asserts the reported ranges
  actually differ and the surrounding anchors actually match. This is a
  property test on the engine's own output, cheap and total.
- **Ecological replication:** re-run the paper's E3 protocol
  (`bench/run-e3.php` shape: gapped clones, dedupe by file pair, git
  staleness gap) with the unified engine on the same Firefly III slice. It
  must recover both manually verified diverged-copy pairs and report their
  divergent ranges; differences from the suffix tree's 68-clone list must be
  explained, not averaged away.

## Prior art worth reading (updated)

Start here — the project's own record:
- Pereira, **"Token-Based Clone Detection for PHP: An Empirical Evaluation
  of Methods and a Labelled Benchmark"**
  (`docs/paper/token-based-clone-detection-for-php.tex`) — the measured
  baselines, the BCB-PHP benchmark, the type-density methodology, and the E3
  inconsistent-clone protocol this brief builds on. Its results are
  established; the unified engine answers its future-work direction on
  candidate-enumeration cost.

Index / sampling:
- Schleimer, Wilkerson & Aiken, **"Winnowing: Local Algorithms for Document
  Fingerprinting"** (SIGMOD 2003) — the selection guarantee and density
  bound Stage 1 is built on.
- Karp & Rabin (1987) — rolling fingerprints, background for the current
  engine.

Seed–extend–chain:
- Altschul et al., **BLAST** (J. Mol. Biol. 1990) — the seed-and-extend
  paradigm.
- Eppstein, Galil, Giancarlo & Italiano, **"Sparse Dynamic Programming I"**
  (JACM 1992) — O(m log m) colinear chaining; textbook treatment in
  Ohlebusch, *Bioinformatics Algorithms* (2013), the chaining chapter.
- Fredman (1975) — longest increasing subsequence in O(m log m), the reorder
  measure.

Verification:
- Myers, **"A Fast Bit-Vector Algorithm for Approximate String Matching
  Based on Dynamic Programming"** (JACM 1999) — Stage 4.
- Hunt & Szymanski (CACM 1977) — LCS alternative for sparse matches.

Clone detection:
- Nakagawa, Higo & Kusumoto, **NIL** (ESEC/FSE 2021) — N-gram seeding +
  filtering + LCS verification for large-variance clones; the empirical
  proof this pipeline shape reaches suffix-tree-class recall. Read for
  evaluation design; take no code.
- Wang et al., **CCAligner** (ICSE 2018) — large-gap clone detection with
  e-mismatch windows; alternative seed definition worth comparing on the
  gapped fixtures.
- Sajnani et al., **SourcererCC** (ICSE 2016) — the token-bag approach being
  replaced; its filtering math (prefix filtering, frequency ordering)
  informs Stage 1's cap.
- Kamiya, Kusumoto & Inoue, **CCFinder** (TSE 2002) — canonical token
  normalization.
- Roy & Cordy — clone surveys and the **mutation-injection evaluation
  framework**; the Type-1/2/3 taxonomy and the recall methodology above.
- Juergens et al., **"Do code clones matter?"** (ICSE 2009) — inconsistent
  clones carry bugs; the justification for treating divergence
  classification as non-negotiable.

Explicitly *not* input material: source code of any tool above, ConQAT,
PMD-CPD, MOSS server code, or the current suffix-tree implementation. Papers
in; code out.

## Output format

1. Recommended design, with the data structures and their complexity
   **priced under the PHP cost model above** (opcodes and bytes, not just
   big-O).
2. Per component: the provenance table — published source, what was derived,
   why that is licence-clean.
3. How each of the three current capabilities is reproduced — especially
   divergence classification, with the discriminating fixtures from the open
   questions.
4. Honest list of what the unified engine would be *worse* at than the
   current three (start from the list above; extend it).
5. Validation plan per the section above, including the harness self-test.
6. Only then: a staged implementation plan. Suggested stages, each shippable
   and individually benchmarked: **M0** harness self-test + BCB-PHP
   extension (the new gapped-density and permutation injectors — before any
   engine code; this project's history demands it); **M1** Stage 1–2 behind
   `--algorithm=unified`, validated to strictly subsume Rabin-Karp's
   reports; **M2** chaining + classification, validated against suffix-tree
   and TokenBag fixtures and the E3 replication; **M3** verification + the
   precision audit; **M4** flip the default, deprecate the three engines,
   remove the ConQAT-derived code and its Apache-2.0 attribution burden,
   update docs/CHANGELOG/README.
7. **After the code lands: revise the paper.** The whitepaper is the
   project's citable record and must not be left describing a superseded
   architecture. A new revision of
   `docs/paper/token-based-clone-detection-for-php.tex` documents the unified
   engine as the resolution of the paper's own future-work direction (the
   residual candidate-enumeration cost): the fingerprint→extend→chain→verify
   design and its provenance table, the BCB-PHP evaluation of the new engine
   across the type-density gradient, the E3 replication, and the three
   original engines repositioned as the measured baselines they now are.
   Established results from the current revision (rk-phar equivalence,
   type-anchored lift, E3 findings) carry forward unchanged — they are the
   baselines, not things to re-run.
