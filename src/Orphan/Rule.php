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

use function array_keys;

/**
 * The registry of orphan suppression rules. One rule name serves three jobs: it
 * is the `--no-suppress` value that disables the rule, the heading the report
 * groups findings under, and the machine-readable cause on an {@see Orphan}.
 *
 * Keeping them the same string is the point — what a reader sees in the output
 * is exactly what they type to switch it off, and adding a rule costs no new
 * CLI flag.
 *
 * A suppressed symbol is never dropped. It moves to its own counted group and
 * stops gating the exit code, so a misfiring rule stays visible (`--explain`)
 * instead of turning a real orphan into silence.
 */
use LucianoPereira\PhpcpdNext\Strings\Catalogue;

final class Rule
{
    /** Declared inside `if (!function_exists('x'))` — a polyfill or shim. */
    public const string CONDITIONAL = 'conditional';

    /** Declared under a fixture/stub path within a scanned tree. */
    public const string FIXTURES = 'fixtures';

    /** Named in a config file — neon / yaml / xml, or a `config/*.php` array. */
    public const string CONFIG = 'config';

    /** Named in a template (blade / twig / latte / tpl) — a view is a call site. */
    public const string TEMPLATE = 'template';

    /** Named in composer.json — bin, autoload.files, or extra.*. */
    public const string MANIFEST = 'manifest';

    /** Declared outside every namespace prefix the project owns. */
    public const string NAMESPACE_PREFIX = 'namespace';

    /** Carries @api / @phpcpd-keep. */
    public const string KEEP = 'keep';

    /** Wired reflectively — a framework attribute or a test class. */
    public const string ENTRYPOINT = 'entrypoint';

    /** Declared in a directory a sibling file globs and instantiates by filename. */
    public const string DISCOVERY = 'discovery';

    /** Named by a suffix a trait its base class uses appends at runtime. */
    public const string CONVENTION = 'convention';

    /** Carries @phpcpd-planned: known to be unwired, deliberately. */
    public const string PLANNED = 'planned';

    /**
     * Rule name => the heading the report prints for it.
     *
     * The names are the code's — a user types them in `--no-suppress` — and the
     * headings are the catalogue's, so a translation cannot change what has to
     * be typed and a rename cannot silently orphan a sentence.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $strings = new Catalogue();
        $labels  = [];

        foreach (self::NAMES as $name) {
            $labels[$name] = $strings->get('label.orphan.' . $name);
        }

        return $labels;
    }

    /** Every rule, in report order. @var non-empty-list<non-empty-string> */
    private const array NAMES = [
        self::CONDITIONAL,
        self::FIXTURES,
        self::CONFIG,
        self::TEMPLATE,
        self::MANIFEST,
        self::NAMESPACE_PREFIX,
        self::KEEP,
        self::ENTRYPOINT,
        self::DISCOVERY,
        self::CONVENTION,
        self::PLANNED,
    ];

    /** @return non-empty-list<non-empty-string> */
    public static function names(): array
    {
        /** @var non-empty-list<non-empty-string> */
        return array_keys(self::labels());
    }

    public static function label(?string $rule): string
    {
        $none = (new Catalogue())->get('label.orphan.none');

        if ($rule === null || $rule === '') {
            return $none;
        }

        return self::labels()[$rule] ?? $none;
    }
}
