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

namespace LucianoPereira\PhpcpdNext;

use function usort;

/**
 * A clone map, read largest first.
 *
 * Size is the closest thing to importance that this tier can see. Everything
 * that judges a finding — the confidence model, the strata, the ledger — lives
 * in the presentation tier and reads what comes out of here, so the order it
 * receives is the tiebreak it inherits for anything it rates equally.
 *
 * Which makes the ordering worth stating exactly: descending by line count, and
 * among clones of the same size, the order the map recorded them in. PHP's sort
 * has been stable since 8.0, so one descending comparison keeps discovery order
 * among equals; sorting ascending and reversing gives every tie back to front,
 * which is discovery order rewritten by an implementation detail of how the
 * sort was spelled.
 *
 * An aggregate rather than a cursor. `Iterator` is five methods and a position
 * that has to agree with itself across all of them, for an object that has the
 * whole list in memory before anyone asks for the first element.
 *
 * @implements \IteratorAggregate<int, CodeClone>
 */
final class CodeCloneMapIterator implements \IteratorAggregate
{
    /** @var list<CodeClone> */
    private readonly array $clones;

    public function __construct(CodeCloneMap $clones)
    {
        $ordered = $clones->clones();

        usort($ordered, static fn(CodeClone $a, CodeClone $b): int => $b->numberOfLines() <=> $a->numberOfLines());

        $this->clones = $ordered;
    }

    /** @return \Generator<int, CodeClone> */
    #[\Override]
    public function getIterator(): \Generator
    {
        yield from $this->clones;
    }
}
