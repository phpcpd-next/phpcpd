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

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\CoherentClasses;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A clone class names copies, and a site it never compared is not one.
 *
 * Classes are assembled from candidate pairs, so a site can be seated beside
 * others it was never checked against. On php-parser that produced one exact
 * clone of `Standard.php:444-467`, `:479-500` and `:510-533` where no pair of
 * the three agreed — 0 of 166 tokens, 0 of 166, and 17 of 166 — while each had
 * a real duplicate elsewhere in the same file.
 */
#[CoversClass(CoherentClasses::class)]
final class AClassNamesOnlyVerifiedCopiesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/phpcpd-coherent-' . getmypid();
        @mkdir($this->dir, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (['a.php', 'b.php'] as $name) {
            @unlink($this->dir . '/' . $name);
        }

        @rmdir($this->dir);
    }

    private function write(string $name, string $body): string
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, "<?php\n" . $body);

        return $path;
    }

    private static function config(): StrategyConfiguration
    {
        return new StrategyConfiguration(1, 5, Normalization::TypeAnchored, 0.7);
    }

    /**
     * Two sites holding different tokens: an arithmetic pair and a control-flow
     * pair, which no normalization brings together.
     *
     * @return array{0: string, 1: string} the two paths
     */
    private function differingSites(): array
    {
        return [
            $this->write('a.php', "\$alpha = strlen('a') + 1;\n\$beta = strlen('b') + 2;\n"),
            $this->write('b.php', "while (true) { break; }\nforeach (\$x as \$y) { echo \$y; }\n"),
        ];
    }

    /**
     * A map holding one class over these two sites — the shape the verification
     * pass is asked about, and the only thing that varies between the cases
     * below is what the class claims about itself.
     */
    private static function classOver(string $a, string $b, bool $reordered = false): CodeCloneMap
    {
        $map = new CodeCloneMap();
        $map->addToNumberOfLines(10);
        $map->add(new CodeClone(
            new CodeCloneFile($a, 2, 2, 8, 0),
            new CodeCloneFile($b, 2, 2, 8, 0),
            2,
            8,
            gapped: $reordered,
            reordered: $reordered,
        ));

        return $map;
    }

    /** A class whose second site holds different tokens is not a smaller class. */
    #[Test]
    public function aSiteThatHoldsDifferentTokensIsNotACopy(): void
    {
        [$a, $b] = $this->differingSites();

        $map = self::classOver($a, $b);

        self::assertCount(1, $map->clones());
        self::assertCount(0, CoherentClasses::applyTo($map, self::config())->clones());
    }

    /** Two sites that really do hold the same tokens are left alone. */
    #[Test]
    public function aVerifiedCopySurvives(): void
    {
        $body = "\$alpha = strlen('a') + 1;\n\$beta = strlen('b') + 2;\n";

        $map = self::classOver($this->write('a.php', $body), $this->write('b.php', $body));

        $kept = CoherentClasses::applyTo($map, self::config())->clones();

        self::assertCount(1, $kept);
        self::assertCount(2, $kept[0]->files());
    }

    /**
     * A reordered class keeps every site: order is exactly what its engine
     * threw away, so token-identity is not a bar it was ever asked to clear.
     */
    #[Test]
    public function aReorderedClassIsNotHeldToTokenIdentity(): void
    {
        [$a, $b] = $this->differingSites();

        $map = self::classOver($a, $b, reordered: true);

        self::assertCount(1, CoherentClasses::applyTo($map, self::config())->clones());
    }
}
