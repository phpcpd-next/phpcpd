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

use function copy;
use function file_put_contents;
use function implode;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use LucianoPereira\PhpcpdNext\Arguments;
use LucianoPereira\PhpcpdNext\Cache\IncrementalIndex;
use LucianoPereira\PhpcpdNext\Cache\IndexCodec;
use LucianoPereira\PhpcpdNext\Cache\IndexResult;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\FileTokens;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IncrementalIndex::class)]
#[CoversClass(IndexCodec::class)]
#[CoversClass(IndexResult::class)]
#[CoversClass(FileTokens::class)]
final class IncrementalIndexTest extends TestCase
{
    private const string ALPHA  = __DIR__ . '/fixtures/with_clones/Alpha.php';
    private const string BETA   = __DIR__ . '/fixtures/with_clones/Beta.php';
    private const string UNIQUE = __DIR__ . '/fixtures/no_clones/Unique.php';

    private string $tmpDir;
    private string $cacheDir;
    private string $a;
    private string $b;
    private string $c;

    protected function setUp(): void
    {
        $this->tmpDir   = sys_get_temp_dir() . '/phpcpd-idx-' . uniqid('', true);
        $this->cacheDir = $this->tmpDir . '/cache';
        mkdir($this->tmpDir, 0777, recursive: true);

        // Working copies of real fixtures so the change/add/remove tests can mutate them.
        $this->a = $this->copyFixture(self::ALPHA, 'a.php');
        $this->b = $this->copyFixture(self::BETA, 'b.php');
        $this->c = $this->copyFixture(self::UNIQUE, 'c.php');
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->tmpDir);
    }

    #[Test]
    public function index_detection_equals_a_full_rabin_karp_pass(): void
    {
        $files = [$this->a, $this->b, $this->c];

        $expected = $this->fullPass($files);
        $actual   = $this->index()->detect($files)->clones;

        $this->assertSameClones($expected, $actual);
    }

    #[Test]
    public function a_cold_run_scans_every_file_and_reuses_none(): void
    {
        $result = $this->index()->detect([$this->a, $this->b, $this->c]);

        self::assertSame(0, $result->reused);
        self::assertSame(3, $result->scanned);
    }

    #[Test]
    public function a_warm_run_with_no_changes_reuses_every_file(): void
    {
        $files = [$this->a, $this->b, $this->c];

        $this->index()->detect($files);
        $warm = $this->index()->detect($files);

        self::assertSame(3, $warm->reused, 'an unchanged file must be served from the index');
        self::assertSame(0, $warm->scanned);
        $this->assertSameClones($this->fullPass($files), $warm->clones);
    }

    #[Test]
    public function only_the_changed_file_is_rescanned(): void
    {
        $files = [$this->a, $this->b, $this->c];
        $this->index()->detect($files);

        // Break b's clone with a so the answer genuinely changes too.
        file_put_contents($this->b, "<?php\n\$only = 1;\n");

        $warm = $this->index()->detect($files);

        self::assertSame(2, $warm->reused, 'a and c are untouched');
        self::assertSame(1, $warm->scanned, 'only the edited file is re-tokenized');
        $this->assertSameClones($this->fullPass($files), $warm->clones);
    }

    #[Test]
    public function an_added_file_is_scanned_while_the_rest_are_reused(): void
    {
        $this->index()->detect([$this->a, $this->b]);

        $d     = $this->copyFixture(self::ALPHA, 'd.php');
        $files = [$this->a, $this->b, $d];

        $warm = $this->index()->detect($files);

        self::assertSame(2, $warm->reused);
        self::assertSame(1, $warm->scanned, 'only the newly added file is tokenized');
        $this->assertSameClones($this->fullPass($files), $warm->clones);
    }

    #[Test]
    public function a_removed_file_drops_out_of_the_detection(): void
    {
        $this->index()->detect([$this->a, $this->b, $this->c]);

        $files = [$this->a, $this->c];
        $warm  = $this->index()->detect($files);

        self::assertSame(2, $warm->reused);
        self::assertSame(0, $warm->scanned);
        $this->assertSameClones($this->fullPass($files), $warm->clones);
    }

    #[Test]
    public function the_index_persists_across_instances(): void
    {
        $files = [$this->a, $this->b, $this->c];

        // Cold run with one instance, warm run with a fresh instance that can only
        // know the files are unchanged by loading the index from disk.
        $this->index()->detect($files);
        $warm = $this->index()->detect($files);

        self::assertSame(3, $warm->reused);
        $this->assertSameClones($this->fullPass($files), $warm->clones);
    }

    /** @param list<string> $files */
    private function fullPass(array $files): CodeCloneMap
    {
        return (new Detector(new DefaultStrategy($this->config())))->copyPasteDetection($files);
    }

    private function index(): IncrementalIndex
    {
        return new IncrementalIndex($this->cacheDir, 'idx-fp', $this->config());
    }

    private function config(): StrategyConfiguration
    {
        return new StrategyConfiguration(new Arguments(
            directories:      [],
            suffixes:         ['.php'],
            exclude:          [],
            pmdCpdXmlLogfile: null,
            linesThreshold:   5,
            tokensThreshold:  70,
            fuzzy:            false,
            verbose:          false,
            help:             false,
            version:          false,
            algorithm:        'rabin-karp',
            editDistance:     5,
            headEquality:     10,
        ));
    }

    private function assertSameClones(CodeCloneMap $expected, CodeCloneMap $actual): void
    {
        self::assertSame($expected->count(), $actual->count(), 'clone count');
        self::assertSame($expected->numberOfLines(), $actual->numberOfLines(), 'total lines');
        self::assertSame(
            $expected->numberOfDuplicatedLines(),
            $actual->numberOfDuplicatedLines(),
            'duplicated lines',
        );
        self::assertSame(
            $expected->numberOfGappedClones(),
            $actual->numberOfGappedClones(),
            'gapped clone count',
        );
        self::assertSame($this->fingerprint($expected), $this->fingerprint($actual), 'per-clone shape');
    }

    /** A stable description of every clone: its files (name + start line), lines and tokens. */
    private function fingerprint(CodeCloneMap $map): string
    {
        $lines = [];

        foreach ($map->clones() as $clone) {
            $files = [];

            foreach ($clone->files() as $file) {
                $files[] = $file->name() . ':' . $file->startLine();
            }

            $lines[] = implode('|', $files) . '#' . $clone->numberOfLines() . '/' . $clone->numberOfTokens();
        }

        return implode("\n", $lines);
    }

    private function copyFixture(string $source, string $name): string
    {
        $path = $this->tmpDir . '/' . $name;
        copy($source, $path);

        return $path;
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
