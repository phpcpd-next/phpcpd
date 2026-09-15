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

namespace LucianoPereira\PhpcpdNext\Detector\Strategy;

use function array_values;
use function pack;
use function unpack;

/**
 * A key for every window of tokens in one file, rolled rather than recomputed.
 *
 * Rabin–Karp is O(n) because the window's hash is *rolled*: the token leaving is
 * subtracted, the token entering is added, and the cost does not depend on how
 * wide the window is. This matcher instead took a digest over the whole window
 * at every position — O(n · minTokens), and 64 % of the scan phase.
 *
 * Two polynomial hashes, not one, each modulo a prime just under 2^31 so that
 * every product stays inside a 64-bit integer. One such hash would collide far
 * too readily for a table that keys on nothing else; their pair is the key, and
 * it is the same eight bytes the digest prefix used to supply.
 *
 * ## Why a whole file at a time
 *
 * The obvious shape — an object holding the window, asked to advance — was
 * written first and measured slower than the digest it replaced: 0.108s against
 * 0.057s. PHP charges for method calls, and at six per position that swamps an
 * algorithm whose whole advantage is doing less arithmetic than a C function.
 * Building every key for a file in one call runs at 0.021s, three times faster
 * than the digest, for about 2.6 MB of peak memory on a corpus whose largest
 * file holds some twenty thousand windows.
 *
 * Which is the general lesson worth writing down: in this language an
 * asymptotic improvement is not automatically an improvement, and the version
 * that reads best lost to the version nobody wanted to read until the calls
 * were taken out of the loop.
 */
final class RollingWindow
{
    /**
     * Primes just below 2^31, so that `hash * base` cannot leave a 64-bit
     * integer: both factors are under 2^31, so the product is under 2^62.
     */
    private const int FIRST_PRIME  = 2147483647;
    private const int SECOND_PRIME = 2147483629;
    private const int FIRST_BASE   = 1000003;
    private const int SECOND_BASE  = 1000033;

    /**
     * One key per window start, in order; empty when the file holds fewer
     * tokens than a window.
     *
     * @param string $signature the file's per-token hashes, eight bytes each
     * @param int $count how many tokens those bytes describe
     * @param int $size the window, in tokens
     * @return list<string>
     */
    public static function keys(string $signature, int $count, int $size): array
    {
        if ($count < $size || $signature === '') {
            return [];
        }

        /** @var list<int> $word each token's two 32-bit halves, one per polynomial */
        $word = array_values((array) unpack('N*', $signature));

        $firstPower  = 1;
        $secondPower = 1;

        for ($step = 1; $step < $size; ++$step) {
            $firstPower  = $firstPower * self::FIRST_BASE % self::FIRST_PRIME;
            $secondPower = $secondPower * self::SECOND_BASE % self::SECOND_PRIME;
        }

        $first  = 0;
        $second = 0;

        for ($token = 0; $token < $size; ++$token) {
            $first  = ($first * self::FIRST_BASE + $word[$token * 2] % self::FIRST_PRIME) % self::FIRST_PRIME;
            $second = ($second * self::SECOND_BASE + $word[$token * 2 + 1] % self::SECOND_PRIME) % self::SECOND_PRIME;
        }

        $keys = [];
        $last = $count - $size;

        for ($at = 0; $at <= $last; ++$at) {
            $keys[] = pack('N2', $first, $second);

            if ($at === $last) {
                break;
            }

            // The outgoing term is removed before the incoming one is added.
            // PHP's `%` keeps the sign of the dividend, so the subtraction is
            // brought back into range explicitly rather than left to produce a
            // negative key.
            $leaving  = $word[$at * 2] % self::FIRST_PRIME;
            $entering = $word[($at + $size) * 2] % self::FIRST_PRIME;
            $first    = (($first - $leaving * $firstPower) % self::FIRST_PRIME + self::FIRST_PRIME) % self::FIRST_PRIME;
            $first    = ($first * self::FIRST_BASE + $entering) % self::FIRST_PRIME;

            $leaving  = $word[$at * 2 + 1] % self::SECOND_PRIME;
            $entering = $word[($at + $size) * 2 + 1] % self::SECOND_PRIME;
            $second   = (($second - $leaving * $secondPower) % self::SECOND_PRIME + self::SECOND_PRIME) % self::SECOND_PRIME;
            $second   = ($second * self::SECOND_BASE + $entering) % self::SECOND_PRIME;
        }

        return $keys;
    }
}
