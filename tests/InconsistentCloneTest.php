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

use function ob_get_clean;
use function ob_start;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use LucianoPereira\PhpcpdNext\Arguments;
use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTreeStrategy;
use LucianoPereira\PhpcpdNext\Log\Text;

/**
 * R4 — inconsistency reporting.
 *
 * A gapped (Type-3) clone is where two copies share a skeleton but diverge — the
 * literature ("Do Code Clones Matter?") identifies these inconsistent clones as
 * where duplication breeds bugs. Engine B can already tell them apart from exact
 * copies because it matches on raw token content; CodeClone::isGapped() now exposes
 * that signal.
 */
#[CoversClass(CodeClone::class)]
#[CoversClass(CodeCloneMap::class)]
#[CoversClass(SuffixTreeStrategy::class)]
#[CoversClass(Text::class)]
final class InconsistentCloneTest extends TestCase
{
    private const string BASE       = __DIR__ . '/fixtures/type3/clone_base.php';
    private const string EXACT_COPY = __DIR__ . '/fixtures/type3/clone_exact_copy.php';
    private const string GAPPED     = __DIR__ . '/fixtures/type3/clone_gapped.php';

    #[Test]
    public function value_object_defaults_to_not_gapped(): void
    {
        // Rabin-Karp constructs CodeClone without the flag → exact by default.
        $clone = new CodeClone(
            new CodeCloneFile(self::BASE, 1),
            new CodeCloneFile(self::EXACT_COPY, 1),
            10,
            50,
        );

        self::assertFalse($clone->isGapped());
    }

    #[Test]
    public function exact_copy_is_not_flagged_gapped(): void
    {
        $clones = $this->suffixTreeClones(self::EXACT_COPY, editDistance: 3);

        self::assertFalse($clones->isEmpty(), 'sanity: the exact copy is detected');

        foreach ($clones->clones() as $clone) {
            self::assertFalse(
                $clone->isGapped(),
                'A byte-identical copy must not be flagged as an inconsistent (gapped) clone',
            );
        }
    }

    #[Test]
    public function gapped_clone_is_flagged_gapped(): void
    {
        $clones = $this->suffixTreeClones(self::GAPPED, editDistance: 3);

        self::assertFalse($clones->isEmpty(), 'sanity: the gapped clone is detected');

        $gappedFound = false;

        foreach ($clones->clones() as $clone) {
            if ($clone->isGapped()) {
                $gappedFound = true;
            }
        }

        self::assertTrue(
            $gappedFound,
            'The clone bridged across an inserted statement must be flagged as gapped',
        );
    }

    /**
     * Regression: a whole-file exact clone used to report 0 lines because the line
     * span overshot into the second file, whose line numbers restart. The span is
     * now measured within the first occurrence's own file.
     */
    #[Test]
    public function whole_file_exact_clone_reports_a_real_line_span(): void
    {
        $clones = $this->suffixTreeClones(self::EXACT_COPY, editDistance: 3);

        self::assertFalse($clones->isEmpty(), 'sanity: the exact copy is detected');

        foreach ($clones->clones() as $clone) {
            self::assertGreaterThan(
                1,
                $clone->numberOfLines(),
                'A whole-file clone must span multiple lines, not 0',
            );
        }
    }

    #[Test]
    public function map_counts_gapped_clones(): void
    {
        self::assertSame(0, $this->suffixTreeClones(self::EXACT_COPY, editDistance: 3)->numberOfGappedClones());
        self::assertSame(1, $this->suffixTreeClones(self::GAPPED, editDistance: 3)->numberOfGappedClones());
    }

    #[Test]
    public function text_output_marks_a_gapped_clone_as_inconsistent(): void
    {
        $output = $this->renderText($this->suffixTreeClones(self::GAPPED, editDistance: 3));

        self::assertStringContainsString('[inconsistent]', $output);
        self::assertStringContainsString('(1 inconsistent)', $output);
    }

    #[Test]
    public function text_output_does_not_mark_an_exact_clone(): void
    {
        $output = $this->renderText($this->suffixTreeClones(self::EXACT_COPY, editDistance: 3));

        self::assertStringNotContainsString('inconsistent', $output);
    }

    private function renderText(CodeCloneMap $clones): string
    {
        ob_start();
        (new Text())->printResult($clones, false);

        return (string) ob_get_clean();
    }

    private function suffixTreeClones(string $variant, int $editDistance): CodeCloneMap
    {
        $config = new StrategyConfiguration(new Arguments(
            directories: [],
            suffixes: ['.php'],
            exclude: [],
            pmdCpdXmlLogfile: null,
            linesThreshold: 1,
            tokensThreshold: 60,
            fuzzy: false,
            verbose: false,
            help: false,
            version: false,
            algorithm: null,
            editDistance: $editDistance,
            headEquality: 10,
        ));

        return (new Detector(new SuffixTreeStrategy($config)))
            ->copyPasteDetection([self::BASE, $variant]);
    }
}
