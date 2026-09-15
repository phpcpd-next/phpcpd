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

namespace LucianoPereira\PhpcpdNext\Presentation;

use function array_key_exists;
use function array_slice;
use function count;
use function file_get_contents;
use function is_array;
use function min;
use function token_get_all;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\Util\CodeLines;

use const T_CONSTANT_ENCAPSED_STRING;
use const T_DNUMBER;
use const T_LNUMBER;

/**
 * How many literal values differ between the copies of one clone.
 *
 * The unified engine matches under a normalized view in which a string folds to
 * `STR` and a number to `NUM`, which is deliberate and is what lets it see a
 * Type-2 clone. What was not deliberate is that the report then said nothing.
 *
 * Two files differing only in an error message, a threshold of 100 against
 * 5000, and a multiplier of 2 against 7 are reported as a clone that is **not**
 * inconsistent, with no divergences at all — while `CodeClone::isGapped()`
 * promises precisely this information: "copies that share a skeleton but
 * diverge. These carry the bug risk: one copy patched, the sibling not." A
 * threshold that differs between two copies is that risk exactly, and it was
 * invisible. On php-parser, 23 of the 25 clones reported as not inconsistent
 * have copies that differ, every one of them in a literal.
 *
 * So the count is measured and printed. It does not change what is detected and
 * it does not change what `inconsistent` means — a structural gap stays the
 * thing that word names — it tells the reader the one fact the normalized view
 * had swallowed.
 *
 * Positions only, compared against the first site: the clone's own token range
 * at each occurrence, one index at a time. An occurrence whose start the
 * strategy did not record answers zero, because "cannot tell" must not read as
 * "nothing differs" in the other direction either — it is reported as no claim
 * rather than as a claim of sameness. A gapped clone answers zero for the same
 * reason and a sharper one: see {@see of()}.
 */
final class LiteralDivergence
{
    /** @var array<int, true> */
    private const array LITERALS = [
        T_CONSTANT_ENCAPSED_STRING => true,
        T_LNUMBER                  => true,
        T_DNUMBER                  => true,
    ];

    /** @var array<string, list<array{0: int, 1: string}>> */
    private array $tokens = [];

    /**
     * How many token positions hold a literal that differs between copies.
     *
     * Asked only of a clone with no gaps, and that is not a scope decision but
     * an arithmetic one. Two occurrences are compared position by position,
     * which is sound exactly when nothing has been skipped in either — a gapped
     * clone's ranges are *not* aligned that way, and past its first divergence
     * every later index compares two unrelated tokens. Measured on php-parser,
     * naive alignment reports 57.7% of all token positions differing across
     * gapped clones against 24.9% across the rest: the first number is an
     * artefact of the comparison and nothing else.
     *
     * Nothing is lost by declining. The count exists because `inconsistent`
     * says nothing when literals fold away, and a gapped clone is one that says
     * `inconsistent` already; its divergences are reported in full beside it.
     */
    public function of(CodeClone $clone): int
    {
        $sites = $clone->files();

        if (count($sites) < 2 || $clone->isGapped()) {
            return 0;
        }

        $lead = null;
        $count = 0;

        foreach ($sites as $site) {
            if ($site->startToken === null) {
                return 0;
            }

            $range = array_slice(
                $this->tokensOf($site->name),
                $site->startToken,
                $site->tokens($clone->numberOfTokens()),
            );

            if ($lead === null) {
                $lead = $range;

                continue;
            }

            $differing = 0;

            for ($i = 0, $n = min(count($lead), count($range)); $i < $n; $i++) {
                if ($lead[$i][1] === $range[$i][1]) {
                    continue;
                }

                if (isset(self::LITERALS[$lead[$i][0]]) && isset(self::LITERALS[$range[$i][0]])) {
                    $differing++;
                }
            }

            $count = $differing > $count ? $differing : $count;
        }

        return $count;
    }

    /**
     * The significant tokens of one file, as the matchers see them.
     *
     * The same ignore set the matcher uses, for the same reason it lives in one
     * place: a reader is being told about the tokens that were compared, so this
     * has to be counting those and not some neighbouring set.
     *
     * @return list<array{0: int, 1: string}>
     */
    private function tokensOf(string $file): array
    {
        if (array_key_exists($file, $this->tokens)) {
            return $this->tokens[$file];
        }

        $source = @file_get_contents($file);

        if ($source === false) {
            return $this->tokens[$file] = [];
        }

        $tokens = [];

        foreach (token_get_all($source) as $token) {
            if (!is_array($token)) {
                $tokens[] = [0, $token];

                continue;
            }

            if (isset(CodeLines::IGNORED_TOKENS[$token[0]])) {
                continue;
            }

            $tokens[] = [$token[0], $token[1]];
        }

        return $this->tokens[$file] = $tokens;
    }
}
