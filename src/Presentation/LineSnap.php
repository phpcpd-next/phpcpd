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

namespace LucianoPereira\PhpcpdNext\Presentation;

use function count;

use LucianoPereira\PhpcpdNext\Facts\FileFacts;

/**
 * A matched token run, reported as whole source lines.
 *
 * A match is made in tokens and printed in lines, and the two do not agree at
 * the edges: a run routinely begins partway through a line and ends partway
 * through another. The report prints `Foo.php:10-20` either way, so the reader
 * is *shown* the whole of lines 10 and 20 while the tool has only *matched*
 * part of them. Measured at the shipped default, a boundary falls mid-line on
 * 61% of php-parser's sites, 34% of symfony-string's and 13% of
 * symfony-console's; only 6–20% are already whole-line at both ends.
 *
 * So the reported extent is pulled **inward** to the lines it wholly covers.
 * Inward rather than outward for two reasons. It never claims a token that did
 * not match, which outward would; and it is the direction that separates two
 * runs abutting on one line — the residue in NEXT-TASKS item 2, where a
 * token-disjoint pair shares the single line their boundary sits on, and which
 * is the whole of that residue on php-parser, symfony-console and
 * symfony-string. Snapping both edges outward would leave both runs claiming
 * that line; inward sends one to the line above and the other to the line
 * below.
 *
 * **Reporting only.** The gate stays on the run the engine matched: a clone
 * that clears `--min-tokens` is still a clone after the snap drops a few tokens
 * from its edges, and re-gating would lose borderline findings to a
 * presentation decision. Nothing here reaches the detector, the cache, or the
 * exit code.
 *
 * ## Why both copies usually move together
 *
 * The two occurrences are snapped independently, against their own files. That
 * could in principle pull them out of correspondence — and measured on aligned
 * pairs it mostly does not: both sides drop *exactly the same* number of tokens
 * at the head in 87–96% of pairs and at the tail in 94–100%. Copies are copies,
 * so they are usually laid out alike.
 *
 * Where they are not, one side lands on whole lines and the other is pulled in
 * a little further than its partner. That is the weaker invariant this project
 * already holds: {@see \LucianoPereira\PhpcpdNext\CodeCloneFile} records that
 * copies "do not have to agree" on how many source lines they span, because the
 * matchers compare significant tokens and the lines between them are free.
 */
final readonly class LineSnap
{
    /**
     * The whole-line span an occurrence covers, given its token range.
     *
     * Null when the run holds no whole line at all — a match that begins and
     * ends inside a single line, or one whose every token shares a line with a
     * token outside it. There is nothing to snap to there, and the caller keeps
     * the range the engine reported.
     *
     * @param array{0: int, 1: int} $occurrence [first token index, token count]
     * @return ?array{0: int, 1: int} [first line, last line]
     */
    public static function of(FileFacts $facts, array $occurrence): ?array
    {
        $lines = $facts->tokenLines;
        $total = count($lines);
        [$first, $tokens] = $occurrence;
        $last = $first + $tokens - 1;

        if ($tokens < 1 || $first < 0 || $last >= $total) {
            return null;
        }

        // The head shares its line with a token before the match: that line is
        // only partly ours, so the span starts at the next one.
        if ($first > 0 && $lines[$first - 1] === $lines[$first]) {
            $line = $lines[$first];

            while ($first <= $last && $lines[$first] === $line) {
                ++$first;
            }
        }

        // The tail, the same way, from the other end.
        if ($last + 1 < $total && $lines[$last + 1] === $lines[$last]) {
            $line = $lines[$last];

            while ($last >= $first && $lines[$last] === $line) {
                --$last;
            }
        }

        if ($first > $last) {
            return null;
        }

        return [$lines[$first], $lines[$last]];
    }
}
