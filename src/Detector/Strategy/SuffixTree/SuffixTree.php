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
use function count;

class SuffixTree
{
    protected int $INFTY;
    /** @var list<AbstractToken> */
    protected array $word;
    protected int $numNodes = 0;
    /** @var array<int, int> */
    protected array $nodeWordBegin;
    /** @var array<int, int> */
    protected array $nodeWordEnd;
    /** @var array<int, int> */
    protected array $suffixLink;
    protected SuffixTreeHashTable $nextNode;
    /** @var array<int, int> */
    protected array $nodeChildFirst = [];
    /** @var array<int, int> */
    protected array $nodeChildNext  = [];
    /** @var array<int, int> */
    protected array $nodeChildNode  = [];
    private int $currentNode  = 0;
    private int $refWordBegin = 0;
    private int $explicitNode = 0;

    /** @param list<AbstractToken> $word */
    public function __construct(array $word)
    {
        $this->word  = $word;
        $size        = count($word);
        $this->INFTY = $size;

        $expectedNodes       = 2 * $size;
        $this->nodeWordBegin = array_fill(0, $expectedNodes, 0);
        $this->nodeWordEnd   = array_fill(0, $expectedNodes, 0);
        $this->suffixLink    = array_fill(0, $expectedNodes, 0);
        $this->nextNode      = new SuffixTreeHashTable($expectedNodes);

        $this->createRootNode();

        for ($i = 0; $i < $size; $i++) {
            $this->update($i);
            $this->canonize($i + 1);
        }
    }

    protected function ensureChildLists(): void
    {
        if (count($this->nodeChildFirst) < $this->numNodes) {
            $this->nodeChildFirst = array_fill(0, $this->numNodes, 0);
            $this->nodeChildNext  = array_fill(0, $this->numNodes, 0);
            $this->nodeChildNode  = array_fill(0, $this->numNodes, 0);
            $this->nextNode->extractChildLists($this->nodeChildFirst, $this->nodeChildNext, $this->nodeChildNode);
        }
    }

    private function createRootNode(): void
    {
        $this->numNodes         = 1;
        $this->nodeWordBegin[0] = 0;
        $this->nodeWordEnd[0]   = 0;
        $this->suffixLink[0]    = -1;
    }

    private function update(int $charPos): void
    {
        $lastNode = 0;

        while (!$this->testAndSplit($charPos, $this->word[$charPos])) {
            $newNode                       = $this->numNodes++;
            $this->nodeWordBegin[$newNode] = $charPos;
            $this->nodeWordEnd[$newNode]   = $this->INFTY;
            $this->nextNode->put($this->explicitNode, $this->word[$charPos], $newNode);

            if ($lastNode !== 0) {
                $this->suffixLink[$lastNode] = $this->explicitNode;
            }
            $lastNode          = $this->explicitNode;
            $this->currentNode = $this->suffixLink[$this->currentNode];
            $this->canonize($charPos);
        }

        if ($lastNode !== 0) {
            $this->suffixLink[$lastNode] = $this->currentNode;
        }
    }

    private function testAndSplit(int $refWordEnd, AbstractToken $nextCharacter): bool
    {
        if ($this->currentNode < 0) {
            return true;
        }

        if ($refWordEnd <= $this->refWordBegin) {
            if ($this->nextNode->get($this->currentNode, $nextCharacter) < 0) {
                $this->explicitNode = $this->currentNode;

                return false;
            }

            return true;
        }

        $next = $this->nextNode->get($this->currentNode, $this->word[$this->refWordBegin]);

        if ($nextCharacter->equals($this->word[$this->nodeWordBegin[$next] + $refWordEnd - $this->refWordBegin])) {
            return true;
        }

        $this->explicitNode                       = $this->numNodes++;
        $this->nodeWordBegin[$this->explicitNode] = $this->nodeWordBegin[$next];
        $this->nodeWordEnd[$this->explicitNode]   = $this->nodeWordBegin[$next] + $refWordEnd - $this->refWordBegin;
        $this->nextNode->put($this->currentNode, $this->word[$this->refWordBegin], $this->explicitNode);

        $this->nodeWordBegin[$next] += $refWordEnd - $this->refWordBegin;
        $this->nextNode->put($this->explicitNode, $this->word[$this->nodeWordBegin[$next]], $next);

        return false;
    }

    private function canonize(int $refWordEnd): void
    {
        if ($this->currentNode === -1) {
            $this->currentNode = 0;
            $this->refWordBegin++;
        }

        if ($refWordEnd <= $this->refWordBegin) {
            return;
        }

        $next = $this->nextNode->get(
            $this->currentNode,
            $this->word[$this->refWordBegin],
        );

        while ($this->nodeWordEnd[$next] - $this->nodeWordBegin[$next] <= $refWordEnd
                - $this->refWordBegin) {
            $this->refWordBegin += $this->nodeWordEnd[$next] - $this->nodeWordBegin[$next];
            $this->currentNode = $next;

            if ($refWordEnd > $this->refWordBegin) {
                $next = $this->nextNode->get($this->currentNode, $this->word[$this->refWordBegin]);
            } else {
                break;
            }
        }
    }
}
