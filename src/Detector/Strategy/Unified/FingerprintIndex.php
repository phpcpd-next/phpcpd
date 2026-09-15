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

namespace LucianoPereira\PhpcpdNext\Detector\Strategy\Unified;

use function count;

/**
 * Stage B's global index: selected fingerprint → the places it was selected.
 *
 * A posting is a (file, token position) pair packed into one integer — the file
 * id in the high 32 bits, the token position in the low 32. PHP's packed integer
 * arrays cost about 16 bytes an element, where a list of two-element arrays would
 * cost several times that plus a bucket header each; at corpus scale that
 * difference is the difference between tens of megabytes and hundreds. The
 * packing is also what makes a postings list sort itself: because the file id is
 * the high word, ascending integer order *is* (file, position) order.
 *
 * ## The two frequency caps, and why they are counted
 *
 * A fingerprint that occurs in thousands of places is generated code or framework
 * boilerplate, and every pair of its postings is a candidate Stage C would have to
 * extend — quadratic work for a region no one wants reported. Postings past
 * {@see POSTINGS_CAP} are therefore dropped.
 *
 * That cap counts a fingerprint's postings across the whole corpus, and it is
 * counting an axis that cannot bound the work. The anchors a fingerprint
 * contributes to a *file pair* are the **product** of its counts in the two files,
 * not their sum, so what generates the work is how often it repeats inside one
 * file. On `bench/corpus/phpunit` a single fingerprint occurring 9,179 times across
 * 13 files — 706 per file — accounts for 42.1 million of the 42.4 million anchor
 * pairs the normalized view generates, 99.4% of the total from one fingerprint; the
 * corpus-wide cap fires on it and still admits the half a million pairs that 1,000
 * postings imply. On `bench/corpus/php-parser` the worst offender sits in *two*
 * files at 160 occurrences each, where no cap counting distinct files can reach it
 * at all: a document-frequency cutoff removes 0.3% of anchor generation there
 * against 68.6% for an occurrence cutoff at the same rank.
 *
 * {@see PER_FILE_CAP} therefore bounds occurrences kept per fingerprint per file,
 * which is the axis the measurement points at.
 *
 * Both are recall trades, and the one place in this engine where a clone can be
 * lost for a reason other than a theorem. So neither is ever silent: the index
 * keeps the true occurrence count of every fingerprint, and the strategy surfaces
 * the tally. "Framework boilerplate is exactly where duplication lives" is a real
 * objection to both caps, and the only honest answer to it is a number.
 *
 * Postings arrive in ascending (file id, position) order — file ids are assigned
 * from the sorted file list and each file's fingerprints are selected in position
 * order — so capping keeps a prefix of an already-sorted list, and the same corpus
 * yields the same index whatever order the files were handed over in.
 */
final class FingerprintIndex
{
    /**
     * Maximum postings kept for a single fingerprint.
     *
     * One thousand postings is already half a million candidate pairs for that
     * fingerprint alone; beyond it the fingerprint is describing the language, not
     * the program.
     */
    public const int POSTINGS_CAP = 1000;

    /**
     * Maximum occurrences kept for one fingerprint from one file.
     *
     * **M2 audit ruling D (2026-08-31)**, which granted this constant at 32 and
     * entered it in the plan's §1 constants table. It is the same *class* of
     * constant as {@see POSTINGS_CAP} — a counted recall trade, not a theorem —
     * and the ruling's reasoning is that the class was already admitted: what
     * plan §3 forbids is a value *tuned to pass a gate*, and this one is not.
     *
     * **The derivation was restated at the M4 audit (2026-09-01) and the value
     * did not move.** Ruling D chose 32 on a dense-coverage knee — the union of
     * reported source lines restricted to clones of ≥ 4 tokens per line, which
     * peaked at 32 and there carried more coverage than no cap at all. On the
     * post-M3 engine that metric no longer has a knee: it peaks at C = 8 and
     * rises monotonically from 16 toward uncapped, so read literally it now
     * selects 8. The full curve is in the M4 packet. The cause is this engine's
     * own M3/M4 changes rather than anything about the cap — the dirty and clean
     * corpora give the same shape, so contamination is excluded — and the
     * standing *hypothesis*, recorded as hypothesis and not as finding, is that
     * ruling O's evidence-return supplies from refused candidates what capping
     * used to rescue.
     *
     * A constant may not stand on a dead derivation, so the derivation is the
     * **floor argument** — which was always what rejected the smaller values,
     * and which is legitimate here precisely because its two exhibits were
     * pre-registered: both were recorded at M2, verified by inspection, before
     * anyone knew the knee would invert. On phpunit, "preserved" meaning the
     * exhibit's large class is reported at all:
     *
     *     cap                4     8    16    24    32    48    64   128    ∞
     *     Assert.php 572-ln  ·     ·     ·     Y     Y     Y     Y     Y    Y
     *     BuilderTest 974-ln ·     ·     ·     ·     Y     Y     ·     Y    Y
     *     phpunit secs     1.4   1.6   1.9    —   2.7    —    7.1    —   5.1
     *
     * **Thirty-two is the smallest cap that preserves both exhibits.** It is not
     * the only one — 48, 128 and uncapped preserve them too — so the derivation
     * is minimality against a cost that rises steeply, not uniqueness. Below 32
     * one exhibit or both are destroyed: at C = 16 `Assert.php`'s 572-line class
     * is absent and `BuilderTest.php`'s largest falls to 263 lines; at C = 8 they
     * are 98 and 357. Speed cannot buy that back, and 32 is still chosen
     * *knowing it makes the wall-clock number worse* than 8 — the opposite of
     * tuning to pass.
     *
     * One measured irregularity, recorded rather than smoothed: C = 64 **loses**
     * `BuilderTest.php`'s large class (largest falls to 510 lines) while 32, 48,
     * 128 and uncapped all keep it. Preservation is therefore not monotonic in
     * the cap, which is a further reason to treat any single-number reading of
     * this constant as evidence about a particular engine state.
     *
     * **This derivation is engine-state-dependent, and says so.** It rests on
     * two exhibits behaving differently at different caps. Any future change
     * that makes them cap-insensitive — as the dense metric became — re-opens
     * this constant and requires a fresh derivation rather than an appeal to
     * this comment.
     *
     * Subsumption against Rabin-Karp cannot arbitrate any of this: that gate
     * only asks whether the baseline's exact contiguous clones are covered, and
     * is blind to the Type-3 and same-file classes a cap on repetition is most
     * likely to break.
     *
     * Counted and surfaced exactly as {@see POSTINGS_CAP} is, through
     * {@see cappedFingerprintCount()} and {@see discardedPostingCount()}. Never
     * silent.
     */
    public const int PER_FILE_CAP = 32;

    /** Width of the token-position half of a packed posting. */
    private const int FILE_SHIFT = 32;

    private const int POSITION_MASK = 0xFFFFFFFF;

    /** @var array<string, list<int>> fingerprint (8 raw bytes) => packed postings */
    private array $postings = [];

    /** @var array<string, int> fingerprint => how many times it really occurred, capped or not */
    private array $occurrences = [];

    /**
     * Index one file's selected fingerprints.
     *
     * @param list<array{0: string, 1: int}> $selected fingerprint, token position
     */
    public function add(int $fileId, array $selected): void
    {
        $base = $fileId << self::FILE_SHIFT;

        // This file's running count per fingerprint. Selected fingerprints arrive
        // in position order, so the occurrences kept are the file's first ones,
        // which makes the choice a function of the file's bytes and nothing else.
        $withinFile = [];

        foreach ($selected as [$fingerprint, $position]) {
            $this->occurrences[$fingerprint] = ($this->occurrences[$fingerprint] ?? 0) + 1;
            $withinFile[$fingerprint]        = ($withinFile[$fingerprint] ?? 0) + 1;

            if ($withinFile[$fingerprint] > self::PER_FILE_CAP) {
                continue;
            }

            $list = $this->postings[$fingerprint] ?? [];

            if (count($list) >= self::POSTINGS_CAP) {
                continue;
            }

            $list[]                       = $base | $position;
            $this->postings[$fingerprint] = $list;
        }
    }

    /**
     * Fingerprints selected in more than one place, with their postings.
     *
     * A fingerprint seen once seeds nothing — there is no pair to extend — and
     * dropping those here keeps Stage C's loop over candidates rather than over
     * the corpus, which is the whole architectural bet.
     *
     * @return array<string, list<int>>
     */
    public function sharedPostings(): array
    {
        $shared = [];

        foreach ($this->postings as $fingerprint => $list) {
            if (count($list) > 1) {
                $shared[$fingerprint] = $list;
            }
        }

        return $shared;
    }

    public static function fileOf(int $posting): int
    {
        return $posting >> self::FILE_SHIFT;
    }

    public static function positionOf(int $posting): int
    {
        return $posting & self::POSITION_MASK;
    }

    /** How many distinct fingerprints lost occurrences to either cap. */
    public function cappedFingerprintCount(): int
    {
        $capped = 0;

        foreach ($this->occurrences as $fingerprint => $trueCount) {
            if ($trueCount > count($this->postings[$fingerprint] ?? [])) {
                $capped++;
            }
        }

        return $capped;
    }

    /**
     * How many occurrences the caps discarded, in total.
     *
     * Measured against what was actually kept rather than against the constant,
     * so the tally stays truthful however the discarding happened.
     */
    public function discardedPostingCount(): int
    {
        $discarded = 0;

        foreach ($this->occurrences as $fingerprint => $trueCount) {
            $discarded += $trueCount - count($this->postings[$fingerprint] ?? []);
        }

        return $discarded;
    }

    public function fingerprintCount(): int
    {
        return count($this->postings);
    }
}
