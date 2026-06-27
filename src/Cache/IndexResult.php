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

namespace LucianoPereira\PhpcpdNext\Cache;

use LucianoPereira\PhpcpdNext\CodeCloneMap;

/**
 * The outcome of an incremental index run: the clones detected, plus how many
 * files were served from the persisted index ($reused) versus re-tokenized
 * because their content changed ($scanned). The split is the incrementality
 * metric — on a warm CI run only the touched files should be scanned.
 */
final readonly class IndexResult
{
    public function __construct(
        public CodeCloneMap $clones,
        public int $reused,
        public int $scanned,
    ) {}
}
