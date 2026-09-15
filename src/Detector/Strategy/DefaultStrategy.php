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

use function array_values;
use function count;
use function file_get_contents;
use function is_array;
use function ord;
use function substr;
use function substr_count;
use function token_get_all;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;

/**
 * Exact duplication, found by hashing every window of tokens.
 *
 * Each file becomes a signature — one fixed-width hash per significant token —
 * and every window of `minTokens` consecutive tokens is keyed into a table that
 * outlives the file. A window already in the table is a window seen before, so
 * the run of such windows is a duplicated fragment and the table says where its
 * first copy was.
 *
 * Two passes rather than one, because they are wanted separately: `tokenize()`
 * is pure and depends only on a buffer and the configuration, which is what
 * lets the incremental index cache its result and re-scan only the files that
 * changed; `scan()` carries the cross-file table and must see every file.
 */
final class DefaultStrategy extends AbstractStrategy
{
    /**
     * Window hash => the first place that window was seen.
     *
     * @var array<string, array{0: string, 1: int, 2: int}> hash => [file, line, token]
     */
    private array $hashes = [];

    /**
     * Re-read tokenizations, for files that turn out to hold a clone.
     *
     * The earlier occurrence of a match is in a file this class finished with
     * long ago, and both questions asked about it need that file's tokens: how
     * far it reaches needs the token-to-line map, and whether the hash entry
     * describes this run at all needs the signature. Retaining every file's
     * would cost roughly what the hash table does; re-reading one costs a
     * tokenize, and only files that actually appear in a finding are ever
     * re-read.
     *
     * @var array<string, FileTokens>
     */
    private array $reread = [];

    #[\Override]
    public function processFile(string $file, CodeCloneMap $result): void
    {
        $buffer = file_get_contents($file);

        if ($buffer === false) {
            $result->couldNotRead($file);

            return;
        }

        $this->scan($file, $this->tokenize($buffer), $result);
    }

    /**
     * A source buffer as the scanner wants it: a signature, and two line numbers
     * per token.
     *
     * Pure — it reads the buffer and the configuration and nothing else — which
     * is the property the incremental index depends on when it stores the result
     * and replays it for a file that has not changed.
     *
     * The two line numbers answer different questions and both are needed.
     * `tokenLines` accumulates only the distance between tokens the matcher can
     * see, so it measures how many lines of *code* a run occupies;
     * `tokenRealLines` is where each token actually is, which is what a report
     * has to print. A clone spanning a long comment differs sharply in the two.
     */
    public function tokenize(string $buffer): FileTokens
    {
        $signature = '';
        $codeLines = [];
        $realLines = [];
        $at        = 0;
        $previous  = 0;
        // The line the next token begins on, tracked because a single-character
        // token carries no line of its own.
        $cursor    = 1;

        foreach (token_get_all($buffer) as $token) {
            // A single-character token — `;`, `{`, and every operator — comes
            // back from `token_get_all()` as a bare string with no line number,
            // and used to be dropped for want of one. That is 47 % of the
            // program text, and it is the half that says what the code *does*:
            // with `+`, `-`, `*` and `.` all invisible, `$x = $a + $b;` and
            // `$x = $a - $b;` had identical signatures, and two files differing
            // on every operator were reported as an exact copy of one another.
            //
            // It takes the line the cursor is on. An earlier version gave it
            // the line of the *preceding token*, on the reasoning that a newline
            // only ever appears inside a whitespace or comment token and both of
            // those are arrays. That is true and it is not enough:
            // `token_get_all()` dates a token by where it **begins**, so the
            // whitespace holding a newline is dated to the line it starts on,
            // and every single-character token that opens a line inherited the
            // line above it. A `}` on its own line was reported one line early,
            // and so was any clone starting at one.
            //
            // The cursor is therefore advanced past the newlines a token
            // contains rather than to the line it began on, which is the only
            // way to date a token that carries no line of its own.
            //
            // Its type is the character's own ordinal, which cannot collide
            // with a `T_` constant: those start at 256.
            if (is_array($token)) {
                $line   = $token[2];
                $cursor = $line + substr_count($token[1], "\n");
            } else {
                $token = [ord($token), $token, $cursor];
                $line  = $cursor;
            }

            // A qualified name is the name it ends in.
            //
            // IGNORED_TOKENS drops T_NS_SEPARATOR so that where a name comes
            // from does not decide whether two files match. On PHP 8 that entry
            // is dead — 0 occurrences in 196,795 tokens of php-parser, 0 in this
            // project's own src — because the separator is no longer a token of
            // its own: `\App\Support\Money` arrives whole, as one
            // T_NAME_FULLY_QUALIFIED, and the qualifier rides along inside it.
            // So `\App\Support\Money::of($x)` did not match `Money::of($x)`
            // in the raw view, and the intent had quietly stopped applying to
            // most of modern PHP.
            //
            // This is the same oversight {@see TokenNormalizer} already fixed
            // for the normalized view, with the same reasoning recorded there;
            // the raw view simply never got it. Folding to the last segment,
            // typed as the plain identifier it would have been, is what the
            // ignore set was already asserting.
            if (isset(self::QUALIFIED_NAMES[$token[0]])) {
                $cut      = strrpos($token[1], '\\');
                $token[1] = $cut === false ? $token[1] : substr($token[1], $cut + 1);
                $token[0] = T_STRING;
            }

            if (!isset(self::IGNORED_TOKENS[$token[0]])) {
                $codeLines[$at] = ($at === 0 ? 0 : $codeLines[$at - 1]) + $line - $previous;
                $realLines[$at] = $line;
                ++$at;

                if ($this->config->normalization->folds()) {
                    $token[1] = $this->normalizer->normalize($token[0], $token[1]);
                }

                // Eight bytes: one hash over the token's type and its text
                // together. It was five — the type truncated to a byte, the text
                // through crc32 — and 32 bits is narrow enough that two
                // different literals in WordPress hashed alike, so the matcher
                // called the files holding them an exact copy of one another.
                $signature .= FileTokens::token($token[0], $token[1]);
            }

            // Advanced for every token, seen or ignored, so that the distance
            // added above is measured from the last thing in the file rather
            // than from the last thing the matcher kept.
            $previous = $line;
        }

        return new FileTokens(
            self::countLines($buffer),
            $signature,
            array_values($codeLines),
            array_values($realLines),
        );
    }

    /**
     * Slide the window across one file, against the table of every file before it.
     *
     * The table is the state that makes this a detector rather than a hash
     * function: a window already in it was seen somewhere earlier, and a *run*
     * of consecutive such windows is one duplicated fragment rather than
     * `minTokens` overlapping ones. So a run is opened at the first window that
     * matches and closed by the first that does not, and only windows that
     * closed no run are added to the table — the first occurrence of a fragment
     * is the one later occurrences resolve back to.
     */
    public function scan(string $file, FileTokens $tokens, CodeCloneMap $result): void
    {
        $result->addToNumberOfLines($tokens->numberOfLines);

        $count = count($tokens->tokenLines);
        $keys  = RollingWindow::keys($tokens->signature, $count, $this->config->minTokens);
        $last  = $count - $this->config->minTokens;
        $run   = null;
        // The file the run being built matches. A run is a contiguous match
        // against **one** earlier occurrence, and the table does not promise
        // that: consecutive windows can have been registered by different
        // files, and stitching them together claims a match against whichever
        // file happened to register the first one.
        $owner = null;

        for ($at = 0; $at <= $last; ++$at) {
            $hash = $keys[$at];

            if (isset($this->hashes[$hash])) {
                $registrant = $this->hashes[$hash][0];

                // The registrant changed, so the run so far and the window here
                // are matches against two different files. Close the one that
                // ended rather than absorbing this window into it.
                if ($run !== null && $owner !== $registrant) {
                    $this->record($result, $file, $tokens, $run, $at);

                    $run = null;
                }

                // A run continues, or one begins here.
                if ($run === null) {
                    $owner = $registrant;
                    $run   = new MatchedRun($hash, $tokens->tokenLines[$at], $tokens->tokenRealLines[$at], $at);
                }

                continue;
            }

            if ($run !== null) {
                $this->record($result, $file, $tokens, $run, $at);

                $run = null;
            }

            $this->hashes[$hash] = [$file, $tokens->tokenRealLines[$at], $at];
        }

        // A run reaching the end of the file is closed by the file ending.
        if ($run !== null) {
            $this->record($result, $file, $tokens, $run, $last + 1);
        }
    }

    /**
     * Record the fragment a closed run describes, if it is long enough to report.
     *
     * `$endedAt` is the window that closed the run, so the run's last window
     * began one before it and the last token of that window is `minTokens - 1`
     * further on.
     */
    private function record(
        CodeCloneMap $result,
        string $file,
        FileTokens $tokens,
        MatchedRun $run,
        int $endedAt,
    ): void {
        [$firstFile, $firstLine, $firstToken] = $this->hashes[$run->hash];

        $lastToken = $endedAt + $this->config->minTokens - 2;

        // Two copies inside one file must not grow into each other: their total
        // length can be at most the distance between them, or the "clone" is one
        // stretch of code reported as a duplicate of itself.
        //
        // Normalization is what makes this reachable. A run of near-identical
        // members — eight one-line accessors, a flat list of constants — becomes
        // one repeating token sequence once the names and string keys are
        // normalized away, and a repeating sequence matches itself shifted by a
        // period. Unclamped, php-parser reported `NodeAbstract.php:15-96` as a
        // clone of `25-107`: 72 of those lines are the same lines, and the
        // finding asserted at +1.72. Ten of that corpus's 35 findings were this.
        //
        // Truncating rather than dropping is deliberate. The repetition among
        // those accessors is real duplication and worth reporting; what is false
        // is the extent. One period is the honest extent, and it is what
        // {@see AnchorSet::extend()} has always reported for the same reason.
        if ($firstFile === $file) {
            $distance = abs($run->token - $firstToken);

            if ($distance === 0) {
                return;
            }

            $lastToken = min($lastToken, $run->token + $distance - 1);

            if ($lastToken + 1 - $run->token < $this->config->minTokens) {
                return;
            }
        }

        // Two measures of one span, and they answer different questions.
        //
        // `$codeLines` counts the lines the matched *code* occupies, which is
        // what `--min-lines` is a minimum of — "identical lines", where a
        // comment between two matched tokens is not one. `$realLines` is the
        // span in the file, which is what a reader needs to find the clone and
        // what every report prints.
        //
        // They were measured against each other before this was written down:
        // at the default seventy tokens the two gates admit exactly the same
        // clones on php-parser, firefly-iii and symfony-console, because a
        // seventy-token run clears five lines whichever way it is counted.
        $codeLines = $tokens->tokenLines[$lastToken] + 1 - $run->line;
        $realLines = $tokens->tokenRealLines[$lastToken] + 1 - $run->realLine;

        if ($codeLines < $this->config->minLines) {
            return;
        }

        // A fragment matching itself is not a clone. Two occurrences in one file
        // are, as long as they are two places.
        if ($firstFile === $file && $firstLine === $run->realLine) {
            return;
        }

        // Neither occurrence is measured, and both say so.
        //
        // This one's extent is known — it is `$realLines`, taken from this
        // file's own tokens — but recording it while the earlier one stayed null
        // would make the report depend on which file was scanned first: reverse
        // the file list and the same clone serialises differently. The pair is
        // symmetric, so it is described symmetrically, and the clone's length —
        // which is that same number — carries both.
        //
        // The earlier occurrence is the one that cannot be measured. Its end
        // line is not recoverable here. The obvious trick — read it off the
        // run's last window, which this class does record — was implemented and
        // then measured, and it is wrong: the entry for that window belongs to
        // whichever occurrence registered the hash first, which in a file of
        // repeated blocks is routinely a later, unrelated one. On PHPUnit's
        // MetadataTest.php it produced a 5,647-line extent for a 31-line clone.
        // Nothing cheap distinguishes the good reading from that one, because
        // the earlier occurrence's token-to-line mapping is not retained past
        // its own file, and retaining every file's would cost roughly what the
        // hash table already costs.
        //
        // So null, and the clone's length stands in — exactly what every site
        // got before. Wrong extents are worse than borrowed ones: a borrowed one
        // is at least the length of a real occurrence of this clone.
        // Both occurrences carry where they begin in tokens, how many tokens
        // they span, and how many lines — both of them or neither.
        //
        // Symmetry is the constraint. Measuring this occurrence and leaving the
        // earlier one to borrow this one's length is what made a reported
        // extent differ from what was matched: copies need not span equally
        // many lines, because comments and blank lines between them are not
        // significant tokens, and the borrowed figure was wrong by up to 293
        // lines on WordPress. It also made the report depend on which file was
        // scanned first.
        //
        // The earlier occurrence's end *is* recoverable, now that its beginning
        // is recorded: it is the line of the token `$tokenCount - 1` further
        // on, in its own file's map, which {@see realLinesOf()} re-reads once
        // per file that holds a clone.
        $tokenCount  = $lastToken + 1 - $run->token;
        $firstTokens = $this->tokensOf($firstFile, $file, $tokens);
        $firstLines  = $firstTokens->tokenRealLines;

        // The hash entry may not describe this run's partner at all. It belongs
        // to whichever occurrence registered the window first, and in a file of
        // repeated blocks that is routinely a later, unrelated one — the hazard
        // the extent comment above describes.
        //
        // Normalization makes it reachable, because it is what turns a table's
        // rows into windows that hash alike: measured at --min-tokens=30, raw
        // matching produces no such site on php-parser or symfony-console and
        // normalized matching produces one in 1,719 and two in 2,872.
        //
        // The cheap half of the check is free here, because the first file's
        // line map has just been read: a run of this length starting there must
        // fit inside that file. When it does not, the entry provably describes a
        // different occurrence and its token anchor is not this run's.
        //
        // What is wrong is the anchor, not the length. In the strata fixture the
        // entry points at token 49 where the matching rows begin at 37 — one
        // table row late — so the run overruns the file by three tokens, and the
        // frame check then asks whether tokens past the end of the file sit
        // inside an array literal, answers no, and the pair loses the `table`
        // demotion the fixture exists to demonstrate.
        //
        // Dropping the finding would cost a real duplication; the two tables do
        // share thirteen rows. So only the anchor is withheld, and the occurrence
        // falls back to the line-derived span {@see FileFacts::occurrence()} has
        // always kept for strategies that record no token position. Coarser, and
        // true.
        // So the entry is checked against the run it claims to describe. A
        // Rabin-Karp match is an equality of token sequences — that is what the
        // window hashes assert — so the two spans must hold the same tokens,
        // and comparing them is one memcmp over a flat signature.
        //
        // The length check is kept as the cheap half: a run that does not fit
        // inside the first file cannot be compared at all, and reading past the
        // end would compare a short string against a long one rather than
        // saying why.
        //
        // Measured before this existed: 4 of php-parser's two-site findings and
        // 1 of symfony-string's 28 named a first occurrence whose tokens agreed
        // with the run at 0.06 where an exact match agrees at 1.00. The extent
        // printed for those was a different piece of code's.
        $anchored = $firstToken + $tokenCount <= count($firstLines)
            && self::sameSpan(
                $firstTokens->signature,
                $firstToken,
                $tokens->signature,
                $run->token,
                $tokenCount,
            );

        $firstEnd = $anchored ? ($firstLines[$firstToken + $tokenCount - 1] ?? null) : null;

        $result->add(
            new CodeClone(
                new CodeCloneFile(
                    $firstFile,
                    $firstLine,
                    $firstEnd === null ? null : $firstEnd + 1 - $firstLine,
                    $tokenCount,
                    $anchored ? $firstToken : null,
                ),
                new CodeCloneFile($file, $run->realLine, $realLines, $tokenCount, $run->token),
                $realLines,
                $tokenCount,
            ),
        );
    }

    /**
     * One file's tokenization, read once and kept.
     *
     * The file being scanned already has its own in hand, so the common case of
     * a clone inside one file costs nothing.
     */
    private function tokensOf(string $wanted, string $current, FileTokens $tokens): FileTokens
    {
        if ($wanted === $current) {
            return $tokens;
        }

        return $this->reread[$wanted] ??= $this->tokenize(
            (string) file_get_contents($wanted),
        );
    }

    /**
     * Do two spans hold the same tokens?
     *
     * A signature is a flat run of fixed-width opaque blobs, so this is one
     * comparison over `$count` of them rather than a loop: the same reason
     * every other consumer slices rather than reads inside a token.
     */
    private static function sameSpan(
        string $a,
        int $firstA,
        string $b,
        int $firstB,
        int $count,
    ): bool {
        $width = $count * FileTokens::TOKEN_BYTES;

        return substr($a, $firstA * FileTokens::TOKEN_BYTES, $width)
            === substr($b, $firstB * FileTokens::TOKEN_BYTES, $width);
    }
}
