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

namespace LucianoPereira\PhpcpdNext;

use function max;

/**
 * One occurrence of a clone: the file it sits in and the line it starts on.
 *
 * The `id` is that pair as a string, and it is what makes a clone a *set* rather
 * than a list — {@see CodeCloneMap} keys occurrences by it so the same fragment
 * found twice by two strategies is stored once. Derived in the constructor so it
 * cannot disagree with the values it summarises.
 */
final readonly class CodeCloneFile
{
    /** `name:startLine` — the occurrence's identity, used for de-duplication. */
    public string $id;

    /**
     * @param ?int $numberOfLines how many source lines *this* occurrence spans,
     *        where the strategy knows it, and null where it does not.
     *
     *        A clone class carries one length, measured on the site it was led
     *        by, and every other site used to be reported and counted at that
     *        length. Copies do not have to agree on it: the matchers compare
     *        significant tokens, so two token-identical occurrences separated by
     *        a different number of comment or blank lines legitimately span
     *        different numbers of source lines. Since duplicated lines became
     *        the union of the lines clones cover, that length is not a label any
     *        more — it decides which lines each occurrence is recorded as
     *        covering, so a borrowed one mis-attributes real source.
     *
     *        Null is honest rather than convenient: it means this strategy did
     *        not measure the occurrence, and the reader should fall back to the
     *        clone's own length as before.
     * @param ?int $numberOfTokens how many significant tokens *this* occurrence
     *        spans, on the same terms and for the same reason.
     *
     *        Lines were given their own field first because they decide which
     *        source each occurrence is recorded as covering. Tokens are the
     *        other half of the same omission: a class merged from several
     *        near-identical files is sized by its lead, so the one token count
     *        it carries understates every longer member, and anything asking
     *        "how much do *these two* sites share" reads a number that answers
     *        a different question. `bench/check-superset.php` asked exactly
     *        that and reported seven unexplained disagreements on phpunit for
     *        no better reason.
     */
    public function __construct(
        public string $name,
        public int $startLine,
        public ?int $numberOfLines = null,
        public ?int $numberOfTokens = null,
        /**
         * Where this occurrence begins in the file's significant tokens, when
         * the strategy that found it knows.
         *
         * A match is made in tokens and reported in lines, and the two do not
         * round-trip: a clone that begins partway through a line is reported as
         * beginning *on* that line, and deriving the token range back from the
         * line range then hands out every token the line holds — including the
         * ones before the match. Three tokens, in the case that found this, and
         * enough to put a whole statement inside a span that does not contain
         * it.
         */
        public ?int $startToken = null,
    ) {
        $this->id = $name . ':' . $startLine;
    }

    /**
     * The last line this occurrence covers, given the clone it belongs to.
     *
     * Falls back to the clone's length where the occurrence was not measured,
     * which is what every caller did before occurrences carried their own.
     */
    public function lastLine(int $cloneNumberOfLines): int
    {
        return $this->startLine + max(1, $this->lines($cloneNumberOfLines)) - 1;
    }

    /**
     * How far this occurrence reaches — its own measurement where the strategy
     * took one, the clone's where it did not.
     *
     * The lines counterpart of {@see tokens()}, which already said why: the
     * rule belongs in one place per quantity, and this one was inlined in
     * {@see lastLine()} instead of stated.
     */
    public function lines(int $cloneNumberOfLines): int
    {
        return $this->numberOfLines ?? $cloneNumberOfLines;
    }

    /**
     * How many significant tokens this occurrence spans, given the clone it
     * belongs to.
     *
     * The same fallback as {@see lastLine()}, and stated here for the same
     * reason: the rule "this occurrence's own measurement, or the clone's where
     * the strategy did not take one" belongs in one place per quantity. Every
     * caller that spelled it out instead got it wrong the moment occurrences
     * started carrying their own — which is how `bench/check-superset.php` came
     * to read a class's lead length as though it were each member's.
     */
    public function tokens(int $cloneNumberOfTokens): int
    {
        return $this->numberOfTokens ?? $cloneNumberOfTokens;
    }
}
