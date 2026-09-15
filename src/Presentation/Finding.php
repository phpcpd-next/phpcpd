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

use function abs;
use function count;
use function arsort;
use function implode;
use function sprintf;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\Strings\Catalogue;

/**
 * One clone, as the report presents it: the finding itself, and what the
 * presentation tier can say about it.
 *
 * The clone is carried unchanged. Everything this class adds is *about* the
 * finding rather than part of it, which is why it lives beside `CodeClone`
 * rather than inside it: the detector's value object is what the cache stores
 * and what every engine produces, and a presentation decision must not be able
 * to reach either.
 */
final readonly class Finding
{
    /**
     * @param list<string>          $strata     the demote strata that hold over every site
     * @param float                 $confidence log-odds of "duplicated logic" against "not",
     *                                          from the counts over the recorded label corpus
     * @param array<string, float>  $terms      the per-feature contributions that sum to it
     * @param bool $acknowledged this run's ledger holds an entry matching every
     *                           side's content. A second demote axis, kept apart
     *                           from the strata: the strata are the M5 charter's
     *                           pre-registered classes and are a property of the
     *                           code, while an acknowledgment is a decision this
     *                           project made about it.
     */
    public function __construct(
        public CodeClone $clone,
        public array $strata = [],
        public float $confidence = 0.0,
        public array $terms = [],
        public bool $acknowledged = false,
        /**
         * How many literal values differ between the copies.
         *
         * Not a third demote axis and not part of any verdict: a fact the
         * normalized view had swallowed, carried so the report can state it.
         * {@see LiteralDivergence} for why it is worth stating.
         */
        public int $literalDivergences = 0,
        /**
         * The functions the lead site's reported range lands in, in source order.
         *
         * A token run does not begin or end where a function does, and snapping
         * it to those boundaries was measured and refused. This is the reader's
         * way back to what the range is: `MetadataTest.php:5716-5780` says
         * nothing, `testCanBeRetry` says everything.
         *
         * @var list<string>
         */
        public array $functions = [],
        /**
         * Each occurrence's reported extent, as whole lines, keyed by
         * {@see CodeCloneFile::$id}.
         *
         * Empty where the tier could not read the file or the run held no whole
         * line, and {@see span()} then falls back to what the engine reported.
         * The engine's own numbers are never overwritten: this is a second
         * reading of the same match, in the unit the report prints.
         *
         * @var array<string, array{0: int, 1: int}>
         */
        public array $spans = [],
    ) {}

    /**
     * Where this occurrence is, as the report states it: first line and last.
     *
     * Every format asks here rather than reading `startLine` and `lastLine()`
     * itself, so the console and the log files cannot disagree about the extent
     * — the same reason one {@see Presenter} serves all four.
     *
     * @return array{0: int, 1: int}
     */
    public function span(CodeCloneFile $site): array
    {
        return $this->spans[$site->id]
            ?? [$site->startLine, $site->lastLine($this->clone->numberOfLines())];
    }

    /**
     * Is this finding **asserted** — the stratum ruling U's restated bar applies
     * to — or **demoted**?
     */
    public function demoted(): bool
    {
        return $this->strata !== [] || $this->acknowledged;
    }

    /** The demote tags as one comma-separated label, or the empty string. */
    public function tag(): string
    {
        $tags = $this->strata;

        if ($this->acknowledged) {
            $tags[] = 'acknowledged';
        }

        return implode(', ', $tags);
    }

    /**
     * The score and the terms that carried it, largest contribution first — the
     * "ship evidence with every number" rule, applied to a rank.
     *
     * A reader who disagrees with where a finding was placed can see which
     * bucket did it, and argue with the bucket instead of with the number.
     */
    public function why(int $limit = 3): string
    {
        $terms = $this->terms;
        $ranked = [];

        foreach ($terms as $name => $value) {
            $ranked[$name] = abs($value);
        }

        arsort($ranked);
        $parts = [];

        foreach ($ranked as $name => $_) {
            if (count($parts) >= $limit) {
                break;
            }

            $parts[] = sprintf('%s %+.2f', $name, $terms[$name]);
        }

        return (new Catalogue())->get('report.clones.confidence', [
            'score' => sprintf('%+.2f', $this->confidence),
            'terms' => implode(', ', $parts),
        ]);
    }
}
