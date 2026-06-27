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

require_once __DIR__ . '/_guard.php';

use LucianoPereira\PhpcpdNext\Arguments;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTreeStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * R2 — type-aware edit weights, end to end.
 *
 * Three fixtures differ from the base by exactly one token in the same mid-body
 * position: one changes a control keyword (`if` → `while`, structural), the other
 * an identifier (`$scaled` → `$banner`, cosmetic). With min-tokens above each half
 * of the split, only a bridged match qualifies, and bridging spends the edit budget
 * equal to the substitution cost. Because a keyword change costs 2 and an identifier
 * change costs 1, the keyword change needs a larger --edit-distance to be detected.
 *
 * Thresholds empirically pinned (effective word = 67 tokens, diff at index 32 →
 * halves 32 and 34, so min-tokens 35 forces a bridge).
 */
#[CoversClass(SuffixTreeStrategy::class)]
#[CoversClass(Detector::class)]
final class EditWeightDetectionTest extends TestCase
{
    private const string BASE       = __DIR__ . '/fixtures/r2/base.php';
    private const string KEYWORD    = __DIR__ . '/fixtures/r2/keyword.php';    // if -> while (cost 2)
    private const string IDENTIFIER = __DIR__ . '/fixtures/r2/identifier.php'; // $scaled -> $banner (cost 1)

    #[Test]
    public function neither_change_is_visible_without_an_edit_budget(): void
    {
        self::assertTrue($this->detect(self::KEYWORD, editDistance: 0)->isEmpty());
        self::assertTrue($this->detect(self::IDENTIFIER, editDistance: 0)->isEmpty());
    }

    #[Test]
    public function a_cosmetic_identifier_change_is_bridged_at_edit_distance_1(): void
    {
        self::assertFalse(
            $this->detect(self::IDENTIFIER, editDistance: 1)->isEmpty(),
            'a renamed identifier (cost 1) should be bridged with a budget of 1',
        );
    }

    #[Test]
    public function a_structural_keyword_change_is_not_bridged_at_edit_distance_1(): void
    {
        // The R2 point: if -> while costs 2, so a budget of 1 cannot bridge it,
        // even though the identical cosmetic-distance change is bridged at 1.
        self::assertTrue(
            $this->detect(self::KEYWORD, editDistance: 1)->isEmpty(),
            'a changed control keyword (cost 2) must not be bridged with a budget of 1',
        );
    }

    #[Test]
    public function the_structural_change_is_bridged_once_the_budget_reaches_2(): void
    {
        self::assertFalse(
            $this->detect(self::KEYWORD, editDistance: 2)->isEmpty(),
            'with a budget of 2 the keyword change is affordable',
        );
    }

    private function detect(string $variant, int $editDistance): CodeCloneMap
    {
        $config = new StrategyConfiguration(new Arguments(
            directories: [],
            suffixes: ['.php'],
            exclude: [],
            pmdCpdXmlLogfile: null,
            linesThreshold: 1,
            tokensThreshold: 35,
            fuzzy: false,
            verbose: false,
            help: false,
            version: false,
            algorithm: null,
            editDistance: $editDistance,
            headEquality: 10,
        ));

        return (new Detector(new SuffixTreeStrategy($config)))
            ->copyPasteDetection([self::BASE, $variant]);
    }
}
