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

namespace LucianoPereira\PhpcpdNext\Console;

/**
 * What a piece of the report means, not what colour it is.
 *
 * The report already ranks: findings come out highest-confidence first, a
 * demoted one carries its stratum, a near-miss carries its divergence. All of
 * that is currently carried by punctuation a reader has to parse — `[demoted:
 * table]`, `[inconsistent]` — in a wall of text where every character has the
 * same weight. Colour's job here is to make that existing ranking visible at a
 * glance, and nothing else: it never carries information the plain text does not
 * already carry, because the plain text is what a pipe, a log file and every
 * golden fixture see.
 *
 * Naming the roles rather than the colours is what keeps that honest. A call
 * site asks for `Demoted`, so it cannot invent a sixth meaning by reaching for
 * an unused colour, and the five decisions about which colour says what are all
 * made here.
 *
 * Deliberately the eight basic SGR codes, which every terminal since the VT100
 * renders and every user's theme remaps to their own palette. Choosing exact
 * shades would override that choice for no gain.
 */
enum Style
{
    /**
     * A finding the triage tier ranked down. Dim: still printed, still
     * countable, and visibly not what to read first.
     */
    case Demoted;

    /**
     * The part of a near-miss clone that differs — the reason the finding is
     * interesting rather than a plain copy. Yellow, the one thing on the line
     * that wants a second look.
     */
    case Divergence;

    /** The suggested next move. Cyan: useful, secondary to the finding itself. */
    case Advice;

    /**
     * The second and later occurrences of one clone. Dim, because they are the
     * same finding as the line above and the eye should read the group as one.
     */
    case Sibling;

    /** A run that failed. Red, and on stderr. */
    case Problem;

    /** Select Graphic Rendition parameters, without the escape or the `m`. */
    public function sequence(): string
    {
        return match ($this) {
            self::Demoted, self::Sibling => '2',
            self::Divergence             => '33',
            self::Advice                 => '36',
            self::Problem                => '31',
        };
    }
}
