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


use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The default pipeline runs two engines over the same files and merges what
 * they find. They see the same duplication and need not agree on where it ends,
 * and identity is a content hash, so two spans differing by a line were two
 * clones and the reader was shown the same duplication twice.
 */
#[CoversClass(CodeCloneMap::class)]
final class MergeContainmentTest extends TestCase
{
    #[Test]
    public function aMergedCloneInsideOneAlreadyFoundIsNotReportedTwice(): void
    {
        // What two Laravel controllers sharing a block actually produced:
        // Rabin-Karp said lines 8-42, the token bag said 9-42.
        $wide   = self::clone(35, 8);
        $merged = self::merged($wide, self::clone(34, 9));

        self::assertCount(1, $merged, 'one duplication, one finding');
        self::assertSame($wide->id(), $merged->clones()[0]->id(), 'the wider span is the one kept');
    }

    #[Test]
    public function theTotalsDoNotMoveWhenAContainedCloneIsDropped(): void
    {
        // The point of dropping only *contained* clones: every line of the one
        // dropped is named by the one kept, so the union cannot change.
        $alone = new CodeCloneMap();
        $alone->add(self::clone(35, 8));
        $alone->addToNumberOfLines(200);

        $merged = new CodeCloneMap();
        $merged->add(self::clone(35, 8));
        $merged->addToNumberOfLines(200);

        $other = new CodeCloneMap();
        $other->add(self::clone(34, 9));
        $merged->mergeFrom($other);

        self::assertSame(
            $alone->numberOfDuplicatedLines(),
            $merged->numberOfDuplicatedLines(),
            'a subset contributes no new covered lines',
        );
        self::assertSame($alone->percentage(), $merged->percentage());
    }

    #[Test]
    public function twoDistinctDuplicationsInTheSameFilesBothSurvive(): void
    {
        // These do not meet — eighty lines apart — so they are two regions and
        // must stay two findings.
        self::assertCount(2, self::merged(self::clone(20, 10), self::clone(20, 100)));
    }

    #[Test]
    public function aCloneNamingMoreSitesIsNeverFoldedIntoOneNamingFewer(): void
    {
        $twoSites = new CodeClone(
            new CodeCloneFile('a.php', 10, 30),
            new CodeCloneFile('b.php', 10, 30),
            30,
            120,
        );

        $threeSites = new CodeClone(
            new CodeCloneFile('a.php', 12, 20),
            new CodeCloneFile('b.php', 12, 20),
            20,
            80,
        );
        $threeSites->add(new CodeCloneFile('c.php', 12, 20));

        self::assertCount(
            2,
            self::merged($twoSites, $threeSites),
            'a third site is a different finding, however the lines fall',
        );
    }

    /**
     * The two engines need not agree on where a region begins either, and when
     * they disagree at both ends neither span contains the other.
     */
    #[Test]
    public function twoReadingsOfOneRegionAreOneFinding(): void
    {
        // A token-bag block and a Rabin-Karp run inside it, which is what
        // phpunit's tandem-repeat test files produce: 311 lines and 155 within.
        $region = self::clone(40, 10);
        $merged = self::merged($region, self::clone(20, 25));

        self::assertCount(1, $merged, 'one region, one finding');
        self::assertSame($region->id(), $merged->clones()[0]->id(), 'the region is kept, not the reading of it');
    }

    /**
     * What the reader is shown when the two engines each found one thing: the
     * first engine's map with the second's merged into it, which is the order
     * the pipeline merges in.
     */
    private static function merged(CodeClone $first, CodeClone $second): CodeCloneMap
    {
        $map = new CodeCloneMap();
        $map->add($first);

        $other = new CodeCloneMap();
        $other->add($second);

        $map->mergeFrom($other);

        return $map;
    }

    private static function clone(int $lines, int $start): CodeClone
    {
        return new CodeClone(
            new CodeCloneFile('app/Http/Controllers/OrderController.php', $start, $lines),
            new CodeCloneFile('app/Http/Controllers/InvoiceController.php', $start, $lines),
            $lines,
            $lines * 4,
        );
    }
}
