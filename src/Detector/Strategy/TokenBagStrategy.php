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

use function array_keys;
use function count;
use function file_get_contents;
use function max;
use function min;
use function substr_count;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenBag\Block;
use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenBag\BlockExtractor;

/**
 * SourcererCC-style token-bag detector.
 *
 * Each function/method body becomes an order-invariant multiset of tokens. Two
 * blocks are a clone when their token overlap reaches a similarity threshold of
 * the larger block. Because the bag ignores order, this detects clones where
 * statements were REORDERED — which the contiguous matchers (Rabin-Karp exact
 * windows, suffix-tree edit distance) miss, since a swap reads as delete+insert.
 *
 * An inverted index (token -> blocks) gathers only candidate blocks that share a
 * token, instead of comparing every pair.
 *
 * IDF stopword filtering: on corpora of ≥ 5 blocks, token signatures that appear
 * in more than 60 % of all blocks carry no discriminative power (every PHP function
 * uses variables, identifiers, numbers). They are excluded from both the index and
 * the overlap/size computation, leaving only the structurally informative tokens.
 * On small corpora (< 5 blocks — the micro-fixture scans used by unit tests) the
 * filter is disabled and the original full-bag comparison is used.
 */
final class TokenBagStrategy extends AbstractStrategy
{
    private const int   IDF_MIN_BLOCKS     = 5;
    private const float IDF_STOP_THRESHOLD = 0.60;

    /** @var list<Block> */
    private array $blocks = [];
    private ?CodeCloneMap $result = null;

    #[\Override]
    public function processFile(string $file, CodeCloneMap $result): void
    {
        $content = file_get_contents($file);

        if ($content !== false) {
            $result->addToNumberOfLines(substr_count($content, "\n"));
        }

        $blocks = (new BlockExtractor())->extract(
            $file,
            $this->tokensIgnoreList,
            $this->config->fuzzy() || $this->config->typeAnchored(),
            $this->normalizer,
        );

        foreach ($blocks as $block) {
            if ($block->size >= $this->config->minTokens()) {
                $this->blocks[] = $block;
            }
        }

        $this->result = $result;
    }

    #[\Override]
    public function postProcess(): void
    {
        if ($this->result === null || $this->blocks === []) {
            return;
        }

        $stopwords = $this->computeStopwords();
        $sizes     = $this->computeUsefulSizes($stopwords);
        $index     = $this->buildInvertedIndex($stopwords);
        $threshold = $this->config->similarity();

        foreach ($this->blocks as $i => $block) {
            if ($sizes[$i] === 0) {
                continue;
            }

            foreach ($this->candidatesFor($i, $block, $index, $stopwords) as $j) {
                $other   = $this->blocks[$j];
                $maxSize = max($sizes[$i], $sizes[$j]);

                if ($maxSize === 0) {
                    continue;
                }

                $overlap = $this->overlap($block->bag, $other->bag, $stopwords);

                if ($overlap >= $threshold * $maxSize) {
                    $this->result->add(
                        new CodeClone(
                            new CodeCloneFile($block->file, $block->startLine),
                            new CodeCloneFile($other->file, $other->startLine),
                            $block->endLine - $block->startLine,
                            $overlap,
                        ),
                    );
                }
            }
        }
    }

    /**
     * Tokens that appear in more than IDF_STOP_THRESHOLD of all blocks are
     * structural noise (every PHP function uses T_VARIABLE, identifiers, etc.)
     * and are excluded from similarity comparisons. The filter is disabled for
     * small corpora where document-frequency statistics are not meaningful.
     *
     * @return array<string, true>
     */
    private function computeStopwords(): array
    {
        $n = count($this->blocks);

        if ($n < self::IDF_MIN_BLOCKS) {
            return [];
        }

        $df = [];

        foreach ($this->blocks as $block) {
            foreach (array_keys($block->bag) as $sig) {
                $df[$sig] = ($df[$sig] ?? 0) + 1;
            }
        }

        $stopwords = [];

        foreach ($df as $sig => $freq) {
            if ($freq / $n > self::IDF_STOP_THRESHOLD) {
                $stopwords[$sig] = true;
            }
        }

        return $stopwords;
    }

    /**
     * Per-block token count excluding stopwords. When no stopwords are active
     * (small corpus) this equals Block::$size.
     *
     * @param array<string, true> $stopwords
     * @return array<int, int>
     */
    private function computeUsefulSizes(array $stopwords): array
    {
        $sizes = [];

        foreach ($this->blocks as $i => $block) {
            if ($stopwords === []) {
                $sizes[$i] = $block->size;
                continue;
            }

            $useful = 0;

            foreach ($block->bag as $sig => $count) {
                if (!isset($stopwords[$sig])) {
                    $useful += $count;
                }
            }

            $sizes[$i] = $useful;
        }

        return $sizes;
    }

    /**
     * @param array<string, true> $stopwords
     * @return array<string, list<int>>
     */
    private function buildInvertedIndex(array $stopwords): array
    {
        $index = [];

        foreach ($this->blocks as $i => $block) {
            foreach (array_keys($block->bag) as $signature) {
                if (!isset($stopwords[$signature])) {
                    $index[$signature][] = $i;
                }
            }
        }

        return $index;
    }

    /**
     * Block indices > $i that share at least one non-stopword token with $block.
     *
     * @param array<string, list<int>> $index
     * @param array<string, true> $stopwords
     * @return list<int>
     */
    private function candidatesFor(int $i, Block $block, array $index, array $stopwords): array
    {
        $candidates = [];

        foreach (array_keys($block->bag) as $signature) {
            if (isset($stopwords[$signature])) {
                continue;
            }

            foreach ($index[$signature] ?? [] as $j) {
                if ($j > $i) {
                    $candidates[$j] = true;
                }
            }
        }

        return array_keys($candidates);
    }

    /**
     * Multiset intersection size of two bags, restricted to non-stopword tokens.
     *
     * @param array<string, int> $a
     * @param array<string, int> $b
     * @param array<string, true> $stopwords
     */
    private function overlap(array $a, array $b, array $stopwords): int
    {
        $shared = 0;

        foreach ($a as $signature => $count) {
            if (!isset($stopwords[$signature]) && isset($b[$signature])) {
                $shared += min($count, $b[$signature]);
            }
        }

        return $shared;
    }
}
