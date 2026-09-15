# M7 pre-commitment — making the whole token stream visible

Written **before** any measurement of the change it governs. Its purpose is to
fix what would count as success while the answer is still unknown, because the
alternative — deciding afterwards, from numbers already seen — is taste with a
number attached.

Nothing here states a measured value. Every bar below names a gate that already
exists and says which way it must come out; the values live in the gates, so
this document cannot go stale and cannot be quietly re-fitted.

## The defect

`DefaultStrategy::tokenize()` records only tokens `token_get_all()` returns as
arrays. Every single-character token — `; { } ( ) , = + - * / . < >` — comes
back as a bare string carrying no line number, and was dropped for want of one.

That is roughly half the program text, and it is the half that says what the
code *does*. With `+`, `-`, `*` and `.` invisible, `$x = $a + $b;` and
`$x = $a - $b;` have identical signatures. Two files differing on **every**
operator are reported as an exact, non-gapped clone — a claim that code which
differs is character-for-character identical.

The fix is to record them, giving each the line of the token before it, which
is sound because a newline only ever appears inside a whitespace or comment
token and both of those are arrays.

## Why the fix is not free, and what that implies

A token stops meaning what it meant. On the reference corpora the signature
grows by roughly nine tenths, so a window of `--min-tokens=N` spans about half
the source it used to. Every constant expressed in tokens keeps its arithmetic
and changes its reach.

Two consequences follow, and they are different in kind:

- **`Winnower::MINIMUM_MIN_TOKENS = 2·SEED_LENGTH + 6` is untouched.** It is
  the winnowing bound of Schleimer, Wilkerson and Aiken (2003), stated in
  *k*-grams. The guarantee holds whatever a token is, so no re-derivation is
  owed and none will be attempted.
- **`SEED_LENGTH`, `RATIO`, `DISPLACED_MASS_FLOOR` and the shipped
  `--min-tokens` default are calibrations**, not derivations. Their values were
  chosen against measurements taken in the old unit. Whether they still express
  the intent they were chosen for is an empirical question, and it is the
  question this experiment exists to answer.

## What must hold — the gates, and which way

Each of these already exists and already prints its own verdict. A change that
cannot keep them green is rejected, whatever it does for the defect above.

| gate | requirement |
|---|---|
| `bench/run-recall.php` | the guaranteed region stays at full recall — this is a gate, not a curve point, and the winnowing guarantee is what it tests |
| `bench/check-superset.php` (php-parser, phpunit) | no baseline location lost that its adjudicator judges real |
| `bench/check-determinism.php` | unchanged |
| `bench/check-incremental.php` | unchanged, with the index version bumped for the new signature format |
| `bench/check-chaining.php` | unchanged |
| `bench/audit-precision.php` | asserted-stratum precision no lower than the bar the M5 report recorded, on the same rated pool |
| `tests/fixtures/collision` | files differing on every operator report no clone |

## The methodological prerequisite

**`bench/run-recall.php` injects clones sized in tokens.** With a token worth
about half of what it was, a fifty-token injection is a different experiment
before and after, and a recall comparison across the change would be measuring
the ruler rather than the engine.

So the harness is normalised to inject by **source lines** before any recall
number is read. Until that is done, no recall figure from this experiment is
admissible — including a favourable one.

## Choosing the default threshold

`--min-tokens` has to move, because leaving it makes a window span half of what
a user configured it for. The criterion is fixed here in advance:

> The default is chosen so that the **median size of a reported finding**, in
> lines, on the reference corpora, matches the pre-change median within a fifth.

Median finding size, and deliberately **not** clone count. Count is the wrong
instrument: the fix is expected to find duplication the blind engine could not
see, so a count that rises may be the fix working and a count that matches may
be the fix being cancelled out. Size says whether the tool still reports the
same *kind* of thing.

## What would reject the change

- The guaranteed region falls below full recall at every candidate threshold.
- `check-superset` loses a location its adjudicator judges real, at every
  candidate threshold.
- Asserted-stratum precision falls below the M5 bar and no threshold recovers
  it.

Any of these and the operator fix does not ship in this form, however
compelling the false positive it removes.

## What is not evidence

A probe passing after its expected value was edited to match the new output.

`UnifiedProbesTest` encodes rulings — the displaced-mass rule, the
edge-divergence rule, the guarantee's documented miss. Seven of them fail under
this change. Each failure means one of two things, and **the failing test cannot
say which**: either the engine is now correctly more conservative, or a
calibration no longer fits the unit it is expressed in. Deciding by editing the
expectation until it is green answers neither and records nothing.

Each probe is therefore resolved by naming, in writing, which of the two it is
and on what evidence, before its expectation is touched. A probe whose failure
cannot be explained that way blocks the milestone.

## The stratum question, separately

`FileFacts::span()` maps a reported line range to token positions. A line
holding only punctuation used to contribute no tokens and now contributes some,
so a span can reach into the statement that encloses it — which makes
`literalAppendRun()` refuse a range that is not wholly appends, and the seeder
finding comes back with no stratum.

The question is whether a stratum should classify the statements a span **fully
covers** or those it **overlaps**. This is not novel: Bellon et al. (2007), the
reference comparison of clone detection tools, judges matches by overlap
thresholds rather than exact spans precisely because tools disagree about
boundaries by a line or two, and exact-boundary matching does not survive
contact with real tools.

Decision rule, fixed here: the overlap reading is adopted only if, on the rated
pool, it changes no finding's stratum except ones a rater labelled a boundary
artefact. If it moves a finding a rater judged on its merits, it is rejected.
