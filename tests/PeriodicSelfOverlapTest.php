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

namespace LucianoPereira\PhpcpdNext\Tests;

use function array_values;
use function count;
use function dirname;
use function max;
use function min;

use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A stretch of code reported as a duplicate of itself.
 *
 * Normalization is what makes this reachable. A run of near-identical members —
 * the fixture's twelve one-line accessors, or a flat list of constants — has no
 * two identical tokens under raw matching: every method name and every array key
 * differs. Normalized, the names and the keys are erased and what is left is one
 * token sequence repeating with a fixed period, so the run matches *itself*
 * shifted by one period and the matcher extends that match as far as the
 * repetition goes.
 *
 * The report that comes out names two ranges that are mostly the same lines.
 * Unclamped, this fixture gives `12-65` against `17-70`: 54 lines each, 49 of
 * them shared, from a file whose longest honest clone is four lines. On
 * php-parser the same defect produced `NodeAbstract.php:15-96` against `25-107`
 * — 72 lines of overlap, asserted at +1.72 — and ten of that corpus's
 * thirty-five findings were this shape.
 *
 * The fix is the rule {@see AnchorSet::extend()} has always applied: two copies
 * inside one file may together be no longer than the distance between them.
 * Truncating rather than dropping is the point. The repetition among these
 * accessors is real duplication and worth reporting; it is the *extent* that was
 * false, and one period is the honest extent.
 */
#[CoversClass(DefaultStrategy::class)]
final class PeriodicSelfOverlapTest extends TestCase
{
    private const string FIXTURE = '/tests/fixtures/periodic/Periodic.php';

    /**
     * The premise: raw matching finds nothing here, so anything the normalized
     * run reports is produced by normalization and not by the fixture being an
     * ordinary copy-paste.
     */
    #[Test]
    public function rawMatchingFindsNoCloneInTheFixture(): void
    {
        self::assertCount(0, $this->clonesIn(fuzzy: false, minTokens: 20));
    }

    /**
     * The repetition is real and must still be reported — the fix truncates an
     * over-long extent, it does not silence the finding.
     */
    #[Test]
    public function normalizedMatchingStillReportsTheRepetition(): void
    {
        self::assertNotCount(0, $this->clonesIn(fuzzy: true, minTokens: 20));
    }

    /**
     * The defect itself: no clone may name two ranges of one file that overlap.
     *
     * Checked at two token floors because the unclamped matcher cleared both —
     * it reported the same 220-token span at a floor of 20 and of 40, the extent
     * having nothing to do with either.
     */
    #[Test]
    public function noCloneOverlapsItself(): void
    {
        foreach ([20, 40] as $minTokens) {
            foreach ($this->clonesIn(fuzzy: true, minTokens: $minTokens) as $clone) {
                $occurrences = array_values($clone->files());

                if (count($occurrences) !== 2 || $occurrences[0]->name !== $occurrences[1]->name) {
                    continue;
                }

                $startA = $occurrences[0]->startLine;
                $startB = $occurrences[1]->startLine;
                $endA   = $occurrences[0]->lastLine($clone->numberOfLines());
                $endB   = $occurrences[1]->lastLine($clone->numberOfLines());

                self::assertLessThan(
                    max($startA, $startB),
                    min($endA, $endB),
                    "at --min-tokens={$minTokens} a clone claims lines {$startA}-{$endA} duplicate "
                    . "{$startB}-{$endB} of the same file, which are mostly the same lines",
                );
            }
        }
    }

    /** @return list<\LucianoPereira\PhpcpdNext\CodeClone> */
    private function clonesIn(bool $fuzzy, int $minTokens): array
    {
        return array_values((new Detector(new DefaultStrategy(
            new StrategyConfiguration(
                minLines:      3,
                minTokens:     $minTokens,
                normalization: $fuzzy ? Normalization::TypeAnchored : Normalization::Raw,
                minSimilarity: 0.7,
            ),
        )))->copyPasteDetection([dirname(__DIR__) . self::FIXTURE])->clones());
    }
}
