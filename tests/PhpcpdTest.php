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

use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Engine;
use LucianoPereira\PhpcpdNext\InvalidStrategyException;
use LucianoPereira\PhpcpdNext\Phpcpd;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Phpcpd::class)]
#[CoversClass(Engine::class)]
final class PhpcpdTest extends TestCase
{
    #[Test]
    public function detect_returns_a_clone_map_for_clean_sources(): void
    {
        $map = Phpcpd::detect(__DIR__ . '/fixtures/no_clones', minTokens: 20, minLines: 2);

        self::assertInstanceOf(CodeCloneMap::class, $map);
        self::assertSame(0, $map->count());
    }

    #[Test]
    public function detect_finds_duplication_in_a_dirty_fixture(): void
    {
        $map = Phpcpd::detect(__DIR__ . '/fixtures/with_clones', minTokens: 20, minLines: 2);

        self::assertGreaterThan(0, $map->count());
    }

    #[Test]
    public function detect_accepts_a_single_path_string_or_a_list(): void
    {
        $fromString = Phpcpd::detect(__DIR__ . '/fixtures/with_clones', minTokens: 20, minLines: 2);
        $fromList   = Phpcpd::detect([__DIR__ . '/fixtures/with_clones'], minTokens: 20, minLines: 2);

        self::assertSame($fromString->count(), $fromList->count());
    }

    #[Test]
    public function detect_honours_a_named_algorithm(): void
    {
        $map = Phpcpd::detect(
            __DIR__ . '/fixtures/with_clones',
            minTokens: 20,
            minLines: 2,
            algorithm: 'rabin-karp',
        );

        self::assertInstanceOf(CodeCloneMap::class, $map);
    }

    #[Test]
    public function detect_rejects_an_unknown_algorithm(): void
    {
        $this->expectException(InvalidStrategyException::class);

        Phpcpd::detect(__DIR__ . '/fixtures/with_clones', algorithm: 'nope');
    }

    #[Test]
    public function detect_rejects_an_unknown_preset(): void
    {
        $this->expectException(InvalidStrategyException::class);
        $this->expectExceptionMessage('symfony');

        Phpcpd::detect(__DIR__ . '/fixtures/with_clones', preset: 'symfony');
    }

    #[Test]
    public function preset_excludes_are_applied_in_headless_mode(): void
    {
        // The laravel preset excludes 'vendor'; pointing it at a tree whose only
        // duplication lives under a vendor/ path must yield no clones.
        $root = sys_get_temp_dir() . '/phpcpd-preset-' . uniqid();
        mkdir($root . '/vendor/pkg', recursive: true);

        $dup = "<?php\nfunction a() { \$x = 1; \$y = 2; \$z = 3; return \$x + \$y + \$z; }\n"
             . "function b() { \$x = 1; \$y = 2; \$z = 3; return \$x + \$y + \$z; }\n";
        file_put_contents($root . '/vendor/pkg/A.php', $dup);

        try {
            $map = Phpcpd::detect($root, preset: 'laravel', minTokens: 5, minLines: 1);
            self::assertSame(0, $map->count(), 'vendor/ duplication must be excluded by the laravel preset');
        } finally {
            @unlink($root . '/vendor/pkg/A.php');
            @rmdir($root . '/vendor/pkg');
            @rmdir($root . '/vendor');
            @rmdir($root);
        }
    }
}
