# M3 close handoff — written by the auditor at the rework verdict (2026-09-01, `02e07fd`)

You are the **executor**, continuing milestone M3 of the unified engine after its
rework verdict. The verdict, the rulings, and the close conditions are at the
bottom of `docs/research/audit/M3-report.md`; the rulings also live in the plan,
which is authoritative.

**Read first, before any code:** the plan's §"Rules for the executing session"
and §3, then §2 M3's rulings block (K, L, M, N, F, precision), then the M3
packet's audit verdict. Constants change only with a recorded derivation or
ruling; when a gate cannot pass as specified, stop and surface the measurement.

## Step 0 — preserve the rater worksheets (do this before anything else)

The two rated worksheets and the attribution key live in session scratchpads
under `/private/tmp`, which the OS may clear:

- rater A + key: `/private/tmp/claude-501/-Users-studiox-Downloads-phpcpd-main/f50349bf-7e48-4efb-98c4-b1541963048c/scratchpad/` (`audit.tsv`, `audit.key.tsv`, `audit-raterB.tsv`)

Copy all three to a durable directory **outside every repository** (they quote
closed-source corpus text and must never be committed — `pool` itself refuses to
write inside the repo, honour the same rule by hand). They are the record behind
κ = 0.898 and the acceptance fixture for the precision fix.

## The close conditions, in order

### 1. Correct packet §7 (audit defect 1)

§7's pool transcript is the corpus ladder's *third* rung (588 unified clones,
600 pooled) while the worksheet that was actually rated is the *fourth* (74
pooled: unified 65, tokenbag 9, rabin-karp 2 — which the per-engine denominators
53/7/2 confirm). Correct the transcript to the rated pool and add the full
ladder with per-engine counts at each rung, per ruling K:

| corpus definition | unified | rabin-karp | tokenbag | pool |
|---|---:|---:|---:|---:|
| every `.php` on disk, prefix slice | 952 | 65 | 2 | 1009 |
| every `.php` on disk, even stride | 841 | 56 | 5 | 897 |
| + entire-file orphans dropped | 588 | 13 | 3 | 600 |
| + preset excludes honoured for the scan corpus | 65 | 2 | 9 | 74 |

Re-run `score` against both preserved worksheets so the corrected §7 quotes the
two-rater output verbatim (κ 0.898; A: unified 18/53 = 0.340 [0.227, 0.474];
B: unified 19/53 = 0.358 [0.243, 0.493]; both baselines 1.000).

### 2. Ruling M — the guaranteed-region fix (two commits)

- (a) Exempt the two **outer flanks** from `MINIMUM_RECOVERED_RUN`. This is §1
  Stage D1's own wording ("longest common prefix and suffix at each flank", no
  minimum); a flank run has nothing beyond it to bridge to. You already built
  and measured this once — rebuild it as its own commit.
- (b) `ChainBuilder` breaks **equal-score** chains toward greater coverage — a
  total-order completion, no constant. Extend the brute-force oracle to the same
  tie order and re-run it over its full 3,000-set sweep before trusting anything.

Conditions: recall gate re-run — expected **295/295**; if anything still misses,
re-trace it completely and surface (never tune). Both superset gates re-run
(php-parser 3/3; phpunit residual 21, not grown unexplained). Two CHANGELOG
entries, each riding in its own commit.

### 3. Ruling N — the seed-pair bound

`AnchorSet` pays Θ(n²) in a fingerprint's posting count; `POSTINGS_CAP` bounds
the list, not the pairs (C(1000,2) = 499,500 from one capped fingerprint; 600
dogfood files → 14.7 M normalized pairs → 3 GB exhausted). The bound is restated
on **pairs emitted per fingerprint**, ruling D's argument one level up, same
admitted class of counted recall trade.

- Value by ruling D's method: the **dense-coverage knee** (union of reported
  lines at ≥ 4 tokens/line), measured on phpunit **and** the 600-file dogfood
  slice — never on wall-clock or a failing gate. Record the sweep table as the
  derivation.
- Counted and surfaced exactly as `F` and `C` are; `IndexCodec::VERSION` bumps
  with rationale; one CHANGELOG entry of its own; the code comment cites
  ruling N.
- Acceptance: the 600-file dogfood slice completes within the **default** memory
  limit; php-parser superset 3/3; phpunit residual not worse unexplained.
- Streaming the pair enumeration is optional, measured if done, and does not
  replace the bound.

### 4. Wall-clock, after M and N

Re-run `php bench/check-walltime.php bench/corpus/phpunit --sizes=60,200,600,2500`
in a quiet window and record honest numbers either way. If the gate still
fails: draft candidate 2's restated guarantee (normalized view at W = 55 —
guarantee narrows to exact normalized runs ≥ minTokens, raw view untouched) and
put it in the packet **for the project owner's decision. Do not implement it.**

### 5. Docs

CHANGELOG and README match reality after M and N. One entry per change, never
folded, and pair each entry with its own commit (audit defect 2 was an
entry riding in a neighbour's commit).

### 6. Append the final numbers to the packet and stop

The packet ends awaiting the close-audit verdict. You do not close your own
milestone.

## Standing constraints

- **Ruling K is frozen.** The audit corpus is the tool's own machinery
  (defaults + presets + `--wired-only`); nothing further is excluded without a
  recorded ruling *before* it is applied — whatever the pool shows.
- **Dead discriminators stay dead** (anchor multiplicity, tokens-per-line
  sparseness, logic share). The precision fix is ruling H's structural-context
  avenue, and it is **not** in this close's scope — its acceptance fixture (the
  57 consensus-labelled findings) is preserved in step 0 for when it is.
- The dogfood corpus's name, paths, and source strings never appear anywhere;
  worksheets never enter a repository.
- Licensing: never open another clone detector's source or `SuffixTree/`
  internals; new files BSD-3-Clause, (c) 2026 Luciano Federico Pereira.
- Stage explicit paths only. PHPStan level max clean on both configs after
  every change. `check-incremental` runs against `bench/corpus/php-parser`.

## Facts already established — do not re-derive

- The four recall misses are one shape, fully traced in packet §4 (49-token
  chain, 2-token tail recovered by extension, dropped on an exact tie, span 49
  < 50). The half-fix alone does not move the gate; both halves are needed.
- The wall-clock hot spots: self-pairs are 73 % of candidate time from 239 of
  9,596 pairs; `MetadataTest.php` alone a quarter of it (packet §5).
- The seed-pair table at 60/200/600 files, both views, is in packet §6.
- Candidate 3 (one-pass decomposition) is implemented-measured-**rejected**:
  more volume, same recall, more work. Do not retry it.
- κ = 0.898; the three inter-rater disagreements (findings 016, 024, 030) are
  the parameterized-wiring/adapted-copy boundary; 26 of rater B's 34 N's come
  from two files (menu dump, ISO region table).
