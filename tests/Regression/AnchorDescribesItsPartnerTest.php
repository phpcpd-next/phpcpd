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

use function file_put_contents;
use function substr;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\FileTokens;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A reported occurrence points at the code it was matched against, or it points
 * at nothing.
 *
 * The window table maps a hash to whichever occurrence registered it *first*,
 * and in a file of repeated blocks that is routinely a later, unrelated one —
 * a hazard `DefaultStrategy::record()` has always named and used to half-guard,
 * rejecting only an anchor whose run would not fit inside the file. An anchor
 * that fits but describes different code passed, and the extent printed for it
 * was another fragment's: measured at the shipped default, 4 of php-parser's
 * two-site findings and 1 of symfony-string's 28 named a partner whose tokens
 * agreed with the run at 0.06, where an exact match agrees at 1.00.
 *
 * A Rabin-Karp match *is* an equality of token sequences — that is the whole
 * claim the window hashes make — so the invariant below is the engine's own
 * definition of what it found, asserted rather than assumed. Withholding the
 * anchor is the honest failure: the occurrence falls back to its line-derived
 * span, which is coarser and true.
 */
#[CoversClass(DefaultStrategy::class)]
final class AnchorDescribesItsPartnerTest extends TestCase
{
    /**
     * One statement, distinct from every other under normalization.
     *
     * The operators vary, because normalization folds identifiers and literals
     * and keeps operators: two statements differing only in their variable
     * names are the same token sequence to the matcher, and a fixture built
     * from those would be testing periodicity instead of this.
     */
    private static function statement(int $i): string
    {
        $operators = ['+', '-', '*', '/', '%', '&', '|', '^'];
        $operator  = $operators[$i % count($operators)];

        return "        \$v{$i} = (\$a{$i} {$operator} \$b{$i}) {$operator} count(\$rows{$i});\n";
    }

    /** @param list<int> $statements */
    private static function method(string $name, array $statements): string
    {
        $body = '';

        foreach ($statements as $i) {
            $body .= self::statement($i);
        }

        return "    public function {$name}(): void\n    {\n" . $body . "    }\n\n";
    }

    /**
     * Three short methods that overlap in pairs, and one long one holding all
     * of their statements in order.
     *
     * Scanning registers a window the first time it is seen, so by the time the
     * long method is reached every window it contains is already in the table —
     * but registered by *three different methods, at three unrelated offsets*.
     * The run therefore never breaks, and its anchor is whichever occurrence
     * registered its **first** window, which is `one()`. The window hash
     * guarantees the first `minTokens` tokens agree and says nothing about the
     * rest, so the reported first occurrence is `one()` extended to the length
     * of `four()` — twice the code it actually holds.
     *
     * The file-change guard in `scan()` does not see this: all four methods are
     * in one file, so the registrant never changes.
     */
    private static function overlappingMethods(): string
    {
        return "<?php\n\nclass Shape\n{\n"
            . self::method('one', [1, 2, 3, 4])
            . self::method('two', [3, 4, 5, 6])
            . self::method('three', [5, 6, 7, 8])
            . self::method('four', [1, 2, 3, 4, 5, 6, 7, 8])
            . "}\n";
    }

    #[Test]
    public function everyAnchoredOccurrenceHoldsTheTokensItWasMatchedAgainst(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'phpcpd-anchor-');

        try {
            file_put_contents($path, self::overlappingMethods());

            // Normalization on, as the default ships it, and a low floor so the
            // fixture stays readable.
            $strategy = new DefaultStrategy(new StrategyConfiguration(1, 20, Normalization::TypeAnchored, 0.7));
            $map      = (new Detector($strategy))->copyPasteDetection([$path]);
            $map->settle();

            $signature = $strategy->tokenize((string) file_get_contents($path))->signature;
            $checked   = 0;

            foreach ($map as $clone) {
                $sites = [];

                foreach ($clone->files() as $site) {
                    if ($site->startToken !== null) {
                        $sites[] = $site;
                    }
                }

                for ($i = 1; $i < count($sites); ++$i) {
                    $tokens = min($clone->tokensOf($sites[0]), $clone->tokensOf($sites[$i]));

                    ++$checked;

                    self::assertSame(
                        self::span($signature, $sites[0]->startToken, $tokens),
                        self::span($signature, $sites[$i]->startToken, $tokens),
                        sprintf(
                            'line %d claims to be a copy of line %d, and their tokens differ',
                            $sites[0]->startLine,
                            $sites[$i]->startLine,
                        ),
                    );
                }
            }

            self::assertGreaterThan(0, $checked, 'the fixture produced no anchored pair to check');
            self::assertGreaterThan(0, count($map->clones()), 'the fixture produced no finding at all');
        } finally {
            unlink($path);
        }
    }

    private static function span(string $signature, int $first, int $count): string
    {
        return substr($signature, $first * FileTokens::TOKEN_BYTES, $count * FileTokens::TOKEN_BYTES);
    }
}
