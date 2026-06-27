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

use function crc32;
use function serialize;

use LucianoPereira\PhpcpdNext\OutOfBoundsException;

/**
 * @template TFirst
 * @template TSecond
 */
class PairList
{
    private int $size = 0;
    /** @var array<int, TFirst> */
    private array $firstElements;
    /** @var array<int, TSecond> */
    private array $secondElements;

    public function __construct()
    {
        $this->firstElements  = [];
        $this->secondElements = [];
    }

    public function isEmpty(): bool
    {
        return $this->size === 0;
    }

    public function size(): int
    {
        return $this->size;
    }

    /**
     * @param TFirst $first
     * @param TSecond $second
     */
    public function add(mixed $first, mixed $second): void
    {
        $this->firstElements[$this->size]  = $first;
        $this->secondElements[$this->size] = $second;
        $this->size++;
    }

    /** @param PairList<TFirst, TSecond> $other */
    public function addAll(self $other): void
    {
        $otherSize = $other->size;

        for ($i = 0; $i < $otherSize; $i++) {
            $this->firstElements[$this->size]  = $other->firstElements[$i];
            $this->secondElements[$this->size] = $other->secondElements[$i];
            $this->size++;
        }
    }

    /** @return TFirst */
    public function getFirst(int $i): mixed
    {
        $this->checkWithinBounds($i);

        return $this->firstElements[$i];
    }

    /**
     * @param TFirst $value
     */
    public function setFirst(int $i, mixed $value): void
    {
        $this->checkWithinBounds($i);
        $this->firstElements[$i] = $value;
    }

    /** @return TSecond */
    public function getSecond(int $i): mixed
    {
        $this->checkWithinBounds($i);

        return $this->secondElements[$i];
    }

    /**
     * @param TSecond $value
     */
    public function setSecond(int $i, mixed $value): void
    {
        $this->checkWithinBounds($i);
        $this->secondElements[$i] = $value;
    }

    /** @return list<TFirst> */
    public function extractFirstList(): array
    {
        return $this->extractList($this->firstElements);
    }

    /** @return list<TSecond> */
    public function extractSecondList(): array
    {
        return $this->extractList($this->secondElements);
    }

    public function swapEntries(int $i, int $j): void
    {
        $this->checkWithinBounds($i);
        $this->checkWithinBounds($j);

        [$this->firstElements[$i], $this->firstElements[$j]]   = [$this->firstElements[$j], $this->firstElements[$i]];
        [$this->secondElements[$i], $this->secondElements[$j]] = [$this->secondElements[$j], $this->secondElements[$i]];
    }

    /**
     * @template T
     * @param array<int, T> $elements
     * @return list<T>
     */
    private function extractList(array $elements): array
    {
        $result = [];

        for ($i = 0; $i < $this->size; $i++) {
            $result[] = $elements[$i];
        }

        return $result;
    }

    public function clear(): void
    {
        $this->size = 0;
    }

    public function removeLast(): void
    {
        $this->size--;
    }

    public function hashCode(): int
    {
        $prime = 31;
        $hash  = $this->size;
        $hash  = $prime * $hash + crc32(serialize($this->firstElements));

        return $prime * $hash + crc32(serialize($this->secondElements));
    }

    private function checkWithinBounds(int $i): void
    {
        if ($i < 0 || $i >= $this->size) {
            throw new OutOfBoundsException('Out of bounds: ' . $i);
        }
    }
}
