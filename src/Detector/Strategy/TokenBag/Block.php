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

namespace LucianoPereira\PhpcpdNext\Detector\Strategy\TokenBag;

/**
 * A function/method body as an order-invariant multiset of tokens (the "bag").
 * Two blocks that differ only in statement order have identical bags, which is
 * what lets the token-bag engine detect reordered clones the contiguous matchers
 * (Rabin-Karp) miss.
 *
 * `$startLine` and `$endLine` bound **what went into the bag** and nothing else.
 * They used to bound something wider: the start was the line of the `function`
 * keyword while the bag begins after the opening brace, so every site on a
 * PSR-12 corpus reported a signature line the matcher had never compared —
 * 38 of 38 sites on symfony-console, 6 of 6 on symfony-string. A reported
 * extent is a claim about what matched, so it is measured off the first and
 * last token that did.
 *
 * `$startToken` is that first token's index among the file's significant
 * tokens, in the same numbering {@see \LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy::tokenize()}
 * and {@see \LucianoPereira\PhpcpdNext\Facts\RegionStructure} use. Without it a
 * token-bag site carried no token anchor at all, and every audit that works in
 * token indices — the boundary-alignment measurements, `check-superset.php` —
 * scored the Rabin-Karp arm alone while reporting its answer as the engine's.
 */
final readonly class Block
{
    /**
     * @param array<string, int> $bag token signature => count
     */
    public function __construct(
        public string $file,
        public int $startLine,
        public int $endLine,
        public int $startToken,
        public array $bag,
        public int $size,
    ) {}
}
