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
use function array_pop;
use function array_values;
use function count;
use function max;
use function min;
use function usort;

class ApproximateCloneDetectingSuffixTree extends SuffixTree
{
    protected int $minLength  = 70;
    /** @var array<int, int> */
    private array $leafCount  = [];
    private int $INDEX_SPREAD = 10;
    /** @var array<int, list<CloneInfo>> */
    private array $cloneInfos = [];
    /**
     * Cap on the length (in tokens) of a single clone the edit-distance matrix can
     * span. The matrix is O(MAX_LENGTH^2), but {@see $edBuffer} is now filled
     * lazily, so a run only pays for the longest clone it actually encounters —
     * not this bound up front. Clones longer than the cap are length-capped (their
     * shorter prefix is still reported), which is vanishingly rare at 4096.
     */
    private int $MAX_LENGTH   = 4096;
    /** @var array<int, array<int, int>> */
    private array $edBuffer   = [];
    private int $headEquality = 10;
    private readonly SubstitutionCost $substitutionCost;

    /** @param list<AbstractToken> $word */
    public function __construct(array $word)
    {
        parent::__construct($word);

        // $edBuffer is filled on demand by calculateMaxLength()/fillEDBuffer(): the
        // DP writes every cell (boundaries explicitly, interior in order) before it
        // is read, so no pre-allocation is needed and memory tracks the longest
        // clone actually seen rather than MAX_LENGTH^2 on every run.
        $this->substitutionCost = new SubstitutionCost();
        $this->ensureChildLists();
        $this->leafCount = array_fill(0, $this->numNodes, 0);
        $this->initLeafCount(0);
    }

    /** @return list<CloneInfo> */
    public function findClones(int $minLength, int $maxErrors, int $headEquality): array
    {
        $this->minLength    = $minLength;
        $this->headEquality = $headEquality;
        $this->cloneInfos   = [];

        $wordCount = count($this->word);

        for ($i = 0; $i < $wordCount; $i++) {
            $node = $this->nextNode->get(0, $this->word[$i]);

            if ($node < 0 || $this->leafCount[$node] <= 1) {
                continue;
            }

            $length      = $this->nodeWordEnd[$node] - $this->nodeWordBegin[$node];
            $numReported = 0;

            for ($e = $this->nodeChildFirst[$node]; $e >= 0; $e = $this->nodeChildNext[$e]) {
                if ($this->matchWord(
                    $i,
                    $i + $length,
                    $this->nodeChildNode[$e],
                    $length,
                    $maxErrors,
                )) {
                    $numReported++;
                }
            }

            if ($length >= $this->minLength && $numReported !== 1) {
                $this->reportClone($i, $i + $length, $node, $length, $length);
            }
        }

        $map = [];

        for ($index = 0; $index <= $wordCount; $index++) {
            if (!empty($this->cloneInfos[$index])) {
                foreach ($this->cloneInfos[$index] as $ci) {
                    if ($ci->length > $minLength) {
                        $previousCi = $map[$ci->token->line] ?? null;

                        if ($previousCi === null) {
                            $map[$ci->token->line] = $ci;
                        } elseif ($ci->length > $previousCi->length) {
                            $map[$ci->token->line] = $ci;
                        }
                    }
                }
            }
        }

        $values = array_values($map);
        usort($values, static fn(CloneInfo $a, CloneInfo $b): int => $b->length - $a->length);

        return $values;
    }

    protected function mayNotMatch(AbstractToken $token): bool
    {
        return $token instanceof Sentinel;
    }

    private function initLeafCount(int $root): void
    {
        // Iterative post-order DFS: the recursive version overflows PHP's call
        // stack when fuzzy normalization produces many identical tokens, which
        // creates a degenerate suffix tree O(n) levels deep.
        /** @var list<array{int, bool}> $stack */
        $stack = [[$root, false]];

        while ($stack !== []) {
            [$node, $processed] = array_pop($stack);

            if ($processed) {
                $this->leafCount[$node] = 0;

                for ($e = $this->nodeChildFirst[$node]; $e >= 0; $e = $this->nodeChildNext[$e]) {
                    $this->leafCount[$node] += $this->leafCount[$this->nodeChildNode[$e]];
                }

                if ($this->leafCount[$node] === 0) {
                    $this->leafCount[$node] = 1;
                }
            } else {
                $stack[] = [$node, true];

                for ($e = $this->nodeChildFirst[$node]; $e >= 0; $e = $this->nodeChildNext[$e]) {
                    $stack[] = [$this->nodeChildNode[$e], false];
                }
            }
        }
    }

    private function matchWord(int $wordStart, int $wordPosition, int $node, int $nodeWordLength, int $maxErrors): bool
    {
        if ($this->leafCount[$node] === 1 && $this->nodeWordBegin[$node] === $wordPosition) {
            return false;
        }

        $currentNodeWordLength = min($this->nodeWordEnd[$node] - $this->nodeWordBegin[$node], $this->MAX_LENGTH - 1);

        $currentLength = $this->calculateMaxLength(
            $wordStart,
            $wordPosition,
            $node,
            $maxErrors,
            $currentNodeWordLength,
        );

        if ($currentLength === 0) {
            return false;
        }

        $best  = $maxErrors + 42;
        $iBest = 0;
        $jBest = 0;

        // Only the in-band endpoints can have distance <= maxErrors; scanning beyond
        // the band would also read stale out-of-band cells from a previous (larger)
        // matrix. Bound the search to the band that calculateMaxLength actually filled.
        $kMax = min($currentLength, $maxErrors);

        for ($k = 0; $k <= $kMax; $k++) {
            $i = $currentLength - $k;
            $j = $currentLength;

            if ($this->edBuffer[$i][$j] < $best) {
                $best  = $this->edBuffer[$i][$j];
                $iBest = $i;
                $jBest = $j;
            }

            $i = $currentLength;
            $j = $currentLength - $k;

            if ($this->edBuffer[$i][$j] < $best) {
                $best  = $this->edBuffer[$i][$j];
                $iBest = $i;
                $jBest = $j;
            }
        }

        while ($wordPosition + $iBest < count($this->word) &&
                $jBest < $currentNodeWordLength &&
                $this->word[$wordPosition + $iBest] !== $this->word[$this->nodeWordBegin[$node] + $jBest] &&
                $this->word[$wordPosition + $iBest]->equals(
                    $this->word[$this->nodeWordBegin[$node] + $jBest],
                )) {
            $iBest++;
            $jBest++;
        }

        $numReported = 0;

        if ($currentLength === $currentNodeWordLength) {
            for ($e = $this->nodeChildFirst[$node]; $e >= 0; $e = $this->nodeChildNext[$e]) {
                if ($this->matchWord(
                    $wordStart,
                    $wordPosition + $iBest,
                    $this->nodeChildNode[$e],
                    $nodeWordLength + $jBest,
                    $maxErrors - $best,
                )) {
                    $numReported++;
                }
            }
        }

        if ($numReported === 1) {
            return true;
        }

        while ($iBest > 0 &&
                $jBest > 0 &&
                !$this->word[$wordPosition + $iBest - 1]->equals(
                    $this->word[$this->nodeWordBegin[$node] + $jBest - 1],
                )) {
            if ($iBest > 1 &&
                    $this->word[$wordPosition + $iBest - 2]->equals(
                        $this->word[$this->nodeWordBegin[$node] + $jBest - 1],
                    )) {
                $iBest--;
            } elseif ($jBest > 1 &&
                    $this->word[$wordPosition + $iBest - 1]->equals(
                        $this->word[$this->nodeWordBegin[$node] + $jBest - 2],
                    )) {
                $jBest--;
            } else {
                $iBest--;
                $jBest--;
            }
        }

        if ($iBest > 0 && $jBest > 0) {
            $numReported++;
            $this->reportClone($wordStart, $wordPosition + $iBest, $node, $jBest, $nodeWordLength + $jBest);
        }

        return $numReported > 0;
    }

    private function calculateMaxLength(
        int $wordStart,
        int $wordPosition,
        int $node,
        int $maxErrors,
        int $currentNodeWordLength,
    ): int {
        $this->edBuffer[0][0] = 0;
        $currentLength        = 1;

        for (; $currentLength <= $currentNodeWordLength; $currentLength++) {
            $best                              = $currentLength;
            $this->edBuffer[0][$currentLength] = $currentLength;
            $this->edBuffer[$currentLength][0] = $currentLength;

            if ($wordPosition + $currentLength >= count($this->word)) {
                break;
            }

            $iChar = $this->word[$wordPosition + $currentLength - 1];
            $jChar = $this->word[$this->nodeWordBegin[$node] + $currentLength - 1];

            if ($this->mayNotMatch($iChar) || $this->mayNotMatch($jChar)) {
                break;
            }

            // Banded edit distance: a cell (i, j) with |i - j| > maxErrors is
            // necessarily > maxErrors and can never lie on a sub-threshold path, so
            // only the diagonal band of width 2*maxErrors+1 is computed. This turns
            // the per-clone cost from O(L^2) to O(L*maxErrors) with identical results.
            // The cell just outside the band is capped so band-edge fills that read it
            // can never beat an in-band path.
            $lowK = $currentLength - $maxErrors;

            if ($lowK > 1) {
                $this->edBuffer[$lowK - 1][$currentLength] = $maxErrors + 1;
                $this->edBuffer[$currentLength][$lowK - 1] = $maxErrors + 1;
            } else {
                $lowK = 1;
            }

            for ($k = $lowK; $k < $currentLength; $k++) {
                $best = min(
                    $best,
                    $this->fillEDBuffer(
                        $k,
                        $currentLength,
                        $wordPosition,
                        $this->nodeWordBegin[$node],
                    ),
                );
            }

            for ($k = $lowK; $k < $currentLength; $k++) {
                $best = min(
                    $best,
                    $this->fillEDBuffer(
                        $currentLength,
                        $k,
                        $wordPosition,
                        $this->nodeWordBegin[$node],
                    ),
                );
            }
            $best = min(
                $best,
                $this->fillEDBuffer(
                    $currentLength,
                    $currentLength,
                    $wordPosition,
                    $this->nodeWordBegin[$node],
                ),
            );

            if ($best > $maxErrors ||
                    $wordPosition - $wordStart + $currentLength <= $this->headEquality &&
                    $best > 0) {
                break;
            }
        }
        $currentLength--;

        return $currentLength;
    }

    private function reportClone(
        int $wordBegin,
        int $wordEnd,
        int $currentNode,
        int $nodeWordPos,
        int $nodeWordLength,
    ): void {
        $length = $wordEnd - $wordBegin;

        if ($length < $this->minLength || $nodeWordLength < $this->minLength) {
            return;
        }

        /** @var PairList<int, int> $otherClones */
        $otherClones = new PairList();
        $this->findRemainingClones(
            $otherClones,
            $nodeWordLength,
            $currentNode,
            $this->nodeWordEnd[$currentNode] - $this->nodeWordBegin[$currentNode] - $nodeWordPos,
            $wordBegin,
        );

        $occurrences = 1 + $otherClones->size();

        $t       = $this->word[$wordBegin];
        $newInfo = new CloneInfo($length, $wordBegin, $occurrences, $t, $otherClones);

        for ($index = max(0, $wordBegin - $this->INDEX_SPREAD + 1); $index <= $wordBegin; $index++) {
            $existingClones = $this->cloneInfos[$index] ?? null;

            if ($existingClones !== null) {
                foreach ($existingClones as $cloneInfo) {
                    if ($cloneInfo->dominates($newInfo, $wordBegin - $index)) {
                        return;
                    }
                }
            }
        }

        for ($i = $wordBegin; $i < $wordEnd; $i += $this->INDEX_SPREAD) {
            $this->cloneInfos[$i][] = new CloneInfo($length - ($i - $wordBegin), $wordBegin, $occurrences, $t, $otherClones);
        }
        $t = $this->word[$wordBegin];

        for ($clone = 0; $clone < $otherClones->size(); $clone++) {
            $start       = $otherClones->getFirst($clone);
            $otherLength = $otherClones->getSecond($clone);

            for ($i = 0; $i < $otherLength; $i += $this->INDEX_SPREAD) {
                $this->cloneInfos[$start + $i][] = new CloneInfo($otherLength - $i, $wordBegin, $occurrences, $t, $otherClones);
            }
        }
    }

    private function fillEDBuffer(int $i, int $j, int $iOffset, int $jOffset): int
    {
        $iChar = $this->word[$iOffset + $i - 1];
        $jChar = $this->word[$jOffset + $j - 1];

        $insertDelete = 1 + min($this->edBuffer[$i - 1][$j], $this->edBuffer[$i][$j - 1]);
        $change       = $this->edBuffer[$i - 1][$j - 1] + $this->substitutionCost->between($iChar, $jChar);

        return $this->edBuffer[$i][$j] = min($insertDelete, $change);
    }

    /** @param PairList<int, int> $clonePositions */
    private function findRemainingClones(
        PairList $clonePositions,
        int $nodeWordLength,
        int $currentNode,
        int $distance,
        int $wordStart,
    ): void {
        for ($nextNode = $this->nodeChildFirst[$currentNode]; $nextNode >= 0; $nextNode = $this->nodeChildNext[$nextNode]) {
            $node = $this->nodeChildNode[$nextNode];
            $this->findRemainingClones($clonePositions, $nodeWordLength, $node, $distance
                    + $this->nodeWordEnd[$node] - $this->nodeWordBegin[$node], $wordStart);
        }

        if ($this->nodeChildFirst[$currentNode] < 0) {
            $start = count($this->word) - $distance - $nodeWordLength;

            if ($start !== $wordStart) {
                $clonePositions->add($start, $nodeWordLength);
            }
        }
    }
}
