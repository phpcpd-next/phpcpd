# Changelog

All notable changes to **phpcpd-next** are documented here.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)  
Versioning: [Semantic Versioning](https://semver.org/spec/v2.0.0.html)

> For the complete technical diff against upstream (every changed line with a *Why* explanation),
> see [MODERNIZATION.md](MODERNIZATION.md).

---

## [Unreleased]

### Fixed — the two default engines disagreed about what a token is

The shipped default runs two engines and merges them, and they did not mean the same thing by
"token", by `--min-tokens`, or by the extent they reported. Four defects, all in the token bag, all
of which survived a suite that was green at 685 tests because nothing asserted any of it.

The bag held only the tokens `token_get_all()` returns as arrays, so every single-character token —
`;`, `(`, `=`, and every operator — never reached it. That is about half the program text and the
half that says what the code does: `$x = $a + $b;` and `$x = $a - $b;` produced byte-identical bags.
`DefaultStrategy` records fixing exactly this on its own side, with the same reasoning; the bag never
got the fix.

The token count it reported was the stopword-filtered overlap — the quantity the similarity decision
is made on, which is fixed by document frequency across whatever else was scanned and is therefore
not a property of the clone at all. It is printed as `tokens` by three of the four writers. Against
the tokens the blocks actually span it ran to a median of 0.04 on symfony-console.

`--min-tokens` gated the two blocks rather than what they share, so a pair could clear the floor
twice over and share far less: symfony-console reported three unrelated sites as one clone on 61
shared tokens under a floor of 100.

And the extent began at the `function` keyword while the bag begins after the brace, so every site on
a PSR-12 corpus claimed a line the matcher had never compared.

**What it cost.** Fixing the first and third move detection in opposite directions — including
punctuation roughly doubles a block's token count, so blocks that never cleared the floor now do,
while the shared floor removes pairs that never should have. Measured on the injected-clone study,
the bag's recall over the guaranteed region goes from 82.8% to 94.8%, against Rabin-Karp's 98.3%.

### Changed — the token bag never takes the normalized view

A bag is order-free, and normalization folds every identifier to one placeholder and every literal to
another. Together those erase what tells one data table from another, so two tables of the same shape
become the same multiset. Order is what keeps the contiguous matcher's tables apart, and the bag has
thrown order away.

Measured by two blind raters at Cohen's κ 0.797 on symfony/string, the corpus where the data tables
live: the raw view scores 0.857 and 0.857, the normalized view **0.200 and 0.133**. Twelve or
thirteen of fifteen findings were false, and every one of them was asserted — the `table` stratum
demoted none, so nothing downstream was catching it. Both raters described the same cause
independently: a French pluralization word list matched against an unrelated string-casing data
provider, and two halves of one precedence map matched against each other.

Removed rather than defaulted away from. A configuration whose measured precision is 0.13 is not a
choice a caller should be able to make by accident, and leaving it selectable would have kept it in
the benchmark's variant list as though it were a real alternative. What it costs is the clone that is
both renamed and reordered.

### Fixed — findings that asserted more than anything had verified

Three defects of the same family: a report claiming something no measurement supported.

A Rabin-Karp occurrence named a first copy it had never been matched against. A run's anchor is the
window table's entry for its *first* window, so the hash guarantees the first `minTokens` tokens
agree and says nothing past that; the run then continues over windows registered elsewhere, and the
guard in `scan()` catches a different registrant *file* but not a different *offset in the same file*.
Every divergence measured began past the floor — at token 129, 208, 156 and 115 of runs of 245, 314,
166 and 146.

A clone class named sites that nothing had compared to one another. `Standard.php:444-467`, `:479-500`
and `:510-533` were reported as one **exact** class in which no pair of the three agreed — 0 of 166
tokens, 0 of 166, 17 of 166 — while each had a real duplicate elsewhere in the same file. That is not
a misstated extent; the finding was invented.

And the token bag reported its findings as exact clones, which is the one thing an order-free engine
cannot establish. They are now reported as `[reordered]`, which is a different claim from
`[inconsistent]`: the first says the material is all present in another order, the second says the
copies diverge, one patched and its sibling not. Nothing had ever read `isReordered()`, so the flag
existed and no format showed it.

### Fixed — numbers that outlived the findings they described

Coverage is charged as clones arrive and was never charged again when one was removed. `settle()`
removes several: 4 of php-parser's 41 findings, 14 of symfony/string's 55, 9 of symfony-console's 97
— and the duplicated-line total did not move by a line, while the percentage the report prints is
built on that number.

Coverage is now recomputed from the survivors, and what a run removed is reported rather than kept
quiet: readings dropped as already described, and findings dropped as unverified, on their own lines
and in the JSON summary. Both counts survive the passes that rebuild the map, which suppression and
the coherence clamp both do.

### Fixed — a function body was recorded as including its opening brace

Asymmetric, and contrary to what `Facts\RegionStructure` promises. It stayed invisible until a
reported extent was compared against it: a match runs from the first token *inside* the body, so
every site in every corpus sat exactly one token after the recorded start — 239 of symfony-console's
368 sites at exactly `+1`.

That constant was being read as drift, and reported as "0% of sites start at a function-body start".
Against the corrected boundary it is 20% on php-parser, 26% on symfony/string and 60% on
symfony-console, and the snap the project had refused costs 33.5–69.5% outward and 25.4–33.6% inward
rather than the 37% and 44% on file. No detection changes.

### Changed — extents are stated in whole source lines

A match is made in tokens and printed in lines, and the two disagree at the edges: a boundary fell
mid-line on 61% of php-parser's sites. The report *claimed* a range it had not matched while
*displaying* one it had. The extent is pulled inward to the lines it wholly covers — never claiming a
token that did not match, and the direction that separates two runs abutting on one line.

The gate stays on the run the engine matched, so no finding is lost to a presentation decision.

### Changed — one normalization value instead of two booleans

`fuzzy` and `typeAnchored` were four combinations for three behaviours, and the fourth was a silent
duplicate: normalization ran when either was set while the normalizer read only the anchor, so
`fuzzy` did nothing whenever type-anchoring was on — which is the default. Measured on three corpora,
`--type-anchored` and `--raw --type-anchored` produced byte-identical clone sets.

The three CLI flags are unchanged and each now sets one value. `--fuzzy` remains, measured as
dominated, because deleting it would delete the E2 comparison that is the evidence for type-anchoring
being the default.

### Added — `--min-confidence` and `--hidden`

Every finding already carried a confidence, its strata and an acknowledgment, and nothing consumed
them. The threshold partitions the report into shown and held back and removes nothing: the whole set
is still counted, still stratified, and still gates the exit code, and the report always says how many
were held back and that `--hidden` lists them.

Unset by default, which is the M5 pre-commitment standing rather than being overturned — no rule
derivable from this project's labels reaches the 0.80 bar by *silencing*, which is an argument against
the tool hiding unasked rather than against a reader choosing to. No threshold is recommended, because
none has been calibrated against a rated pool.

---

## [2.0.0] - 2026-09-13

### Added — one place for everything the tool says, and twenty-eight languages

The wording was spread across five classes, so a phrase could not be reviewed beside its neighbours
and rewording one meant finding it by grep. Every sentence now lives in `locale/`, keyed by what the
message does to its reader — `refuse`, `warn`, `notice`, `report`, `explain`, `label`, `advise`,
`help`, `document` — and the code asks for it by key. `docs/localization.md` carries the conventions;
the locale files carry strings and nothing else, so a translator opening `locale/fr.php` reads French
rather than a rationale.

**What reading them together exposed.** This was expected to be a mechanical move and was not. A
dozen refusals had been living under six groups keyed by the class that threw them, and had drifted
into four ways of saying "unknown value". Two mechanisms existed for plurals. A flag was rendered
three ways. Eight defaults were typed into prose as literals where the code already knew them, and
one of them — `--min-tokens` — had been wrong since the default moved from 70 to 100. None of that is
visible while the sentences live apart, and none of it required a translation to find; it required
putting them on adjacent lines.

The keys are ordered result-first inside `refuse`, which is the same lesson one level down:
`refuse.needsValue.option` beside `refuse.needsValue.setting`, not forty lines apart under
`refuse.option` and `refuse.config`. Grouped that way, two sentences turned out to be saying one
result two ways — an option "requires a value" while a setting "needs a value" — and had been for as
long as both existed.

**Counts are written label-first.** `Scanned files (1), roots (1), excludes (13)`, not `Scanned 1
file(s), 1 path(s)`. The `(s)` convention is English orthography that Polish and Russian cannot use,
and `%d file(s)` is wrong in English too. Putting the count after the label removes the agreement
problem rather than papering over it, which is why the catalogue now has no plural mechanism at all.
It had two.

**Severity is a word.** `ERROR:` and `WARNING:` prefix what they describe. Severity had survived only
as colour and stream, and `NO_COLOR`, a pipe, or `2>&1` in a CI log each erase both. Applying the
frame immediately exposed a misclassification: `report.scan.noFiles` and `noFilesAfterTriage` were
keyed as reports while going to stderr and exiting 1. They are refusals.

**Twenty-seven translations ship with English.** A translation is one file, registered by existing —
`locale/` *is* the list, so `Catalogue::available()` makes a new code legal in `--language` and in
`phpcpd.ini` the moment the file is dropped in. It may be partial: a key it has not reached falls
back to English, one key at a time, so a file is useful before it is finished.
`bench/check-locales.php` reports coverage and fails only on the two ways a locale file is always
wrong — a key English does not have, or a `:placeholder` renamed or dropped. The first is dead weight
or, worse, a typo that silently means the real key is missing; the second puts a hole in a sentence
at the call site's expense.

### Fixed — `--language` did nothing

It parsed, validated itself against the shipped catalogues, reported its default in `--help` and
`--show-config`, and changed not one byte of output. All thirty-eight construction sites asked for
`new Catalogue()` with no argument, so every one got English. Selection was wired and never connected
to the thing selected.

The language is now a process-wide default, set once — which is what it describes: a command-line
tool writes its whole output in one language, chosen by whoever ran it. The alternative was threading
a catalogue through those thirty-eight sites, a dozen of them static helpers with no object to hang
one on; `new Catalogue()` resolving the process default meant nothing had to be rewritten and nothing
could be missed. Three classes that held their catalogue in a static are built fresh per call
instead: the cache saved nothing at seventeen call sites and cost correctness, because whichever
caller ran first fixed the language, and the first caller runs before the command line has been read.

**The half that was hardest.** `Settings::fromArgv()` can refuse — an unknown option, an unreadable
config file — and those refusals are printed while the settings are being built, so the settings
cannot say what language to print them in. `LanguagePreference` reads what can be known first:
`--language`, `--config` and `--no-config` from argv, then `language` from the config files those
name. It is not a parser, never raises, and ignores what it does not recognise; its answer holds only
for the window in which the real answer does not exist, and `run()` replaces it the moment the parse
succeeds. Config files are read through `ConfigFile` rather than re-read, so `language = de` in
phpcpd.ini cannot mean one thing to it and another to the parse that follows. Its precedence test
earned itself at once: the first version resolved config over CLI, which is backwards, and which the
comment directly above it already said was backwards.

### Fixed — two defects the tool found in itself

Both were found by running `phpcpd .` on this repository, and neither reads as a defect in the
output: each names a real file and a real range, and a reader would have acted on them.

**A file matched against itself.** `phpcpd:4-47` against `phpcpd:4-47` — 44 lines, asserted, counted
in the total and in the percentage. A console entry point arrives from two directions: `FileFinder`
admits it because its `#!` line names php, and the composer manifest names it under `bin`. The two
spell the same file differently — the finder as the scan root spells it (`./phpcpd` under `phpcpd .`),
the manifest as an absolute path resolved against the composer.json it was read from — so `in_array()`
compared strings, both entered the file list, and the detector tokenized one file twice. Membership
is decided on `realpath()` now. This repository reports 3 clones over 179 files rather than 4 over
180, and 0.16% rather than 0.26%.

**An advisory its own remedy contradicts.** A default run ended with "further orphan findings not
shown (1) — run --orphans to review", and `--orphans` reported none. Triage runs first in a default
run and discarded one foreign-namespace file that held the only reference to a symbol, so the symbol
looked dead to the advisory riding along afterwards. Triage narrows what is *reviewed for
duplication*; it must not narrow what is *read for references*, because a discarded file still calls
what it calls. The requirement was already stated at the call site that resolves entry points for
both modes — *"or the two modes disagree about whether a symbol is reachable"* — and triage undid it
thirty lines further on.

### Fixed — what the translations taught the repository about itself

`locale/` was in no linter's finder, `en.php` included, for as long as the directory has existed. One
hand-written file hid it; twenty-two arriving at once did not, four of them with no trailing newline.

Scanning this repository, the twenty-eight translations report as one 279-line clone. That is correct
and useless: a translation's structure is *required* to match every other translation's, key for key,
or it is a rewrite. The model already distrusts the finding — it scores -1.21, the most negative
confidence this repository produces — and it still reports as asserted, because the matched extent
opens at `declare(strict_types=1)`, where the licence header matches too, and the table stratum
requires a statement-free array-literal frame. The stratum is right by its own definition; the
instrument is the wrong one here. `locale` joins `tests/fixtures` and `bench/corpus` in `phpcpd.ini`,
under the notation that already means "not program text under review".

### Added — the reporter goldens ruling S's protocol asks for

`bench/check-log-equivalence.php` shipped with nothing to compare against: `tests/fixtures/golden-logs/`
did not exist, so all 30 of its checks failed on the absence. It refuses to capture them itself,
because its own protocol puts the capture **before** the rewrite — *"a golden captured after a rewrite
proves that the rewrite equals itself"* — and that looked like a dead end.

It was not one. The three reporters still carry their upstream attribution, which is step 4 of ruling
S's protocol and comes last: `Log\Text`, `Log\PMD` and `Log\AbstractXmlLogger` have never been
rewritten. **The current reporters are the inherited implementations**, so capturing now is step 1
rather than a circular proof — and capturing from the 1.4.0 tree instead would have been wrong, since
its reporters predate `Findings` and the strata and emit output today's code could not reproduce for
reasons unrelated to any rewrite.

Thirty goldens: six scenarios — exact, gapped, renamed, strata, table, empty — in five formats each,
Text plain and verbose, PMD XML, JSON and SARIF. The unit is the reporter and not the CLI, so a
banner or a wall-clock line cannot break a golden that is not about them. The gate passes 30/30 and
fails on demand: a single byte appended to one golden is reported as `first difference at line 48`.

What this pins is today's inherited behaviour, which is what a rewrite has to reproduce. Ruling S's
reporter rewrites now have the gate they were always supposed to have first.

### Fixed — the benchmark scans what the product scans

Every entry in firefly-iii's manifest `strip` list was already in the shipped Laravel preset. Every
other corpus's was already in `FileFinder`'s defaults. The manifest was a hand-maintained second copy
of a list the tool owns, which is the drift `bcb_gate_files()`'s own docblock warns about — *"the tool
already knows what is not program text, so the benchmark asks it rather than maintaining a hand list
that drifts"* — while `bcb_gate_files()` itself never asked for a preset.

So the gates measured 1,445 firefly-iii files where an ordinary run scans 1,291. The 154 included
`database/migrations`, which the preset excludes by name and which a hand rating of a precision
sample had already flagged as a family of false positives — findings no user of this tool can see.

`bcb_gate_files()` now detects the preset the way the product does, and the manifest keeps only
`wp-content/uploads`, which nothing else covers. firefly-iii is the one corpus that detects a preset,
so it is the only one whose numbers move — and the recall gate is unmoved at 710 of 710 across the
six, which was the risk worth checking before anything else.

### Fixed — the benchmark corpus is what its manifest says it is

`bench/manifest.json` declares a `strip` list per corpus. `bench/fetch.sh` read `repo` and `sha` and
nothing else, so the list did nothing. `bcb_files()` carries a hardcoded default covering most of
what those entries name, which is why it went unnoticed until an entry fell outside it: firefly-iii's
`database/migrations`, sixty files of `up()`/`down()` scaffolding, sitting in the corpus and in every
number measured over it.

It surfaced from the other end. Rating a precision sample by hand turned up a family of migration
findings, and the shipped Laravel preset already excludes that directory by name — *"up()/down()
boilerplate is duplicate by design"* — so they were findings no user of this tool can see. An
ordinary run auto-detects the preset and scans 1,291 files where `--no-preset` scans 1,445.

Two things follow, and neither is a detection rule.

`fetch.sh` applies the strip list, and warns when a corpus already on disk still holds something it
strips. It does not delete anything already fetched: that would move every published firefly-iii
number without saying so, and re-fetching is a deliberate act that invalidates the measured tables.

`bench/audit-precision.php pool` says so when it detects a preset that was not asked for. A pool
built over a file set the product would never scan measures the wrong thing, and this one was —
quietly, for a whole rating round. It is announced rather than applied, because a rated pool is a
document about a corpus and silently changing which corpus would be worse than the gap.

### Changed — a table written as statements is a table

The table stratum recognised data only when it was spelled as an array literal, because the frame it
looks for has to be **statement-free**. A seeder writes the same table out of statements:

```php
$currencies[] = ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2];
$currencies[] = ['code' => 'HUF', 'name' => 'Hungarian forint', 'symbol' => 'Ft', 'decimal_places' => 2];
```

Forty-one of those in firefly-iii's `TransactionCurrencySeeder`, and no statement-free frame can
contain one of them, let alone all. So `literalTable()` could not see it and the finding was
asserted. The two spellings are one thing to a reader, and they are one thing here now: a **literal
append** is `$name[] = <literal>;`, and a **run** is consecutive appends to the same array. One call,
one variable or one constant on the right and it stops being data — the scope names what the code
*is* and does not measure how literal it is.

**The identity is the run, and that is the whole of the second half.** `RegionStructure` already
argues it for arrays: *"a table matching itself ... its second run is not another copy anyone could
edit out, it is the regularity that makes it a table"*. Two sites in two **different** runs are not
in scope, because the M5 rating round called copied seeder-like tables genuine duplication — half of
what it vindicated in the demoted stratum was that shape.

Ruling H's dropper is deliberately left alone. It removes a finding outright rather than demoting it,
and on M5's evidence silencing seeders would be wrong.

Pre-registered before it was measured: the currency-seeder finding should move from asserted to
table, and nothing else should move. Over a 60-finding sample of firefly-iii, drawn identically
before and after, **exactly one finding moved and it was that one**.

### Changed — a route file is recognised as one, and only demoted against itself

Stage D2's registration stratum fired on **no file in any benchmark corpus**. `Facts\FileRole` asks
whether a strict majority of a file's top-level statements are registration expressions, and a
`Route::group(…, function () { … })` was not one — so a route file written entirely with grouping
wrappers scored zero, not a minority. firefly-iii's `routes/web.php` is sixty groups of sixty-one
statements.

That was not a contradiction with the design. `FileStatementsTest` asserted three registrations of
four over three plain calls and one wrapper, and the majority carried the file; the rule works for as
long as a route file has a majority of *ungrouped* calls. It is one of the two ordinary ways to write
Laravel routes that has none.

**A closure passed as an argument is now read as a value.** The scan already kept the holder's tokens
together — "splitting there would leave two half-statements where the source has one" — and then
marked it a block anyway. Three places had to learn the same sentence: only a brace closing inside
another brace makes its holder a block; the shape test steps over the closure header the scan left
behind; and a closure's parameters are not the statement's dataflow, which alone had made 135
independent breadcrumb registrations read as one coupled procedure.

**And a wrapper counts only for what it holds.** A value can hold anything, so the closure is asked
the same question recursively: a wrapper around a route list is a registration, a wrapper around an
assignment, a branch or a loop is not, and nothing but the body decides. No constant, no size below
which a closure is forgiven. On `routes/breadcrumbs.php` the second clause refuses 26 of the 135 the
first clause accepted; the majority carries the file at 109 of 136.

**The stratum now demotes a registration file only against itself.** Ruling H keeps one span-level
discriminator alive by asking what a thing is — "a table matching itself ... its second run is not a
copy anyone could remove, it is the regularity that makes it a table" — and the same question
separates two cases this stratum had treated alike. A route file repeating itself is a route file;
two *different* route files agreeing is the same block written into two surfaces, and someone may
want it gone. Measured on firefly-iii: 112 self-repeating against 1 crossing for the unified engine,
16 against 5 for the token bag, and every one of those six crossings is `routes/api.php` against
`routes/web.php`.

`PresentationTest::twoRouteFilesAreDemotedByTheirRole` asserted the wider reading over two
byte-identical route fixtures. It is rewritten, not deleted, and its fixtures are kept as the paired
negative they turned out to be; `routes_self.php` is new and carries the demoted case. Live on
firefly-iii with the unified engine: **602 asserted, 140 demoted** (table 28, registration 112),
where registration was 0.

Nothing is suppressed. A demoted finding is still reported, still exported and still gates the exit
code exactly as before.

### Fixed — a keyword used as a method name is a name

PHP allows every reserved word as a member name. After `->` and `?->` the lexer knows it is looking
for a property and hands back `T_STRING`; after `::` it does not, and the keyword arrives as itself.
`Foo::class` reaches the tokenizer as `T_CLASS`, and `Breadcrumbs::for(…)` reaches it as `T_FOR`.

`FileStatements` read the second as a `for` loop. Its shape test — "does this statement read as one
call whose value is discarded?" — refused any statement carrying a control-flow keyword, and knew
about `T_CLASS` alone, with a comment calling it *"the one member of the list with an expression
reading"*. It was not; it was the one that came up first. The callee walk did not know even that
much, so `Breadcrumbs::for` stopped being a call at the `::`.

Found by asking `Facts\FileRole` about a real breadcrumb file, where it scored **0 of 136** — not a
minority, zero. The position after `::` is what decides now, not the word, and two tests pin both
halves: a keyword used as a member name reads as a call, and the same keyword opening a statement is
still the construct it names.

No finding moves on any benchmark corpus by itself — `symfony-console` reports the same 52 and 122
clones before and after — because the file it was found on needs a second, separate change to be
seen as a registration file at all. That one is a decision rather than a defect and is not taken
here; `bench/probe-registration-shape.php` measures it.

### Fixed — an occurrence is reported at its own length, not at its class's

A clone class carries one token count and one line count, measured on the site it was led by. Every
other site was reported at those, and copies do not have to agree on them: a class merged from
several near-identical files is as long as its shortest lead, and anything asking "how much do
*these two* sites share" got a number answering a different question.

`CodeCloneFile` already carried a per-occurrence line count for exactly this reason, and gained a
per-occurrence token count on the same terms — null where the strategy did not measure it, so a
report from an engine that does not is unchanged. `CodeClone::toArray()` emits both when present.
Two callers were computing an occurrence's extent by hand instead of asking it — `CloneSuppressions`
and `bench/check-superset.php` — and both now go through `CodeCloneFile::lastLine()`, which is where
the fallback rule was always meant to live. That open-coding *was* the bug: the rule was written
three times and only one copy learned that occurrences carry their own measurements.

`bench/check-superset.php` reported seven unexplained disagreements on phpunit for no better reason.
Six were the `Triggered` event family, where the class's lead spans 129 tokens and the pair the
baseline names shares 137; the seventh was a printer-test pair whose second site sits 124 lines past
where the class's own length said its extent ended, so the check could not see that it was covered
at all. Subsumption on phpunit is now 3/3 with **0 unexplained** — 13 pairs at the same length, 221
longer, and 4 shorter, all four of which are the baseline over-reporting a run the source does not
support.

### Fixed — a gapped clone whose two flanks overlap by a token is no longer lost

`ChainBuilder` required a chain's predecessor to end before its successor starts in **both** files.
Anchors are extended maximally and independently of one another, so the two flanking a divergence
routinely overlap by a token or two on one side: the right flank's leftward extension runs back past
the point where the left flank stopped, over material that happens to agree there as well. Such a
junction was therefore not merely penalized, it was unavailable — the chainer kept whichever single
flank was longer, and where that flank was under `--min-tokens` the clone was not reported at all.

Found by `bench/run-recall.php`, which stood at 709 of 710 inside the winnowing guarantee. The pair
it missed was a 7-token statement deleted from a 70-token function in `symfony-console`: two anchors,
A[0,34)↔B[0,34) and A[39,70)↔B[32,63), overlapping by two tokens in B. Seeding was never the
problem — both flanks are seeded well above S = 25 — and the pair sits inside the guarantee by every
clause of the gate's own definition.

The anchor set is now augmented rather than rewritten: each anchor keeps its own geometry and gains
a copy whose start is advanced past everything a legal predecessor could already claim, and the
recurrence chooses between them on score. A trimmed copy may not start a chain, only continue one —
standing alone it is an anchor needlessly shortened, and on phpunit's `NoticeTriggered` family
allowing it cut 137 shared tokens to 129. `chain()` now returns the geometry it used, so
`CloneClassifier` reads the reading that was chosen instead of reconstructing it from untrimmed
anchors.

Recall is 710 of 710 at the default sample and 478 of 478 at the smaller one, both 100%.
`bench/check-chaining.php` was extended to the same contract — its brute force enumerates three
states per anchor (left out, taken whole, taken trimmed) rather than two — and agrees with the sparse
DP on all 6,000 comparisons, tie order included. Subsumption against Rabin-Karp is unchanged on
php-parser and gains ten clones on phpunit with no new length disagreement.

### Fixed — `bench/check-determinism.php` reads the project's own `phpcpd.ini`

The CLI half of the determinism check inherited `./phpcpd.ini`, which excludes `bench/corpus` so that
the tool can scan itself. Pointed at a corpus, the CLI therefore found no files while the in-process
half, which never reads the file, scanned it — and the check compared two empty reports. It now
passes `--no-config`. A benchmark that changes answer with the working directory is measuring the
working directory.

### Added — `Phpcpd::supports()`, so an embedder can ask instead of guessing

A consumer that binds against more than one version of this package cannot ask which one it got
without parsing `Version::NUMBER` or probing member by member with `method_exists()`.
`Phpcpd::supports(string $capability): bool` is the one question it can ask instead.

It ships in 2.0.0 rather than in the minor that first needs it, because a probe released later can
never gate the release that introduced it: every capability added in 1.6 would be undetectable by a
consumer that also supports 1.5. That cost is permanent and compounds per release; the cost of
shipping it now is one method.

**The parameter is a string, deliberately not an enum.** An enum argument cannot name a case the
installed version has never heard of, which is exactly the situation the probe exists for. An
unrecognised capability returns `false` rather than raising, so a consumer written against a later
vocabulary degrades instead of fataling.

**A capability answers "can I call this", never "will it be populated".** `line-spans` is true
because `CodeCloneFile::$numberOfLines` exists to read, not because every occurrence carries one —
Rabin-Karp measures neither of its occurrences and says so with null. There is no algorithm
argument: the algorithm is chosen at `detect()` time from configuration an embedder typically
passes through unvalidated, so a probe that had to know it could not answer.

Recognised in 2.0.0: `default-excludes`, `divergences`, `line-spans`. `classification` is
deliberately not recognised — the engine computes a Type-1/Type-2/gapped distinction and
`CodeClone` currently discards it, so there is nothing to call. A capability string that predates
its surface is worse than no string. It becomes true when a clone can be asked its type.

### Added — `Phpcpd::detect()` takes `$defaultExcludes`

Matching `Orphans::detect()`, which has taken it since it shipped. Also fixes a latent defect: the
facade never passed the resolved setting to `FileFinder`, so `no-default-excludes` was inert on
this path.

### Fixed — duplicated lines are counted as coverage, and each copy over its own lines

A duplicated-line percentage is a coverage measure and is now computed as one: the source lines
belonging to at least one clone, over the lines scanned. That is the clone coverage of ConQAT and
Teamscale, and it is bounded by the size of the code by construction.

What it replaces summed a per-clone contribution, and was wrong in both directions. It double-counted
a line two overlapping clones covered — a single file could be reported as 440% duplicated — and it
undercounted where a longer clone began on a line a shorter one had already claimed, reporting 20
lines where the union is 60. Both errors scaled with how finely duplication was grouped, so the
metric rewarded an engine for fragmenting a clone class and penalised one for grouping it correctly.
`averageSize()` is the mean size of a clone rather than duplicated lines per clone, so it can no
longer exceed `largestSize()`; a run that once printed an average of 435 beside a largest of 145 is
no longer expressible.

Each occurrence is measured over the lines it covers, rather than every occurrence being charged the
length of the one its class was led by. Copies need not agree on that length: the matchers compare
significant tokens, and comments and blank lines are not, so two token-identical copies can span very
different numbers of source lines. On the phpunit corpus 467 of 632 unified clones and 174 of 202
token-bag clones have an occurrence whose true span differs from its class's.

**This changes reported totals.** Against phpcpd 6.0.3 the shipped default differs by 0 to 0.8% —
the clones found are identical, on all six corpora, at the same locations and lengths, and only the
accounting differs. Under `--algorithm=unified` the change is large, about 50% on phpunit, because
that engine reports many overlapping clones and the old scheme counted the overlaps repeatedly. A CI
threshold pinned against an earlier build may need adjusting; the figure can no longer exceed 100%.

### Fixed — a built PHAR is not source, however it opens

`FileFinder` scans an extensionless file when its `#!` line names php, so a project's console entry
point is not the one file a scan never sees. A built PHAR opens with the same line. PHPUnit commits
five of them under `tools/`, and they carried 350,480 lines — 63.9% of every line the tool counted
for that project — into the denominator of every percentage it reported, taking its duplication from
4.64% to 1.67%. `__HALT_COMPILER();` is what makes a PHAR a PHAR and is what distinguishes them;
`artisan` and `bin/console` are unaffected.

### Fixed — a generated banner that announces the file in prose

nikic/php-parser's generated parsers open with `This is an automatically GENERATED file, which should
not be manually edited.` and were scanned as ordinary source. No marker in `GENERATED_MARKERS`
occurs in that sentence: `auto-generated` is hyphenated, and "should not be manually edited" is not
`do not edit`.

The obvious repair does not work, and measuring it is what showed why. Adding `automatically
generated` to the marker list fails twice over — the marker sits four words in, behind "This is an",
so the anchor rejects it; and widening the anchor to admit a lead-in makes the phrase fire on prose,
because unlike the three existing markers it is ordinary English. That variant drops WordPress's
`wp-includes/blocks/post-excerpt.php` on the line `* automatically generated and user-created
excerpts.` — a continuation `*` is an anchor, and a live source file leaves the scan silently. That
is worse than the miss it fixes, and is the exact failure the anchoring rule exists to prevent.

So the phrase is matched as a banner *form* rather than as a marker, and counts only where it
announces the file: introduced by "this is a" / "this file is" / "this file was", or naming the
artifact it produced. Swept over the six benchmark corpora, this adds exactly `Php7.php` and
`Php8.php` and nothing else.

**This changes what a default scan reads.** `bench/corpus/php-parser` now yields 340 files rather
than 342, and those two were 82% of that corpus's unified runtime, so every php-parser figure in
`docs/research/deferred-engine-work.md` is pre-fix until it is retaken on a machine that can be
timed.

### Added — measured numbers are rendered into documents, not typed into them

A number typed into prose is true once. The speed claim above, the wall-clock table, and a corpus
file count each rotted in place and each was caught by hand, late. So documents no longer state
numbers; they reference them, in the syntax of [sigilmd](https://github.com/lucianofedericopereira/sigilmd)
reduced to a fact table and made re-enterable:

    a default scan finds <!-- [[ $scan.php_parser_files ]] -->340<!--/--> files

`bench/collect-facts.php` measures and writes `bench/results/facts.toml`; `bench/sigil.php` renders
the references; `php bench/sigil.php --check` exits non-zero when a document disagrees with the
facts, naming the reference and both values. HTML comments render as nothing, so a reader sees only
the value.

The gate proves every value a document *declares* is current. It cannot prove a document declares
everything it should — a number typed as bare prose carries no reference and is invisible to it, the
same way an unasserted behaviour is invisible to a test suite.

### Fixed — the wall-clock gate no longer reports the power policy as an engine failure

`bench/check-walltime.php` on a battery-throttled CPU reported unified at 0.35x and called it a
FAIL. Nothing about the engine had changed; the clock had dropped from 3.9 GHz to 800 MHz. The rule
against this was already written down — "state the machine and its power state beside any wall-clock
number, or do not publish the number" — and was enforced by memory alone.

`bench/power.php` now reads the state before any timing is taken and names each reason a number
would be meaningless: a power-biased `energy_performance_preference` (not the governor — `powersave`
with EPP `performance` measures fine, so reading the governor alone passes a machine that cannot
deliver), running on battery, a clock far below the advertised maximum, or a load average showing a
co-tenant process. The gate declines out loud and asserts nothing rather than emitting a verdict the
hardware caused; `--force` measures anyway and says so. The predicate is pure and tested against
synthetic machine states, because a test for this rule must not depend on the machine it runs on.

### Fixed — a consistent rename is a Type-2 clone whether or not the name is qualified

PHP 8 stopped spelling `App\Foo` as three tokens and bundles it into one `T_NAME_*` token, which
`TokenNormalizer` never learned. Every qualified reference kept its concrete text in the normalized
view, so under `--fuzzy` a rename was detected on unqualified code and missed on the rest — which is
most of modern PHP. On `php-parser` the unified engine's line coverage rises from 8,236 to 9,387.

### Fixed — a short candidate no longer suppresses the long one containing it

`alreadyReported()` asked whether a candidate was already covered and answered with a test that
measures the shared span against the *shorter* of the two ranges, which made a directional question
symmetric: a raw fragment covered all of itself inside a longer normalized candidate, so the longer
one was dropped as a repeat and the duplication only it could see went unreported. The covering
question is now directional. Present since before the normalization change above, which only
lengthened normalized matches enough to reach it.

### Fixed — the token bag reported every clone one line short

`endLine` is the line of a block's last token, so the span is inclusive of both ends;
`endLine - startLine` is a difference, not a count, and reported a two-line block as one line.

### Fixed — detection depends on which files were given, not the order they arrived in

The engines are order-sensitive by construction: Rabin-Karp records the first occurrence of a window
and reports later ones against it, and the token bag compares each block against those already seen.
Reversing a file list therefore drew a different set of pairs. `FileFinder` sorts what it finds, so a
directory scan was stable and the defect stayed hidden — but an embedder, a test, or an explicit
argument list assembles its own list. `Engine::detect()` now sorts what it is given.
`bench/check-determinism.php` goes from 7/10 to 10/10.

### Fixed — `bench/fetch.sh` pointed at a URL that 404s

`sebastianbergmann/phpcpd` is archived and its release assets are gone, so a cold benchmark setup
could not complete. Re-pinned to `phar.phpunit.de`.

### Changed — the speed claim says what was measured

The README and this file carried "the ratio improves with corpus size". Measured across all six
corpora in one sitting, that is not what the ratio tracks. The unified engine is at 0.84x the default
pipeline on WordPress at 1,848 files — faster than the two engines it replaces — and at 11.9x on
PHPUnit, which is 45% larger. Both corpora it loses on carry large near-duplicate regions: 82% of
`php-parser`'s runtime is two generated parser files that are near copies of each other, 18% of its
lines, and PHPUnit's cost concentrates in one directory of some ninety near-identical test methods.
The cost tracks self-similar content, not scale. The earlier figures were within-application
sampling, and generalised into a claim about corpus size that cross-corpus measurement does not
support.

Since measured, the two php-parser files named above have stopped being scanned at all — they carry
a generated banner the file finder now reads, which took php-parser from 12.74x to about 5x. The
table itself is not restated here: `docs/research/deferred-engine-work.md` renders it from
`bench/results/walltime.tsv`, so it moves when the measurement does and this paragraph cannot
contradict it. What has not moved across every re-measurement is the shape — the cost tracks
self-similar content rather than scale, and WordPress, the largest corpus but the least
self-similar, is the one that passes.

### Known issues

- **`bench/check-walltime.php` fails every corpus whose verdict it can determine.** It asks the
  unified engine to run at or below the default pipeline, and it is missed wherever a corpus carries
  large near-duplicate regions — by 3.4x to 12.5x on five of the six. WordPress was recorded as a
  pass at 0.97x and, re-measured on a cooler machine, reads 1.13x. The engine did not change: the gap
  the gate judges there is ~13% and that corpus's run-to-run spread is ~15%, so its verdict is
  undetermined rather than either way. The table now records `defaultspread` and `unifiedspread` so a
  reader can see which rows are decided. The cause is understood and the fix — a
  candidate-generation guard for self-similar input — is deferred, because it changes detection
  semantics and needs a recall study rather than a subsumption gate to clear it. See
  `docs/research/deferred-engine-work.md`, whose table is rendered from `bench/results/walltime.tsv`
  rather than transcribed — read the per-corpus ratios there rather than from a sentence here.
  PHPUnit is the worst case and WordPress the one that passes.
- **A clone's reported extent can overstate by one line at a boundary.** Two disjoint token ranges
  can share one physical line, so a site's last line may equal the next site's first. Display only:
  the coverage union counts such a line once, so no total is affected. Quantified: 42 site pairs on
  phpunit, 4 on firefly-iii, 0 on symfony-console — disjoint in tokens and touching on one line.

### The 2.0.0 release, in one place

The entries below are the change record, one per change, in the order the changes landed. This is
what they add up to.

**Two components were removed, each on a condition fixed before the evidence arrived.** The
ConQAT-derived suffix-tree engine is gone, and with it the package's second licence — phpcpd-next is
single-licence BSD-3-Clause, and the ruling-S inventory of inherited files falls from 18 to 9. The
Stage 0 fishiness classifier is gone, on the pre-registered condition it shipped under, and Stage 0
is now proof on every rung. Neither removal was a change of mind: both conditions were written down
first and then applied to the numbers that arrived.

**The default engine did not change, and that too was decided by arithmetic rather than by
preference.** The criterion for flipping the default to the unified engine — both blinded raters'
precision at 0.80 or above on the findings the tool *asserts* — was fixed before the pool was drawn.
It was applied: 0.639 [0.476, 0.775] from both raters at κ = 0.811, not met, and the bar was not
moved to accommodate the result. `phpcpd <dir>` still runs Rabin-Karp and the token bag, merged.

**Stage 0 now runs on every scan, and discards what it labels.** On the four library corpora that
changes nothing at all; on the two applications it removes between a quarter and a half of what the
tool used to report, because that much of it was duplication inside code nothing references. The
posture that judges without acting is `--triage-posture=label`, and its own no-op property is still
asserted byte for byte against `--no-triage` rather than argued for.

**The recall-versus-speed trade, stated in both halves, because a release note that gives one of
them is not a release note.** The unified engine now recalls **710 of 710** pairs inside the
winnowing guarantee at the harness's default sample, and **478 of 478** at the smaller one — the
guarantee is met exactly, not approximately, and the residual that stood open through the previous
release is closed. That was
bought at a measured cost: against the merged default pipeline it runs at **1.37× to 1.86×** at the
largest sizes measured, where the project's own target is 1.5×, so the *level* is missed. What holds
is the *shape* — the ratio still improves monotonically with corpus size, which was always the
property that mattered, since a ratio that degrades with size is a tool that stops working on the
codebases that need it. The trade was put to the project owner as a release decision with both
numbers on the table, and accepted.

### Changed — the wall-clock sweep measures Stage 0 as its own line

`bench/check-walltime.php` gains a `triage (label)` configuration: the shipped default's Stage 0, in
the shipped posture, over the same file set the engines are measured on. From 2.0.0 every default run
pays it before an engine sees a token, so a sweep that measured only engines would be reporting a
configuration nobody runs.

It is a line rather than an addend inside `default`, for the same reason the presentation tier is:
the M3 and M4 ratios were taken without it, and silently changing what `default` means would break
every comparison against them. Add the line to whichever engine line you care about to get what a run
of that engine costs today; the difference between the two readings is Stage 0's price, stated rather
than absorbed.

### Added — Stage 0 triage, discarding by default

Stage 0 now runs on every scan. It asks one question of each file before anything is measured — is
this program text? — and answers it on four rungs that **prove** rather than estimate: `derived`
(outside the file set your own default excludes leave), `unwired` (nothing references anything it
declares), `shadowed` (every name it declares is autoloadable from elsewhere), `foreign` (it declares
only namespaces no `composer.json` above it claims, from outside every directory they wire).

**The default posture is `discard`: a labelled file is not scanned.** This moved late in development
— it was `label` for most of 2.0.0's life — and it moved because the two postures were measured
against each other on all six benchmark corpora rather than argued about.

On the four library corpora the postures are indistinguishable. Not one finding differs on
`symfony-string`, `php-parser`, `symfony-console` or `phpunit`; between 0 and 34 files of 2,689 are
labelled, and none of them carried a reported clone.

On the two applications the difference is the whole point of the stage. `firefly-iii` has 441 unwired
files out of 1,291, and discarding them takes it from **398 clones to 168** and from **9.9s to 4.8s**.
WordPress has 289 unwired and 215 vendored-foreign out of 1,848, and goes from **437 to 321** and from
**44.3s to 31.9s**. Better than half of what the tool reported on a real Laravel application was
duplication inside code nothing references — which is Stage 0's own argument, that analysing unwired
code measures a corpus's housekeeping rather than its duplication, measured again on the way in.

**The cost is stated because it is real: removing a file can only ever lose a finding.** A rung that
is wrong about one file takes genuine duplication with it and nothing says so. Three things hold that
in check — every rung proves rather than estimates, the removal announces itself and counts its
reasons, and `--explain` names the evidence per file so a removal can be argued with. `label` remains
one flag away for anyone who wants the stage's judgement without its consequences, and its own
property is still asserted: `TriagePostureTest` runs the CLI under `--triage-posture=label` and under
`--no-triage` over one fixture and compares the JSON reports byte for byte, then runs it a third time
under `discard` to show the comparison is capable of failing.

The stage announces itself, the way preset detection does, and names the flags that change it:

```
Triage: 3 of 56 files removed · unwired 2 · shadowed 1
  (--explain lists each file and the evidence for or against it)
```

**One behaviour change to call out for anyone already passing `--triage`:** `--no-triage` is new, and
is how a run opts out of the stage entirely. `--triage` alone still discards, as it did before the
posture existed.

`--orphans` runs skip the stage entirely, in either posture. That report already names every unwired
file with its evidence — and Stage 0's first rung *is* the orphan machinery, so discarding would
remove precisely the files the report was asked about and answer emptily on a tree full of them.
Pre-filtering the input by the answer is not a way to give it.

`--show-config` gained `no-triage` and `triage-posture` rows, because a default that changes what a
run does has to be visible in the report of what is in force.

**`demote` and `label` now remove the same set — nothing.** That is a consequence of the classifier's
retirement in this same release: `demote`'s only removal-relevant difference from `discard` was that
the estimator tagged where the proof rungs dropped, and there is no estimator. `demote` is kept
because it was selectable before this release and pipelines name it; which of the two names survives
is a question for the next milestone rather than one settled here.

### Removed — `--algorithm=suffixtree`, and with it the package's second licence

The ConQAT-derived approximate-clone suffix tree is gone: `SuffixTreeStrategy` and the nine files
under `src/Detector/Strategy/SuffixTree/`, the `NOTICE` section attributing the eight of them that
carried the upstream header plus the strategy, and the Apache-2.0 clause in `LICENSE` — all in one
commit, because a licence notice that outlives the code it covers is a notice nobody can check.
**phpcpd-next is single-licence BSD-3-Clause from here on.** `bench/profile-suffixtree.php`, which
profiled that engine's edit-distance DP and nothing else, went with it.

Also removed: `--edit-distance` and `--head-equality`, which were that engine's knobs and no other's
— `README` documented both as *"suffixtree only"* — along with their `Settings` properties, their
`StrategyConfiguration` fields and their entry in the cache fingerprint. `--min-similarity` stays;
TokenBag reads it.

The deprecation-release convention is not being skipped so much as scoped. Defaults and public APIs
get a deprecation cycle; a hidden, opt-in research flag that needs 199 seconds for 50 files of a real
application, against the unified engine's 0.63, protects nobody by staying one more release.

**What is lost, stated as a number rather than waved past.** On the mutation-injection recall curves
(`bench/run-recall.php --sample=40 --min-tokens=50`) the tree led every engine on the *pure-insert*
family: 100.0 / 100.0 / 85.6 % at the three insert densities, against the unified engine's
97.3 / 79.8 / 70.1 % when that comparison was recorded. The tree's column cannot be re-run now, so it
stands as measured. The unified engine's has moved since — the same instrument at the same settings
now reads **100.0 / 99.2 / 85.6 %** — so the gap that justified keeping the tree is down to one
operator and 0.8 points. Both halves are printed because a limitation note that refreshes only the
flattering column is not one.

`[inconsistent]` is unaffected: it is the report's own flag for a diverged clone, not the tree's, and
`--algorithm=unified` sets it *and* names the token range that diverged on each side, which is what
the tree's boolean never did. The `bench/results/` files from the E2 and E3 runs that included the
tree are left exactly as they are — history is recorded, not deleted — and `bench/run-e3.php` now
defaults to `unified` with a note saying which engine the published run used.

### Changed — MODERNIZATION.md's ruling-S inventory, and it is a measurement now

`src/` is **92 files and 18,631 lines, 90.2 % of them original to this project** by the file unit
ruling S counts in, 93.5 % by lines — up from the 83.0 % / 88.3 % the same instrument measures over
`src/` at the previous commit, and up because nine attributed files left with the suffix tree.

The document's own prose was stale before this: it claimed 19 attributed files and 85.5 %, where the
tree held 18 and 83.0 %, because `CLI/Application.php`'s header was flipped to this project's own and
the count was never re-run. The number is now taken from `bench/check-provenance.php` and the document
says so, so the next reader re-runs the command instead of trusting a paragraph.

Group A — the Apache-2.0 group — is empty. Nine BSD-3-Clause files remain before ruling S's MIT
precondition is met.

### Removed — the fishiness classifier is retired, and Stage 0 becomes all proof

`--triage`'s fifth rung estimated. The four above it prove: a file is *derived* by the project's own
default excludes, *unwired* because nothing references anything it declares, *shadowed* because every
name it declares is autoloaded from elsewhere, *foreign* because no manifest above it claims the
namespaces it declares. The fifth asked a trained naive-Bayes model whether what was left looked
fishy, and demoted the findings inside a file it tagged.

It shipped under a pre-registered condition — decided at the next rating round — and that round has
now decided it. Of the seven pooled findings the tag demoted, five were **already** demoted by the
`table` tag's proof test, which asks a question about the code rather than about a probability. The
two the tag reached alone were both rated **genuine duplication by both raters**. So its correct
demotions were redundant and its exclusive contribution was error, and no threshold on that model
changes that arithmetic.

Deleted: the model, the classifier, its feature extractor and its include index, the trainer
(`bench/triage.php train`, with its label-set arguments and its margin derivation), the
`--triage-classifier-discard` flag and its setting, and the `fishy` demote stratum. The label set the
model was trained on is preserved outside this repository; nothing in the tree reads it. Findings now
carry two demote tags, `table` and `registration`, and both of them prove their case.

This is the lifecycle the stage was designed around rather than a reversal of it: the estimator
scouted a question nobody had a proof for, and what it taught was promoted out of it — the `foreign`
rung exists because the model was measured on that class and separated one file in 117, and the
literal-share scale it published is the one the confidence ranking still buckets on. It retires when
the proofs cover what it covered.

`Presentation\Strata`'s every-site composition rule loses the test the classifier tag used to carry
it on, and gains a better one: a route file's registrations copied into a class method, where the
stratum holds on one site and the finding therefore stays asserted — the case the rule was written
for, now pinned by a fixture instead of by prose.

### Fixed — the precision pool is now reproducible, and its site labels line up

Three defects in `bench/audit-precision.php pool`, all found while drawing the M5 pool and all
affecting which findings a rater sees:

**The sample moved when nothing moved.** Findings were keyed for de-duplication on the *salted*
per-path digest, and that key also decided the pool's sort order and therefore the even-stride
sample. The salt is fresh per run, so two runs of one command over one unchanged tree produced two
different worksheets — measured at 10, 14 and 17 table-stratum findings on three consecutive runs.
The key is now the paths, which is what a finding's identity actually is; the worksheet still shows
only digests. Two runs now produce an identical sample and identical strata.

**A site's digest label could belong to another site.** The digest list and the path list were
sorted independently — hash order against alphabetical order — and then read index by index when the
worksheet was written, so a class whose sites live in two files could be labelled with the wrong
site's digest. Since that label is what `bench/relocate-worksheet.php` propagates by, a mislabelled
site is a site that relocates to the wrong file. The digests are now derived from the sorted paths,
so the two are aligned by construction.

**A stratum that reached nothing could not be told from one with nothing to reach.** The pool now
reports each stratum over the sampled worksheet *and* over the whole pool, and reports how many of
the scanned files carry the registration role — broken down by which clause of the definition
refused the rest. An empty stratum is then a result rather than a puzzle.

### Changed — MODERNIZATION.md's ruling-S inventory, re-measured

`src/` is now 106 files and 20,907 lines, of which **85.5 %** is original to this project — up from
84.4 % at M4, and up for the ordinary reason that new work is original work: M5 added the facts
layer's second half and the whole presentation tier, twelve files descended from nothing. The
nineteen attributed files are the same nineteen, and several of them grew, because a report change
lands in the reporter that already exists.

The inventory is what ruling S's MIT endgame is measured against, so it is re-counted whenever the
tree moves rather than left to drift.

### Changed — the precision audit records and scores the M5 strata

`bench/audit-precision.php pool` now computes each pooled finding's stratum through the shipped
`Presentation\Strata` — not a copy — and writes it to the companion `.key.tsv`, beside the engine
attribution. It is **not** written to the worksheet: a rater who can see that the tool has already
declined to assert a finding has stopped judging the code, which is the one thing the rubric asks of
them. The classifier tags that make up stratum D3 come from the same Stage 0 pass the pool is built
with, so the stratum a finding is rated under is the stratum a user would see it in.

`score` reports the stratified bar the M5 charter pre-registers: both strata, both raters, point
estimates and Wilson intervals, **always together**, with a per-tag breakdown so the demoted stratum
is not one opaque bucket. The pre-registered criterion — both raters' asserted-stratum point
estimates ≥ 0.80 — is applied and printed as MET or NOT MET; it is quoted rather than restated, and
scoring one worksheet says so rather than deciding anything.

A key file written before M5 has no stratum column, and every finding in it reads as `asserted` —
which is what a pool drawn before the strata existed was. Stated in the reader rather than left to
be discovered: a missing column is not a demoted finding.

### Changed — `bench/check-walltime.php` measures the presentation tier as its own line

The sweep gains a fifth configuration, `unified + presentation`: the engine's detection cost and
then the strata, the ranking features and the total order over every finding — what a user's run
actually pays now that findings are stratified and ranked.

Measured as a separate line rather than folded into `unified`, for the reason the posture-relative
gate rule gives: `unified` stays comparable with the M3 and M4 sweeps that recorded it, and the
difference between the two lines is the tier's price, stated rather than absorbed. Two lines, one
corpus.

### Fixed — ruling 7(b): the chain scorer no longer decides where its contract says it ranks

`ChainBuilder`'s recorded contract is that its score *"only ranks candidates in any case: whether a
chain is reported is decided later"*. In one narrow band it did not rank, it decided. A divergence
`d` followed by a trailing run `r ≤ d` loses its junction by arithmetic — `r − gapA − gapB < 0` — so
the chain truncates, and the `--min-tokens` floor then refuses a pair the aligner would have
accepted. The traced case lost by exactly one point: a 5-token tail across a 6-token deletion, on a
pair whose similarity is 0.882. `bench/run-recall.php --sample=60` has stood at **439 of 441** since
M4 because of it.

Outer flanks are now **proposed** to the verifier rather than dropped by the scorer: attach, verify,
and back the attachment out if the aligner refuses. The aligner holds the last word, so the 0.85
similarity invariant cannot bend — and the naive version of this change, implemented and reverted at
M4, broke exactly that invariant at 0.838, which is why it is a proposal and not a rule.

`ChainBuilder` and its brute-force oracle are **untouched**; the whole change lives in the consumer.
The scorer keeps its theorem and stops being the last word about it.

Three bounds keep it to the object ruling M's argument is about, and each was derived by
measurement rather than assumed:

- **Only where the scorer decides.** A proposal is made only when the chain's own span falls under
  `--min-tokens`. Where both readings are acceptable the scorer is doing exactly its job — choosing
  between two valid descriptions of one pair — and there is no defect to fix. Proposing
  unconditionally moves phpunit from 581 to 629 clones by swallowing shared preambles into spans the
  scorer had deliberately not taken, at 4.2 s → 159.0 s.
- **Only within extension's own reach**, one span either side of the chain. Without this, any
  colinear anchor the penalized chain deliberately dropped qualifies however far away it sits: on
  php-parser that produced 591 alignments at a mean span of 790 tokens and 14.5 s of dynamic
  programming on a corpus that scans in under a second. A distant dropped anchor is not a flank
  extension recovered, it is a competing chain element, and the gap penalty exists to arbitrate
  those.
- **Only when the attachment clears the floor**, which is the other half of the band and saves an
  alignment run to learn what `verify()` would refuse a moment later.

**Recall is now 441 of 441 at both sample sizes** (295/295 at 40, 441/441 at 60). Measured on the
bench corpora, bench walker, no preset applied:

```
                     clones        duplicated lines     files      wall-clock
  php-parser        82 →  96      11,884 → 12,430      31 → 43     0.8 → 0.8 s
  symfony/string    22 →  22       1,430 →  1,430      13 → 13     0.1 → 0.1 s
  phpunit          581 → 610     111,778 → 118,178    478 → 510    4.2 → 4.9 s
  firefly-iii      617 → 725      59,536 → 71,735     481 → 560    2.4 → 3.1 s
```

The additions are near-miss clones the floor was refusing — `Builder/Class_.php` against
`Builder/Enum_.php`, `Node/Param.php` against `Node/Stmt/Property.php`, blocks of the generated
parser tables. The Rabin-Karp subsumption gate improves with them: firefly-iii's unexplained pairs go
from 7 to 6, php-parser and symfony/string stay at 0, phpunit stays at its single known pair.
Determinism, incremental and the chaining oracle are unchanged.

### Fixed — a divergence now says whether it is inside the clone or past its edge

`CloneDivergence` gains `edge`, true for a **bounded edge divergence** (M2 audit ruling C) and
absent otherwise, so every report this tool has ever written for an internal divergence is
unchanged byte for byte.

The two are different facts wearing one shape. An internal divergence sits *between* two exact runs
and is part of both sides' spans; an edge divergence is material one copy has *past* the shared part,
and is part of neither. Once a clone class is sized by its lead member, no reader and no checker can
tell them apart from the line numbers alone — which was not a hypothetical: the probe suite's
independent similarity oracle reconstructs each side's true length from public data, and on the
symfony/string inflector pair it counted two edge divergences into that reconstruction, read a
218-token side as 197, and reported a genuine 0.864 alignment as 0.838. The engine was right and the
instrument could not see it.

The flag is computed where the fact is known — a gap outside the candidate's own span is an edge
divergence, the same test the verifier uses to decide what the aligner is responsible for — and the
oracle now skips them. That makes the 0.85 similarity invariant verifiable on clones that carry both
kinds at once, which it was not before.

### Added — `--acknowledged` / `--write-acknowledged`: a ledger that demotes and never hides

Adopting a clone detector on a large existing codebase turns up duplication a team knows about and
is not fixing this quarter. Saying so with `phpcpd-ignore` markers means editing a codebase to change
a report, and the markers outlive the decision. `--write-acknowledged=<file>` records this run's
findings into one committed, diffable file; `--acknowledged=<file>` reads it back.

It is deliberately **not a baseline in the usual sense**. An acknowledged finding is **demoted**:
still detected, still printed, still in every count, still gating the exit code. A mechanism that made
findings disappear would, over a few quarters, turn the report into a record of what nobody had got
round to acknowledging yet.

Entries are keyed to the **content of every side** of the duplication, hashed — not to a path, a line
number or an ordinal — with the sides sorted, because a clone class is a set and the engine's order
within it is not something a user should have to think about. Three consequences, all wanted: editing
either copy **expires** the entry and the finding is asserted again; moving or renaming the code
changes nothing; and an entry that matches nothing is **reported by name** so the line can be
deleted rather than accumulate. The counts print whenever a ledger is consulted, zeroes included, and
the split reaches JSON, PMD and SARIF.

The ledger has no constants — a hash matches or it does not — and it never asks anyone to write a
marker into their source. The existing `phpcpd-ignore` notations are untouched and stay: they are the
right tool when the duplication is deliberate design and the explanation belongs beside the code.

The test makes the instrument fail on demand, which this project asks of a gate: one line edited
inside the clone, in the *second* copy — the side a first-file-only key would never look at — flips
the verdict from acknowledged to asserted and produces the stale report.

### Added — confidence ranking: findings ordered by evidence, and the evidence printed

Findings are now printed highest-confidence first, each carrying a log-odds score, with `--verbose`
naming the three feature buckets that carried it. The score is exported in JSON
(`confidence.logOdds` plus per-feature `terms`), PMD (`confidence`) and SARIF
(`properties.confidence`).

**It ranks and it never filters.** Every finding the detector produced is in every report either
way — the test asserts the two sets are equal, and separately asserts that an untrained model
(which has no opinion) reorders nothing. That constraint is what makes shipping a model counted from
118 findings defensible at all: a weak ranking costs a reader some scrolling, where a weak *filter*
would cost them a clone. Ties break on the clone's content hash, so the order is total and two runs
agree.

The parameters are **counts, not weights**: 120 rated findings across the two preserved precision
worksheets, each contributing one observation per rater, so a finding the raters disagreed about
contributes one to each class rather than being dropped. Two are absent because their lead site does
not relocate into the pinned tree; both are named in the packet. Ruling T's derivation standard,
applied to a second model — counts over a pinned, recorded label set, never fitted to a gate being
passed — and `bench/train-confidence.php` is the instrument that counts them, using the shipped
feature class rather than a copy so train time and run time cannot drift apart.

Four questions, fixed before any count was taken: how many occurrences the finding has, whether they
sit in one file, how many lines it spans, and the literal share of its lead span. Bucket edges are
inspectable rather than fitted — counts at 2 and 3, decade landmarks at 10 and 100 plus the tool's
own existing "large block" landmark at 50, and `FileFeatures`' published share scale reused unchanged
so one log-odds table stays comparable with the other. The demote strata are deliberately **not**
features: the ranking is an independent lens, and folding them in would make two instruments into one
reported twice. No refuted discriminator appears in any wording.

A fifth question — whether the finding is exact, gapped or reordered — was pre-registered and then
**could not be counted**: neither worksheet records it, and the pool is multi-engine so a finding has
no single engine's classification to recover. Dropped before any count was taken, and recorded rather
than quietly replaced.

### Added — asserted and demoted findings, tagged inline in every output format

A finding is now either **asserted** — the tool is claiming it is duplicated logic — or **demoted**:
found, reported, counted, gating the exit code exactly as before, and labelled with why the tool is
not asserting it. Three tags, and a finding carries one only when **every** one of its occurrences
qualifies: `table` (every occurrence wholly inside a statement-free array literal), `registration`
(every occurrence in a registration-role file), `fishy` (every occurrence in a file `--triage`'s
classifier tagged). All three are decided from file contents; none reads a path.

This closes the file-list-granularity gap the M4 packet recorded. `--triage-posture=demote` (since retired; `label` is what it did) already
named the demoted *files* in its summary, but the findings inside them looked exactly like every
other finding, so the tag stopped where a reader needed it to start. It now reaches the console
(`[demoted: table]` inline, plus a counted `N asserted, M demoted (…)` line that prints every
stratum's zero), PMD XML (`stratum`/`demotedBy` attributes on `<duplication>`), JSON (per clone and
in `summary`) and SARIF (a demoted result at `level: note`, with `properties.stratum`).

**Nothing is filtered.** Every clone the detector produced reaches every format, and the test asserts
the identity of the two sets rather than trusting it. The M5 pre-commitment experiment measured that
no rule derivable from this project's labels reaches the precision bar by silencing, and that what
the labels *do* license is demotion: zero of 56 consensus-Y findings sit in the table scope, so
tagging that class costs nothing the raters valued — while a byte-identical copied table shows that
silencing it would be wrong.

Two paired negatives ship with it. A route file written against one shared `$router` receiver is a
*procedure*, not a table, and stays asserted. And two generated tables in two files matching
whole-file — the `Php7.php`/`Php8.php` shape, this corpus's flagship true positive — stay asserted
too, because the reported span reaches out past the array frame to the `return` that opens it and the
scope test declines to speak. A scope test that crept outward to cover that pair would be the failure
every refuted discriminator shared.

**Changed:** `Log\Logger::process()` now takes `Presentation\Findings` instead of a bare
`CodeCloneMap` (the map is carried on it, so every format keeps its corpus-level summary). The
console, the log files and the exit code are computed from one presentation, so they cannot disagree
about what the tool asserts.

### Added — the facts layer's second half: a file's statements, and its role

`Facts\FileStatements` segments a file into statements and says which of them are at its
top level; `Facts\FileRole` reads that segmentation and answers what the file *is*. Ruling T
as amended (2026-09-02) authorises role features on one condition — content- and
wiring-derived only, **never path patterns** — and this class cannot break it: it is handed a
token stream and nothing else, so there is no filename in scope to read.

One role so far. A file is **registration-role** when, over its top-level statements past the
`declare`/`namespace`/`use` preamble, a strict majority are single call expressions whose
value is discarded, and those expressions share no variable. Route tables, provider bindings
and middleware stacks are written that way. "Predominantly" means more than half; that is the
arithmetic of the word rather than a constant on a curve, which is why this ships without a
derivation and the M5 charter names the role membership a definition.

The majority is what makes it a role rather than the M5 experiment's span filter. That filter
asked whether *every* statement of a reported span was a registration expression, and on real
route files the answer was no — one grouping wrapper in a block of forty refuses the whole
span, which is why it reached one route finding of seven. A file whose top level is thirty
`Route::get(…)` calls and three `Route::group(…, function () { … })` blocks is a route file,
and the majority says so.

Dataflow independence is required and its cost is stated rather than hidden: a run of
`$builder->add(…)` over one shared receiver is a procedure, not a table, so it is **not**
registration-role and stays asserted — that pair is the charter's paired negative, built
before the role was measured, and both halves have equal token counts so they differ only in
the thing the definition names. A route file written against a `$router` variable falls on
the same side, which is the conservative direction.

The statement segmentation and the single-call shape test are this project's own work, moved
here from `bench/precommit-rules.php` where the M5 experiment built and self-tested them.
Rule 8 as a *filter* stays dead; only its membership test is reused, and it silences nothing.
Significant-token numbering agrees with the encoder and with `RegionStructure` over the whole
of `src/`, and the segmentation is asserted total — every significant token belongs to
exactly one statement — because "a majority of the top-level statements" is meaningless over
a partial denominator.

### Added — `bench/precommit-rules.php`, the M5 pre-commitment experiment's instrument

The plan opens M5 **only on a passing pre-commitment experiment**: the two candidate
precision rules are prototyped as post-hoc measurement filters before any engine change, and
the outcome decides whether the milestone is built at all. This is that prototype. No `src/`
file changed and no product behaviour changed; the report is
`docs/research/audit/M5-experiment.md`, and its verdict is **FAIL**.

Four modes — `self-test`, `measure`, `corpus`, `explain`. Rule 8 (order-independence) is a
membership test with no constant: a span whose statements are each a single call expression
and which name no variable in common is configuration written as statements. Rule 9
(literal overlap) scopes itself with `RegionStructure::literalTable()` — ruling 5's granted
definition of *literal*, reused rather than reimplemented — and scores a pair by the Jaccard
overlap of its distinct literal values. Its floor is **not** written into the file: the
derivation rule is pre-registered in the docblock and the floor is derived inside the
experiment from the two preserved worksheets' consensus labels, then passed in as an
argument.

Both rules ship with the paired negative this project requires of a bounded mechanism, and
both pairs are minimal — equal token counts, differing only in the thing the rule names.
`Config::set(…)` three times is silenced and `$config->set(…)` three times is kept; one
table against a byte-identical copy of itself scores 1.0000 and is kept, against a
same-shaped table of different data it scores 0.1429. The self-test also makes the
instrument fail on demand: the copied pair is kept at floor 0.50 and silenced at 1.50, and
the unrelated pair flips the other way, so the floor is shown to be what decides rather than
a dead branch. 15/15, and clean at PHPStan level max.

### Documentation — the unified engine's three open items now say what was measured

The README's three open items still carried the M3 numbers: speed "widens with corpus size" at
0.62× / 0.47× / 0.55× / 0.36×, precision 0.34–0.36 at κ = 0.898 with a 0.77 projection, and the
superset gate at 19 unmet items. All three have been re-measured since, and two of them changed
direction rather than degree.

```
  speed       the ratio now improves with size: 3.4x → 1.7x → 1.8x → 1.1x on firefly-iii,
              5.4x → 3.8x → 2.3x → 1.3x → 1.4x on a 2,735-file private application.
              The <= 1.5x target is met at the largest sizes and missed below them.
  precision   a second two-rater audit, kappa 0.721 on 52 of 60: 0.468 [0.333, 0.608] and
              0.574 [0.433, 0.705], against the token bag's 10/10. The 0.80 bar is outside
              both intervals. The 0.77 projection is replaced by the measurement it projected.
  recall      every location Rabin-Karp reports is covered on all four bench corpora; what
              remains is a grouping difference plus eight full-length pairs.
```

The two precision numbers come from two different corpus definitions and the improvement is not
a subtraction of one from the other; the README says so rather than printing a delta.

### Deprecated — `--algorithm=tokenbag`, and why it is not removed with it (ruling 6)

Selecting the token bag alone now prints a deprecation notice, the same way `--algorithm=suffixtree`
does. The reason is different, and the notice says so rather than only recommending a replacement.

The unified engine beats the token bag on the capability the bag exists for: permutation recall
89.1 % / 89.7 % against 77.5 % / 81.4 %, with the displaced ranges *localized* rather than a
similarity score. That was the condition the plan set for retiring it.

It is not enough. Ruling 6's adjudicated merged-default gate finds **96 location entries at 69
distinct locations** across the four bench corpora that the token bag reports, an independent
bijective recompute supports at the baseline's own threshold, and unified does not cover. A class
of real findings would die with a removal now. So the token bag ships **selectable-deprecated**:
the notice tells a user the gap is real and the flag still works, and the removal waits on a
successor that demonstrably covers those locations rather than on this engine's opinion of itself.

The merged default pipeline is unchanged — it still runs Rabin-Karp and the token bag together, and
nothing about that is deprecated here.

### Changed — `check-superset.php` adjudicates an uncovered location before calling it a miss (ruling 6)

The pairs half of the subsumption check already refuses to charge this engine for a baseline claim
an independent measure does not support. The location half — the half the release gate is written
in — did not: it counted every location the baseline named and this engine did not report as a
miss, without ever asking whether the baseline's claim about that location was real.

Under `--baseline=default` (the merged Rabin-Karp + TokenBag pipeline 1.4.0 ships) each uncovered
location is now recomputed independently: three-token shingles, multiset intersection matched one
occurrence to one occurrence over the larger bag, at the run's own `--min-similarity` rather than
any engine constant. Below that threshold the location is listed as a **baseline over-report** and
set aside; at or above it, it is a **survivor** and still fails the gate.

Three conservatisms, all pointing toward calling a location a genuine miss: a class is adjudicated
at its *strongest* supporting pair; a span the recompute cannot place stays a miss; and a claim
Rabin-Karp also makes is contiguous, so a bag measure has no standing over it and it stays a miss.
`--baseline=rabin-karp` is unaffected — Rabin-Karp makes contiguous claims only.

What the release gate actually measures, restated:

```
                    was    survivors (distinct)   baseline over-reports (distinct)
  php-parser          8      2  ( 1)               6  ( 6)
  symfony/string      0      0  ( 0)               0  ( 0)
  phpunit           134     10  ( 8)             124  (21)
  firefly-iii       311     84  (60)             227  (93)
```

Both numbers are printed and neither is folded into the other. No constant is introduced: theta is
read from the configuration the baseline itself ran under.

### Changed — *literal* in the span rule now means statement-free (ruling 5)

The span-level precision tier silences a pair of spans that are two runs of elements of **one and
the same** literal array. Its definition of *literal* was "contains no computation token" — a
whitelist of numbers, strings, bare names, `=>` and array punctuation, with a call parenthesis
disqualifying the frame. That definition is **struck**, and replaced by the one the language
itself draws: a frame is a data table when it is **statement-free**, meaning no `function`, no
`fn` and no `;` anywhere inside it.

An array element is an expression; the only way a *statement* reaches inside an array literal is
a closure body. So `['driver' => env('DB_DRIVER', 'mysql')]` is a table however its values are
spelled, and `['handler' => function () { … }]` is not — a second copy of a closure body is a
second copy of behaviour, which is what the tool exists to report.

The struck reading excluded a whole family of files every reader calls data tables: framework
config files whose rows are `env()` calls, data-provider returns whose rows are
`self::from(new IntervalProvider(…))`, transformer and form-request mapping tables whose rows are
`$this->clearString($object['x'])`. On the four bench corpora it silences 37 self-matching tables — three of
which re-form at shorter spans, for a net 34 — and loses nothing else:

```
  php-parser        83 →  83   no change (Php7.php ↔ Php8.php, the flagship true positive, intact)
  symfony/string    23 →  22   one data-provider table matching itself
  phpunit          590 → 581   two data-provider returns, one `.php-cs-fixer.dist.php` config table
  firefly-iii      597 → 573   `config/firefly.php` (24 self-findings → 17), two data
                               providers, two mapping tables
```

Still a membership test over one token class, so no constant moves and none is introduced. The
identity half of the rule is untouched: two *different* tables, in two files, remain a finding.

### Changed — the paper gains the unified engine, a correction, and a method note

`paper/token-based-clone-detection-for-php.tex` gains \S"The Unified Engine": the design and its
provenance, the two additions that make it more than seed-and-extend (a refused reading that does
not consume its evidence; an order-free channel for the displacement class seeding provably cannot
reach), and the two structural precision tiers. The first revision's third future-work direction —
the residual cost of candidate enumeration — is marked as answered and points at it, and the three
earlier engines are repositioned in the conclusion as the measured baselines for the fourth.

Two passages are there because the project owes them rather than because they flatter it.

**The precision correction.** The 0.34 figure was drawn from a pool taken on a live working tree
that drifted between the rating pass and the close — the pooled count moved 74 → 1,030, and about
30 % of the rated pool sat in a backup subtree that no longer exists. The paper says so, states
that the two numbers describe two different corpora, and records the two rules that followed:
pinned snapshots for any published figure, and one corpus per comparison. It does **not** print a
replacement number: a fresh pool exists and one rater has rated it, and reporting one rater's
verdicts as the two-rater protocol's output is the error the protocol exists to prevent.

**The classifier's life cycle**, as a method rather than an anecdote: train a guesser for a
question the tool cannot answer from first principles, read its log-odds to find which of its
features are *provable* rather than merely predictive, promote those to rules that discard on
checkable evidence, and let the guesser shrink toward the residue. On this corpus the positive
class was removed from underneath it entirely.

Five bibliography entries were added for work the engine builds on and the paper had not yet
cited. **The PDF is not rebuilt in this commit** — `pdflatex` is not installed in the environment
the revision was written in, and `bin/build-paper.sh` refuses rather than producing a stale
artifact. The `.tex` is checked for balanced environments and for dangling citations and
references; the PDF must be rebuilt where TeX Live is available before release.

### Added — `MODERNIZATION.md`, the inherited-surface inventory (ruling S)

Both this file and the README link to `MODERNIZATION.md`. It did not exist — two dangling links
in the two documents a reader reaches first.

It exists now, and it is the inventory ruling S asks for rather than the line-by-line upstream
diff the old links promised. Measured over `src/`: **84.4 % of the tree (75 of 94 files, 15,448
of 18,298 lines) is original to this project**; 19 files carry an upstream attribution, of which
9 are the Apache-2.0 ConQAT-derived suffix tree now deprecated, and 10 are BSD-3-Clause upstream
PHPCPD — the tokenizer, the Rabin-Karp strategy, the finding model, and CLI and reporter
scaffolding.

The unit is *files still carrying the attribution*, not surviving lines, and the document says
why: a header is an attribution claim rather than a measurement, and computing a line-level diff
would require reading the inherited implementation, which is the one discipline ruling S's
replacement standard actually enforces. It is also the unit that has to reach zero before the
package can be relicensed MIT.

### Deprecated — `--algorithm=suffixtree`

The suffix-tree engine is deprecated and will be removed. It stays selectable for this release,
and a run that asks for it says so on stdout rather than only here.

Two reasons, and the second is the one that sets the schedule. It does not scale — 208 files
took over 300 seconds against the default engine's 3.6 — and it is **the one component of this
package that is not the project's own work**: it descends from the ConQAT toolkit and carries an
Apache-2.0 attribution the rest of the tree does not need. `--algorithm=unified` now reports
gapped clones with the divergent ranges named on both sides, which is more than the suffix
tree's `[inconsistent]` boolean, so nothing is lost by the removal.

The removal itself is a later commit and takes the `NOTICE` section with it, per the plan's
sequence: an engine is deprecated for one release before it is removed. That sequence is
anchored on the default flip, which is a release decision the measurements do not yet support.

### Changed — the precision pool builds its corpus from Stage 0, not from a second definition of it

Ruling T's point is that there be exactly one definition of "the corpus", shared by the
engine, the bench walker and the precision-audit pool. The pool had its own: default excludes,
plus a preset's exclude list, plus an entire-file-orphan pass — the M3-era ladder, assembled
inline. A pool built on a different definition than the engine measures a corpus nobody runs.

`bench/audit-precision.php pool --triage` now runs Stage 0 itself, in the shipped posture: the
four rungs that can prove their case discard, the classifier tags and does not remove. The old
path stays reachable so M3's numbers can still be reproduced.

### Changed — the superset check can now adjudicate an order-free claim instead of declining to

`bench/check-superset.php` settles a length disagreement with its own walk over the two token
streams, trusting neither engine. That walk measures a **contiguous** run, which is the wrong
instrument for a token-bag finding — a bag makes no claim about order — so since the M4 stop
point such pairs were reported `inapplicable`: counted, listed, never folded into a pass. That
was honest, and it left the baseline's claim unadjudicated in either direction.

The deferred condition was that the check gain a bag-appropriate independent adjudicator once
ruling R landed. It has: three-token shingles, multiset intersection matched one occurrence to
one occurrence, over the baseline's own claimed span, at the baseline's own
`--min-similarity`. Written here rather than called from the engine, for the same reason the
contiguous walk is — an adjudicator that shares code with one of the parties is not one.

It changes what the gate can say. On php-parser, three of the four token-bag findings the
merged-default gate reports as uncovered are **over-reports by this measure**: their bijective
coverage is 0.43–0.55 against the 0.70 the token bag claimed, because a bag of token
*unigrams* overlaps far more readily than a bag of shingles. The fourth clears the threshold at
0.72 and is a genuine gap — its span is 45 tokens, below the run's own `--min-tokens`.

### Added — ruling R: the order-free capability, built into the engine rather than beside it

The unified engine seeds on k-grams and chains them colinearly, so its evidence is always an
*ordered* run. Ruling A's algebra says what that costs: a displacement shorter than K = 16
tokens leaves no run long enough to seed on either side of it, so a function whose statements
have been permuted in place is invisible to the seeded path at any threshold. That is the one
class the token bag could see and this engine could not, and it is why the token bag is
**replaced** rather than removed.

Two additions, both inside the existing spine — there is no fourth `--algorithm`:

**A third seed channel.** Per-function bags of 3-token shingles, indexed under a prefix filter
(Chaudhuri, Ganti & Kaushik, ICDE 2006; PPJoin, Xiao et al., TODS 2011) ordered by ascending
document frequency, promote bag-overlapping function pairs into Stage D as ordinary
candidates. It fires only where the seeded path is silent, through the same subsumption test
the normalized view already uses, so its entire finding surface is the marginal class.

**A second verdict in the verifier.** An aligner refusal says two spans cost too much *in
order*; whether they hold the same material in a different arrangement is a different question
about the same evidence, so it is asked there. It is asked **beside** the refusal, never
instead of it — an acceptance that replaced the refusal would consume the exact clones inside
the refused reading, which is precisely the defect ruling O fixed, and it was measured doing
so before being corrected.

The same verdict also *renames* an accepted gapped clone as a reorder when the material is all
present and the moved block can be named. That is what brings fixture r1 — two swapped
three-token statements — back into contract: the pair was always reported, but as a
substitution, because nothing seeded could say otherwise.

**k = 3 is derived, not chosen.** A statement shorter than k contributes no shingle that
survives being moved, so the floor case decides it, and ruling R names that case: r1's swapped
statements, which are **three** tokens in the engine's own numbering. Measured over 8,680
statements in the four bench corpora (`bench/measure-statements.php`, new), 90 % are three
tokens or longer. k = 4 was implemented first and put r1 permanently out of contract — bag
coverage 0.775, above θ, with zero localizable displacement — which is how the unit error was
found.

**Displacement is order-based, not offset-based.** A run sitting at an unusual offset is what
an *insertion* produces; a run whose order differs between the two sides is what a permutation
produces. The displaced set is the complement of the heaviest order-preserving subsequence —
the same construction the anchor path already used, so there is one notion of "displaced" in
the engine rather than two. The offset rule reported probe 2's twin insertions as a reorder.

Measured:

```
  permutation recall, adjacent    73.6%  ->  89.1%     (token bag 77.5%)
  permutation recall, distant     61.9%  ->  89.7%     (token bag 81.4%)
  fixture r1                      substitution -> reorder, with the moved block named
  superset vs rabin-karp, phpunit 19 unmet pairs -> 1; the location half still passes
  superset vs rabin-karp, php-parser            3/3, unchanged
  guaranteed recall region        295/295, unchanged
  wall-clock, phpunit 2,694 files 3.24s -> 4.31s against the default pipeline's 0.81s
```

### Added — the span discriminator: a literal table matching itself is not a clone

Ruling H left exactly one avenue open for the data-table false positive and closed the other
three by measurement. Anchor multiplicity, tokens-per-line sparseness and logic share were
all *statistics* of a span, each needing a threshold, and all three died on the same
counter-example: php-parser's `Php7.php` and `Php8.php` hold generated action tables that
are as literal as any config file **and** are the corpus's flagship true positive, because
whatever regenerates one regenerates the other.

So the new rule asks a different question — not how literal a span is, but **what it is**:

> Are these two spans two runs of elements of *one and the same* pure literal array?

Identity is the whole point. Two different tables in two files are left alone. A table
matching *itself* is silenced, because its second run is not a copy anyone could delete — it
is the regularity that makes the file a table. A "pure" literal array is one containing no
computation anywhere inside it: a variable, a call, a `::`, a `->`, an interpolation or a
closure disqualifies it. That is a membership test, not a cut on a curve, so **no constant
is introduced**.

The structural description lives in `src/Facts/RegionStructure.php` — the facts layer, so
the same annotation can be read once by this tier and by what follows it. It numbers tokens
exactly as the encoder does, and a data-provider test asserts that alignment over every file
in `src/`, because an off-by-one would not fail loudly: it would move every span by one token
and answer confidently.

Measured, on the corpora it can be measured on:

```
  php-parser  Php7.php:381 ↔ Php8.php:383 (2,536 lines)   still reported
  symfony/string   24 → 23 clones   (an inflector's rule table, matching itself)
  phpunit         599 → 597 clones   (a const table of deprecated ini settings, ditto)
  firefly-iii     604 → 594 clones   (all ten in one `config/` table, ditto)
  superset gates  php-parser 3/3, phpunit 19 unmet items — both unchanged
```

On the private corpus's rated worksheet, re-scored: it silences **27 of the 32** false
positives that survive corpus triage and loses **none** of the findings both raters called
real, taking the projected precision from 0.347 to **0.773**.

### Changed — the tracking metric names the findings it silences, not just how many

`bench/triage.php check` reported *"silenced by Stage 0 — 1 N and 0 Y"*. The acceptance the
number is read against is written per finding — every consensus data-table N silenced, zero
consensus Y lost — and a count cannot be checked against that sentence. The finding ids are
printed with it.

### Changed — the Stage 0 acceptance reports a posture comparison instead of asserting a bar

`bench/triage.php check` asserted that Stage 0 keeps at least 95 % of the frozen corpus
definition. That bar was never in ruling T — it was this project's own operationalization
of *"approximately reproduces"*, and it was scored against a posture nobody had chosen yet,
so a coverage number the owner is supposed to weigh at the release flip was being reported
as a failure.

The auditor's posture-relative rule (2026-09-02) settles it: a comparison states **one
corpus**, and **posture differences are corpus lines, not pass/fail bars**. So the check
prints both:

```
  Retention against the target, by classifier posture — reported, not a bar
    posture    kept retained coverage cost
    discard    2382    90.0%       206 files
    demote     2543    97.8%        45 files
```

The two postures differ in exactly one rung, and the residual under `demote` is the four
rungs that prove their case — they discard under either posture, which is what "posture
follows epistemics" means. Stage 0's acceptance now gates on the two claims ruling T
actually makes: no file holding a consensus-Y finding is discarded, and two runs over one
tree agree.

### Added — `bench/relocate-worksheet.php`, so a rated worksheet can name its files again

`bench/audit-precision.php` digests every path behind a per-run salt that is never stored,
which is the right default for a document about a closed tree and is also why the M3
worksheet could not answer the two questions this milestone asks of it: ruling T's
acceptance ("no file holding a consensus-Y finding is discarded") and ruling U's tracking
metric are both claims about *files*.

The worksheet embeds source, and source locates itself. Each site's excerpt is a contiguous
run of lines beginning at a recorded start line, so the tool searches the pinned tree for
that exact run — **matching, never judgement**: it either finds the block or reports that
it did not, and no verdict is ever revised. Two rules, in order:

1. **Block match** — the run found at the recorded start line is `exact`; the same run at a
   different offset is `moved` (the file drifted, the content did not).
2. **Digest propagation** — the salt is fixed for one run, so two sites carrying the same
   digest are two sites in the same file. Applied only when every already-resolved site of
   that digest agrees on one file; a disagreement is a digest collision and is reported
   rather than resolved.

Candidates come from ruling P's manifest and a file qualifies only while its content still
hashes to the pinned value, so a worksheet is never relocated into a tree it was not rated
against. Corpus root, manifest and worksheets are arguments, never defaulted; the output
refuses to be written inside this repository.

### Fixed — `bench/pin-snapshot.php` was outside the static-analysis gate

`phpstan-bench.neon` lists its files one by one and says so: *"Anything added to `bench/`
from now on belongs in this list."* Ruling P's pin instrument was added during M4 and was
never listed, so the config reported `[OK] No errors` over a set that did not include it.
Now listed; it is clean at level max.

### Fixed — the private corpus's path was kept out of commits by a file outside the repository

`.claude/settings.local.json` records the literal command lines a Claude Code session was
allowed to run. On this project those commands name the dogfood corpus root, so that file
is the one place in the working tree where the private corpus is reliably named — and it
was untracked only because the *user's global* gitignore excluded it. A protection that
does not travel with the repository is not a protection of the repository, so the entry is
now in `.gitignore` where it belongs.

Found by the hygiene grep that `4.13`'s naming breach added to the stop-point routine: run
over the whole working tree rather than over tracked files alone, it finds what `git grep`
cannot see.

### Changed — the classifier's trade is re-derived on the population it is actually asked about

`bench/triage.php train` now reports the trade twice: over the whole label set, and over the
files that survive the rungs that can prove their case.

The distinction turned out not to be academic. The label set defines "fishy" as rung 1 minus
rung 2 — which is exactly what ruling V's *derived* rung now discards, by proof, before the
classifier is consulted. Measured on the private corpus: **of 2,113 fishy-labelled files,
zero survive the proof rungs.** The original curve describes a question that is no longer
asked, so it is not cited any more.

### Added — Stage 0 is selectable from the command line, and flips nothing

Ruling T's triage stage has existed since `1a861ca` with exactly one consumer: the
benchmark. It is now wired into the product and reachable, with both postures the ruling
names:

```bash
phpcpd --triage                      # skip what is not program text
phpcpd --triage-posture=label        # scan it anyway, but say what triage decided
phpcpd --triage-classifier-discard   # let the rung that estimates remove, not just tag
```

**Posture follows epistemics.** Four of the five rungs prove their case from a manifest, a
symbol table, or the project's own default excludes, and a reader can check any one of them
— those *discard*. The fishiness classifier estimates, so it *tags*: files it scores fishy
are marked with the score and their top log-odds features, counted, and still scanned. A
wrong estimate then costs a tag rather than a finding.

```
Triage: 441 of 1291 files removed · unwired 441
Triage: 34 of 1291 files tagged, not removed · fishy 34
  fishy  config/cache.php — score +12.98 — returns_data=yes +3.40, literal_mass=medium +2.87, referenced=n/a +2.70
```

`--triage-classifier-discard` lets the estimate remove files too. It stays opt-in until two
pre-registered criteria are met: zero discards of files carrying a consensus-Y finding, and
a measured ~zero false-discard rate on wired program text at the shipped margin.

**Off by default, on purpose.** Turning triage on changes which files a scan reports on —
on one public corpus, `--triage` takes 1,291 files to 850 and 398 findings to 168. That is
a flip, and flips are the owner's at release, on the evidence. Ruling (a) asked for the
stage to be wired and both postures selectable; it did not ask for the default to move, and
the default has not moved: a bare run reports exactly what it reported before.

**Never silent.** Triage prints what it removed and under which rung before anything is
detected, and `--explain` lists every file with the evidence against it. A file that
disappears from a scan without saying why is the failure mode this stage would otherwise
introduce.

`demote` keeps every file in the scan and names the doubted ones; its findings are
therefore identical to a bare run's, plus the report. The tag is at file-list granularity:
individual findings are *not* yet marked inline in each output format, which is the
remaining piece of the demote-and-tag posture and is recorded rather than implied.

Ruling V is honoured here too. In an ordinary run the walk has already applied the default
excludes, so the file list *is* the witness set; under `--no-default-excludes` it is not,
and the witness set is walked separately rather than letting a compiled cache vouch for a
dead class.

### Added — a framework preset applies itself when the project says what it is

A preset encodes knowledge the tool already ships: that `storage/` is a framework's
scratch space and not the programmer's source. Requiring `--preset=laravel` to obtain
that meant the bare invocation reported noise the tool already knew how to name. When the
scanned tree **is** a Laravel application, the preset's excludes now apply on their own.

Detection reads the project's own declarations, never a path guess — "there is a directory
called `app/`" is exactly the guess this does not make. Both halves are required: a
`composer.json` at or above the scan root requiring `laravel/framework`, **and** that
manifest's directory carrying `artisan` or `bootstrap/app.php`. The manifest alone would
fire on a package that merely tests against the framework, which is why `require-dev` is
not consulted; a marker alone is a filename anyone may choose. Read together they are a
project stating, in two independent places, what it is.

Detection seeds **excludes only, never scan paths**. It has established what the project
is, which justifies skipping the framework's scratch trees; it has not established that the
user meant to scan four directories instead of the one they typed. `--preset=<name>` still
asks for the full treatment and still overrides detection.

Never silent: a run whose file set was narrowed by detection says so and names the escape.

```
$ phpcpd
Laravel detected — preset applied (--no-preset to disable)
```

The new rule is a shipped default-exclude convention like `vendor/` — overridable and
documented — and not an instance of the path-pattern ban, which governs classifier features
and measurement-time exclusions.

Adding a framework is one row in `PresetDetection::EVIDENCE`, and only where detection is
unambiguous; a framework that cannot be established from a declared dependency plus a
structural marker does not get a row and stays opt-in.

### Fixed — a compiled cache no longer counts as evidence that code is alive (ruling V)

Stage 0 is pointed at the *raw* tree on purpose: its acceptance is that it reproduces the
frozen corpus definition from the first rung, and it cannot triage a cache it is never shown.
But it was also *believing* that tree. The symbol and reference graph was built over every
file found, including the compiled blobs under `storage/framework` and `bootstrap/cache` —
so a class named by nothing but a container cache was judged wired, on the strength of a file
the product itself never scans.

The consequence was a verdict that moved with the developer's cache state rather than with
the code. Deleting and regenerating the cache on one unchanged tree moved the unwired count
`156 → 157 → 156`, while the frozen definition did not move at all. Two runs over one tree
still agreed; two runs over the same *project* in two cache states did not, and that is the
property a user actually has.

The stage now takes a witness set alongside the file set: everything handed in is still
triaged and still accounted for, but only a file the project's own default excludes leave may
be evidence that another file is alive. A derived artifact is judged, and never a judge.
Removed files are reported under their own `derived` reason, counted and explained like every
other discard.

Nothing here is a path pattern. The default excludes are the product convention the walker
already owns — documented, overridable with `--no-default-excludes` — and Stage 0 consumes
that boundary from its caller exactly as it consumes the `composer.json` manifest, rather
than re-implementing it.

### Added — the between-rounds precision instrument (ruling U)

`bench/triage.php check` gains `--key=<file>`, which turns the preserved worksheet into
ruling U's **tracking metric**: the 60-finding audited set re-scored with a tier applied,
reporting the precision that tier projects between full rating rounds.

It is a printed report and deliberately not a `bcb_check()` gate. Ruling U's wording is that
a projection "is never a substitute for the final two-rater pass", and a projection wired
into the gate summary would be a number a session could move by changing the tier that
produced it. The two-rater pass stays the only thing that decides the bar.

Two conventions are recorded in the code because they change what the number means. A
finding is silenced when *any* file it spans is triaged out, since a clone pair needs both
of its sides. And a finding that was never relocated to a file is held as **surviving** —
the conservative direction, because assuming the other way would inflate the projection.

### Changed — Stage 0 gains two more rungs of proof, and ships a trained model

The triage stage announced above shipped with its classifier untrained, because the labelling
question behind it was open. It is settled, and the answer moved work *out* of the estimator
and into rungs that can prove their case:

- **Foreign** — a file whose every declared namespace is claimed by no `composer.json` above
  it, and which sits outside every directory those manifests wire, is someone else's code
  vendored into the tree. This has to be a wiring question rather than a content one: a
  vendored tool's source is program text, and no content feature can say otherwise. The whole
  chain of manifests is consulted, not just the root one — a monorepo package's tests live in
  a namespace only that package's manifest declares, and asking the root alone condemned a
  project's own test suite.
- **Loaded by path** — a `require`/`include` anywhere in the corpus naming the file is
  wiring, by the same standard a symbol reference is. This is what keeps a hand-written seed
  table that no symbol references. It is a rung rather than a feature on purpose: naive Bayes
  multiplies independent evidence, and eight features saying "data file" outvote one saying
  "wired" every time. Proof does not get outvoted.

Only what all four proofs leave goes to the classifier. Measured on the development corpus:
97.3 % of the files the product's own default excludes call not-code are scored fishy, at a
3.2 % rate on files it calls program — and every one of those is a returned configuration or
language array, which is genuinely the same shape as a dumped one.

The threshold is a project-owner decision recorded with the trade curve it was chosen on
rather than a derived separation point, because the two distributions genuinely overlap:
there is no separation between a returned config array and a dumped table to derive one from.

### Changed — a hidden directory is not application source

`.phpstan`, `.phpstan.cache`, `.phpunit.cache`, `.phpunit.result.cache`,
`.php-cs-fixer.cache`, `.psalm`, `.psalm-cache`, `.rector`, `.rector.cache`, `.git` — the
default exclude list had grown into a catalogue of every tool cache someone had already
been burned by. It could never be complete: the directory name is user-configured (PHPStan's
`tmpDir`, Rector's `cacheDirectory`), so matching the documented default left every other
spelling being scanned as source.

The list is replaced by the rule it was approximating: **a directory whose name begins with a
dot is not scanned by default.** A leading dot is the filesystem's own convention for state
rather than content, every ecosystem honours it — version control, editors, CI, and every
analysis cache — and no PHP autoloading convention puts application code in one.

Measured on one real project, a dumped analysis cache under a directory the old list did not
name contributed 1,024 files: a third of everything the tool considered that project's code.
A rule catches the next one; a list catches the last one.

Two things are unchanged, and both matter. `--no-default-excludes` still scans everything.
And a scan root is never pruned by its own name — `phpcpd .github/scripts` scans that
directory, because pruning applies to what a walk descends into, never to what it was
pointed at.

### Added — Stage 0: what is program text, decided once, before duplication is measured

Analysing unwired code measures a project's housekeeping, not its duplication. Measured on
the codebase this tool is developed against: entire-file orphans were 3.9 % of files and
77 % of one engine's findings, and a rated audit's false positives concentrated in *files*
rather than in spans. Three separate times now, a corpus has turned out to contain a dumped
tool cache that no exclude list anticipated.

Detection therefore gains a triage stage that runs before any file is fingerprinted, and
that the engine, the benchmark walker and the precision-audit pool all share, so there is
exactly one definition of "the corpus". Three rungs, ordered by how much each can prove:

1. **unwired** — nothing in the project references anything the file declares, decided by
   the existing orphan machinery with every suppression rule it already carries, so a
   controller reached only from a route file is not called dead;
2. **shadowed** — every name it declares is autoloaded from another file (above);
3. **fishy** — what remains, scored by a naive-Bayes classifier over bucketed features of
   content and wiring: how much of the file is literals, whether it is one returned data
   array, comment density, declaration and function counts, the biggest literal array,
   whether anything references it.

The classifier is deliberately the plainest model that fits. Its independence assumption is
false here — a dumped table is literal-heavy *and* comment-free *and* unreferenced, and
those co-vary — and it is still the right choice, because what this stage owes a user is not
the last point of accuracy but a defensible account of why a file was dropped. Every verdict
is a sum of per-feature log-odds, so a discard reports the three terms that carried it.

**Never silent, and biased toward keeping.** A wrong discard costs a real clone; a wrong
keep costs one noisy finding. So the wiring rungs go first and the estimator only ever sees
the residual, every discard is counted and explained, and the decision threshold is a margin
rather than a coin flip. **No path pattern is consulted anywhere in the stage** — a
manifest, a symbol table and a token stream are evidence a reader can check; a directory
called `backup` is not.

The shipped model is trained by `bench/triage.php` from label sets that exist independently
of any gate, and it currently ships **untrained**, which makes the stage keep every file:
the labelling question it depends on is open and recorded in the M4 audit packet rather than
answered by whoever ran the trainer last.

### Added — a file the autoloader cannot reach is not program text

Two files declare `Acme\Support\Ledger`. One sits where composer.json says that name lives;
the other is a copy someone left behind. PHP loads the first and can never load the second,
yet a duplication scan reports 100 % duplication between them and calls it a finding. On the
corpus this tool is developed against, that shape was roughly 30 % of an audited finding
pool — not duplication anyone could act on, and not a judgement about the code at all.

The new triage rung removes it, and the discriminator is the **autoloader**, never a path.
`Orphan\AutoloadRule` resolves a fully-qualified name to the file PSR-4 or PSR-0 would load
it from; `Triage\ShadowedDuplicates` reports a file whose *every* declared symbol resolves
to a different, wired file. Directory names — `backup`, `old`, `copy`, `.trash` — are not
consulted and never will be: a project's own manifest is checkable evidence, and a name a
person chose is not.

It keeps by default whenever the question cannot be answered, because a wrong discard costs
a real clone while a wrong keep costs one noisy finding. Kept: no composer.json; a namespace
the manifest does not map; a prefix mapping two directories, so the autoloader reaches both
copies (this project's own manifest maps three); a file that declares one shadowed name and
one of its own, since it still holds code; and a file that declares nothing at all, whose
"every symbol is declared elsewhere" would otherwise be vacuously true.

Measured on the four public benchmark corpora and on this repository: no file is shadowed on
any of them, which is the expected answer for trees under version control.

### Added — `bench/pin-snapshot.php`, so a corpus measurement can be shown to be about one tree

A measurement that cannot be reproduced is not evidence. During the M3 measurement pass the
private corpus drifted between rating and close — the pooled finding count moved from 74 to
1,030 — so two numbers taken from it were not numbers about the same thing, and neither
could be checked afterwards.

`pin` records every `.php` file under a root with its SHA-256 and size; `verify` reports
what has since been added, removed or changed, and exits non-zero if anything has. A packet
cites the manifest's one-line **digest**.

Two choices worth stating. It pins the tree **as the project wrote it** — every `.php` with
no excludes applied, except that dependency trees (`vendor/`, `node_modules/`) are always
pruned. Everything else stays: dumped caches, build output, scratch and backup directories,
generated tables are exactly the material a corpus-definition stage has to learn to triage,
and pinning a tree with those already removed would pin the answer along with the question.
Dependencies are the one exception because both reasons point the same way — they are not
the project's text at all, so dropping them begs no question, and they churn on every
`composer install`, which would make `verify` fail for reasons that have nothing to do with
the corpus drifting. And it is a hash list rather than a frozen copy: a copy needs write
access and answers "what did it look like?", while a hash list needs only reads and answers
the question that was actually recorded — "is this still the tree the number came from?" —
so drift is detected instead of hidden.

The same discipline the precision audit already applies: the corpus directory is an
argument, never defaulted and never recorded; paths are stored relative to it, so the tree's
name appears nowhere in the output; and the manifest refuses to be written inside this
repository, because a listing of a private tree's paths is as uncommittable as its source.

### Fixed — the subsumption check no longer fails order-free baseline findings with an adjudicator that cannot judge them

`bench/check-superset.php` settles length disagreements with an independent walk over the
two token streams, deliberately trusting neither engine. That walk recomputes a
**contiguous** run, which is the right adjudicator for exactly one claim: "these two places
hold the same run of code". It is not the claim the token bag makes. A bag finding is two
spans holding the same multiset of tokens, and is deliberately not contiguous — on
php-parser it reports `NodeTraverser.php:93 ↔ :181` at 140 tokens where the walk finds 2
shared in a row.

Failing such a pair is a category error; dropping it silently would weaken the gate. So a
pair whose baseline attribution is the token bag is now reported **inapplicable** —
counted, listed, and never folded into a pass line. Attribution is per pair: a pair is
Rabin-Karp's if some Rabin-Karp clone covers both its locations, and the token bag's
otherwise. `--baseline=rabin-karp` is unaffected in every respect.

It changes what the merged-default gate says about itself, by a lot:

| corpus | pairs, before | after |
|---|---|---|
| symfony-string | 0 unexplained | 0 unexplained + 3 inapplicable |
| php-parser | 6 unexplained | **0 unexplained** + 6 inapplicable — the pairs half now passes |
| phpunit | 38,884 unexplained | **23 unexplained** + 99,854 inapplicable |
| firefly-iii | 1,941 unexplained | **7 unexplained** + 4,871 inapplicable |

The location half is untouched and stays binding for every engine — it is the half the
2.0.0 release gate is written in, and it still fails at 8 / 137 / 311 uncovered locations
on php-parser / phpunit / firefly-iii, every one of them the token bag's.

This is a deferred condition, not a closed one. When the order-free complement pass lands,
the pairs half becomes applicable to these findings again through a bag-appropriate
independent adjudicator — a bijective coverage recompute over the two spans, written
independently of the engine's own code, exactly as the location walk is independent today.

### Added — the subsumption check can be run against the merged default pipeline, which is the 2.0.0 release gate

`bench/check-superset.php --baseline=default` compares the unified engine against
Rabin-Karp **and** the token bag merged — what 1.4.0 actually ships — rather than against
Rabin-Karp alone. This is the project owner's release gate for 2.0.0: "detection better
than 1.4" means every location the 1.4 default reports is reported. `--baseline=tokenbag`
runs the token bag alone, which is what attributes a failure.

It is a strictly harder baseline, and it fails today. First run, on the bench corpora:

| corpus | vs `rabin-karp` | vs `default` (rk+tokenbag) | of which token bag's |
|---|---|---|---|
| symfony-string | pass | **3/3 pass** | — |
| php-parser | 3/3 pass | 8 locations uncovered | **8 — all of them** |
| phpunit | 0 locations uncovered | 137 locations uncovered | **137 — all of them** |
| firefly-iii | 0 locations uncovered | 311 locations uncovered | **311 — all of them** |

The missing-location count under `--baseline=default` equals the count under
`--baseline=tokenbag` exactly, on every corpus: the unified engine covers everything
Rabin-Karp reports and nothing the token bag reports alone. That is the known gap — the
order-free duplication a seeded method provably cannot see below the seed length — and it
is reported here as a number rather than patched.

One caveat is worth stating, because the output invites a wrong reading. The check settles
length disagreements with an independent walk over the two token streams, and that walk
measures a *contiguous* run. A token-bag finding is by construction not contiguous: on
php-parser it reports `NodeTraverser.php:93 ↔ NodeTraverser.php:181` at 140 tokens where
the walk finds 2 shared contiguously. So the pair-length half of the check cannot arbitrate
a token-bag baseline and its "unexplained" counts against one are not evidence of
over-reporting by either engine. The *location* half is unaffected, and it is the half the
release gate is written in.

### Fixed — a candidate the aligner refuses gives back the exact clones inside it, split where the alignment failed

A cluster is mined by taking the best chain, removing the anchors it consumed, and asking
again. Removal happened *before* verification, so a candidate refused by the aligner took
its whole span's evidence with it — and the aligner's refusal is the one verdict that says
nothing about the candidate's parts. "Below `--min-tokens`" and "below the normalized
diversity floor" are inherited by every sub-span; "these two spans are too dissimilar taken
as one alignment" is not, because the chain that built them is made of *exact* runs and the
refusal means only that the gaps between those runs cost more than acceptance allows.

On PHPUnit's `assertArraysAreEqualIgnoringOrderTest.php` against
`assertArraysAreIdenticalIgnoringOrderTest.php`, one chain spanned 389 tokens against 351
with two gaps — 78 tokens and 20 — and was refused at similarity 0.00. Inside it sat two of
Rabin-Karp's three clones for that file pair, 193 and 98 tokens, **both exact**. Both were
consumed by the refusal and never reported. The engine found three clones for that pair;
it reported one.

The evidence is now returned, decomposed rather than restored whole. Restoring it whole
would rebuild the same chain next round and spin. The reading is not merely unlucky, it is
wrong in a locatable way — it bridges its widest gap, which is precisely the material the
alignment could not pay for — so the cluster is cut there and each side mined as a cluster
in its own right. Anchors straddling that gap survive into neither half, which is both what
stops a half rebuilding the refused chain and what makes each half strictly smaller than
the set it came from, so the loop still terminates.

This is the mechanism the self-overlapping refusal already used, applied to a second
refusal: a candidate that cannot be reported as it stands gives back what it holds,
narrowed by a rule read off the candidate itself. **No constant is introduced** — the split
point is the widest gap the chain already reported, and only gaps interior to the aligned
span are considered, matching the filter verification applies when it totals their cost.

On `bench/corpus/phpunit` the subsumption residual falls from 22 items to 19, and the two
items removed are exactly the two this fix targets, with nothing else in the list changed:
the uncovered location `assertArraysHaveEqualValuesIgnoringOrderTest.php:269` is now
reported, so the location check passes outright for the first time. Unified reports 564 →
599 clones on phpunit and 81 → 82 on php-parser; php-parser's superset check stays 3/3,
recall stays 295/295 across the guaranteed region, the chaining oracle stays 2/2, and
determinism and incremental are unchanged. The cost is wall-clock: refused candidates now
do work where they used to stop, and the (already failing) ratios move from
0.66/0.51/0.57/0.41 to 0.62/0.47/0.55/0.36 at 60/200/600/2,500 files.

### Fixed — the benchmark's gates scan the corpus the product defines, instead of turning its excludes off

Four checks that record numbers — subsumption, wall-clock, determinism, incremental — asked
`FileFinder` for their file list with default excludes **off** and no excludes of their own.
On `bench/corpus/phpunit` that scanned 175 files the product would never scan: 117 under
`tools/.phpstan` (a dumped static-analysis tool tree, the exact adversarial input the
default excludes exist for), 35 vendor stubs inside end-to-end test fixtures, 12 other
vendor files, and 11 build scripts. Every phpunit number recorded from M1 through M3 was
measured over that set.

They now share one walker, `bcb_gate_files()`, which applies the tool's own definition of
program text — the same principle the corpus definition uses one level up: the tool already
knows what is not source, so the benchmark asks it rather than keeping a hand list that
drifts. The determinism check's CLI half drops `--no-default-excludes` for the same reason,
so its two halves compare one corpus rather than two.

The justification the checks carried for turning the defaults off — that a check pointed at
a directory *under* `vendor/` would see everything pruned and compare two empty reports —
does not hold: `find()` prunes descendants of the roots it is given, never the roots
themselves, so `find(['vendor/phpunit'], …)` returns the same 1,039 files either way.

What moved, on `bench/corpus/phpunit` (php-parser and symfony-string were already clean;
firefly-iii loses 16 files): 2,870 → 2,695 files scanned, Rabin-Karp 160 → 156 clones,
unified 587 → 564. What did **not** move: the subsumption residual (22 items — one
uncovered location and 21 unexplained pairs — with the same composition), recall,
determinism and incremental under `--algorithm=unified`, and the wall-clock verdict, which
still fails at all four sizes. The dump was noise in the corpus, not the cause of any
failing gate.

### Added — `--algorithm=unified` gains Stage D: gapped, reordered, and the normalized second view

Stage D — extension across gaps, chaining, classification, verification — completes the
engine M1 left reporting only its gapless anchors. The unified engine now finds everything
the three engines it replaces find, from one anchor set: exact copies, renamed copies,
statements inserted/deleted/changed, and statements reordered — each classified rather
than requiring a different `--algorithm` or `--fuzzy` flag.

**Type-3 gapped, with the divergent ranges kept.** Where two copies' anchors chain with a
gap between them, the gap **is** the divergence — reported as an exact token range on
*both* sides, not the suffix tree's boolean. Gapped extension recovers exact runs a seed is
too short to reach directly (a common prefix/suffix at each flank, and a bounded
longest-common-run search inside a gap for a run stranded between two edits) rather than
through a banded-DP traceback; the trade, recorded plainly, is that a divergence whose far
side matches only *approximately* — never exactly, for four tokens or more — is not
crossed. `--min-tokens 50` on the suffix-tree Type-3 fixtures: `gapped: true`, four named
ranges, each one flanked by anchors that actually match and each one containing material
that actually differs (both are now asserted as a property test on engine output, not by
inspection).

**Type-3 reordered.** The same anchors judged twice: once for the best in-order chain
(`ChainBuilder::chain`, gap-penalized), once for total coverage regardless of order. A
candidate is reordered when total coverage is at least `θ · span` (0.7, Sajnani et al.,
ICSE 2016) **and** the coverage the in-order chain had to drop is at least K = 16 tokens —
the smallest displacement a seeded method can attest, since any anchor crossing another on
the diagonal is that long by construction. (The plan's original figure — dropped coverage
under half of total — is provably unsatisfiable for a two-block swap of any size; the
proof is two lines and is now in `docs/research/unified-engine-plan.md` §1 Stage D3.) The
displaced block is named on both sides the same way a gap's ranges are, so a reader can see
*which* statements moved — a token bag can only say that the file's contents are still all
present. Below K tokens (a single swapped statement, say), no seeded method can tell a
reorder from a substitution; this is a documented, out-of-contract case, not a bug, and
`--min-tokens` set anywhere near a file's own size can additionally make the winnow window
too narrow to seed a swap at all.

**The normalized second view is real Type-2, not name-blind fuzzing.** A second signature —
identifiers normalized, types kept distinct — is fingerprinted, anchored, and classified the
same way as the raw one; which view found a clone *is* its type. A normalized-only gapless
match is Type-2; a normalized-only gapped or reordered match is the same finding under a
rename. Where the raw view already reports a gap the normalized view reads as an unbroken
match, the divergence is a rename, not a defect, and the report says Type-2 instead of
Type-3 — the normalized view is what makes that distinction possible at all.

**A normalized-seed diversity floor keeps the second view from becoming a different
detector.** Measured across this project's benchmark corpora (real function bodies against
generated/data files — composer class maps, Laravel locale arrays, symfony/string's Unicode
range tables — all under the same type-anchored normalized view, K = 16 tokens per window):
function-body windows sit at 3 or more distinct tokens at their own 1st percentile;
generated/data windows sit at 2 or fewer, 97.6% of the time, and the cleanest case — two
unrelated Unicode range tables, every integer literal folding to the same normalized token —
never exceeds 2 anywhere in either file. **3** is therefore where the floor sits: the
smallest value that clears the data case entirely while costing real code under 1% of its
windows. A normalized k-gram below the floor is not fingerprinted, so a run built entirely
of such k-grams seeds nothing; a normalized-only match that still slips through (direct
extension pulls bytes, not diversity) is rejected again at verification. Before the floor,
unified reported 28.75% duplicated lines on symfony/string against Rabin-Karp's 0.33% and
ran an order of magnitude slower from the resulting candidate-space explosion; after it, the
two unrelated Unicode tables produce no report at all, Type-2 recall on a consistent-rename
mutation sample is unchanged (100% before and after, 114/114 real function-shaped files
sampled across the corpora), and the raw view is untouched — the floor is never applied
there. `Winnower::NORMALIZED_DIVERSITY_FLOOR`; the full measurement and per-file breakdown
are in `docs/research/audit/M2-report.md`.

**Bounded edge divergence.** A clone whose copies simply stop agreeing — one file's
statement continues where the other's ends, with nothing beyond it that still matches — was
previously reported as its exact core only (plan §1 Stage D3 originally required a gap to be
*internal*). It is now also flagged when the continuation is asymmetric: one side's
remainder past the shared span is small (at most `⌈RATIO · span⌉` tokens, the same 15%
budget the aligner already verifies matches against) while the other side's is not.
The ranges reported are the short side's remaining tail (which may be empty) and the long
side's tail truncated at the same bound — never "the rest of both files", which is what
every clone would otherwise "diverge" into without the bound. Two clones that merely
continue differently, both by more than the bound, are deliberately **not** flagged (a
paired negative fixture, `tests/fixtures/probes/edgeneg_*.php`, pins this down). Measured
directly against the real case that motivated it — `CurrencyController`/`TagController` in
Firefly III, where `Currency` later gained an endpoint `Tag` never received — the two
remainders (127 tokens, 49 tokens) both exceed the derived bound, so the mechanism does not
fire on that specific pair; the pair is still recovered as gapped, through an unrelated
internal one-token divergence extension already finds inside the shared constructor. This
is recorded rather than adjusted for: the bound is derived from `BandedAligner::RATIO`, not
tuned to this pair.

**Pairs-to-classes.** A seed-and-extend engine naturally reports pairs; N copies of one
block give N(N−1)/2 of them, counted as if they were N(N−1)/2 separate findings. Locations
now merge into **sites** (same file, ≥80% range overlap), candidates become **edges**
between sites, and each connected component is reported once — sized by its first member in
sorted order (so a `CodeClone`'s line excerpt never reads past the end of a shorter copy),
gapped or reordered if any of its edges was. On phpunit this took the reported-vs-union
duplication ratio from 6.2× (M1, uncorrected pairs) to 1.44× (M2, classes) — the residual is
overlapping classes counted individually where a line union counts them once, a smaller and
separate question from the quadratic pair count this item removes.

**`CodeClone::isReordered()`** distinguishes a Type-3 reordered finding from a Type-3 gapped
one in the public API and JSON output (`"reordered": true`, present only when true, so every
other engine's output is unchanged byte for byte) — the two were previously both just
`"gapped": true` with no way to tell them apart from outside the engine.

**Honest limits, carried forward from the design rather than discovered after shipping:**
sub-K statement swaps (out of contract for any seeded method; TokenBag's own
`tests/fixtures/r1` fixture — 7-token swapped statements — is the documented case, and M3's
permutation recall curve is where the loss gets measured rather than guessed at);
approximate (non-exact) far sides of a divergence, which gapped extension does not cross;
edits denser than roughly one per 12 tokens against the 16-token seed, which is a genuine
detection ceiling, not a bug (`tests/fixtures/probes/dense_*.php` pins the documented miss);
and — now bounded rather than open — duplication inside a single highly self-repetitive
file, addressed by the shift-banding, disjointness and bijection fixes recorded below.

What remains there is a **genuine recall loss**, and the subsumption gate
(`bench/check-superset.php`) still fails on `bench/corpus/phpunit`. An earlier draft of
this entry called it a granularity disagreement that the gate merely could not see; that
was wrong, and the correction matters. The standard methodology for comparing detectors
that group differently (Bellon et al., *Comparison and Evaluation of Clone Detection
Tools*, IEEE TSE 33(9), 2007) is deliberately containment-tolerant — a candidate clone may
be larger than the reference — but it matches **clone pairs**: reference fragment 1 against
candidate fragment 1 *and* reference fragment 2 against candidate fragment 2. Where
Rabin-Karp reports lines 34 and 104 of `MetadataTest.php` as two fragments, the unified
engine puts both inside its *one* 2,658-line fragment. Under the field's own criterion that
is a miss, and the gate is implementing it correctly.

The two relations are also not the same fact. Block *i* ≡ block *i+1* implies half ≡ half
transitively; half ≡ half does not imply block *i* ≡ block *i+1*. The fine relation is
strictly the stronger one, so reporting only the coarse one discards information that
cannot be recovered from it.

The right output is neither the coarse clone nor Rabin-Karp's 113 pairs (which is the
blow-up the pairs-to-classes pass exists to prevent): it is **one clone class naming every
block**. Getting there took the consumption fix above and the symmetric site rule below,
which between them take the gate's residual from 366 items to 66; what is left is recorded
with its measurement in `docs/research/audit/M2-report.md`.

**Wall-clock:** the diversity floor recovers most of the second view's cost (phpunit: ~40s
interim → 3.8s; php-parser: ~18s → 1.7s; symfony-string: ~6s → 0.05s), the chain-as-witness
rule below removes the quadratic alignment entirely from every candidate whose own chain
already proves it, and the per-file occurrence cap below takes another 1.5–3× off. The M1
gate (unified ≤ the default pipeline, `bench/check-walltime.php`) still does **not** pass:
phpunit 2.01s vs 0.85s (0.42×), php-parser 0.51s vs 0.19s (0.38×), symfony-string 0.039s vs
0.029s (0.76×).

That is stated as failing rather than netted out. At the interim measurement the engine was
also partly fast because it was wrong — one oversized candidate consumed a whole file's
anchors and was then dropped, so the work of reporting that file's clones was never done.
phpunit now reports 369 clones against 118, and 68,399 duplicated lines against 345,385.

The gate itself now lives in **M3**, where it already existed as "unified ≥ default-pipeline
speed at every size" measured at four corpus sizes (M2 audit ruling F): M2's scope is
correctness and classification, and holding a correctness milestone open on a performance
number is what produced the constant-sweeping this release's history records once already.

**Deviations from the plan, recorded rather than silent:** Stage D1's extension is direct
comparison at each flank/gap (longest common prefix, suffix, and a bounded interior search).
Divergent ranges come from the chain's own geometry, not a DP traceback. `BandedAligner` is
the plan's banded Levenshtein DP, but it verifies Stage D4 rather than performing Stage D1's
extension — reading boundaries back out of a DP would need a full traceback matrix to
re-derive exactly the exact runs direct comparison already finds. `src/CloneDivergence.php`
is a value object the plan's file layout did not originally name; it now does.

### Changed — the index's frequency cap counts occurrences per file, which is the number that was generating the work

`POSTINGS_CAP` counts a fingerprint's postings across the whole corpus, and that is an axis
which cannot bound the work. The anchors a fingerprint contributes to a *file pair* are the
**product** of its counts in the two files, not their sum, so what generates the work is how
often it repeats inside one file. On `bench/corpus/phpunit` a single fingerprint occurring
9,179 times across 13 files — 706 per file — accounts for **42.1 million of the 42.4
million** anchor pairs the normalized view generates, 99.4% of the total from one
fingerprint; the corpus-wide cap fires on it and still admits the half a million pairs 1,000
postings imply. On `bench/corpus/php-parser` the worst offender sits in *two* files at 160
occurrences each, where no cap counting distinct files can reach it at all: a
document-frequency cutoff removes 0.3% of anchor generation there, against 68.6% for an
occurrence cutoff at the same rank.

`FingerprintIndex::PER_FILE_CAP` therefore bounds occurrences kept per fingerprint per file,
at **C = 32**, and is counted and surfaced exactly as `POSTINGS_CAP` is —
`discardedPostingCount()` measures against what was actually kept rather than against either
constant, so the tally the strategy reports stays truthful whichever cap did the discarding.
Neither cap is a theorem: both are recall trades, and both are reported rather than assumed
away.

**How the value was chosen, since a constant here needs more than a measurement.** Granted
by the M2 audit (ruling D, 2026-08-31) after being measured and *reverted* once for want of
one. The class of constant was already admitted — `POSTINGS_CAP` has sat in the plan's
constants table since M0 as a counted recall trade with no theorem behind it either — so
what was at issue was the value, and specifically whether it had been tuned against a
failing gate. It was not. The metric is the union of source lines the engine reports,
restricted to clones dense enough to be code rather than a chain across a gap (≥ 4 tokens
per line), on phpunit:

| per-file cap | 8 | 16 | 32 | 64 | none |
|---|---:|---:|---:|---:|---:|
| dense lines covered | 1,116 | 1,097 | **2,741** | 2,254 | 2,296 |
| all lines covered | 43,206 | 42,732 | 49,222 | 51,913 | 51,287 |

Thirty-two is the knee, and carries *more* dense coverage than no cap at all, because
bounding the runaway occurrences also stops the oversized candidates that used to consume a
cluster's evidence and then be refused. **A tighter cap is not free**, which is the argument
against the value that speed would have preferred: at C = 8, `Assert.php`'s three-site,
572-line class disappears entirely and `BuilderTest.php`'s eight-site, 974-line class
fragments into 75-line pieces — both confirmed by inspection. Subsumption against Rabin-Karp
cannot arbitrate this, because it only asks whether the baseline's exact contiguous clones
are covered and is blind to the Type-3 and same-file classes such a cap breaks; by that gate
alone C = 8 looks like an improvement (187 unreported locations against 217).

Wall-clock **worsens** at 32 against the 8 that was swept — phpunit 0.73× → 0.45×,
php-parser 0.85× → 0.38× — which is the clearest evidence available that the value is not
tuned to a gate. Against the uncapped engine it is still a large win: median of five runs,
phpunit 3.65s → **2.01s**, php-parser 1.66s → **0.52s**, symfony-string 0.097s → **0.040s**.

Subsumption on `bench/corpus/php-parser` stays 3/3; on phpunit the unreported-location count
moves 217 → 219, and the packet records that dense coverage is the metric that sees the
failure mode this cap exists for.

`IndexCodec::VERSION` is bumped 4 → 5: a cached fingerprint list from before the cap is not
the list the same bytes produce now, and a cached entry carries no record of which rule
selected it.

### Fixed — two clone sites are the same place only if they agree on the *longer* of them, so a block inside a bigger span is no longer swallowed

The pairs-to-classes pass decides when two reported ranges in one file are one **site**. It
measured their shared extent against the **shorter** range, which makes containment score a
perfect 1.0: a 378-token block sitting inside a 14,584-token span was "the same place" as
the span, was absorbed into it, and stopped being reported at all. Every block of a
self-repetitive file collapsed into the file.

Two ranges are now one site when they share at least `SITE_OVERLAP` of the **longer**.
That is a derivation and not a threshold change: "same place" is an equivalence relation,
and a relation that holds between a range and something merely containing it is not one —
it is not symmetric. The ordinary case the rule exists for is untouched, because there the
two ranges are nearly the same size (one block found twice, ten tokens longer the second
time: 100 shared of 110 = 0.91, still one site); containment now scores 0.026 and stays two.
`SITE_OVERLAP` itself is unchanged at 0.8. M2 audit ruling G, 2026-08-31.

**Scope, because the same predicate answered three different questions.** Site identity is
one; "has this candidate already been reported by the other view?" and "is this the same
duplication the other view saw?" are the other two, and those are *covering* questions where
containment is the answer wanted rather than a defect. They keep the shorter-range rule,
under its own name (`subsumes()`). Making all three symmetric was measured and rejected: it
makes the pass emit the same material twice on a second alignment — probe 4's reordered
class gains a duplicate of its own displaced block — and costs phpunit 369 → 401 reported
clones and 68,399 → 95,985 duplicated lines to recover 20 baseline locations.

**What it recovers**, on `bench/check-superset.php` against `bench/corpus/phpunit`: the
residual falls from **366 items to 66** — unreported baseline locations 219 → 32, unexplained
pairs 147 → 34. `bench/corpus/php-parser` stays 3/3. The gate still fails, and the remainder
is measured rather than patched: **63 of the 66 are `MetadataTest.php` against itself**, the
same-file fine-granularity case the ruling anticipated. The other three are two shapes, both
single instances — one file pair where the engine reports a differently-aligned clone
between the same two files, and one 70-token pair whose coverage turns out to have been an
artefact of the over-wide site this fix removes (the only residual item the change *adds*).

Reported findings rise — phpunit 166 → 369 clones, php-parser 21 → 34, symfony-string 13 →
17 — which is the direct consequence of no longer folding a smaller finding into a larger
one that contains it. Whether the finer relation is worth its volume is a precision
question, and the next milestone's two-rater audit is where it gets answered rather than
asserted.

### Changed — a cluster stops being mined once its best chain is too short to be a clone

Clones are extracted from a cluster in rounds: take the best-scoring chain, remove the
anchors it consumed, ask again. A round whose candidate fell under `--min-tokens` or
`--min-lines` used to skip to the next round; it now ends the cluster.

Each round's anchors are a strict subset of the one before, so every chain a later round
could build was already available to this one, and this one returned the best-scoring of
them. Once that chain is too short to be a clone, the rounds that follow are asking the same
question of strictly less evidence. Measured on phpunit, one 228-anchor cluster ran **147
rounds and produced nothing**; the pattern repeats across the clusters that dominate the
profile, which are the pairs against heavily self-repetitive files.

Stated as what it is: the chainer's score is coverage minus gaps, not span, so a
lower-scoring chain could in principle reach further by spanning wider gaps. This is a bound
on the ordinary case rather than a theorem, so its cost is measured instead — and on both
corpora it loses no reported clone. `bench/check-superset.php` is unchanged on phpunit (217
locations, 147 pairs, exactly as before) and still passes 3/3 on php-parser. Over-reporting
falls slightly: 53,309 → 51,317 duplicated lines on phpunit.

Wall-clock, five runs: phpunit 5.53s → **3.58s** (−35%). php-parser and symfony-string are
unchanged, which locates the win precisely — it is the self-repetitive-file shape, not a
general speed-up.

### Changed — a file pair is chained only once it has shown one guaranteed-detectable run's worth of agreement

Profiling the engine over PHPUnit's own suite put **77% of its running time in one place**:
building candidates from the *normalized* view. The reason is fan-out, not any single slow
step. The normalized view reaches **23,717 file pairs** against the raw view's 1,487 —
sixteen times as many, because normalizing identifiers makes short, shallow agreement
common — and **91% of those pairs yield no clone at all**. Everything downstream of the
anchor set is spent on them.

A pair is now skipped unless its anchors add up to at least `S = ⌈min-tokens/2⌉` tokens,
summed over both files. That is not a new constant: `S` is the winnowing guarantee's own
threshold, the run length at and above which a common run is *guaranteed* to be seeded, and
so the unit this engine's evidence is denominated in. A pair whose anchors do not add up to
one such run, across the whole of both files, has not shown a single guaranteed-detectable
run's worth of agreement in total. `Winnower::guaranteedRunLength()` already computed it.

The cost is measured, not assumed. On phpunit the floor removes 62% of the normalized
view's pairs and 52% of the raw view's, and of the 2,152 normalized pairs that do produce a
candidate it removes 5 — none of which survives to be reported. **Reported output is
unchanged**: 166 clones and 53,309 duplicated lines on phpunit before and after, and
`bench/check-superset.php` on `php-parser` still passes 3/3.

Wall-clock, five runs each: phpunit 6.27s → **5.53s**, php-parser 1.92s → **1.67s**,
symfony-string 0.056s → **0.043s**. This is not a proof — extension can recover runs shorter
than `S` that no anchor carries — so the subsumption gate is what holds it honest.

It was also **not on its own enough to pass the wall-clock gate**. The filter removes the
pairs that are cheap to process; what remained was concentrated in the pairs carrying
thousands of anchors each, which is what the entry above went after.

### Fixed — a candidate that is about to be refused no longer carries the evidence away with it

Chains are extracted from a cluster repeatedly: take the best, remove the anchors it
consumed, ask again. A same-file candidate whose two ranges overlap is refused — it
describes one region read against a shifted view of itself — but it was refused *after*
`withoutSpan()` had already let it claim its whole span, so every duplication inside it was
consumed and thrown away with it.

That is not a rare shape. In a file built from many near-identical blocks, block *i*
matches block *i+1* for every *i*, so a single shift's anchors run the whole length of the
file on one perfectly colinear diagonal; the chainer has no grounds to stop, and returns a
candidate spanning nearly the whole file against nearly the whole file. On phpunit's
`MetadataTest.php` that consumed 19 of the raw view's 24 shift bands — each one spent on a
candidate that was then discarded.

Such a candidate now consumes only as far as its own shift, which is the most two copies
that far apart could ever span without running into each other, and the rest is left for
the next round. It is also refused *before* verification rather than after, since there is
nothing to verify about a region against itself and the alignment it would otherwise be put
through is the engine's one quadratic step.

Measured: more sites recovered per repetitive file, and wall-clock unchanged at corpus scale
(phpunit 6.6s → 6.3s) — the extra rounds are paid for by the alignments no longer run.

**This does not by itself fix the phpunit subsumption gate**, and the reason is a second,
independent mechanism, recorded here rather than left to be rediscovered:
`classes()` merges two sites when they share `SITE_OVERLAP` of the *shorter* range, so a
378-token block contained in a 14,584-token site scores 1.0 and merges into it. Granularity
therefore collapses at class-formation time regardless of what the candidates look like.
Changing that rule governs all class formation, cross-file included — it is the pass
credited with M1's 84,556 → 13,735 duplicated-line correction — so it is not folded in here.

### Fixed — a file's self-duplication is grouped by *shift*, so a repetitive file no longer swallows its own clones

Two copies in one file sit some distance apart, and that distance — the shift between the
two positions — belongs to the duplication, not to the anchor: edits between the copies
move it a little, nothing else moves it at all. Anchors at very different shifts are
therefore evidence about *different* pairs of copies, and until now they were chained
together anyway.

In a file built from many near-identical blocks — a generated parser's productions, a test
class with one method per case — that file matches itself at every multiple of its own
period, so one cluster arrived holding shifts p, 2p, 3p, … at once. The chainer answered
with the only reading that treats them as one duplication: a span covering nearly the whole
file against nearly the whole file, whose two "copies" overlapped each other by thousands
of tokens. The damage was not the nonsense report — it was that the candidate then claimed
every anchor in the cluster, so the genuine block-against-block duplication inside was
consumed and never found. On phpunit's `MetadataTest.php` that was 9,286 anchors spent on
one candidate and **every** clone in the file lost; the engine reported nothing there where
Rabin-Karp reports 113.

A file compared against itself is now clustered once more, by shift. The tolerance is the
aligner's own edit budget for that shift, so it is derived rather than tuned: anchors whose
shifts differ by more than `⌈RATIO · shift⌉` could not survive one alignment together in
any case. Cross-file anchors are **not** banded — two files share no origin for their
offsets, so a shift between them means nothing, and a genuine cross-file reorder is
precisely a chain across shifts.

`MetadataTest.php` now reports the duplication that is actually there: lines 33–2691 against
3054–5712, which a line diff confirms is 89% identical.

### Fixed — the index version stands at 5: the seed-pair cap changes no stored byte

`IndexCodec::VERSION` was bumped 5 → 6 alongside the seed-pair cap below, because the ruling that
granted the cap said to. The executor recorded a dissent with the bump rather than silently
skipping it, and the close audit upheld the dissent (M3 close ruling Q): the cap applies at
enumeration time, after the index — a version-5 entry decodes to exactly what a fresh run
selects, and the seeding rule is applied at run time whether the cache is warm or cold, so the
hazard the previous two bumps guard against (replaying entries a current run would not have
selected) cannot occur. Invalidating every user's warm cache for a change that alters no stored
byte and no selection rule buys nothing; the version returns to 5. A version-6 entry written
during the bump's brief window is rejected by the mismatch and rescanned once, harmlessly.

### Fixed — the seed bound counts the pairs a fingerprint yields, not the places it occurs

The unified engine could not finish a large, repetitive application at all: 600 files of one
exhausted the default 1 GB memory limit inside `AnchorSet`, and raising the limit to 3 GB did not
help. The cause is a bound on the wrong quantity. `FingerprintIndex::POSTINGS_CAP` limits a
fingerprint's posting **list** to 1,000 entries, while Stage C pays for the **pairs** that list is
enumerated into — C(1000, 2) = 499,500 seed pairs from one fingerprint, 14.7 million across the
corpus. That is the same product-versus-sum mistake the per-file cap fixed one level down: a
bound on a sum cannot bound a product.

`AnchorSet::SEED_PAIR_CAP` restates the bound where the cost is, at **16,000 pairs per
fingerprint**. The value is the dense-coverage knee, chosen the same way `PER_FILE_CAP` was — the
union of reported source lines restricted to clones dense enough to be code (≥ 4 tokens per
line), swept on phpunit:

    cap      1,000    2,000    4,000    8,000  *16,000*   32,000  uncapped
    dense    8,614    8,661    8,964    8,951   *9,036*    9,036     9,036

Sixteen thousand is the smallest cap that reports every dense line an uncapped run reports. It is
chosen there and nowhere else: not on wall-clock, which it makes *worse* (3.4 s at 1,000 against
4.2 s uncapped), and not on the memory failure, which is checked afterwards. On the application
corpus that motivated it, dense coverage is identical at every cap from 1,000 to uncapped —
that corpus's duplication is literal data tables rather than dense code — so what the bound buys
there is that the engine finishes: the 600-file slice that exhausted 1 GB now peaks at 744 MB.

Past the cap a fingerprint's later postings pair only with its earliest ones. That is the recall
trade, and it is a bounded one: every posting still appears in a pair, so every place a runaway
fingerprint selects is still seeded against a representative copy of that region, and the
pairs-to-classes pass closes the relation transitively over what those pairs report. Like both
posting caps it is never silent — `pairCappedFingerprintCount()` and `discardedSeedPairCount()`
report it, and two tests pin the accounting. The index format version is **not** bumped for this
change — an accompanying bump to 6 briefly existed and was reverted by the close audit (its own
entry below), because the cap changes no stored byte and no selection rule.

Recorded under M3 audit ruling N. php-parser subsumption stays 3/3, phpunit's residual is
unchanged, and recall inside the guaranteed region stays at 295/295. `--rk` is no longer needed
as the workaround for large application codebases.

### Fixed — the precision audit reports per-engine precision for *both* raters, not just the first

`bench/audit-precision.php score` computes Cohen's κ from two filled worksheets and then reported
precision — overall and per engine — from the first worksheet alone. That leaves the M3 gate's own
comparison ("precision not below the better of RK/TokenBag on the audited set") resting on one
rater's reading while κ is quoted from two, which is precisely the arrangement the second rater
exists to prevent. Each rater is now reported the same way, and one key file serves both
worksheets because they are the same pool rated twice.

### Fixed — two equally-scored chains are settled by coverage, not by which one the sweep reached first

The chain score is coverage minus what each junction skips, and two different readings of one
anchor set can reach exactly the same number. The recurrence then had to pick one, and picked
whichever the sweep happened to reach first — an iteration-order accident presented as a rule.

It was a measurable one. A 51-token function with one statement inserted matches for 49 tokens
and then again for the 2-token tail; joining the tail adds 2 tokens of coverage and costs a
2-token gap, so it ties **exactly**, and the tie went to the chain that leaves the tail out. The
clone was reported at span 49 — one token under `--min-tokens 50` — and refused, at similarity
0.962. With the edge recovery above, this is the whole of the guaranteed-region gate's remaining
failure: recall inside the guarantee goes from 291/295 to **295 of 295**
(`bench/run-recall.php`).

Between equal scores the reading covering more matched tokens is now taken. Score stays strictly
primary — no lower-scoring chain is ever preferred — and coverage is a number the recurrence
already carried for the reorder test, so this completes the order rather than adding a criterion,
and introduces no constant. A residual tie goes to the lower anchor index, so the chain remains a
function of the anchor set alone. `bench/check-chaining.php` is new and re-runs the M2
brute-force oracle — every colinear subset of 3,000 seeded anchor sets, both weightings — against
the refined tie order: 6,000 comparisons, no mismatch, and 84 of the sets held a score tie
between chains of different coverage, so the new rule is actually exercised. It also fails
against the old tie order, which is the point of keeping it.

**One measured cost, traced rather than tuned away.** On phpunit the Rabin-Karp subsumption
residual grows by one item, from 21 to 22, and the cause is an interaction rather than the tie
rule itself. In `assertArraysAreEqualIgnoringOrderTest.php` against
`assertArraysAreIdenticalIgnoringOrderTest.php`, a 193-token exact run ties at score 193 with a
three-anchor chain covering 311 tokens across 118 tokens of divergence; the wider reading is now
taken, spans 389 tokens, and fails the 0.85 acceptance. A candidate refused for low similarity
still consumes its whole span from the anchor set — `withoutSpan()` runs before verification, and
the M2 fix for this covered only the self-overlapping case — so the two exact clones inside it
are never found again. Recorded, with the mechanism, as work needing its own ruling: the fix is
to return a similarity-refused candidate's evidence the way a self-overlapping one's is returned,
which is a mechanism change and not this ruling's to make. php-parser stays at 3/3.

Recorded under M3 audit ruling M(b).

### Fixed — a clone's outer edges are recovered whole, because the run there has nothing to bridge to

Gapped extension recovers exact runs by direct comparison, and refused any run shorter than
four tokens. That floor is a statement about **gaps**: a spurious run of `);` or `return $this;`
inside a gap bridges two regions that are not otherwise related, and buys coverage across a
divergence with material that recurs everywhere. At a clone's two outer flanks there is nothing
on the far side to bridge to — a flank run either extends the candidate's own edge or it does
not exist — and the plan's own Stage D1 says so, specifying "longest common prefix and suffix at
each flank" with no minimum attached while the gap-interior search carries one.

Measured, this is the shape it was losing: a 51-token function gains one inserted statement, 49
tokens match contiguously, and the two-token tail after the insertion is real matched material
that the floor discarded. The candidate was then reported at span 49 — one token under
`--min-tokens 50` — and refused, at a token-level similarity of 0.962.

The floor is unchanged and still governs every gap; only the two flanks are exempt, which is
the gap between the specification and the implementation closed rather than a constant loosened.
Recorded under M3 audit ruling M(a). On its own this recovers the material without yet moving
the guaranteed-region gate — the recovered tail is dropped again by an exactly-tied chain, which
ruling M(b) settles; phpunit goes from 590 clones to 588, the Rabin-Karp subsumption residual is
unchanged at 21 items, and php-parser stays at 3/3.

### Added — the precision audit can restrict itself to code that is actually part of the program

`bench/audit-precision.php --wired-only` runs the corpus through this project's own
`Orphans::detect()` first and drops every entire-file orphan before any engine sees it, with
`--preset=` seeding the orphan detector the same way the CLI does. Reading every `.php` file on
disk and calling all of it "the corpus" measures the wrong thing: duplication among dead files,
generated caches and scratch copies is a different question from duplication in live code, and
mixing them produces a precision number that answers neither.

The effect is out of all proportion to its size. On a private application corpus, **146 of
3,731 files (3.9%) were entire-file orphans, and dropping them removed 30% of the unified
engine's findings and 77% of Rabin-Karp's** — orphaned files are disproportionately duplicated,
which is exactly what dead and copy-pasted code is.

Dropping orphans is not sufficient on its own, because a generated file declares no symbol and
so gives the orphan detector nothing to mark. The scan corpus therefore also honours the
**preset's own exclude list** — this project's existing answer to which files are a framework's
predictable noise — instead of `bcb_files()`'s four directory names, and `--exclude=` adds
whatever else a corpus needs. Together these matter more than anything else in this tool: an
even-strided 400-file slice of that corpus was 110 files of one dumped static-analysis cache,
and the pooled finding count falls from 897 to **74** once caches and scratch trees are out —
which is the "~60 findings" the plan expected all along. Every earlier number was measuring the
corpus's housekeeping.

Two smaller corrections ship with it. `--max-files=` now samples on an even stride over the
sorted tree rather than taking a prefix: the first 200 files of that corpus in sorted order were
196 files of one scratch directory, which would have made the audit a study of that directory.
And `--engines=` names which engines contribute to the pool, because on that corpus the suffix
tree needs 199 s for 50 files against the unified engine's 0.63 s and does not finish 100 in ten
minutes — an audit that names its engines is honest, one that hangs is not.

The worksheet is also **blinded**: which engines reported a finding now goes to a companion
`.key.tsv` that `score` reads and raters do not see, because a rater who can tell that only the
engine under test found something has stopped judging the code. `score` uses that key to report
precision per engine with its own Wilson interval, which is what the M3 gate compares.

### Documented — the unified engine can exhaust memory on a large, repetitive codebase

Not a behaviour change; a limit found by M3's measurement pass and now written down where a
user will meet it. `AnchorSet` enumerates one seed pair per pair of postings sharing a
fingerprint, which is quadratic in the posting count, while `FingerprintIndex::POSTINGS_CAP`
bounds the posting *list* at 1,000 rather than the 499,500 pairs a list that long yields. The
cap's stated job is to guard against boilerplate, and against boilerplate-by-volume it does
nothing.

Measured on a private 3,730-file application: 60 files scan in 0.7 s at 30 MB; 200 files take
21 s at 566 MB (16,251 → 358,939 raw seed pairs); 600 files reach 2.3 million raw and 14.7
million normalized seed pairs and exhaust 3 GB without finishing. The public benchmark corpora
do not show it — PHPUnit's 2,870 files scan in seconds — because the blow-up follows a
corpus's internal repetition, not its file count. README now says so and points to `--rk` for
large application codebases. The fix is a constant's derivation rather than an implementation
detail (the bound has to be stated on pairs, not on postings), so it is surfaced for a ruling
rather than taken here.

### Added — recall curves for the density-parameterized injector families

`bench/run-recall.php` scores the two operator families M0 added — `gapped_{insert,
delete,substitute}_d{1,2,3}` and `permute_{adjacent,distant}` — across every fetched
corpus, and reports two curves: recall against edits per 100 tokens, and recall against
permutation distance. Every engine is scored side by side, TokenBag included, because
the permutation curve is what decides whether TokenBag's removal costs anything real.

The **guaranteed region** is reported as a gate rather than a curve point, and split in
two, because two of the design's rules meet there and do not agree. Winnowing guarantees
that an exact common run of at least S = ⌈min-tokens/2⌉ tokens is *seeded*. Acceptance is
a separate rule: a candidate is reported only at token-level similarity ≥ 0.85. A single
divergence can be large enough to leave a well-seeded run on each flank and still take the
pair under the similarity threshold — deleting a 19-token statement from a 93-token
function leaves a 38-token exact run at similarity 0.796. So the region is reported as
"seeded **and** within the acceptance contract" (the gate) and "seeded but outside it"
(the RATIO rule working as designed, counted and shown rather than folded into either
number). Both the longest surviving run and the similarity used for that split are
recomputed by plain textbook dynamic programming over the token signatures, never by
`BandedAligner` — the component that decides whether to report a pair does not get to
answer whether it should have.

### Added — the wall-clock check measures a size sweep, not just whole corpora

`bench/check-walltime.php` gains `--sizes=60,200,600,2500`. The M3 gate is "unified at or
below the default pipeline at **every size**", and a single whole-corpus number cannot
answer that: the two engines have different shapes, and a ratio that holds at 2,500 files
says nothing about 60. A size is the first N files of the directory in sorted order, so
the same N is the same file set on every run and between engines, and a size larger than
the corpus is measured once rather than twice under two labels.

### Added — a precision-audit harness for the two-rater protocol

`bench/audit-precision.php` does the mechanical half of the precision audit plan §2 M3
asks for: `pool` runs every engine over a corpus, merges findings that name the same
places into one item to rate, samples them on an even stride over a sorted pool (so the
worksheet is reproducible and is not all one directory), and writes a worksheet carrying
the rubric and the source excerpts. `score` reads one or two filled worksheets and reports
Cohen's κ, per-rater precision, and Wilson intervals — Wilson because the sample is small
and the proportions sit near 1, where the normal approximation puts its bounds outside
[0, 1]. Given one worksheet it reports precision and **refuses to invent a κ**, since a
single rater has no inter-rater agreement to measure.

The dogfood corpus is closed source, so the tool is built to keep it that way: the corpus
directory is an argument and is never defaulted or recorded, findings carry an opaque
per-run salted digest instead of a path, and the worksheet — which necessarily quotes
source — **refuses to be written anywhere inside this repository**. Only aggregate numbers
are ever copied out of it.

### Fixed — a file's repeated block is reported as one class naming every copy, instead of being discarded as "periodicity"

A file built from many near-identical blocks lines its own anchors up on one diagonal, and
the chain that results overlaps itself: block *i* matched against block *i+1* for every
*i* at once, spanning nearly the whole file against nearly the whole file. Such a chain is
not a clone — a stretch of code is not a duplicate of a shifted view of itself — and it was
therefore thrown away. But it is not noise either, and throwing it away discarded the
duplication it was made of: on phpunit's `MetadataTest.php`, twelve of the raw view's
twenty-two shift bands produced no clone at all, including the band carrying the file's own
repeat period.

A chain aligning `[s, s+L)` with `[s+D, s+D+L)` says that token *p* matches token *p+D*
everywhere it reaches: the region has **period D**, in the elementary sense (Lothaire,
*Combinatorics on Words* — the definition, no theorem required). A region of length `L+D`
with period `D` *is* a run of `⌊(L+D)/D⌋` blocks of `D` tokens that agree pairwise. So the
chain is restricted to **one period step** — the anchors matching one block against the
next, which the extraction loop was already computing in order to advance — and
re-classified. Those two ranges are `D` apart and at most `D` long, so they are disjoint by
construction: an ordinary clone pair, verified on its own evidence like any other. The loop
then steps to the following block, and the pairs-to-classes pass closes the consecutive
relation transitively into one class naming every copy.

Nothing here is a new rule and no constant is introduced: the period is read off the
candidate, the number of blocks falls out of the loop, `--min-tokens`/`--min-lines` decide
whether the blocks are large enough to report, and the site-identity threshold is untouched.
A shift of zero — a region against itself at no distance — has no period to decompose and is
refused as before.

On phpunit this reports `MetadataTest.php`'s repeat period as a single 17-site class of 75
lines, and takes the Rabin-Karp subsumption residual from 66 items to 21 (`bench/check-superset.php`);
php-parser is unchanged at 3/3. It costs reported volume — the finer relation is strictly
more findings than the coarse one it replaces — and that is the point: block *i* ≡ block
*i+1* implies half ≡ half transitively, while half ≡ half implies nothing about adjacent
blocks.

### Fixed — extension no longer matches a file against itself at offset zero

`AnchorSet` generates a same-file pair only for `posA < posB`, "which is what keeps a run
from being found against itself at offset zero". Gapped extension did not honour that rule:
it recovers runs by direct comparison at a chain's flanks and inside its gaps, and on a file
compared against itself the two sides are the same string, so every one of those searches
matched trivially at zero offset.

The identity runs it produced were not harmless. Chained together they formed a whole-file
candidate at shift 0 that consumed a shift band's anchors and reported nothing, which is
what stopped the periodic walk above after a single step — the band carrying
`MetadataTest.php`'s repeat period yielded one clone where it holds thirty-six blocks.
Extension now applies Stage C's own exclusion, and the walk runs to the end of the region.

### Fixed — a same-file clone must occupy two different places, not merely start in two

The check that two copies in one file are in different places compared their two start
positions. That admits a range against a shifted view of *itself* — overlapping ranges,
which is a statement about the file's periodicity and not about duplication. The guard now
tests the ranges for overlap rather than the starts for equality. The banding above keeps
the chainer from assembling such a candidate in the first place; this is the check that the
rule holds of what is finally reported, whichever route the candidate took.

### Fixed — the reorder test now measures a bijection, so a repetitive file is not "a reorder of itself"

Reordering claims the *same* material appears on both sides in a different arrangement, and
"the same material" is a bijection: a token of A is the copy of one token of B, not of nine.
The test measured coverage on the A side only — effectively asking "is each token of A
matched *somewhere*?" — and in a file of many near-identical blocks the answer is yes for
every token, to every other block, however the two spans are drawn. Coverage then reached
θ by arithmetic rather than by evidence, and any two halves of such a file were "a reorder
of each other": `MetadataTest.php` scored 28,394 of 30,945 tokens that way.

`ChainBuilder::matchedCoverage()` replaces the plain coverage for this test. It builds the
pairing greedily, longest anchor first, over a byte mask per side, counting a token only
when its position is still free on *both* sides — so the correspondence stays one-to-one and
the figure cannot be inflated by repetition. Anchors are counted token by token rather than
taken or refused whole: an anchor overlapping an earlier one by a few tokens still attests
the rest of itself, and discarding all of it would put the figure below the colinear chain
it is compared against.

### Fixed — the chain is accepted as its own witness, and one file's verification drops from 118s to 0.5s

The chain is already an alignment. Between two spans it interleaves exact runs with gaps,
and each gap can be closed by substituting across the shorter side and inserting or deleting
the difference — `max(gapA, gapB)` edits, never more. So the chain *witnesses* an edit
distance of at most the sum of those maxima, and when that sum is within the budget the pair
is accepted with no dynamic program run at all.

This is not an estimate standing in for the aligner. It is a bound in the direction that
decides the question: what the DP could still do is lower the distance further, and the
verdict cannot change. A candidate whose gaps *exceed* the budget still goes to the aligner,
because there the chain proves nothing — the DP can match material inside a gap that Stage B
never seeded and extension did not reach, and that is exactly when its answer is not already
known.

The aligner doubles its band up to the budget, costing `O(RATIO · span²)`. On phpunit's
`MetadataTest.php` that was nine alignments of 12,000–25,000 tokens — 60 seconds of dynamic
programming, every one returning `accepted`, every one already proved by its own chain.
That file now verifies in 0.47s instead of 118s, reporting the same clone.

### Added — orphan detection recognises class names the code *constructs*

A second audit of a live Laravel monolith put **64 of 94** reported dead symbols — **68%** — into
one family: classes whose name is never written down anywhere, because it is assembled at runtime
from a filename or from a base class plus a suffix. Reference detection asks whether a name is
mentioned; for these there is no mention to find, so the tool correctly saw nothing and incorrectly
said "safe to delete". Acting on the report would have removed wired code.

Nothing here is application-specific: every rule is written against a **framework idiom**, over
`token_get_all` streams, with no parser and no new dependency.

**`discovery` — a directory scan that instantiates by filename (52 of the 64).** A base class globs
a directory, derives a fully-qualified name from each filename, checks `class_exists`, instantiates,
and registers the instance in a map a front controller dispatches through. All three token signals
are required — an `__DIR__`-anchored `glob` / `scandir` / `DirectoryIterator`, a `new $var` or
`$var::`, and a `class_exists(` guard — because each alone is ordinary code.

The loop's own glob **pattern** then decides which files it reaches, so a class sitting in a
discovered directory that the pattern does not select stays reported. That guard is the point: a
rule keyed on the directory alone would have made the count drop just as far while swallowing the
genuinely dead class beside the wired ones, which is exactly what the audit found when a directory
was excluded instead.

Suppression, never `--exclude`: the symbols stay counted, `--explain` names the rule *and* the
`file:line` of the discovering loop, and `--no-suppress=discovery` puts every one of them back.
A glob over a path not built from `__DIR__` is documented out of scope — with no anchor there is
nothing in the tokens that says which directory on disk is read.

**`convention` — a companion class named by suffix (12 of the 64).** A trait resolves a companion
class by concatenating `static::class` (or `class_basename(static::class)`) with a literal suffix,
so a model `X` is paired with an `X<Suffix>` whose name is written nowhere. The suffix is registered
against the type that declared it, and both halves of the claim are then required before a symbol is
spared: a class `X` must exist in the scanned set, *and* it must actually `use` the trait that
declared the suffix. Drop either and the rule degenerates into "any class ending in `Translation`",
which spares the dead ones too — so a companion whose base does not exist, and one whose base exists
but does not use the trait, both stay reported.

Two literal shapes are read — `. 'Translation'` and `. config('x.suffix', 'Translation')`. A suffix
with no literal anywhere is documented out of scope: inventing one would mean suppressing by
class-name pattern, which is what every rule here exists to avoid. `--explain` cites the
concatenation site inside the declaring trait, and names the base class and the trait in the reason.

**`config` now reads `config/*.php`, not only the declarative formats.** Laravel's native config
format is PHP — `config/*.php` returns an array of `Provider::class` entries and quoted
fully-qualified names — and `.php` was absent from the sweep's suffix list. Measured on the audited
monolith: the config rule suppressed **exactly one** symbol repo-wide, while the wiring it should
have found sat in those arrays. A scan is normally pointed at `src/`, so `config/` is outside the
scanned source and its `::class` entries are not code references the collector ever sees either.

Both citation styles resolve — the sweep matches on name shape, so `Foo::class` and `'Ns\Foo'` are
the same signal — and each symbol moves from **possible** (a name seen in some string) to
**suppressed** with a `file:line` config citation, which is the tier split people act on. Only
directories named `config/` are swept, and that restriction is the rule rather than an optimisation:
every `.php` file is already read as source, so sweeping them all as configuration would promote
every string literal in the project to a config registration. The extra walk is why this rides the
existing lazy `NameSweep` — at most once per run, and only when a symbol actually reaches the rule.

**Not in scope, recorded for later.** The lower-priority half of the same family — validation `Rule`
objects, facade accessors, and container bindings assembled from config arrays — is left reported.
It needs value-flow reasoning a token pass cannot do safely, and none of it reached the volume the
three patterns above did.

### Added — `--algorithm=unified`, stages A–C

The first three stages of the engine meant to replace the three the tool ships
(`docs/research/unified-engine-plan.md`). Stage D — extension across gaps, chaining,
classification — is not built yet, so the engine currently reports exactly its gapless
anchors: the subset Rabin-Karp already finds. That is deliberate, so this much can be
validated against an engine whose answers are known before anything harder is layered on.

**How it works.** Encode reuses the existing tokenizer and its packed five-bytes-per-token
signature. Stage B fingerprints every 16-token k-gram with `xxh3` and **winnows** them
(Schleimer, Wilkerson & Aiken, SIGMOD 2003): in every window of W consecutive fingerprints
the minimum is kept, rightmost on ties. Stage C grows each shared fingerprint into its
maximal exact match with `substr_compare`, binary-searching the mismatch, and deduplicates
by (file pair, diagonal, end).

The point of winnowing is that sampling ~10% of positions cannot lose a clone — it is a
theorem, not a tuning result. Any common run of at least W + K − 1 tokens spans a whole
window in both copies, so both select the same minimum and share a fingerprint. W is
derived from `--min-tokens` so that W + K − 1 = ⌈min-tokens/2⌉, which by pigeonhole is
the shortest exact run a clone of `--min-tokens` with one divergence must contain. The
guarantee is tested directly, over synthetic pairs with a run planted at a known offset,
at five thresholds; measured selection density matches the published 2/(W+1) bound
(9.51% against 9.52% at `--min-tokens=70`, the default when it was measured).

**Two knobs, not six.** The unified engine reads `--min-tokens` and `--min-lines` and
nothing else. `--fuzzy` and `--type-anchored` are not modes here, `--edit-distance`,
`--head-equality` and `--min-similarity` become derived constants in Stage D, and K, W and
the postings cap are derived rather than exposed. It refuses `--min-tokens` below 38 with
an explanation rather than degrading quietly: below that the winnow window drops under 4
and the index stops being a sample.

**It is order-stable, which none of the three current engines is.** File ids are assigned
from the sorted path list, so reversing the file list changes nothing downstream. Verified
on 2,870 files: byte-identical across repeated runs, across a reversed list, and across
two CLI invocations.

**Incremental.** `--incremental --algorithm=unified` caches each file's encoding *and* its
selected fingerprints (index format version 2), both being pure functions of the file's
bytes and the configuration. Touching one file in a 2,868-file corpus recomputes exactly
one file, and the resulting report is byte-identical to a cold run on the same tree.

**Measured against the pipeline it replaces** (5 runs each, median, in-process wall clock):
1.83× faster on phpunit (2,870 files), 1.44× on PHP-Parser, 2.40× on Symfony, 3.01× on
symfony/string.

**Subsumption is checked, and the baseline loses the disagreements.** Every location
Rabin-Karp reports is reported by the unified engine, on the fixture suite and on three
corpora. Where the two disagree about a clone's *length*, `bench/check-superset.php`
recomputes what the two files actually share — a token-by-token walk, no hashing, no
windows — and that decides. On phpunit, 90 of 242 baseline pairs turn out to be
Rabin-Karp over-reports, every one resolved in the unified engine's favour, none
unexplained. The cause is structural: Rabin-Karp keys its hash table on the *first* file
to register a window, so a run of matching windows that continues only because a third
file matches further is still attributed to that first file at the run's full length. On
the type-3 fixtures it reports 62 shared tokens between two files that share 58.

### Fixed — a clone's excerpt is read once per file instead of once per clone

`CodeClone` computes its identity as the md5 of its own text, which means reading its
first file. On a corpus where one 5,782-line file carried 6,961 of 7,115 reported clones,
that was 6,961 reads of the same file: 1.09s of the 1.13s spent building clones. A
two-entry cache of file contents — reports arrive grouped by first file, so two entries
suffice — cuts it to 0.03s with byte-identical output on every corpus and engine. The
default pipeline benefits too: phpunit went from 1.58s to 1.16s.

### Added — a benchmark harness that verifies itself, and two new injector families

Groundwork for the `unified` detection engine (see `docs/research/unified-engine-plan.md`).
No engine code yet: this is the measuring apparatus, built and proven *before* there is
anything to measure, because the alternative has already been tried here.

**`bench/harness.php` — the measurement primitive.** In-process wall-clock deltas from
`hrtime()` (monotonic: an NTP step cannot hand back a negative duration), and a
subprocess runner that owns its own deadline via `proc_open` on an argv list. Two rules
are structural rather than conventional:

- a timeout is a fact the harness *causes*, never one it infers. `BCB_TIMEOUT` is
  reachable only from the branch that decides the deadline passed and kills the child.
  No exit status, no stderr text, and no missing binary can produce it;
- exec is an argv list, not a shell string, so the kill reaches the actual subject
  instead of a wrapper shell that leaves it running past its own timeout.

`bcb_run_parsed()` adds the third: a zero exit whose output does not parse is demoted to
a failure, so a tool whose output format drifts breaks the benchmark visibly instead of
contributing a row of zeroes.

**`bench/self-test.php` — the gate.** 24 checks that make the harness do the bad things
on purpose: a run that really exceeds its deadline (and a heartbeat file proving the
child is dead afterwards, not orphaned), a run that exits 3, a binary that does not
exist, a throwing in-process measurement, and a clean run with unparseable output. It
also checks the mirror image — that a run *inside* its deadline is not called a timeout —
because a harness that reported timeouts unconditionally would pass the rest. Run it
before recording any number: `composer bench:verify`.

This exists because of a specific failure. An earlier benchmark drove its subject
through the `timeout` binary, which macOS does not ship. Every run exited 127 without
executing, every one was recorded as a timeout, and the resulting ">575x slower" figure
was used to justify deleting the `suffixtree` engine — a decision later reversed. The
self-test asserts, by name, that exit 127 is a failure.

**`bench/injectors.php` — gapped-edit and permutation operator families.** At function
granularity, parameterized by density:

- `gapped_{insert,delete,substitute}_d{1,2,3}` — d statement edits per clone at evenly
  spaced interior positions, leaving a matching head and tail. This is the family a
  seed-and-extend detector is structurally weakest against, and the point is the recall
  *curve* against edit density, not a summary number;
- `permute_{adjacent,distant}` — statement swaps at a recorded distance, the Type-3
  reordered case.

The operators report what they did, not just the mutated text: every edit carries its
byte offsets in both files and the exact bytes before and after. The five E2 operators
are untouched and `bcb_operators()` still returns exactly those five, so E2's published
results keep meaning what they meant; the new families are reached through
`bcb_all_operator_names()` or `inject.php --ops all`.

Statement segmentation is the hard part, and four constructs broke it before they were
handled — each found by generating variants over ~4,100 real third-party files and
parsing every one: `for` headers (two semicolons at body level), interpolated strings
(`"{$x}"` closes a brace that ends nothing), arrays of closures (a `}` before every
comma), and `$this->{$name}` (a `{` that opens a name, not a block). The final sweep
generates 20,480 variants with zero parse failures, and each construct now has a
regression case in `tests/InjectorsTest.php`.

**`bench/check-manifest.php` — byte-for-byte verification.** Re-derives every variant by
calling the same `bcb_inject()` entry point that produced it and compares against the
file on disk, then checks sha256 digests of both files, that the manifest records every
edit the operator makes, that each edit's before/after bytes are at their recorded
offsets, that the variant parses, and that `is_clone` is labelled correctly. An empty or
missing manifest fails rather than passing vacuously.

**`bench/check-determinism.php`.** Identical input must give byte-identical output:
the same file list twice, the same files reversed, and two full CLI runs byte-compared.
It refuses to pass on a corpus with no duplication in it, since two empty reports are
byte-identical and prove nothing.

Running it recorded a pre-existing property worth stating plainly. On 348 files of
`vendor/symfony`, all three current engines are stable across repeated runs and across
repeated CLI invocations, but **none is stable under reversal of the file list**:
`rabin-karp` finds the same clone pairs and exchanges which copy it names first, while
`tokenbag` — and therefore the merged default — returns a different set of clones. The
CLI is unaffected, because `FileFinder` sorts before detection; this is reachable only
by an embedder passing its own unsorted list to `Engine::detect()`. It is recorded here
as the baseline the `unified` engine has to beat, not fixed in this change.

**Restored:** `bench/` (absent from the working tree), `phpunit.xml` and `phpstan.neon`.
`bench/lib.php` was ported from the removed `Arguments` class to `StrategyConfiguration`
and `Engine`; the other pre-2.0 runners are stale against the 1.4 API and are not
rewritten, so `phpstan-bench.neon` lists the files held to level max rather than
analysing the whole directory. `composer check` now runs both PHPStan configs.

### The rest of this release

Every remaining item was found by adopting 1.4.0 on a 382k-line Laravel modular monolith
(no `app/` directory; all source under `packages/`). Each is covered by a regression
test in `tests/Regression/ScanCorrectnessTest.php`.

They share one shape, which is why they are released together: **a wrong answer that
reads as a right one.** A clean gate over a contaminated corpus, a clean gate over 2%
of the source, or a live class named as safe to delete. None of them looked like a
failure in the output.

### Kept — the `suffixtree` engine, after a removal that was reversed

It was deleted and then restored within the same development cycle. The reasoning
is recorded because the mistake is instructive, not because the outcome changed.

**What was measured, and holds:** the engine does not scale. Benchmarked against the
default engine on a real corpus, warm cache, wall-clock — 67 files: 0.10 s vs 1.58 s;
208 files: 3.60 s vs >300 s (killed); 571 files: 0.50 s vs >300 s (killed). It is not
usable as a default, which is why it remains opt-in behind `--algorithm`.

**What was claimed and was false:** an earlier benchmark reported ">575x slower" using
a `timeout` command that does not exist on macOS. Every suffix-tree run in it failed
instantly with exit 127 and was mislabelled a timeout. The engine was removed while
that number was the stated justification.

**What the removal actually cost:** the `[inconsistent]` / `gapped: true` classification
— a clone whose copies have diverged, one patched and its sibling not. On the Type-3
fixtures, TokenBag matches the suffix tree's *span* (15 lines on the insertion case) but
reports `gapped: false`: it sees the overlap and cannot see the divergence. Nothing else
in the tool provides that signal, and the claim that "it found nothing the default engine
missed" was drawn from three hand-written fixtures — too little evidence for the weight
it was given.

**Unvalidated, and worth knowing:** on a 2,540-file corpus Rabin-Karp reports 16 clones
and TokenBag 24, agreeing on only 4 of 32/48 locations. TokenBag's precision has not been
hand-audited. A bag-of-tokens matcher is order-invariant and therefore structurally
less precise than a position-aware, edit-bounded one; that trade has not been measured.

### Fixed — restored third-party attribution that the fork had stripped

Verified against `sebastianbergmann/phpcpd` itself. The suffix-tree detector was **not
original to phpcpd**: it was a PHP port of the ConQAT toolkit (CQSE GmbH / TU München,
**Apache License 2.0**), and upstream retained ConQAT's authorship markers in four
files — `@author $Author: hummelb $`, `@author Benjamin Hummel`, `$Author: kinnen $`,
`$Author: juergens $`, each with its `@ConQAT.Rating` hash.

This fork had removed all of them, leaving Apache-2.0-derived code with no attribution
to its actual authors. Method-name overlap with upstream measured 8/9, 5/8, 7/7 and
15/15, so the derivation was not in question.

The attribution is restored: `NOTICE` names CQSE/TU München, `LICENSE` carries the
Apache-2.0 clause for that subtree, and `composer.json` declares
`(BSD-3-Clause AND Apache-2.0)`.

**This provenance has not been re-verified in the current cycle** — it rests on an
earlier comparison against upstream. `NOTICE` records that explicitly. If the suffix
tree turns out to be original work of this project, the Apache-2.0 obligation
disappears and the package returns to single-license BSD-3-Clause; that is a
determination to make from evidence, not from assumption in either direction.

The lesson is recorded because it generalises: a header that looks unusually bare may
have been stripped rather than written that way. Check upstream before assuming a file
is yours to relabel.

### Changed — the resolution pipeline is one fold, and four inherited files are gone

`Arguments`, `ArgumentsBuilder`, `ArgumentsBuilderException` and the getter-based
`StrategyConfiguration` — all carrying upstream code — are **deleted**, replaced by
original code written against the option specification rather than derived from the
old implementation.

What they cost: one option lived in **five places** that had to agree — its parse
definition, a builder local carrying a second copy of its default, a `switch` case, a
constructor parameter carrying a third copy, and a getter. And the headless facades
paid the same bill twice over: `Phpcpd::detect()` re-implemented preset seeding by
hand and then forged a fake 28-parameter CLI `Arguments` just to hand the engine its
seven thresholds.

The replacement is `Settings`, built on three ideas:

- **Defaults are property initializers — the only copy.** Asymmetric visibility
  (`public private(set)`, PHP 8.4+) makes every property publicly readable and
  internally writable: the immutability the 28 getters bought, minus the 28 getters,
  enforced by both the runtime and PHPStan.
- **Resolution is a fold.** A preset seeds first (it is a layer, not a position —
  it seeds identically wherever the flag appears), then every `[option, value]`
  pair from config files and the command line is applied in order by one `match`
  with exactly one arm per option and **no silent default arm**: an option defined
  without a binding throws, and a test folds every defined option so that throw can
  only ever fire in CI, never on a user. The layering rule is not implemented
  anywhere — it falls out of fold order.
- **Every entry point goes through the same fold.** CLI argv, `phpcpd.ini`,
  presets, and the headless `Phpcpd::detect()` / `Orphans::detect()` facades all
  translate to option pairs and resolve identically, verified by a test that
  compares a `fromArgv()` run against the equivalent fold field by field.

Consequences for the headless API (`@api`, still 2.0.0):

- `minLines` / `minTokens` now default to `null` = "the engine default", so the
  defaults are declared once instead of once per signature.
- `suffixes` / `exclude` now **append** to the preset's seed exactly like
  `--suffix` / `--exclude`, where the facade previously *replaced* the seed —
  the CLI and the facade used to disagree about the same inputs; they cannot now.
- An unknown preset throws `SettingsException` (the new resolution error,
  replacing `ArgumentsBuilderException`) instead of `InvalidStrategyException`.
- `StrategyConfiguration` is a plain readonly record of the seven values the
  engine may see — constructed via `Settings::strategy()`, deliberately without
  defaults of its own.

Behavior parity was verified against captured oracles (`--help`, `--show-config`
with a preset, a clone scan, an orphan scan) and a 2,540-file real-world scan:
identical output, identical counts.

### Changed — the config and template sweeps are read on demand, and at most once

`ProjectContext` swept the config tree (`.neon`/`.yaml`/`.yml`/`.xml`/`.dist`) and the
template tree (`.blade.php`/`.twig`/`.latte`/`.tpl`) at construction, opening and
scanning every one of those files before classification began. Both maps are consulted
**last** in the decision tree — a symbol reaches them only after a code reference, an
author tag (`@api` / `@phpcpd-keep`), the manifest, the namespace rule and the fixture
rule have all declined. On a healthy project almost nothing gets that far, so almost
every run paid for two full filesystem sweeps and read neither answer.

Each map is now a `NameSweep`: described at discovery with the roots and excludes that
call resolved, run on first read, and memoised. `OrphanDetector` also consults the maps
one rule at a time, so a symbol wired in a config file never provokes the template
sweep. A run in which no symbol reaches the lookup does **zero** sweep I/O; a run in
which any number of symbols reach it sweeps each tree exactly once.

Measured on a synthetic corpus with **zero** unreferenced symbols — 400 mutually
referenced classes, 600 config files and 300 Blade templates, 5.1 MB, warm cache,
`php phpcpd --orphans`, three alternating rounds of 15 runs each:

| | fastest run | slowest round's fastest run |
|---|---|---|
| before (eager) | 141.4 ms | 159.5 ms |
| after (lazy) | 96.9 ms | 105.0 ms |

Roughly 45 ms, or 30%, of a 145 ms run — all of it spent answering a question nobody
asked. (Minimums are quoted because medians on this machine drifted 105–151 ms between
rounds under background load; the before/after gap held in every round.) Output is unchanged: a clone scan and an orphan scan (`--explain`, and with
`--no-suppress=config,template`) over this repository, over the synthetic corpus, and
over a 1,900-line real-world orphan report are byte-identical before and after, and two
consecutive runs remain byte-identical to each other.

`ProjectContext` keeps its public surface — `$context->configNames` and
`$context->templateNames` still read as `array<string, string>` and are still
write-protected. The class itself is no longer a `readonly` *class* (a hooked property
cannot be readonly); every stored property is individually `readonly`, and the two maps
are get-only virtual properties. `manifestNames` is unchanged: it comes from
already-parsed composer data and costs no sweep.

Covered by `testTheNameSweepsRunOnlyWhenConsultedAndOnlyOnce` (both halves: never-swept
and swept-once) and `testALazilySweptConfigNameStillSuppressesTheSymbol` in
`tests/Regression/ScanCorrectnessTest.php`.

### Fixed — a code generator was mistaken for generated code

**This is the real cause of the "a `use` import plus a constructor type-hint is not
counted as a reference" report, and the diagnosis in that report was wrong.** The
reference was never missed. The file making it was never *scanned*.

`isGenerated()` searched the first 2 KB of every file for `@generated`, `do not edit`
or `auto-generated` as raw bytes. A *generator* contains the banner it writes **into the
file it emits**, as a string literal in a method body —

```php
'# AUTO-GENERATED, do not edit by hand.'
```

— so the generator was silently dropped from the scan. Dropping a file drops its
declarations *and every reference it makes*, which is how a repository
constructor-injected one line below that string reached the **definite** orphan tier
that gates CI.

Measured across the adopting codebase: **8 PHP files dropped, 3 of them wrongly.** The
other two false positives were ordinary prose in comments — a property docblock reading
`Marker prefix used to identify auto-generated options`, and a note recording that only
a generated type list referenced a removed route. A file that *mentions* generated code
is not generated code.

- Markers are now matched **only inside comment tokens**, never in code or string
  literals. A byte search over the head is what a grep would do, and this project
  already rejects that everywhere else.
- Markers must **open a line or a sentence**. `// Generated from metrics.afm. Do not
  edit by hand.` is a banner because "Do not edit" opens a sentence; `identify
  auto-generated options` opens nothing. This is the same anchoring rule, for the same
  reason, that `SymbolCollector::docTag()` applies to docblock tags.
- Verified against all 8 files: the genuinely generated ones are still dropped; all 3
  false positives are now scanned.
- **The scope line now reports files skipped as generated.** Same argument as the
  unreadable-directory count: a content sniff that drops a file drops its references
  with it, so a wrong drop must be a number that moved rather than silence.

```text
Scanned 2540 files (1 directory, 21 exclude patterns applied, 4 files skipped as generated).
```

### Fixed — `--orphans` stayed quiet when it covered only *some* autoload roots

Found by running the tool against the codebase that produced these reports. A scan of
`packages/` in a modular monolith covers 60-odd psr-4 prefixes and looks complete —
while the same `composer.json` also maps `database/seeders`, `database/factories` and
`tests`, which is exactly where the top-level wiring that references those packages
lives. Measured: **`packages/` alone reported 163 orphans; the full set of autoload
roots reported 158.**

The warning added above could not catch this. It keyed on `manifestApplies`, which is
true as soon as *one* root is covered — the right question for "does this scan belong to
the project", the wrong one for "can this scan see every caller".

`--orphans` now warns when any autoload directory that exists on disk was never opened,
and names them:

```text
Warning: --orphans cannot see the whole project.
  3 autoload directories declared in /path/composer.json were never opened:
    /path/database/factories
    /path/database/seeders
    /path/tests
```

### Fixed — a preset silently scanned almost nothing on a non-standard layout

`--preset=laravel` scans `app routes database config`. On a modular monolith with no
`app/` and no root `routes/`, only two of those existed — so the run scanned **61 of
2,626 files** and printed `No code clones found`, which is indistinguishable from
*"I did not look."* This is the same failure mode the default-excludes work was written
to prevent (a gate that passes for the wrong reason), arriving via preset paths instead
of cache contamination.

- Preset-declared scan paths are checked against the filesystem before the scan, and
  the missing ones are named:
  `Warning: preset 'laravel' declares 4 scan paths; 2 do not exist (app, routes).`
- Only preset-supplied paths are checked. A path you typed is your own claim about your
  own project.
- When *every* declared path is missing the run already exits non-zero via
  `No files found to scan`; the warning covers the partial case, which was the silent one.

### Fixed — `--show-config` omitted the scan paths and mislabelled the preset

Found while trying to verify the item above without running a scan, which turned out to
be impossible.

- **Scan paths are now printed**, first in the table, with non-existent ones marked
  `(missing)`. For a preset this is the single most important resolved value and the one
  a user most needs to check; previously `--preset=laravel` could only be understood by
  running it and reading the file count.
- **The preset is now a named layer** (`preset:laravel`), sitting directly above the
  built-in defaults where it actually applies, with its values attributed to it. They
  were previously reported as `(*) default`, which was false — `*.blade.php` and
  `database/migrations` are not in the base default set. A report that names the wrong
  layer is worse than one that names none, and this is the case the feature exists for.
- A repeatable option no longer repeats one layer's label once per contributed value
  (`preset:laravel + preset:laravel + ...` nine times over).

### Fixed — Blade-only references were invisible to orphan detection

`*.blade.php` is in the Laravel preset's excludes, correctly: templates are repetitive
by nature and would flood a clone report. Applied to *reference* detection it does the
opposite of its purpose — it hides the call sites. A class invoked by fully-qualified
name directly in a view was reported a **definite** orphan, the tier that gates CI,
because the file holding the reference was never opened.

- Clone detection and reference scanning now read **different file sets**. Clone
  detection keeps its excludes; reference scanning additionally reads `.blade.php`,
  `.twig`, `.latte` and `.tpl` for symbol mentions, never treating them as scannable
  source for clones.
- An exclude is dropped from the reference scan only when it blinds templates
  *specifically* — it matches a template filename but not a plain `.php` one — so
  `vendor` and `demo` still prune and only patterns like `*.blade.php` step aside.
- New `template` suppression rule, counted and listed like every other, citing the
  template file and line. Audit it with `--no-suppress=template`.

### Fixed — seeders and factories were reported as definite orphans

Laravel discovers seeders by directory and class-name convention, so no seeder class
name appears anywhere in code. Measured: **24 live seeders and factories** in the tier
that gates CI, where acting on the finding deletes a working database bootstrap.

- The `entrypoint` rule now recognises classes declared in a `Database\Seeders` or
  `Database\Factories` namespace, matched as a namespace **suffix** so a modular project
  namespacing them `Acme\Database\Seeders` is covered too.
- Keyed on the namespace, not the class name: this stays a structural claim about where
  the code lives, the standard every other suppression rule meets.

### Fixed — a brace-less existence guard suppressed an unrelated later declaration

`if (!class_exists('X')) require ...;` left the pending block kind set to `guard` and
stamped it on the next unrelated `{` — a `foreach`, a `try`, any block that does not
announce its own kind. A type declared inside that block was then read as living in an
existence guard and **suppressed as a polyfill**: a real orphan turned into silence.
Pending block state is now cleared at every statement boundary.

### Added — the run states its scan root, and refuses a runaway one

`phpcpd /` is almost always a typo for `phpcpd ./`. The two differ by one character and
several million files, and the old behaviour announced the difference with a wall of
permission warnings followed by a fatal error — the user learned about the mistake from
a stack trace rather than from the tool.

- A scan root of `/`, or one resolving above the nearest `composer.json` / `phpcpd.ini`,
  is **refused** with a one-line explanation. `--allow-root-scan` proceeds anyway: this
  is a guard, not a wall.
- Every run now prints its **resolved scan root** above the file count. A wrong root is
  the fastest route to a wrong total, and it was the one thing the scope line never said.
- The scope line reports **unreadable directories** when there are any, completing the
  2.0.0 unreadable-directory fix: a run that covered less of the tree than the caller
  believes now says so instead of reporting a clean result over a partial scan.
- Paths no longer carry the doubled separator a root scan produced (`//usr/bin/sudo`),
  which was the thread that led a reader to the unreadable-directory bug in the first place.

### Added — `--orphans` warns when it cannot see the whole project

An orphan verdict is a claim that **nothing** references a symbol, which is a claim about
the entire project. Measured: scanning a single controller directory reported nine live
controllers as "whole file is unwired" — every one imported from a route file in a
different package, outside the scanned path.

`--orphans` now warns when every scan root sits below every directory `composer.json`
maps. `phpcpd --orphans src/` in an ordinary package stays quiet, because `src/` is what
the manifest maps.

### Documented — what the duplicated-line percentage measures

It counts **copied tokens, not repeated design**. A 1,132-line controller whose seven
methods each repeat the same six-step sequence reported `1.06% duplicated`, with both
reported clones correct — while being the largest refactor candidate in the codebase.
Each method spelled the shared sequence with its own names and arguments, so at the token
level there was almost nothing to match. A reader trusting the number would have cleared
the file. Now stated plainly in the README.

### Fixed — one unreadable directory aborted the entire run

`FileFinder::walk()` let `RecursiveDirectoryIterator::__construct()` throw
`UnexpectedValueException` out of `getChildren()`. A single permission-denied
directory anywhere beneath the scan root killed the process: no report, no partial
result, no usable exit code. `phpcpd /` reproduced it every time, dying on
`//usr/sbin/authserver` after a wall of `fopen()` warnings.

The two failure paths were inconsistent — a file it could not read already degraded
to a warning and continued, while a directory it could not read was fatal.

- Unreadable directories are now **pruned in the filter callback** and counted.
- `RecursiveIteratorIterator::CATCH_GET_CHILD` is set as a backstop for races and
  exotic filesystems.
- New `FileFinder::skippedDirectoryCount()` exposes how much of the tree could not
  be read, so a run that covered less than the caller intended can say so instead of
  reporting a clean result over a partial scan.

### Fixed — `.phpstan` was not excluded, only `.phpstan.cache`

1.4.0 added default excludes precisely so a tool cache could not turn a gate green.
It listed `.phpstan.cache`. PHPStan's `tmpDir` is user-configured, and the shorter
`.phpstan` is just as common — so the mitigation missed the case it was written for.

Measured on the adopting project, whose `phpstan.neon` sets `tmpDir: .phpstan`:

| | reported | actual |
|---|---:|---:|
| clones | 483 | 46 |
| duplicated lines | 231,112 | 2,216 |
| duplication | 15.52% | 0.58% |
| total LOC | 1,489,224 | 382,145 |

1.1 million of the counted "lines of code" were generated
`nette.configurator/Container_*.php`; the largest single reported clone was 5,917
lines of it. The `@generated` / `Do not edit` content sniff did not catch these
files either — PHPStan's containers carry no such marker.

- Both spellings of every tool cache are now excluded by default: `.phpstan` and
  `.phpstan.cache`, `.psalm` and `.psalm-cache`, `.rector` and `.rector.cache`,
  `.phpunit.cache` and `.phpunit.result.cache`.
- The general rule this encodes: where a directory name is user-configured, matching
  only the documented default leaves the other spelling scanning as source.

### Fixed — internals

`FileFinder::find()` had been separated from its own docblock by the unreadable-directory
change above, erasing the `list<string>` types from its signature and cascading **14
PHPStan level-max errors** through `Application`, `Orphans`, `Phpcpd` and `ProjectContext`.
Reattached; `src/` and `tests/` are clean at level max.

### Added — `bench/check-provenance.php`, the ruling-S inventory as a measured number

`MODERNIZATION.md` states the inherited-surface inventory in prose, and prose goes stale between the
commit that changes the tree and the person who remembers to re-count. This script computes the same
statement from the tree as it stands: every `.php` file under `src/`, the copyright holders named in
its **header block**, and the share of files and lines that carry no attribution but this project's.

It was wrong about the tree the moment it was written, which is the point. `MODERNIZATION.md` records
19 attributed files and 85.5 % original; the measurement says **18 files and 83.0 %** at the same
commit. `CLI/Application.php`'s header had already been flipped and the document had not been
re-counted. The percentages differ in both directions because the file count and the total both moved.

Three deliberate properties:

- **Only the header block counts.** A `(c)` line inside a docblock further down is documentation, not
  ancestry — `CLI/Application.php` still discusses its upstream in a comment, and a script that
  grepped the whole file would call it inherited forever. Pinned by a self-test fixture.
- **The second licence's scope is read from `NOTICE`, not restated.** `NOTICE` says the Apache-2.0
  grant covers `src/Detector/Strategy/SuffixTree/`; the script extracts that path from `NOTICE` and
  partitions the inventory with it. If the two ever disagree, `NOTICE` wins, because `NOTICE` is the
  document a licensee reads. This surfaces one disagreement immediately:
  `Detector/Strategy/SuffixTreeStrategy.php` sits **outside** that directory, so `NOTICE` puts it in
  the BSD group while `MODERNIZATION.md` lists it under Apache-2.0. Recorded, not resolved here.
- **The line column is `lines in attributed files`, never `lines of upstream code`,** and the report
  prints that sentence next to the number. Measuring surviving upstream lines would mean diffing
  against upstream, which is precisely the discipline ruling S's replacement standard forbids.

`php bench/check-provenance.php self-test` runs the instrument against trees whose answer is known by
construction — all-original, all-inherited, mixed with the sizes crossed so a files/lines confusion is
caught, an attribution below the header block, a file with no header, and a `NOTICE`-scoped split —
and then asserts that a false claim in *each* direction is reported false. 13/13. The script exits
non-zero while the inventory is above zero, so it is a gate and not only a report.

### Added — `bench/check-log-equivalence.php`, the gate no reporter had

Nothing in `tests/` asserted a single byte any output format writes. The detector, the facts layer
and the presentation tier were covered; `Log\Text`, `Log\PMD`, `Log\AbstractXmlLogger`, `Log\Json` and
`Log\Sarif` were not, in any format, at any granularity. A reporter could have changed its output in
every run and the suite would have stayed green.

That gap is worth closing on its own, and it is also a precondition for ruling S's replacement
standard: "proven behaviour-identical by the gates" needs a gate that knows what the behaviour *was*.

The script builds the real `Findings` the CLI builds and captures exactly what each reporter emits —
`Text` stdout plain and verbose, and the files `PMD`, `Json` and `Sarif` write — over six cases: two
exact copies, a gapped Type-3 pair, renamed identifiers, a demote-stratum set, a literal table where
XML escaping meets data, and the empty report, which every format gets wrong differently.

Three decisions worth naming:

- **The unit is the reporter, not `phpcpd` the command.** A console run also prints a banner, a scan
  root, an orphan section and a wall-clock line, none of which belong to `Log\Text`. Folding them in
  would make goldens that break whenever `CLI\Application` changes, and would let a reporter
  regression hide inside a diff nobody attributes to it. It also means no timing or memory value is
  in scope, so nothing has to be normalised away.
- **`--capture` is a separate word, never a fallback for a missing golden.** A gate that writes the
  answer it failed to find is not a gate; compare mode reports the absence and exits non-zero, and
  does not even create the golden directory. Interpretation checklist rule 3.
- **The self-test runs on real reporter output, not on hand-written literals.** A literal comparison
  is decided at parse time and proves nothing about the instrument. It renders a real report twice
  and requires byte-identity, then mutates one line of that real output and requires the gate to name
  the line it changed. 11/11.

`Json` and `Sarif` are already original work and are not slated for rewrite. They are in the gate
anyway, because `AbstractXmlLogger` is not the only scaffolding the reporters share and a change that
quietly moved a shared behaviour would surface there first.

The `gapped` and `renamed` cases run at `minTokens` 38 rather than 30 because the unified engine
refuses anything below its derived floor of `2K + 6` instead of degrading quietly. The case moved;
the floor did not.

No goldens are captured yet, and the gate is failing by design until they are — capturing them
against a tree mid-refactor would prove a rewrite equal to itself, which is the failure the
golden-first ordering exists to prevent.

---

## [1.4.0] - 2026-08-23

### Fixed — scanning a cache directory could turn a failing gate green

Measured against a 63k-line project: pointing `--orphans` at the project root scanned
`.phpstan.cache/` along with everything else, and reported **0 orphaned, exit 0**. The correct answer
was **21 orphaned, exit 1**.

A static-analysis result cache embeds the fully-qualified name of every class it analysed as a string
literal, so it satisfied the reference check for 21 symbols that are genuinely unreferenced. It also
made the run 52× slower and used 21× the memory (4m 0.7s / 1100 MB against 4.6s / 51 MB). The cost
was visible; the wrong verdict was not, and nothing in the output hinted that a question had gone
unanswered.

- **Generated and cache trees are now excluded by default** — `vendor`, `node_modules`, `.git`,
  `.phpstan.cache`, `.phpunit.cache`, `.php-cs-fixer.cache`, `.psalm-cache`, `.rector.cache`,
  `var/cache`, `storage/framework`, `bootstrap/cache`, `build`, `dist`, `out`, `coverage`.
  Disable with `--no-default-excludes`.
- Defaults match whole path **segments**, unlike the substring-based `--exclude`, so a default named
  `out` prunes `out/` and never `routes/`.
- A file whose first 2 KB contains `@generated`, `Do not edit`, or `Auto-generated` is skipped
  wherever it lives.
- **Every run states its scope** — `Scanned 764 files (3 directories, 15 exclude patterns applied).`
  A file count wildly out of step with the project is what makes a contaminated run obvious.

### Fixed — the test guard blocked every runner but two

`tests/_guard.php` detected direct execution by asking whether the entry point was *named* `phpunit`
or `pest`. Every other runner — `paratest`, `infection`, a `phpdbg` run, an IDE run configuration,
any PHPUnit-compatible runner — was classified as direct execution and killed at `require_once` time,
before a single test ran. The runner then reported an empty or aborted suite rather than a reason,
which is the failure mode hardest to read: nothing failed, so nothing looks wrong.

The guard now compares `SCRIPT_FILENAME` with its own caller's path. Direct execution is exactly the
case where those are the same file, which is checkable without knowing any runner's name — correct
for every runner that exists and every one that does not exist yet. Same intent, same message. Both
branches are covered by tests.

### Added — suppression rules for structurally-explained symbols

Every finding in the measured run was a false positive with a structural explanation. Six rules now
recognise them. Each keys on a *structural* property — a guard statement, a namespace, a manifest
entry, a fixture path — never a name pattern or a guess about intent:

| Rule | Recognises |
|------|------------|
| `conditional` | Declared inside `if (!function_exists('x'))` and friends — a polyfill or shim, by definition declared for an external caller. |
| `namespace` | Declared outside every `psr-4`/`psr-0` prefix the project's `composer.json` declares. |
| `manifest` | An `autoload.files` entry point, or an FQN under `extra` (Laravel providers/aliases). |
| `config` | Named in a `.neon`, `.yaml`, `.yml`, `.xml`, or `.dist` file. |
| `fixtures` | Under a `Fixtures`/`Stubs` directory inside a test tree. |
| `keep` / `entrypoint` / `planned` | Docblock tags and framework attributes (previously silent). |

**A suppressed symbol is never dropped.** It moves to its own counted tier, always printed as a
census by rule and listed in full with `--explain`. A rule that starts over-firing therefore shows up
as a number that moved — a silent suppression would quietly turn a real orphan into no output at all,
which is the same failure shape as the cache-directory bug above.

`composer.json` is now read as a set of reference roots: `bin` entry points (conventionally
extensionless, so a `.php` suffix filter never saw the one file where top-level wiring lives) are
added to the scan, and a file with no recognised suffix is accepted when its `#!` line names php.

### Added — `@phpcpd-planned`, and reasons on tags

`@phpcpd-keep` asserts *this symbol is reachable, you just can't see it*. That is false for code
written ahead of the work that will wire it, and marking such code with a keep tag means nothing
prompts its removal later. `@phpcpd-planned` makes the opposite claim, and its symbols are reported
as their own group — a staged-work inventory derived from the source rather than from a tracker.

A `@phpcpd-planned` symbol that later becomes referenced is reported as a possible finding: the tag
has served its purpose and should be deleted. `@phpcpd-keep` can never give that prompt.

Both tags take a free-text reason, printed next to the symbol, so a suppression whose stated reason
has gone stale becomes reviewable.

### Added — clone suppression

There was previously **no way to declare a duplication intentional**; the only remedy was `--exclude`
on the whole file, which also hid the duplication worth fixing.

```php
// phpcpd-ignore-start ... // phpcpd-ignore-end
/** @phpcpd-ignore-clone Dispatch table — one arm per block type, by design. */
$x = $y; // phpcpd-ignore-line
```

A clone is dropped when any of its copies intersects a suppressed range. Markers are read only from
files that took part in a clone, so an unmarked codebase pays nothing.

### Added — `phpcpd.ini`

Per-project settings whose keys **are the long option names**, so there is no second vocabulary and
an option is configurable the day it ships. The file is found from the paths being scanned rather
than from the working directory, so `phpcpd ../other-project/src` picks up that project's settings.

Layered, each overriding the last: built-in defaults → `~/.config/phpcpd/phpcpd.ini` → project
`phpcpd.ini` → command line. Single-valued keys are replaced by the closer layer; repeatable ones
(`exclude`, `suffix`) append. `--config <file>` names one explicitly; `--no-config` ignores them all.
Unknown keys and invalid values are rejected by name, exactly as the equivalent flag would be.

### Added — `--show-config`

Layering is only trustworthy if it can be inspected, and a setting no file mentions keeps a built-in
default that appears in no file at all. `--show-config` prints every setting in force and names the
layer that produced it, marking fallbacks with `(*)`. A repeatable setting names every layer that
contributed, since those append rather than replace.

### Fixed — the default run and `--orphans` could disagree about reachability

Composer `bin` entry points were added to the scan in `--orphans` mode but not in the default run, so
a class instantiated only from an extensionless console entry point could appear in the default run's
advisory and not under `--orphans`. Entry points are now resolved once, for both modes.

### Added — findings grouped by cause, with evidence

The report repeated one of two sentences across every entry, so a reader had to re-derive each one by
hand. Findings are now grouped by cause, which makes the group worth reading — the symbols nothing
explains — visible instead of buried. A string-literal demotion cites **where** the name appears:

```text
→ never referenced in code; name appears in a string literal (possible dynamic use)
  ⤷ name appears at src/Support/Registry.php:23
```

### Added — `--fail-on`, `--no-suppress`, `--explain`

Rather than a flag per feature, three knobs named after what the report prints:

- `--no-suppress=<rules>` turns rules off by name (or `all`). A disabled rule's symbols are judged
  normally rather than skipped, which is what makes it a way to audit the rule itself.
- `--fail-on=<tiers>` chooses what gates CI; `dead` alone by default. `--fail-on=dead,planned` makes
  shipping staged, unwired components a conscious decision.
- `--explain` lists suppressed symbols instead of only counting them.

### Fixed — docblock tag matching was substring-based

`str_contains($doc, '@api')` fired on prose: `Unlike @api classes, this one is internal` silently
suppressed a real finding. Tags must now start a docblock line.

### Changed

- `OrphanResult` gained `suppressed()`, `planned()`, `tier()`, `entries()` and `fails()`.
  `all()`, `definite()`, `possible()`, `count()` and `isEmpty()` speak only about findings, so a scan
  that suppresses everything still reads as "no orphans".
- `Orphans::detect()` accepts `noSuppress`, `failOn` and `defaultExcludes`.
- `Symbol` replaces `$suppressed`/`$entrypoint` with `$rule`/`$ruleReason`; `Orphan` gained `$rule`
  and `$evidence`.

## [1.3.0] - 2026-08-18

### Fixed — orphan detection: block-structure tracking and aliased imports

Three unrelated PHP constructs desynced the symbol collector's context stack. A desynced stack
silently corrupted every declaration after it in the same file: methods were recorded as global
functions, and references inside skipped spans were lost — so live code was reported as a
**definite orphan**. Because orphans ride along in the default scan as an advisory, this affected
every run, not only `--orphans`.

- **Closure capture clauses** — `function () use ($x) { ... }` was treated as an import statement and
  skipped to the next `;`, which lands *inside* the closure body. Every reference in that span was
  lost, and because the skip bypassed the closure's own `{`, the stack stayed shallow for the rest
  of the file.
- **Anonymous classes** — `new class { ... }` did not open a *type* body, so its methods were
  recorded as global functions and a `use SomeTrait;` inside it was skipped instead of counted as a
  trait reference.
- **Curly-brace string interpolation** — `"{$var}"` popped a block level that was never pushed:
  `token_get_all()` emits an array `T_CURLY_OPEN` token for the opening brace but a plain `}` string
  token to close it.

A fourth, separate cause was found while re-measuring the survivors:

- **Aliased imports** — `use A\B\Original as Alias;` means the class is only ever written as
  `Alias`, so `Original` was never counted and a class used solely under an alias was reported as a
  definite orphan. Alias pairs are now resolved for single, comma-separated, grouped
  (`use A\{B as C};`) and `use function ... as ...` forms. An import whose alias is never used still
  counts nothing, so an unused import cannot mask a dead class.

Measured against third-party sources: Laravel `Illuminate/Database` went from 2150 symbols scanned
and 538 definite orphans to 249 and 13; `Illuminate/Support` from 494 and 144 to 147 and 21. The
findings that disappeared were phantoms — `__clone` and other methods reported as dead *global
functions* — plus the four grammar classes Laravel imports under an alias.

### Changed — an unreferenced trait is a *possible* orphan, not a definite one

A trait exists to be consumed by *other* classes, so a library ships traits for consumers that are
never part of the scan — Laravel's `HasFactory` and `HasBuilder` are the archetype. Traits now join
interfaces and abstract classes in the contract tier: still reported, but no longer failing the
build. Nothing is hidden — the total finding count is unchanged, only the confidence tier moves.
Across `laravel/framework` this shifts 13 findings, from 48 definite / 32 possible to 35 / 45.

Symbols that a source-only scan genuinely cannot resolve — a service provider discovered through
`composer.json`, a cast class named only in a downstream model — remain out of scope; `@api` /
`@phpcpd-keep` are the escape hatch for those.

Added `SymbolCollectorContextTest`, which pins each construct plus three regression guards (a
brace-delimited namespace import must stay un-referenced; `::class` must not be read as a
declaration; an unused aliased import must credit nothing), and a dogfooding invariant: phpcpd's own
`src/` declares no global functions.

---

## [1.2.0] - 2026-07-19

### Added — orphan detection (dead code)

- **Orphaned symbols** — top-level classes, interfaces, traits, enums, and global functions that
  nothing in the scanned set references. Same token engine, same zero-dependency, no-parser design as
  the clone side.
- **Two run modes**: orphans **ride along in the default scan as an advisory** (reported, but only
  clones set the exit code — safe for framework-heavy code where dynamic dispatch causes false
  positives); **`--orphans`** runs orphans-only and *gates* CI (a definite orphan → non-zero exit,
  exactly like a clone).
- **Explains *why* something is dead**, not just *that* it is:
  - **Whole-file "unwired"** — flags when every symbol declared in a file is itself an orphan (a
    stronger delete signal than one dead class among live ones). This is phpunused's "unreferenced
    file", done at symbol granularity.
  - **"Superseded copy of ..."** — reuses the clone engine: an orphan whose body duplicates a *live*
    symbol is annotated as the stale copy some refactor replaced but left behind. This is the
    orphan × clone synergy unique to phpcpd-next.
- **Two confidence tiers** (harvested from Psalm's `UnusedClass` / `PossiblyUnusedClass` split): a
  **definite** orphan is referenced nowhere and drives the exit code; a **possible** orphan is either
  a contract (interface / abstract class, which an out-of-tree package may implement) or a name that
  only appears in a string literal (a candidate for `new $class` / DI-container lookup) — reported for
  review, but does not fail the build.
- **Entry-point awareness** (harvested from shipmonk/dead-code-detector's usage providers): classes
  wired via framework attributes (`#[Route]`, `#[AsCommand]`, `#[AsEventListener]`, `#[Entity]`,
  `#[Attribute]`, …) and `*Test` classes are recognised as reachable and never flagged.
- **Suppression annotations**: `@api`, `@psalm-api`, `@phpstan-api`, `@phpcpd-keep`, and
  `@phpcpd-ignore-orphan` in a symbol's docblock mark it intentionally public / kept.
- **Better than a grep-based finder** (the phpunused niche, done right): because detection is
  token-based, a name mentioned in a comment or the declaration itself no longer masks a real orphan,
  and a name in a string is scored as a *weak* dynamic signal rather than a hard reference. Reference
  detection is deliberately generous — over-counting hides a real orphan (safe), under-counting would
  tell someone to delete live code (never).
- **Headless API** `LucianoPereira\PhpcpdNext\Orphans::detect()`, mirroring `Phpcpd::detect()`, plus a
  new `LucianoPereira\PhpcpdNext\Orphan\` subsystem (`SymbolCollector`, `OrphanDetector`, `Symbol`,
  `Orphan`, `OrphanResult`, `OrphanTextReport`). Covered by `tests/OrphanDetectorTest.php` (10 tests).
  Dogfooding the tool against `src/` immediately surfaced a genuinely dead exception class
  (`MissingResultException`).

## [1.1.0] - 2026-06-28

### Added — integrations

- **Headless mode** (`LucianoPereira\PhpcpdNext\Phpcpd::detect()`): a one-call, in-process API that
  finds files, runs the same engine the CLI uses, and returns the raw `CodeCloneMap` — no banner, no
  argv parsing, no file I/O. The CLI and all embedders now share a single detection core (`Engine`),
  so they can never disagree about what a clone is.
- **Framework presets** (`--preset=<name>`, and `preset:` in the headless API): a named bundle of
  paths, suffixes, and excludes — pure configuration, no runtime dependency. Ships with a **`laravel`**
  preset (scans `app routes database config`; skips `vendor`, `storage`, `bootstrap/cache`, `public`,
  Blade views, and migration boilerplate). Explicit flags seed-then-override the preset. New presets
  are a single `Preset` entry in `src/Presets.php`.
- **PHPUnit integration** (`integration/phpunit/`): an `AssertNoDuplication` trait and a
  `DuplicationConstraint` that turn copy/paste detection into a regression test, with offending
  locations (and `[inconsistent]` flags) printed on failure. Shipped in the **production**
  autoloader under `LucianoPereira\PhpcpdNext\PHPUnit\`, so it works for any project that requires
  phpcpd-next (even as `--dev`). phpcpd-next dogfoods it — `SelfDryTest` now keeps `src/` clean
  through this exact trait.
- **Laravel via Artisan**: documented (no extra package) by wiring the headless API into a command.

### Packaging & distribution

- **Published to Packagist** as `phpcpd-next/phpcpd`: `composer require --dev phpcpd-next/phpcpd`.
- `composer.json`: added `type`, `keywords`, and a `suggest` for `phpunit/phpunit` (the optional
  PHPUnit integration); moved the `PHPUnit\` namespace into the production autoloader.
- Added `.gitattributes` with `export-ignore` rules so the dist tarball ships only runtime code
  (`src/`, `integration/`, the binary), not tests, benchmarks, or tool configs.

### Tooling

- Committed a `.php-cs-fixer.dist.php` codifying the existing code style, so `composer lint` /
  `composer check` run non-interactively.

### Documentation

- Reworked the README to document the **full** feature surface accurately: the real default
  (Rabin-Karp + TokenBag) and `--rk`, all four output formats, the complete option reference split
  into stable vs. advanced/research flags, presets, headless mode, and the PHPUnit integration.

## [1.0.0] - 2026-06-27

### Performance

- **Banded edit-distance DP in the suffix-tree engine.** Profiling showed the approximate-matching
  DP — not construction — dominated `findClones` and grew super-linearly with `--edit-distance`. Since
  a cell `(i,j)` with `|i−j| > maxErrors` can never lie on a sub-threshold path, the DP is restricted
  to the diagonal band of width `2·maxErrors+1` (Ukkonen cutoff), turning the per-clone cost from
  `O(L²)` to `O(L·maxErrors)`. Measured **~3.5× faster** at every edit distance on a Firefly III slice,
  with **byte-identical** clone output.

### Fixed

- **Degenerate zero-line clones** are no longer reported by the suffix-tree engine. A clone whose
  in-file span collapsed to zero lines (its matched run lay almost entirely beyond a file boundary) was
  emitted as meaningless `(0 lines)` noise; such clones are now skipped.

### Added — detection

- **Type-2 detection on every engine** via `--fuzzy`: a shared `TokenNormalizer` abstracts
  identifiers and literals to type classes (previously `--fuzzy` only touched variables, and only
  in the default engine — the suffix tree had no Type-2 at all).
- **Inconsistent-clone reporting**: gapped (Type-3) clones are distinguished from exact copies
  (`CodeClone::isGapped()`), marked `[inconsistent]` in console output and surfaced as `warning`
  severity in SARIF.
- **Type-aware edit weights** in the suffix-tree engine: a changed control keyword (`if`→`while`)
  costs more of the `--edit-distance` budget than a renamed identifier.
- **New `tokenbag` engine** (`--algorithm=tokenbag`): a SourcererCC-style order-invariant token
  bag + inverted index that detects **reordered** clones the contiguous engines miss. Threshold
  via `--min-similarity` (default 0.7).

### Added — CI

- **Incremental result cache** (`--cache` / `--cache-dir`): keyed by a fingerprint of the
  configuration and a manifest of file hashes; a re-run on unchanged files skips detection
  entirely and prints `(cache hit)`. Designed to be mounted with `actions/cache`.
- **Per-file incremental index** (`--incremental`, Rabin–Karp only): Hummel-style index that
  persists each file's tokenization and re-tokenizes **only the files that changed**, replaying the
  rest from the index. Finer-grained than `--cache` (one edit no longer invalidates the whole run)
  and provably equivalent to a full scan. Prints `(incremental index: N reused, M scanned)`.

### Added — output

- **JSON** report (`--log-json`) and **SARIF 2.1.0** report (`--log-sarif`, for GitHub Code
  Scanning), alongside the existing PMD-CPD XML. A shared `Log\Logger` contract unifies them.

### Changed

- **Zero runtime Composer dependencies**: `sebastian/version`, `sebastian/cli-parser`,
  `phpunit/php-file-iterator`, and `phpunit/php-timer` were removed — replaced with owned,
  improved code (a declarative self-documenting CLI parser with value validation; a file finder
  that prunes excluded directories and supports glob excludes; a timer that reports throughput).
- Namespace migrated to `LucianoPereira\PhpcpdNext`; autoloading switched from classmap to PSR-4.
- PMD XML logger simplified to use DOM-native escaping.

---

## [0.1.0] — 2026-06-26

First release of **phpcpd-next**. Picks up where Sebastian Bergmann's archived
`sebastianbergmann/phpcpd` (7.0-dev) left off and brings the tool forward to PHP 8.5.

### Platform

- Requires PHP **≥ 8.5** (upstream required ≥ 8.1)
- `composer.json` platform locked to `8.5.0`

### Fixed

- **`sebastian/version` v4 API break** — `getVersion()` renamed to `asString()` in v4;
  the banner was crashing silently on import.
- **`empty($object)` always false** — `SuffixTreeStrategy` used `empty($this->result)` on
  a `CodeCloneMap` object; `empty()` on any object always returns `false`. Fixed to
  `=== null`.
- **Division by zero** — `CodeCloneMap::averageSize()` divided by `count()` without
  guarding the empty case. Fixed with an early `return 0.0`.
- **`current()` returning `false`** — `CodeClone::lines()` called `current()` on an
  associative array and used the result as a `CodeCloneFile`; `current()` returns `false`
  on an empty array. Replaced with `array_values($this->files)[0]` which is guaranteed safe
  after the existing non-empty guard.
- **`file_get_contents()` false return** — both `DefaultStrategy` and `SuffixTreeStrategy`
  passed the raw `string|false` return directly into tokenisation. Added `if ($buffer === false) { return; }` guards.
- **`file()` returning `false`** — `CodeClone::lines()` called `file()` without checking
  the return. Fixed with `?: []` fallback.
- **`mb_convert_encoding()` returning `false`** — `AbstractXmlLogger` did not check the
  return of `mb_convert_encoding()`, which returns `false` on encoding failure. Fixed with
  an explicit false-check and fallback to the original string.
- **`preg_replace()` returning `null`** — `AbstractXmlLogger::toUtf8String()` could return
  `string|null` from `preg_replace`. Fixed with `?? $string` fallback.

### Changed

- **Banner** updated to credit both the original author and the fork:
  `phpcpd 0.1.0 by Luciano Federico Pereira based on phpcpd 7.0-dev by Sebastian Bergmann.`

### Modernised (PHP 8.0 – 8.5)

- `readonly class` applied to `Arguments`, `CodeCloneFile`, `StrategyConfiguration`,
  `CloneInfo` — immutability enforced at the class level.
- Constructor property promotion on all eligible classes — eliminates boilerplate
  `$this->x = $x` assignments.
- `#[\Override]` attribute on every method that implements or overrides a contract.
- Typed class constants (`private const string`, `private const int`) throughout.
- `foreach ($array as $item)` replaces `foreach (array_keys($array) as $k)` where the key
  was never used.
- `$result === null` replaces `empty($result)` wherever the variable is an object or
  nullable type.

### Improved

- **Duplicate code eliminated** — `DefaultStrategy::processFile()` contained two identical
  17-line blocks that built and recorded a `CodeClone`. Extracted to
  `recordCloneIfValid()`. Running the tool on its own source now reports zero clones.
- PHPDoc generics (`list<T>`, `@template`, `@implements`) on all collection classes.

### Toolchain (new files)

- **PHPStan level 9** — zero errors. `phpstan.neon` + `phpstan-stubs.php` for the
  untyped `sebastian/cli-parser` return.
- **PHP-CS-Fixer** — `@PER-CS2.0` + risky fixers (`declare_strict_types`,
  `native_function_invocation`, `strict_param`, …).
- **Rector** — `php85` set, `CODE_QUALITY`, `TYPE_DECLARATION`.
- **PHPUnit 12** — `phpunit.xml` wired; test writing is the next milestone.
- **GitHub Actions CI** — `audit → lint → analyse → test` on every push.
- **`.editorconfig`** — consistent whitespace before any tool runs.
- **Composer scripts** — `lint`, `lint:fix`, `analyse`, `test`, `check`.

[1.4.0]: https://github.com/phpcpd-next/phpcpd/releases/tag/v1.4
[1.3.0]: https://github.com/phpcpd-next/phpcpd/releases/tag/v1.3
[1.2.0]: https://github.com/phpcpd-next/phpcpd/releases/tag/v1.2
[1.1.0]: https://github.com/phpcpd-next/phpcpd/releases/tag/v1.1
[1.0.0]: https://github.com/phpcpd-next/phpcpd/releases/tag/v1.0
[0.1.0]: https://github.com/phpcpd-next/phpcpd/releases/tag/v0.1
