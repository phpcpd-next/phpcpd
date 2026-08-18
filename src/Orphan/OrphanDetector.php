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

use function count;

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
final class OrphanDetector
{
    public function __construct(
        private readonly SymbolCollector $collector = new SymbolCollector(),
    ) {}

    /**
     * @param list<string>   $files
     * @param ?CodeCloneMap  $clones a duplication map over the same files; when
     *                               supplied, orphans that copy live code are
     *                               annotated as superseded copies. Optional —
     *                               null just skips that enrichment.
     */
    public function detect(array $files, ?CodeCloneMap $clones = null): OrphanResult
    {
        $collected = $this->collector->collect($files);

        // First pass: which symbols are orphaned, and why (base reason/tier).
        /** @var list<array{symbol: Symbol, confidence: string, reason: string}> $provisional */
        $provisional = [];

        foreach ($collected->definitions as $symbol) {
            $verdict = $this->classify($symbol, $collected);

            if ($verdict !== null) {
                $provisional[] = $verdict;
            }
        }

        $perFileTotal   = $this->countByFile($collected->definitions);
        $perFileOrphans = $this->countByFile($this->symbolsOf($provisional));
        $duplicateOf    = $this->duplicateTargets($provisional, $collected->definitions, $clones);

        $orphans = [];

        foreach ($provisional as $verdict) {
            $symbol = $verdict['symbol'];
            $key    = $this->key($symbol);

            $orphans[] = new Orphan(
                $symbol,
                $verdict['confidence'],
                $verdict['reason'],
                entireFileOrphaned: ($perFileOrphans[$symbol->file] ?? 0) === ($perFileTotal[$symbol->file] ?? 0),
                duplicateOf: $duplicateOf[$key] ?? null,
            );
        }

        return new OrphanResult($orphans, count($files), count($collected->definitions));
    }

    /**
     * @return array{symbol: Symbol, confidence: string, reason: string}|null
     */
    private function classify(Symbol $symbol, CollectedSymbols $collected): ?array
    {
        if ($symbol->suppressed || $symbol->entrypoint !== null) {
            return null;
        }

        if (($collected->references[$symbol->name] ?? 0) > 0) {
            return null;
        }

        if ($symbol->isContract()) {
            return [
                'symbol'     => $symbol,
                'confidence' => Orphan::CONFIDENCE_POSSIBLE,
                'reason'     => match ($symbol->kind) {
                    Symbol::KIND_INTERFACE => 'never referenced (interface — may be implemented outside the scanned set)',
                    Symbol::KIND_TRAIT     => 'never referenced (trait — may be used by classes outside the scanned set)',
                    default                => 'never referenced (abstract — may be extended outside the scanned set)',
                },
            ];
        }

        if (isset($collected->stringNames[$symbol->name]) || isset($collected->stringNames[$symbol->fqn])) {
            return [
                'symbol'     => $symbol,
                'confidence' => Orphan::CONFIDENCE_POSSIBLE,
                'reason'     => 'never referenced in code; name appears in a string literal (possible dynamic use)',
            ];
        }

        return [
            'symbol'     => $symbol,
            'confidence' => Orphan::CONFIDENCE_DEAD,
            'reason'     => 'never referenced',
        ];
    }

    /**
     * For each orphan that shares a clone with a *live* symbol, produce a label
     * for that live symbol — the code this orphan appears to be a stale copy of.
     * A clone between two orphans is not a replacement (both are dead), so it is
     * ignored here.
     *
     * @param list<array{symbol: Symbol, confidence: string, reason: string}> $provisional
     * @param list<Symbol>                                                     $definitions
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
                $owner = $this->ownerAt($definitions, $file->name(), $file->startLine());

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
     * @param list<array{symbol: Symbol, confidence: string, reason: string}> $provisional
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
