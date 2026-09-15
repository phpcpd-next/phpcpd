# Roadmap

phpcpd-next is **token-based by design, and permanently so**: `token_get_all`
into Rabin–Karp, a SourcererCC token bag, and the unified winnowing engine, with
no parser and no AST — neither a third-party dependency nor a from-scratch one.
The out-of-scope table at the end says why, and that decision is closed.

Everything below is admitted on **measurement, not appeal**. Each planned item
names the benchmark that would settle it and the number it has to move. An idea
with no such benchmark is not on this list; that is what the list is for.

## What 2.0 settled, so the roadmap can be short

The unified engine landed, the suffix-tree lane was removed, Stage 0 triage
became a proof rather than an estimator, and the inherited surface reached zero
under MIT. Those were the open questions of the 1.x line and they are not
questions any more. The four items below are what is genuinely left.

## Planned

| # | Item | Why it is not done | What would settle it |
|---|------|--------------------|----------------------|
| 1 | **Candidate generation on self-similar input** | The unified engine runs well above the default pipeline across six corpora, and the ratio tracks self-similar content rather than size: PHPUnit is the worst at <!-- [[ $walltime.phpunit_ratio ]] -->31.90<!--/-->×, WordPress the best at <!-- [[ $walltime.wordpress_ratio ]] -->3.44<!--/-->×. `bench/check-walltime.php` fails every corpus today, and says so rather than hiding it. | A guard on candidate generation, measured on `bench/corpus/phpunit/tests/unit/Metadata` (11.83s, 87 clones) with `bench/corpus/wordpress` as the control it must not regress. It changes detection semantics, so it needs a recall study — `bench/run-recall.php` — not just a subsumption gate. |
| 2 | **Normalized data-table false positives** | The one confirmed false-positive shape the precision audit found, and still live: under identifier normalization every string literal folds to one token, so an array of string pairs matches an array of string pairs with no duplicated logic. A default unified scan of `bench/corpus/symfony-string` reports it at 85 lines. | A discriminator, and there is no candidate left. **Five were measured and all five refuted**, the third — logic share — twice over: it was refuted for failing to separate the flagship true positive, and its measurement was then found to be dropping every single-character token, about half the program text and nearly all of it non-literal. Corrected, it puts every finding on all six corpora at or above 0.40: there is no band to cut at, and the conjunction rule built on that band cannot fire. Read §2, §2b and §2c of `docs/research/deferred-engine-work.md` before proposing a sixth; the refutations are cheaper to read than to repeat. |
| 3 | **One finding per self-similar region** | Two files can produce 64 clones a reader can do nothing with. The periodicity decomposition already in `fromCluster()` has the right shape for collapsing a run of mutually-similar blocks into one class naming every block; it is simply not reached for cross-file near-duplication. | Worth doing with (1) and measured with it — same input class, same benchmarks. |
| 4 | **Maximal extents for Rabin–Karp** | A run closes when the registrant changes, which is sound but can report an extent shorter than the true match. Extents are currently short rather than wrong, which is the safe direction. | Per-file postings, so a run can be extended across registrants without reintroducing the defect that made it necessary. |

## Also open, smaller

- **A second rater for the precision audit.** The current precision interval
  rests on one rater. A second, with Cohen's κ reported, is what turns it from a
  number into a measurement. `bench/audit-precision.php` already writes the
  worksheet; the corpus source strings never enter this repository.

  It now gates a second thing. The confidence model's `literal` feature is
  computed from the line span a worksheet records, because that is what a
  worksheet has, while `Strata` asks the occurrence's own token range. Both are
  deliberate and the two ends agree with each other, but they are different
  definitions: the raw share differs on about a third of lead sites and the
  bucket the model reads differs on 8 of 348. Aligning them means training on
  the token range, which means a worksheet that records it, which means a fresh
  rating round — this one.
- **A clone's reported extent can overstate by one line at a boundary.** Two
  disjoint token ranges can share one physical line, so a site's last line may
  equal the next site's first. Display only: the coverage union counts such a
  line once, so no total is affected. Quantified now: 42 site pairs on phpunit,
  4 on firefly-iii, 0 on symfony-console — disjoint in tokens and touching on
  one line. Untouched by the Rabin-Karp length fix in this release, which was a
  different mechanism — occurrences borrowing one length — and is fixed rather
  than listed here.
- **Translations.** Twenty-seven ship and each is complete: 165 of 165 keys.
  `php bench/check-locales.php` reports coverage, fails on a key English does not
  have, on placeholder drift, on a copied file, and on a translation whose
  English has changed since it was written. Missing keys fall back to English per
  key, so a future gap costs nothing until someone closes it.

## Out of scope — declined, not deferred

Recorded so the decision is explicit and is not re-litigated.

| Idea | Why declined |
|------|--------------|
| **Neural / LLM Type-4 detection** | Targets a rare clone class; non-deterministic; needs a model, an API or a GPU. Incompatible with a fast, deterministic, dependency-free CLI. |
| **ML or dynamic similarity thresholds** | Breaks determinism, and a CI tool must give the same answer every run. The IDF stopword filter already provides corpus adaptivity without it. |
| **AST + LSH (Deckard)** | Low marginal gain over the token engines on real PHP: simple clones dominate, and Type-1 through Type-3 are already covered. |
| **A `nikic/php-parser` dependency** | Never. Incompatible with the zero-runtime-dependency design, and brace-tracked block extraction works on partial or broken code, which a parser does not. |
| **A from-scratch PHP parser — going syntactic at all** | Considered as a next-major direction and **rejected**. Avoiding the third-party dependency does not change the arithmetic: a hand-written parser re-implements PHP's grammar, must track every language version, and is a large maintenance-heavy subsystem — for the same low marginal gain that rules out AST+LSH. phpcpd-next stays token-based permanently. There is no AST path, by dependency or otherwise. |

## How an item gets onto this list

State the number it moves and the benchmark that measures it. The benchmark
exists precisely so that a proposal is settled by running it rather than by
arguing about it — and so that a refutation is recorded, which is why item 2
carries five of them.

Working notes, dated and not revised afterwards, are in
[`docs/research/`](docs/research/); the characterisations behind items 1–3 are
in [`docs/research/deferred-engine-work.md`](docs/research/deferred-engine-work.md).
