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

namespace LucianoPereira\PhpcpdNext\Detector\Strategy\TokenBag;

use function file_get_contents;
use function is_array;
use function ord;
use function strrpos;
use function substr;
use function substr_count;
use function token_get_all;

use const T_FUNCTION;
use const T_STRING;

use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenNormalizer;

/**
 * Splits a file into function/method blocks by scanning tokens and tracking brace
 * depth — no full parser needed. Each block becomes an order-invariant token bag.
 * Nested closures are folded into their enclosing block (depth tracking keeps them
 * from closing it early); abstract/interface methods (no body) are skipped.
 *
 * What counts as a token here is what counts as a token in
 * {@see \LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy::tokenize()},
 * and the two agreeing is the point. They did not: this extractor bagged only
 * the tokens `token_get_all()` returns as arrays, so every single-character
 * token — `;`, `(`, `=`, and every operator — was invisible to it. That is the
 * half of the program text that says what the code *does*, and the default
 * strategy records having fixed exactly this defect on its own side; the bag
 * never got the fix. Measured on the fixture in that comment, `$x = $a + $b;`
 * and `$x = $a - $b;` produced *identical bags*, so the two arms of the shipped
 * default disagreed about whether two fragments were the same code.
 */
final class BlockExtractor
{
    /**
     * @param array<int, true> $ignore tokens to drop (whitespace/comments/...)
     * @param array<int, true> $qualified token types that bundle a whole
     *        namespaced name, folded to the name they end in — the same fold
     *        the contiguous matcher applies, so that `\App\Money::of()` and
     *        `Money::of()` are one token sequence in both arms
     * @return list<Block>
     */
    public function extract(
        string $file,
        array $ignore,
        array $qualified,
        bool $fuzzy,
        TokenNormalizer $normalizer,
    ): array {
        $content = file_get_contents($file);

        if ($content === false) {
            return [];
        }

        $blocks     = [];
        $state      = 'idle'; // idle | seekingBody | inBody
        $depth      = 0;
        $startLine  = 0;
        $endLine    = 0;
        $startToken = 0;
        $bag        = [];
        $size       = 0;
        // The index of the next significant token, counted from the start of
        // the file over every state. It is what makes a block's position
        // comparable with a Rabin-Karp run and with the facts layer's function
        // ranges, so it counts the signature and the braces even though the bag
        // does not.
        $at = 0;
        // The line the next token begins on, tracked because a single-character
        // token carries no line of its own. Advanced past the newlines a token
        // contains rather than to the line it began on — dating a `}` by the
        // preceding token's line reports it on the line above.
        $cursor = 1;

        foreach (token_get_all($content) as $token) {
            if (is_array($token)) {
                $line   = $token[2];
                $cursor = $line + substr_count($token[1], "\n");

                // A qualified name is the name it ends in.
                if (isset($qualified[$token[0]])) {
                    $cut      = strrpos($token[1], '\\');
                    $token[1] = $cut === false ? $token[1] : substr($token[1], $cut + 1);
                    $token[0] = T_STRING;
                }

                if (isset($ignore[$token[0]])) {
                    continue;
                }

                $index = $at++;

                if ($state === 'idle' && $token[0] === T_FUNCTION) {
                    $state = 'seekingBody';

                    continue;
                }

                if ($state === 'inBody') {
                    $this->bag($bag, $size, $startLine, $startToken, $endLine, $token[0], $token[1], $line, $index, $fuzzy, $normalizer);
                }

                continue;
            }

            // A single-character token — `;`, `{`, and every operator. Its type
            // is the character's own ordinal, which cannot collide with a `T_`
            // constant: those start at 256.
            $line  = $cursor;
            $index = $at++;

            if ($state === 'seekingBody') {
                if ($token === '{') {
                    $state = 'inBody';
                    $depth = 1;
                    $bag   = [];
                    $size  = 0;
                } elseif ($token === ';') {
                    $state = 'idle'; // abstract/interface method — no body
                }

                continue;
            }

            if ($state !== 'inBody') {
                continue;
            }

            if ($token === '{') {
                ++$depth;
            } elseif ($token === '}') {
                --$depth;

                // The brace that closes the body is the delimiter, not content:
                // it is the one token every function body ends in, so it
                // discriminates nothing, and bagging it would put a line the
                // match does not cover back into the reported extent.
                if ($depth === 0) {
                    if ($size > 0) {
                        $blocks[] = new Block($file, $startLine, $endLine, $startToken, $bag, $size);
                    }

                    $state = 'idle';

                    continue;
                }
            }

            $this->bag($bag, $size, $startLine, $startToken, $endLine, ord($token), $token, $line, $index, $fuzzy, $normalizer);
        }

        return $blocks;
    }

    /**
     * Add one token to the block being built, and let the first and last one in
     * set the extent.
     *
     * @param array<string, int> $bag
     */
    private function bag(
        array &$bag,
        int &$size,
        int &$startLine,
        int &$startToken,
        int &$endLine,
        int $type,
        string $text,
        int $line,
        int $index,
        bool $fuzzy,
        TokenNormalizer $normalizer,
    ): void {
        $signature       = $type . ':' . ($fuzzy ? $normalizer->normalize($type, $text) : $text);
        $bag[$signature] = ($bag[$signature] ?? 0) + 1;

        if (++$size === 1) {
            $startLine  = $line;
            $startToken = $index;
        }

        $endLine = $line;
    }
}
