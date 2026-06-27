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

use function file_put_contents;
use function glob;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use LucianoPereira\PhpcpdNext\Arguments;
use LucianoPereira\PhpcpdNext\Cache\CloneCache;
use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CloneCache::class)]
final class CloneCacheTest extends TestCase
{
    private string $cacheDir;
    private string $fileA;
    private string $fileB;
    private string $fileC;

    protected function setUp(): void
    {
        $base           = sys_get_temp_dir() . '/phpcpd-cache-' . uniqid();
        $this->cacheDir = $base . '/cache';
        $this->fileA    = $this->tempFile($base, 'a.php', "<?php\n\$alpha = 1;\n\$bravo = 2;\n");
        $this->fileB    = $this->tempFile($base, 'b.php', "<?php\n\$alpha = 1;\n\$bravo = 2;\n");
        $this->fileC    = $this->tempFile($base, 'c.php', "<?php\n\$alpha = 1;\n\$bravo = 2;\n");
    }

    protected function tearDown(): void
    {
        foreach ([$this->cacheDir, $this->fileA, $this->fileB, $this->fileC] as $path) {
            $this->remove($path);
        }
    }

    #[Test]
    public function a_cold_cache_is_a_miss(): void
    {
        self::assertNull($this->cache()->get([$this->fileA]));
    }

    #[Test]
    public function put_then_get_round_trips_the_result(): void
    {
        $files = [$this->fileA, $this->fileB];
        $this->cache()->put($files, $this->mapWith(gapped: true));

        $restored = $this->cache()->get($files);

        self::assertNotNull($restored);
        self::assertSame(1, $restored->count());

        foreach ($restored->clones() as $clone) {
            self::assertTrue($clone->isGapped(), 'the gapped flag must survive the round-trip');
        }
    }

    #[Test]
    public function a_changed_file_is_a_miss(): void
    {
        $files = [$this->fileA, $this->fileB];
        $this->cache()->put($files, $this->mapWith());

        self::assertNotNull($this->cache()->get($files), 'unchanged files: hit');

        file_put_contents($this->fileB, "<?php\n// edited\n");

        self::assertNull($this->cache()->get($files), 'a changed file must invalidate the cache');
    }

    #[Test]
    public function an_added_or_removed_file_is_a_miss(): void
    {
        $this->cache()->put([$this->fileA, $this->fileB], $this->mapWith());

        self::assertNull($this->cache()->get([$this->fileA]), 'fewer files: miss');
        self::assertNull($this->cache()->get([$this->fileA, $this->fileB, $this->fileC]), 'more files: miss');
    }

    #[Test]
    public function different_config_fingerprints_do_not_collide(): void
    {
        $files = [$this->fileA, $this->fileB];
        $this->cache('rabin-fp')->put($files, $this->mapWith());

        self::assertNull(
            $this->cache('suffix-fp')->get($files),
            'a different config must not read another config\'s cache',
        );
    }

    #[Test]
    public function duplicated_lines_round_trip_for_a_three_occurrence_clone(): void
    {
        // Built like the strategies do: two 2-file clones with the same id merge into
        // one 3-file clone. The stored stat must come back exactly.
        $map = new CodeCloneMap();
        $map->add(new CodeClone(new CodeCloneFile($this->fileA, 1), new CodeCloneFile($this->fileB, 1), 10, 50));
        $map->add(new CodeClone(new CodeCloneFile($this->fileA, 1), new CodeCloneFile($this->fileC, 1), 10, 50));

        $files = [$this->fileA, $this->fileB, $this->fileC];
        $this->cache()->put($files, $map);

        $restored = $this->cache()->get($files);

        self::assertNotNull($restored);
        self::assertSame($map->numberOfDuplicatedLines(), $restored->numberOfDuplicatedLines());
    }

    #[Test]
    public function config_fingerprint_is_stable_and_algorithm_sensitive(): void
    {
        self::assertSame(
            CloneCache::configFingerprint($this->arguments('rabin-karp')),
            CloneCache::configFingerprint($this->arguments('rabin-karp')),
        );
        self::assertNotSame(
            CloneCache::configFingerprint($this->arguments('rabin-karp')),
            CloneCache::configFingerprint($this->arguments('suffixtree')),
        );
    }

    private function cache(string $fingerprint = 'default-fp'): CloneCache
    {
        return new CloneCache($this->cacheDir, $fingerprint);
    }

    private function mapWith(bool $gapped = false): CodeCloneMap
    {
        $map = new CodeCloneMap();
        $map->add(new CodeClone(
            new CodeCloneFile($this->fileA, 1),
            new CodeCloneFile($this->fileB, 1),
            2,
            10,
            $gapped,
        ));

        return $map;
    }

    private function arguments(string $algorithm): Arguments
    {
        return new Arguments(
            directories: ['src'],
            suffixes: ['.php'],
            exclude: [],
            pmdCpdXmlLogfile: null,
            linesThreshold: 5,
            tokensThreshold: 70,
            fuzzy: false,
            verbose: false,
            help: false,
            version: false,
            algorithm: $algorithm,
            editDistance: 5,
            headEquality: 10,
        );
    }

    private function tempFile(string $base, string $name, string $content): string
    {
        if (!is_dir($base)) {
            mkdir($base, 0777, true);
        }

        $path = $base . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }

    private function remove(string $path): void
    {
        if (is_dir($path)) {
            foreach (glob($path . '/*') ?: [] as $child) {
                $this->remove($child);
            }

            rmdir($path);

            return;
        }

        if (@is_file($path)) {
            unlink($path);
        }
    }
}
