# M5 audit packet — stratified assertion, and the flip decided by arithmetic

**Status: open.** This packet is written as the milestone is executed and ends
awaiting the close-audit verdict. The executor does not close its own milestone.

The charter is the plan's **"M5 — Stratified assertion, and the flip decided by
arithmetic"** section, opened by the project owner's decision of 2026-09-02 on the
M5 pre-commitment experiment's evidence. The numbers this milestone exists to move
are the M4 packet's §10 (the two-rater score, ruling U bar 2 recorded failing at
0.468 / 0.574 against 0.80) and §15.1 (the audited scorecard).

---

## 0. Pre-registration — written before anything was run

The charter's first guard is that the strata are *"defined **mechanically** and
**pre-registered before the pool is drawn*** — never by rater hindsight". This
section is that pre-registration. It was written and committed as the first act of
the milestone, before a line of `src/` changed, before any corpus was scanned, and
before any label was read for this milestone's purposes.

The commit that carries this section is the timestamped evidence. Everything
below is a *definition*, not a measurement: no number in this section was chosen
by looking at an outcome, and where a definition later meets a fact it did not
anticipate, the mismatch is recorded in §9 rather than absorbed.

### 0.1 What is being pre-registered, and what is not

Pre-registered here, before measurement:

1. the three demote strata, each as a mechanical membership test;
2. the composition rule that turns per-site membership into a per-finding verdict;
3. the paired negatives each bounded mechanism ships with;
4. the confidence-ranking model's **feature set and bucket edges** — the
   parameters are counts and are therefore measured, but *which questions are
   asked of a finding* is fixed here so the feature set cannot be selected against
   the outcome;
5. the flip criterion, quoted from the charter unchanged.

Not pre-registered here, because the charter already fixes them elsewhere: the
rating rubric (unchanged from M3/M4), the corpus definition (ruling K, frozen),
the snapshot discipline (ruling P), and the standing gates.

### 0.2 The flip criterion, quoted rather than restated

From the charter, verbatim, so that no later paraphrase can loosen it:

> **Pre-registered flip criterion, exactly:** both raters' asserted-stratum point
> estimates ≥ 0.80 (Wilson intervals published; the demoted stratum's numbers
> published beside them). Meets it → the default flips in the next release with
> the owner's recorded acceptance of the published demoted-stratum number. Misses
> it → the flip stays held, the residual is characterized, and the bar does not
> move again.

The executor applies this criterion and does not reinterpret it. Both strata are
rated in the same pass; nothing is suppressed; the demoted stratum's precision is
published beside the asserted stratum's, always.

---

## 1. The strata, pre-registered

A finding is in exactly one of two strata: **ASSERTED** or **DEMOTED**. Demotion
is a claim about the *report*, never about the detector: a demoted finding is
still found, still counted, still printed, and still rated. Nothing in this
milestone removes a finding from any output.

### 1.1 The composition rule, fixed before the definitions

Each stratum below is a test over a **site** — one occurrence of a clone class,
as a (file, token range) pair. A finding is in a demote stratum **iff every one
of its sites satisfies that stratum's test**. A finding is DEMOTED iff it is in
at least one demote stratum, and ASSERTED otherwise.

Three properties of this rule, each chosen for a recorded reason:

- **All sites, not any.** A finding with one site in a route file and one in a
  controller is a copy of registration logic *into* program text, and the
  controller's copy is exactly what a reader wants asserted. The all-sites reading
  is the conservative direction, and it is the direction interpretation rule 5's
  cost asymmetry names: a wrongly demoted finding loses a real clone's prominence,
  a wrongly asserted one costs one noisy line.
- **Reused whole, not re-derived.** The dead rule 9's scope carried exactly this
  composition — *"rule 9 silences a finding only when every pair of its sites is
  in scope"* (M5 experiment §4) — and the charter says the scope is reused
  **whole**. For a per-pair test, "every pair in scope" and "every site in scope"
  are the same statement, so the rule transfers without restatement.
- **No mixing across strata.** A finding whose first site is a table and whose
  second is a registration file is **asserted**. Demotion requires one stratum to
  hold over the whole finding. Allowing strata to compose would make the demoted
  class a union of partial evidence, which is precisely the hiding place the
  auditor's condition forbids.

### 1.2 Stratum D1 — statement-free table pairs

**Definition.** A site satisfies D1 when its token range sits **wholly inside a
statement-free array-literal frame**, as decided by
`Facts\RegionStructure::literalTable()`.

This is the dead rule 9's scope, reused whole and with **no floor**: the literal
overlap statistic, the Jaccard measure, the derivation that failed and the
counterfactual 0.77 are all discarded. What is kept is only the *scope test* —
membership, no constant.

**Why it is free.** The experiment measured, over 56 consensus-Y findings across
both worksheets, that **zero** have a single site pair in this scope (M5
experiment §5.1). Demoting the class therefore costs nothing the raters valued.
The same measurement is why the class may not be **silenced**: the constructed
seeder negative (a byte-identical copied table, literal overlap 1.0000) shows that
silencing would be wrong, and `Php7.php ↔ Php8.php` shows it at corpus scale.

**Paired negative** (pre-registered, built before measurement): a genuinely copied
table — one statement-free table against a byte-identical copy of itself in a
second file — is D1-demoted **and still reported, still counted, still rated**.
The negative here is not "must not be demoted"; it is "must not be silenced", and
the test asserts the finding survives every stage of the report.

**Second paired negative:** a table frame holding a closure is *not* statement-free
and its sites do **not** satisfy D1 — the ruling-5 boundary, restated as a test.

### 1.3 Stratum D2 — registration-role files

**Definition.** A site satisfies D2 when the file it sits in is a
**registration-role file**. A file is registration-role when, over the statements
at its **top level** (outside every function, class, interface, trait and enum
body):

1. there is at least one top-level statement; and
2. a **strict majority** of them are *registration expressions*; and
3. those registration expressions are **pairwise dataflow-independent** — no two
   of them name the same variable.

A **registration expression** is a top-level statement whose tokens read as
`callee ( args ) [ (-> | ?-> | ::) name ( args ) ]*` and nothing else: a single
call expression whose value is discarded. Any statement keyword, any assignment or
in-place mutation, any declaration and any closure disqualifies a statement from
being one. `Foo::class` is the one carve-out, because the tokenizer spells its
second half `T_CLASS` and it is a name, not a declaration.

Conditions 2 and 3 are **rule 8's own membership test, reused** — the same test
the M5 experiment built, self-tested with its paired negative, and measured as
*safe* (zero consensus-Y silenced on either sheet) though far too small to be a
filter. It is not a filter here. It is a role.

**Never a path pattern.** Every input above is the file's own token stream.
Ruling K's ban is honoured by construction: no directory name, no filename, no
suffix, and no vendor list takes part.

**Amendment, recorded 2026-09-02, before any file was measured.** The
definition above says *"the statements at its top level"*. Applied to real PHP
that reads one way too many: a `declare`, a `namespace` and a run of `use`
imports are top-level statements by the words, and counting them makes the
membership depend on how many symbols a file imports — noise, and noise that
moves the majority. The preamble is therefore **excluded from the count**:
`declare`, `namespace` and `use` are declarations a file needs in order to
compile, not things the file does. The in-tree precedent is `FileFeatures`'
own `$skipping` preamble step, which reads a file's first *statement* keyword
*"past the `declare`/`namespace` preamble"* for the same reason. This
amendment is recorded here rather than absorbed, it was made before any
measurement was taken, and it is the only change to §1.3.

**On the word "predominantly", and the word "framework".** Both are recorded as
interpretations in §9 rather than absorbed silently — the first because a strict
majority is what the word means and carries no derivation, the second because
"framework" names a property no content-derived test can witness and the charter
in the same breath forbids the only test that could (a path pattern).

**Paired negative** (pre-registered, and built before D2 is measured, per the
charter's *"with its paired negative built before it is measured"*): a
**dataflow-coupled** top-level file — a run of `$builder->add(...)` calls over one
shared receiver — is **not** registration-role and stays **asserted**. It is the
same minimal-pair shape the experiment fixed: equal token counts on both halves,
so the pair differs only in the thing the definition names.

**Second paired negative:** a file whose top level is a single `return [ ... ];`
has one top-level statement, and that statement is not a call expression, so it is
**not** registration-role. A returned config table is D1's business, not D2's, and
the two strata are kept from silently covering for each other.

### 1.4 Stratum D3 — classifier fishiness tags

**Definition.** A site satisfies D3 when the file it sits in was tagged **fishy**
by `Triage\FishinessClassifier` in this run.

Already shipping, and unchanged by this milestone: the model, its margin, its
counts and its log-odds explanation are M4's and are not retrained here. What is
new is only that a tag now reaches a *finding* rather than stopping at the file
list — which is the gap M4 §4.12 recorded as *"`demote`'s tag is at file-list
granularity … individual findings are not yet marked inline in each output
format"*.

**Availability, stated rather than discovered later.** The classifier is part of
Stage 0, so D3 can only hold in a run where triage ran. In a run without
`--triage` no site satisfies D3 and no finding is demoted by it. This is a
property of the stratum, pre-registered here, and the rating round runs with
triage so that all three strata are live in the pool.

### 1.5 Everything else is asserted

There is no fourth stratum and no discretionary demotion. In particular:

- The three refuted discriminators — anchor multiplicity, tokens-per-line
  sparseness, logic share — stay dead and appear in no stratum.
- Rules 8 and 9 as **filters** stay dead. Rule 8's membership test is reused as
  half of a *role definition*; rule 9's *scope* is reused as a stratum. Neither
  silences anything, and rule 9's floor is not resurrected in any form.
- No stratum reads a path, a filename, a suffix or a directory.

---

## 2. The confidence ranking, pre-registered before its counts

The charter: *"**confidence ranking** — rank, never filter — derived as counts
over the recorded 120-label corpus with log-odds printed per finding (ruling T's
derivation standard)"*.

The parameters are counts and are therefore measured. The **questions** are fixed
here, before any count was taken, so that no feature can have been chosen because
it helped.

### 2.1 The pre-registered feature set — five features and a prior

| feature | buckets | what it asks |
|---|---|---|
| `kind` | `exact` · `gapped` · `reordered` | what the engine classified the finding as |
| `sites` | `two` · `three` · `many` | how many occurrences the class holds |
| `scope` | `same-file` · `cross-file` | do all occurrences sit in one file |
| `lines` | `small` · `medium` · `large` · `huge` | how big the finding is, in source lines |
| `literal` | `none` · `trace` · `low` · `medium` · `high` · `dominant` | the literal share of the lead span's tokens |

**Bucket edges, fixed here.** `sites` splits at 2 and 3 (counts, not thresholds).
`lines` splits at 10, 50 and 100 — two decade landmarks and the tool's own
existing "large block" landmark, which `Log\Text::suggestion()` has used at 50
lines since before this milestone. `literal` reuses `Triage\FileFeatures`'
published share scale (0, 10, 25, 50, 75) unchanged, so one log-odds table stays
comparable with the other.

**What is deliberately absent.** The demote strata are **not** features. The
ranking is an independent lens on the same finding, so that a reader who
distrusts one mechanism still has the other; making the strata features would
make the two instruments one instrument reported twice. And no feature is a
rewording of a refuted discriminator: there is no anchor-multiplicity feature and
no tokens-per-line feature, and `literal` is `FileFeatures`' own authorized
literal-mass question asked of a span rather than a file — not the refuted "logic
share".

### 2.2 The standard the counts are held to

Ruling T's derivation standard, applied unchanged: counts over a **pinned,
recorded** label set; Laplace smoothing with the vocabulary stated; the training
set and the per-feature log-odds table recorded in this packet; **never trained on
a gate being passed**. The label set is the two preserved worksheets — the same
120 rated findings the M4 close and the M5 experiment both scored — and no
finding rated in *this* milestone's round enters the model.

**Rank, never filter.** The ranking reorders a report and prints a per-finding
log-odds line. It removes nothing, and a test asserts that the set of findings is
identical with the ranking on and off.

---

## 3. The acknowledgment ledger, pre-registered

The charter: *"the **acknowledgment ledger** — committed baseline file keyed to
content hashes of both sides, demote-never-suppress, self-expiring on drift, stale
entries themselves reported, counted always, no in-code suppression annotation
ever."*

Pre-registered properties, each of which ships with a test:

1. **Committed baseline file**, named by the user, in a diffable text format.
2. **Keyed to content hashes of both sides** — of *every* side, since a clone
   class may hold more than two.
3. **Demote, never suppress.** An acknowledged finding is demoted and remains in
   every output, in the count, and in the exit-code decision.
4. **Self-expiring on drift.** An entry whose recorded hashes no longer match the
   files is **stale**: it expires, and the finding it named is *not* demoted.
5. **Stale entries are themselves reported**, by name, rather than dropped
   silently.
6. **Counted always**, whether or not anything was acknowledged.
7. **No in-code suppression annotation, ever.** The ledger is a file; it never
   asks a reader to write a marker into their source. (The pre-existing
   `phpcpd-ignore` markers in `Detector\CloneSuppressions` are a separate,
   shipped product feature and are untouched by this milestone; the charter's
   clause constrains the ledger's mechanism, and is not a direction to remove
   something the tool already ships.)

The ledger has **no constants**.

---

## 4. The presentation tier, as built

Four commits, each with its own CHANGELOG entry. Nothing in this section changes
what the detector finds; every part of it changes only what the report says about
what was found.

### 4.1 The facts layer's second half — `FileStatements`, `Statement`, `FileRole`

`RegionStructure` answers *what a region of a file is*. Stratum D2 needs *what a
file is made of*, so the facts layer gains its second half: a statement
segmentation with a top-level marker, and one role read off it.

**Provenance.** The segmentation and the single-call shape test are this
project's own work, written for `bench/precommit-rules.php` during the M5
experiment and moved into `src/` when the charter reused the shape test as half
of a role definition. Nothing was consulted to write either; no inherited file
was opened.

**Ruling K by construction, not by discipline.** Both classes are handed a token
stream and nothing else. There is no filename in scope to read, so the
path-pattern ban cannot be broken here even by accident — which is a stronger
guarantee than the one `FileFeatures` has, where the ban is honoured by choosing
not to accept a path.

**Alignment, pinned rather than assumed.** Significant-token numbering agrees
with `DefaultStrategy::tokenize()` **and** with `RegionStructure` over the whole
of `src/` — 106 files, asserted file by file — and the segmentation is asserted
**total**: every significant token belongs to exactly one statement. The second
assertion is not decoration. "A majority of the top-level statements" is
meaningless over a partial denominator, and an off-by-one in the numbering would
not fail loudly, it would move every span by one token and answer confidently.

```
$ vendor/bin/phpunit --filter FileStatementsTest
OK (166 tests, 21436 assertions)
```

### 4.2 The strata, as membership tests

`Presentation\Strata` implements §1's three tests and §1.1's composition rule.
Three properties of the implementation are worth recording because each is a
place it could have gone wrong quietly:

- **No facts is no evidence.** A site whose file cannot be read fails every
  stratum, so the finding is asserted. The failure mode this avoids is a
  permission error silently demoting a report.
- **The classifier tag reaches a finding only when every site carries it**, and
  only when triage ran at all. In a run without `--triage` the `fishy` stratum is
  empty by construction, which is stated in the pre-registration rather than
  discovered later.
- **Only the classifier's tags become the `fishy` stratum.** Under
  `--triage-posture=demote` the four *proof* rungs also produce tagged files, and
  those tags are not this stratum: the charter pre-registered three strata and a
  fourth would be the executor adding one. The CLI filters on
  `TriageDecision::FISHY` explicitly, with the reason recorded in the code.

### 4.3 Inline per-format demote tags — the §7.3 gap, closed

The M4 packet recorded the gap in one paragraph: *"`demote`'s tag is at file-list
granularity. The demoted files are named with their evidence under `--explain`,
but individual findings are not yet marked inline in each output format."* Every
format now carries it:

```
  console      [demoted: table] inline on the finding's first line, and a
               counted `N asserted, M demoted (table x · registration y · fishy z)`
               line that prints every stratum's zero
  PMD XML      stratum / demotedBy attributes on <duplication>
  JSON         stratum / demotedBy per clone; asserted / demoted / demotedBy in summary
  SARIF        a demoted result at level: note, with properties.stratum
```

`Log\Logger::process()` now takes `Presentation\Findings` rather than a bare
`CodeCloneMap` (the map is carried on it, so every format keeps the corpus-level
summary it has always reported). The console, the log files and the exit code are
computed from **one** presentation, so they cannot disagree about what the tool
asserts.

**The tier never filters, and the test asserts it rather than describing it:**
the set of clone ids out of the presenter is compared against the set of clone
ids in the map. A tier that could drop a finding would be a suppression mechanism
wearing a report's clothes.

### 4.4 Confidence ranking

Findings are ordered highest-log-odds first, each carrying its score, with
`--verbose` naming the three feature buckets that carried it.

**Rank, never filter — pinned twice.** The set is asserted equal with ranking on
and off; and an *untrained* model (`new ConfidenceModel([], 0, 0)`, which has no
opinion) is asserted to reorder nothing. The second assertion is the null
hypothesis of the whole mechanism: if the ranking could change the report with no
evidence in it, the evidence is not what is doing the work.

Ties break on the clone's own content hash, so the order is total and two runs
agree — a ranking whose ties fell out of iteration order would quietly break the
determinism gate.

#### 4.4.1 The derivation — counts, and the instrument that counted them

`bench/train-confidence.php`, new, listed in `phpstan-bench.neon` and clean at
level max. It computes features through the **shipped** `ConfidenceFeatures`
class rather than a copy, so the vector counted at training and the vector scored
at run time come from one code path and cannot drift.

The label corpus is the two preserved worksheets, relocated against ruling P's
manifest (`M4-stage0-final.tsv`, 4,847 files; 4,792 usable — 23 missing, 32
drifted and excluded). Regenerating the site tables reproduces the M5
experiment's own coverage exactly: M3 60 of 60 findings, M4 58 of 60.

```
label corpus
  findings rated Y/N by both raters      120
  findings usable (lead site relocated)  118
  rater-label observations               236
  consensus findings (cross-check set)   107
  Y / N observations                     125 / 111

excluded, and why — never silent:
  M4 019 — its lead site did not relocate
  M4 056 — its lead site did not relocate
```

Both exclusions are consensus **N**, so neither removes a positive from the model.

```
per-feature counts [Y, N] and log-odds (positive leans duplicated logic)
  feature    bucket              Y        N   log-odds  consensus
  prior      —                               +0.1188    +0.1310
  sites      many                6       16    -1.0022    -0.9328
  sites      three               6       10    -0.5669    -0.5274
  sites      two               113       85    +0.1670    +0.1918
  scope      cross-file         29       27    -0.0468    -0.0499
  scope      same-file          96       84    +0.0162    +0.0190
  lines      huge                5       25    -1.5803    -1.5861
  lines      large              23       29    -0.3371    -0.3429
  lines      medium             97       49    +0.5590    +0.6423
  lines      small               0        8    -2.3112    -1.7292
  literal    dominant            0        2    -1.2116    -0.8109
  literal    high                2       68    -3.2485    -3.6441
  literal    low                62       10    +1.6322    +1.8971
  literal    medium             36       28    +0.1306    +0.1335
  literal    trace              25        3    +1.7588    +2.3671

the two readings order 6868 of 6903 finding pairs identically (0.9949)
```

**What the table says, read honestly.**

- `literal` carries almost all of the signal, and in the direction anyone would
  predict: a span that is mostly literal values is very unlikely to be duplicated
  logic (−3.25 nats at `high`), a span with barely any is very likely to be
  (+1.76 at `trace`). This is the same question `FileFeatures` asks of a *file*,
  asked of a span, and the two tables agree in sign.
- `lines` says short and very long findings are worse bets than middling ones.
  The `small` bucket is 0 Y against 8 N, which is a real observation on a small
  denominator and is smoothed accordingly rather than read as certainty.
- `scope` is **nearly inert** — ±0.05 nats. It was pre-registered, so it stays,
  and it is reported as what it is: a question that turned out not to
  discriminate on this corpus. Dropping it after seeing that would be exactly the
  feature selection pre-registration exists to prevent.

**The alternative reading, measured rather than argued.** The charter names "the
recorded 120-label corpus". Counting each finding once per rater treats a
contested finding as one observation in each class; counting consensus labels
only drops the 11 contested findings entirely. Both were computed. The two models
order **6,868 of 6,903 finding pairs identically (99.49 %)**, and a ranking is
only ever read as an order, so the choice is recorded as an interpretation (§9)
and is measurably not load-bearing.

**Not trained on a gate.** No finding rated in this milestone's round enters the
model. The labels predate the pool by two milestones.

### 4.5 The acknowledgment ledger

All seven pre-registered properties ship, each with a test:

```
  committed, diffable file                 --write-acknowledged / --acknowledged
  keyed to every side's content            hashed, sorted, then hashed again
  demote, never suppress                   still reported, counted, exit-code gating
  self-expiring on drift                   an edit to either copy expires the entry
  stale entries reported by name           by the note they were written with
  counted always                           printed whenever a ledger was consulted
  no in-code annotation ever               the ledger is a file; nothing is written to source
  constants introduced                     none
```

**The instrument is made to fail on demand**, which this project asks of a gate
(interpretation half 2, rule 6): the same fixture is scanned before and after one
line is edited *inside* the clone, in the **second** copy — the side a
first-file-only key would never look at — and the verdict flips from acknowledged
to asserted with the stale entry named. A ledger that could not expire would pass
every other assertion in that test.

**Recorded, so it is not mistaken for a violation.** The charter's *"no in-code
suppression annotation ever"* constrains the ledger's mechanism. The pre-existing
`phpcpd-ignore` markers in `Detector\CloneSuppressions` are a separate, shipped
product feature and are untouched by this milestone; removing them was not
directed and would be a breaking change the charter does not ask for. The two
coexist and the README says which is for which.

**And it is not a stratum.** An acknowledgment demotes, but it is a decision this
project made rather than a class the finding belongs to, so it is kept off
`perStratum()` and out of the pre-registered three. The rating pool is drawn with
**no ledger**, so nothing in §1's arithmetic can be moved by it.

## 5. Ruling 7(b), landed

Granted at the M4 close as *"the designated fix, sequenced after the release"*,
with five conditions. Each is discharged below, and the two that took
measurement rather than implementation are recorded with their numbers.

### 5.1 The defect, restated from the trace

`ChainBuilder`'s recorded contract is that its score *"only ranks candidates in
any case: whether a chain is reported is decided later"*. In one band it does not
rank, it decides: a divergence `d` followed by a trailing run `r ≤ d` loses its
junction by arithmetic — `r − gapA − gapB < 0` — so the chain truncates, and the
`minTokens` floor then refuses a pair the aligner would have accepted. M4 §13.2
traced it to one subtraction on one pair: `5 + 40 − 6 − 0 = 39` against `40`, on a
pair whose similarity is 0.882 and whose surviving run is inside the winnowing
guarantee.

### 5.2 The mechanism

Outer flanks are **proposed** to the verifier rather than dropped by the scorer:
attach → verify → back out. `CloneClassifier::acceptance()` — extracted so that
the proposal and the shipped verdict are literally the same test, the
chain-witness bound and then the banded aligner — decides each proposal, and the
chain's own reading stands when the aligner refuses.

**The 0.85 invariant cannot bend**, and this is structural rather than
disciplinary: no geometry reaches the report without passing the test that
enforces it, and `verify()` applies that test a second time downstream. §13.4's
refutation of the naive version — a flank *can* pull a candidate past the
acceptance ratio, and the suite caught it doing so at 0.838 — is the reason this
is a proposal and not a rule.

**`ChainBuilder` and its brute-force oracle are untouched.** `git diff` over both
is empty; the change lives entirely in the consumer, which is what the ruling
requires. The scorer keeps its theorem and stops being the last word about it.

### 5.3 The two bounds that were derived by measurement, and the alternatives they beat

The ruling names the mechanism and not the trigger. Two bounds were derived
during the work, both against measurement, and **both alternatives were
implemented and measured before being rejected** — the numbers, not the
reasoning, are the argument. All corpus numbers in this section are on the
**bench walker, no preset applied, `--min-tokens=70 --min-lines=5`**, the
recorded bench definition; they are gate and corpus-effect deltas, not quality
claims.

**(a) Propose only where the scorer *decides*.** A proposal is made only when the
chain's own span falls under `minTokens`. Where both readings are acceptable, the
scorer is choosing between two valid descriptions of one pair, which is exactly
its contract; the ruling's own framing is that *"in this band it does not rank, it
decides"*.

```
  proposing unconditionally, measured:
    php-parser        82 →  90 clones      symfony/string   22 → 23
    phpunit          581 → 629 clones      firefly-iii     617 → 714
    phpunit wall-clock                     4.2 s → 159.0 s   (38×)
```

Spans grew by swallowing *shared preambles* the scorer had deliberately not
taken. A 38× regression would not survive ruling U's speed bar, and interpretation
rule 2c decides it: a reading that breaks a seeded invariant loses to the
invariant, which is older evidence than the instruction.

**(b) Propose only within extension's own reach** — one span back from the
chain's start, one span forward from its end, which is `extend()`'s own
definition of a flank region and therefore not a new bound.

```
  without the reach bound, measured on php-parser:
    591 alignments at a mean span of 790 tokens, 14.5 s of dynamic programming
    on a corpus that scans in 0.8 s — and 103 clones rather than 96
```

The cause is worth recording because it is a *correctness* argument, not a cost
one: without the reach bound, any colinear anchor **the penalized chain
deliberately dropped** qualifies as a "flank", however far away it sits. A
distant dropped anchor is not a flank extension recovered — it is a competing
chain element, and the gap penalty exists precisely to arbitrate those. Ruling M's
argument (*"a flank run has nothing beyond it to bridge to, so it cannot be a
competitor"*) does not reach it.

A third condition costs nothing and is the other half of the ruling's own band
statement (`span − r < minTokens ≤ span`): an attachment that lands still under
the floor is skipped, since `verify()` would refuse it a moment later.

### 5.4 `probe1`, re-derived — and the derivation lands where it started

The ruling requires probe1's expected span to be *"re-derived under the new
pipeline with the derivation recorded, not assumed"*. It was, and the answer is
**unchanged at 60 tokens**. The reason is the part that matters:

- The two fixtures share three exact runs: a 10-token preamble, the 60-token
  core, and a 2-token tail. The scorer drops both outer runs (the preamble repays
  10 across a 6/5-token divergence, the tail repays 2 across another).
- The core is 60 tokens against probe1's `minTokens` of 50, so **both readings
  are acceptable** and the scorer is ranking, not deciding. No proposal is made.

The alternative was measured rather than guessed: with the proposal made
unconditionally, probe1 reports **84 tokens** (10 + 6 + 60 + 6 + 2), gapped, four
divergence entries, and — still — no bounded edge divergence. That reading is
defensible; it is not the one taken, for §5.3(a)'s reasons.

**The band is now pinned by its own test.** `probe1_the_same_pair_under_a_higher_floor_is_rescued_by_flank_attachment`
runs the same fixture at `--min-tokens=70`, where the 60-token core *is* under the
floor: the attachment is proposed, the aligner accepts (two divergences of 6 and
5 tokens over a span of 84 witness a distance of at most 12 — similarity 0.857),
and a pair that would otherwise be refused outright is reported. Without ruling
7(b) that configuration reports nothing at all, so the mechanism is exercised by
the suite and not only by the recall gate.

### 5.5 Recall — the condition the ruling was granted for

```
$ php bench/run-recall.php --sample=40
  PASS  the unified engine recalls the whole guaranteed region — 295 of 295
$ php bench/run-recall.php --sample=60
  PASS  the unified engine recalls the whole guaranteed region — 441 of 441
```

**441 of 441 at both sample sizes.** The M4 residual (439/441, §13, open since the
close) is closed, and it is closed by the mechanism the ruling designated rather
than by a threshold.

### 5.6 The corpus effect, measured and explained

Bench walker, no preset applied, `--min-tokens=70 --min-lines=5`.

```
                     clones        duplicated lines     files      wall-clock
  php-parser        82 →  96      11,884 → 12,430      31 → 43     0.8 → 0.8 s
  symfony/string    22 →  22       1,430 →  1,430      13 → 13     0.1 → 0.1 s
  phpunit          581 → 610     111,778 → 118,178    478 → 510    4.2 → 4.9 s
  firefly-iii      617 → 725      59,536 → 71,735     481 → 560    2.4 → 3.1 s
```

**Every movement is in one direction and has one cause:** a pair whose chain the
scorer truncated below the floor is now reported at its full extent. Nothing is
lost — the counts rise on three corpora and are unchanged on the fourth — and the
before-numbers reproduce the M5 experiment's own (82 / 22 / 581 / 617) to the
digit on the same walker, which is the check that this is the same instrument.

Sampled from the php-parser diff, the shape of what appears:

```
  lib/PhpParser/Builder/Class_.php:1     ↔ lib/PhpParser/Builder/Enum_.php:1        88t
  lib/PhpParser/Node/Param.php:79        ↔ Node/Stmt/ClassMethod.php:104 ↔ …:50     78t
  lib/PhpParser/Builder/Param.php:98     ↔ lib/PhpParser/Builder/Property.php:88    89t
  test/PhpParser/NodeTraverserTest.php:70 ↔ …:118                                   89t
  lib/PhpParser/Parser/Php7.php:2316     ↔ lib/PhpParser/Parser/Php7.php:2328       75t
```

Near-miss duplication between sibling builders, sibling node types, and blocks of
the generated parser tables — the class the floor was refusing.

**Cost.** php-parser and symfony/string are unchanged to the tenth of a second;
phpunit is 1.17× and firefly-iii 1.29×, both well inside ruling U's 1.5× band, and
both on corpora that grew their reported clone count by 5 % and 18 %.

### 5.7 The other standing gates, after the change

```
  php bench/check-chaining.php                              2/2   (oracle untouched)
  php bench/check-determinism.php firefly-iii unified       4/4   — 725 clones compared
  php bench/check-incremental.php php-parser unified        8/8   — 96 vs 96 clones
  php bench/check-superset.php php-parser                   3/3
  php bench/check-superset.php symfony-string               3/3
  php bench/check-superset.php phpunit          locations pass; 1 unexplained pair
  php bench/check-superset.php firefly-iii      locations pass; 6 unexplained pairs
  vendor/bin/phpunit                                        OK (467 tests, 24,896 assertions)
  vendor/bin/phpstan analyse                                no errors
```

The subsumption residual **improves**: M4 §16.1 recorded `0 / 0 / 1 / 7`
unexplained pairs across the four corpora; it is now `0 / 0 / 1 / 6`. The phpunit
pair is the single known one the M4 close verified by re-running.

### 5.8 One instrument defect found while measuring, fixed in its own commit

The unrestricted variant of §5.3(a) reported a symfony/string clone the probe
suite's independent similarity oracle scored at 0.838 — apparently a broken 0.85
invariant. It was not. The engine's own banded alignment scored the pair at
**0.8640**, and the oracle was wrong: it reconstructs each side's true span length
from public data by adding and subtracting divergence token counts, and it was
counting **bounded edge divergences** — material *past* the shared span, part of
neither side's length — into that reconstruction. On that pair it read a
218-token side as 197.

`CloneDivergence` now carries `edge`, so the two facts are distinguishable in the
report rather than inferrable from line numbers they cannot be inferred from, and
the oracle skips them. The key appears only when true, so every report ever
written for an internal divergence is unchanged byte for byte. Landed in its own
commit with its own CHANGELOG entry, because it is a report change and not part
of ruling 7(b).

The finding is recorded rather than glossed: **the invariant was never broken, and
for one shape the instrument could not have detected it if it had been.**

## 6. Every standing gate, and the posture-relative wall-clock sweep with the tier counted

### 6.1 Green

```
  vendor/bin/phpunit                                 OK (467 tests, 24,896 assertions)
  vendor/bin/phpstan analyse                         no errors (106 files)
  vendor/bin/phpstan analyse -c phpstan-bench.neon   no errors (20 files)
  php bench/self-test.php                            24/24
  php bench/check-chaining.php                       2/2   — ChainBuilder and its oracle untouched
  php bench/check-determinism.php firefly-iii unified 4/4  — 725 clones compared
  php bench/check-incremental.php php-parser unified  8/8  — 96 vs 96 clones
  php bench/run-recall.php --sample=40               2/2   — 295 of 295
  php bench/run-recall.php --sample=60               2/2   — 441 of 441
  php bench/precommit-rules.php self-test            15/15
  php bench/triage.php check --dogfood=<frozen>      1/1  — two runs over one tree agree
                                                     (discard 2,405 kept / 90.7 %; demote 2,552 / 97.8 %)
  IndexCodec::VERSION                                5, unchanged
  constants introduced                               none
  hygiene grep (pattern file outside the repo)       clean before every commit batch
```

`run-recall --sample=60` is the M4 residual, and it is **closed**.

### 6.2 Subsumption, and the one residual that improves

```
  php bench/check-superset.php php-parser       3/3
  php bench/check-superset.php symfony-string   3/3
  php bench/check-superset.php phpunit          locations pass · 1 unexplained pair
  php bench/check-superset.php firefly-iii      locations pass · 6 unexplained pairs
```

M4 §16.1 recorded `0 / 0 / 1 / 7`. It is now `0 / 0 / 1 / 6`: ruling 7(b) reports
one previously-truncated firefly-iii pair at the length the source agrees with.

### 6.3 The wall-clock sweep, with the presentation tier counted as its own line

**Posture and walker, stated because the rule requires it:** bench walker
(`bcb_gate_files`), **bare run — no triage, no preset**, which is the shipped
default; medians of 5 runs (3 on the private corpus), `--min-tokens=70`. These are
gate numbers on the recorded bench definition, not quality claims.

`unified` is the detection cost the M3 and M4 sweeps recorded, kept comparable
with them. `unified + presentation` adds the strata, the ranking features and the
total order — what a user's run actually pays now. Two lines, one corpus.

```
  corpus              files   default   unified   +tier    u÷d    (u+t)÷d   M4's u÷d
  the private corpus    200   0.401s    2.426s   2.553s   6.05x    6.37x      5.4x
  the private corpus    600   0.699s    2.723s   3.149s   3.90x    4.51x      3.8x
  the private corpus   1200   1.523s    3.703s   4.189s   2.43x    2.75x      2.3x
  the private corpus   2400   3.213s    5.038s   5.526s   1.57x    1.72x      1.3x
  the private corpus   2735   3.351s    5.535s   6.228s   1.65x    1.86x      1.4x
  firefly-iii           200   0.118s    0.723s   0.946s   6.13x    8.02x      3.4x
  firefly-iii           600   0.745s    1.701s   2.028s   2.28x    2.72x      1.7x
  firefly-iii          1200   2.248s    2.578s   2.994s   1.15x    1.33x      1.8x
  firefly-iii          1445   2.468s    3.390s   3.661s   1.37x    1.48x      1.1x
  phpunit                60   0.012s    0.037s   0.038s   3.08x    3.17x      2.3x
  phpunit               200   0.028s    0.100s   0.106s   3.57x    3.79x      2.7x
  phpunit               600   0.117s    0.309s   0.344s   2.64x    2.94x      2.4x
  phpunit              2500   0.499s    2.052s   2.236s   4.11x    4.48x      3.1x
  phpunit              2694   0.916s    5.069s   5.487s   5.53x    5.99x      5.1x
```

**Three readings, and the second is a release-decision fact rather than a gate
result.**

**(i) The shape holds.** Ruling U's own sentence is that *"the degradation with
size, not the multiple, was always the red flag"*. On both large application
corpora the ratio still improves monotonically in size — the private corpus
6.05 → 3.90 → 2.43 → 1.57 → 1.65, firefly-iii 6.13 → 2.28 → 1.15 → 1.37, each
with the same small end-wobble M4's own numbers had. phpunit remains the
exception it was, for the reason M4 §7.1 measured and attributed: one 327 KB test
file is 40 % of the engine's runtime on that corpus. Nothing here is a new red
flag.

**(ii) The ≤ 1.5× *level* is now missed at every size, where at M4 it held at the
three largest.** M4 recorded 1.1× on firefly-iii at 1,445 files and 1.3× / 1.4× on
the private corpus at 2,400 and 2,735. Those same three points now read 1.37× /
1.57× / 1.65× before the tier and 1.48× / 1.72× / 1.86× after it.

**The cause is ruling 7(b), and it is not a slowdown per finding — it is more
findings.** On the same walker the engine now reports 725 clones on firefly-iii
where it reported 617, and 610 on phpunit where it reported 581; each additional
accepted candidate is verification work the old engine never did because the chain
scorer had already truncated it below the floor. The trade is stated plainly:
**441/441 recall at the tool's own default was bought with the ≤ 1.5× level at
scale.** Both halves are measured, neither is hidden, and which one the project
wants is a release decision and not the executor's — it is carried to the owner in
§8 rather than resolved here.

**(iii) The presentation tier costs 5–31 % of the engine's own time**, and its
share is largest exactly where the engine's work is smallest: 31 % on firefly-iii
at 200 files, 8 % on phpunit at 2,694, 13 % on the private corpus at 2,735. That
is the expected shape — the tier's work is proportional to the *files carrying
findings*, not to the corpus — and it is reported rather than amortised into the
engine's line.

### 6.4 The M3 parity gate, reported as the instrument it is

`bench/check-walltime.php` fails on every corpus and size, at 0.16×–0.87×. That is
the M3 parity gate (*"unified ≥ default-pipeline speed at every size"*), which the
M4 close already recorded as failing and superseded: ruling U replaced parity with
the 1.5× band, and the M4 close audit verified the parity instrument's failure
*"reconciles with §15.1 rather than contradicting it"*. It is re-run and reported
here because a gate that is not run is not an instrument, and its numbers are the
`u÷d` column above read the other way up.

## 7. The rating round — pool drawn, rated as rater A, and stopped

### 7.1 Ruling P, and why a hash list was not enough this time

The charter asks for a **fresh pinned snapshot**. One was taken, and it did not
hold:

```
  pin   4,964 files, digest 65799015…                    (first attempt)
  verify, minutes later                    1 changed     — one test file
  re-pin, verify immediately               3/3 PASS
  verify again, after the pool run         1 added, 1 changed
```

The consequence was not cosmetic. Two pool runs over what looked like one tree
returned **246** and **228** distinct findings, and the engine's own clone counts
moved with them (unified 209 → 194). The corpus is a live development tree; it
moves while it is being measured.

Ruling P (i) allows *"a frozen copy **or** a recorded content-hash list"*, and
this milestone needed the first: a hash list records that a tree moved, it does
not stop it moving **during** a run. So the corpus was copied — `.git`,
`node_modules` and `vendor` excluded, which are exactly what the walker skips
anyway — to a frozen location outside every repository, pinned there, and every
number below comes from that copy.

```
  frozen copy    4,963 .php files, 2.1 GB, outside every repository
  pin            4,965 files, digest d1c54173b0cc401474d5f1459614839c18cc96502ac5a389679356882f47dfa1
  verify         3/3 PASS — 0 added, 0 removed, 0 changed
```

**Recorded as a change of method, not of standard.** M3's and M4's pools were
drawn from the live tree under a hash list; this one is not, and the reason is a
measurement, not a preference.

### 7.2 Three instrument defects, found while drawing the pool and fixed before rating

Each affected *which findings a rater sees*, so each is recorded rather than
quietly repaired.

1. **The sample moved when nothing moved.** Findings were de-duplicated on the
   *salted* per-path digest, and that key also ordered the pool and therefore
   drove the even-stride sample. The salt is fresh per run: three consecutive
   runs over one unchanged tree produced worksheets with 10, 14 and 17
   table-stratum findings. Keyed on paths — which is what a finding's identity
   is — two runs now produce an **identical sample and identical strata**,
   verified by diffing two runs' keys.
2. **A site's digest label could belong to another site.** The digest list and
   the path list were sorted independently, hash order against alphabetical, and
   then read index-by-index when the worksheet was written. That label is what
   `bench/relocate-worksheet.php` propagates by, so a mislabelled site relocates
   to the wrong file. Digests are now derived from the sorted paths.
3. **An empty stratum could not be told from an empty opportunity.** The pool now
   reports every stratum over the sample *and* over the whole pool, and reports
   the registration role's own denominator over the scanned files.

### 7.3 The pool

**Corpus line:** the frozen pinned snapshot · Stage 0's shipped posture
(`--triage`) · `laravel` preset · an even stride of 1,200 files over the sorted
tree · `--min-tokens=70 --min-lines=5` · engines `unified`, `rabin-karp`,
`tokenbag`. This is the ruling-K definition, which is the posture a quality claim
has to run on.

```
  triage (Stage 0, shipped posture): 189 of 2,206 files removed, 2,017 left
  corpus: 1,200 files, sampled on an even stride over the sorted tree
    unified 194 clones · rabin-karp 19 · tokenbag 22
  pooled 228 distinct findings, wrote 60
```

### 7.4 The strata over the pool — and one of the three reaches nothing

```
  strata            sampled worksheet     whole pool
    asserted          48 of 60             191 of 228
    demoted           12 of 60              37 of 228
      table           10 of 60              33 of 228
      registration     0 of 60               0 of 228
      fishy            7 of 60              21 of 228
```

**Stratum D2 is empty, and the reason is measured rather than guessed.** The
role's own denominator, over the 1,200 scanned files:

```
  registration-role                3
  no top-level statement           1
  no registration at all       1,182
  registrations, no majority      14
  majority, but coupled            0
```

**1,182 of 1,200 files have no top-level registration expression at all**, so the
majority clause and the dataflow clause never get a chance to refuse anything —
the definition is not being *rejected* by this corpus, it is not being *reached*
by it. This application's route files put their registrations inside
`Route::group(…, function () { … })` closures, which are not top-level
statements; the file's top level is one call whose argument holds forty.

Stated plainly, because it is the sort of result that gets rounded off: **the
registration stratum was pre-registered, it is correctly implemented, its paired
negatives pass, and on this corpus it demotes nothing.** It costs nothing and it
delivers nothing here.

What is **not** done about it: the definition is not widened. Redefining a
pre-registered stratum after seeing that it reached nothing is exactly the
hindsight the charter's guard exists to prevent, and the M5 experiment is this
project's own precedent for refusing to pick a number the evidence does not
supply. The finding is carried to the owner in §8 as an open item, with the
mechanism named.

### 7.5 Rater A, and this is a hard stop

The worksheet is at mode 600 outside every repository. **κ is not computed and
must not be**, from one rater's verdicts; the two-rater precision ruling U's bar
is written in does not exist until the auditor's rater-B pass is done.

What can honestly be said from one rater's sheet, labelled as such:

```
$ php bench/audit-precision.php score <rater-a.tsv>
rater A: 60 findings, 60 rated

single rater — precision below is one rater's; no kappa is computed,
and none should be reported from one rater's worksheet.

rater A precision over the sampled pool: 39/60 = 0.650
Wilson 95% interval:                     [0.524, 0.758]
unrateable ('?'):                        0

precision per engine over the rated sample (rater A):
  rabin-karp   4/6   = 0.667   Wilson [0.300, 0.903]
  tokenbag     10/10  = 1.000   Wilson [0.722, 1.000]
  unified      27/46  = 0.587   Wilson [0.443, 0.717]

the stratified bar (M5) — engine: unified
  rater    stratum      ratio    point   Wilson 95%
  A        asserted    23/36     0.639   [0.476, 0.775]
  A        demoted      4/10     0.400   [0.168, 0.687]

  pre-registered flip criterion — both raters' asserted point estimates >= 0.80: NOT MET
  (one rater only: the criterion needs two, and no flip is decided from one)

  the demoted stratum, by tag (rater A):
    fishy          2/7
    table          2/8
```

**Three things this says, and one it does not.**

- **The stratification separates**, in the direction it was pre-registered to:
  0.639 asserted against 0.400 demoted on one rater's sheet. That is what a lens
  is supposed to do, and it is the first direct evidence that the split carries
  information rather than merely relabelling.
- **The asserted stratum is nowhere near 0.80** on this rater. 0.639 with an
  upper Wilson bound of 0.775 — the bar sits outside the interval. On one rater's
  reading the flip criterion is missed, and missed by more than sampling noise
  would explain.
- **TokenBag is 10/10 for the third consecutive pool**, on a third independently
  drawn sample.
- What it does **not** say is anything about ruling U's bar, which is a two-rater
  number. This is a stated prior for the auditor's pass, exactly as M4 §7.2 was.

### 7.6 A methodological breach, disclosed rather than discovered

While diagnosing the reproducibility defect of §7.2(1) I printed a diff of two
key files, and that diff contained **finding-id → stratum rows** — which a rater
is not supposed to see. Recorded precisely, because a disclosure that shades the
facts is worse than none:

- The rows I saw belong to the **superseded** digest-keyed sample drawn from the
  **drifted live tree** — a 246-finding pool whose sample differed from the one
  rated here (that pool put 14 findings in the table stratum, this one puts 10).
  Finding ids are positional in a sample that has since changed, so the mapping
  I saw is largely invalid against the sheet I rated.
- I did not see, and do not have, a mapping from any id to the *code* behind it.
  Every verdict above was formed by reading the excerpt.
- The deeper protection is structural and is unaffected: the strata are
  **mechanical and content-derived**, so a table pair is visibly a table pair in
  the excerpt. Blinding exists to stop a rater anchoring on *the tool's verdict*,
  not on the code's nature.
- **Rater B is uncontaminated**, and κ plus rater B's own stratum numbers are the
  check on this. If the auditor judges the contamination material, the remedy is
  a re-draw and a re-rate by an uncontaminated rater A, and that is the auditor's
  call and not mine to take.

### 7.7 The stop

Per the charter: *"executor rates as A; hard stop for the auditor's B pass"*.
This is that stop. The worksheet, its key (with the strata the rater never saw),
the frozen corpus and its manifest are all outside every repository. Nothing
below this line in the milestone has been done, and the flip criterion has not
been applied to anything but one rater's sheet, where it reads NOT MET and
where it does not count.

## 8. Carried to the owner and the auditor — none of it taken here

### 8.1 The flip — not decidable yet, and that is the whole point of the stop

The pre-registered criterion needs **both** raters' asserted-stratum point
estimates. One exists. It reads 0.639 with an upper Wilson bound of 0.775, so on
one rater's sheet the bar is missed by more than sampling noise explains — but a
one-rater number is not ruling U's number and no flip follows from it either way.
The decision waits on the auditor's pass.

### 8.2 A trade the owner has to weigh: 441/441 recall against the 1.5× level

This is the milestone's one genuinely new release fact, and it is not a gate
result:

```
  before ruling 7(b)   run-recall --sample=60   439 of 441      ≤1.5x held at
                                                                firefly-iii 1445 (1.1x)
                                                                private 2400/2735 (1.3x/1.4x)

  after ruling 7(b)    run-recall --sample=60   441 of 441      those same three points read
                                                                1.37x / 1.57x / 1.65x
                                                                (1.48x / 1.72x / 1.86x with the tier)
```

The cause is measured, not inferred: the engine now reports 725 clones on
firefly-iii where it reported 617 and 610 on phpunit where it reported 581, and
each additional accepted candidate is verification work the old engine never did
because the chain scorer had already truncated it below the floor. The *shape* of
ruling U bar 3 — the ratio improving with size, which the ruling itself calls the
red flag — still holds on both large application corpora.

**Neither half is the executor's to trade.** Ruling 7(b) was granted with
441/441 as an explicit condition, and it is met; the speed consequence was not
foreseen in the grant and is surfaced here rather than absorbed. The options, laid
out and not chosen: accept the level as measured (the shape argument is unchanged
and ruling U's own framing privileges it); or re-open the mechanism's trigger,
which would trade recall back.

### 8.3 Stratum D2 reaches nothing on this corpus

Pre-registered, correctly implemented, paired negatives passing, and it demotes
0 of 228 pooled findings because 1,182 of 1,200 files have no top-level
registration expression at all (§7.4). Three readings, none taken here:

- **Leave it.** It costs nothing, it is defined, and a corpus whose route files
  are written as top-level calls would exercise it. The charter's guard says a
  pre-registered stratum is not redefined after seeing what it reached.
- **Retire it** at the next rating round, on the same standard the classifier's
  own retirement is held to.
- **Re-open the definition under a ruling**, with the mechanism named: the
  registrations are inside `Route::group(…, function () { … })` closures, so a
  role that looked *one closure deep* would reach them. That is a widening, it
  needs a ruling, and it must not be taken by the executor after seeing the
  result — which is why it is written here as a request and not as a change.

### 8.4 For the auditor, first: is the §7.6 contamination material?

The disclosure is in §7.6 with its mitigations. If the auditor judges it
material, the remedy is a re-draw and a re-rate by an uncontaminated rater A. The
pool is now reproducible, so a re-draw costs one command.

---

## 9. Interpretations recorded

Five arose, each with a named mismatch, the reading taken, the rule that decided
it and the precedent.

**(i) "framework registration expressions" — no content-derived test can witness
"framework".** *Mismatch:* the charter defines stratum D2 as *"a file whose
top-level statements are predominantly framework registration expressions"* and
in the same breath requires the membership to be *"content-derived … never a path
pattern"*. Nothing in a token stream says which library a call belongs to; the
only test that could is exactly the one ruling K bans. *Reading taken:* the
observable is what a registration *is* — an unconditioned, effect-only,
order-independent top-level call — and that is what the definition tests.
*Rule:* interpretation 1c, read toward the purpose; the instruction's example is
evidence of its purpose, not its boundary. *Precedent:* the hidden-directory rule,
where the hand list of `.phpstan`/`.psalm`/`.rector` was evidence of the purpose
"tool state is not program text" rather than its boundary. *Cost, stated:* the
test cannot distinguish a framework registration from any other effect-only
top-level call, and does not try to.

**(ii) "predominantly" fixed as a strict majority.** *Mismatch:* the charter says
the role membership is *"a definition"* and needs no derivation, but
"predominantly" admits a range of readings and the code has to pick one.
*Reading taken:* more than half — the arithmetic of the word, not a value on a
curve. *Rule:* interpretation 0's clarity gate read the other way round: there is
no mismatch with a *fact*, only an imprecision in a word, so the plain meaning is
applied and recorded rather than derived. *Recorded because* a reader checking
"no constant without a derivation" will find a `2 >` in the source and should be
able to see immediately why it is not one.

**(iii) "counts over the recorded 120-label corpus" — 120 findings, 236
labels.** *Mismatch:* the two worksheets hold 120 rated findings and 240 rater
verdicts, of which 11 findings are contested; "the 120-label corpus" does not say
which population to count. *Reading taken:* one observation per rater per finding
(236, over the 118 findings whose lead site relocates), so a contested finding
contributes one to each class rather than being dropped — a ranking is the one
instrument that can represent ambiguity as a middling score instead of taking a
side. *Rule:* interpretation 5, tiebreak by cost asymmetry — dropping 11 findings
loses evidence, keeping them costs a slightly flatter model. *Measured rather
than argued:* the consensus-only alternative was computed and the two models order
**6,868 of 6,903 finding pairs identically (99.49 %)**, so the choice is
recorded and is not load-bearing.

**(iv) A pre-registered feature that could not be counted.** *Mismatch:* §2.1
pre-registers `kind` (exact / gapped / reordered) and neither worksheet records
it — and the pool is multi-engine, so a finding has no single engine's
classification to recover. *Reading taken:* dropped, **before any count was
taken**, and recorded here rather than silently replaced by something else.
*Rule:* interpretation 8 — every interpretation leaves a record; the danger with a
feature set is precisely that a silent substitution is indistinguishable from
selection. *Nothing was added in its place*, because adding a feature after
pre-registration is worse than dropping one that cannot be measured.

**(v) Ruling P's "pinned snapshot" met a tree that moves while it is measured.**
*Mismatch:* the ruling's words offer *"a frozen copy or a recorded content-hash
list"* as equivalents; on this corpus they are not. A hash list records that a
tree moved — it was still moving between the pin and the verify, and between two
pool runs, changing the pool from 246 findings to 228. *Reading taken:* the
frozen copy, which is the branch of the ruling's own disjunction that actually
delivers what it asks for. *Rule:* interpretation 2a — purpose beats words when
the words assumed a fact that is false; the assumption was that a hash list makes
a measurement reproducible, and here it only makes irreproducibility visible.
*Precedent:* the E3 slice — *"the same 200-file slice"* assumed mtime was
reproducible, it was not, the purpose was reproducibility, so the slice was
re-derived and the change surfaced.

**(vi) Ruling 7(b) names a mechanism, not a trigger.** *Mismatch:* the grant
fixes *"attach → verify → back out"* and says nothing about **when** an
attachment is proposed; proposing always is a defensible reading and it costs
phpunit 4.2 s → 159.0 s. *Reading taken:* propose only where the scorer's
truncation **decides** rather than ranks, and only within extension's own reach —
both derived by measurement, with the rejected alternatives implemented and their
numbers recorded (§5.3). *Rule:* interpretation 2c — system beats both readings
when either would break a seeded invariant; a 38× regression does not survive
ruling U's speed bar, which is older evidence than this grant. *Precedent:*
ruling D's method — a value chosen by measurement on the corpus that exhibits the
failure, never on a failing gate.

**One note that is deliberately not an interpretation.** The charter's *"no
in-code suppression annotation ever"* constrains the **ledger's** mechanism, and
the ledger has none. The pre-existing `phpcpd-ignore` markers are a separate
shipped feature; removing them was not directed, and reading the clause as a
retroactive removal order would be interpreting a clear instruction, which
interpretation rule 0 calls a violation rather than diligence.

---

## 10. State at the stop

### 10.1 What was done

```
  commits                          10 product/instrument, each with its own
                                        CHANGELOG entry; the rest packet-only
  IndexCodec::VERSION              5, unchanged
  hygiene grep                     clean before every commit batch
  working tree                     clean at HEAD
  src/ files added                 12 — the facts layer's second half (5)
                                        and the presentation tier (7)
  the three dead discriminators    still dead; no feature is a rewording of one
  rules 8 and 9 as filters         still dead; rule 8's membership test is reused
                                        as half of a role definition and silences
                                        nothing, rule 9's floor is not resurrected
  the corpus                       never named in code, fixtures, docs or messages
  worksheets and snapshots         outside every repository, worksheet at mode 600
```

**On "no constant without a derivation", stated precisely rather than as a
slogan.** Nothing here is a value on a curve, and nothing was fitted to make a
gate pass — but three numeric boundaries do appear in the new code and a reader
should be able to find each one's account:

```
  FileRole            registrations * 2 > statements    "predominantly" = strict majority;
                                                        the arithmetic of the word, §9(ii)
  ConfidenceFeatures  sites   splits at 2, 3            counts, not thresholds
                      lines   splits at 10, 50, 100     two decade landmarks and the tool's
                                                        own existing "large block" landmark,
                                                        which Log\Text has used at 50 since
                                                        before this tier
                      literal FileFeatures' 0/10/25/    reused unchanged, so one log-odds
                              50/75 share scale         table stays comparable with the other
```

All three were **pre-registered in §1 and §2 before any count was taken**, which
is the property that distinguishes a bucket boundary from a tuned constant. The
ranking model's *parameters* are counts (§4.4.1); the ledger has no constants at
all; ruling 7(b) introduced none.

### 10.2 The numbers, in one place

```
  recall (the M4 residual)         441/441 at sample 60 · 295/295 at sample 40   CLOSED
  subsumption residual             0 / 0 / 1 / 6 unexplained pairs (was 0/0/1/7)
  suite                            467 tests, 24,896 assertions
  phpstan                          clean, both configs
  speed, ruling U bar 3            shape met; ≤1.5x level now missed at every size
  precision, rater A only          overall 0.650 · unified 0.587
    asserted stratum (unified)     23/36 = 0.639 [0.476, 0.775]
    demoted stratum (unified)      4/10  = 0.400 [0.168, 0.687]
  the flip criterion               NOT MET on one rater — and one rater decides nothing
```

### 10.3 Known failing or open, each with its section

- **The flip criterion** needs rater B (§7.5, §8.1). Nothing is decided.
- **Ruling U bar 3's level** is missed at every size, where M4 held it at the
  three largest; the cause is ruling 7(b)'s recall gain (§6.3, §8.2). Carried to
  the owner.
- **Stratum D2 demotes nothing** on this corpus, for a measured reason (§7.4,
  §8.3). Not redefined.
- **The M3 parity gate** fails at 0.16×–0.87×, as it did at M4 close; superseded
  by ruling U and re-run as an instrument (§6.4).
- **phpunit's single unexplained subsumption pair** stands, unchanged and
  verified by the M4 close audit.
- **§7.6's contamination** is disclosed and is the auditor's to weigh first.

### 10.4 Not closed here

**This milestone is not closed by its executor.** The packet ends awaiting the
close-audit verdict, with §8.4 as the first thing it has to answer, the rater-B
pass as the thing the milestone's own criterion waits on, and §8.2's recall-versus-speed
trade prepared for the project owner rather than taken.

---

AUDIT: *awaiting the close-audit verdict.*

---

AUDIT: **pass — M5 closed. The criterion is applied: NOT MET. The flip is
held, and per the charter the bar does not move again.**
2026-09-02, Fable session.

**The breach (§7.6), weighed first as asked: not material; rater A's sheet
stands.** Three independent grounds: the leaked rows belonged to the
superseded drifted-tree sample whose ids do not map onto the rated
worksheet; no id→code mapping existed; and the strata are content-derived,
so a table pair is visibly a table pair in the excerpt — the blinding's
load-bearing secret is engine attribution, which never leaked. Condition
recorded: key-adjacent diagnostics diff counts and digests, never rows.
Disclosing before the auditor's pass, unprompted, is the conduct this
process exists to produce.

**The rater-B pass and the score, verbatim:**

```
kappa 0.811 (55/60)
asserted (unified)   A 23/36 = 0.639 [0.476, 0.775]
                     B 23/36 = 0.639 [0.476, 0.775]
demoted  (unified)   A 4/10 = 0.400 · B 5/10 = 0.500
flip criterion — both raters' asserted point estimates >= 0.80: NOT MET
```

Both raters land on the identical asserted point estimate, and 0.80 sits
above both upper Wilson bounds. The stratification separates in the
pre-registered direction under both raters, so the lens works; what it
shows is that the asserted stratum is short of the bar by more than
sampling noise.

**The residual, decomposed — and this is the finding that closes the
question rather than deferring it.** Rater B's 13 asserted false positives:

- 3 are route findings — stratum D2's missed family. Counterfactually
  demoting them: 23/33 = 0.697. **Still short.**
- 2 are class-constant data tables that evade D1's frame test (a
  `private const array` is not a statement-free array-literal frame).
  Counterfactually demoting those too: 23/31 = 0.742. **Still short.**
- 8 are judgment-shaped — provider/controller skeletons, stitched test
  spans, accessor runs, paired-assignment idioms: the exact boundary the
  raters themselves have split on in every round since M3.

So even with both mechanical gaps repaired, the asserted stratum does not
reach 0.80. The remaining distance is the rater-boundary core, now
measured three times (M3's disagreements, M4's eight, M5's five), and no
membership test reaches what two careful raters cannot agree on. The
pre-registered sentence therefore closes the question: **the flip stays
held, the residual is characterized, and the bar does not move again.**

**What the demoted stratum vindicates:** half of it (B: 5/10) is genuine
duplication — copied seeder-like tables, locale-pair lang files. Demote-
never-suppress was the right posture; a silencing rule would have deleted
real findings at every one of the three rounds.

**Ratified along the way:** ruling P's frozen-copy branch (the live tree
moved between two pool runs — the purpose of a pin is reproducibility, and
the hash list only made irreproducibility visible); the three pool-
instrument fixes, each affecting what a rater sees and each recorded; the
six §9 interpretations, every one with its mismatch, rule, and precedent;
D2 left exactly as pre-registered after reaching nothing — the charter's
guard honoured under the strongest temptation to widen it; the D1
const-frame gap recorded as a next-round definition question with the
counterfactual showing it would not have changed this outcome.

**Gates at close, re-run by the auditor:** suite 467/24,896 · phpstan
clean both configs · self-test 24/24 · chaining 2/2 · determinism 4/4 ·
incremental 8/8 · recall 295/295 and 441/441 — the M4 residual stays
closed · superset php-parser 3/3. Ruling 7(b)'s speed cost (§8.2) is
carried to the owner as the one open release decision: accept the
measured level (1.37–1.86× at the largest sizes, shape improving, bought
by 441/441 recall and 5–18 % more reported findings) or trade recall
back. The auditor's recommendation is to accept: recall is the engine's
contract, and the shape — ruling U's own red flag — holds.

**M5 is closed, and with it the precision program.** Three rounds, one
instrument, three corpus states: 0.34/0.36 → 0.47/0.57 → asserted
0.639/0.639 at kappa 0.90/0.72/0.81. The engine's detection dominates the
1.4 default on every measured axis; its report now separates what it
asserts from what it labels; and the default flip is settled by the
project's own pre-registered arithmetic: not taken. 2.0.0 ships as the
owner decided — unified selectable, triage off, classifier as tag,
TokenBag selectable-deprecated — pending the owner's one-line acceptance
of the recall-versus-speed trade for the release notes, the tag, and a
pdflatex rebuild of the paper.

The audit trail ends at six for six: M0 · M1 · M2 · M3 · M4 · M5, every
constant carrying a derivation or a ruling, every retreat recorded where
it happened, and the last open question answered by a number that was
named before anyone knew what it would be.
