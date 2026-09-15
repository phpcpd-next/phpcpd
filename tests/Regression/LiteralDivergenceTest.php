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
use function is_dir;
use function mkdir;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Engine;
use LucianoPereira\PhpcpdNext\Presentation\LiteralDivergence;
use LucianoPereira\PhpcpdNext\Presentation\Presenter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The unified engine matches under a normalized view where a string folds to
 * `STR` and a number to `NUM`. That is deliberate; the report saying nothing
 * about it was not.
 *
 * These two files differ only in an error message, a threshold of 100 against
 * 5000, and a multiplier of 2 against 7 — three values, every one of them
 * behaviour — and were reported as a clone that is not inconsistent, carrying no
 * divergences at all, while `CodeClone::isGapped()` promises exactly this
 * information: "one copy patched, the sibling not".
 */
#[CoversClass(LiteralDivergence::class)]
final class LiteralDivergenceTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/phpcpd-literals-' . uniqid();

        mkdir($this->directory);

        file_put_contents($this->directory . '/A.php', self::source('A', "'amount must not be negative'", 100, 2));
        file_put_contents($this->directory . '/B.php', self::source('B', "'balance may not be below zero'", 5000, 7));
    }

    protected function tearDown(): void
    {
        foreach (['A.php', 'B.php'] as $name) {
            @unlink($this->directory . '/' . $name);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    #[Test]
    public function copiesDifferingOnlyInLiteralsAreStillReportedAsMatching(): void
    {
        $findings = $this->present();

        self::assertGreaterThan(0, $findings->count(), 'the normalized view is what makes these two a clone at all');
    }

    #[Test]
    public function theReportSaysHowManyLiteralsDiffer(): void
    {
        $best = 0;

        foreach ($this->present()->findings as $finding) {
            $best = $finding->literalDivergences > $best ? $finding->literalDivergences : $best;
        }

        // The message, the threshold and the multiplier.
        self::assertSame(3, $best);
    }

    #[Test]
    public function aGappedCloneIsNotMeasuredAtAll(): void
    {
        // Position-by-position comparison is sound only when nothing has been
        // skipped in either copy. A gapped clone's ranges are not aligned that
        // way, so the count would be an artefact of the comparison — and it is
        // not needed there, because such a clone reports `inconsistent` and
        // carries its divergences in full.
        $config = new StrategyConfiguration(
            minLines: 5,
            minTokens: 38,
            normalization: Normalization::Raw,
            minSimilarity: 0.7,
        );

        // The project's own Type-3 fixture, the one the `gapped` golden is cut
        // from, so this is checked against a clone that really does have gaps.
        $findings = (new Presenter())->present(
            (new Engine($config, 'unified'))->detect([
                __DIR__ . '/../fixtures/type3/clone_base.php',
                __DIR__ . '/../fixtures/type3/clone_gapped.php',
            ]),
        );

        $gapped = 0;

        foreach ($findings->findings as $finding) {
            if (!$finding->clone->isGapped()) {
                continue;
            }

            $gapped++;

            self::assertSame(0, $finding->literalDivergences);
        }

        self::assertGreaterThan(0, $gapped, 'the Type-3 fixture has to produce a gapped clone');
    }

    #[Test]
    public function itIsSaidSeparatelyFromAStructuralGap(): void
    {
        foreach ($this->present()->findings as $finding) {
            if ($finding->literalDivergences === 0) {
                continue;
            }

            self::assertFalse(
                $finding->clone->isGapped(),
                'these copies have no structural gap — which is the whole reason the count is needed',
            );

            return;
        }

        self::fail('no finding carried a literal divergence');
    }

    private function present(): \LucianoPereira\PhpcpdNext\Presentation\Findings
    {
        $config = new StrategyConfiguration(
            minLines: 5,
            minTokens: 40,
            normalization: Normalization::Raw,
            minSimilarity: 0.7,
        );

        return (new Presenter())->present(
            (new Engine($config, 'unified'))->detect([
                $this->directory . '/A.php',
                $this->directory . '/B.php',
            ]),
        );
    }

    private static function source(string $class, string $message, int $threshold, int $factor): string
    {
        return sprintf(
            <<<'PHP'
                <?php
                final class %s
                {
                    public function check(array $rows): array
                    {
                        $out = [];
                        foreach ($rows as $row) {
                            if ($row['amount'] < 0) {
                                throw new \InvalidArgumentException(%s);
                            }
                            if ($row['amount'] > %d) {
                                $out[] = $row['amount'] * %d;
                            }
                        }
                        return $out;
                    }
                }
                PHP,
            $class,
            $message,
            $threshold,
            $factor,
        );
    }
}
