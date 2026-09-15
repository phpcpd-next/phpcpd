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

namespace LucianoPereira\PhpcpdNext\Detector\Strategy;

/**
 * Where a run of already-seen windows began.
 *
 * The scanner walks a file one window at a time and remembers, while a run of
 * windows it has seen before continues, the place that run started: the window's
 * hash, which is how the earlier occurrence is looked up, and three coordinates
 * of the same point — its code line, its source line, and its token index.
 *
 * They travelled as four loose locals and were then handed on individually,
 * which is how the method recording a clone came to take eight parameters and
 * how its call sites came to be two nine-line argument lists that had to agree
 * with each other by eye. Four values that are only ever set together, read
 * together and reset together are one value.
 */
final readonly class MatchedRun
{
    public function __construct(
        public string $hash,
        public int $line,
        public int $realLine,
        public int $token,
    ) {}
}
