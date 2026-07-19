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

namespace LucianoPereira\PhpcpdNext\Orphan;

/**
 * The raw material a {@see SymbolCollector} extracts from a file set, before any
 * orphan judgement is made:
 *
 *   - $definitions  every top-level type/function declaration found.
 *   - $references   short name → how many times it is *used* in code (not in the
 *                   declaration itself, not in comments). Zero means orphan.
 *   - $stringNames  short/qualified names that appear inside string literals —
 *                   the weak signal that a name might be reached dynamically
 *                   (`new $class`, a DI service id, a config array).
 */
final readonly class CollectedSymbols
{
    /**
     * @param list<Symbol>          $definitions
     * @param array<string, int>    $references
     * @param array<string, bool>   $stringNames
     */
    public function __construct(
        public array $definitions,
        public array $references,
        public array $stringNames,
    ) {}
}
