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

namespace LucianoPereira\PhpcpdNext\Strings;

use function basename;
use function dirname;
use function implode;
use function in_array;
use function is_array;
use function is_file;
use function is_string;
use function scandir;
use function sort;
use function sprintf;
use function str_ends_with;
use function strtr;

use LucianoPereira\PhpcpdNext\Exceptions\MissingStringException;

/**
 * Every sentence the tool says, in one keyed file per language.
 *
 * The conventions the files keep — `:name` placeholders, keying by act then by
 * result, counts written label-first, and what belongs in a catalogue at all —
 * are in docs/localization.md. What this class guarantees:
 *
 * - The tree is flattened once, at load, so a dotted path costs a hash lookup
 *   and grouping is free.
 * - **Only the parameters the caller passed are substituted.** An unrecognised
 *   `:word` is left standing rather than blanked, so a translation that mentions
 *   a colon, or names a placeholder that no longer exists, degrades to visible
 *   text instead of a silent hole.
 * - A key missing from a translation falls back to `en`, one key at a time, so
 *   a partial translation ships. A key missing from `en` raises: that is a bug
 *   in the caller, and it should not be quiet.
 * - No number formatting and no plural machinery. The caller knows the type it
 *   holds, and a count is written `files (:count)` so no plural rule applies.
 */
final class Catalogue
{
    /** @var array<string, string> */
    private readonly array $strings;

    public const string FALLBACK = 'en';

    /** @var array<string, string> the fallback's strings, when this is not it */
    private readonly array $fallback;

    /**
     * The language this process speaks. Process-wide because that is what it
     * describes: one command, one output, one language.
     */
    private static ?string $processLanguage = null;

    private readonly string $language;

    public function __construct(?string $language = null)
    {
        $this->language = $language ?? self::$processLanguage ?? self::FALLBACK;
        $this->strings  = self::load($this->language);
        $this->fallback = $this->language === self::FALLBACK ? [] : self::load(self::FALLBACK);
    }

    /**
     * Choose the language for everything this process prints from here on.
     * Loading validates, so an unknown language is refused here rather than at
     * the first message printed.
     *
     * @throws MissingStringException
     */
    public static function useLanguage(string $language): void
    {
        self::load($language);

        self::$processLanguage = $language;
    }

    /**
     * Read one language file.
     *
     * An unknown language is refused rather than quietly served in English:
     * `--language=engllish` is a typo, and a run that silently ignores it looks
     * exactly like one that worked. The refusal names what does exist, the way
     * an out-of-range `--algorithm` does.
     *
     * @return array<string, string>
     */
    private static function load(string $language): array
    {
        $path = self::directory() . '/' . $language . '.php';

        if (!is_file($path)) {
            throw new MissingStringException(sprintf(
                'No catalogue for language "%s". Available: %s.',
                $language,
                implode(', ', self::available()),
            ));
        }

        /** @var array<string, mixed> $tree */
        $tree = require $path;

        return self::flatten($tree, '');
    }

    /** Where the language files live — outside `src/`, because they are data. */
    private static function directory(): string
    {
        return dirname(__DIR__, 2) . '/locale';
    }

    /**
     * Every language the package ships — for the refusal above to name, and for
     * `--language` to constrain itself to.
     *
     * @return non-empty-list<string>
     */
    public static function available(): array
    {
        $found = [];

        // `scandir()` rather than `glob()`: glob() does not support the
        // `phar://` stream wrapper and returns an empty list inside a phar,
        // where `is_dir()`, `is_file()` and `scandir()` all work. Listing the
        // directory and filtering here is the same answer by a route the phar
        // build can take, and the check below turns the difference into a
        // refusal rather than a tool that silently speaks no language.
        foreach (scandir(self::directory()) ?: [] as $file) {
            if (str_ends_with($file, '.php')) {
                $found[] = basename($file, '.php');
            }
        }

        sort($found);

        // Never empty. The fallback is not optional — a `locale/` without it is
        // an install missing files rather than a package with no languages, and
        // every message that would report *that* also lives in `locale/`. So it
        // is checked here, once, where the failure can still be described.
        if (!in_array(self::FALLBACK, $found, true)) {
            throw new MissingStringException(sprintf(
                'The %s catalogue is missing from %s; the package is incomplete.',
                self::FALLBACK,
                self::directory(),
            ));
        }

        return $found;
    }

    /**
     * A message in its severity frame.
     *
     * The marker is not part of the sentence — it never inflects and never
     * reorders with what follows — so it lives in one key and every failure
     * wears it. That matters beyond consistency: colour and choice of stream
     * are the only things currently separating a failure from ordinary output,
     * and `NO_COLOR`, a pipe, or `2>&1` in a CI log erase both.
     */
    public function error(string $message): string
    {
        return $this->get('frame.error', ['message' => $message]);
    }

    public function warning(string $message): string
    {
        return $this->get('frame.warning', ['message' => $message]);
    }

    /**
     * A message and the remedy that lifts it — two sentences, not one split in
     * half, so a translation may reorder them and the break between them is
     * layout rather than grammar.
     */
    public function withHint(string $message, string $hint): string
    {
        return $this->get('frame.hint', ['message' => $message, 'hint' => $hint]);
    }

    /**
     * Flatten the tree into dotted paths, once, at load.
     *
     * @param  array<string, mixed> $tree
     * @return array<string, string>
     */
    private static function flatten(array $tree, string $prefix): array
    {
        $flat = [];

        foreach ($tree as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $flat += self::flatten($value, $path);

                continue;
            }

            // A leaf that is not a sentence is a mistake in the language
            // file, and casting would bury it — `1.5` would silently become a
            // message. Raised where the file is loaded, not where it prints.
            if (!is_string($value)) {
                throw new MissingStringException(sprintf('The %s catalogue holds a non-string at "%s".', $prefix === '' ? 'root' : $prefix, $path));
            }

            $flat[$path] = $value;
        }

        return $flat;
    }

    /**
     * The sentence for a key, with its parameters filled in.
     *
     * A missing key raises rather than returning the key: a report that prints
     * `totals.average` to a user is worse than one that fails loudly in the
     * test that would have caught it.
     *
     * @param array<string, string|int> $parameters
     */
    public function get(string $key, array $parameters = []): string
    {
        // A translation is allowed to be incomplete. A translator who has done
        // half the file should be able to use it, and the half they have not
        // reached should read as English rather than stop the run — so a key
        // missing from a translation falls back. A key missing from the
        // fallback itself is a bug in the package with nothing behind it, and
        // raises: a report printing `report.totals.coverage` at a user is worse
        // than a test failing here.
        $template = $this->strings[$key] ?? $this->fallback[$key] ?? null;

        if ($template === null) {
            throw new MissingStringException(sprintf('No string "%s" in the %s catalogue.', $key, $this->language));
        }

        if ($parameters === []) {
            return $template;
        }

        $replacements = [];

        foreach ($parameters as $name => $value) {
            $replacements[':' . $name] = (string) $value;
        }

        return strtr($template, $replacements);
    }
}
