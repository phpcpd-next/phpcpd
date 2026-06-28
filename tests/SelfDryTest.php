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

use LucianoPereira\PhpcpdNext\PHPUnit\AssertNoDuplication;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The detector must report its own src/ as clone-free. A tool that ships
 * duplication it would flag in someone else's code has no standing — so this is
 * the dogfood invariant, enforced across all three engines.
 *
 * It is also the project's own use of the shipped PHPUnit integration: the same
 * {@see AssertNoDuplication} trait an embedder would `use` is what guards this
 * repository. If the integration breaks, this test breaks first.
 *
 * The bar is min-tokens 40 (tighter than the default 70). It already caught real
 * duplication once: the Json and Sarif loggers shared a boilerplate block, factored
 * out into AbstractJsonLogger. If this test goes red, src/ grew a clone worth
 * extracting — fix the code, don't relax the threshold.
 */
final class SelfDryTest extends TestCase
{
    use AssertNoDuplication;

    private const int MIN_TOKENS = 40;
    private const int MIN_LINES  = 5;

    /** @return iterable<string, array{string}> */
    public static function engines(): iterable
    {
        yield 'rabin-karp' => ['rabin-karp'];
        yield 'suffixtree' => ['suffixtree'];
        yield 'tokenbag'   => ['tokenbag'];
    }

    #[Test]
    #[DataProvider('engines')]
    public function src_is_clone_free(string $algorithm): void
    {
        $this->assertNoDuplication(
            __DIR__ . '/../src',
            minTokens: self::MIN_TOKENS,
            minLines: self::MIN_LINES,
            algorithm: $algorithm,
        );
    }
}
