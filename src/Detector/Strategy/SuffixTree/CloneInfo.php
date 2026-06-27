<?php

declare(strict_types=1);
/*
 * This file is part of PHP Copy/Paste Detector (PHPCPD).
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 * (c) 2026 Luciano Federico Pereira
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTree;

readonly class CloneInfo
{
    /**
     * @param PairList<int, int> $otherClones
     */
    public function __construct(
        public int $length,
        public int $position,
        private int $occurrences,
        public AbstractToken $token,
        public PairList $otherClones,
    ) {}

    public function dominates(self $ci, int $later): bool
    {
        return $this->length - $later >= $ci->length && $this->occurrences >= $ci->occurrences;
    }
}
