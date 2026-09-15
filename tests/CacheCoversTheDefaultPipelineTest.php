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

use function file_put_contents;
use function glob;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use LucianoPereira\PhpcpdNext\Cache\CloneCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `--cache` covers the default pipeline.
 *
 * The cache block sat below an early return taken whenever no `--algorithm` was
 * given — which is the default invocation — so the documented CI feature wrote
 * nothing, hit nothing, and said nothing about it for everyone who did not name
 * an engine.
 */
#[CoversClass(CloneCache::class)]
final class CacheCoversTheDefaultPipelineTest extends TestCase
{
    use RunsTheBinary;

    /** @var non-empty-string set per test in setUp() */
    private string $project = '/';

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/phpcpd-cache-' . uniqid();

        mkdir($this->project . '/src', 0o777, true);

        $body = '';

        for ($index = 0; $index < 40; $index++) {
            $body .= sprintf("        \$v%d = strlen('abcdef') * %d;\n", $index, $index);
        }

        foreach (['A', 'B'] as $name) {
            file_put_contents(
                $this->project . '/src/' . $name . '.php',
                sprintf("<?php\n\nnamespace Demo;\n\nclass %s\n{\n    public function run(): int\n    {\n%s\n        return 1;\n    }\n}\n\nnew %s();\n", $name, $body, $name),
            );
        }
    }

    protected function tearDown(): void
    {
        self::remove($this->project);
    }

    #[Test]
    public function aSecondDefaultRunIsServedFromTheCache(): void
    {
        $dir = $this->project . '/cache';

        $arguments = ['--no-config', '--no-triage', '--cache', '--cache-dir=' . $dir, 'src'];

        [$first, , ] = $this->invoke($arguments, [], $this->project);
        self::assertStringNotContainsString('cache hit', $first, 'nothing to hit on the first run');
        self::assertNotSame([], glob($dir . '/*.json'), 'the first run writes the cache');

        [$second, , ] = $this->invoke($arguments, [], $this->project);
        self::assertStringContainsString('cache hit', $second, 'the second run is served from it');
    }

    private static function remove(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach ((array) scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            is_dir($path) ? self::remove($path) : unlink($path);
        }

        rmdir($directory);
    }
}
