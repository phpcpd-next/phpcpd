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

namespace LucianoPereira\PhpcpdNext\Util;

use function count;
use function is_string;

/**
 * Walks a `token_get_all` stream while tracking bracket depth.
 *
 * Both token consumers in this codebase need the same primitive — read forward
 * from a position until a bracket pair closes, deciding something along the way.
 * The orphan collector uses it on `(` to inspect an `if` condition; the clone
 * suppressor uses it on `{` to find where a declaration body ends.
 */
final class TokenCursor
{
    /**
     * Visit tokens from $from until the $open/$close pair returns to depth zero.
     *
     * $visit receives each token and the depth at that point, and returns null to
     * keep going or a value to stop the walk with. Reaching the closing bracket —
     * or the end of the stream — yields null, which each caller reads as its own
     * "nothing decided" answer.
     *
     * @template TResult
     * @param array<int, array{0: int, 1: string, 2: int}|string>            $tokens
     * @param callable(array{0: int, 1: string, 2: int}|string, int): ?TResult $visit
     * @return ?TResult
     */
    public static function untilBalanced(
        array $tokens,
        int $from,
        string $open,
        string $close,
        callable $visit,
    ): mixed {
        $count = count($tokens);
        $depth = 0;

        for ($i = $from; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_string($token) && $token === $open) {
                $depth++;
            }

            $decision = $visit($token, $depth);

            if ($decision !== null) {
                return $decision;
            }

            if (is_string($token) && $token === $close) {
                $depth--;

                if ($depth === 0) {
                    return null;
                }
            }
        }

        return null;
    }
}
