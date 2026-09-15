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

namespace LucianoPereira\PhpcpdNext\Facts;

/**
 * One statement of a file, as {@see FileStatements} segments it.
 *
 * Ranges are **significant-token indices** — the encoder's numbering, the same
 * one {@see RegionStructure} uses — so a statement and a reported span are
 * expressed in one coordinate system and can be compared without a mapping.
 *
 * `singleCall` is the shape test the M5 pre-commitment experiment built and
 * self-tested: the statement's tokens read as
 * `callee ( args ) [ (-> | ?-> | ::) name ( args ) ]*` and nothing else. It is
 * reused here as half of a *role* definition; it silences nothing, and rule 8
 * as a filter stays dead.
 */
final readonly class Statement
{
    /**
     * @param int                   $first     first significant token index
     * @param int                   $last      last significant token index; less than
     *                                         $first for a statement holding no
     *                                         significant token at all
     * @param bool                  $singleCall the statement is one call expression
     *                                          whose value is discarded
     * @param bool                  $literalAppend it reads `$name[] = <literal>;` —
     *                                          one element of a table written as
     *                                          statements rather than as an array
     * @param bool                  $topLevel  it sits at the file's outermost brace
     *                                         frame — outside every function, class,
     *                                         interface, trait and enum body
     * @param bool                  $preamble  it is `declare`, `namespace` or `use`:
     *                                         a declaration the file needs to compile,
     *                                         not a thing the file does
     * @param array<string, true>   $variables the variable names it mentions
     * @param list<array{0: int, 1: int}> $closureBodies statement-index ranges,
     *        inclusive, of the closures this statement receives as arguments
     *
     *        A closure passed as an argument is a value, so its body is not part
     *        of this statement; the scan collects it as statements of its own
     *        and records where they are. Anything asking what the closure
     *        *holds* — {@see FileRole}, which counts a call as a registration
     *        only when the closures it receives hold registrations too — needs
     *        the link, and reconstructing it from token ranges afterwards would
     *        be guessing at what the scan already knew.
     */
    public function __construct(
        public int $first,
        public int $last,
        public bool $singleCall,
        public bool $literalAppend,
        public bool $topLevel,
        public bool $preamble,
        public array $variables,
        public array $closureBodies = [],
        /**
         * The statement holds nothing but delimiters closing something already
         * open — the `}` of `});`, say.
         *
         * It never was a statement; until the encoder counted single-character
         * tokens it held none at all and `length()` returned zero, which is why
         * the rules that skip empty statements are the ones that need to skip
         * this. Kept as a property rather than re-derived, because the tokens
         * are not available where the question is asked.
         */
        public bool $closing = false,
    ) {}

    /** How many significant tokens it covers. */
    public function length(): int
    {
        return $this->last < $this->first ? 0 : $this->last - $this->first + 1;
    }
}
