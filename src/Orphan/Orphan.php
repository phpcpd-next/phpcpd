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
 * A single orphan finding: a {@see Symbol} that no reference could be found for,
 * plus how confident we are and why.
 *
 * Two confidence tiers, harvested from Psalm's UnusedClass / PossiblyUnusedClass
 * distinction:
 *
 *   - CONFIDENCE_DEAD     — nothing anywhere mentions this name. Safe to delete.
 *                           These drive the non-zero exit code (the CI gate).
 *   - CONFIDENCE_POSSIBLE — no *code* reference, but there is a reason it might
 *                           still be reachable (a public contract, or the name
 *                           only shows up in a string literal that could feed a
 *                           dynamic `new $class`). Reported for review, but does
 *                           not fail the build.
 */
final readonly class Orphan
{
    public const string CONFIDENCE_DEAD     = 'dead';
    public const string CONFIDENCE_POSSIBLE = 'possible';

    /**
     * @param bool    $entireFileOrphaned every symbol declared in this file is
     *                                     itself an orphan — the whole file is
     *                                     unwired, a stronger delete signal than
     *                                     a lone dead class among live ones.
     * @param ?string $duplicateOf         a human label (`Fqn (file:line)`) for a
     *                                     *live* symbol this orphan duplicates —
     *                                     i.e. this looks like the superseded copy
     *                                     that some refactor replaced but left
     *                                     behind. Null when it is not a copy.
     */
    public function __construct(
        public Symbol $symbol,
        public string $confidence,
        public string $reason,
        public bool $entireFileOrphaned = false,
        public ?string $duplicateOf = null,
    ) {}

    public function isDefinite(): bool
    {
        return $this->confidence === self::CONFIDENCE_DEAD;
    }
}
