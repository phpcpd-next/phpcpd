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
 * Four tiers. The first two are harvested from Psalm's UnusedClass /
 * PossiblyUnusedClass distinction; the last two exist so that a symbol something
 * already accounts for stays visible instead of vanishing:
 *
 *   - CONFIDENCE_DEAD     — nothing anywhere mentions this name. Safe to delete.
 *                           These drive the non-zero exit code (the CI gate).
 *   - CONFIDENCE_POSSIBLE — no *code* reference, but there is a reason it might
 *                           still be reachable (a public contract, or the name
 *                           only shows up in a string literal that could feed a
 *                           dynamic `new $class`). Reported for review, but does
 *                           not fail the build.
 *   - CONFIDENCE_SUPPRESSED — a rule structurally accounts for it (an existence
 *                           guard, a fixture path, a config registration). Still
 *                           reported and counted, never gating: a rule that
 *                           misfires must stay visible rather than turn a real
 *                           orphan into silence.
 *   - CONFIDENCE_PLANNED  — the author declared it deliberately unwired with
 *                           `@phpcpd-planned`. Reported as a staged-work
 *                           inventory rather than as a defect.
 */
final readonly class Orphan
{
    public const string CONFIDENCE_DEAD       = 'dead';
    public const string CONFIDENCE_POSSIBLE   = 'possible';
    public const string CONFIDENCE_SUPPRESSED = 'suppressed';
    public const string CONFIDENCE_PLANNED    = 'planned';

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
     * @param ?string $rule                the {@see Rule} that produced this
     *                                     verdict, and the heading it groups under.
     * @param ?string $evidence            where the claim can be checked —
     *                                     "file:line" of the string literal or
     *                                     config entry naming this symbol.
     */
    public function __construct(
        public Symbol $symbol,
        public string $confidence,
        public string $reason,
        public bool $entireFileOrphaned = false,
        public ?string $duplicateOf = null,
        public ?string $rule = null,
        public ?string $evidence = null,
    ) {}

    public function isDefinite(): bool
    {
        return $this->confidence === self::CONFIDENCE_DEAD;
    }

    /** A finding needing a decision, as opposed to an accounted-for symbol. */
    public function isFinding(): bool
    {
        return $this->confidence === self::CONFIDENCE_DEAD
            || $this->confidence === self::CONFIDENCE_POSSIBLE;
    }
}
