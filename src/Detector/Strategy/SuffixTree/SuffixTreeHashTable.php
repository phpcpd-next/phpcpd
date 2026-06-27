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

use function array_fill;
use function array_keys;
use function ceil;

class SuffixTreeHashTable
{
    /** @var list<int> */
    private array $allowedSizes = [53, 97, 193, 389, 769, 1543,
        3079, 6151, 12289, 24593, 49157, 98317, 196613, 393241, 786433,
        1572869, 3145739, 6291469, 12582917, 25165843, 50331653, 100663319,
        201326611, 402653189, 805306457, 1610612741];

    private int $tableSize;
    /** @var array<int, int> */
    private array $keyNodes;
    /** @var array<int, AbstractToken|null> */
    private array $keyChars;
    /** @var array<int, int> */
    private array $resultNodes;
    private int $_numStoredNodes = 0;
    private int $_numFind        = 0;
    private int $_numColl        = 0;

    public function __construct(int $numNodes)
    {
        $minSize   = (int) ceil(1.5 * $numNodes);
        $sizeIndex = 0;

        while ($this->allowedSizes[$sizeIndex] < $minSize) {
            $sizeIndex++;
        }
        $this->tableSize = $this->allowedSizes[$sizeIndex];

        $this->keyNodes    = array_fill(0, $this->tableSize, 0);
        $this->keyChars    = array_fill(0, $this->tableSize, null);
        $this->resultNodes = array_fill(0, $this->tableSize, 0);
    }

    public function get(int $keyNode, AbstractToken $keyChar): int
    {
        $pos = $this->hashFind($keyNode, $keyChar);

        if ($this->keyChars[$pos] === null) {
            return -1;
        }

        return $this->resultNodes[$pos];
    }

    public function put(int $keyNode, AbstractToken $keyChar, int $resultNode): void
    {
        $pos = $this->hashFind($keyNode, $keyChar);

        if ($this->keyChars[$pos] === null) {
            $this->_numStoredNodes++;
            $this->keyChars[$pos] = $keyChar;
            $this->keyNodes[$pos] = $keyNode;
        }
        $this->resultNodes[$pos] = $resultNode;
    }

    /**
     * @param array<int, int> $nodeFirstIndex
     * @param array<int, int> $nodeNextIndex
     * @param array<int, int> $nodeChild
     */
    public function extractChildLists(array &$nodeFirstIndex, array &$nodeNextIndex, array &$nodeChild): void
    {
        foreach (array_keys($nodeFirstIndex) as $k) {
            $nodeFirstIndex[$k] = -1;
        }
        $free = 0;

        for ($i = 0; $i < $this->tableSize; $i++) {
            if ($this->keyChars[$i] !== null) {
                $nodeChild[$free]                    = $this->resultNodes[$i];
                $nodeNextIndex[$free]                = $nodeFirstIndex[$this->keyNodes[$i]];
                $nodeFirstIndex[$this->keyNodes[$i]] = $free++;
            }
        }
    }

    private function hashFind(int $keyNode, AbstractToken $keyChar): int
    {
        $this->_numFind++;
        $hash      = $keyChar->hashCode();
        $pos       = $this->posMod($this->primaryHash($keyNode, $hash));
        $secondary = $this->secondaryHash($keyNode, $hash);

        while ($this->keyChars[$pos] !== null) {
            if ($this->keyNodes[$pos] === $keyNode && $keyChar->equals($this->keyChars[$pos])) {
                break;
            }
            $this->_numColl++;
            $pos = ($pos + $secondary) % $this->tableSize;
        }

        return $pos;
    }

    private function primaryHash(int $keyNode, int $keyCharHash): int
    {
        return $keyCharHash ^ (13 * $keyNode);
    }

    private function secondaryHash(int $keyNode, int $keyCharHash): int
    {
        $result = $this->posMod(($keyCharHash ^ (1025 * $keyNode)));

        if ($result === 0) {
            return 2;
        }

        return $result;
    }

    private function posMod(int $x): int
    {
        $x %= $this->tableSize;

        if ($x < 0) {
            $x += $this->tableSize;
        }

        return $x;
    }
}
