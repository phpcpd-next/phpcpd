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
use function dirname;
use function fnmatch;
use function realpath;
use function str_ends_with;
use function strlen;
use function strrpos;
use function substr;

/**
 * What {@see IdiomScanner} found across the whole scanned set, in the shape the
 * decision tree asks its questions in.
 *
 * Kept apart from {@see CollectedSymbols}'s three maps because it answers a
 * different question. Those say whether a name is mentioned; this says whether
 * some *other* file constructs the name at runtime — which is exactly the case
 * where no mention exists to find.
 *
 * The queries cost no I/O beyond resolving a directory, so the decision tree can
 * ask them before it reaches the rules that sweep the filesystem: a symbol these
 * account for never provokes a config or template sweep.
 */
final readonly class IdiomIndex
{
    /**
     * @param array<string, list<array{pattern: string, at: string}>> $discoveries
     *        resolved directory => the discovery loops that read it, each with the
     *        filename pattern it selects and the "file:line" it was found at.
     *        A list rather than one entry because two files may each discover
     *        into the same directory under different patterns.
     * @param array<string, array<string, string>> $conventions
     *        literal suffix => declaring type fqn => the "file:line" that
     *        concatenates it onto a runtime class name.
     * @param array<string, list<string>> $traitUses
     *        type fqn => short names of the traits used in its body. The half of
     *        the convention claim that keeps it from being a name pattern.
     */
    public function __construct(
        public array $discoveries = [],
        public array $conventions = [],
        public array $traitUses = [],
    ) {}

    /**
     * Where the loop that discovers $file is, or null when no loop reaches it.
     *
     * The pattern match is the precision guard. A class sitting in a discovered
     * directory whose filename the loop's glob does not select is not wired by
     * that loop, and stays reported — the difference between a rule that spares
     * live endpoints and one that merely makes the number go down.
     */
    public function discoveredBy(string $file): ?string
    {
        $directory = realpath(dirname($file));

        if ($directory === false) {
            return null;
        }

        $base = basename($file);

        foreach ($this->discoveries[$directory] ?? [] as $loop) {
            if (fnmatch($loop['pattern'], $base)) {
                return $loop['at'];
            }
        }

        return null;
    }

    /**
     * Is $name the companion class some trait's suffix convention produces, and
     * if so, which trait declared it and where?
     *
     * BOTH halves are required, and both are structural:
     *
     *   1. splitting `<X><Suffix>` on a registered suffix leaves a non-empty
     *      `<X>` that exists as a class in the scanned set, and
     *   2. that `<X>` actually uses the trait that declared the suffix.
     *
     * Dropping either turns the rule into "a class ending in Translation", which
     * would spare every same-suffix class in the project — including the ones
     * that are genuinely dead. A companion whose base does not exist, or whose
     * base does not use the trait, stays reported.
     *
     * @param array<string, list<string>> $byName short class name => fqns declared under it
     * @return ?array{trait: string, base: string, at: string}
     */
    public function conventionFor(string $name, array $byName): ?array
    {
        foreach ($this->conventions as $suffix => $declarers) {
            if (!str_ends_with($name, $suffix) || strlen($name) === strlen($suffix)) {
                continue;
            }

            $base = substr($name, 0, -strlen($suffix));

            foreach ($byName[$base] ?? [] as $candidate) {
                foreach ($this->traitUses[$candidate] ?? [] as $used) {
                    foreach ($declarers as $declarer => $at) {
                        if (self::shortName($declarer) === $used) {
                            return ['trait' => $declarer, 'base' => $candidate, 'at' => $at];
                        }
                    }
                }
            }
        }

        return null;
    }

    private static function shortName(string $qualified): string
    {
        $pos = strrpos($qualified, '\\');

        return $pos === false ? $qualified : substr($qualified, $pos + 1);
    }
}
