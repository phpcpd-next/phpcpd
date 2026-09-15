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

namespace LucianoPereira\PhpcpdNext\Log;

use function count;
use function implode;
use function printf;
use function sprintf;

use const PHP_EOL;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Console\Ink;
use LucianoPereira\PhpcpdNext\Console\Style;
use LucianoPereira\PhpcpdNext\Presentation\Finding;
use LucianoPereira\PhpcpdNext\Strings\Catalogue;
use LucianoPereira\PhpcpdNext\Presentation\Findings;

/**
 * The console report.
 *
 * Findings in the order the presentation tier put them in, highest confidence
 * first, each naming every place it occurs. The first of those places carries
 * what the run decided about the finding — its length, whether the copies
 * diverge, which stratum demoted it if one did, and the score that ranked it —
 * because that is the line a reader stops on, and the rest are the same finding
 * seen again.
 *
 * The rank is printed rather than merely applied. A reader who disagrees with
 * where a finding sits can see the number that put it there, and `--verbose`
 * names the buckets that number came from. Before that, a demoted *file* was
 * named once in a triage summary and the findings inside it looked exactly like
 * every other finding — the file-list-granularity gap the M4 packet recorded.
 */
final class Text
{
    /** Two spaces for the report's own indent, four for anything beneath a finding. */
    private const string INDENT = '  ';
    private const string DETAIL = '    ';

    /**
     * Plain unless the caller has a terminal that takes colour. Colour is only
     * ever emphasis on text that is already there, so a piped run, a golden
     * capture and a coloured run differ by escape sequences and nothing else.
     */
    private readonly Ink $ink;
    private readonly ReportPath $path;

    /** Every sentence this reporter says; see {@see Catalogue}. */
    private readonly Catalogue $strings;

    public function __construct(?Ink $ink = null, ?ReportPath $path = null, ?Catalogue $strings = null)
    {
        $this->ink     = $ink ?? Ink::plain();
        $this->path    = $path ?? ReportPath::fromWorkingDirectory();
        $this->strings = $strings ?? new Catalogue();
    }

    /**
     * `$listHidden` is `--hidden`, and is deliberately not `$verbose`: verbose
     * says how much to show about a finding, this says which findings there
     * are. A reader who wants the quiet report in detail must not be forced to
     * take back the findings they filtered out.
     */
    public function printResult(Findings $findings, bool $verbose, bool $listHidden = false): void
    {
        $clones = $findings->clones;

        $this->printHeading($clones);

        foreach ($findings->visible() as $finding) {
            $this->printFinding($finding, $verbose);
        }

        // Before the empty-result return, not after it. A run that could not
        // read a file is exactly the run most likely to find nothing, and
        // "No code clones found" is the wrong thing to say on its own when part
        // of the scan never happened.
        $this->printUnreadable($clones);

        if ($clones->isEmpty()) {
            print $this->strings->get('report.clones.none') . PHP_EOL . PHP_EOL;

            return;
        }

        $this->printStrata($findings);
        $this->printSettled($clones);
        $this->printUnfounded($clones);
        $this->printHidden($findings, $listHidden);
        $this->printLedger($findings);
        $this->printTotals($clones);
    }

    /** What was found, before any of it is listed. */
    private function printHeading(CodeCloneMap $clones): void
    {
        if (count($clones) === 0) {
            return;
        }

        $gapped    = $clones->numberOfGappedClones();
        $reordered = $clones->numberOfReorderedClones();

        print $this->strings->get('report.clones.heading', [
            'clones' => count($clones),
            'gapped' => $gapped > 0
                ? $this->ink->paint(Style::Divergence, $this->strings->get('report.clones.gapped', ['count' => $gapped]))
                : '',
            'reordered' => $reordered > 0
                ? $this->ink->paint(Style::Divergence, $this->strings->get('report.clones.reorderedCount', ['count' => $reordered]))
                : '',
            'lines'  => $clones->numberOfDuplicatedLines(),
            'files'  => $clones->numberOfFilesWithClones(),
        ]) . PHP_EOL . PHP_EOL;
    }

    /** One finding: where it occurs, what to do about it, and why it ranks where it does. */
    private function printFinding(Finding $finding, bool $verbose): void
    {
        $clone = $finding->clone;
        $lead  = true;

        foreach ($clone->files() as $occurrence) {
            // The occurrence's own extent, in whole lines, from the one place
            // that decides it. `Finding::span()` exists so this rule lives in
            // one place: copies need not agree on how many source lines they
            // span, since the matchers compare significant tokens and comments
            // between them are free. Adding the clone's length to this site's
            // start got both halves wrong — it borrowed the class's length for
            // every member, and it counted the start line twice, so `3-33` was
            // printed beside `(30 lines)` for a clone ending at 32.
            [$from, $to] = $finding->span($occurrence);

            $location = sprintf(
                '%s:%d-%d',
                $this->path->of($occurrence->name),
                $from,
                $to,
            );

            printf(
                '%s%s%s%s' . PHP_EOL,
                self::INDENT,
                $lead ? '- ' : self::INDENT,
                $lead ? $location : $this->ink->paint(Style::Sibling, $location),
                $lead ? $this->verdict($finding) : '',
            );

            $lead = false;
        }

        // Which functions the reported range lands in.
        //
        // A reported extent is a token run, and a token run need not begin or
        // end where a function does — at the shipped default 20% of
        // php-parser's sites start at a body start and 60% of
        // symfony-console's do. Snapping the extent to those boundaries is
        // refused: outward invents 33.5% to 69.5% of tokens that never
        // matched, inward drops 25.4% to 33.6% that did. So the range stays
        // exact and the reader is told where it lands, which is the thing a
        // line number cannot say.
        $functions = $finding->functions;

        if ($functions !== []) {
            printf(
                '%s· %s' . PHP_EOL,
                self::DETAIL,
                $this->strings->get('report.clones.functions', ['names' => self::nameList($functions)]),
            );
        }

        printf('%s→ %s' . PHP_EOL, self::DETAIL, $this->ink->paint(Style::Advice, $this->suggestion($finding)));

        if ($verbose) {
            printf('%s· %s' . PHP_EOL, self::DETAIL, $finding->why());
            print PHP_EOL . $clone->lines(self::DETAIL);
        }

        print PHP_EOL;
    }

    /**
     * Function names, three of them and then a count.
     *
     * A span through a run of near-identical methods can touch ninety, and a
     * ninety-name line is one nobody reads — the same reason
     * `superset_short_label()` truncates. Three is what fits beside the range
     * it annotates.
     *
     * @param list<string> $names
     */
    private static function nameList(array $names): string
    {
        if (count($names) <= 4) {
            return implode(', ', $names);
        }

        return implode(', ', array_slice($names, 0, 3)) . sprintf(' and %d more', count($names) - 3);
    }

    /**
     * The occurrence a finding is printed under — the first, which is the one
     * carrying the verdict.
     */
    private function lead(CodeClone $clone): CodeCloneFile
    {
        foreach ($clone->files() as $site) {
            return $site;
        }

        throw new \LogicException('a clone always names at least two occurrences');
    }

    /** What this run made of the finding, on the line a reader stops at. */
    private function verdict(Finding $finding): string
    {
        $clone   = $finding->clone;
        $demoted = $finding->demoted();

        // The lead site's own length, from the same place its range came from.
        // It used to be the class's length, which is a different number the
        // moment the report states an extent in whole lines: `292-305 (17
        // lines)` is the `3-33 (30 lines)` contradiction this file already
        // fixed once, reintroduced from the other side.
        [$from, $to] = $finding->span($this->lead($clone));
        $verdict     = ' (' . ($to + 1 - $from) . ' lines)';

        // Two claims, not one severity. `[inconsistent]` says the copies
        // diverge — the bug-prone kind, one patched and its sibling not.
        // `[reordered]` says the material is all present in another sequence,
        // which is what an order-free engine establishes and the only thing it
        // can: the token bag knows two blocks hold the same tokens and nothing
        // about where they moved. Printing "exact" for that was the previous
        // answer and printing "inconsistent" would be the next wrong one.
        if ($clone->isReordered()) {
            $verdict .= $this->ink->paint(Style::Divergence, ' ' . $this->strings->get('report.clones.reordered'));
        } elseif ($clone->isGapped()) {
            $verdict .= $this->ink->paint(Style::Divergence, ' [inconsistent]');
        }

        // The copies differ in a value the matcher folded to see them as one.
        // Said separately from `[inconsistent]`, which names a structural gap:
        // this is the same skeleton carrying different constants, and it is the
        // shape of "one copy patched, the sibling not" that leaves no gap
        // behind at all.
        if ($finding->literalDivergences > 0) {
            $verdict .= $this->ink->paint(
                Style::Divergence,
                ' ' . $this->strings->get('report.clones.literals', [
                    'count' => $finding->literalDivergences,
                ]),
            );
        }

        if ($demoted) {
            $verdict .= $this->ink->paint(Style::Demoted, ' [demoted: ' . $finding->tag() . ']');
        }

        $rank = sprintf(' %+.2f', $finding->confidence);

        return $verdict . ($demoted ? $this->ink->paint(Style::Demoted, $rank) : $rank);
    }

    /**
     * Files this run could not open, named.
     *
     * Printed beside the totals because that is what they change: a file that
     * could not be read contributes no lines, so the percentage is over a
     * denominator smaller than the scan the user asked for.
     */
    private function printUnreadable(CodeCloneMap $clones): void
    {
        $unreadable = $clones->unreadableFiles();

        if ($unreadable === []) {
            return;
        }

        print $this->strings->get('report.clones.unreadable', ['count' => count($unreadable)]) . PHP_EOL;

        foreach ($unreadable as $file) {
            printf('%s%s' . PHP_EOL, self::INDENT, $this->ink->paint(Style::Problem, $this->path->of($file)));
        }
    }

    /**
     * The stratification, counted — always, and with every stratum's zero shown.
     *
     * A count that appears only when it is non-zero is a count a reader cannot
     * calibrate, and the auditor's condition on the stratified bar is that the
     * split can never hide anything: both halves are reported, every run.
     */
    private function printStrata(Findings $findings): void
    {
        if ($findings->demoted() === 0) {
            print $this->strings->get('report.clones.strataAsserted', ['asserted' => $findings->asserted()]) . PHP_EOL;

            return;
        }

        $parts = [];

        foreach ($findings->perStratum() as $stratum => $count) {
            $parts[] = sprintf('%s %d', $stratum, $count);
        }

        print $this->strings->get('report.clones.strataSplit', [
            'asserted' => $findings->asserted(),
            'demoted'  => $findings->demoted(),
            'detail'   => implode(' · ', $parts),
        ]) . PHP_EOL;
    }

    /**
     * How many readings the run dropped as already described elsewhere.
     *
     * Printed only when there were any: unlike the stratum and ledger lines,
     * this is not a figure a reader calibrates against, it is an explanation
     * for a count being smaller than the scan produced. Zero explains nothing.
     */
    private function printSettled(CodeCloneMap $clones): void
    {
        $settled = $clones->numberOfSettledClones();

        if ($settled === 0) {
            return;
        }

        print $this->strings->get('report.clones.settled', ['count' => $settled]) . PHP_EOL;
    }

    /**
     * How many findings were removed because nothing verified them.
     *
     * Its own line rather than folded into the one above: a settled reading
     * described real duplication that another finding described better, and an
     * unfounded one described duplication that was not there. A reader chasing
     * a count that shrank wants to know which happened.
     */
    private function printUnfounded(CodeCloneMap $clones): void
    {
        $unfounded = $clones->numberOfUnfoundedClones();

        if ($unfounded === 0) {
            return;
        }

        print $this->strings->get('report.clones.unfounded', ['count' => $unfounded]) . PHP_EOL;
    }

    /**
     * What the reader's threshold held back.
     *
     * Printed whenever a threshold is in force, zero included: a count that
     * appears only when it is non-zero is a count nobody can calibrate, which
     * is the rule the stratum and ledger lines already follow. Saying nothing
     * when a filter is active is how a report becomes a suppression mechanism
     * wearing a report's clothes.
     */
    private function printHidden(Findings $findings, bool $list): void
    {
        if ($findings->minConfidence === null) {
            return;
        }

        $hidden = $findings->hidden();

        print $this->strings->get('report.clones.hiddenLine', [
            'count'     => count($hidden),
            'total'     => $findings->count(),
            'threshold' => sprintf('%+.2f', $findings->minConfidence),
        ]) . PHP_EOL;

        if (!$list || $hidden === []) {
            return;
        }

        print PHP_EOL . $this->strings->get('report.clones.hiddenHeading', [
            'count'     => count($hidden),
            'threshold' => sprintf('%+.2f', $findings->minConfidence),
        ]) . PHP_EOL . PHP_EOL;

        foreach ($hidden as $finding) {
            $this->printFinding($finding, false);
        }
    }

    /**
     * The ledger's own line, printed whenever a ledger was consulted — including
     * when it acknowledged nothing, because a count that appears only when it is
     * non-zero cannot be calibrated.
     *
     * Stale entries are named rather than counted. An entry that matches nothing
     * is a decision about code that no longer exists, and the only useful thing
     * to say about it is which line of the file to delete.
     */
    private function printLedger(Findings $findings): void
    {
        if (!$findings->ledger) {
            return;
        }

        $stale = count($findings->stale);

        print $this->strings->get('report.ledger.line', [
            'acknowledged' => $findings->acknowledged(),
            'total'        => $findings->count(),
            'stale'        => $stale,
        ]) . PHP_EOL;

        foreach ($findings->stale as $note) {
            print self::INDENT . $this->strings->get('report.ledger.staleNote', ['note' => $note]) . PHP_EOL;
        }
    }

    /**
     * The totals, worded as what they measure.
     *
     * "Duplicated lines out of total lines" invited the reading that made the
     * old arithmetic printable: a percentage that could exceed a hundred,
     * because one region counted once per clone that touched it. The figure is
     * the coverage union now — ConQAT's and Teamscale's definition, bounded by
     * the size of the code by construction.
     *
     * Of that union it counts the lines holding a token the matchers can see,
     * so the sentence no longer says "lie inside at least one clone": a clone's
     * line range runs from its first matched token to its last and carries
     * every docblock in between, and those were never compared with anything.
     * See {@see \LucianoPereira\PhpcpdNext\Util\CodeLines}.
     */
    private function printTotals(CodeCloneMap $clones): void
    {
        print $this->strings->get('report.clones.coverage', [
            'percentage' => $clones->percentage(),
            'lines'      => $clones->numberOfLines(),
        ]) . PHP_EOL
            . $this->strings->get('report.clones.sizes', [
                // `averageSize()` is a float and the sentence has always shown
                // it whole — `printf('%d')` truncated it silently. The
                // catalogue substitutes what it is given, so the rounding is
                // stated here rather than hidden in a format specifier.
                'average' => (int) $clones->averageSize(),
                'largest' => $clones->largestSize(),
            ]) . PHP_EOL . PHP_EOL;
    }

    /**
     * What to do about a finding, from what this run decided about it.
     *
     * It used to answer "is this a test?" with `str_contains($path, 'test')`,
     * and so told the author of `app/Mail/AdminTestMail.php`,
     * `TestRuleFormRequest.php` and `OwnerTestNotificationPushover.php` to reach
     * for a `@dataProvider`. Any project checked out under `latest/` or
     * `contest/` had every finding mislabelled, because the match was against
     * the whole path. {@see \LucianoPereira\PhpcpdNext\Facts\FileRole} states
     * the rule this broke: role features are content- and wiring-derived, never
     * path patterns — and it has no test role to ask, so there was nothing to
     * switch the question to.
     *
     * The size threshold went with it. Fifty lines meant "extract a class rather
     * than a constant", which is a judgement about shape wearing a number, and
     * the number was never derived from anything.
     *
     * What is left is what the run actually established: whether the copies
     * diverge, and which stratum — if any — demoted the finding. Both are
     * computed from the code, and neither is a guess about the file's purpose.
     */
    private function suggestion(Finding $finding): string
    {
        if ($finding->clone->isGapped()) {
            return $this->strings->get('advise.clone.gapped');
        }

        if ($finding->strata !== []) {
            return $this->strings->get('advise.clone.demoted', ['stratum' => $finding->tag()]);
        }

        return $this->strings->get('advise.clone.extract');
    }
}
