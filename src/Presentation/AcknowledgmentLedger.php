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

use function array_slice;
use function explode;
use function file;
use function hash;
use function implode;
use function is_file;
use function sort;
use function sprintf;
use function str_starts_with;

use const FILE_IGNORE_NEW_LINES;

use LucianoPereira\PhpcpdNext\CodeClone;

/**
 * A committed record of duplication a team has looked at and decided to live
 * with — and the one mechanism in this tool by which they can say so **without
 * writing anything into their source**.
 *
 * ## Why a ledger rather than an annotation
 *
 * `phpcpd-ignore` markers already exist and stay: they are the right tool when
 * the duplication is deliberate design and the comment explaining it belongs
 * beside the code. This is the other case — a large existing codebase adopting
 * the tool, where the honest position is *"we know, we are not fixing it this
 * quarter"*. Writing hundreds of markers into source to say that would be
 * editing a codebase to change a report, and the markers would outlive the
 * decision. A ledger is one file, in review, that a reader can diff.
 *
 * ## Demote, never suppress
 *
 * An acknowledged finding is **demoted**: still detected, still printed, still in
 * every count, still gating the exit code. This is the property that separates a
 * ledger from a baseline in the usual sense, and it is deliberate — a mechanism
 * that made findings disappear would, over a few quarters, make the report a
 * record of what nobody had got round to acknowledging yet.
 *
 * ## Self-expiring, because a stale acknowledgment is a lie
 *
 * An entry is keyed to the **content of every side** of the duplication, hashed.
 * Nothing else: no path, no line number, no ordinal. So an entry acknowledges a
 * piece of text rather than a place, which has three consequences, all wanted:
 *
 *   - **Edit either copy and the acknowledgment expires.** The decision was made
 *     about code that no longer exists, so the finding is asserted again. This is
 *     the whole reason the key is content and not location.
 *   - **Move the code and the acknowledgment survives.** A rename or a shifted
 *     line number changes no content, and re-acknowledging the same decision
 *     after a refactor is noise.
 *   - **An entry nothing matches is reported**, by the paths it was written with,
 *     rather than sitting in the file forever. A ledger that quietly accumulates
 *     dead entries is a ledger nobody trusts.
 *
 * ## No constants
 *
 * There is no threshold here and nothing to derive: a hash matches or it does
 * not.
 */
use LucianoPereira\PhpcpdNext\Strings\Catalogue;

final readonly class AcknowledgmentLedger
{
    /** @param array<string, string> $entries key => the note it was written with */
    private function __construct(public array $entries) {}

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Read a ledger. A path that does not exist is an empty ledger rather than
     * an error: the first run of a project that has not written one yet is not a
     * failure, and the writer's whole job is to produce the file that is missing.
     */
    public static function load(string $path): self
    {
        if (!is_file($path)) {
            return self::empty();
        }

        $entries = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $columns = explode("\t", $line);
            $key     = $columns[0];

            if ($key !== '') {
                $entries[$key] = $columns[1] ?? $key;
            }
        }

        return new self($entries);
    }

    public function has(string $key): bool
    {
        return isset($this->entries[$key]);
    }

    /**
     * The entries no finding in this run matched — expired, and named by the note
     * they were written with so a reader can go and delete the line.
     *
     * @param  array<string, true> $matched
     * @return list<string>
     */
    public function stale(array $matched): array
    {
        $stale = [];

        foreach ($this->entries as $key => $note) {
            if (!isset($matched[$key])) {
                $stale[] = $note;
            }
        }

        sort($stale);

        return $stale;
    }

    /**
     * The key of one finding: every side's content, hashed, sorted, and hashed
     * again.
     *
     * Sorted because a clone class is a **set** of occurrences and the engine's
     * order within it is not something a user should have to think about — two
     * runs that list the same three sites in a different order must produce one
     * key. Hashed again so the key is one fixed-width token on a diffable line
     * rather than a row that grows with the class.
     */
    public static function keyOf(CodeClone $clone): string
    {
        $sides = [];

        foreach ($clone->files() as $file) {
            $sides[] = hash('xxh3', self::excerpt($file->name, $file->startLine, $clone->numberOfLines()));
        }

        sort($sides);

        return hash('xxh3', implode(' ', $sides));
    }

    /**
     * The note written beside a key: the paths and lines the acknowledgment was
     * made about. **Never matched on** — it is there so a human can read the
     * ledger and so an expired entry can name itself.
     */
    public static function noteFor(CodeClone $clone): string
    {
        $parts = [];

        foreach ($clone->files() as $file) {
            $parts[] = sprintf('%s:%d', $file->name, $file->startLine);
        }

        return sprintf('%d lines · %s', $clone->numberOfLines(), implode(' ↔ ', $parts));
    }

    /**
     * The file to commit: one line per finding, sorted, with a header explaining
     * what the file does to anyone who meets it in a code review.
     */
    public static function render(Findings $findings): string
    {
        $lines = [];

        foreach ($findings->findings as $finding) {
            $lines[] = self::keyOf($finding->clone) . "\t" . self::noteFor($finding->clone);
        }

        sort($lines);

        $strings = new Catalogue();

        // `#` is the file's syntax, not part of any sentence: `parse()` skips on
        // it, so it is prefixed here rather than written into every line of the
        // catalogue where a translator could drop one and silently turn a
        // comment into an entry.
        $header = [
            $strings->get('document.ledger.title'),
            '',
            $strings->get('document.ledger.what'),
            '',
            $strings->get('document.ledger.key'),
            '',
            $strings->get('document.ledger.note'),
            '',
        ];

        $commented = [];

        foreach ($header as $paragraph) {
            foreach (explode("\n", $paragraph) as $line) {
                $commented[] = $line === '' ? '#' : '# ' . $line;
            }
        }

        return implode("\n", $commented) . "\n" . implode("\n", $lines) . "\n";
    }

    /**
     * One site's text, as the lines the finding covers. Read here rather than
     * through `CodeClone::lines()` because that reports only the *first* file's
     * excerpt, and this key is about every side.
     */
    private static function excerpt(string $path, int $startLine, int $lines): string
    {
        $contents = @file($path);

        if ($contents === false) {
            // A side that cannot be read produces a key nothing will match, so
            // the finding is asserted. No evidence, no acknowledgment.
            return $path . ':' . $startLine . ':unreadable';
        }

        return implode('', array_slice($contents, $startLine - 1, $lines));
    }
}
