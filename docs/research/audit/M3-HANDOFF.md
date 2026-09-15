# M3 handoff — written by the auditor at M2 close (2026-08-31, `aab923f`)

You are the **executor** for milestone M3 of the unified engine. The plan is
`docs/research/unified-engine-plan.md`. M2 closed at `aab923f` with a recorded
`AUDIT: pass`; the full record is `docs/research/audit/M2-report.md`.

**Read first, before any code:** the plan's §"Rules for the executing session"
and §3 "If a gate fails". M2's process section records what happened the one
time they were skipped: a constant swept against a failing gate, a revert, and
a milestone that took an extra round to close. Constants change only with a
recorded derivation or an auditor's ruling — when a gate cannot pass as
specified, stop and surface the measurement; do not tune past it.

## Order of work

M3 is the measurement milestone, but it opens with two design items ruled into
its scope at M2 close. They land **before** the measurement passes, because
whatever they change is the engine the raters rate and the curves measure.

### 1. Ruling J — same-file fine-granularity class emission

The phpunit subsumption residual (66 items, 63 of them `MetadataTest.php`
against itself) is a genuine recall loss under the field's own pair-matching
criterion (Bellon et al., IEEE TSE 33(9), 2007), and it blocks M4's release
gate, so it cannot be documented away.

- **Target output, already ruled:** where a file holds N mutually-similar
  blocks, report **one clone class naming every block** at the fine period —
  not the coarse half-against-half clone (information-destroying), and not the
  baseline's quadratic pair list (the blow-up pairs-to-classes exists to
  prevent).
- **Evidence to build from:** the fine period is already isolated upstream —
  shift banding splits the raw view's 9,286-anchor cluster into 24 bands, 139
  anchors on shift 351. The defect is downstream: class formation collapses
  granularity whatever the candidates look like.
- **Constraints:** the mechanism needs a derivation. `SITE_OVERLAP` changes
  only under a recorded ruling. Ruling G's scope is settled — `overlaps()`
  (symmetric, longer-range) is site identity; `subsumes()` stays at the two
  covering call sites; do not re-open that split, and do not weaken probe 4's
  documented clone count (plan §3: never weaken one gate to pass another).
- **Acceptance:** `bench/check-superset.php bench/corpus/phpunit` — report the
  gate's result either way; if a residual remains, measure and itemize it as
  the M2 packet did. php-parser must stay 3/3. `MetadataTest.php`'s
  whole-halves clone is a **true positive** (89% line-identical) — the fine
  classes must not lose that fact, they must state it as the stronger relation.

### 2. Ruling F — wall-clock closure

Gate (M3's own): `php bench/check-walltime.php` — unified ≤ the default
pipeline on every corpus. Starting position: 0.37–0.42× phpunit, 0.36–0.38×
php-parser, 0.66–0.76× symfony-string (machine-dependent; both endpoints
recorded in the M2 packet).

The known cost is the normalized view's anchor volume (php-parser: 71,619
normalized anchors vs 5,251 raw; 77% of phpunit's time in `candidates()` on
the normalized view). The three candidate design changes are recorded at the
end of the M2 packet, and **none is tuning**:

1. re-opening ruling B's diversity floor (measured at 3 — a change needs a new
   measurement and a ruling);
2. sampling the two views at different rates — touches the winnowing
   guarantee, so it needs the theorem restated, not just a number;
3. collapsing a file's internal repetition before pairing it against every
   other file — likely composes with ruling J's work.

Pick on the evidence, surface the derivation, and if the gate still cannot
pass without an underivable constant, stop and request a ruling with the
measurement attached.

### 3. The measurement program (plan §2 M3, unchanged)

- **Recall curves** from the M0 injectors: recall vs. edits-per-100-tokens and
  vs. permutation distance, per corpus across the type-density gradient. The
  permutation curve is also the instrument that measures the sub-K
  displacement class (fixture r1's kind) — M4 revisits TokenBag's removal if
  that loss is material, so this curve carries a decision.
- **Precision:** pool all locations reported by any engine on the dogfood
  corpus (~≤ 60 total), exhaustive **two-rater** audit per the brief — rubric,
  Cohen's κ ≥ 0.7, Wilson intervals. The corpus's name, paths, and source
  strings never appear anywhere.
- **Wall-clock** at 60 / 200 / 600 / 2,500 files, ≥ 5 runs, median + spread,
  through the self-tested harness only.

**Gates:** unified ≥ default-pipeline speed at every size; the recall curve
shows the guaranteed region (≤ 1 divergence, runs ≥ S) at 100%; precision not
below the better of RK/TokenBag on the audited set.

## Standing rulings that bind M3

- **Ruling H (precision):** the three refuted discriminators — anchor
  multiplicity, tokens-per-line sparseness, logic share — are **dead; do not
  re-propose them**. The data-table false-positive shape goes through the
  two-rater protocol. The one avenue open for prototyping is *structural*
  context (data-provider / literal-array-return detection); the `Php7.php`
  action-table counter-example does not refute it. No span-statistic filter
  ships.
- **Clustering stays permissive** — the code comment at
  `UnifiedStrategy::cluster()` explains why; tightening it breaks probe 2.
- **Ruling I:** all E3 numbers derive from the reproducible last-commit-time
  slice; the old claimed `NavigationAddPeriodTest` recovery stays unverified.

## Process, non-negotiable

- After the milestone: PHPStan level max clean (both configs), full suite
  green, determinism 4/4, CHANGELOG.md and README.md matching reality — one
  CHANGELOG entry per change, never folded.
- `check-incremental` must be pointed at a corpus
  (`bench/corpus/php-parser`); its default directory contains no clones and
  fails its own sanity guard.
- Licensing: never open, port, or paraphrase any other clone detector's source
  or `SuffixTree/` internals. New files BSD-3-Clause, (c) 2026 Luciano
  Federico Pereira. Where a change touches inherited code, replacing it with
  original work is a standing bonus.
- Stage explicit paths only — no `git add -A`.
- Write the audit packet to `docs/research/audit/M3-report.md`: exact
  commands, verbatim outputs, deviations recorded rather than silent. **You do
  not close your own milestone** — the packet ends awaiting the audit verdict.

## Corpus facts already established — do not re-derive

- phpunit's runaway fingerprint: 9,179 occurrences across 13 files (706/file),
  99.4% of the normalized view's anchor pairs before the C=32 cap.
- php-parser's worst offender: two files at 160 occurrences each — invisible
  to any document-frequency rule.
- `MetadataTest.php`'s coarse clone (lines 33–2691 vs 3054–5712): true
  positive, 89% line-identical.
- The M2 residual, itemized: 31 locations + 32 pairs `MetadataTest.php` self;
  `ProgressPrinterTest.php:212 ↔ ResultPrinterTest.php:1023` (pre-existing,
  different-alignment shape); `assertArraysHaveEqualValuesIgnoringOrderTest.php:269
  ↔ assertArraysHaveIdenticalValuesIgnoringOrderTest.php:344` (70 tokens, the
  one item ruling G added).
