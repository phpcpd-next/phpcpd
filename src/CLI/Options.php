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
use function implode;
use function max;
use function sprintf;
use function str_pad;
use function str_repeat;
use function strlen;
use function trim;
use function wordwrap;

use const PHP_EOL;

use LucianoPereira\PhpcpdNext\Console\Terminal;
use LucianoPereira\PhpcpdNext\Orphan\Rule;

/**
 * The phpcpd-next option set — declared once. The parser, the --help text, and
 * the {@see ConfigReport} table all derive from this list; what each option DOES
 * to a run lives as its arm in the {@see Settings} fold, which this suite's
 * drift guard keeps total. A new option is therefore one definition here plus
 * one arm there, and the two cannot silently disagree.
 */
use LucianoPereira\PhpcpdNext\Strings\Catalogue;

final class Options
{
    /** @return list<OptionDefinition> */
    public static function definitions(): array
    {
        // The defaults a description quotes come from the settings themselves,
        // not from the sentence. `--help` claimed `default: 70` for a fortnight
        // after the default became 100, because the number was prose; a fact the
        // code already knows should never be retyped where it can drift.
        $defaults = Settings::resolve([]);

        return [
            new OptionDefinition(
                name: 'suffix',
                takesValue: true,
                repeatable: true,
                valuePlaceholder: '<suffix>',
                description: 'suffix',
                descriptionParameters: ['default' => implode(', ', $defaults->suffixes)],
                group: 'selecting',
            ),
            new OptionDefinition(
                name: 'exclude',
                takesValue: true,
                repeatable: true,
                valuePlaceholder: '<path>',
                description: 'exclude',
                group: 'selecting',
            ),
            new OptionDefinition(
                name: 'preset',
                takesValue: true,
                allowedValues: Presets::names(),
                valuePlaceholder: '<name>',
                description: 'preset',
                group: 'selecting',
            ),
            new OptionDefinition(
                name: 'triage',
                description: 'triage',
                group: 'selecting',
            ),
            new OptionDefinition(
                name: 'no-triage',
                description: 'no_triage',
                group: 'selecting',
            ),
            new OptionDefinition(
                name: 'triage-posture',
                takesValue: true,
                allowedValues: ['label', 'discard'],
                valuePlaceholder: '<posture>',
                description: 'triage_posture',
                group: 'selecting',
            ),
            new OptionDefinition(
                name: 'no-preset',
                description: 'no_preset',
                group: 'selecting',
            ),
            new OptionDefinition(
                name: 'no-default-excludes',
                description: 'no_default_excludes',
                group: 'selecting',
            ),
            new OptionDefinition(
                name: 'allow-root-scan',
                description: 'allow_root_scan',
                group: 'selecting',
            ),
            new OptionDefinition(
                name: 'orphans',
                description: 'orphans',
                group: 'orphans',
            ),
            new OptionDefinition(
                name: 'no-suppress',
                takesValue: true,
                repeatable: true,
                allowedValues: [...Rule::names(), 'all'],
                valuePlaceholder: '<rules>',
                description: 'no_suppress',
                descriptionParameters: ['rules' => implode(', ', Rule::names())],
                group: 'orphans',
                listValue: true,
            ),
            new OptionDefinition(
                name: 'fail-on',
                takesValue: true,
                allowedValues: ['dead', 'possible', 'planned', 'suppressed'],
                valuePlaceholder: '<tiers>',
                description: 'fail_on',
                descriptionParameters: ['default' => implode(', ', $defaults->failOn)],
                group: 'orphans',
                listValue: true,
            ),
            new OptionDefinition(
                name: 'explain',
                description: 'explain',
                group: 'orphans',
            ),
            new OptionDefinition(
                name: 'rk',
                description: 'rk',
                group: 'analysing',
            ),
            new OptionDefinition(
                name: 'min-lines',
                takesValue: true,
                valuePlaceholder: '<N>',
                description: 'min_lines',
                descriptionParameters: ['default' => (string) $defaults->minLines],
                group: 'analysing',
            ),
            new OptionDefinition(
                name: 'min-tokens',
                takesValue: true,
                valuePlaceholder: '<N>',
                description: 'min_tokens',
                descriptionParameters: ['default' => (string) $defaults->minTokens],
                group: 'analysing',
            ),
            new OptionDefinition(
                name: 'language',
                takesValue: true,
                // Discovered rather than listed: the allowed set is whatever
                // `locale/` holds, so adding a translation needs no edit here
                // and `--help` names it the day the file lands.
                allowedValues: Catalogue::available(),
                valuePlaceholder: '<code>',
                description: 'language',
                descriptionParameters: ['default' => $defaults->language],
                group: 'general',
            ),
            new OptionDefinition(
                name: 'verbose',
                description: 'verbose',
                group: 'analysing',
            ),
            // Research / benchmark flags — parsed but hidden from --help.
            new OptionDefinition(
                name: 'algorithm',
                takesValue: true,
                allowedValues: ['rabin-karp', 'tokenbag', 'unified'],
                valuePlaceholder: '<name>',
                description: 'algorithm',
                group: 'analysing',
                advanced: true,
            ),
            new OptionDefinition(
                name: 'raw',
                description: 'raw',
                group: 'analysing',
            ),
            new OptionDefinition(
                name: 'fuzzy',
                description: 'fuzzy',
                group: 'analysing',
                advanced: true,
            ),
            new OptionDefinition(
                name: 'type-anchored',
                description: 'type_anchored',
                group: 'analysing',
                advanced: true,
            ),
            new OptionDefinition(
                name: 'min-similarity',
                takesValue: true,
                valuePlaceholder: '<0-1>',
                description: 'min_similarity',
                descriptionParameters: ['default' => (string) $defaults->minSimilarity],
                group: 'analysing',
                advanced: true,
            ),
            new OptionDefinition(
                name: 'min-confidence',
                takesValue: true,
                valuePlaceholder: '<log-odds>',
                description: 'min_confidence',
                group: 'reporting',
            ),
            new OptionDefinition(
                name: 'hidden',
                description: 'hidden',
                group: 'reporting',
            ),
            new OptionDefinition(
                name: 'acknowledged',
                takesValue: true,
                valuePlaceholder: '<file>',
                description: 'acknowledged',
                group: 'reporting',
            ),
            new OptionDefinition(
                name: 'write-acknowledged',
                takesValue: true,
                valuePlaceholder: '<file>',
                description: 'write_acknowledged',
                group: 'reporting',
            ),
            new OptionDefinition(
                name: 'log-pmd',
                takesValue: true,
                valuePlaceholder: '<file>',
                description: 'log_pmd',
                group: 'reporting',
            ),
            new OptionDefinition(
                name: 'log-json',
                takesValue: true,
                valuePlaceholder: '<file>',
                description: 'log_json',
                group: 'reporting',
            ),
            new OptionDefinition(
                name: 'log-sarif',
                takesValue: true,
                valuePlaceholder: '<file>',
                description: 'log_sarif',
                group: 'reporting',
            ),
            new OptionDefinition(
                name: 'cache',
                description: 'cache',
                group: 'ci',
            ),
            new OptionDefinition(
                name: 'cache-dir',
                takesValue: true,
                valuePlaceholder: '<path>',
                description: 'cache_dir',
                group: 'ci',
            ),
            new OptionDefinition(
                name: 'incremental',
                description: 'incremental',
                group: 'ci',
            ),
            new OptionDefinition(
                name: 'config',
                takesValue: true,
                valuePlaceholder: '<file>',
                description: 'config',
                group: 'general',
            ),
            new OptionDefinition(
                name: 'show-config',
                description: 'show_config',
                group: 'general',
            ),
            new OptionDefinition(
                name: 'no-config',
                description: 'no_config',
                group: 'general',
            ),
            new OptionDefinition(
                name: 'help',
                short: 'h',
                description: 'help',
                group: 'general',
            ),
            new OptionDefinition(
                name: 'version',
                short: 'v',
                description: 'version',
                group: 'general',
            ),
        ];
    }

    /**
     * The narrowest a description column may get.
     *
     * On a very narrow terminal the option column can eat most of the line, and
     * wrapping what is left produces a column of two-word fragments. Below this
     * the text is better allowed to overrun than shredded.
     */
    private const int NARROWEST_DESCRIPTION = 24;

    /**
     * The option list, wrapped to the terminal.
     *
     * It used to append each description raw. That is fine until a description
     * is long, and eleven of them are: `--help` ran to 205 columns with 23 of
     * its 53 lines past 80, so on an ordinary terminal nearly half of it wrapped
     * — and wrapped at column zero, which destroys the very alignment the
     * padding exists to create. A reader lost the option column exactly where
     * the text was hardest to follow.
     *
     * `wordwrap()` already does the work; what was missing was a width to give
     * it and an indent to continue at.
     */
    public static function help(?Terminal $terminal = null, ?Catalogue $strings = null): string
    {
        $terminal    = $terminal ?? Terminal::detect();
        $strings     = $strings ?? new Catalogue();
        $definitions = self::definitions();

        $visible = array_filter($definitions, static fn(OptionDefinition $d) => !$d->advanced);

        $invocations = [];
        $width       = 0;

        foreach ($visible as $definition) {
            $invocation                      = self::invocation($definition);
            $invocations[$definition->name]  = $invocation;
            $width                           = max($width, strlen($invocation));
        }

        $help      = $strings->get('help.frame.usage') . PHP_EOL . $strings->get('help.frame.invocation') . PHP_EOL;
        $lastGroup = '';

        foreach ($visible as $definition) {
            if ($definition->group !== $lastGroup) {
                $help     .= PHP_EOL . $strings->get('help.group.' . $definition->group) . ':' . PHP_EOL . PHP_EOL;
                $lastGroup = $definition->group;
            }

            // Continuations line up under the description, not under the
            // option, so the two columns stay two columns however narrow the
            // terminal is.
            $indent = 2 + $width + 2;
            $room   = max(self::NARROWEST_DESCRIPTION, $terminal->measure() - $indent);

            $help .= sprintf(
                '  %s  %s%s',
                str_pad($invocations[$definition->name], $width),
                wordwrap(
                    $strings->get('help.option.' . $definition->description, $definition->descriptionParameters),
                    $room,
                    PHP_EOL . str_repeat(' ', $indent),
                ),
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
