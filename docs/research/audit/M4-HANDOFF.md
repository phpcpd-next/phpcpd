# M4 handoff — written by the auditor at M4 open (2026-09-01, after `e9293b8`)

You are the **executor** for milestone M4 of the unified engine. M3 closed with
`AUDIT: pass` (`23613ed`); the project owner resolved decision point 2 (TokenBag
is **replaced**, ruling R) and directed M4 to open. The M3 close verdict's entry
gating is amended accordingly: the two remaining owner decisions — candidate 2
and the default flip — are **release-time** decisions, deliberately deferred
until the work below has changed the numbers they depend on. Do not take either
of them yourself.

**Read first, before any code:** the plan's §"Rules for the executing session"
and §3, then §2 M4 in full — the M3-close scope block (rulings O, P), rulings R
and S, the owner's release gate, and the decision points. Then the M3 packet's
two audit verdicts. Constants change only with a recorded derivation or ruling;
when a gate cannot pass as specified, stop and surface.

The audit packet goes to `docs/research/audit/M4-report.md`. Surface ruling
requests as they arise rather than batching them to the end — this milestone
has natural stop points and the auditor expects to be used at them.

## Order of work, and why this order

### 1. Instrument hygiene — the bench corpora were partly dirty, and two constants drank from it

Found by the project owner at M4 open, verified by the auditor:
`bcb_files()` prunes only `vendor`, `node_modules`, `storage`,
`bootstrap/cache` — and `bench/corpus/phpunit` contains **115 `.php` files
under `tools/.phpstan`**, a dumped static-analysis cache, scanned in every
phpunit number from M1 through M3. php-parser and symfony-string are clean;
firefly-iii's one match (`config/cache.php`) is an ordinary config file, not
dirt. The dogfood corpus's `.trash`/backup/cache trees are already governed by
rulings K and P and are not this item.

Do, in this order:

1. **Fix the walker on principle, not with a longer hand list:** `bcb_files()`
   honours the same default excludes the product itself applies (ruling K's
   principle at the bench layer — the tool already knows what is not program
   text). Its own CHANGELOG entry.
2. **Re-run every standing phpunit gate and diff.** Superset residual,
   wall-clock sweep, determinism, the corpus facts. Any recorded M1–M3 number
   that moves gets a dated **correction note appended to its packet** — the
   retraction discipline, never a silent supersession. Re-measure the
   "runaway fingerprint" fact (9,179 occurrences / 13 files / 706 per file):
   establish whether those files are the `.phpstan` dump.
3. **Re-derive the two knee-derived constants on the clean corpus:**
   `FingerprintIndex::PER_FILE_CAP = 32` (ruling D) and
   `AnchorSet::SEED_PAIR_CAP = 16000` (ruling N) both took their values from
   phpunit dense-coverage sweeps that included the dump. Re-run each sweep
   clean. If a knee moves, the constant moves **with the new derivation
   recorded and the old one struck through in the packet** — this is
   re-derivation under the same method, not tuning; if the knee holds, record
   that it held. `IndexCodec::VERSION` policy per ruling Q: bump only if a
   selection rule actually changes (PER_FILE_CAP changing ⇒ bump;
   SEED_PAIR_CAP changing ⇒ no bump).

Everything after this step cites only clean numbers.

### 2. Ruling O — a refused candidate must not consume its evidence

Spec and acceptance in plan §2 M4: the traced phpunit pair reports its two
exact clones again, the residual returns to its clean-corpus baseline (21 on
the dirty corpus; step 1 re-pins the number), recall stays 295/295, chaining
oracle stays 2/2, no constant. The mechanism is yours to derive — the refused
reading must not simply be rebuilt next round — with the derivation recorded.

### 3. The merged-default baseline gate — build it, run it, report it

`bench/check-superset.php --baseline=default` (RK+TokenBag merged, what 1.4.0
ships), through the self-tested harness like everything else. This is the
owner's release-gate instrument and it has **never been run**. Run it on the
bench corpora and report honestly. The known risk is the sub-K permutation
class (TokenBag leads unified 77.5 % vs 73.6 % adjacent, 81.4 % vs 61.9 %
distant) — a failure there is expected, is ruling R's job to close, and is
reported as a number, not patched.

### 4. Stage 0 — corpus triage (rulings P and T together)

Before any precision work, and read ruling T in plan §2 M4 in full first —
it is the owner-directed design item of this milestone:

- (i) take a **pinned snapshot** of the dogfood corpus (frozen copy or
  recorded content-hash list, outside every repository, never named — the
  live tree drifted 74 → 1,030 pooled findings during M3, so live numbers
  are not evidence);
- (ii) implement the shadowed-duplicate rung of ruling K — from the tool's
  own symbol table and autoloader mapping (two files declaring the same
  symbols, only one loadable), **never a path pattern**. Verify it removes
  the backup subtree that contaminated ~30 % of the M3 pool, and report what
  else it removes;
- (iii) build ruling T's triage stage: wiring first, then the naive-Bayes
  fishiness classifier — POPFile-style, original dependency-free PHP,
  content- and wiring-derived features only, asymmetric margin toward
  keeping, counted and explained discards. Train on the pinned label set the
  plan names (the M3 ladder's kept-vs-excluded files, phpunit's
  `tools/.phpstan` dump vs its real tree, the worksheet files, the public
  corpora as clean priors) — never on a gate. The acceptance fixture is the
  ladder itself: pointed at the raw disk tree, Stage 0 approximately
  reproduces the frozen definition's fourth rung from rung one; no file
  holding a consensus-Y finding is discarded; determinism holds. The engine,
  the bench walker, and the pool all consume the same stage, so step 1's
  walker fix is the interim and Stage 0 is its replacement. The default
  posture (discard vs demote-and-tag) is prepared for the owner's release
  decision, not taken.

### 5. The precision discriminator — ruling H's one open avenue, now the second tier

Stage 0 removes the dump/backup/trash false-positive *mass*; what remains is
the wired literal table — a seeder, a data-provider return — which is program
text and survives triage by design. That is this step's target, and it is
span-level structural context only: what a span *is* (a data-provider return,
a literal array, a dumped table) rather than any statistic of it. The three
refuted discriminators — anchor multiplicity, tokens-per-line sparseness,
logic share — are dead and stay dead. Measure after Stage 0 lands, so this
tier is sized against what triage actually leaves. Acceptance, all
pre-existing (the 57-label fixture arbitrates both tiers together):

- the 57 consensus-labelled findings from the M3 worksheets (ask the project
  owner for the preserved directory; it is outside every repo, mode 600): every
  consensus data-table N silenced, **zero** consensus Y lost;
- the symfony-string Unicode-table pair stays silent; `Php7.php`'s action table
  (a true positive inside dispatch code) stays reported;
- superset gates unchanged or every change explained.

Any new constant needs a derivation. If the discriminator wants a threshold
that has no derivation, stop and surface the measurement.

### 6. Ruling R — the order-free complement pass

Full spec in plan §2 M4; build it after the discriminator, because the
discriminator changes the candidate volume the pass sees and the wall-clock
base it is measured against. The five design constraints are binding; the
inherited TokenBag source is on the **never-open list** — build against the
recall curves and fixtures. k needs its derivation (measured statement-length
distribution; r1's 7-token swaps are the known floor case). Acceptance:
permutation recall ≥ TokenBag's 77.5 % / 81.4 % (beating it is the target), r1
in contract, step 3's gate passing its TokenBag half, wall-clock re-run with
the pass counted.

### 7. Re-measure, then prepare the two owner decisions

With the hygiene fix, O, the discriminator, and R landed: re-run the
wall-clock sweep and re-pool precision on the pinned snapshot under K+P.
**The re-rate is a stop point:** generate the blinded worksheet, rate as
rater A, and hand off for the auditor's rater-B pass — do not compute κ from
your own verdicts alone. Then write up the two remaining decisions against the
fresh numbers: candidate 2 (the drafted guarantee is in the M3 packet's close
section) and the default flip (requires both gates passing or the owner's
recorded acceptance). Prepared, not taken.

### 8. Only after all of the above: the landing sequence

The owner's release gate (superset vs merged default, plus the capability
demonstrations, each by fixture or gate), the deprecation/removal sequence
(SuffixTree with its NOTICE entry in the same commit; TokenBag only after
ruling R's pass covers it), ruling S's inventory as its own packet section,
and the paper revision per plan §2 M4 — which now also inherits step 1's
corrections wherever it cites a contaminated number. If M4's earlier steps
change what the paper must say, note it as you go rather than reconstructing
at the end.

## Standing rules

- One CHANGELOG entry per change, each riding in its own commit; README matches
  reality at every stop point. `IndexCodec::VERSION` stands at 5 (ruling Q) —
  do not re-bump for changes that alter no stored byte and no selection rule.
- The never-open list now includes the in-tree TokenBag strategy (ruling R) and
  any inherited file during its ruling-S replacement; the other detectors and
  `SuffixTree/` internals as always. New files BSD-3-Clause until ruling S's
  endgame commit.
- The dogfood corpus is never named; worksheets and snapshots never enter a
  repository. Stage explicit paths only. PHPStan level max clean, both
  configs. `check-incremental` runs against `bench/corpus/php-parser`.
- You do not close your own milestone.

## Facts already established — do not re-derive

- Gate state at M4 open (**dirty-corpus numbers** — step 1 re-baselines them):
  recall 295/295 · superset php-parser 3/3 · superset phpunit 22 items (→ 21
  expected after O) · wall-clock 0.78×/0.52×/0.59×/0.42× failing, degrading
  with size · precision 0.340/0.358 vs 1.000, κ = 0.898.
- The contamination inventory: phpunit `tools/.phpstan` = 115 files;
  php-parser and symfony-string clean; firefly's `config/cache.php` a false
  hit. Verified at M4 open — do not re-scan to rediscover it.
- 91 % of normalized-view file pairs yield nothing; self-pairs were 73 % of
  candidate time pre-N; phpunit never reaches `SEED_PAIR_CAP`.
- The M3 residual's composition (1 uncovered location + grouping differences,
  several baseline over-reports) is itemized in the M3 packet — cite it, don't
  recount it.
- TokenBag rated 7/7 on the M3 audited set; the successor inherits that bar.
