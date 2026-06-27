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

use function array_filter;
use function max;
use function sprintf;
use function str_pad;
use function strlen;
use function trim;

use const PHP_EOL;

/**
 * The phpcpd-next option set — declared once. Both ArgumentsBuilder (parsing) and
 * the --help text derive from this list, so a new option is documented and parsed
 * from a single edit and the two can never drift.
 */
final class Options
{
    /** @return list<OptionDefinition> */
    public static function definitions(): array
    {
        return [
            new OptionDefinition(
                name: 'suffix',
                takesValue: true,
                repeatable: true,
                valuePlaceholder: '<suffix>',
                description: 'Include files with names ending in <suffix> (default: .php; repeatable)',
                group: 'Options for selecting files',
            ),
            new OptionDefinition(
                name: 'exclude',
                takesValue: true,
                repeatable: true,
                valuePlaceholder: '<path>',
                description: 'Exclude files with <path> in their path (repeatable)',
                group: 'Options for selecting files',
            ),
            new OptionDefinition(
                name: 'rk',
                description: 'Rabin-Karp only (exact/Type-1 clones; faster, no reorder detection). Default runs both Rabin-Karp and TokenBag.',
                group: 'Options for analysing files',
            ),
            new OptionDefinition(
                name: 'min-lines',
                takesValue: true,
                valuePlaceholder: '<N>',
                description: 'Minimum number of identical lines (default: 5)',
                group: 'Options for analysing files',
            ),
            new OptionDefinition(
                name: 'min-tokens',
                takesValue: true,
                valuePlaceholder: '<N>',
                description: 'Minimum number of identical tokens (default: 70)',
                group: 'Options for analysing files',
            ),
            new OptionDefinition(
                name: 'verbose',
                description: 'Print the duplicated code for each clone',
                group: 'Options for analysing files',
            ),
            // Research / benchmark flags — parsed but hidden from --help.
            new OptionDefinition(
                name: 'algorithm',
                takesValue: true,
                allowedValues: ['rabin-karp', 'suffixtree', 'tokenbag'],
                valuePlaceholder: '<name>',
                description: "Single algorithm override (rabin-karp | suffixtree | tokenbag)",
                group: 'Options for analysing files',
                advanced: true,
            ),
            new OptionDefinition(
                name: 'fuzzy',
                description: 'Fuzz variable and identifier names (research; high false-positive rate on real corpora)',
                group: 'Options for analysing files',
                advanced: true,
            ),
            new OptionDefinition(
                name: 'type-anchored',
                description: 'Like --fuzzy but preserves type keywords (research)',
                group: 'Options for analysing files',
                advanced: true,
            ),
            new OptionDefinition(
                name: 'min-similarity',
                takesValue: true,
                valuePlaceholder: '<0-1>',
                description: 'TokenBag overlap threshold (default: 0.7)',
                group: 'Options for analysing files',
                advanced: true,
            ),
            new OptionDefinition(
                name: 'edit-distance',
                takesValue: true,
                valuePlaceholder: '<N>',
                description: 'SuffixTree edit distance (default: 5)',
                group: 'Options for analysing files',
                advanced: true,
            ),
            new OptionDefinition(
                name: 'head-equality',
                takesValue: true,
                valuePlaceholder: '<N>',
                description: 'SuffixTree head-equality prefix (default: 10)',
                group: 'Options for analysing files',
                advanced: true,
            ),
            new OptionDefinition(
                name: 'log-pmd',
                takesValue: true,
                valuePlaceholder: '<file>',
                description: 'Write log in PMD-CPD XML format to <file>',
                group: 'Options for report generation',
            ),
            new OptionDefinition(
                name: 'log-json',
                takesValue: true,
                valuePlaceholder: '<file>',
                description: 'Write log in JSON format to <file>',
                group: 'Options for report generation',
            ),
            new OptionDefinition(
                name: 'log-sarif',
                takesValue: true,
                valuePlaceholder: '<file>',
                description: 'Write log in SARIF 2.1.0 format to <file> (for GitHub Code Scanning)',
                group: 'Options for report generation',
            ),
            new OptionDefinition(
                name: 'cache',
                description: "Cache results in '.phpcpd-cache/' for faster CI re-runs (mount with actions/cache)",
                group: 'Options for CI integration',
            ),
            new OptionDefinition(
                name: 'cache-dir',
                takesValue: true,
                valuePlaceholder: '<path>',
                description: "Read/write cache from <path> (implies --cache; overrides default directory)",
                group: 'Options for CI integration',
            ),
            new OptionDefinition(
                name: 'incremental',
                description: "Per-file incremental index: re-tokenize only changed files (rabin-karp only; uses the cache directory)",
                group: 'Options for CI integration',
            ),
            new OptionDefinition(
                name: 'help',
                short: 'h',
                description: 'Print this help',
                group: 'General options',
            ),
            new OptionDefinition(
                name: 'version',
                short: 'v',
                description: 'Print version information',
                group: 'General options',
            ),
        ];
    }

    public static function help(): string
    {
        $definitions = self::definitions();

        $visible = array_filter($definitions, static fn(OptionDefinition $d) => !$d->advanced);

        $invocations = [];
        $width       = 0;

        foreach ($visible as $definition) {
            $invocation                      = self::invocation($definition);
            $invocations[$definition->name]  = $invocation;
            $width                           = max($width, strlen($invocation));
        }

        $help      = 'Usage:' . PHP_EOL . '  phpcpd [options] <directory>' . PHP_EOL;
        $lastGroup = '';

        foreach ($visible as $definition) {
            if ($definition->group !== $lastGroup) {
                $help     .= PHP_EOL . $definition->group . ':' . PHP_EOL . PHP_EOL;
                $lastGroup = $definition->group;
            }

            $help .= sprintf(
                '  %s  %s%s',
                str_pad($invocations[$definition->name], $width),
                $definition->description,
                PHP_EOL,
            );
        }

        return $help . PHP_EOL;
    }

    private static function invocation(OptionDefinition $definition): string
    {
        $invocation = '--' . $definition->name;

        if ($definition->short !== null) {
            $invocation = '-' . $definition->short . ', ' . $invocation;
        }

        if ($definition->valuePlaceholder !== '') {
            $invocation .= ' ' . $definition->valuePlaceholder;
        }

        return trim($invocation);
    }
}
