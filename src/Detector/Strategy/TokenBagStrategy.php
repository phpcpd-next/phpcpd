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

        if ($content === false) {
            $result->couldNotRead($file);
        } else {
            $result->addToNumberOfLines(self::countLines($content));
        }

        // The bag never takes the normalized view, whatever the run asked for.
        //
        // This is a property of the bag, not a tuning preference. A bag is
        // order-free, and normalization folds every identifier to `ID` and
        // every literal to `STR` or `NUM`; together those erase everything that
        // tells one data table from another, so two tables of the same shape
        // become the same multiset. Order is what keeps the contiguous
        // matcher's tables apart, and the bag has thrown order away.
        //
        // Measured, pool v6, two blind raters at kappa 0.797 on symfony/string,
        // the corpus where the data tables live:
        //
        //     raw view          6/7  and 6/7   — 0.857, 0.857
        //     normalized view   3/15 and 2/15  — 0.200, 0.133
        //
        // Twelve or thirteen of fifteen were false and every one was asserted:
        // the `table` stratum demoted none of them, so nothing downstream was
        // catching it. Both raters described the same cause independently — a
        // French pluralization word list matched against an unrelated
        // string-casing data provider, two halves of one precedence map matched
        // against each other.
        //
        // So the mode is gone rather than defaulted away from. A flag whose
        // measured precision is 0.13 is not a choice a caller should be able to
        // make by accident, and leaving it selectable would have kept it in the
        // benchmark's variant list as though it were a real alternative.
        //
        // What it costs is the rename-and-reorder clone: the raw bag sees the
        // rename. Rabin-Karp keeps the normalized view and the Type-2 recall
        // that comes with it, and `--algorithm=unified` remains the engine that
        // classifies reordering outright.
        $blocks = (new BlockExtractor())->extract(
            $file,
            self::IGNORED_TOKENS,
            self::QUALIFIED_NAMES,
            false,
            $this->normalizer,
        );

        foreach ($blocks as $block) {
            if ($block->size >= $this->config->minTokens) {
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
        $threshold = $this->config->minSimilarity;

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

                if ($overlap < $threshold * $maxSize) {
                    continue;
                }

                // What the pair shares, over the whole bags.
                //
                // `$overlap` is the number the *decision* is made on, and it is
                // the right number for that: stopwords are removed because a
                // token three quarters of the corpus uses is no evidence that
                // these two blocks are related. It is the wrong number to
                // report, and it was reported — passed straight in as the
                // clone's token count, and printed as `tokens` by the PMD,
                // SARIF and JSON writers. It is not a property of the clone at
                // all: the stopword set is fixed by document frequency across
                // whatever else was scanned, so the same two methods reported a
                // different size depending on the rest of the run. Measured
                // against the tokens the blocks actually span, it ran to a
                // median of 0.04 on symfony-console — a 500-token body reported
                // at twenty.
                //
                // The unfiltered intersection answers the question the field
                // asks — how many significant tokens these two have in common —
                // and answers it the same way wherever it is run.
                $shared = $this->overlap($block->bag, $other->bag, []);

                // `--min-tokens` is a floor on the duplication, not on the
                // haystack it was found in.
                //
                // Rabin-Karp gives that by construction: its match *is* a run of
                // at least `minTokens` tokens. This engine only ever gated the
                // two blocks, each of which can clear the floor while sharing
                // far less with the other — so one flag meant two different
                // things depending on which arm answered, and the merged default
                // runs both. On symfony-console it let three unrelated sites —
                // `Table.php`, `ConsoleLoggerTest.php`, `InputTest.php` — be
                // reported as one clone on 61 shared tokens under a floor of
                // 100.
                //
                // The stopword filter is what makes it reachable. The threshold
                // is a fraction of the *discriminative* size, and on a bag that
                // is mostly punctuation and variables that size is small, so a
                // large fraction of it is a small amount of evidence. Filtering
                // is right for deciding whether two blocks are related and
                // cannot also be what decides whether there is enough of them.
                //
                // Implied, but kept as the pre-filter it also is: the shared
                // multiset is no larger than either bag, so a pair clearing this
                // clears the per-block gate in `processFile()` too.
                if ($shared < $this->config->minTokens) {
                    continue;
                }

                // `endLine` is the line of the block's last token, so the span
                // is inclusive of both ends. Without the +1 every token-bag
                // clone was reported one line short, and a two-line block came
                // out as one.
                $linesBlock = $block->endLine + 1 - $block->startLine;
                $linesOther = $other->endLine + 1 - $other->startLine;

                // Not an exact clone, and the bag is the last thing that could
                // claim otherwise.
                //
                // Every clone here used to be built with `gapped: false`, which
                // is the flag all four writers read to print **Exact** — SARIF
                // `duplicate-code` at level note, JSON `gapped: false`, the
                // console with no marker at all. A bag is an order-free
                // multiset: it establishes that two blocks hold the same
                // material and says nothing whatever about the sequence. Asked
                // whether their sites agree in extent and order, 80 of
                // symfony-console's 85 bag classes do not — nor should they,
                // since order is precisely what this engine throws away to find
                // what the contiguous matcher cannot.
                //
                // `reordered` implies `gapped`, which {@see CodeClone} states as
                // its contract: "a reordered clone is never an exact copy
                // either". The unified engine has always set both together. This
                // one set neither, so the one engine that cannot see order was
                // also the one asserting that order matched.
                //
                // No divergences are named, because the bag cannot name them: it
                // knows the material is shared and not where it moved to. That
                // is what an empty divergence list already means for every
                // engine but the unified one.
                $this->result->add(
                    new CodeClone(
                        new CodeCloneFile($block->file, $block->startLine, $linesBlock, $block->size, $block->startToken),
                        new CodeCloneFile($other->file, $other->startLine, $linesOther, $other->size, $other->startToken),
                        $linesBlock,
                        $shared,
                        gapped: true,
                        reordered: true,
                    ),
                );
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
