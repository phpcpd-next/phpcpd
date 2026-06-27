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

use LucianoPereira\PhpcpdNext\Arguments;
use LucianoPereira\PhpcpdNext\Cache\CloneCache;
use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CloneCache::class)]
#[CoversClass(CodeCloneMap::class)]
final class CacheTest extends TestCase
{
    private const string ALPHA  = __DIR__ . '/fixtures/with_clones/Alpha.php';
    private const string BETA   = __DIR__ . '/fixtures/with_clones/Beta.php';
    private const string UNIQUE = __DIR__ . '/fixtures/no_clones/Unique.php';

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpcpd-cache-test-' . md5(uniqid('', true));
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->tmpDir);
    }

    #[Test]
    public function get_returns_null_when_cache_directory_does_not_exist(): void
    {
        $cache = new CloneCache('/nonexistent-' . md5(uniqid('', true)), 'fp');

        self::assertNull($cache->get([self::ALPHA]));
    }

    #[Test]
    public function get_returns_null_before_any_put(): void
    {
        $cache = new CloneCache($this->tmpDir, 'fp');

        self::assertNull($cache->get([self::ALPHA, self::BETA]));
    }

    #[Test]
    public function round_trip_preserves_clone_count_and_total_lines(): void
    {
        $map = $this->detect([self::ALPHA, self::BETA]);

        $cache = new CloneCache($this->tmpDir, 'fp');
        $cache->put([self::ALPHA, self::BETA], $map);

        $loaded = $cache->get([self::ALPHA, self::BETA]);

        self::assertNotNull($loaded);
        self::assertSame($map->count(), $loaded->count());
        self::assertSame($map->numberOfLines(), $loaded->numberOfLines());
    }

    #[Test]
    public function round_trip_preserves_duplicated_lines(): void
    {
        $map = $this->detect([self::ALPHA, self::BETA]);

        $cache = new CloneCache($this->tmpDir, 'fp');
        $cache->put([self::ALPHA, self::BETA], $map);

        $loaded = $cache->get([self::ALPHA, self::BETA]);

        self::assertNotNull($loaded);
        self::assertSame($map->numberOfDuplicatedLines(), $loaded->numberOfDuplicatedLines());
    }

    #[Test]
    public function round_trip_preserves_gapped_flag(): void
    {
        $fileA = new CodeCloneFile(self::ALPHA, 5);
        $fileB = new CodeCloneFile(self::BETA, 5);
        $clone = new CodeClone($fileA, $fileB, numberOfLines: 10, numberOfTokens: 80, gapped: true);

        $map = new CodeCloneMap();
        $map->add($clone);

        $cache = new CloneCache($this->tmpDir, 'fp');
        $cache->put([self::ALPHA, self::BETA], $map);

        $loaded = $cache->get([self::ALPHA, self::BETA]);

        self::assertNotNull($loaded);
        self::assertSame(1, $loaded->numberOfGappedClones());
    }

    #[Test]
    public function get_returns_null_when_file_is_removed_from_set(): void
    {
        $cache = new CloneCache($this->tmpDir, 'fp');
        $cache->put([self::ALPHA, self::BETA], new CodeCloneMap());

        self::assertNull($cache->get([self::ALPHA]));
    }

    #[Test]
    public function get_returns_null_when_file_is_added_to_set(): void
    {
        $cache = new CloneCache($this->tmpDir, 'fp');
        $cache->put([self::ALPHA], new CodeCloneMap());

        self::assertNull($cache->get([self::ALPHA, self::UNIQUE]));
    }

    #[Test]
    public function get_returns_null_when_file_content_changes(): void
    {
        mkdir($this->tmpDir, 0777, recursive: true);
        $tmpFile = $this->tmpDir . '/testfile.php';
        file_put_contents($tmpFile, '<?php echo 1;');

        $cache = new CloneCache($this->tmpDir . '/cache', 'fp');
        $cache->put([$tmpFile], new CodeCloneMap());

        file_put_contents($tmpFile, '<?php echo 2;');

        self::assertNull($cache->get([$tmpFile]));
    }

    #[Test]
    public function config_fingerprint_is_stable_across_calls(): void
    {
        $args = $this->makeArgs();

        self::assertSame(
            CloneCache::configFingerprint($args),
            CloneCache::configFingerprint($args),
        );
    }

    #[Test]
    public function config_fingerprint_differs_for_different_algorithms(): void
    {
        self::assertNotSame(
            CloneCache::configFingerprint($this->makeArgs(algorithm: 'rabin-karp')),
            CloneCache::configFingerprint($this->makeArgs(algorithm: 'suffixtree')),
        );
    }

    #[Test]
    public function config_fingerprint_differs_for_different_token_thresholds(): void
    {
        self::assertNotSame(
            CloneCache::configFingerprint($this->makeArgs(minTokens: 70)),
            CloneCache::configFingerprint($this->makeArgs(minTokens: 100)),
        );
    }

    #[Test]
    public function different_fingerprints_coexist_in_same_directory(): void
    {
        $mapA = $this->detect([self::ALPHA, self::BETA]);
        $mapB = new CodeCloneMap();

        $cacheA = new CloneCache($this->tmpDir, 'fingerprint-a');
        $cacheB = new CloneCache($this->tmpDir, 'fingerprint-b');

        $cacheA->put([self::ALPHA, self::BETA], $mapA);
        $cacheB->put([self::ALPHA, self::BETA], $mapB);

        $loadedA = $cacheA->get([self::ALPHA, self::BETA]);
        $loadedB = $cacheB->get([self::ALPHA, self::BETA]);

        self::assertNotNull($loadedA);
        self::assertNotNull($loadedB);
        self::assertSame($mapA->count(), $loadedA->count());
        self::assertSame(0, $loadedB->count());
    }

    /** @param list<string> $files */
    private function detect(array $files, int $minTokens = 70): CodeCloneMap
    {
        $config = new StrategyConfiguration($this->makeArgs(minTokens: $minTokens));

        return (new Detector(new DefaultStrategy($config)))->copyPasteDetection($files);
    }

    private function makeArgs(string $algorithm = 'rabin-karp', int $minTokens = 70): Arguments
    {
        return new Arguments(
            directories:      [],
            suffixes:         ['.php'],
            exclude:          [],
            pmdCpdXmlLogfile: null,
            linesThreshold:   5,
            tokensThreshold:  $minTokens,
            fuzzy:            false,
            verbose:          false,
            help:             false,
            version:          false,
            algorithm:        $algorithm,
            editDistance:     5,
            headEquality:     10,
        );
    }

    private function rmrf(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . '/' . $entry;

            if (is_dir($full)) {
                $this->rmrf($full);
            } else {
                unlink($full);
            }
        }

        rmdir($path);
    }
}
