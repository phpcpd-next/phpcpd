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

use function json_decode;
use function json_encode;
use function str_repeat;

use LucianoPereira\PhpcpdNext\Application;
use LucianoPereira\PhpcpdNext\Tests\BuildsAFixtureProject;
use LucianoPereira\PhpcpdNext\Tests\RunsTheBinary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A console entry point reaches the scan from two directions — `FileFinder`
 * admits its `#!` line, the composer manifest names it under `bin` — and the
 * two spell it differently, so deduplicating on the string admitted both and
 * the detector matched the file against itself.
 */
#[CoversClass(Application::class)]
final class EntryPointDeduplicationTest extends TestCase
{
    use BuildsAFixtureProject;
    use RunsTheBinary;

    protected function setUp(): void
    {
        $this->makeFixtureRoot('entrypoint');

        $this->write('composer.json', (string) json_encode([
            'name'     => 'fixture/entry-point',
            'bin'      => ['console'],
            'autoload' => ['psr-4' => ['Fixture\\' => 'src/']],
        ]));

        // Long enough to clear --min-tokens if the file is scanned twice.
        $this->write(
            'console',
            "#!/usr/bin/env php\n<?php\n\n" . str_repeat("\$total = strlen('abcdef') + 1;\n", 60),
        );

        $this->write('src/Unrelated.php', "<?php\n\nnamespace Fixture;\n\nclass Unrelated {}\n");
    }

    protected function tearDown(): void
    {
        $this->delete($this->root);
    }

    #[Test]
    public function aConsoleEntryPointIsScannedOnceHoweverTheScanRootIsSpelled(): void
    {
        $report = $this->root . '/report.json';

        // `.` is what makes the spellings differ. Given an absolute scan root
        // both agree and there is nothing to catch.
        [$stdout, $stderr, ] = $this->invoke(
            ['--no-config', '--no-triage', '--log-json=' . $report, '.'],
            [],
            $this->root,
        );

        $decoded = json_decode((string) file_get_contents($report), true);

        self::assertIsArray($decoded);
        self::assertArrayHasKey('clones', $decoded);
        self::assertIsArray($decoded['clones']);

        foreach ($decoded['clones'] as $clone) {
            self::assertIsArray($clone);
            self::assertIsArray($clone['files']);
            self::assertNotSame(
                $clone['files'][0],
                $clone['files'][1],
                'A clone whose two sites are the same file at the same line is a file '
                . 'matched against itself, never a finding: ' . $stdout . $stderr,
            );
        }
    }
}
