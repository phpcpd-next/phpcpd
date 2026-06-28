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

namespace LucianoPereira\PhpcpdNext\PHPUnit;

use LucianoPereira\PhpcpdNext\Phpcpd;
use PHPUnit\Framework\Assert;

/**
 * Drop this trait into a TestCase to turn copy/paste detection into a regression
 * test: a clone introduced after the suite is green makes the build red, with the
 * offending locations printed in the failure message.
 *
 *   final class DuplicationTest extends TestCase
 *   {
 *       use AssertNoDuplication;
 *
 *       public function test_app_is_dry(): void
 *       {
 *           $this->assertNoDuplication(__DIR__ . '/../app', minTokens: 70);
 *       }
 *   }
 *
 * It runs detection in-process through {@see Phpcpd::detect()} — no shelling out
 * to the binary, no temp files.
 */
trait AssertNoDuplication
{
    /**
     * @param string|list<non-empty-string> $paths one or more directories to scan
     * @param ?string                  $algorithm null = default (Rabin-Karp + TokenBag)
     * @param list<non-empty-string>   $exclude  substring/glob patterns to skip
     * @param list<non-empty-string>   $suffixes file suffixes to include
     * @param ?string              $preset   a built-in preset name (e.g. 'laravel')
     */
    final protected function assertNoDuplication(
        string|array $paths = [],
        int $minTokens = 70,
        int $minLines = 5,
        ?string $algorithm = null,
        array $exclude = [],
        array $suffixes = ['.php'],
        ?string $preset = null,
        string $message = '',
    ): void {
        $clones = Phpcpd::detect(
            paths: $paths,
            minLines: $minLines,
            minTokens: $minTokens,
            algorithm: $algorithm,
            exclude: $exclude,
            suffixes: $suffixes,
            preset: $preset,
        );

        Assert::assertThat($clones, new DuplicationConstraint(), $message);
    }
}
