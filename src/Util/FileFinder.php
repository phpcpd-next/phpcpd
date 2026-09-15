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

use function array_keys;
use function count;
use function fclose;
use function fnmatch;
use function fopen;
use function fread;
use function in_array;
use function is_array;
use function is_dir;
use function preg_match;
use function preg_quote;
use function sort;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function stripos;
use function strpbrk;
use function strpos;
use function str_starts_with;
use function substr;
use function token_get_all;

use const T_COMMENT;
use const T_DOC_COMMENT;

/**
 * Finds files to scan. Replaces phpunit/php-file-iterator with three improvements:
 *
 *  1. Excluded directories are PRUNED during traversal — the walk never descends
 *     into vendor/ etc., instead of walking everything and filtering afterwards.
 *  2. Exclude patterns may be glob patterns (e.g. "*.blade.php", "build/*"), not
 *     only plain substrings — while substring excludes still work for compatibility.
 *  3. Generated and cached trees are excluded by default. A tool cache is
 *     adversarial input for reference detection: PHPStan's result cache embeds
 *     every analysed class name as a string literal, which silently satisfies the
 *     orphan scan's reference check. Defaults match whole path SEGMENTS, never
 *     substrings, so a default named "out" cannot swallow "routes/".
 */
final class FileFinder
{
    /**
     * Directory names pruned unless default excludes are turned off.
     *
     * Short, because the tool caches that used to fill this list are now covered
     * by a rule instead: see {@see isDefaultExcludedDir()} for the leading-dot
     * convention. What remains is the two dependency trees and the four
     * conventional build outputs, none of which is hidden.
     *
     * @var list<string>
     */
    private const array DEFAULT_EXCLUDED_DIRS = [
        'vendor', 'node_modules',
        'build', 'dist', 'out', 'coverage',
    ];

    /**
     * Multi-segment directory paths pruned by default — framework cache trees
     * whose last segment alone ("cache", "framework") is too common to prune.
     *
     * @var list<string>
     */
    private const array DEFAULT_EXCLUDED_PATHS = [
        'var/cache', 'storage/framework', 'bootstrap/cache',
    ];

    /**
     * Markers that identify a generated file wherever it lives, for the trees a
     * path-based default cannot predict.
     *
     * These are matched only inside COMMENTS, and only where the marker opens a
     * line or a sentence — the same anchoring rule, for the same reason, that
     * {@see \LucianoPereira\PhpcpdNext\Orphan\SymbolCollector::docTag()} applies
     * to docblock tags. An unanchored byte search over the file head is what a
     * grep would do, and it fails in both directions:
     *
     *   - `'# AUTO-GENERATED, do not edit by hand.'` is a string literal a
     *     *generator* writes into the file it emits. The generator itself is
     *     ordinary source, and dropping it also drops every symbol it declares
     *     AND every reference it makes — which is how a live class reached the
     *     definite-orphan tier that gates CI.
     *   - `Marker prefix used to identify auto-generated options` is prose in a
     *     property docblock. A file that *mentions* generated code is not
     *     generated code.
     *
     * A banner announces the file; prose merely mentions it. Anchoring is what
     * tells them apart, and it is why this list can stay short and generous.
     *
     * A phrase earns a place here only if anchoring alone separates the two
     * senses. `automatically generated` does not, which is why it is handled by
     * {@see GENERATED_BANNER_FORM} instead.
     *
     * @var list<string>
     */
    private const array GENERATED_MARKERS = ['@generated', 'do not edit', 'auto-generated'];

    /**
     * Anchors a marker to the start of a line, a comment delimiter, or a
     * sentence. `// Generated from metrics.afm. Do not edit by hand.` is a
     * banner because "Do not edit" opens a sentence; `identify auto-generated
     * options` is not, because it opens nothing.
     */
    private const string GENERATED_ANCHOR = '#(?:^|[*/\#]|[.!?])[ \t]*%s#im';

    /**
     * The phrasing anchoring cannot arbitrate, matched as a whole banner rather
     * than as a marker.
     *
     * nikic/php-parser's generated parsers open with `This is an automatically
     * GENERATED file, which should not be manually edited.` — no marker in
     * {@see GENERATED_MARKERS} occurs in it ("auto-generated" is hyphenated,
     * and "should not be manually edited" is not "do not edit"), so both
     * parsers are read as ordinary source.
     *
     * Adding `automatically generated` to the marker list does not fix it. It
     * fails twice over. The marker sits four words into the sentence, behind
     * "This is an", so the anchor rejects it; and merely widening the anchor to
     * let a lead-in through makes the phrase fire on prose, because unlike the
     * markers above it is ordinary English. WordPress's `post-excerpt.php` says
     * `* automatically generated and user-created excerpts.` — a continuation
     * `*` is an anchor, and a live source file would be dropped from the scan.
     *
     * So the phrase counts only where it announces the file: introduced by
     * "this is a" / "this file is" / "this file was", or naming the artifact it
     * produced. Measured over the six benchmark corpora (~6,700 files), this
     * adds exactly `Php7.php` and `Php8.php` and nothing else.
     */
    private const string GENERATED_BANNER_FORM = '#(?:^|[*/\#]|[.!?])[ \t]*(?:this[ \t]+(?:is|file[ \t]+is|file[ \t]+was)[ \t]+(?:an?[ \t]+)?automatically[ \t]+generated|automatically[ \t]+generated[ \t]+(?:file|code|source))#im';

    private const int GENERATED_PROBE_BYTES = 2048;

    /** Directories skipped this run because they could not be read. */
    private int $skippedDirectories = 0;

    /** Files skipped this run because they announce themselves as generated. */
    private int $skippedGenerated = 0;

    /**
     * Directories the last find() could not read, for the scope line. A run that
     * covered less of the tree than the caller intended should say so rather than
     * report a clean result over a partial scan.
     */
    public function skippedDirectoryCount(): int
    {
        return $this->skippedDirectories;
    }

    /**
     * Files the last find() skipped as generated, for the scope line.
     *
     * Same argument as the unreadable-directory count, and the same failure it
     * guards against: a content sniff that drops a file drops its declarations
     * and its references with it, so a wrong drop turns live code into a definite
     * orphan. A count that moves is reviewable; a silent drop is not.
     */
    public function skippedGeneratedCount(): int
    {
        return $this->skippedGenerated;
    }

    /**
     * @param list<string> $directories
     * @param list<string> $suffixes match files ending in any of these (empty = all)
     * @param list<string> $excludes substring or glob patterns to skip
     * @param bool         $defaultExcludes prune generated/cache trees and skip generated files
     * @return list<string>
     */
    public function find(array $directories, array $suffixes, array $excludes, bool $defaultExcludes = true): array
    {
        $this->skippedDirectories = 0;
        $this->skippedGenerated   = 0;

        $files = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach ($this->walk(self::normalize($directory), $suffixes, $excludes, $defaultExcludes) as $file) {
                $files[self::normalize($file)] = true;
            }
        }

        $result = array_keys($files);
        sort($result);

        return $result;
    }

    /**
     * Number of patterns the default exclude set contributes, for the scope line.
     * The leading-dot rule counts as one, because it is one.
     */
    public static function defaultExcludeCount(): int
    {
        return count(self::DEFAULT_EXCLUDED_DIRS) + count(self::DEFAULT_EXCLUDED_PATHS) + 1;
    }

    /**
     * @param list<string> $suffixes
     * @param list<string> $excludes
     * @return list<string>
     */
    private function walk(string $directory, array $suffixes, array $excludes, bool $defaultExcludes): array
    {
        $filter = new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            function (\SplFileInfo $entry) use ($suffixes, $excludes, $defaultExcludes): bool {
                $path = $entry->getPathname();

                if ($entry->isDir()) {
                    // An unreadable directory is pruned here rather than left to throw
                    // out of getChildren(). Counted so the run can say how much of the
                    // tree it could not see — a scan that silently covered less than
                    // the caller believes is the failure mode worth surfacing.
                    if (!$entry->isReadable()) {
                        $this->skippedDirectories++;

                        return false;
                    }

                    // Prune: returning false stops recursion into this directory entirely.
                    return !$this->isExcluded($path, $entry->getFilename(), $excludes)
                        && !($defaultExcludes && $this->isDefaultExcludedDir($path, $entry->getFilename()));
                }

                if (!$this->isCandidate($path, $entry->getFilename(), $suffixes)
                    || $this->isExcluded($path, $entry->getFilename(), $excludes)) {
                    return false;
                }

                if ($defaultExcludes && $this->isGenerated($path)) {
                    $this->skippedGenerated++;

                    return false;
                }

                return true;
            },
        );

        $result = [];

        // CATCH_GET_CHILD: an unreadable directory must not abort the walk. Without
        // it, RecursiveDirectoryIterator::__construct() throws UnexpectedValueException
        // out of getChildren() and the entire run dies with no report — one
        // permission-denied directory anywhere under the root loses every finding.
        // The isReadable() prune in the callback catches the common case and keeps
        // the count; this flag is the backstop for races and exotic filesystems.
        $iterator = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::LEAVES_ONLY, \RecursiveIteratorIterator::CATCH_GET_CHILD);

        foreach ($iterator as $entry) {
            if ($entry instanceof \SplFileInfo && $entry->isFile()) {
                $result[] = $entry->getPathname();
            }
        }

        return $result;
    }

    /**
     * A file is scanned when its suffix matches, or — for an extensionless file —
     * when a `#!` line names php. Console entry points are conventionally
     * extensionless (`artisan`, `bin/console`), so a suffix filter never sees the
     * one file where top-level wiring lives.
     *
     * A PHAR is excluded even though it satisfies that rule, because a built
     * archive opens with exactly the same `#!/usr/bin/env php` line as the entry
     * point it was built from. Projects commit them — PHPUnit tracks `composer`,
     * `php-cs-fixer`, `php-scoper`, `phive` and `phpab` under `tools/` — and they
     * are enormous: those five carry 350,480 lines between them, 63.9% of every
     * line the tool counted for that project. The duplication percentage is a
     * ratio, so admitting them does not merely add noise, it silently divides the
     * answer: PHPUnit reported 1.67% where the truth over its own sources is
     * 4.64%. Nothing is gained in exchange, since a compiled archive is not code
     * anyone can refactor.
     *
     * @param list<string> $suffixes
     */
    private function isCandidate(string $path, string $name, array $suffixes): bool
    {
        if ($this->hasSuffix($path, $suffixes)) {
            return true;
        }

        return !str_contains($name, '.')
            && $this->hasPhpShebang($path)
            && !$this->isPharArchive($path);
    }

    /** @param list<string> $suffixes */
    private function hasSuffix(string $path, array $suffixes): bool
    {
        if ($suffixes === []) {
            return true;
        }

        foreach ($suffixes as $suffix) {
            if (str_ends_with($path, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $excludes */
    private function isExcluded(string $path, string $name, array $excludes): bool
    {
        foreach ($excludes as $exclude) {
            if ($this->isGlob($exclude)) {
                if (fnmatch($exclude, $path) || fnmatch($exclude, $name)) {
                    return true;
                }
            } elseif (str_contains($path, $exclude)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Segment match, not substring: "out" prunes a directory named out/, never a
     * path merely containing those letters.
     *
     * **A hidden directory is not application source.** A leading dot is the
     * filesystem's own convention for "this is state, not content", and every
     * ecosystem honours it: version control (`.git`), editors (`.idea`,
     * `.vscode`), CI (`.github`), and every static-analysis cache
     * (`.phpstan`, `.psalm`, `.rector`, `.phpunit.cache`) live in one. No PHP
     * autoloading convention puts application code there.
     *
     * This replaces a hand list of a dozen specific cache directory names, and
     * replacing it was the point. That list could only ever name the caches
     * someone had already been burned by: the directory name is user-configured
     * (PHPStan's `tmpDir`, Rector's `cacheDirectory`), so matching the documented
     * default left every other spelling scanning as source. Measured on one real
     * tree, a dumped analysis cache under a directory the list did not name
     * contributed 1,024 files — a third of everything the tool considered that
     * project's code. A rule catches the next one; a list catches the last one.
     *
     * A scan root is never pruned by this, because `find()` prunes descendants of
     * the roots it is given and never the roots themselves. Pointing the tool at
     * `.github/scripts` scans it, exactly as asking for it should.
     */
    private function isDefaultExcludedDir(string $path, string $name): bool
    {
        if (str_starts_with($name, '.') && $name !== '.' && $name !== '..') {
            return true;
        }

        if (in_array($name, self::DEFAULT_EXCLUDED_DIRS, true)) {
            return true;
        }

        $normalized = str_replace('\\', '/', $path);

        foreach (self::DEFAULT_EXCLUDED_PATHS as $tail) {
            if (str_ends_with($normalized, '/' . $tail) || $normalized === $tail) {
                return true;
            }
        }

        return false;
    }

    private function isGenerated(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $head = fread($handle, self::GENERATED_PROBE_BYTES);
        fclose($handle);

        if ($head === false || $head === '') {
            return false;
        }

        // Tokenizing rather than byte-searching is the whole fix: it is what
        // separates a comment from a string literal a generator emits. The probe
        // is a truncated file, so the tokenizer will warn about an unterminated
        // comment or string — expected, and irrelevant to the comment tokens it
        // has already produced.
        $tokens = @token_get_all($head);

        foreach ($tokens as $token) {
            if (!is_array($token) || ($token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT)) {
                continue;
            }

            if ($this->hasGeneratedBanner($token[1])) {
                return true;
            }
        }

        return false;
    }

    /** Does this comment announce the file as generated, rather than mention it? */
    private function hasGeneratedBanner(string $comment): bool
    {
        foreach (self::GENERATED_MARKERS as $marker) {
            if (preg_match(sprintf(self::GENERATED_ANCHOR, preg_quote($marker, '#')), $comment) === 1) {
                return true;
            }
        }

        return preg_match(self::GENERATED_BANNER_FORM, $comment) === 1;
    }

    private function hasPhpShebang(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $head = fread($handle, 128);
        fclose($handle);

        if ($head === false || !str_starts_with($head, '#!')) {
            return false;
        }

        $break = strpos($head, "\n");

        return stripos($break === false ? $head : substr($head, 0, $break), 'php') !== false;
    }

    /**
     * Is this a built PHAR rather than a script?
     *
     * `__HALT_COMPILER();` is what makes a PHAR a PHAR: the stub ends there and
     * the archive follows as raw bytes, so the token is present in every one and
     * is not something a hand-written console entry point has any reason to say.
     * It is the marker the format itself is defined by, which is why this tests
     * for it rather than for a name under `tools/` or `bin/`.
     *
     * Read far enough to clear the stub and no further. The five archives PHPUnit
     * commits place the token at bytes 683 to 32,927; 64 KiB covers them with room
     * to spare, and bounds the cost for the ordinary case this never fires on —
     * an extensionless file with a php shebang, of which a project has one or two.
     */
    private function isPharArchive(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $head = fread($handle, 65536);
        fclose($handle);

        return $head !== false && str_contains($head, '__HALT_COMPILER');
    }

    private function isGlob(string $pattern): bool
    {
        return strpbrk($pattern, '*?[') !== false;
    }

    /**
     * Collapse the doubled separator a root scan produces.
     * `RecursiveDirectoryIterator` joins its root to each entry by concatenation,
     * so the scan root `/` reports every path as `//usr/bin/sudo`. Cosmetic on its
     * own, but it is the thread a reader pulls when a run goes wrong, and a
     * duplicated path is a duplicated cache key for anything downstream.
     *
     * Only a leading duplicate is touched — a `scheme://` later in the string is
     * left alone.
     */
    private static function normalize(string $path): string
    {
        while (str_starts_with($path, '//')) {
            $path = substr($path, 1);
        }

        return $path;
    }
}
