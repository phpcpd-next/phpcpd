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

use function array_map;
use function basename;

use LucianoPereira\PhpcpdNext\Util\FileFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Which files a scan admits, at the one place the suffix filter is deliberately
 * bypassed.
 *
 * An extensionless file is scanned when a `#!` line names php, so that a
 * project's console entry point — `artisan`, `bin/console` — is not the one file
 * nobody checks. A built PHAR opens with the very same line, and the two must be
 * told apart by something other than the shebang.
 *
 * The consequence of getting it wrong is not a little noise. The duplication
 * figure is a ratio, and a committed archive contributes tens of thousands of
 * lines to its denominator while contributing nothing anybody can act on: with
 * five of them under `tools/`, PHPUnit reported 1.67% duplication where the
 * truth over its own sources is 4.64%. A wrong answer of that shape is worse
 * than a loud failure, because it looks like good news.
 */
#[CoversClass(FileFinder::class)]
final class FileFinderTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/fixtures/finder';

    /** @return list<string> */
    private function scan(): array
    {
        return array_map(
            static fn(string $path): string => basename($path),
            (new FileFinder())->find([self::FIXTURES], ['php'], []),
        );
    }

    #[Test]
    public function itScansAnExtensionlessConsoleEntryPoint(): void
    {
        self::assertContains(
            'console',
            $this->scan(),
            'A `#!` line naming php is why the suffix filter is bypassed at all; '
            . 'dropping the entry point would defeat the rule it exists for.',
        );
    }

    #[Test]
    public function itDoesNotScanABuiltPharThatOpensWithTheSameShebang(): void
    {
        self::assertNotContains(
            'demo-tool',
            $this->scan(),
            'A PHAR is `#!/usr/bin/env php` followed by an archive. Admitting it '
            . 'inflates the denominator of every percentage the run reports.',
        );
    }

    #[Test]
    public function itStillScansOrdinarySources(): void
    {
        self::assertContains('Ordinary.php', $this->scan());
    }
}
