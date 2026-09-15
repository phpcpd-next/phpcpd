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

use function count;
use function implode;
use function printf;
use function ucfirst;

use const PHP_EOL;

/**
 * Human-readable console output for an orphan scan — the orphan counterpart to
 * {@see \LucianoPereira\PhpcpdNext\Log\Text}.
 *
 * Findings are grouped by cause rather than listed flat. A flat list repeats one
 * of two sentences across every entry, so a reader has to re-derive each one by
 * hand; grouped, the only line that usually needs reading is the last one — the
 * symbols nothing explains.
 *
 * Suppressed symbols are summarised by rule and listed only under --explain.
 * Counting them in the open is the point: a rule that starts over-firing shows
 * up as a number that moved, which a silent suppression never would.
 *
 * Two entry points for the two run modes:
 *   - printResult()   full report, all tiers — used by `--orphans` (gating).
 *   - printAdvisory() definite orphans plus counts — used in the default
 *                     combined run, where orphans inform but do not fail.
 */
use LucianoPereira\PhpcpdNext\Strings\Catalogue;

final class OrphanTextReport
{
    /** Every sentence this report says; see {@see Catalogue}. */
    public function __construct(private readonly Catalogue $strings = new Catalogue()) {}

    public function printResult(OrphanResult $result, ?OrphanConfiguration $config = null): void
    {
        $config   = $config ?? new OrphanConfiguration();
        $definite = $result->definite();
        $possible = $result->possible();

        if ($result->isEmpty()) {
            print $this->strings->get('report.orphan.none', [
                'symbols' => $result->symbolsScanned,
                'files'   => $result->filesScanned,
            ]) . PHP_EOL;
        }

        if ($definite !== []) {
            print $this->strings->get('report.orphan.found', ['count' => count($definite)]) . PHP_EOL . PHP_EOL;
            $this->printGrouped($definite);
        }

        if ($possible !== []) {
            print $this->strings->get('report.orphan.possible', ['count' => count($possible)]) . PHP_EOL . PHP_EOL;
            $this->printGrouped($possible);
        }

        $this->printPlanned($result->planned());
        $this->printSuppressed($result->suppressed(), $config->explain);

        printf('%s' . PHP_EOL . PHP_EOL, $this->summary($result));
    }

    /**
     * Compact advisory used when orphan detection rides along with a clone scan:
     * only the safe-to-delete findings, and a pointer to `--orphans` for the
     * rest. Does not affect the process exit code.
     */
    public function printAdvisory(OrphanResult $result): void
    {
        $definite = $result->definite();

        if ($definite !== []) {
            print $this->strings->get('report.orphan.advisory', ['count' => count($definite)]) . PHP_EOL . PHP_EOL;
            $this->printGrouped($definite);
        }

        $rest = count($result->possible()) + count($result->planned());

        if ($rest > 0) {
            print $this->strings->get('report.orphan.notShown', ['count' => $rest]) . PHP_EOL . PHP_EOL;
        }
    }

    /** @param list<Orphan> $planned */
    private function printPlanned(array $planned): void
    {
        if ($planned === []) {
            return;
        }

        printf('%s — %d' . PHP_EOL . PHP_EOL, Rule::label(Rule::PLANNED), count($planned));
        $this->printGroup($planned);
    }

    /**
     * A one-line census by rule, expanded to the full list only on request. The
     * counts are always visible so a suppression that starts swallowing real
     * findings is legible without anyone running anything.
     *
     * @param list<Orphan> $suppressed
     */
    private function printSuppressed(array $suppressed, bool $explain): void
    {
        if ($suppressed === []) {
            return;
        }

        if (!$explain) {
            $census = [];

            foreach ($this->byRule($suppressed) as $rule => $group) {
                $census[] = $rule . ' ' . count($group);
            }

            print $this->strings->get('report.orphan.suppressed', [
                'count'  => count($suppressed),
                'census' => implode(' · ', $census),
            ]) . PHP_EOL
                . $this->strings->get('report.orphan.explainHint') . PHP_EOL . PHP_EOL;

            return;
        }

        printf('Suppressed — %d:' . PHP_EOL . PHP_EOL, count($suppressed));

        foreach ($this->byRule($suppressed) as $rule => $group) {
            printf('%s — %d' . PHP_EOL . PHP_EOL, Rule::label($rule), count($group));
            $this->printGroup($group);
        }
    }

    /**
     * Findings, split by the rule that explains them. The residual group — the
     * symbols nothing accounts for — is the one worth reading, and grouping is
     * what makes it visible instead of buried in undifferentiated lines.
     *
     * @param list<Orphan> $orphans
     */
    private function printGrouped(array $orphans): void
    {
        $groups = $this->byRule($orphans);

        if (count($groups) === 1) {
            $this->printGroup($orphans);

            return;
        }

        foreach ($groups as $rule => $group) {
            printf('%s — %d' . PHP_EOL . PHP_EOL, Rule::label($rule), count($group));
            $this->printGroup($group);
        }
    }

    /**
     * @param list<Orphan> $orphans
     * @return array<string, list<Orphan>> rule name ('' when none) => entries
     */
    private function byRule(array $orphans): array
    {
        $groups = [];

        foreach ($orphans as $orphan) {
            $groups[$orphan->rule ?? ''][] = $orphan;
        }

        return $groups;
    }

    /** @param list<Orphan> $orphans */
    private function printGroup(array $orphans): void
    {
        foreach ($orphans as $orphan) {
            $symbol = $orphan->symbol;

            printf(
                '  - %s %s' . PHP_EOL . '    %s:%d' . PHP_EOL . '    → %s' . PHP_EOL,
                ucfirst($symbol->kind),
                $symbol->fqn,
                $symbol->file,
                $symbol->line,
                $orphan->reason,
            );

            if ($orphan->evidence !== null) {
                printf('    ⤷ %s %s' . PHP_EOL, $this->evidenceLabel($orphan), $orphan->evidence);
            }

            if ($orphan->entireFileOrphaned) {
                print $this->strings->get('report.orphan.wholeFile') . PHP_EOL;
            }

            if ($orphan->duplicateOf !== null) {
                print $this->strings->get('report.orphan.supersededBy', ['name' => $orphan->duplicateOf]) . PHP_EOL;
            }

            print PHP_EOL;
        }
    }

    /**
     * One line is the whole verification for most entries; without it, every
     * demotion costs the reader a grep.
     *
     * The idiom rules need their own wording because their evidence is not a
     * mention of the symbol — nothing mentions it, which is the point. It is the
     * site that CONSTRUCTS the name, so the label has to say which construct was
     * found rather than claim the symbol was named there.
     */
    private function evidenceLabel(Orphan $orphan): string
    {
        return match ($orphan->rule) {
            null             => $this->strings->get('explain.orphan.evidence.nameAt'),
            Rule::DISCOVERY  => $this->strings->get('explain.orphan.evidence.loopAt'),
            Rule::CONVENTION => $this->strings->get('explain.orphan.evidence.suffixAt'),
            default          => $this->strings->get('explain.orphan.evidence.namedIn'),
        };
    }

    private function summary(OrphanResult $result): string
    {
        return $this->strings->get('report.orphan.summary', [
            'symbols'  => $result->symbolsScanned,
            'files'    => $result->filesScanned,
            'orphaned' => count($result->definite()),
            'possible' => count($result->possible()),
            'suppressed' => count($result->suppressed()),
            'planned'    => count($result->planned()),
        ]);
    }
}
