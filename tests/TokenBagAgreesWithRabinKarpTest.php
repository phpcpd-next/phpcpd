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
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenBag\Block;
use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenBag\BlockExtractor;
use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenBagStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenNormalizer;
use LucianoPereira\PhpcpdNext\Util\CodeLines;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The two arms of the shipped default have to mean the same things by "token",
 * by "--min-tokens", and by the extent they report.
 *
 * They did not, and nothing here asked them to: the suite passed at 685 tests
 * while the token bag was blind to every operator in PHP, reported a corpus-
 * relative number as its token count, claimed a signature line it had never
 * compared, and carried no token anchor for any audit to read. Each check below
 * is one of those.
 */
#[CoversClass(TokenBagStrategy::class)]
#[CoversClass(BlockExtractor::class)]
#[CoversClass(Block::class)]
final class TokenBagAgreesWithRabinKarpTest extends TestCase
{
    private const array QUALIFIED = [
        T_NAME_QUALIFIED       => true,
        T_NAME_FULLY_QUALIFIED => true,
        T_NAME_RELATIVE        => true,
    ];

    /**
     * `$x = $a + $b` and `$x = $a - $b` are not the same code.
     *
     * The bag held only the tokens `token_get_all()` returns as arrays, so every
     * single-character token — roughly half the program text, and the half that
     * says what the code does — never reached it. Two bodies differing on every
     * operator produced byte-identical bags. {@see DefaultStrategy::tokenize()}
     * records fixing exactly this on its own side; this arm never got it.
     */
    #[Test]
    public function operatorsTellTwoBodiesApart(): void
    {
        $plus  = $this->blockOf('<?php function f($a, $b, $c) { $x = $a + $b; $y = $x * $c; return $y; }');
        $minus = $this->blockOf('<?php function g($a, $b, $c) { $x = $a - $b; $y = $x / $c; return $y; }');

        self::assertNotSame($plus->bag, $minus->bag, 'the bag is blind to operators');
    }

    /**
     * A block's token anchor is an index into the same numbering the contiguous
     * matcher and the facts layer use, or it anchors nothing.
     *
     * Without one, a token-bag site carried no token position at all, and every
     * audit that works in token indices scored the Rabin-Karp arm alone while
     * reporting the answer as the engine's.
     */
    #[Test]
    public function aBlockAnchorsIntoRabinKarpsTokenNumbering(): void
    {
        $source = "<?php\nclass C {\n    public function a(): int\n    {\n        \$x = 1;\n\n        return \$x + 2;\n    }\n}\n";

        $this->withFile($source, function (string $path) use ($source): void {
            $tokens = (new DefaultStrategy($this->config()))->tokenize($source);
            $blocks = (new BlockExtractor())->extract($path, CodeLines::IGNORED_TOKENS, self::QUALIFIED, false, new TokenNormalizer());

            self::assertCount(1, $blocks);
            $block = $blocks[0];

            self::assertSame(
                $block->startLine,
                $tokens->tokenRealLines[$block->startToken],
                'the anchor does not point at the line the block starts on',
            );
            self::assertSame(
                $block->endLine,
                $tokens->tokenRealLines[$block->startToken + $block->size - 1],
                'the block is not a contiguous token range in that numbering',
            );
        });
    }

    /**
     * The reported extent is a claim about what matched.
     *
     * The bag is reset at the opening brace, so the signature is not in it — but
     * the start line was taken from the `function` keyword, so every site on a
     * PSR-12 corpus reported one line it had never compared: 38 of 38 sites on
     * symfony-console.
     */
    #[Test]
    public function theExtentCoversWhatWasMatchedAndNothingElse(): void
    {
        // PSR-12: the brace is on its own line, below the signature.
        $source = "<?php\nclass C {\n    public function a(): int\n    {\n        return 1;\n    }\n}\n";

        $this->withFile($source, function (string $path): void {
            $blocks = (new BlockExtractor())->extract($path, CodeLines::IGNORED_TOKENS, self::QUALIFIED, false, new TokenNormalizer());

            self::assertCount(1, $blocks);
            // `return 1;` is on line 5. The signature (3) and the brace (4) are
            // not matched and must not be claimed; nor is the closing brace (6).
            self::assertSame(5, $blocks[0]->startLine);
            self::assertSame(5, $blocks[0]->endLine);
        });
    }

    /**
     * `--min-tokens` is a floor on the duplication, not on the haystack.
     *
     * Rabin-Karp gives that by construction — its match *is* a run of at least
     * that many tokens. This arm gated the two blocks and not what they share,
     * so one flag meant two different things depending on which arm answered,
     * and the merged default runs both. On symfony-console it let `Table.php`,
     * `ConsoleLoggerTest.php` and `InputTest.php` be reported as one clone on
     * 61 shared tokens under a floor of 100.
     *
     * The two bodies below are structurally disjoint — every statement of one
     * calls a function, every statement of the other assigns an integer — and
     * share only their `=` and `;`. Both clear a 40-token floor on their own
     * size; together they have 24 tokens in common. The low similarity
     * threshold is what makes them a candidate at all, which is the point: it
     * is the floor, and nothing else, that has to reject them.
     */
    #[Test]
    public function aFindingSharesAtLeastMinTokens(): void
    {
        $this->withFiles($this->twoBodiesSharingLittle(), function (array $paths): void {
            // Below the floor: the pair is a candidate, and is reported.
            $found = $this->bagClones($paths, minTokens: 5);

            self::assertCount(1, $found, 'the fixture no longer produces a candidate pair');
            self::assertSame(24, $found[0], 'the fixture no longer shares what this test assumes');

            // At a floor above what they share: gone, on that ground alone.
            self::assertSame(
                [],
                $this->bagClones($paths, minTokens: 40),
                'a clone was reported on less shared material than --min-tokens',
            );
        });
    }

    /**
     * The shared-token count of every clone the bag finds over `$paths`.
     *
     * @param list<string> $paths
     * @return list<int>
     */
    private function bagClones(array $paths, int $minTokens): array
    {
        $map = (new Detector(new TokenBagStrategy(new StrategyConfiguration(
            minLines: 1,
            minTokens: $minTokens,
            normalization: Normalization::Raw,
            // Low enough that similarity is not what decides this test.
            minSimilarity: 0.1,
        ))))->copyPasteDetection($paths);

        $shared = [];

        foreach ($map as $clone) {
            $shared[] = $clone->numberOfTokens();
        }

        return $shared;
    }

    /**
     * Two bodies that share only their punctuation.
     *
     * @return list<string>
     */
    private function twoBodiesSharingLittle(): array
    {
        $calls   = '<?php function a() { ';
        $assigns = '<?php function b() { ';

        for ($i = 0; $i < 12; ++$i) {
            $calls   .= '$a' . $i . ' = strlen("x' . $i . '"); ';
            $assigns .= '$b' . $i . ' = ' . $i . '; ';
        }

        return [$calls . '}', $assigns . '}'];
    }

    /**
     * @param list<string> $sources
     * @param callable(list<string>): void $body
     */
    private function withFiles(array $sources, callable $body): void
    {
        $paths = [];

        try {
            foreach ($sources as $source) {
                $paths[] = $path = (string) tempnam(sys_get_temp_dir(), 'phpcpd-bag-');
                file_put_contents($path, $source);
            }

            $body($paths);
        } finally {
            foreach ($paths as $path) {
                unlink($path);
            }
        }
    }

    private function blockOf(string $source): Block
    {
        $block = null;

        $this->withFile($source, function (string $path) use (&$block): void {
            $blocks = (new BlockExtractor())->extract($path, CodeLines::IGNORED_TOKENS, self::QUALIFIED, false, new TokenNormalizer());

            self::assertCount(1, $blocks);
            $block = $blocks[0];
        });

        self::assertInstanceOf(Block::class, $block);

        return $block;
    }

    private function withFile(string $source, callable $body): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'phpcpd-bag-');

        try {
            file_put_contents($path, $source);
            $body($path);
        } finally {
            unlink($path);
        }
    }

    private function config(int $minTokens = 70): StrategyConfiguration
    {
        return new StrategyConfiguration(
            minLines: 1,
            minTokens: $minTokens,
            normalization: Normalization::Raw,
            minSimilarity: 0.7,
        );
    }
}
