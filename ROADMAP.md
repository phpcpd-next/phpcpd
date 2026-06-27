# phpcpd-next — Roadmap

phpcpd-next is **token-based by design, and permanently so**: `token_get_all` → Rabin–Karp, an
approximate suffix tree, and a SourcererCC token-bag, with no parser or AST — neither a third-party
dependency nor a from-scratch one (see the out-of-scope table for why). Every item below is admitted
only on **PHP-specific empirical evidence** measured on the BCB-PHP benchmark — not on theoretical
appeal. Items that contradict the project's core properties (determinism, zero runtime dependencies,
CLI speed) are listed as out of scope, with reasons.

## Planned

| # | Item | When | Cost | Notes |
|---|------|------|------|-------|
| 1 | **Broaden BCB-PHP injection operators** — `bool`↔`int`, nullable↔non-nullable, class/interface substitutions | Now | Low | Benchmark only; reuses `bench/injectors.php` and the existing type-anchored normalizer. Enlarges the type-load-bearing population behind the E2 specificity result. (`bool`↔`int` done and measured; nullable/class pending.) |
| 2 | **Suffix-tree performance** — reduce the measured O(n·k) cost while staying **pure-PHP and deterministic** | DP done (1.0.0); enumeration base open | Medium | **Banded edit-distance DP done in 1.0.0** (profiler-driven, ~3.5× faster, byte-identical output). Remaining: candidate-enumeration cost, which banding does not reduce — the natural next code-level optimization. No native (C/Rust) extension; pure-PHP and deterministic throughout. |
| 3 | **Local type-inference heuristic** in `TokenNormalizer` — extend the type-anchoring precision gain toward untyped code | Conditional | Low to test / Medium to ship | **Measured before built:** test on BCB-PHP whether a light, local heuristic (types from literals and signatures) lifts precision on untyped corpora such as WordPress. Implement only if the numbers justify it. Full static type inference is out of scope (that is PHPStan/Psalm's job). |

## Out of scope (declined, not deferred)

These were analyzed and deliberately rejected; they are recorded so the decision is explicit.

| Idea | Why declined |
|------|--------------|
| **Neural / LLM Type-4 detection** | Targets a rare clone class; non-deterministic; requires a heavy external dependency (model/API/GPU). Incompatible with a fast, deterministic, dependency-light CLI. |
| **ML / dynamic similarity thresholds** | Breaks determinism (a CI tool must give the same answer every run); the IDF stopword filter already provides corpus adaptivity without ML. |
| **AST + LSH (Deckard)** | Low marginal gain over the token engines on typical PHP codebases (most clones are Type-1/2/3, which the token methods already cover). |
| **`nikic/php-parser` dependency** | Never. A parser dependency is incompatible with the zero-runtime-dependency design; brace-tracked block extraction works on partial/broken code and is fast. |
| **A from-scratch PHP parser / going syntactic at all** | Considered as a possible next-major direction and **rejected**. Avoiding the third-party dependency does not change the calculus: a hand-written parser is a large, maintenance-heavy subsystem that re-implements PHP's grammar and must track every language version — for the same low marginal gain that rules out AST+LSH (Finding 1: simple clones dominate). The token methods already span Type-1 through Type-3. phpcpd-next therefore stays **token-based permanently**; there is no AST path, by dependency or otherwise. |

_The roadmap is intentionally short. The benchmark exists precisely so that future ideas are decided
by measurement rather than by argument._
