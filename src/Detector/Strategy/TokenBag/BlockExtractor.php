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
use function token_get_all;

use const T_FUNCTION;

use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenNormalizer;

/**
 * Splits a file into function/method blocks by scanning tokens and tracking brace
 * depth — no full parser needed. Each block becomes an order-invariant token bag.
 * Nested closures are folded into their enclosing block (depth tracking keeps them
 * from closing it early); abstract/interface methods (no body) are skipped.
 */
final class BlockExtractor
{
    /**
     * @param array<int, true> $ignore tokens to drop (whitespace/comments/...)
     * @return list<Block>
     */
    public function extract(string $file, array $ignore, bool $fuzzy, TokenNormalizer $normalizer): array
    {
        $content = file_get_contents($file);

        if ($content === false) {
            return [];
        }

        $blocks    = [];
        $state     = 'idle'; // idle | seekingBody | inBody
        $depth     = 0;
        $startLine = 0;
        $endLine   = 0;
        $bag       = [];
        $size      = 0;

        foreach (token_get_all($content) as $token) {
            if (is_array($token)) {
                if ($state === 'idle' && $token[0] === T_FUNCTION) {
                    $state     = 'seekingBody';
                    $startLine = $token[2];
                } elseif ($state === 'inBody' && !isset($ignore[$token[0]])) {
                    $content2 = $fuzzy ? $normalizer->normalize($token[0], $token[1]) : $token[1];
                    $signature = $token[0] . ':' . $content2;
                    $bag[$signature] = ($bag[$signature] ?? 0) + 1;
                    $size++;
                    $endLine = $token[2];
                }

                continue;
            }

            // single-character token: '{', '}', ';', '(', ...
            if ($state === 'seekingBody') {
                if ($token === '{') {
                    $state = 'inBody';
                    $depth = 1;
                    $bag   = [];
                    $size  = 0;
                } elseif ($token === ';') {
                    $state = 'idle'; // abstract/interface method — no body
                }
            } elseif ($state === 'inBody') {
                if ($token === '{') {
                    $depth++;
                } elseif ($token === '}') {
                    $depth--;

                    if ($depth === 0) {
                        $blocks[] = new Block($file, $startLine, $endLine, $bag, $size);
                        $state    = 'idle';
                    }
                }
            }
        }

        return $blocks;
    }
}
