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
 * (Rabin-Karp, suffix tree) miss.
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
        public array $bag,
        public int $size,
    ) {}
}
