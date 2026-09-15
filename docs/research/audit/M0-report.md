# M0 — Harness self-test + BCB-PHP extension

Executor: Claude Opus. Plan: `docs/research/unified-engine-plan.md` §2, milestone M0.
No engine code in this milestone, by design.

**Scope, as specified:**

1. a harness self-test that (a) deliberately times out and records it as a timeout,
   (b) deliberately fails and records a failure, (c) asserts wall-clock deltas are
   measured in-process;
2. restore `bench/` from `https://github.com/phpcpd-next/phpcpd` (`bench/` on `main`),
   without rewriting it;
3. extend `bench/injectors.php` with two operator families at function granularity,
   parameterized by density — gapped edits (insert/delete/substitute, d ∈ {1,2,3} at
   controlled spacing) and statement permutations (adjacent and distant swaps), with
   manifests recording every injection.

**Gates:** self-test green; injectors produce manifests that a checker script can verify
against the mutated files byte-for-byte.

---

## 1. Gates

### Gate 1 — harness self-test green

```
$ php bench/self-test.php ; echo "exit=$?"
```

```
BCB-PHP harness self-test

A. deliberate timeout
  PASS  a run past its deadline is recorded as a timeout — outcome=timeout
  PASS  the timed-out run is recorded as having actually started — started=true
  PASS  the deadline is enforced, not merely observed after the fact — deadline 0.600s, child would run 10s, returned after 0.646s
  PASS  the timed-out process is actually dead, not orphaned behind a killed shell — heartbeat 11 bytes at kill, 11 bytes 0.4s later
  PASS  a run that fits inside its deadline is NOT recorded as a timeout — outcome=ok

B. deliberate failure
  PASS  a run that exits non-zero is recorded as a failure — outcome=failed
  PASS  the failing run reports its actual exit status — exitCode=3
  PASS  the failing run captures what the subject wrote to stderr — stderr='deliberate failure'
  PASS  a failure is never reclassified as a timeout
  PASS  a command that does not exist is recorded as a failure — outcome=failed
  PASS  a missing binary is NEVER recorded as a timeout (the 2024 benchmark burn) — error='could not start command: bcb-no-such-binary-51927 --version'
  PASS  the missing binary is reported honestly as never-started or exit 127 — started=false exitCode=NULL
  PASS  a missing binary fails immediately rather than consuming its deadline — 0.004s

C. in-process wall clock
  PASS  an in-process 0.25s of work measures at least 0.25s — 0.2508s
  PASS  the clock never runs backwards — 0.000001s
  PASS  the clock discriminates: a no-op measures shorter than 0.25s of sleep — noop 0.000001s < slept 0.2508s
  PASS  a subprocess duration is the in-process delta an outer measurement also sees — inner 0.4955s, outer 0.4956s, difference 0.0000s
  PASS  a throwing measurement is recorded as a failure with its message — error='RuntimeException: deliberate in-process failure'
  PASS  a failed measurement still reports how long it ran before failing — 0.000027s
  PASS  repeated measurement reports every run, never a single number — runs=5
  PASS  repeated measurement reports a median and a spread — median 0.1050s, spread 0.0005s

D. output must be parseable
  PASS  a zero-exit run whose output does not parse is recorded as a failure — outcome=failed
  PASS  the unparseable run still reports its clean exit status, so the cause is visible — exitCode=0
  PASS  a run that produces the expected answer passes and yields the parsed value — parsed=42

harness self-test: 24/24 checks passed.
exit=0
```

Mapping to the three required properties:

| Required | Where |
|---|---|
| (a) deliberately times out, recorded as a timeout | section A, checks 1–3 |
| (b) deliberately fails, recorded as a failure | section B, all five checks |
| (c) wall-clock deltas measured in-process | section C, checks 1–4 |

Three checks beyond the specification, each closing a way the required three could pass
while the harness was still broken:

- **A5** — a run *inside* its deadline must not be a timeout. Without it, a harness that
  returned `BCB_TIMEOUT` unconditionally passes A1–A3.
- **A4** — a heartbeat file written by the child every 50 ms is measured at kill time and
  again 0.4 s later; equal sizes prove the child is dead rather than orphaned behind a
  killed wrapper. This is why `bcb_run()` execs an argv list rather than a shell string.
- **D** — a zero exit whose output does not parse is demoted to a failure, so a subject
  whose output format drifts breaks the benchmark instead of contributing zeroes.

The 2024 burn is reproduced by name in B: `bcb-no-such-binary-<pid>` is classified
`failed`, never `timeout`, and fails in 0.004 s rather than consuming its deadline.
Structurally, `BCB_TIMEOUT` is returned from exactly one branch of `bcb_run()` — the one
that observes the deadline and calls `proc_terminate()`. No exit status and no stderr
text can reach it.

### Gate 2 — manifests verifiable byte-for-byte

```
$ php bench/inject.php src/Detector/Strategy/DefaultStrategy.php <out>/DefaultStrategy --ops all
```

```
  type1                  → DefaultStrategy_type1.php (0 recorded edits)
  type2                  → DefaultStrategy_type2.php (0 recorded edits)
  type3                  → DefaultStrategy_type3.php (0 recorded edits)
  ssdiff                 → DefaultStrategy_ssdiff.php (0 recorded edits)
  ssdiff_bool            → DefaultStrategy_ssdiff_bool.php (0 recorded edits)
  gapped_insert_d1       → DefaultStrategy_gapped_insert_d1.php (1 recorded edit)
  gapped_insert_d2       → DefaultStrategy_gapped_insert_d2.php (2 recorded edits)
  gapped_insert_d3       → DefaultStrategy_gapped_insert_d3.php (3 recorded edits)
  gapped_delete_d1       → DefaultStrategy_gapped_delete_d1.php (1 recorded edit)
  gapped_delete_d2       → DefaultStrategy_gapped_delete_d2.php (2 recorded edits)
  gapped_delete_d3       → DefaultStrategy_gapped_delete_d3.php (3 recorded edits)
  gapped_substitute_d1   → DefaultStrategy_gapped_substitute_d1.php (1 recorded edit)
  gapped_substitute_d2   → DefaultStrategy_gapped_substitute_d2.php (2 recorded edits)
  gapped_substitute_d3   → DefaultStrategy_gapped_substitute_d3.php (3 recorded edits)
  permute_adjacent       → DefaultStrategy_permute_adjacent.php (2 recorded edits)
  permute_distant        → DefaultStrategy_permute_distant.php (2 recorded edits)
  manifest → <out>/DefaultStrategy/manifest.json (16 pairs, 0 skipped)
```

```
$ php bench/check-manifest.php <out>/DefaultStrategy ; echo "exit=$?"
```

```
  ... 156 checks ...
  PASS  DefaultStrategy/permute_distant: is_clone is labelled correctly for the operator — is_clone=true

manifest check: 156/156 checks passed.
exit=0
```

The checker verifies five independent things per pair, of which #2 is the gate's literal
requirement:

1. sha256 of base and variant match what the manifest recorded;
2. **re-running the recorded operator on the recorded base reproduces the variant
   byte-for-byte** — via `bcb_inject()`, the same entry point `inject.php` used, so the
   checker cannot be verifying its own second copy of the mutation logic;
3. re-derivation reports the same number of edits the manifest records;
4. each recorded edit's before-bytes are at its offset in the base and its after-bytes
   are at its offset in the variant;
5. the variant parses, and `is_clone` is labelled correctly for the operator.

An empty `pairs` list and a missing manifest both fail rather than passing vacuously.

**The checker was verified to discriminate.** A checker that always passes would satisfy
the gate's text while proving nothing, so six tampering cases were run; each exits 1 with
a specific diagnosis:

| Tampering | Result |
|---|---|
| one byte appended to a variant file | exit 1 — 2 of 156 failed: variant sha256 mismatch, and re-derivation reports `first difference at offset 7453` |
| one byte appended to the base file | exit 1 — 32 of 156 failed (base digest + re-derivation, every pair) |
| a recorded `base_offset` shifted by 7 | exit 1 — 1 of 156 failed: `edit 0 replaces the bytes it says it does in the base` |
| `is_clone` flipped on `ssdiff` | exit 1 — 1 of 156 failed |
| manifest with `pairs: []` | exit 1 — `manifest describes at least one pair` |
| manifest absent | exit 1 — `manifest exists` |

Verbatim, for the first case:

```
manifest check: 2 of 156 checks FAILED:
  - neg/gapped_insert_d2: variant file matches its recorded sha256 — recorded 'c55a6aae0a89ef24bf877c7b021c1ed511fa02de3a1bf895d70fd90a119c3b14', found 809fe5ac2a41624973bec3c269f722b62c77a67145879036ab241b513140bcf1
  - neg/gapped_insert_d2: re-deriving the operator reproduces the variant byte-for-byte — re-derived 7453 bytes, on disk 7466 bytes, first difference at offset 7453
```

The first and fifth cases are also asserted in the suite
(`BenchHarnessTest::injection_produces_a_manifest_the_checker_accepts_and_tampering_breaks_it`).

---

## 2. Standing gates

### PHPStan level max, zero errors

```
$ vendor/bin/phpstan analyse --memory-limit=1G --no-progress
Note: Using configuration file /Users/studiox/Downloads/phpcpd-main/phpstan.neon.

 [OK] No errors
```

```
$ vendor/bin/phpstan analyse -c phpstan-bench.neon --memory-limit=1G --no-progress

 [OK] No errors
```

`phpstan.neon` (level max, `src/`) is unchanged — it is the project's established config.
`phpstan-bench.neon` is new and holds every bench file this milestone wrote or touched to
the same level. It lists files rather than the directory because the pre-2.0 runners
(`run-compare`, `run-e2`, `run-e3`, `measure-density`, `profile-suffixtree`) are written
against the 1.4 API and produce 49 errors at level max; see Deviation D2.

### Full test suite green

```
$ vendor/bin/phpunit --do-not-cache-result
PHPUnit 11.5.55 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.9
Configuration: /Users/studiox/Downloads/phpcpd-main/phpunit.xml
Random Seed:   1787909249

................................................................. 65 / 82 ( 79%)
.................                                                 82 / 82 (100%)

Time: 00:04.599, Memory: 10.00 MB

OK (82 tests, 400 assertions)
```

Baseline at the start of M0 was 24 tests / 102 assertions. This milestone adds 56:
`InjectorsTest` (43) and `BenchHarnessTest` (13). The remaining 2 came from the parallel
commit `4eeb720`, which extended `tests/Regression/ScanCorrectnessTest.php` (§6, C4).

### Determinism check

Built this milestone as `bench/check-determinism.php`, since the standing rules require it
after every milestone and no such check existed. It compares three things and refuses to
pass on a corpus with no duplication in it (two empty reports are byte-identical).

```
$ php bench/check-determinism.php vendor/symfony ; echo "exit=$?"
```

```
Determinism check — 348 files, algorithms: default, rabin-karp, tokenbag

  default (rk+tokenbag) — 47 clones in 0.283s
  PASS  default (rk+tokenbag): two runs over the same file list are byte-identical
  FAIL  default (rk+tokenbag): reversing the file list does not change a byte of the report — a different set of clones entirely
  PASS  default (rk+tokenbag): two CLI runs write byte-identical JSON — 23403 bytes
  rabin-karp — 38 clones in 0.151s
  PASS  rabin-karp: two runs over the same file list are byte-identical
  FAIL  rabin-karp: reversing the file list does not change a byte of the report — the same clone pairs, but with the two copies' roles exchanged — the report names whichever copy it happened to reach first
  PASS  rabin-karp: two CLI runs write byte-identical JSON — 19454 bytes
  tokenbag — 9 clones in 0.128s
  PASS  tokenbag: two runs over the same file list are byte-identical
  FAIL  tokenbag: reversing the file list does not change a byte of the report — a different set of clones entirely
  PASS  tokenbag: two CLI runs write byte-identical JSON — 4200 bytes
  PASS  the corpus actually contains clones, so the comparisons above mean something — 94 clones compared

determinism check: 3 of 10 checks FAILED:
  - default (rk+tokenbag): reversing the file list does not change a byte of the report — a different set of clones entirely
  - rabin-karp: reversing the file list does not change a byte of the report — the same clone pairs, but with the two copies' roles exchanged — the report names whichever copy it happened to reach first
  - tokenbag: reversing the file list does not change a byte of the report — a different set of clones entirely
exit=1
```

**This is a measurement of the three existing engines, not a failure of M0 — M0 adds no
engine code.** It is reported in full because it bears directly on an M1 gate. See
Open concern C1.

M0's own deterministic-code obligations are met and tested: every operator is a pure
function of its input, `InjectorsTest::every_operator_is_deterministic` asserts all 16
produce identical output on repeated application, edit sites are chosen by a stated rule
(`bcb_edit_positions()`, evenly spaced and interior), the target function is chosen by a
stated rule (most statements, earliest on a tie), and edits are applied last-first so
offsets cannot shift under each other. No clocks, no randomness, no hash-order dependence.

---

## 3. Diff summary

### New files

| File | Lines | What it is |
|---|---:|---|
| `bench/harness.php` | 407 | The measurement primitive: `bcb_now()` / `bcb_time()` / `bcb_repeat()` on the monotonic clock; `bcb_run()` / `bcb_run_parsed()` for subprocesses with a harness-owned deadline; `bcb_check()` / `bcb_check_summary()` for gate reporting; `bcb_argv()`. |
| `bench/self-test.php` | 310 | The 24-check gate of Gate 1. |
| `bench/check-manifest.php` | 232 | The byte-for-byte checker of Gate 2. |
| `bench/check-determinism.php` | 251 | The determinism check. |
| `phpstan-bench.neon` | 23 | Level max over the bench files this milestone owns. |
| `tests/InjectorsTest.php` | 447 | 43 tests: the five E2 operators, statement segmentation (including a regression case per construct that broke it), edit placement, both new families, the registry, determinism. |
| `tests/BenchHarnessTest.php` | 219 | 13 tests: outcome classification, parseable-output demotion, in-process timing, and both gate scripts run as subprocesses with their exit status asserted. |

### Restored from upstream `main`, unchanged

`fetch.sh`, `manifest.json`, `measure-density.php`, `profile-suffixtree.php`,
`run-compare.php`, `run-e2.php`, `run-e3.php`, and all of `results/` (18 files).

### Restored and modified

| File | Change | Why |
|---|---|---|
| `bench/lib.php` | +22 −26 | **Port, not rewrite.** It referenced `LucianoPereira\PhpcpdNext\Arguments`, deleted in the 2.0.0 refactor, so the file could not even load. `bcb_config()` now builds `StrategyConfiguration` directly and `bcb_strategy()` resolves through `Engine::strategyFor()`. The benchmark's own defaults (minTokens 50 / minLines 1) are preserved so previously recorded runs stay comparable. Behaviour change: an unknown algorithm name now throws instead of silently falling back to `DefaultStrategy`. |
| `bench/injectors.php` | +712 −1 | The two new operator families (§4) plus `bcb_parses()`. `bcb_operators()` still returns exactly the five E2 operators. |
| `bench/inject.php` | +75 −23 | `--ops all`; manifest v2 with sha256 digests and per-edit records; ineligible operators are reported and dropped rather than written as an identity pair. Default op set unchanged. |

### Modified outside `bench/`

`.gitignore` (+19: `bench/corpus/`, `bench/vendor/`, LaTeX artifacts),
`CHANGELOG.md` (+90), `README.md` (+57: a benchmarking section and an ordering note in
the embedding section), `composer.json` (+14 −7: `analyse:bench`, `bench:verify`, and
`check` now running both PHPStan configs).

---

## 4. The two operator families

Nine gapped operators and two permutation operators, reached by name:

```
gapped_{insert,delete,substitute}_d{1,2,3}   permute_{adjacent,distant}
```

**Density and spacing.** For N top-level statements and d edits, sites are
`round(i·(N−1)/(d+1))` for i = 1..d — evenly spaced, distinct, and strictly interior, so
every gapped variant keeps a matching head and tail statement. Eligibility requires
N ≥ d+2; `permute_adjacent` requires N ≥ 4 and `permute_distant` N ≥ 5. An ineligible
operator returns `eligible: false` with a reason and the input unchanged, and `inject.php`
drops it rather than writing an identity pair under a gapped label.

**Function granularity.** Edits land in one function: the one with the most top-level
statements, earliest on a tie. "One edit per clone" is only a density if the clone is a
fixed unit, and the statement count of that function is the denominator the spacing is
derived from.

**Every injection is recorded.** Each edit is a byte-range replacement carrying
`kind`, `function`, `statement_index`, `base_offset`, `base_length`, `base_text`,
`variant_offset`, `variant_text`, and a note. A swap is recorded as two such
replacements. Variant offsets are derived arithmetically from the cumulative length
change of preceding edits, never searched for — a search would find the wrong copy of a
marker that appears twice.

**Statement segmentation, and how it was validated.** The segmenter walks tokens with a
stack of brace *kinds*, an enclosure depth for `(`/`[`/`#[`, and lookaheads for
`}` followed by `;` / `else` / `elseif` / `catch` / `finally` / do-`while`. Four
constructs broke earlier versions, each found by generating variants over the full
`vendor/` + `src/` + `tests/` tree and parsing every one:

| Construct | Failure | Fix |
|---|---|---|
| `for ($i = 0; $i < $n; $i++)` | two `;` at body level split the loop header | enclosure-depth guard |
| `"a{$x}b"` | the interpolation's `}` ended a statement mid-string | stack of brace kinds |
| `[1 => fn() {...}, 2 => fn() {...}]` | a `}` before every comma ended a statement | `[`/`]` counted as enclosures |
| `$this->{$p}[0]` | a name brace read as a block | previous-significant-token check |

Final sweep, in-process via `token_get_all(..., TOKEN_PARSE)`:

```
parseable input files=4149  variants generated=20502  ineligible=25137  variants that do not parse=0
```

Each of the four constructs has a named regression case in
`InjectorsTest::segmentation_survives_the_constructs_that_once_broke_it`.

**E2 is untouched.** `bcb_operators()` returns exactly `type1, type2, type3, ssdiff,
ssdiff_bool` and `InjectorsTest::the_e2_operator_set_is_exactly_the_five_it_has_always_been`
pins that. `run-e2.php` reads no manifest, so the v2 manifest format cannot affect it.
The new families are reached only through `bcb_all_operator_names()` or `--ops all`.

---

## 5. Deviations from the plan

**D1 — `bench/lib.php` was modified, against "Do not rewrite it".** It was unrunnable:
it constructed `Arguments`, a class the 2.0.0 refactor deleted. The change is the
minimum port to the current API (see §3) and preserves the benchmark's defaults and
semantics. Nothing about what the benchmark measures changed. The published results in
`bench/results/` were not re-run and no runner that produces them was modified.

**D2 — PHPStan scope.** The plan requires "PHPStan level max, zero errors" without naming
paths. `phpstan.neon` covers `src/` only, which is the project's established config, and
it is left untouched. The five pre-2.0 bench runners produce 49 errors at level max because
they call removed APIs (`CodeCloneFile::name()`, `::startLine()`) — 23 in `run-compare.php`,
11 in `run-e3.php`, 7 in `run-e2.php`, 6 in `measure-density.php`, 2 in
`profile-suffixtree.php`; rewriting them is
forbidden by M0 and unnecessary for it. Rather than leave new code unchecked, the files
this milestone wrote or touched are held to level max in a second config,
`phpstan-bench.neon`, wired into `composer check`. Porting the remaining runners is
proposed as separate work (Open concern C3).

**D3 — three artifacts were built that M0 does not name:** `bench/check-manifest.php`,
`bench/check-determinism.php`, and `phpunit.xml`/`phpstan.neon`. The first is required
by Gate 2 ("a checker script"). The second is required by the standing rule that the
determinism check pass after every milestone, and no such check existed. The config files
were absent from the working tree; a parallel commit (`698155b`) has since tracked
identical copies.

No constant was tuned, no gate was weakened, and no fixture was made to pass by
adjusting a threshold. The one behavioural change found mid-milestone (the segmenter
bugs) was fixed in the segmenter, not worked around in the fixtures.

---

## 6. Open concerns

**C1 — the three current engines are not stable under file-list reordering, and M1's
determinism gate demands that the unified engine is.** Measured above on 348 files:
`rabin-karp` finds the same clone pairs and exchanges which copy it names first;
`tokenbag`, and therefore the merged default, returns a different set of clones. Repeated
runs and repeated CLI runs are byte-identical, and the CLI is unaffected because
`FileFinder` sorts before detection — this is reachable only by an embedder passing an
unsorted list to `Engine::detect()`. Recorded here as the baseline, documented in README
under the embedding section, and left unfixed: M0 adds no engine code, and M4 removes
these engines. **The auditor may want to rule on whether M1's determinism gate is
understood to apply to `unified` alone** (my reading, from the gate's placement under
M1's "the unified report…" list) or to the whole tool.

**C2 — the test suite this tree inherits is thin, and it is not the published one.** The
working tree is 2.0.0, substantially refactored past upstream 1.4.0 (`Settings`,
`ScanScope`, `Presets` replacing `Arguments`/`ArgumentsBuilder`), but only
`tests/SettingsTest.php` and `tests/Regression/ScanCorrectnessTest.php` came with it —
24 tests at the start of this milestone, against roughly 30 test files upstream. Those
upstream tests cannot simply be restored, since many target removed APIs. This is not an
M0 defect, but "full test suite green" is a weaker statement here than it reads.

**C3 — five bench runners are stale against the 2.0 API** (`run-compare.php`,
`run-e2.php`, `run-e3.php`, `measure-density.php`, `profile-suffixtree.php`). They load,
but calls such as `CodeCloneFile::name()` and `::startLine()` no longer exist, so they
will fail at runtime. M2 needs `run-e3.php` for the E3 replication and M3 needs
`run-compare.php`'s shape for wall-clock measurement, so this must be resolved before
then. It is deliberately not done here: M0 forbids rewriting them, and porting them is
better done in the milestone that actually runs them, against that milestone's gates.

**C4 — the parallel session is committing to this branch.** Three commits landed under
this milestone while it was in progress: `4eeb720` (an orphan-detection performance
change in `src/`), `0e8d4dd` (the whitepaper), and `698155b` (the PHPStan/PHPUnit
configs). Every gate above was re-run at the current HEAD, so the results are valid for
this tree, but the M0 commit will sit on top of an `src/` change this milestone did not
make and did not review.

**C5 — one soft spot in the new operators, stated rather than hidden.** `gapped_delete`
and `gapped_substitute` can remove a `return` statement or another semantically
load-bearing line. Every variant is verified to *parse*, and none is ever executed, so
this does not affect clone detection — but a reader of a variant file may find code that
would not run. If the auditor prefers, the substitute operator could be restricted to
non-`return` statements; that would change which statements the density lands on, so it
is not something to do without a decision.

---

## 7. Reproducing every gate

```bash
composer bench:verify                                        # Gate 1
php bench/inject.php src/Detector/Strategy/DefaultStrategy.php /tmp/m0/ds --ops all
php bench/check-manifest.php /tmp/m0/ds                      # Gate 2
vendor/bin/phpstan analyse --memory-limit=1G                 # level max, src/
vendor/bin/phpstan analyse -c phpstan-bench.neon --memory-limit=1G
vendor/bin/phpunit
php bench/check-determinism.php vendor/symfony               # baseline; see C1
```

---

AUDIT: **pass** — recorded 2026-08-28 by the authoring (Fable) session.

Independently re-run by the auditor, not taken from this report: self-test
(exit 0), inject + check-manifest on `DefaultStrategy` (156/156, exit 0), both
PHPStan configs (zero errors), the suite (82/400 green), and an **independent
tamper test** (byte appended to `gapped_delete_d2` variant → 3 specific
failures, exit 1 — the checker discriminates). Code-level claims verified by
reading: `BCB_TIMEOUT` is constructed at exactly one site, guarded by the
deadline branch with SIGKILL + reap; `check-manifest.php` re-derives through
`bcb_inject()`; `lib.php`'s diff is the stated 48 lines; the new bench code
carries no provenance from other tools. No gate was weakened.

Rulings on the open concerns:

- **C1 — ruled: M1's file-list-reversal gate applies to the `unified` engine
  alone.** The incumbents' instability is baseline, correctly recorded, and dies
  with them in M4; retrofitting order-stability into engines scheduled for
  removal is dead work. Tool-wide, CLI determinism (which holds) remains
  mandatory, and `unified` must pass reversal at both the CLI and the embedder
  API (`Engine::detect()` with an unsorted list).
- **C2 — accepted as recorded.** "Full suite green" is understood to be relative
  to a thin inherited suite; each milestone keeps adding its own tests, and M4
  must add CLI-surface characterization tests before the default flips. Noted as
  an M4 precondition.
- **C3 — approved as proposed, now a plan amendment:** `run-e3.php` is ported
  inside M2, `run-compare.php` (and `measure-density.php` if M3 uses it) inside
  M3, each held to `phpstan-bench.neon` level max when touched.
  `profile-suffixtree.php` may remain stale and leave with the suffix tree in
  M4.
- **C4 — resolved, no conflict.** The three parallel commits are this auditor
  session's own audited work (`4eeb720` audited here; `0e8d4dd`/`698155b`
  authored here). The M0 commit may sit on top of them.
- **C5 — ruled: keep the operators as they are.** Parse-validity is the
  contract; variants are never executed, and restricting `substitute` away from
  `return` statements would perturb the stated density rule for no measurement
  benefit. Add one doc line in `injectors.php` stating variants are parse-valid,
  not run-valid — with the commit, no re-audit needed.

M0 may be committed as one milestone commit. M1 is cleared to start.
