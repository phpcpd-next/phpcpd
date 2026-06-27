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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use LucianoPereira\PhpcpdNext\Arguments;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenNormalizer;

/**
 * E2 — the type lever (BCB-PHP evaluation).
 *
 * Fixture pair: compute(int $value, int $factor): int   vs
 *               compute(string $value, string $factor): string
 * Structurally identical bodies; the ONLY token-level difference is the three
 * PHP type keywords in the signature (int vs string, ×2 params + return type).
 *
 * Token budget (min-tokens = 50):
 *   - Full file (both): 57 significant tokens  → exceeds threshold
 *   - Body alone (after {): 38 tokens          → below threshold
 *
 * Under --fuzzy (name-blind): T_STRING 'int'/'string' → 'ID' → streams are
 * identical → Rabin-Karp reports a 57-token clone. This is a FALSE POSITIVE:
 * the two functions have incompatible type signatures.
 *
 * Under --type-anchored: PHP built-in types (int, string, …) are preserved as
 * concrete tokens. The streams now differ at every type keyword. The longest
 * contiguous identical run is the body (38 tokens < 50) → NO clone reported.
 * Precision is recovered without sacrificing recall on same-type pairs.
 */
#[CoversClass(DefaultStrategy::class)]
#[CoversClass(TokenNormalizer::class)]
#[CoversClass(Detector::class)]
final class E2TypeLeverTest extends TestCase
{
    private const string INT_FILE    = __DIR__ . '/fixtures/e2/type_int.php';
    private const string STRING_FILE = __DIR__ . '/fixtures/e2/type_string.php';
    private const string RENAMED_FILE = __DIR__ . '/fixtures/e2/type_int_renamed.php';

    #[Test]
    public function fuzzy_reports_same_shape_different_type_as_clone_false_positive(): void
    {
        $clones = $this->detect(fuzzy: true, typeAnchored: false);

        self::assertFalse(
            $clones->isEmpty(),
            '--fuzzy should detect a (false-positive) clone between int and string variants '
            . 'because type keywords collapse to the same ID placeholder',
        );
    }

    #[Test]
    public function type_anchored_rejects_same_shape_different_type(): void
    {
        $clones = $this->detect(fuzzy: false, typeAnchored: true);

        self::assertTrue(
            $clones->isEmpty(),
            '--type-anchored must NOT report int vs string variant as a clone; '
            . 'the body alone (38 tokens) falls below min-tokens 50',
        );
    }

    #[Test]
    public function type_anchored_preserves_recall_for_same_type_renamed_pair(): void
    {
        // type_int.php vs type_int_renamed.php: identical type annotations, different var names.
        // type-anchored should still detect this as a clone (recall not sacrificed).
        $config = new StrategyConfiguration(new Arguments(
            directories: [],
            suffixes: ['.php'],
            exclude: [],
            pmdCpdXmlLogfile: null,
            linesThreshold: 1,
            tokensThreshold: 25,
            fuzzy: false,
            typeAnchored: true,
            verbose: false,
            help: false,
            version: false,
            algorithm: null,
            editDistance: 0,
            headEquality: 10,
        ));

        $clones = (new Detector(new DefaultStrategy($config)))
            ->copyPasteDetection([self::INT_FILE, self::RENAMED_FILE]);

        self::assertFalse(
            $clones->isEmpty(),
            '--type-anchored must still detect a clone between int-typed pairs that differ '
            . 'only in variable names (recall preserved for same-type pairs)',
        );
    }

    private function detect(bool $fuzzy, bool $typeAnchored): CodeCloneMap
    {
        $config = new StrategyConfiguration(new Arguments(
            directories: [],
            suffixes: ['.php'],
            exclude: [],
            pmdCpdXmlLogfile: null,
            linesThreshold: 1,
            tokensThreshold: 25,
            fuzzy: $fuzzy,
            typeAnchored: $typeAnchored,
            verbose: false,
            help: false,
            version: false,
            algorithm: null,
            editDistance: 0,
            headEquality: 10,
        ));

        return (new Detector(new DefaultStrategy($config)))
            ->copyPasteDetection([self::INT_FILE, self::STRING_FILE]);
    }
}
