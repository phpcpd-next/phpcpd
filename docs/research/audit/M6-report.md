# M6 audit packet

Executor: Claude Opus 5 session, 2026-09-02, from `eaf61bf` (*The 2.0.0 landing
decisions, four process amendments, and the M6 charter*).

M6's charter is the ranked precision-at-K instrument, pre-registered in the plan's
"After M5" section. Its **build scope before the rating round** is named there:

> the pure-label posture (amendment 2), the classifier retirement and SuffixTree
> removal landing in 2.0.0 first, and ruling 7(b)'s numbers carried as the
> baseline.

This packet's first section is that build scope. The rating round is not in it.

---

## 2.0.0 landing

Six steps, in the order the owner set them, one CHANGELOG entry per change and
each change in its own commit, with the hygiene grep — the pattern file kept
outside every repository, run over the whole working tree rather than over
tracked files alone — run before every commit batch.

### 1.1 The state this started from

```
  HEAD                                 eaf61bf, working tree clean
  vendor/bin/phpunit                   OK (467 tests, 24,896 assertions)
  vendor/bin/phpstan analyse           no errors (106 files)
  ... -c phpstan-bench.neon            no errors (19 files)
  hygiene grep                         clean
```

One discrepancy against the M5 packet, recorded rather than smoothed: M5 §6.1
reports the bench PHPStan config as *"no errors (20 files)"*. `phpstan-bench.neon`
lists nineteen paths and PHPStan reports `19/19` here, at `eaf61bf` and unchanged
since. Nothing in this stretch touched the list before the measurement; the
packet's twenty appears to be a transcription slip. Reported because a gate
number that moves without a cause is exactly what a packet exists to catch.

**This stretch shares its working tree with a parallel session**, and the packet
says so because it changes what a reader can conclude from a `git status`. An
untracked `bench/check-provenance.php` was in the tree at the start of this
session and was not written by it; it was committed by that session as `40b4c2e`
(*bench: the ruling-S inventory becomes a measured number*) between this
stretch's first and second commits, and §1.3 takes MODERNIZATION.md's number from
it rather than from a hand count, on the auditor's relayed instruction. Every
commit here therefore stages an explicit path list rather than `-A`, and where a
shared file carried both sessions' edits (`phpstan-bench.neon`) only this
session's hunk was staged. Files belonging to that session — at the time of
writing, an in-progress `bench/check-log-equivalence.php` — are left untouched and
are the reason a bench-PHPStan run over the shipped config is not clean; §1.7
reports both numbers.

**One incident, and it touched this stretch's work.** The other session's
commit-building plumbing resolved `HEAD` twice — once for the tree, once for the
parent — and this stretch's SuffixTree removal (`1df2b0d`) landed between the two
reads, so two of that session's commits carried a tree from the old HEAD with the
new HEAD as parent: an exact revert of the removal under someone else's commit
message. They caught it, rebuilt both commits against an explicit base SHA, and
recorded it as a precedent in `docs/research/audit/M6-ruling-S.md` (`0c5545f`).
Verified independently from this side rather than taken on report: at the current
HEAD the ten SuffixTree files are absent from the index, `NOTICE` carries one
licence, `LICENSE` contains no occurrence of "Apache", MODERNIZATION.md carries
the measured 90.2 %, README carries the triage-labels section, and
`Settings::$triagePosture` still defaults to `label`. Nothing of this stretch's
was lost. Recorded because a packet that reports only its own commits would not
show that its own commits were briefly not what the history said they were.

### 1.2 Step 1 — the fishiness classifier is retired (`2973293`)

**The condition, and the evidence that met it.** The estimator shipped under a
pre-registered condition: *decided at the next rating round*. The owner's landing
decision states the finding; it was re-verified here against the M5 key and both
raters' worksheets, all three of which live outside every repository:

```
  pooled findings carrying the fishy tag          7
    also carrying the table tag (a proof)         5     003 005 037 050 056
    reached by the fishy tag alone                2     058 059

  rater A verdicts on the five      N N N N N     rater B      N N Y N N
  rater A verdicts on the two       Y Y           rater B      Y Y
```

So every demotion the estimator got right was already produced by the table
stratum's *proof* test, and both demotions it produced alone were findings both
raters called genuine duplication. Its correct output was redundant and its
exclusive output was error. This is not a threshold that could be moved: no
margin on that model turns 058 and 059 into non-hits without also losing the
five, which cost nothing because the proof already had them.

**What was deleted.** `Triage\FishinessModel`, `Triage\FishinessClassifier`,
`Triage\FileFeatures`, `Triage\IncludeIndex`, the `train` command in
`bench/triage.php` with its label-set construction, its histogram, its margin
derivation and its model renderer, the `--triage-classifier-discard` flag and its
`Settings` property, the D3 stratum in `bench/audit-precision.php`,
`TriageDecision::FISHY`, and `Strata::FISHY`. `Strata::NAMES` is now
`[table, registration]` and every report format follows it without further
change, because all four loggers read the constant rather than a list of their
own.

`IncludeIndex` is included in that list on a stated reason rather than by
association: its only caller was Stage 0's *loaded-by-path* check, whose only
effect was to exempt a file from the estimator before it was consulted. With no
estimator there is nothing for it to exempt a file from, so it is dead rather
than merely unused.

**Where the estimator's residue legitimately survives, and where it is renamed.**
Two live mechanisms descend from it and are kept, with their lineage restated so
it does not point at a deleted class:

- the **`foreign` rung**, which exists precisely *because* the model could not
  answer that question — M4 measured it at one file in 117 on that class;
- the **literal-share bucket scale** (`0 / 10 / 25 / 50 / 75 %`) that
  `Presentation\ConfidenceFeatures` buckets on, and the literal token class
  `Facts\FileStatements` counts. Both were `FileFeatures`' and are recorded in
  M5 §10.1 as reused-unchanged bucket boundaries. The edges are now stated where
  they are used instead of cited to a class that no longer exists, so the
  derivation is still findable.

**The test that was lost, and the better one that replaced it.** The deleted test
`theClassifierTagReachesAFindingOnlyWhenEverySiteCarriesIt` was the *only* test
carrying `Strata`'s every-site composition rule — the rule that a finding is
demoted only when a stratum holds over all of its sites. Deleting it would have
left a documented invariant unpinned, so it is replaced by
`aStratumHoldingOverOnlyOneSiteDoesNotDemote`, over a new fixture
(`tests/fixtures/registration/routes_inlined.php`): the same fourteen
registrations as `routes_a.php`, copied into a class method so the file's top
level is one class declaration and the role does not hold. The clone is found,
the `registration` stratum holds on one site only, and the finding stays
asserted. That is the case the rule's own docblock is written about — *"a copy of
registration into program text, and the controller's copy is exactly what a
reader wants asserted"* — and it had never been asserted by a test.

**The bench check's posture table collapsed, and why that is the result rather
than a loss.** `bench/triage.php check` printed retention by classifier posture,
because the classifier rung was the only one whose posture changed *what Stage 0
removed*. With the estimator gone there is one Stage 0 answer, so the table has
one line per posture and no curve. The measured effect is the cleanest single
number in this step:

```
  M5 §6.1, same frozen tree     discard 2,405 kept / 90.7 %   demote 2,552 / 97.8 %
  after the retirement          discard 2,552 kept / 97.8 %   demote 4,965 / 100 %
```

`discard` now sits exactly where M5's `demote` sat. The estimator was removing
147 files, and removing it *raises* retention against the frozen corpus
definition from 90.7 % to 97.8 % at no cost to any other rung.

`--fishy-under` went with the trainer. The M4 packet calls it *"a **training**
argument that never becomes a feature"*, and records in the same packet that it
*"is no longer needed for the training labels: the ladder labels them"*, once the
dot-directory product rule left such trees at rung 2. Its one surviving use was
the acceptance target's carve-out, and no run of this check has passed it since;
the target is rung 4, which is what every recorded run actually measured.

**Gates after step 1** (each command as run, output verbatim in §1.6's table):
suite 454/23,380 · phpstan clean, both configs · self-test 24/24 · chaining 2/2 ·
determinism 4/4 · incremental 8/8 · recall 295/295 and 441/441 · precommit-rules
15/15 · triage check 1/1 · superset unchanged at 0 / 0 / 1 / 6 unexplained pairs.
The suite moves from 467/24,896 to 454/23,380, and the whole of the difference is
accounted for: two classifier tests removed and one composition test added
(net −1), plus **twelve data-provider rows** — three `src/`-file-driven tests in
`FileStatementsTest` and `RegionStructureTest` times the four deleted files. The
assertion drop is the same four files' token counts, since `itsSegmentationIsTotal`
asserts once per token. No test lost coverage of anything that still exists.

### 1.3 Step 2 — the SuffixTree is removed (`1df2b0d`)

One commit, as the owner's decision requires: *"Removal takes the NOTICE entry and
the Apache-2.0 attribution in the same commit."* A licence notice that outlives
the code it covers is a notice nobody can check, so the code, the `NOTICE`
section and the `LICENSE` clause move together or not at all.

**What went.** `SuffixTreeStrategy` and the nine files under
`src/Detector/Strategy/SuffixTree/` — eight of which carried the upstream header,
plus `SubstitutionCost.php`, which was this project's own work but had exactly one
caller — `bench/profile-suffixtree.php`, the `NOTICE` section, the `LICENSE`
clause, the `suffixtree` arm in `Engine::strategyFor()` and its `--algorithm`
value, the deprecation notice `Application` printed for it, `run-compare`'s
`--with-st` lane and the now-unused `run_cli()` helper it was the only caller of,
`run-recall`'s and `audit-precision`'s engine lists, and `run-e3`'s default.

**`--edit-distance` and `--head-equality` went with it, and that is an
interpretation — recorded in §1.5.** In short: the instruction named "its
selectable flag", singular; the observed fact is three flags, two of which README
documents as *"suffixtree only"* and which no other engine reads. Leaving them
would ship two hidden flags that silently do nothing.

**The honest limitation, and a fact the instruction did not have.** The owner's
decision records the note to carry: *"the tree still led unified on the pure-insert
recall family (100/100/86 vs 97/80/70)"*. The first column is M3's measurement and
cannot be re-run now the code is gone. The second column is M3's too — and the
engine has moved twice since. Re-run here on the same instrument at the same
settings, before the deletion was committed:

```
  bench/run-recall.php --sample=40 --min-tokens=50, gapped-insert family

    operator             density   pairs   suffixtree (M3)   unified (M3)   unified (now)
    gapped_insert_d1     1.34      147     100.0 %           97.3 %         100.0 %
    gapped_insert_d2     2.60      129     100.0 %           79.8 %          99.2 %
    gapped_insert_d3     3.66       97      85.6 %           70.1 %          85.6 %
```

The limitation is real and is recorded, at both widths: as measured when the
decision was taken it was 100/100/86 against 97/80/70; on the engine actually
shipping it is one operator and 0.8 points. Reporting only the owner's figures
would have understated the engine; reporting only the current ones would have
quietly refreshed the flattering half of a limitation note. Both are in the
CHANGELOG and in README.

The permutation curve moved too and is recorded here because README states it:
`permute_adjacent` / `permute_distant` now read **93.8 % / 89.7 %** where README
says 89.1 % / 89.7 %. Corrected in step 4 rather than here, so the removal commit
carries only the removal.

**`[inconsistent]` was not removed, and the distinction matters.** It is the
report's own flag for a diverged clone — `Log\Text`, `Log\Json`, `Log\Sarif` and
the PHPUnit constraint all emit it for any engine — not the tree's. What was
removed is every reference *attributing* it to the tree, in README, in
`CloneDivergence`, in `CodeClone::isGapped()`'s docblock, in the type-3 fixtures'
comments and in the PHPUnit integration example. Unified's named ranges are named
as the successor everywhere the tree's boolean used to be cited.

**bench/results is untouched.** The E2 and E3 recorded results that include the
tree stay exactly as they are; `run-e3.php` now defaults to `unified` and its
header says which engine the published run used, so a re-run is comparable rather
than silently different.

**The inventory (§1.4's first half, landed here).** MODERNIZATION.md's ruling-S
count is the document the removal changes, so it moved in the same commit rather
than being left false for one. It is now taken from `bench/check-provenance.php`:

```
  src/ at eaf61bf     106 files, 20,801 lines   18 attributed   83.0 % files · 88.3 % lines
  src/ at 1df2b0d      92 files, 18,631 lines    9 attributed   90.2 % files · 93.5 % lines
```

The `eaf61bf` line was measured by exporting that commit's `src/` and applying the
instrument's own header-block rule, so both rows come from one definition. The
document's prose had been claiming 19 files and 85.5 %; it was stale by one file
and 2.5 points before this stretch touched it, because `CLI/Application.php`'s
header had been flipped to this project's own and nobody re-counted. Group A —
the Apache-2.0 group — is now empty, and nine BSD-3-Clause files remain before
ruling S's MIT precondition is met.

**Gates after step 2:** suite 451 tests / 22,930 assertions · phpstan `src/` clean
(92 files) · phpstan bench clean over every tracked file (20) · hygiene clean.

### 1.4 Step 3 — the pure-label posture, defaulted on (`864dab8`)

**What was built.** A third `--triage-posture` value, `label`, and it is the
default; `--no-triage` to turn the stage off; `--show-config` rows for both, so a
default that changes what a run does is visible in the report of what is in
force. Stage 0 runs on every scan, its four rungs stamp every file they decide
about, and nothing else moves.

**The no-op is asserted, in the way the instruction names.** `PresentationTest`
asserts the presentation tier removes nothing by comparing the set of findings out
against the set of clones in. The analogue here is `TriagePostureTest`, which
builds a fixture project — a wired duplicated pair, an unwired duplicated pair, an
entry point that references only the first — runs the CLI in-process both ways,
and compares the **machine-readable reports byte for byte**, plus the exit codes.
The console text is deliberately excluded from the comparison, because it *must*
differ: the labelling announces itself.

The instrument is shown able to fail rather than assumed sound (checklist rule 6):
the same fixture under `--triage-posture=discard` produces a different report, and
the test asserts that difference by name — the unwired pair's file appears in the
labelling report and not in the discarding one. Without that third run the
identity assertion would pass on a fixture where triage happened to label nothing.

**Four labels, where the amendment named three.** The owner's amendment 2 names
*"orphaned / generated / shadowed"*; the stage has four proof rungs — `derived`,
`unwired`, `shadowed`, `foreign`. Read as a boundary, the amendment would leave
the vendored-code rung silent for no stated reason. Read as evidence of purpose —
*stamp what the proof rungs conclude* — all four stamp. Interpretation recorded in
§1.5; the naming maps as generated→`derived`, orphaned→`unwired`, shadowed→
`shadowed`, with `foreign` unnamed in the amendment and included.

**`--orphans` skips the labelling.** Not an exemption invented for convenience:
that report already names every unwired file *with its evidence*, and Stage 0's
unwired rung is the same `OrphanDetector`. Running both would run that detector
twice to print a fact the report carries. A posture that changes the scanned set
is a different request and is still honoured under `--orphans`.

**The one thing this step did not resolve, and why it is a ruling request rather
than a decision.** The owner's amendment describes the alternatives as *"discard
and the coverage-costing demote posture"*. The shipped `demote` posture does not
cost coverage: `Application::triaged()` has removed nothing under `demote` since
ruling (a) wired the stage in. What *does* cost coverage is `demote` as modelled
by `bench/triage.php check`, whose retention table let the proof rungs discard and
only the classifier tag — which is where M5 §6.1's `demote 2,552 / 97.8 %` line
comes from. **The product and the bench have disagreed about what `demote` means,
and the amendment follows the bench.**

After this release's classifier retirement the disagreement has a consequence:
`demote` and `label` keep the same set — everything — so on files they are the
same posture under two names.

What was done about it: nothing to `demote`. Interpretation rule 2b — *words beat
purpose when the action is irreversible or crosses a boundary* — and redefining a
selectable posture's semantics is a product decision, which rule 7's ladder sends
to the owner. So `label` was built exactly as instructed, `discard` and `demote`
are byte-identical in behaviour to what they were, and the collision is carried
here with the readings named:

- **(a) Keep both.** `demote` is a deprecated alias of `label`; a pipeline naming
  it keeps working. Costs one redundant posture in `--help`.
- **(b) Redefine `demote` to the bench's meaning** — proof rungs discard, and the
  posture governs something else. There is nothing left for it to govern.
- **(c) Retire `demote`** in 1.6 with a deprecation notice, `label` being what it
  now does.

The executor's reading is (a) now and (c) at the next release, on the same
convention the owner set for the SuffixTree: a name that pipelines carry gets a
cycle. Not taken here.

**Cost, stated because a default that costs is a default that has to say so.** The
labelling posture runs `SymbolCollector`, `OrphanDetector` and `ShadowedDuplicates`
over the whole corpus on every run. A default clone run already ran
`OrphanDetector` once for its advisory report, so the shipped default now runs it
twice. That is measured in §1.6's sweep as its own line rather than folded into
the engine's.

### 1.5 Interpretations recorded

Each one names the mismatch, the reading taken, the rule from
`docs/research/interpretation.md` that decided it, and the precedent.

**(i) "Remove its selectable flag" — the tree had three, not one.**
*Mismatch:* the instruction says *"Remove its selectable flag"*, singular; the
observed fact is `--algorithm=suffixtree`, `--edit-distance` and
`--head-equality`, the last two documented in README as *"suffixtree only"* and
read by no other engine. *Reading:* remove all three. *Rule 2a* — purpose beats
words when the words assumed a fact that is false; the words assumed one flag.
Rule 2b pushes the other way (words beat purpose in the destructive direction),
and the tie is settled by a precedent inside the owner's own decision, made two
days before this stretch: *"defaults and public APIs get deprecation cycles;
unusable opt-in research flags do not."* Both flags are hidden, opt-in and
research-only, and belong to that engine alone. *Cost asymmetry (rule 5):*
removing them and being wrong makes a script fail loudly on an unknown option;
keeping them and being wrong ships two flags that silently do nothing, which
checklist item 1 — *fail loudly, never coerce* — calls a defect rather than
compatibility. Their `StrategyConfiguration` fields and cache-fingerprint entries
went too, for the same reason; `minSimilarity` stayed, because TokenBag reads it.

**(ii) "stamps files (orphaned / generated / shadowed)" — the stage has four
proof rungs.** *Mismatch:* amendment 2's parenthetical names three labels; Stage
0 proves four — `derived`, `unwired`, `shadowed`, `foreign`. *Reading:* all four
stamp; the parenthetical maps generated→`derived`, orphaned→`unwired`,
shadowed→`shadowed`, and does not name the vendored-code rung. *Rule 1c* — the
instruction's example is evidence of its purpose, not its boundary. *Precedent,
named:* the hidden-directory rule, where a hand list of `.phpstan`/`.psalm`/
`.rector` was evidence of the purpose *"tool state is not program text"* rather
than the boundary of it, and the list was struck for the principle. Reading the
parenthetical as a boundary would leave one proof rung silent for no stated
reason, on a posture whose entire content is *saying what was decided*.

**(iii) `--fishy-under` went with the trainer.** *Mismatch:* the instruction
retires *"the model, trainer, margin machinery, and the fishy stratum"*;
`--fishy-under` is a trainer argument that also had one live use inside the
acceptance check's target. *Reading:* it goes, and the check's target reverts to
rung 4. *Rule 3, precedent from the record:* the M4 packet labels it *"a
**training** argument that never becomes a feature"* and records in the same
packet that it *"is no longer needed for the training labels: the ladder labels
them"*. *Rule 4, priors from the record:* no recorded run of the check has passed
it since, so the target being measured has been rung 4 all along; removing the
carve-out changes no number, and the packet says so rather than assuming it.

**(iv) The limitation note is stated at two widths, not one.** *Mismatch:* the
instruction fixes the note's figures as *"100/100/86 vs 97/80/70"*; re-running the
same instrument at the same settings before the deletion shows the unified column
is now 100.0/99.2/85.6, because the engine moved twice after that comparison was
recorded. *Reading:* publish both — the owner's recorded figures as the decision's
basis, and the current ones beside them. *Rule 2a* again, bounded by *rule 6* —
confidence travels with the verdict, and a figure must not be promoted past its
evidence in either direction. Publishing only the instruction's numbers would
overstate the loss; publishing only the current ones would refresh the flattering
half of a limitation note, which is the failure the note exists to prevent.

**(v) MODERNIZATION.md moved in the removal commit, not in step 4's.**
*Mismatch:* the instruction lists the ruling-S inventory under step 4, after the
removal; leaving it until then would leave one commit in which `NOTICE` and
`LICENSE` carry no Apache-2.0 clause while MODERNIZATION.md still inventories nine
Apache-2.0 files. *Reading:* the inventory is part of the removal and moves with
it. *Rule 2c* — system beats both readings when either would break a seeded
invariant; the invariant is the project's own *"every commit leaves the tree's
licensing documents consistent"*, which is what put `NOTICE` and `LICENSE` in one
commit in the first place. It keeps its own CHANGELOG entry, per the one-entry-per-
change rule, in the same commit.

**(vi) `--orphans` runs skip the labelling posture.** *Mismatch:* "default it on"
met a mode whose entire report *is* the unwired rung, computed by the same
`OrphanDetector`. *Reading:* in `--orphans`, the labelling posture is skipped; a
posture that changes the scanned set is still honoured. *Rule 1c* — the purpose is
that a run says what it thinks each file is, and under `--orphans` it already does,
in more detail. The alternative is running the orphan detector twice per run to
print a fact the report already carries.

**(vii) `FileFeatures` and `IncludeIndex` count as "the model".** *Mismatch:* the
instruction names *"the model, trainer, margin machinery"*; two further classes
had no other consumer. `FileFeatures` is the model's feature extractor.
`IncludeIndex` backed the loaded-by-path check, whose only effect was to exempt a
file from the estimator *before it was consulted*. *Reading:* both go. *Rule 1a* —
the plain reading of "the model" is the model as a working thing, and a feature
extractor with no model and a shield with nothing to shield from are dead code,
which this tool's own orphan detector would report on the next run. Their two
surviving contributions — the literal token class and the `0/10/25/50/75 %` share
scale — are restated where they are used, so the derivation stays findable.

**(viii) The `demote` / `label` collision is a ruling request, not a decision.**
Recorded in full in §1.4. *Rule 7* — escalate rather than guess when the
interpretation would set a precedent and the question is a product decision; the
ladder is executor → auditor → owner. The request carries the named mismatch, the
three readings with their costs, and the executor's non-binding preference.

### 1.6 Step 4 — the documents, brought to reality

**README.** The three items the instruction names, plus what the retirements made
false:

- **Defaults.** A new *Triage labels* section: the four proof rungs as a table,
  the statement that the default posture changes nothing and that the suite
  asserts the identity rather than the prose claiming it, the two opt-in postures
  and `--no-triage`. The run-output example now shows the triage line. The options
  block gains `--no-triage` and the three posture values.
- **The retirements.** The `suffixtree` bullet is replaced by a removal note
  carrying the limitation at both widths (§1.3); the `fishy` demote tag is
  replaced by a retirement note; the lineage section says the package is
  single-licence BSD-3-Clause and why it used not to be; the Type-3 row of the
  capability table, the example commands, the headless-mode signature comment and
  the PHPUnit integration example all name `unified` where they named the tree.
- **The recall-versus-speed statement, both halves.** §1.7's numbers.

Three further README claims were false before this stretch and were corrected
while the surrounding text was being rewritten, each recorded here rather than
absorbed: permutation recall was stated as 89.1 % / 89.7 % and measures
93.8 % / 89.7 %; the precision paragraph carried M4's superseded 0.468 / 0.574
rather than M5's applied criterion; the subsumption residual was stated as eight
pairs where M5 recorded seven (1 + 6). A fourth — a reference to a
`tests/SelfDryTest.php` that exists in no commit of this repository — was reworded
rather than repaired, because writing the test is not this stretch's work; it is
flagged in §1.9.

**CHANGELOG.** Five entries, one per change, each in the commit that made it, plus
a release summary at the head of the 2.0.0 section: what the two removals were and
that each ran on a condition fixed beforehand, that the default engine did not
change and why, that Stage 0 now labels by default, and the recall-versus-speed
trade in both halves. The version heading stays `[2.0.0] - unreleased`; naming
the version and the date is part of tagging, and tagging is the owner's.

**MODERNIZATION.md.** Landed in the removal commit (§1.5(v)) and measured by
`bench/check-provenance.php` rather than by hand.

**The paper.** Its LaTeX source is updated only where the *text* is affected —
which, for a paper whose subject is a sequence of experiments, means present-tense
claims about the tool and results that have been superseded, and never the record
of what was run:

```
  changed   tab:coverage      Type-3 now attributed to the unified engine
            tab:reco          the Type-3 recommendation is --algorithm=unified
            "Default mode"    --edit-distance dropped from the research-flag list;
                              the labelling triage stage stated as part of the default
            §what it costs    permutation recall 89.1 -> 93.8; the guarantee met at
                              both sample sizes; the wall-clock paragraph re-stated
            §precision        the one-rater placeholder replaced by the applied
                              criterion, both raters, the residual decomposition
            §life cycle       the classifier's retirement, with the evidence that
                              met its pre-registered condition
            R4                the [inconsistent] flag now set by banded alignment
            §conclusion       the default question answered rather than open;
                              the tree's removal named
            §future work      the outstanding second rating pass replaced by what
                              three rounds located: a rater-boundary residue, and
                              the ranked instrument it argues for

  unchanged §background       the fork's starting point, including the upstream
                              selector and the edit-distance lemma — history
            §engineering      SuffixTreeStrategy::postProcess()'s defect — history
            §practical (5)    the tree's measured scaling limit — a result
            §E2, §E3          what was run, with which engine and which flags
```

**The PDF was not rebuilt.** `bin/build-paper.sh` requires `pdflatex`, which is not
installed here, and the script exits non-zero rather than emitting anything when it
is missing — the refusal-to-emit-stale-artifacts behaviour the instruction says
stands, exercised rather than described. The committed PDF is therefore one
revision behind its source, deliberately and on the owner's instruction that the
rebuild is theirs.

### 1.7 Step 5 — every standing gate, and the posture-relative sweep

#### 1.7.1 The sweep, and a measurement-hygiene incident that changed three of its rows

**Posture and walker, stated because the rule requires it, and it is not the
posture M5 stated.** Bench walker (`bcb_gate_files`), `--min-tokens=70`, medians of
5 runs (3 on the private corpus). M5's sweep says *"bare run — no triage, no
preset, which is the shipped default"*. That sentence is no longer true of this
release, which is the whole reason `bench/check-walltime.php` gained a
`triage (label)` line: the engines are still measured bare, so the M3/M4/M5 ratios
stay comparable, and what a shipped default run *adds* is a separate row that a
reader adds in.

**The incident.** The first pass of this sweep ran the private corpus while an
unrelated PHPStan analysis of another project on this machine held ~90 % of a core.
Three rows were inflated by it — the private corpus at 2,400 and 2,735 files, and
firefly-iii at 1,445 — and one of them by a factor of two (`triage (label)` at
2,735 read 2.678 s contended and 1.386 s clean). The rows were **re-run on a quiet
machine and the clean readings are the ones reported below**; the contended ones
are recorded here rather than discarded, because a sweep that silently replaces its
own numbers is not a sweep:

```
                              contended     clean
  private 2,735  triage         2.678s     1.386s
  private 2,735  unified        9.191s     5.262s
  private 2,735  default        4.672s     5.031s
  firefly 1,445  default        5.259s     2.197s
```

That the *default* line moved in both directions across the two passes is the
finding worth carrying: TokenBag is the volatile component of the merged default,
and every `u ÷ d` ratio in this table is a quotient of a stable numerator by a
noisy denominator. This is stated because it cuts against the engine's own case at
the largest size — see (ii).

```
  corpus            files   triage   default   unified   +tier    u÷d   (u+t)÷d   M5's u÷d
  private corpus      200   0.391s   0.417s    2.593s   2.648s   6.22x   6.35x     6.05x
  private corpus      600   0.484s   0.735s    2.852s   3.201s   3.88x   4.36x     3.90x
  private corpus    1,200   0.814s   1.482s    3.741s   4.164s   2.52x   2.81x     2.43x
  private corpus    2,400   1.180s   3.007s    4.930s   5.457s   1.64x   1.81x     1.57x
  private corpus    2,735   1.386s   5.031s    5.262s   6.190s   1.05x   1.23x     1.65x
  firefly-iii         200   0.095s   0.172s    1.110s   1.182s   6.45x   6.87x     6.13x
  firefly-iii         600   0.560s   1.259s    2.302s   1.773s   1.83x   1.41x     2.28x
  firefly-iii       1,200   0.502s   1.967s    2.704s   3.009s   1.37x   1.53x     1.15x
  firefly-iii       1,445   0.543s   2.197s    3.286s   3.836s   1.50x   1.75x     1.37x
  phpunit              60   0.012s   0.011s    0.035s   0.038s   3.18x   3.45x     3.08x
  phpunit             200   0.034s   0.029s    0.100s   0.105s   3.45x   3.62x     3.57x
  phpunit             600   0.126s   0.117s    0.324s   0.381s   2.77x   3.26x     2.64x
  phpunit           2,500   0.461s   0.533s    2.173s   2.411s   4.08x   4.52x     4.11x
  phpunit           2,694   0.608s   0.866s    5.301s      —     6.12x     —       5.53x
```

*(The firefly-iii 600 `+tier` median sits below its own `unified` median, which is
impossible as work and is therefore noise in a 5-run median; it is left as measured
rather than dropped. The phpunit 2,694 `+tier` reading is absent for the same
reason the last row of any long batch sometimes is — the run was superseded by the
clean re-run batch before it printed. Neither is used for any claim.)*

**Three readings.**

**(i) The shape holds, which is the property that was ever load-bearing.** Ruling
U's own sentence is that *"the degradation with size, not the multiple, was always
the red flag"*. Both large application corpora still improve monotonically in size:
the private corpus 6.22 → 3.88 → 2.52 → 1.64 → 1.05, firefly-iii 6.45 → 1.83 →
1.37 → 1.50 with the same end-wobble M4 and M5 both had. phpunit remains the
exception M4 §7.1 measured and attributed to one 327 KB test file.

**(ii) The ≤ 1.5× level: this session measures it *met* at the largest private
size, and that is reported as noise rather than as news.** The 2,735-file row reads
1.05× where M5 read 1.65×, and the reason is entirely in the denominator — M5's
default pipeline took 3.351 s there and this session's took 5.031 s on a quiet
machine. Unified's own reading barely moved (5.535 s → 5.262 s). **The release
statement in README and CHANGELOG therefore keeps M5's 1.37–1.86× band**, which is
the number the owner's decision was taken on and the conservative one; claiming an
improvement from a quotient whose denominator swung 50 % between two sessions would
be exactly the kind of favourable-half reporting §1.3's limitation note refuses.

**(iii) Stage 0's labelling costs 26 % to 117 % of the default engine pipeline's
own time, and on small corpora it is the largest single line in a run.** As a
fraction of `default`:

```
  private corpus   94 %  ·  66 %  ·  55 %  ·  39 %  ·  28 %   (200 → 2,735 files)
  firefly-iii      55 %  ·  44 %  ·  26 %  ·  25 %            (200 → 1,445)
  phpunit         109 % · 117 % · 108 %  ·  86 %  ·  70 %     (60 → 2,694)
```

The shape is the one the stage's mechanism predicts — it walks and collects symbols
over every file, so its cost is proportional to the corpus while the default
pipeline's is proportional to the duplication in it — and it means a default
`phpcpd <dir>` on a phpunit-shaped corpus now takes roughly twice as long as it did
in 1.4. That is a real cost of a real default, it is measured rather than
estimated, and it is carried to the owner in §1.8 as the one number in this
landing that a user will feel. `--no-triage` restores the old cost exactly.

### 1.8 What is carried, and what is not closed here

**Carried to the auditor:**

1. **The `demote` / `label` collision** (§1.4, §1.5(viii)) — a ruling request with
   three readings, the executor's non-binding preference, and the finding that the
   owner's amendment describes a `demote` posture the product does not have.
2. **The bench and the product disagree about what a posture *is*.** That
   disagreement predates this stretch and is the root of (1): `bench/triage.php
   check` modelled `demote` as *proof rungs discard, estimator tags*, while
   `Application` has removed nothing under `demote` since ruling (a). The M5
   packet's `demote 2,552 / 97.8 %` line is the bench's meaning, and it was read
   as the product's. Whatever is ruled about the name, the two should be made to
   mean one thing, or the bench should say which one it is measuring.
3. **The default's cost.** §1.7's sweep. Labelling is not free, and on small
   corpora it is the largest single line in a default run.

**Carried to the owner:**

4. **The paper's PDF is one revision behind its source**, deliberately: the build
   script refuses to emit when `pdflatex` is absent, and the rebuild is the
   owner's.
5. **The 2.0.0 CHANGELOG heading is still `[2.0.0] - unreleased`.** Naming the
   version and dating it is part of tagging.

**Not closed here:** this stretch is not the milestone. M6's charter is the ranked
precision-at-K instrument, and its build scope was the three landings above. The
rating round has not been drawn, K = 20 has not been sampled, and nothing in this
packet bears on the flip, which M5 closed as held.

#### 1.7.2 Every standing gate, re-run at the landing's tip

```
  vendor/bin/phpunit                                  OK (456 tests, 22,991 assertions)
  vendor/bin/phpstan analyse                          no errors (92 files)
  vendor/bin/phpstan analyse -c phpstan-bench.neon    no errors
  php bench/self-test.php                             24/24
  php bench/check-chaining.php                        2/2   — ChainBuilder and its oracle untouched
  php bench/check-determinism.php <firefly> unified   4/4   — 725 clones compared
  php bench/check-incremental.php <php-parser> unified 8/8  — 96 vs 96 clones
  php bench/run-recall.php --sample=40                2/2   — 295 of 295
  php bench/run-recall.php --sample=60                2/2   — 441 of 441
  php bench/precommit-rules.php self-test             15/15
  php bench/check-provenance.php self-test            13/13
  php bench/triage.php check --dogfood=<frozen>       1/1   — two runs over one tree agree
  IndexCodec::VERSION                                 5, unchanged
  constants introduced                                none
  hygiene grep (pattern file outside the repo)        clean
```

Subsumption, unchanged from M5 in both directions:

```
  php bench/check-superset.php <php-parser>       3/3
  php bench/check-superset.php <symfony-string>   3/3
  php bench/check-superset.php <phpunit>          locations pass · 1 unexplained pair
  php bench/check-superset.php <firefly-iii>      locations pass · 6 unexplained pairs
```

**`IndexCodec::VERSION` stays at 5, and the reason is checked rather than
asserted.** The standing rule bumps it when *stored bytes or selection* change.
Neither did: the two fields removed from `StrategyConfiguration` were read by the
suffix tree alone, and fingerprint selection is a function of `Winnower`'s
constants and the file's bytes, which are untouched. What did change is the
**cache filename** — `CloneCache::configFingerprint()` no longer hashes
`editDistance` and `headEquality` — and the consequence is checked in the safe
direction: a differently-named fingerprint is a cache *miss*, never a stale entry
misread under a new meaning. `CloneCache::VERSION` describes the JSON payload,
which is unchanged, so it stays at 2.

**Two gates that fail, both by prior ruling, both re-run because a gate that is
not run is not an instrument.** `bench/check-walltime.php` fails on every corpus
and size at 0.16×–0.96× — that is the M3 parity gate (*"unified ≥ default-pipeline
speed at every size"*), which ruling U superseded with the 1.5× band and which the
M4 and M5 closes both recorded as failing-and-superseded. The two `check-superset`
residuals are the same seven pairs M5 recorded (1 on phpunit, 6 on firefly-iii),
verified unchanged rather than assumed so.

**On "no constant without a derivation."** This stretch introduced no numeric
constant. It *removed* one — `FishinessClassifier::MARGIN` — and moved no bucket
boundary: `ConfidenceFeatures`' `0/10/25/50/75 %` literal-share scale and its
`10/50/100` line splits are unchanged in value; only the sentence naming their
origin moved, from a citation of a deleted class to a statement of the edges
themselves.

**Hygiene, stated precisely rather than as a checkmark.** The grep — the pattern
file kept outside every repository, run over the whole working tree rather than
over tracked files alone — was run before the commit batches for steps 1, 2 and 3,
and is clean at each. It was **not** run before `2a59064` (the sweep-instrument
commit); it was run immediately after, and is clean, and that commit touched only
`bench/check-walltime.php` and `CHANGELOG.md`. Recorded as a deviation from
"before every batch" rather than rounded up to compliance.

### 1.9 State at the stop

```
  commits by this session          5, each with its own CHANGELOG entry
                                     2973293  the classifier retirement
                                     1df2b0d  the SuffixTree removal
                                     864dab8  the labelling posture, defaulted on
                                     2a59064  Stage 0 as a sweep line
                                     d402a04  the documentation pass
  IndexCodec::VERSION              5, unchanged
  constants introduced             none; one removed
  src/                             92 files, 90.2 % original by files, 93.5 % by lines
  attributed files remaining       9, all BSD-3-Clause; the Apache-2.0 group is empty
  the corpus                       never named in code, fixtures, docs or messages
  worksheets and snapshots         outside every repository; none written here
  TokenBag                         untouched
  the dead discriminators          still dead; nothing here is a rewording of one
  the dead filters                 still dead; rules 8 and 9 not resurrected
  the label set                    outside the repository, read by nothing in it
  the tag                          not applied — step 6, the owner's hand
```

**The numbers, in one place:**

```
  recall                           441/441 at sample 60 · 295/295 at sample 40
  subsumption residual             0 / 0 / 1 / 6 unexplained pairs, unchanged
  suite                            456 tests, 22,991 assertions
  phpstan                          clean, both configs
  speed, ruling U bar 3            shape held; level missed; release band 1.37–1.86x
                                     (M5's, kept — see §1.7.1(ii))
  Stage 0's cost as a default      26 %–117 % of the default pipeline's own time
  precision                        unchanged by this stretch; M5's applied criterion stands
  the flip                         held, as M5 closed it
```

### 1.10 Not closed here

**This stretch is not closed by its executor.** The 2.0.0 landing is built, the
gates are green or failing-by-prior-ruling with each recorded, and the docs match
the tree. What it waits on:

1. The auditor's check of this section, with §1.4's `demote`/`label` ruling request
   first, and §1.7.1's measurement-hygiene incident and §1.7.2's hygiene deviation
   as the two things a close audit exists to catch.
2. The owner's tag, their `pdflatex` rebuild of the paper, and their reading of
   §1.7.1(iii) — the one number in this landing a user will feel.

M6's own charter — the ranked precision-at-K instrument — is untouched. Nothing
here bears on the flip.

---

AUDIT: *awaiting the close-audit verdict on the 2.0.0 landing.*

---

AUDIT (landing stretch): **pass — 2.0.0 is ready to tag.**
2026-09-02, Fable session.

Verified by re-running: suite 456/22,990 · phpstan clean both configs ·
self-test 24/24 · chaining 2/2 · determinism 4/4 · incremental 8/8 ·
recall 295/295 and 441/441 · provenance 13/13 (9 inherited files remain,
90.2 % original) · superset 3/3 on php-parser, residual 0/0/1/6 unchanged.
The classifier retirement re-verified against the M5 key (its five correct
demotions were the table stratum's; its two exclusive ones were consensus-
genuine); the SuffixTree removal verified single-licence with LICENSE
retaining Sebastian's BSD notice as required; the label posture's no-op
proven byte-for-byte with a fixture that shows the comparison can fail.

Two audit fixes landed with this verdict: composer.json's licence
declaration corrected to BSD-3-Clause (it still claimed Apache-2.0 with no
Apache code left — relayed twice, missed twice, fixed once), and the §1.4
ruling: **demote is retired unshipped** — the deprecation-cycle convention
protects names a release has shipped, and 2.0.0 is the first release that
would ever have shipped it. Two postures ship: label (default), discard.

Weighed and accepted: the Stage 0 labelling cost (26–117 % of the default
pipeline's own time) with --no-triage restoring 1.4's cost exactly — the
docs state it; the two disclosures (the loaded sweep re-run clean with
both readings printed; one hygiene grep run after rather than before a
commit, clean); the four-labels-for-three-names interpretation (1c,
correctly recorded); the --orphans labelling skip (same detector, no
double run). The HEAD-race near-miss is ratified as precedent and is now
interpretation.md's tenth checklist line.

The ruling-S lane (M6-ruling-S.md) resumes on the "tree clean" relay:
goldens from this SHA, then the Log trio, then the CodeClone trio. The
tag is the owner's; nothing further blocks it.
