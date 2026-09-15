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

use function basename;
use function count;
use function dirname;
use function in_array;
use function is_string;
use function min;
use function preg_match;
use function realpath;
use function strrpos;
use function substr;
use function trim;

use const T_CLASS;
use const T_COMMENT;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_DIR;
use const T_DOC_COMMENT;
use const T_DOUBLE_COLON;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAME_RELATIVE;
use const T_NEW;
use const T_STRING;
use const T_VARIABLE;
use const T_WHITESPACE;

/**
 * Recognises the framework idioms that CONSTRUCT a class name rather than
 * writing it.
 *
 * They defeat reference detection for one reason. The name never appears as a
 * name anywhere — it is assembled at runtime from a filename, or from a base
 * class plus a suffix — so a scan that looks for mentions of the name correctly
 * finds none and incorrectly concludes the class is dead. On the audit that
 * motivated this file, the constructed-name family accounted for 64 of 94
 * reported dead symbols.
 *
 * The answer is not to widen reference detection, which would cost precision
 * everywhere. It is to recognise the *wiring* — a loop that reads a directory, a
 * trait that appends a suffix — and cite it as evidence, so the reader can see
 * why a symbol was spared and disagree with a specific file and line.
 *
 * Everything here is token-level, over a `token_get_all` stream the caller has
 * already built. No parser, no reflection, no filesystem beyond resolving the
 * directory a glob names.
 */
final class IdiomScanner
{
    /**
     * Calls that read a directory listing. Matched on the short name, so
     * `\DirectoryIterator` and an imported `DirectoryIterator` are the same
     * signal.
     *
     * @var list<string>
     */
    private const array DIRECTORY_READERS = [
        'glob', 'scandir',
        'DirectoryIterator', 'RecursiveDirectoryIterator', 'FilesystemIterator',
    ];

    /** The guard that turns a derived name into a conditional instantiation. */
    private const string EXISTENCE_GUARD = 'class_exists';

    /**
     * How far past a `static::class` the concatenated suffix may sit. The idiom
     * is one expression — `static::class . 'Translation'`, or the same with a
     * `config()` lookup in the middle — so a window is enough, and a window is
     * what keeps an unrelated concatenation forty lines later from being read as
     * a convention.
     */
    private const int CONCATENATION_WINDOW = 48;

    /** A suffix must be a legal class-name segment; anything else is not one. */
    private const string SUFFIX_SHAPE = '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/';

    /**
     * The directory-discovery idiom, or null when this file does not exhibit it.
     *
     * A base class globs a directory, derives a fully-qualified name from each
     * filename, checks `class_exists` and instantiates the result. Nothing names
     * the discovered classes, so every one of them reads as dead — and deleting
     * one silently removes a live endpoint. Measured: 52 classes in a single
     * directory on the audited project.
     *
     * ALL THREE signals are required, and each alone is ordinary code:
     *
     *   1. a directory read — `glob` / `scandir` / a `DirectoryIterator` — whose
     *      argument is anchored at `__DIR__`;
     *   2. an instantiation through a variable — `new $class` or `$class::`;
     *   3. a `class_exists(` guard.
     *
     * **Boundary — the anchor is load-bearing.** A glob over a path that is not
     * built from `__DIR__` (a configured directory, a path argument, an absolute
     * string) is deliberately out of scope: without the anchor there is no way to
     * know from tokens alone which directory on disk the loop reads, so the rule
     * would be suppressing a directory it cannot name. Such a loop is left
     * unrecognised and the classes it discovers stay reported.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @param string                                              $file the file the tokens came from
     * @return ?array{directory: string, pattern: string, at: string}
     */
    public static function discovery(array $tokens, string $file): ?array
    {
        $count     = count($tokens);
        $reader    = null;
        $dynamic   = false;
        $guarded   = false;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                continue;
            }

            [$id, $text] = [$token[0], $token[1]];

            if ($id === T_STRING || $id === T_NAME_QUALIFIED || $id === T_NAME_FULLY_QUALIFIED || $id === T_NAME_RELATIVE) {
                $short = self::shortName($text);
                $open  = self::nextSignificant($tokens, $i + 1);
                $call  = $open !== null && $tokens[$open] === '(';

                if (!$call) {
                    continue;
                }

                if ($short === self::EXISTENCE_GUARD) {
                    $guarded = true;
                } elseif ($reader === null && in_array($short, self::DIRECTORY_READERS, true)) {
                    /** @var int $open */
                    $reader = self::anchoredTarget($tokens, $open, $file, $token[2]);
                }

                continue;
            }

            if ($id === T_NEW) {
                $next    = self::nextSignificant($tokens, $i + 1);
                $dynamic = $dynamic || ($next !== null && !is_string($tokens[$next]) && $tokens[$next][0] === T_VARIABLE);

                continue;
            }

            if ($id === T_VARIABLE) {
                $next    = self::nextSignificant($tokens, $i + 1);
                $dynamic = $dynamic || ($next !== null && !is_string($tokens[$next]) && $tokens[$next][0] === T_DOUBLE_COLON);
            }
        }

        return $reader !== null && $dynamic && $guarded ? $reader : null;
    }

    /**
     * The literal suffix concatenated onto a runtime class name starting at
     * $from, or null when the expression is not that idiom.
     *
     * This is the second constructed-name pattern: a trait resolves a companion
     * class by appending a suffix to its consumer's own name — the translatable
     * idiom, where a model `X` is paired with an `XTranslation`. The companion is
     * named nowhere, so every one of them read as dead (12 on the audited
     * project).
     *
     * Both shapes the field data showed are accepted: a bare literal
     * (`static::class . 'Translation'`) and a `config()` lookup carrying one as
     * its default (`. config('x.suffix', 'Translation')`).
     *
     * **Boundary — the literal is mandatory.** `config('x.suffix')` with no
     * default resolves entirely at runtime, out of a value this tool never sees,
     * so there is no suffix to register and the companion classes stay reported.
     * Registering a guess would mean suppressing by class-name pattern, which is
     * the one thing no rule here does.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    public static function suffixAfter(array $tokens, int $from): ?string
    {
        $limit = min(count($tokens), $from + self::CONCATENATION_WINDOW);

        for ($i = $from; $i < $limit; $i++) {
            $token = $tokens[$i];

            if (!is_string($token)) {
                continue;
            }

            // The expression ended before any concatenation appeared.
            if ($token === ';' || $token === '{') {
                return null;
            }

            if ($token !== '.') {
                continue;
            }

            $next = self::nextSignificant($tokens, $i + 1);

            return $next === null ? null : self::literalSuffix($tokens, $next);
        }

        return null;
    }

    /**
     * Does `static::class` begin at $at? That is the runtime class name the
     * companion-class idiom builds on, and the token trio is unambiguous.
     *
     * `class_basename(static::class)` contains it too, so this one probe covers
     * both shapes the idiom takes.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    public static function readsStaticClass(array $tokens, int $at): bool
    {
        $colon = self::nextSignificant($tokens, $at + 1);

        if ($colon === null || is_string($tokens[$colon]) || $tokens[$colon][0] !== T_DOUBLE_COLON) {
            return false;
        }

        $class = self::nextSignificant($tokens, $colon + 1);

        return $class !== null && !is_string($tokens[$class]) && $tokens[$class][0] === T_CLASS;
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    public static function nextSignificant(array $tokens, int $from): ?int
    {
        $count = count($tokens);

        for ($i = $from; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                return $i;
            }

            if ($token[0] !== T_WHITESPACE && $token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
                return $i;
            }
        }

        return null;
    }

    public static function shortName(string $qualified): string
    {
        $pos = strrpos($qualified, '\\');

        return $pos === false ? $qualified : substr($qualified, $pos + 1);
    }

    /**
     * The directory an `__DIR__`-anchored directory read names, and the filename
     * pattern it selects within it.
     *
     * The pattern is what separates this rule from "suppress the folder". A loop
     * reading `__DIR__ . '/*Handler.php'` reaches exactly the files that match;
     * an unrelated class sitting beside them is untouched by the loop and stays
     * reported, which is the whole difference between a rule that works and a
     * rule that only makes the count go down. A read with no literal at all
     * (`scandir(__DIR__)`) genuinely does reach everything, so its pattern is
     * `*`.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @param int                                                 $open index of the call's `(`
     * @return ?array{directory: string, pattern: string, at: string}
     */
    private static function anchoredTarget(array $tokens, int $open, string $file, int $line): ?array
    {
        $count    = count($tokens);
        $depth    = 0;
        $anchored = false;
        $tail     = '';

        for ($i = $open; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                if ($token === '(') {
                    $depth++;
                } elseif ($token === ')' && --$depth === 0) {
                    break;
                }

                continue;
            }

            if ($token[0] === T_DIR) {
                $anchored = true;
            } elseif ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                // The last literal in the expression carries the pattern; an
                // earlier one is a separator or an interpolated path segment.
                $tail = trim($token[1], "'\"");
            }
        }

        if (!$anchored) {
            return null;
        }

        $directory = dirname($file);
        $pattern   = '*';

        if ($tail !== '') {
            $nested = trim(dirname($tail), '/.');
            $base   = basename($tail);

            if ($nested !== '') {
                $directory .= '/' . $nested;
            }

            if ($base !== '') {
                $pattern = $base;
            }
        }

        $resolved = realpath($directory);

        return $resolved === false
            ? null
            : ['directory' => $resolved, 'pattern' => $pattern, 'at' => $file . ':' . $line];
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @param int                                                 $at index of the token following the `.`
     */
    private static function literalSuffix(array $tokens, int $at): ?string
    {
        $token = $tokens[$at];

        if (is_string($token)) {
            return null;
        }

        if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
            return self::validSuffix(trim($token[1], "'\""));
        }

        if ($token[0] !== T_STRING || $token[1] !== 'config') {
            return null;
        }

        $open = self::nextSignificant($tokens, $at + 1);

        if ($open === null || $tokens[$open] !== '(') {
            return null;
        }

        $arguments = self::literalArguments($tokens, $open);

        // The key is the first argument, the literal default the second. With no
        // default there is nothing literal to register — see suffixAfter().
        return count($arguments) < 2 ? null : self::validSuffix($arguments[1]);
    }

    /**
     * The string literals in a call's argument list, in order.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @param int                                                 $open index of the call's `(`
     * @return list<string>
     */
    private static function literalArguments(array $tokens, int $open): array
    {
        $count     = count($tokens);
        $depth     = 0;
        $arguments = [];

        for ($i = $open; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                if ($token === '(') {
                    $depth++;
                } elseif ($token === ')' && --$depth === 0) {
                    break;
                }

                continue;
            }

            if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $arguments[] = trim($token[1], "'\"");
            }
        }

        return $arguments;
    }

    private static function validSuffix(string $suffix): ?string
    {
        return preg_match(self::SUFFIX_SHAPE, $suffix) === 1 ? $suffix : null;
    }
}
