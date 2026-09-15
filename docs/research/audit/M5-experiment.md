# M5 pre-commitment experiment — rules 8 and 9, measured against the record

**Verdict: FAIL.** Neither candidate reaches ruling U's 0.80 bar, and one of them
cannot be built at all from the evidence it was told to derive itself from.

This is an **experiment, not a milestone**. Nothing in `src/` changed, no product
behaviour changed, and this report ends at the verdict: what happens next is the
auditor's and the owner's, per the plan's *"Fail → the residual is
re-characterized and no milestone is built on a dead projection."*

The charter is the plan's **"After M4 — owner decisions for 2.0.0 (recorded
2026-09-02) and the M5 gate"** section, which carries the pre-registered success
criteria. The numbers being tested against are the M4 packet's §10 (the two-rater
score, ruling U bar 2 recorded failing) and §15.1 (the audited scorecard).

---

## 1. The pre-registered criteria, restated before the results

Quoted from the plan, unchanged:

> Pre-registered success criteria, written before the prototype runs: projected
> two-rater precision ≥ 0.80 on the M4 worksheet with **zero** consensus-Y
> silenced on either worksheet; Php7 ↔ Php8 and every genuinely-copied-table
> fixture survive rule 9; a paired negative per rule.

Read as five conditions, all of which must hold:

```
  1  projected precision >= 0.80 on the M4 worksheet, under BOTH raters
  2  zero consensus-Y silenced, on either worksheet
  3  Php7.php <-> Php8.php survives rule 9
  4  the symfony Unicode pair stays silent
  5  a paired negative per rule, built before the measurement, intact
```

Condition 1 is read as *both raters*, not *either*: ruling U bar 2 is recorded in
M4 §10.3 as failing "under both raters", and plan §3's *"never weaken a gate to
pass it"* settles which way an ambiguity goes.

**Result, condition by condition:**

```
  1  FAIL   rule 8:  0.478 (rater A) / 0.587 (rater B) — was 0.468 / 0.574
            rule 9:  no floor is derivable, so it applies to nothing
            both:    0.478 / 0.587
  2  PASS   zero consensus-Y silenced, on either sheet, by either rule
  3  PASS   reported, and never silenced; its literal overlap is 1.0000
  4  PASS   not reported at all, before or after
  5  PASS   both pairs built first, both intact, both in the self-test
```

Rule 9 fails a second and prior way: **the labels contain no positive example of
the relation it is about**, so the floor the charter told the experiment to derive
has only one side of a separation to sit between. Per the charter — *"If a rule
wants a threshold the labels cannot derive, that is a FAIL for that rule,
surfaced, not a number picked"* — no number was picked.

---

## 2. The instrument

`bench/precommit-rules.php`, new, four modes: `self-test`, `measure`, `corpus`,
`explain`.
It is listed in `phpstan-bench.neon` (which says *"anything added to `bench/` from
now on belongs in this list"*) and is clean at level max.

It **reuses** the shipped facts layer rather than reimplementing it:
`RegionStructure::literalTable()` is ruling 5's granted definition of *literal* —
statement-free — and rule 9's scope test is a call to it. The span mapping (a
reported first-line and line-count, resolved to a significant-token range through
`DefaultStrategy::tokenize()`'s `tokenRealLines`) is the same mapping
`bench/triage.php`'s span tier uses, so the two tiers are scored against the same
spans.

### 2.1 The self-test, and the two paired negatives it carries

Both negatives were written **before** any measurement was taken; they are in the
committed script, as fixtures, and the pairs are minimal — each pair has equal
token counts, so the two halves differ only in the thing the rule names. That is
the shape `RegionStructureTest`'s ruling-5 fixtures fixed for this project.

```
$ php bench/precommit-rules.php self-test
M5 pre-commitment rules — self-test

  PASS  rule 8 fires on order-independent configuration — Config::set × 3
  PASS  rule 8 keeps the paired negative — one shared receiver variable — $config->set × 3
  PASS  the rule 8 pair has equal token counts, so it differs only in the named thing — 15 vs 15 tokens
  PASS  rule 8 keeps a dataflow-coupled builder run — $query->select/where/orderBy
  PASS  rule 8 keeps a span holding a closure — a closure body is statements
  PASS  rule 8 declines a span of one statement — nothing to be pairwise about
  PASS  rule 9 gives a genuinely copied table pair overlap 1 — 1.0000
  PASS  rule 9 separates two shape-matched tables holding different data — 0.1429
  PASS  the rule 9 pair has equal token counts, so it differs only in its literals — 28 vs 28 tokens
  PASS  rule 9 declines a logic-bearing table — ruling 5 decides that case, not this one — a closure in a row makes the frame statement-bearing
  PASS  the floor decides, not the code path — the copied pair is kept at 0.50 and silenced at 1.50 — a gate that cannot fail on demand is not a gate
  PASS  and the unrelated pair flips the other way — silenced at 0.50, kept at 0.10 — overlap 0.1429
  PASS  a whole-file span is exactly the encoder token count — 28 tokens
  PASS  statement segmentation is total — every significant token belongs to one statement — 28 of 28
  PASS  RegionStructure and this script number tokens identically — 28 vs 28

M5 pre-commitment rules self-test: 15/15 checks passed.
```

**The rule 8 pair.** Fifteen significant tokens each; one line differs:

```php
Config::set('mail.host', 'smtp.example.com');   // silenced — nothing is written, nothing shared
$config->set('mail.host', 'smtp.example.com');  // kept — three statements, one shared receiver
```

**The rule 9 pair.** Twenty-eight significant tokens each; one table against a
byte-identical copy of itself in a second file (the genuinely copied seeder,
overlap 1.0000, kept), and against a table of the same shape holding different
data (overlap 0.1429).

**"Prove instruments can fail"** (interpretation half 2, rule 6) is discharged by
the two flip checks: the copied pair is kept at floor 0.50 and silenced at 1.50,
the unrelated pair silenced at 0.50 and kept at 0.10. The floor is what decides,
not a dead branch.

---

## 3. Rule 8 as implemented

> *A span whose statements are pairwise dataflow-independent single
> call-expressions is configuration written as statements, not duplicated logic.*

A membership test over token classes, **no constant**, three structural questions:

1. **Is the span made of statements?** The segmentation assigns every significant
   token to exactly one statement, so this is total by construction (the
   self-test asserts it).
2. **Is each statement a single call-expression?** Its tokens read as
   `callee ( args ) [ (-> | ?-> | ::) name ( args ) ]*` and nothing else. Any
   statement keyword, any assignment or in-place mutation, any declaration, any
   closure disqualifies it. `Foo::class` is the one carve-out — the tokenizer
   spells its second half `T_CLASS`, and it is a name, not a declaration.
3. **Are they pairwise dataflow-independent?** No two statements name the same
   variable.

**The dataflow test is "shares a variable", and it was fixed before the
measurement.** At token level a call's effects are invisible: `$b->add(1)` may or
may not mutate `$b`, and an argument may or may not be by reference. The only
channel that *is* visible is the variable, so two statements naming one variable
are dependent and the span is kept. `$this` counts, deliberately: a run of
`$this->assertX()` calls is a procedure over one receiver, and this rule is not
entitled to silence it. The reading is conservative in the direction the
project's own cost asymmetry names (interpretation rule 5) — a false silence
costs a real clone, a false keep costs one noisy finding.

**Composition over a finding:** rule 8 silences a finding only when *every* one of
its sites is such a span.

---

## 4. Rule 9 as implemented, and the derivation rule

> *Two shape-matched statement-free tables are the same data only if their
> literal values substantially overlap.*

**Scope** is a membership test: both spans sit wholly inside a statement-free
array-literal frame, asked of `RegionStructure::literalTable()`. Shape-matching is
not tested — the engine reporting the pair *is* the shape match.

**The statistic** is the Jaccard overlap of the distinct literal values in the two
spans (`T_CONSTANT_ENCAPSED_STRING`, `T_LNUMBER`, `T_DNUMBER`, by source text):
`|A ∩ B| / |A ∪ B|`. Symmetric, bounded, unweighted by span length.

**The derivation rule, written into the script before any number was looked at,**
follows this project's own precedent — `Winnower::NORMALIZED_DIVERSITY_FLOOR`,
*"the smallest floor that clears the data case entirely while sitting at the
function-body distribution's own 1st percentile … derived from that separation,
not tuned"*:

```
  floor = the smallest two-decimal value strictly greater than the LARGEST
          overlap among in-scope consensus-N findings
  valid only if that value is <= the SMALLEST overlap among in-scope
          consensus-Y findings
```

Hugging the N side rather than splitting the gap is the cost asymmetry again: the
smallest floor that clears the false positives silences least.

**Composition over a finding:** rule 9 silences a finding only when *every* pair of
its sites is in scope and every pair is below the floor. A pair the rule cannot
speak about keeps the finding.

---

## 5. The derivation — the measurement, recorded in full

Two preserved worksheets, both raters, relocated against ruling P's manifest
(`M4-stage0-final.tsv`, 4,847 files). Every file behind every site was verified to
still hash to its pinned value before being read; the live tree has drifted from
the pin (63 added, 39 removed, 32 changed), and no drifted file was used.

```
$ php bench/precommit-rules.php measure --corpus=<root> --pin=<manifest> \
    --sheet=M3:<raterA>:<raterB>:<key>:<sites> --sheet=M4:<raterA>:<raterB>:<key>:<sites>

  M3 — 26 findings in rule 9's scope (26 consensus-labelled, 0 contested and excluded)
    009   consensus N   overlap 0.0205
    051   consensus N   overlap 0.1157
    028   consensus N   overlap 0.1419
    045   consensus N   overlap 0.1738
    002   consensus N   overlap 0.1828
    050   consensus N   overlap 0.2105
    005   consensus N   overlap 0.2192
    056   consensus N   overlap 0.2222
    053   consensus N   overlap 0.2353
    057   consensus N   overlap 0.2400
    013   consensus N   overlap 0.2566
    060   consensus N   overlap 0.2597
    047   consensus N   overlap 0.2759
    049   consensus N   overlap 0.2857
    055   consensus N   overlap 0.2985
    043   consensus N   overlap 0.3208
    048   consensus N   overlap 0.3393
    034   consensus N   overlap 0.3478
    054   consensus N   overlap 0.3485
    033   consensus N   overlap 0.3556
    031   consensus N   overlap 0.3636
    032   consensus N   overlap 0.3636
    041   consensus N   overlap 0.3673
    006   consensus N   overlap 0.4020
    059   consensus N   overlap 0.5538
    001   consensus N   overlap 0.7627

  M4 — 6 findings in rule 9's scope (4 consensus-labelled, 2 contested and excluded)
    057   consensus N   overlap 0.0000
    033   consensus N   overlap 0.1403
    005   consensus N   overlap 0.1690
    010   consensus N   overlap 0.6279
    034   consensus -   overlap 0.9000
    016   consensus -   overlap 0.9245

  largest overlap among consensus-N in scope   0.7627
  smallest overlap among consensus-Y in scope  (none observed)

  NO FLOOR: one side of the separation has no observation. Rule 9 is not derivable.
```

### 5.1 Why there is no Y side, stated as a count rather than an impression

```
  rule 9's scope, over consensus-labelled findings
        findings  no pair   some pair   every pair (= in scope)
    M3 Y     24         24         0             0
    M3 N     31          5         0            26
    M4 Y     32         32         0             0
    M4 N     17         13         0             4
```

**Fifty-six consensus-Y findings across the two sheets, and not one of them has a
single site pair in rule 9's scope.** Not "few" — none. Every consensus-Y finding
has at least one side that is not a statement-free table, which is the same thing
as saying the raters never called two tables duplicated logic on this corpus.

That is a fact about the evidence, not about the rule. A floor cannot be derived
from one side of a separation, and the pre-registered rule says so rather than
letting a midpoint be invented.

### 5.2 Where the Y side would have had to come from, and what is there instead

The two highest overlaps observed anywhere on either sheet are **contested**:
findings 034 (0.9000) and 016 (0.9245) on the M4 sheet, both rated **A = N,
B = Y**. Both are the same shape: one lang table matching another region of
itself. They are two of the eight disagreements M4 §10.2 lists, and by the
pre-registered rule a contested label takes no part in a derivation — a contested
label is not a label.

So the statistic does appear to separate: everything both raters called N sits at
0.0000–0.7627, and the only two observations above 0.90 are the two that one
rater called Y. But those two are **self-matches within one file**, which is
precisely the case M4 §7.2 predicted and §11.4 characterised as the
*outermost-frame* change — a separate, still-unlanded change with its own
prediction and its own acceptance, which would silence them regardless of what
rule 9 does. The anchors rule 9 would need are therefore both contested *and*
targeted by a different pending change. Recorded, not acted on.

### 5.3 Reading the derivation set, and one interpretation

The charter says the floor is derived *"from the 117 consensus labels across both
preserved worksheets"*. **117 does not reproduce.** The two sheets carry 120
rated findings; the M3 sheet has 3 rater disagreements and the M4 sheet has 8, so
the consensus set is 57 + 52 = **109**. 117 is 60 + 57 — the M4 sheet's full count
plus the M3 sheet's consensus count. The derivation was run over the 109, and both
sheets' contested findings are listed above (marked `-`) rather than dropped, so
either reading can be checked against the same record. Under the alternative
reading nothing moves: the two contested in-scope findings are the two above 0.90,
and admitting them would make the floor depend on a label the two raters did not
agree on. The interpretation is recorded in §9.

---

## 6. The worksheets, scored

A filtered finding is not reported, so it leaves the pool. Findings whose evidence
could not be fully recovered are **held as surviving** — the conservative
direction, the same one `bench/triage.php`'s tracking metric takes.

```
=== M3 — what the filters silence ===

  60 findings; 58 scorable, 2 held as surviving
    held  037  A=N B=N  4 of 5 sites relocated
    held  040  A=N B=N  1 of 2 sites relocated

  rule 8 silences 1 finding(s)
    008   A=N B=N consensus=N engines=unified
  rule 9 silences 0 finding(s)

    all engines
      rater A   before Y 25 N 35 0.417 | rule 8 0.424 | rule 9 0.417 | both 0.424  Wilson [0.306, 0.551]
      rater B   before Y 26 N 34 0.433 | rule 8 0.441 | rule 9 0.433 | both 0.441  Wilson [0.322, 0.567]
    unified only
      rater A   before Y 18 N 35 0.340 | rule 8 0.346 | rule 9 0.340 | both 0.346  Wilson [0.232, 0.482]
      rater B   before Y 19 N 34 0.358 | rule 8 0.365 | rule 9 0.358 | both 0.365  Wilson [0.248, 0.501]

=== M4 — what the filters silence ===

  60 findings; 56 scorable, 4 held as surviving
    held  019  A=N B=N  0 of 2 sites relocated
    held  029  A=N B=Y  1 of 2 sites relocated
    held  039  A=Y B=Y  4 of 5 sites relocated
    held  056  A=N B=N  0 of 2 sites relocated

  rule 8 silences 1 finding(s)
    045   A=N B=N consensus=N engines=unified
  rule 9 silences 0 finding(s)

    all engines
      rater A   before Y 34 N 26 0.567 | rule 8 0.576 | rule 9 0.567 | both 0.576  Wilson [0.449, 0.694]
      rater B   before Y 40 N 20 0.667 | rule 8 0.678 | rule 9 0.667 | both 0.678  Wilson [0.551, 0.783]
    unified only
      rater A   before Y 22 N 25 0.468 | rule 8 0.478 | rule 9 0.468 | both 0.478  Wilson [0.341, 0.619]
      rater B   before Y 27 N 20 0.574 | rule 8 0.587 | rule 9 0.574 | both 0.587  Wilson [0.443, 0.717]

=== the failure condition ===
  consensus-Y findings silenced on either sheet: 0
```

The `before` row reproduces M4 §10.1 to the digit — 0.468 rater A and 0.574 rater
B on the unified engine, 0.567 and 0.667 overall — which is the check that this
instrument is scoring the sheet the packet scored.

**Against the bar:**

```
  ruling U bar 2                  >= 0.80
    M4 sheet, unified, rater A     0.468  ->  0.478   FAIL — bar outside [0.341, 0.619]
    M4 sheet, unified, rater B     0.574  ->  0.587   FAIL — bar outside [0.443, 0.717]
```

Rule 8 moves the M4 sheet by **one finding**, from 0.468 to 0.478 and from 0.574
to 0.587 — 0.010 and 0.013 against a gap of 0.33 and 0.23. Rule 9 moves it by
none, having no floor to apply.

**The failure condition is not tripped:** zero consensus-Y silenced, on either
sheet, by either rule, under any of the readings measured. Both rules are *safe*
on this evidence. Neither is *sufficient*.

### 6.1 Why rule 8 reaches one route finding of seven, measured rather than asserted

The M4 sheet holds exactly seven route-list findings — 002, 007, 008, 030, 043,
045, 051 — every one of them consensus N, which is M4 §10.3's "7 route lists"
landing on the nose. Rule 8 silences one. `explain` says why, per site:

```
$ php bench/precommit-rules.php explain --corpus=<root> --pin=<manifest> --sites=<table>

  002    47 statements over  398 tokens — 8 of 47 statements are not single call expressions
         45 statements over  392 tokens — 8 of 45 statements are not single call expressions
  007     9 statements over   80 tokens — 2 of  9 statements are not single call expressions
          9 statements over   83 tokens — 2 of  9 statements are not single call expressions
  008     9 statements over   72 tokens — rule 8 fires
         10 statements over   84 tokens — 1 of 10 statements are not single call expressions
         11 statements over   95 tokens — 1 of 11 statements are not single call expressions
  030    20 statements over  219 tokens — rule 8 fires
         29 statements over  323 tokens — rule 8 fires
         29 statements over  323 tokens — rule 8 fires
          9 statements over  104 tokens — 2 of  9 statements are not single call expressions
  043    17 statements over  148 tokens — 3 of 17 statements are not single call expressions
         17 statements over  165 tokens — 5 of 17 statements are not single call expressions
         20 statements over  172 tokens — 3 of 20 statements are not single call expressions
  045     5 statements over   83 tokens — rule 8 fires
          5 statements over   92 tokens — rule 8 fires
  051    24 statements over  204 tokens — 3 of 24 statements are not single call expressions
         26 statements over  216 tokens — 2 of 26 statements are not single call expressions
         22 statements over  187 tokens — 2 of 22 statements are not single call expressions
         29 statements over  244 tokens — 3 of 29 statements are not single call expressions
```

Not one of the six is refused for the **dataflow** half of the rule — no span
anywhere on either sheet was refused for sharing a variable. Every refusal is the
**single-call-expression** half, and in each case a small minority of statements
does it: 1 to 8 of 9 to 47, a grouping wrapper or a closure-bearing registration
in an otherwise order-independent block. Two findings (008, 030) fire on some of
their sites and not all, and the composition rule fixed in advance — *silence only
when every site is such a span* — keeps them.

So rule 8's shortfall on this evidence is a **composition** result, not a
definition result: the rule recognises the family, and the family's spans are not
made *entirely* of the thing it recognises. That distinction is recorded because
it points at two quite different follow-ups, and choosing between them is not this
report's to do.

### 6.2 A counterfactual, labelled as one, so the residual can be characterised

The plan's Fail branch is *"the residual is re-characterized"*. To size it: the
report was re-run with a floor of **0.77** — the hug-the-N-side value rule 9 would
have taken *had the Y side been observed*. **This is not a derived floor, it
decides nothing, and it does not change the verdict.**

```
  COUNTERFACTUAL floor 0.77 (not derived)
    M3   rule 9 silences 26 findings   unified: A 0.340 -> 0.667   B 0.358 -> 0.704
    M4   rule 9 silences  4 findings   unified: A 0.468 -> 0.512   B 0.574 -> 0.628
    both rules together
    M3   unified  A 0.692 [0.500, 0.835]   B 0.731 [0.539, 0.863]
    M4   unified  A 0.524 [0.377, 0.666]   B 0.643 [0.492, 0.770]
  consensus-Y silenced, even counterfactually: 0
```

Two things this says. The M3 sheet moves enormously (0.34 → 0.69/0.73) because
that pool predates ruling 5 and is full of table self-matches — the shipped engine
already silences most of that class, which is why the M4 sheet has 4 in-scope
findings where M3 has 26. And **even the counterfactual does not reach 0.80 on the
M4 sheet**: 0.524 and 0.643. The bar is not one derivation away.

---

## 7. Public-corpus regressions — every removal opened and named

Four bench corpora, all at their pinned SHAs, `--min-tokens=70 --min-lines=5`,
unified engine, filters applied post-hoc. Rule 9 is applied at the **counterfactual
0.77**, because it has no derived floor; every removal below is therefore what a
floor at the N-side hug *would* remove, named so a reader can check it.

Clone counts were cross-checked against two standing instruments on the same tree
(`bench/check-superset.php`, `bench/check-determinism.php`) and agree to the digit.

```
  corpus            files   clones    rule 8 removes   rule 9 removes (cf. 0.77)
  php-parser          342       82         0                0
  symfony/string       33       22         0                2
  phpunit           2,694      581         0                2
  firefly-iii       1,445      617         4                7
```

**firefly-iii, rule 8 — four removals, every one a route table:**

```
  routes/api.php:66 <-> routes/api.php:75 <-> routes/api.php:95     9 lines
  routes/web.php:415 <-> routes/web.php:523                        18 lines
  routes/web.php:474 <-> :529 <-> :592 <-> :610 <-> :641            26 lines
  routes/web.php:514 <-> routes/web.php:589                         47 lines
```

Nothing outside `routes/` moved, on any corpus. That is the family M4 §10.3 named
as *"7 route lists — a class no rule in this engine currently names"*, and rule 8
reaches exactly it and nothing else.

**firefly-iii, rule 9 at the counterfactual floor — seven removals:**

```
  app/Api/V1/Requests/Models/Recurrence/UpdateRequest.php:106
      <-> app/Api/V1/Requests/Models/Transaction/StoreRequest.php:118    18 lines  overlap 0.4000
  app/Support/JsonApi/Enrichments/SubscriptionEnrichment.php:307
      <-> app/Transformers/AvailableBudgetTransformer.php:65
      <-> app/Transformers/BillTransformer.php:55                        13 lines  overlap 0.6667
  app/Transformers/BillTransformer.php:54
      <-> app/Transformers/BudgetLimitTransformer.php:81
      <-> app/Transformers/PiggyBankTransformer.php:60                   44 lines  overlap 0.3958
  config/bindables.php:68 <-> :93 <-> config/firefly.php:417             25 lines  overlap 0.0000
  config/firefly.php:563 <-> config/firefly.php:587                      25 lines  overlap 0.0000
  config/firefly.php:581 <-> config/firefly.php:622                      40 lines  overlap 0.0000
  config/firefly.php:587 <-> config/firefly.php:747                     100 lines  overlap 0.0000
```

The last three are the `config/firefly.php` self-matches M4 §11.4 identified as
the *outermost-frame* case that ruling 5 correctly declines. A rule-9 floor would
reach them by a different route — worth naming, because it means the two pending
changes overlap on the same findings rather than composing.

**symfony/string, rule 9 at the counterfactual floor — two removals:**

```
  Inflector/EnglishInflector.php:261 <-> :48   100 lines  overlap 0.0903
  Inflector/EnglishInflector.php:288 <-> :33    64 lines  overlap 0.0900
```

Two regions of the inflector's rule table against each other — a table matching
itself, at 9 % literal overlap.

**phpunit, rule 9 at the counterfactual floor — two removals:**

```
  src/TextUI/Configuration/Registry.php:67
      <-> src/TextUI/Configuration/Xml/Migration/MigrationBuilder.php:29   25 lines  overlap 0.0000
  tests/unit/Framework/Assert/assertEqualsTest.php:217 <-> :86             38 lines  overlap 0.2308
```

### 7.1 The two named regressions

```
$ php bench/precommit-rules.php corpus php-parser --floor=0.77
  PASS  Php7.php <-> Php8.php survives rule 9 — out of rule 9 scope, so never
        silenced; its literals overlap 1.0000 anyway

$ php bench/precommit-rules.php corpus symfony-string --floor=0.77
  PASS  the symfony Unicode table pair stays silent — not reported
```

The Php7/Php8 result is worth stating precisely, because "survives" has two very
different readings and only one of them is evidence. The pair is **out of rule 9's
scope** — the reported span begins at the property declaration, so it is not
wholly inside the array frame, and the rule declines to speak. But its literal
overlap, measured with the scope test removed purely to report the number, is
**1.0000**: the two generated parser tables draw on the same literal vocabulary
exactly, as two tables a grammar change regenerates together should. The flagship
true positive survives on its evidence and not only on a technicality. The
statistic is not the part of rule 9 that failed.

The Unicode pair is not reported at all, before or after — the M2 diversity floor
already silences it, and a post-hoc filter can only remove.

---

## 8. Gates

Nothing in `src/` was touched, so the product gates are re-run as confirmation
rather than as acceptance.

```
  vendor/bin/phpunit                                    OK (252 tests, 1243 assertions)
  vendor/bin/phpstan analyse                            no errors
  vendor/bin/phpstan analyse -c phpstan-bench.neon      no errors (18 files)
  php bench/self-test.php                               24/24
  php bench/precommit-rules.php self-test               15/15
  php bench/check-chaining.php                          2/2
  php bench/check-determinism.php firefly-iii unified   4/4
  php bench/check-superset.php on all four corpora      counts cross-checked
  measure run twice, byte-identical output              deterministic
  hygiene grep (pattern file outside the repo, whole working tree)   clean
  constants introduced                                  none
  src/ files changed                                    none
  IndexCodec::VERSION                                   5, unchanged
```

---

## 9. Interpretations recorded

**(i) "the 117 consensus labels across both preserved worksheets" — the count
does not reproduce.** *Mismatch:* the charter names 117; the two sheets carry 120
rated findings with 3 and 8 rater disagreements, so the consensus set is 109. 117
is 60 + 57 — one sheet's full count plus the other's consensus count. *Reading
taken:* the operative word is **consensus**, and the derivation was run over the
actual consensus set (109), with every contested finding still listed in the
record so either reading can be checked. *Rule:* interpretation 2a — purpose beats
words when the words assumed a fact that is false. *Precedent:* the E3 slice —
*"the same 200-file slice"* assumed mtime was reproducible; it was not, the
purpose was reproducibility, so the slice was re-derived and the change surfaced.
The count is surfaced here rather than silently corrected, and nothing in the
verdict turns on it: under either reading no consensus-Y finding is in rule 9's
scope.

**(ii) "Implement the membership test over token signatures" — the signature
cannot carry a statement boundary.** *Mismatch:* `DefaultStrategy::tokenize()`
emits five bytes per token for *array* tokens only, so every single-character
token — `;` included — is dropped. A rule about statements cannot be computed from
that string. *Reading taken:* the test is implemented in the shape of the facts
layer — over the full `token_get_all()` stream, indexed in the encoder's
significant-token numbering, as a membership test over named token classes with no
constant. *Rule:* interpretation 1c — read toward the purpose; the instruction's
example is evidence of its purpose, not its boundary. *Precedent:* ruling 5 itself,
whose granted class is *"`T_FUNCTION`, `T_FN`, `;`"* and which is implemented in
`RegionStructure` exactly this way. The self-test pins the numbering agreement
between this script and `RegionStructure`.

**(iii) The relocated site tables were regenerated rather than reused.**
*Mismatch:* the preserved M4 site table covers 50 of 60 findings; re-running
`bench/relocate-worksheet.php` against the same pin covers 58 of 60. *Reading
taken:* regenerate, because the stored table was written against an earlier pin
and more evidence is better evidence, and because relocation is *"matching, never
judgement"* — no verdict is revised by it. Every file behind every site was
verified still at its pinned hash before being read. The two findings that still
do not relocate (019, 056, both consensus N) are held as surviving. *Rule:*
interpretation 0 — no mismatch with the instruction's words, which name the
worksheets and not a particular derived table; recorded because the coverage
number differs from the one M4 §11.2 reports.

---

## 10. What the experiment establishes, and one discrepancy surfaced

Stated as findings, not as recommendations. What happens next is not this
report's to decide.

1. **Rule 8 is safe, on-target, and far too small — and the shortfall is
   composition, not definition.** It silences exactly the route-definition family
   it was written for — four findings on firefly-iii, all in `routes/`, nothing
   else on any corpus — with one consensus-N finding on each rated sheet and zero
   consensus-Y anywhere. On the M4 sheet that is +0.010 and +0.013 against a gap
   of 0.33 and 0.23. The family M4 §10.3 sized at "7 route lists" is present on
   that sheet at exactly 7 and is reached at 1. §6.1 measures why: every refusal
   is the single-call-expression half, never the dataflow half, and in each case
   1 to 8 statements of 9 to 47 do it — a grouping wrapper inside an otherwise
   order-independent block. The rule recognises the family; the family's spans
   are not made entirely of what it recognises.

2. **Rule 9 is not derivable from this evidence, and the reason is structural.**
   Fifty-six consensus-Y findings across two sheets, zero of them with a single
   site pair in the rule's scope. The rule asks when two tables are the same data;
   the raters, on this corpus, never said two tables were the same data. A
   two-sided separation cannot be derived from a one-sided sample, and the
   two observations that would have anchored the Y side are contested labels on
   self-matching lang tables — a case a different, already-predicted change
   targets.

3. **The statistic itself is not what failed.** Php7 ↔ Php8, the corpus's flagship
   true positive among tables, measures 1.0000. The consensus-N side runs
   0.0000–0.7627. The gap is wide and in the right direction. What is missing is a
   labelled positive to fix a floor against.

4. **Even a counterfactual floor does not reach the bar.** With rule 9 applied at
   0.77 and rule 8 together, the M4 sheet reads 0.524 (rater A) and 0.643
   (rater B). The 0.80 bar is outside both intervals. Whatever the residual is, it
   is not one threshold away.

5. **The derivation set and the evaluation set overlap, and the projection is
   therefore optimistic.** The charter asks for the floor to be derived from both
   sheets and the precision to be projected on the M4 sheet, which is the same
   evidence twice. It does not matter here — no floor was derived, and rule 8
   carries no derived quantity at all — but it would matter to any successor
   experiment, and it is recorded so the point is not rediscovered.

**One discrepancy, surfaced and not chased.** M4 §11.3 records post-ruling-5 clone
counts of `php-parser 83`, `symfony/string 22`, `phpunit 581`, `firefly-iii 573`.
On this tree — whose last `src/` change is `aaac0d1`, one commit after ruling 5's
`35f3490`, with all four corpora at their pinned SHAs and no cache present — the
counts are `82 / 22 / 581 / 617`. Two match exactly, php-parser is off by one, and
firefly-iii is off by 44 in the *other* direction (more clones now than §11.3
records before ruling 5, which was 597). The 617 is confirmed by
`bench/check-superset.php` and `bench/check-determinism.php` independently, and
all three runs are byte-stable, so the number is the tree's and not this
instrument's. It is outside this experiment's charter and is left for the
auditor. `bench/check-manifest.php` also fails on all four corpora, but only for a
missing per-corpus `manifest.json` sidecar that `bench/fetch.sh` writes — the
checkouts themselves are at the pinned SHAs.

---

## 11. Verdict

**FAIL.**

```
  1  projected two-rater precision >= 0.80 on the M4 worksheet
       rule 8 alone     A 0.478 [0.341, 0.619]   B 0.587 [0.443, 0.717]   FAIL
       rule 9 alone     inapplicable — no floor is derivable                FAIL
       both together    A 0.478                  B 0.587                   FAIL
  2  zero consensus-Y silenced on either worksheet                          PASS
  3  Php7.php <-> Php8.php survives rule 9                                  PASS
  4  the symfony Unicode pair stays silent                                  PASS
  5  a paired negative per rule, built first, intact                        PASS
```

Rule 9 fails twice over: it cannot be built from the labels it was told to derive
itself from, and the counterfactual that assumes it could does not reach the bar
either.

Per the plan: *"Fail → the residual is re-characterized and no milestone is built
on a dead projection."* M5 does not open on this experiment. The
re-characterisation and any decision about what follows are the auditor's and the
owner's; this report ends here.

---

AUDIT: **verdict ratified — FAIL, and the failure is the finding.**
2026-09-02, Fable session.

The experiment did exactly what pre-registration exists for: it killed a
milestone for the price of one bench instrument. Conduct is ratified in
full — refusing to pick rule 9's floor when the labels could not derive it
(a one-sided separation is not a derivation, and "no number was picked" is
the correct output), labelling the counterfactual as deciding nothing, and
recording the two charter interpretations rather than absorbing them.

**The discrepancy is resolved, and it is not a defect.** The auditor re-ran
both postures at the pinned SHAs: the CLI — Laravel detection applied,
1,291 files — reproduces §11.3's 573 clones / 54,753 lines exactly;
the bench walker's 1,445 files give the experiment's 617. php-parser is
the same cause at smaller scale (343 files/83 clones CLI vs 341/82
walker). Both packet numbers and both experiment numbers are correct;
what §11.3 lacked was its posture line. The posture-relative rule now
extends explicitly to *packet citations*: any corpus-level number names
its walker and posture, always.

**The re-characterisation, from the evidence:**

1. **The span-filter road is exhausted.** Rule 8 reaches only the pure
   registration runs (1 of the sheet's 7 route findings — the rest carry
   group closures and mixed statements, and both raters call them N by
   *role*, not by any local structure a membership test can see). Rule 9
   has no derivable floor because zero of 56 consensus-Y findings have any
   site pair in its scope. The best counterfactual lands at 0.52/0.64.
   The 0.80 bar is not reachable by silencing rules derived from these
   labels, and no future session should re-walk this road without new
   labels that change the derivation base.
2. **What the labels do license is demotion, not silence.** Zero
   consensus-Y in the statement-free-table scope means tagging that whole
   class as low-confidence costs nothing the raters ever valued — while
   the constructed seeder negative shows silencing it would be wrong. The
   same holds for role-tagged route files. The machinery (demote posture,
   classifier tags, log-odds lines) already ships.
3. **The remaining distance to 0.80 is therefore judgment-shaped**, and
   the honest instruments for it are stratification and ranking, not
   filters: the tool *asserts* an undemoted stratum and *labels* a demoted
   one, and the bar applies to what it asserts — with the demoted stratum
   still rated and reported, so the split cannot hide anything.

**The fork this leaves, for the owner — the auditor frames it and takes
neither branch:**

- **(i) Stratified bar:** pre-register the strata (demoted = statement-free
  table pairs, role-tagged registration files, classifier tags; asserted =
  everything else), then a fresh pool and two-rater pass rating *both*
  strata. The flip criterion becomes: asserted stratum ≥ 0.80. This is a
  bar restatement and only the owner can make it; the auditor's condition
  is that the demoted stratum's precision is measured and published beside
  it, so the stratification is a lens, never a hiding place.
- **(ii) Keep the flat bar:** then the flip stays held indefinitely — this
  experiment is the evidence that no derivable rule reaches it — and 1.5
  ships as decided, with the engine selectable and the presentation tier
  (rank, tags, ledger) improving what users experience without moving the
  measured number.

Either way, the presentation tier is worth building; it is the only work
left that is provably safe (ranking never filters) and it serves both
branches. M5's identity now depends on the owner's answer: (i) makes it
the stratification-and-rating milestone; (ii) makes it the presentation
milestone with no bar attached.
