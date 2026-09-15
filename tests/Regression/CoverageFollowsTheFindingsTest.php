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

use function file_put_contents;
use function getmypid;
use function sys_get_temp_dir;
use function unlink;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The duplicated-line total describes the findings that are left.
 *
 * Coverage is charged as clones arrive, which is right while a map is being
 * built and wrong the moment one is taken away. `settle()` takes several away,
 * and used to leave every line they had been charged for on the books:
 * measured at the shipped threshold it dropped 4 of php-parser's 41 findings,
 * 14 of symfony/string's 55 and 9 of symfony-console's 97, and the totals did
 * not move by a line. The percentage the tool prints is built on that number.
 */
#[CoversClass(CodeCloneMap::class)]
final class CoverageFollowsTheFindingsTest extends TestCase
{
    /** Forty lines of distinct code, so a range can be charged against real source. */
    private static function source(): string
    {
        $source = "<?php\n";

        for ($i = 2; $i <= 40; ++$i) {
            $source .= "\$v{$i} = strlen(\"x{$i}\") + {$i};\n";
        }

        return $source;
    }

    /**
     * Two readings of one region, the shorter starting earlier.
     *
     * `settle()` keeps the longer and drops the shorter, and the five lines the
     * shorter one held alone were charged to nobody else. Built by hand rather
     * than fished out of a corpus, because the case has to be the one this
     * tests and not whatever a fixture happens to produce.
     */
    #[Test]
    public function settlingGivesBackTheLinesItsDroppedFindingsHeld(): void
    {
        $dir = sys_get_temp_dir() . '/phpcpd-coverage-' . getmypid();
        @mkdir($dir, 0o777, true);

        $a = $dir . '/a.php';
        $b = $dir . '/b.php';

        try {
            file_put_contents($a, self::source());
            file_put_contents($b, self::source());

            $map = new CodeCloneMap();
            $map->addToNumberOfLines(80);

            $map->add(new CodeClone(
                new CodeCloneFile($a, 5, 10, 30, 0),
                new CodeCloneFile($b, 5, 10, 30, 0),
                10,
                30,
            ));
            $map->add(new CodeClone(
                new CodeCloneFile($a, 10, 21, 60, 0),
                new CodeCloneFile($b, 10, 21, 60, 0),
                21,
                60,
            ));

            self::assertCount(2, $map->clones());
            $charged = $map->numberOfDuplicatedLines();

            $map->settle();

            self::assertCount(1, $map->clones(), 'the fixture no longer gives settle() anything to drop');

            self::assertLessThan(
                $charged,
                $map->numberOfDuplicatedLines(),
                'the total still counts lines charged by a finding that was dropped',
            );

            // What the map would say had the dropped finding never been added.
            $rebuilt = new CodeCloneMap();
            $rebuilt->addToNumberOfLines($map->numberOfLines());

            foreach ($map->clones() as $clone) {
                $rebuilt->add($clone);
            }

            self::assertSame($rebuilt->numberOfDuplicatedLines(), $map->numberOfDuplicatedLines());
            self::assertSame($rebuilt->numberOfFilesWithClones(), $map->numberOfFilesWithClones());
        } finally {
            @unlink($a);
            @unlink($b);
            @rmdir($dir);
        }
    }
}
