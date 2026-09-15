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

namespace LucianoPereira\PhpcpdNext\Tests\Regression;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Merging two engines takes the union of what they found and drops what is
 * described twice. It cannot invent duplication neither engine saw, so the
 * merged pipeline can never report more clones than its halves report between
 * them.
 *
 * It did. `mergeFrom()` drops a clone another already *describes*, which is
 * half of settling: the region collapse — a self-similar run reported once
 * rather than once per period — lives in `settle()`, and the merged path never
 * called it. On phpunit the shipped default reported 312 clones where
 * Rabin-Karp finds 170 and the token bag 37; settling the merged map brings it
 * to 193, with duplicated lines unchanged at 10,256.
 *
 * The map is built here rather than detected, because no fixture in this
 * repository reproduces it: the shape needs one engine's coarse reading of a
 * periodic region over another's finer one, which is a property of a corpus
 * rather than of a few files. What is asserted is the mechanism — that a merged
 * map is settled — not the corpus that exposed it.
 */
#[CoversClass(CodeCloneMap::class)]
final class MergedPipelineSettlesTest extends TestCase
{
    #[Test]
    public function mergingSettlesTheResultingMap(): void
    {
        $fine = new CodeCloneMap();

        // One class naming every block of a periodic region — the reading a
        // reader wants, and the one the region collapse keeps.
        $fineClone = new CodeClone(
            new CodeCloneFile('run.php', 10, 20, 200),
            new CodeCloneFile('run.php', 60, 20, 200),
            20,
            200,
        );
        $fineClone->add(new CodeCloneFile('run.php', 110, 20, 200));
        $fineClone->add(new CodeCloneFile('run.php', 160, 20, 200));

        $fine->add($fineClone);

        $coarse = new CodeCloneMap();

        // The same region glued at a larger period — one engine's coarser view,
        // naming two places where the finer class names four. The site count is what puts it
        // past `mergeFrom()`: that drops a clone another already describes, and
        // ruling G holds that "a clone naming three sites is not the same
        // finding as one naming two, however the lines fall".
        $coarseClone = new CodeClone(
            new CodeCloneFile('run.php', 10, 50, 500),
            new CodeCloneFile('run.php', 110, 50, 500),
            50,
            500,
        );

        $coarse->add($coarseClone);

        $before = $fine->count() + $coarse->count();

        $fine->mergeFrom($coarse);

        // Half of settling, and the half the merged pipeline used to stop at:
        // `mergeFrom()` drops a clone another already *describes*, and the
        // coarse reading here describes a different span, so it survives.
        self::assertSame(
            $before,
            $fine->count(),
            'mergeFrom() is expected to keep the coarser reading — if it drops it, this test is no longer testing the gap',
        );

        $fine->settle();

        self::assertLessThan(
            $before,
            $fine->count(),
            'settling did not collapse a coarser reading of a region already described finely',
        );
    }
}
