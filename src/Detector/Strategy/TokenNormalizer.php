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

use const T_CONSTANT_ENCAPSED_STRING;
use const T_DNUMBER;
use const T_LNUMBER;
use const T_STRING;
use const T_VARIABLE;

/**
 * Abstracts a token to a type-class for Type-2 (rename-insensitive) matching.
 *
 * Variables, identifiers, and literals collapse to a single placeholder per class;
 * keywords, operators, and punctuation keep their raw text because they form the
 * structural skeleton that defines a clone. This generalises the previous
 * T_VARIABLE-only fuzzy mode toward the token-type abstraction used by Toma
 * (Wu et al., ICSE 2024), where a small set of type classes covers the vast
 * majority of tokens.
 *
 * Two code fragments that differ only in identifier and literal names produce
 * identical normalised content and are therefore detected as a clone — which the
 * raw-content matching of both engines could not do on its own.
 *
 * Type-anchored mode (--type-anchored) preserves PHP built-in type keywords
 * (int, string, bool, float, etc.) as concrete tokens instead of folding them
 * to ID. This prevents same-shape-different-type pairs from being reported as
 * clones — a precision improvement for typed PHP corpora (E2 / BCB-PHP).
 */
final class TokenNormalizer
{
    /** @var array<string, true> */
    private static array $phpTypes = [
        'int'      => true, 'string'   => true, 'bool'     => true,
        'float'    => true, 'array'    => true, 'null'     => true,
        'void'     => true, 'never'    => true, 'mixed'    => true,
        'object'   => true, 'callable' => true, 'iterable' => true,
        'self'     => true, 'static'   => true, 'parent'   => true,
        'false'    => true, 'true'     => true,
    ];

    public function __construct(private readonly bool $typeAnchored = false) {}

    public function normalize(int $tokenCode, string $content): string
    {
        return match ($tokenCode) {
            T_VARIABLE => '$',
            T_STRING   => ($this->typeAnchored && isset(self::$phpTypes[$content]))
                            ? $content
                            : 'ID',
            T_LNUMBER, T_DNUMBER       => 'NUM',
            T_CONSTANT_ENCAPSED_STRING => 'STR',
            default                    => $content,
        };
    }
}
