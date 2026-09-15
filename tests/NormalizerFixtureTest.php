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
use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenNormalizer;
use LucianoPereira\PhpcpdNext\Engine;
use LucianoPereira\PhpcpdNext\Util\FileFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `tests/fixtures/e2/` and `tests/fixtures/r2/` are the two normalizer
 * experiments, written as matched triples and never run.
 *
 * Each is a 2x2: one pair that must match and one that must not, so a mode that
 * matched everything and a mode that matched nothing would both fail.
 */
#[CoversClass(TokenNormalizer::class)]
final class NormalizerFixtureTest extends TestCase
{
    /**
     * E2: type-anchoring keeps the seventeen scalar type names concrete, so a
     * renamed copy still matches and a retyped one does not. Plain `--fuzzy`
     * folds both, which is the contrast the fixtures exist to draw.
     */
    #[Test]
    public function typeAnchoringSeparatesARenameFromARetype(): void
    {
        $renamed = [__DIR__ . '/fixtures/e2/type_int.php', __DIR__ . '/fixtures/e2/type_int_renamed.php'];
        $retyped = [__DIR__ . '/fixtures/e2/type_int.php', __DIR__ . '/fixtures/e2/type_string.php'];

        self::assertSame(1, self::clones($renamed, 40, Normalization::Fuzzy), 'fuzzy folds identifiers');
        self::assertSame(1, self::clones($retyped, 40, Normalization::Fuzzy), 'fuzzy folds the types too');

        self::assertSame(1, self::clones($renamed, 40, Normalization::TypeAnchored), 'a rename still matches');
        self::assertSame(0, self::clones($retyped, 40, Normalization::TypeAnchored), 'int and string are not one clone');
    }

    /**
     * R2: `--fuzzy` folds an identifier and must not fold a keyword.
     *
     * The threshold has to demand the whole body. Below it the engine finds the
     * longest exact run, which excludes only the one differing token — 76 of
     * the 141 here — and a structural change looks like a match for the same
     * reason a tail of it is one.
     */
    #[Test]
    public function fuzzyFoldsAnIdentifierAndNotAKeyword(): void
    {
        $cosmetic   = [__DIR__ . '/fixtures/r2/base.php', __DIR__ . '/fixtures/r2/identifier.php'];
        $structural = [__DIR__ . '/fixtures/r2/base.php', __DIR__ . '/fixtures/r2/keyword.php'];

        self::assertSame(1, self::clones($cosmetic, 100, Normalization::Fuzzy), '$scaled and $banner are one name');
        self::assertSame(0, self::clones($structural, 100, Normalization::Fuzzy), 'if and while are not');
    }

    /**
     * @param list<string> $files two fixture paths, copied into one directory
     *                            because the finder walks directories
     */
    private static function clones(array $files, int $minTokens, Normalization $view = Normalization::Raw): int
    {
        $dir = sys_get_temp_dir() . '/phpcpd-normalizer-' . uniqid();
        mkdir($dir, 0o777, true);

        foreach ($files as $at => $file) {
            copy($file, $dir . '/f' . $at . '.php');
        }

        try {
            $config = new StrategyConfiguration(5, $minTokens, $view, 0.7);
            $found  = (new Engine($config, 'rabin-karp'))->detect((new FileFinder())->find([$dir], ['.php'], []));

            return count($found);
        } finally {
            foreach ((array) scandir($dir) as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    unlink($dir . '/' . $entry);
                }
            }

            is_dir($dir) && rmdir($dir);
        }
    }
}
