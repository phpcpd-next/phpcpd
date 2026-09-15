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

namespace LucianoPereira\PhpcpdNext\Util;

use function array_key_exists;
use function file_get_contents;
use function is_array;
use function substr_count;
use function token_get_all;

use const T_CLOSE_TAG;
use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_INLINE_HTML;
use const T_NS_SEPARATOR;
use const T_OPEN_TAG;
use const T_OPEN_TAG_WITH_ECHO;
use const T_USE;
use const T_WHITESPACE;

/**
 * Which physical lines of a file carry a token the matchers can see.
 *
 * A clone's extent is a token run, but it is *reported* as a line range, and a
 * range runs from the first matched token to the last — so every docblock and
 * blank line between them is swept up. Counting those as duplicated says a file
 * shares material that was never compared, and on
 * `php-parser/lib/PhpParser/Builder/Method.php:20-80` it says it of 40 lines in
 * 61, whose text is not even the same in the copy it is charged against:
 * "Makes the **method** public" there, "Makes the **property** public" here,
 * and four `@var` lines that exist in one and not the other.
 *
 * So the coverage total counts the lines that hold something the engines
 * actually matched on, and nothing else. It moves the headline figure by a
 * fifth to a third — php-parser 8,840 reported lines against 6,233 carrying a
 * token, symfony-console 31,257 against 24,838 — in both directions from the
 * same cause, and the Rabin-Karp default overcounts by more than the unified
 * engine, not less.
 *
 * The extents themselves are untouched: a clone still begins and ends where the
 * matcher put it, and the excerpt a reader sees is still contiguous source. The
 * only thing that changes is what gets added up.
 */
final class CodeLines
{
    /**
     * The token types that carry no program.
     *
     * A constant, because it is the same set for every strategy and for every
     * instance of one, and nothing has ever needed to change it at runtime.
     *
     * This particular nine came from PHP Copy/Paste Detector, and is
     * acknowledged rather than licensed: which of PHP's token types carry no
     * program is a fact about the language, not a way of writing one, and a
     * fact is not somebody's to license. It is recorded here because knowing
     * where a decision came from is worth more than the decision looking
     * inevitable.
     *
     * It lives here rather than beside the matcher because two questions now
     * ask it — what to match on, and what to count — and one set answering both
     * is the only way they cannot drift apart.
     *
     * @var array<int, true>
     */
    public const array IGNORED_TOKENS = [
        T_INLINE_HTML        => true,
        T_COMMENT            => true,
        T_DOC_COMMENT        => true,
        T_OPEN_TAG           => true,
        T_OPEN_TAG_WITH_ECHO => true,
        T_CLOSE_TAG          => true,
        T_WHITESPACE         => true,
        T_USE                => true,
        T_NS_SEPARATOR       => true,
    ];

    /**
     * Line number => true, for every line holding at least one such token.
     *
     * @var array<string, array<int, true>>
     */
    private array $lines = [];

    /**
     * Does this line of this file hold anything the matchers can see?
     *
     * A file that cannot be read answers no for every line, which charges it no
     * duplication. That is the same direction {@see \LucianoPereira\PhpcpdNext\Facts\FileFacts::read()}
     * takes for the same reason: a file nobody can open is evidence of nothing.
     */
    public function isCode(string $file, int $line): bool
    {
        if (!array_key_exists($file, $this->lines)) {
            $this->lines[$file] = self::scan($file);
        }

        return isset($this->lines[$file][$line]);
    }

    /** @return array<int, true> */
    private static function scan(string $file): array
    {
        $source = @file_get_contents($file);

        if ($source === false) {
            return [];
        }

        $lines = [];
        $line  = 1;

        foreach (token_get_all($source) as $token) {
            if (!is_array($token)) {
                // A single-character token carries no line of its own; it sits
                // on the line the last multi-character token ended on.
                $lines[$line] = true;

                continue;
            }

            if (!isset(self::IGNORED_TOKENS[$token[0]])) {
                $lines[$token[2]] = true;
            }

            $line = $token[2] + substr_count($token[1], "\n");
        }

        return $lines;
    }
}
