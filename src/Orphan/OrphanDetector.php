<?php

declare(strict_types=1);
/*
 * This file is part of PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LucianoPereira\PhpcpdNext\Orphan;

use function array_filter;
use function array_slice;
use function array_values;
use function count;
use function dirname;
use function explode;
use function implode;
use function in_array;
use function realpath;
use function str_starts_with;
use function strlen;
use function strrpos;
use function strtolower;
use function substr;
use function trim;

use LucianoPereira\PhpcpdNext\CodeCloneMap;

/**
 * Given a file set, decides which declared symbols are orphans and how confident
 * we are. No I/O — the headless core of orphan detection, the counterpart to the
 * clone side's {@see \LucianoPereira\PhpcpdNext\Engine}.
 *
 * The classification is a small decision tree, built from the three tools this
 * feature harvests:
 *
 *   1. Suppressed (`@api` / `@phpcpd-keep`) or a framework entry point
 *      (`#[Route]`, a test class) → not an orphan at all. (shipmonk providers,
 *      Psalm's `@psalm-api`.)
 *   2. Referenced anywhere in code → live.
 *   3. Unreferenced but a contract (interface / abstract / trait) or mentioned
 *      only in a string literal → *possible* orphan; reported, does not fail CI.
 *      (Psalm's PossiblyUnusedClass; a hedge against DI / dynamic dispatch.)
 *   4. Otherwise → a definite orphan. (Psalm's UnusedClass; phpunused's
 *      unreferenced file.)
 *
 * Each orphan is then *explained*: is the whole file unwired, and is it a
 * superseded copy of live code? The second answer is read straight out of a
 * clone map — the same duplication engine, reused to tell "dead" apart from
 * "dead because it was replaced."
 */
use LucianoPereira\PhpcpdNext\Strings\Catalogue;

final class OrphanDetector
{
    /**
     * Path segments that mark test support code. Being unreferenced is what makes
     * a fixture a fixture — one referenced by ordinary code would be a poor one.
     *
     * @var list<string>
     */
    private const array FIXTURE_SEGMENTS = ['fixtures', 'fixture', 'stubs', 'stub'];

    /**
     * A fixture segment only counts inside a test tree, so a production
     * directory that happens to be called Stubs is not silently exempted.
     *
     * @var list<string>
     */
    private const array TEST_SEGMENTS = ['test', 'tests', 'spec', 'specs'];

    public function __construct(
        private readonly SymbolCollector $collector = new SymbolCollector(),
        private readonly Catalogue $strings = new Catalogue(),
    ) {}

    /**
     * @param list<string>   $files
     * @param ?CodeCloneMap  $clones a duplication map over the same files; when
     *                               supplied, orphans that copy live code are
     *                               annotated as superseded copies. Optional —
     *                               null just skips that enrichment.
     */
    public function detect(
        array $files,
        ?CodeCloneMap $clones = null,
        ?OrphanConfiguration $config = null,
        ?ProjectContext $context = null,
    ): OrphanResult {
        $config    = $config ?? new OrphanConfiguration();
        $context   = $context ?? new ProjectContext();
        $collected = $this->collector->collect($files, $config);
        $roots     = $this->rootsFor($files, $config);
        $byName    = $this->classesByName($collected->definitions);

        // First pass: which symbols are orphaned, and why (base reason/tier).
        /** @var list<array{symbol: Symbol, confidence: string, reason: string, rule: ?string, evidence: ?string}> $provisional */
        $provisional = [];

        foreach ($collected->definitions as $symbol) {
            $verdict = $this->classify($symbol, $collected, $config, $context, $roots, $byName);

            if ($verdict !== null) {
                $provisional[] = $verdict;
            }
        }

        $findings       = array_values(array_filter(
            $provisional,
            static fn(array $v): bool => $v['confidence'] === Orphan::CONFIDENCE_DEAD
                || $v['confidence'] === Orphan::CONFIDENCE_POSSIBLE,
        ));
        $perFileTotal   = $this->countByFile($collected->definitions);
        $perFileOrphans = $this->countByFile($this->symbolsOf($findings));
        $duplicateOf    = $this->duplicateTargets($findings, $collected->definitions, $clones);

        $orphans = [];

        foreach ($provisional as $verdict) {
            $symbol = $verdict['symbol'];
            $key    = $this->key($symbol);

            $orphans[] = new Orphan(
                $symbol,
                $verdict['confidence'],
                $verdict['reason'],
                entireFileOrphaned: $verdict['confidence'] !== Orphan::CONFIDENCE_SUPPRESSED
                    && $verdict['confidence'] !== Orphan::CONFIDENCE_PLANNED
                    && ($perFileOrphans[$symbol->file] ?? 0) === ($perFileTotal[$symbol->file] ?? 0),
                duplicateOf: $duplicateOf[$key] ?? null,
                rule: $verdict['rule'],
                evidence: $verdict['evidence'],
            );
        }

        return new OrphanResult($orphans, count($files), count($collected->definitions));
    }

    /**
     * The decision tree. Order matters: a code reference settles the question
     * outright, an author-stated intent outranks a structural rule, and the two
     * "possible" hedges come last so a stronger explanation always wins.
     *
     * A disabled rule falls through to ordinary classification rather than being
     * skipped — turning a rule off means "judge these symbols normally", which is
     * what makes --no-suppress a way to audit the rule itself.
     *
     * @param list<string>                $roots
     * @param array<string, list<string>> $byName short class name => fqns declared under it
     * @return array{symbol: Symbol, confidence: string, reason: string, rule: ?string, evidence: ?string}|null
     */
    private function classify(
        Symbol $symbol,
        CollectedSymbols $collected,
        OrphanConfiguration $config,
        ProjectContext $context,
        array $roots,
        array $byName,
    ): ?array {
        if (($collected->references[$symbol->name] ?? 0) > 0) {
            // A planned symbol that became referenced has outlived its tag. This
            // is the cleanup prompt a keep tag can never give.
            if ($symbol->rule === Rule::PLANNED && $config->ruleEnabled(Rule::PLANNED)) {
                return $this->verdict(
                    $symbol,
                    Orphan::CONFIDENCE_POSSIBLE,
                    $this->strings->get('explain.orphan.plannedServed'),
                    Rule::PLANNED,
                );
            }

            return null;
        }

        if ($symbol->rule !== null && $config->ruleEnabled($symbol->rule)) {
            return $this->verdict(
                $symbol,
                $symbol->rule === Rule::PLANNED ? Orphan::CONFIDENCE_PLANNED : Orphan::CONFIDENCE_SUPPRESSED,
                $symbol->ruleReason ?? Rule::label($symbol->rule),
                $symbol->rule,
            );
        }

        if ($config->ruleEnabled(Rule::MANIFEST) && $context->isEntryPointFile($symbol->file)) {
            return $this->verdict(
                $symbol,
                Orphan::CONFIDENCE_SUPPRESSED,
                $this->strings->get('explain.orphan.manifest'),
                Rule::MANIFEST,
                'composer.json',
            );
        }

        $namespace = $this->namespaceOf($symbol);

        if ($namespace !== '' && $config->ruleEnabled(Rule::NAMESPACE_PREFIX) && !$context->ownsNamespace($namespace)) {
            return $this->verdict(
                $symbol,
                Orphan::CONFIDENCE_SUPPRESSED,
                $this->strings->get('explain.orphan.foreignNs'),
                Rule::NAMESPACE_PREFIX,
            );
        }

        if ($config->ruleEnabled(Rule::FIXTURES) && $this->isFixturePath($symbol->file, $roots)) {
            return $this->verdict(
                $symbol,
                Orphan::CONFIDENCE_SUPPRESSED,
                $this->strings->get('explain.orphan.fixture'),
                Rule::FIXTURES,
            );
        }

        // Constructed-name idioms are asked before the wired-by-name lookups, and
        // for the same reason those come last: this answer is already in memory
        // (harvested from the token pass) while those cost a filesystem sweep, so
        // a symbol an idiom accounts for never provokes one.
        if ($config->ruleEnabled(Rule::DISCOVERY) && $symbol->kind === Symbol::KIND_CLASS) {
            $where = $collected->idioms->discoveredBy($symbol->file);

            if ($where !== null) {
                return $this->verdict(
                    $symbol,
                    Orphan::CONFIDENCE_SUPPRESSED,
                    $this->strings->get('explain.orphan.discovery'),
                    Rule::DISCOVERY,
                    $where,
                );
            }
        }

        if ($config->ruleEnabled(Rule::CONVENTION) && $symbol->kind === Symbol::KIND_CLASS) {
            $convention = $collected->idioms->conventionFor($symbol->name, $byName);

            if ($convention !== null) {
                return $this->verdict(
                    $symbol,
                    Orphan::CONFIDENCE_SUPPRESSED,
                    $this->strings->get('explain.orphan.convention', [
                        'base'  => $convention['base'],
                        'trait' => $convention['trait'],
                    ]),
                    Rule::CONVENTION,
                    $convention['at'],
                );
            }
        }

        // Wired-by-name is the last question asked, and the only one whose answer
        // costs a filesystem sweep (see {@see NameSweep}). Each map is therefore
        // read one rule at a time, after that rule is known to be enabled: a
        // symbol named in a config file never provokes the template sweep, and a
        // symbol that never gets this far provokes neither.
        foreach ([Rule::MANIFEST, Rule::CONFIG, Rule::TEMPLATE] as $rule) {
            if (!$config->ruleEnabled($rule)) {
                continue;
            }

            $names = match ($rule) {
                Rule::MANIFEST => $context->manifestNames,
                Rule::CONFIG   => $context->configNames,
                default        => $context->templateNames,
            };

            $where = $names[$symbol->fqn] ?? $names[$symbol->name] ?? null;

            if ($where !== null) {
                return $this->verdict($symbol, Orphan::CONFIDENCE_SUPPRESSED, Rule::label($rule), $rule, $where);
            }
        }

        if ($symbol->isContract()) {
            return $this->verdict($symbol, Orphan::CONFIDENCE_POSSIBLE, match ($symbol->kind) {
                Symbol::KIND_INTERFACE => $this->strings->get('explain.orphan.interface'),
                Symbol::KIND_TRAIT     => $this->strings->get('explain.orphan.trait'),
                default                => $this->strings->get('explain.orphan.abstract'),
            });
        }

        $inString = $collected->stringNames[$symbol->fqn] ?? $collected->stringNames[$symbol->name] ?? null;

        if ($inString !== null) {
            return $this->verdict(
                $symbol,
                Orphan::CONFIDENCE_POSSIBLE,
                $this->strings->get('explain.orphan.inString'),
                null,
                $inString,
            );
        }

        return $this->verdict($symbol, Orphan::CONFIDENCE_DEAD, 'never referenced');
    }

    /**
     * @return array{symbol: Symbol, confidence: string, reason: string, rule: ?string, evidence: ?string}
     */
    private function verdict(
        Symbol $symbol,
        string $confidence,
        string $reason,
        ?string $rule = null,
        ?string $evidence = null,
    ): array {
        return [
            'symbol'     => $symbol,
            'confidence' => $confidence,
            'reason'     => $reason,
            'rule'       => $rule,
            'evidence'   => $evidence,
        ];
    }

    /**
     * Classes declared in the scan, indexed by short name — the lookup the
     * convention rule needs to answer "does a class `X` exist for this
     * `X<Suffix>`?". Built once per run rather than per symbol.
     *
     * @param list<Symbol> $definitions
     * @return array<string, list<string>> short name => fqns declared under it
     */
    private function classesByName(array $definitions): array
    {
        $byName = [];

        foreach ($definitions as $symbol) {
            if ($symbol->kind === Symbol::KIND_CLASS) {
                $byName[$symbol->name][] = $symbol->fqn;
            }
        }

        return $byName;
    }

    /**
     * The directories this scan treats as the project. Explicit roots win; with
     * none, the deepest directory containing every scanned file stands in, so a
     * caller that hands over a bare file list still gets path rules judged
     * against that file set rather than against the machine's directory layout.
     *
     * @param list<string> $files
     * @return list<string>
     */
    private function rootsFor(array $files, OrphanConfiguration $config): array
    {
        if ($config->roots !== []) {
            return $config->roots;
        }

        $common = null;

        foreach ($files as $file) {
            $parts = explode('/', dirname($file));

            if ($common === null) {
                $common = $parts;

                continue;
            }

            $shared = [];

            foreach ($parts as $depth => $part) {
                if (($common[$depth] ?? null) !== $part) {
                    break;
                }

                $shared[] = $part;
            }

            $common = $shared;
        }

        return $common === null || $common === [] ? [] : [implode('/', $common)];
    }

    /**
     * Is $file test support code, judged RELATIVE to the scan roots? An absolute
     * path is the wrong thing to test: pointing a scan straight at a fixture
     * directory makes that directory the project for this run, and its contents
     * ordinary code. Judging the absolute path instead would make such a scan
     * suppress everything it was explicitly asked about.
     *
     * @param list<string> $roots
     */
    private function isFixturePath(string $file, array $roots): bool
    {
        $segments = $this->relativeSegments($file, $roots);

        if ($segments === null) {
            return false;
        }

        $fixture = false;
        $test    = false;

        foreach ($segments as $segment) {
            $bare = trim(strtolower($segment), '_');

            $fixture = $fixture || in_array($bare, self::FIXTURE_SEGMENTS, true);
            $test    = $test || in_array($bare, self::TEST_SEGMENTS, true);
        }

        return $fixture && $test;
    }

    /**
     * $file's directory segments below whichever root contains it, or null when
     * no root does.
     *
     * @param list<string> $roots
     * @return ?list<string>
     */
    private function relativeSegments(string $file, array $roots): ?array
    {
        $path = realpath($file);

        if ($path === false) {
            return null;
        }

        foreach ($roots as $root) {
            $base = realpath($root);

            if ($base === false || !str_starts_with($path, $base . '/')) {
                continue;
            }

            $relative = substr($path, strlen($base) + 1);
            $segments = explode('/', $relative);

            return array_slice($segments, 0, count($segments) - 1);
        }

        return null;
    }

    private function namespaceOf(Symbol $symbol): string
    {
        $pos = strrpos($symbol->fqn, '\\');

        return $pos === false ? '' : substr($symbol->fqn, 0, $pos);
    }

    /**
     * For each orphan that shares a clone with a *live* symbol, produce a label
     * for that live symbol — the code this orphan appears to be a stale copy of.
     * A clone between two orphans is not a replacement (both are dead), so it is
     * ignored here.
     *
     * @param list<array{symbol: Symbol, confidence: string, reason: string, rule: ?string, evidence: ?string}> $provisional
     * @param list<Symbol>                                                                                        $definitions
     * @return array<string, string> orphan key → "Fqn (file:line)" of the live original
     */
    private function duplicateTargets(array $provisional, array $definitions, ?CodeCloneMap $clones): array
    {
        if ($clones === null) {
            return [];
        }

        $orphanKeys = [];

        foreach ($provisional as $verdict) {
            $orphanKeys[$this->key($verdict['symbol'])] = true;
        }

        $targets = [];

        foreach ($clones as $clone) {
            /** @var list<Symbol> $owners */
            $owners = [];

            foreach ($clone->files() as $file) {
                $owner = $this->ownerAt($definitions, $file->name, $file->startLine);

                if ($owner !== null) {
                    $owners[] = $owner;
                }
            }

            // Annotate every orphan participant with a live participant, if any.
            foreach ($owners as $orphanCandidate) {
                $key = $this->key($orphanCandidate);

                if (!isset($orphanKeys[$key]) || isset($targets[$key])) {
                    continue;
                }

                foreach ($owners as $other) {
                    if ($this->key($other) !== $key && !isset($orphanKeys[$this->key($other)])) {
                        $targets[$key] = $other->fqn . ' (' . $other->file . ':' . $other->line . ')';
                        break;
                    }
                }
            }
        }

        return $targets;
    }

    /**
     * The declared symbol whose body encloses $line in $file — the last symbol
     * to start at or before that line. A clone occurrence inside a class body
     * therefore resolves to that class.
     *
     * @param list<Symbol> $definitions
     */
    private function ownerAt(array $definitions, string $file, int $line): ?Symbol
    {
        $owner = null;

        foreach ($definitions as $symbol) {
            if ($symbol->file === $file && $symbol->line <= $line
                && ($owner === null || $symbol->line > $owner->line)) {
                $owner = $symbol;
            }
        }

        return $owner;
    }

    /**
     * @param list<Symbol> $symbols
     * @return array<string, int> file → number of symbols declared in it
     */
    private function countByFile(array $symbols): array
    {
        $counts = [];

        foreach ($symbols as $symbol) {
            $counts[$symbol->file] = ($counts[$symbol->file] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param list<array{symbol: Symbol, confidence: string, reason: string, rule: ?string, evidence: ?string}> $provisional
     * @return list<Symbol>
     */
    private function symbolsOf(array $provisional): array
    {
        $symbols = [];

        foreach ($provisional as $verdict) {
            $symbols[] = $verdict['symbol'];
        }

        return $symbols;
    }

    private function key(Symbol $symbol): string
    {
        return $symbol->file . '#' . $symbol->fqn;
    }
}
