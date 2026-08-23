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
final class Rule
{
    /** Declared inside `if (!function_exists('x'))` — a polyfill or shim. */
    public const string CONDITIONAL = 'conditional';

    /** Declared under a fixture/stub path within a scanned tree. */
    public const string FIXTURES = 'fixtures';

    /** Named in a non-PHP config file (neon / yaml / xml / json). */
    public const string CONFIG = 'config';

    /** Named in composer.json — bin, autoload.files, or extra.*. */
    public const string MANIFEST = 'manifest';

    /** Declared outside every namespace prefix the project owns. */
    public const string NAMESPACE_PREFIX = 'namespace';

    /** Carries @api / @phpcpd-keep. */
    public const string KEEP = 'keep';

    /** Wired reflectively — a framework attribute or a test class. */
    public const string ENTRYPOINT = 'entrypoint';

    /** Carries @phpcpd-planned: known to be unwired, deliberately. */
    public const string PLANNED = 'planned';

    /** @return array<string, string> rule name => report heading */
    public static function labels(): array
    {
        return [
            self::CONDITIONAL      => 'Conditionally declared (polyfill / compatibility shim)',
            self::FIXTURES         => 'Test fixtures (loaded by path or by name)',
            self::CONFIG           => 'Registered in a non-PHP config file',
            self::MANIFEST         => 'Referenced from composer.json',
            self::NAMESPACE_PREFIX => "Declared outside the project's own namespaces (compatibility shim)",
            self::KEEP             => 'Marked as kept (@api / @phpcpd-keep)',
            self::ENTRYPOINT       => 'Framework entry points (attribute / test class)',
            self::PLANNED          => 'Planned, not yet wired',
        ];
    }

    /** @return non-empty-list<non-empty-string> */
    public static function names(): array
    {
        /** @var non-empty-list<non-empty-string> */
        return array_keys(self::labels());
    }

    public static function label(?string $rule): string
    {
        if ($rule === null || $rule === '') {
            return 'No reference found';
        }

        return self::labels()[$rule] ?? 'No reference found';
    }
}
