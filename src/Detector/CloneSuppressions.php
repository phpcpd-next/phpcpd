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

namespace LucianoPereira\PhpcpdNext\Detector;

use function count;
use function file_get_contents;
use function is_string;
use function ltrim;
use function str_starts_with;
use function substr_count;
use function token_get_all;
use function trim;

use const PHP_INT_MAX;
use const T_COMMENT;
use const T_DOC_COMMENT;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Util\TokenCursor;

/**
 * Line ranges an author has declared deliberately duplicated.
 *
 * Some duplication is correct design. A visitor dispatch table — one `match` arm
 * per node type, repeated once per renderer — is parallel on purpose, and folding
 * it into a lookup array costs both type safety and the compile-visible default
 * arm that catches an unhandled case. Without a way to say so, the only remedy is
 * `--exclude` on the whole file, which also hides the duplication worth fixing.
 *
 * Three notations, because a clone is a RANGE and frequently corresponds to no
 * single declaration:
 *
 *   // phpcpd-ignore-start ... // phpcpd-ignore-end   an explicit region
 *   \@phpcpd-ignore-clone <reason>                     the declaration that follows
 *   // phpcpd-ignore-line                             one line
 */
final class CloneSuppressions
{
    private const string REGION_START = 'phpcpd-ignore-start';
    private const string REGION_END   = 'phpcpd-ignore-end';
    private const string SINGLE_LINE  = 'phpcpd-ignore-line';
    private const string DECLARATION  = '@phpcpd-ignore-clone';

    /** @param array<string, list<array{0: int, 1: int}>> $ranges file => [firstLine, lastLine] */
    private function __construct(private readonly array $ranges) {}

    /**
     * Markers are read only from files that actually took part in a clone —
     * usually a handful — so an unmarked codebase pays nothing for the feature.
     *
     * @param list<string> $files
     */
    public static function forFiles(array $files): self
    {
        $ranges = [];

        foreach ($files as $file) {
            $found = self::scan($file);

            if ($found !== []) {
                $ranges[$file] = $found;
            }
        }

        return new self($ranges);
    }

    public function isEmpty(): bool
    {
        return $this->ranges === [];
    }

    /**
     * A clone is dropped when ANY of its copies sits in a suppressed range:
     * marking one side is a statement about the duplication itself, not about
     * one participant.
     */
    public function suppresses(CodeClone $clone): bool
    {
        foreach ($clone->files() as $file) {
            $first = $file->startLine();
            $last  = $first + $clone->numberOfLines() - 1;

            foreach ($this->ranges[$file->name()] ?? [] as [$from, $to]) {
                if ($first <= $to && $last >= $from) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Rebuild the map without the suppressed clones. The totals are derived in
     * CodeCloneMap::add(), so a filtered map has to be built by adding the
     * survivors rather than by removing from the original.
     */
    public function filter(CodeCloneMap $map): CodeCloneMap
    {
        if ($this->isEmpty()) {
            return $map;
        }

        $filtered = new CodeCloneMap();
        $filtered->addToNumberOfLines($map->numberOfLines());

        foreach ($map->clones() as $clone) {
            if (!$this->suppresses($clone)) {
                $filtered->add($clone);
            }
        }

        return $filtered;
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private static function scan(string $file): array
    {
        $buffer = file_get_contents($file);

        if ($buffer === false) {
            return [];
        }

        $tokens = token_get_all($buffer);
        $count  = count($tokens);
        $ranges = [];
        $open   = null;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_string($token) || ($token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT)) {
                continue;
            }

            $text = self::marker($token[1]);
            $line = $token[2];

            if (str_starts_with($text, self::REGION_START)) {
                $open ??= $line;
            } elseif (str_starts_with($text, self::REGION_END)) {
                $ranges[] = [$open ?? $line, $line];
                $open     = null;
            } elseif (str_starts_with($text, self::SINGLE_LINE)) {
                $ranges[] = [$line, $line];
            } elseif (str_starts_with($text, self::DECLARATION)) {
                $ranges[] = [$line, self::declarationEnd($tokens, $i + 1, $line)];
            }
        }

        if ($open !== null) {
            $ranges[] = [$open, PHP_INT_MAX];
        }

        return $ranges;
    }

    /**
     * The last line of the declaration following $from, found by matching its
     * body braces. A marker on a declaration with no body covers its own line.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function declarationEnd(array $tokens, int $from, int $fallback): int
    {
        $line = $fallback;

        return TokenCursor::untilBalanced(
            $tokens,
            $from,
            '{',
            '}',
            static function (array|string $token, int $depth) use (&$line): ?int {
                if (!is_string($token)) {
                    // A token's line is where it STARTS; a multi-line one
                    // (whitespace, a heredoc) would end the body too early.
                    $line = $token[2] + substr_count($token[1], "\n");

                    return null;
                }

                return $token === ';' && $depth === 0 ? $line : null;
            },
        ) ?? $line;
    }

    /** Strip a comment's delimiters so a marker can be matched at its start. */
    private static function marker(string $comment): string
    {
        return trim(ltrim(trim($comment), "/*# \t"));
    }
}
