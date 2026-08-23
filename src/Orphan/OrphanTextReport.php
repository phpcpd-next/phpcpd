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
use function sprintf;
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
final class OrphanTextReport
{
    public function printResult(OrphanResult $result, ?OrphanConfiguration $config = null): void
    {
        $config   = $config ?? new OrphanConfiguration();
        $definite = $result->definite();
        $possible = $result->possible();

        if ($result->isEmpty()) {
            printf(
                'No orphaned symbols found (%d symbols in %d files).' . PHP_EOL,
                $result->symbolsScanned,
                $result->filesScanned,
            );
        }

        if ($definite !== []) {
            printf('Found %d orphaned symbol(s):' . PHP_EOL . PHP_EOL, count($definite));
            $this->printGrouped($definite);
        }

        if ($possible !== []) {
            printf('Found %d possible orphan(s) — review before removing:' . PHP_EOL . PHP_EOL, count($possible));
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
            printf(
                'Orphaned symbols (advisory — does not affect exit code): %d' . PHP_EOL . PHP_EOL,
                count($definite),
            );
            $this->printGrouped($definite);
        }

        $rest = count($result->possible()) + count($result->planned());

        if ($rest > 0) {
            printf('%d further orphan finding(s) not shown — run with --orphans to review them.' . PHP_EOL . PHP_EOL, $rest);
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

            printf(
                'Suppressed (%d): %s' . PHP_EOL . '  → --explain to list them' . PHP_EOL . PHP_EOL,
                count($suppressed),
                implode(' · ', $census),
            );

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
                printf('    ⤷ whole file is unwired — no symbol declared here is referenced' . PHP_EOL);
            }

            if ($orphan->duplicateOf !== null) {
                printf('    ⤷ looks like a superseded copy of %s' . PHP_EOL, $orphan->duplicateOf);
            }

            print PHP_EOL;
        }
    }

    /**
     * One line is the whole verification for most entries; without it, every
     * demotion costs the reader a grep.
     */
    private function evidenceLabel(Orphan $orphan): string
    {
        return $orphan->rule === null ? 'name appears at' : 'named in';
    }

    private function summary(OrphanResult $result): string
    {
        return sprintf(
            '%d symbols scanned in %d files; %d orphaned, %d possible, %d suppressed, %d planned.',
            $result->symbolsScanned,
            $result->filesScanned,
            count($result->definite()),
            count($result->possible()),
            count($result->suppressed()),
            count($result->planned()),
        );
    }
}
