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

use function copy;
use function count;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Engine;
use LucianoPereira\PhpcpdNext\Util\FileFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use LucianoPereira\PhpcpdNext\Detector\CloneSuppressions;

/**
 * `tests/fixtures/ignore/` holds three matched pairs — one per marker form —
 * and nothing ran them.
 *
 * Each pair is the same function body twice, so each pair is a clone unless the
 * marker on the `Marked` side removes it. The pairs make the three notations
 * comparable: a region between `phpcpd-ignore-start` and `-end`, a single
 * `phpcpd-ignore-line`, and a declaration carrying `@phpcpd-ignore-clone`.
 */
#[CoversClass(CloneSuppressions::class)]
final class IgnoreMarkerFixtureTest extends TestCase
{
    private const string IGNORE = __DIR__ . '/fixtures/ignore';

    /**
     * The control, and it comes first: the bodies really are a clone.
     *
     * Without it "no clones found" proves nothing — a pair below the threshold
     * reports exactly the same thing as a pair correctly suppressed.
     */
    #[Test]
    public function theUnmarkedBodiesAreACloneOfEachOther(): void
    {
        // Both unmarked halves in one directory, since the finder walks
        // directories and the pairs live in separate ones.
        $dir = sys_get_temp_dir() . '/phpcpd-ignore-' . uniqid();
        mkdir($dir, 0o777, true);
        copy(self::IGNORE . '/region/Plain.php', $dir . '/A.php');
        copy(self::IGNORE . '/line/Plain.php', $dir . '/B.php');

        try {
            self::assertSame(
                1,
                self::clonesIn([$dir]),
                'the fixtures must clone, or the suppression assertions below are vacuous',
            );
        } finally {
            foreach ((array) scandir($dir) as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    unlink($dir . '/' . $entry);
                }
            }

            is_dir($dir) && rmdir($dir);
        }
    }

    #[Test]
    public function aMarkedRegionIsNotReported(): void
    {
        self::assertSame(0, self::clonesIn([self::IGNORE . '/region']));
    }

    #[Test]
    public function aMarkedLineIsNotReported(): void
    {
        self::assertSame(0, self::clonesIn([self::IGNORE . '/line']));
    }

    #[Test]
    public function aMarkedDeclarationIsNotReported(): void
    {
        self::assertSame(0, self::clonesIn([self::IGNORE . '/declaration']));
    }

    /** @param list<string> $roots */
    private static function clonesIn(array $roots): int
    {
        $files = (new FileFinder())->find($roots, ['.php'], []);

        return count((new Engine(new StrategyConfiguration(5, 60, Normalization::Raw, 0.7)))->detect($files));
    }
}
