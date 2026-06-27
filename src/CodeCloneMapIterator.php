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

namespace LucianoPereira\PhpcpdNext;

use function array_reverse;
use function count;
use function usort;

/** @implements \Iterator<int, CodeClone> */
final class CodeCloneMapIterator implements \Iterator
{
    /** @var list<CodeClone> */
    private array $clones;
    private int $position = 0;

    public function __construct(CodeCloneMap $clones)
    {
        $this->clones = $clones->clones();

        usort(
            $this->clones,
            static function (CodeClone $a, CodeClone $b): int {
                return $a->numberOfLines() <=> $b->numberOfLines();
            },
        );

        $this->clones = array_reverse($this->clones);
    }

    #[\Override]
    public function rewind(): void
    {
        $this->position = 0;
    }
    #[\Override]
    public function valid(): bool
    {
        return $this->position < count($this->clones);
    }
    #[\Override]
    public function key(): int
    {
        return $this->position;
    }
    #[\Override]
    public function current(): CodeClone
    {
        return $this->clones[$this->position];
    }
    #[\Override]
    public function next(): void
    {
        $this->position++;
    }
}
