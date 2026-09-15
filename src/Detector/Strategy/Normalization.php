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

/**
 * How much of a token's identity the matcher is allowed to see.
 *
 * Three modes, and they were two booleans — `fuzzy` and `typeAnchored` — which
 * is four combinations for three behaviours. The fourth was not a fourth
 * behaviour but a silent duplicate: normalization ran when *either* flag was
 * set and {@see TokenNormalizer} read only `typeAnchored`, so `fuzzy` did
 * nothing at all whenever type-anchoring was on — which is the shipped default.
 * Measured on three corpora, `(fuzzy: true, typeAnchored: true)` and
 * `(fuzzy: false, typeAnchored: true)` produced byte-identical clone sets,
 * Jaccard 1.00 every time, and `--raw --type-anchored` was a spelling a user
 * could reach for the default.
 *
 * That redundancy had already cost something. `bench/lib.php` carries a guard
 * refusing to name one flag without the other, written after naming exactly one
 * silently inverted meaning when the default moved — a contradiction caught
 * downstream rather than made impossible.
 *
 * One value, three cases, no combination to get wrong.
 */
enum Normalization
{
    /**
     * Match on the token text as written.
     *
     * Two fragments are a clone only if they say the same thing, not merely the
     * same shape. This is `--raw`, and it is the only view an order-free
     * matcher can safely use: see {@see TokenBagStrategy::processFile()}.
     */
    case Raw;

    /**
     * Fold identifiers and literals to their class, all of them.
     *
     * `--fuzzy`. Rename-insensitive, and blind to the difference between an
     * `int` and a `string` parameter, which is what type-anchoring recovers.
     */
    case Fuzzy;

    /**
     * Fold identifiers and literals, but keep PHP's built-in type names.
     *
     * `--type-anchored`, and the shipped default: same-shape-different-type
     * pairs stay distinct while a consistent rename still matches.
     */
    case TypeAnchored;

    /** Does the matcher see anything other than the token's own text? */
    public function folds(): bool
    {
        return $this !== self::Raw;
    }

    /** Are the seventeen built-in type names kept concrete? */
    public function anchorsTypes(): bool
    {
        return $this === self::TypeAnchored;
    }
}
