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

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\Facts\FileFactsIndex;

/**
 * The M5 charter's demote strata, as membership tests over a finding.
 *
 * ## What a stratum is, and what it is not
 *
 * A stratum decides how a finding is **presented**, never whether it is found.
 * A demoted finding is still detected, still counted, still printed, still
 * exported to every format, and still gates the exit code exactly as before.
 * Nothing in this class removes anything: the M5 pre-commitment experiment
 * measured that no rule derivable from the project's own labels reaches the
 * 0.80 bar by silencing, and established that what those labels *do* license is
 * demotion — zero of 56 consensus-Y findings sit in the table scope, so tagging
 * that class costs nothing the raters valued, while the constructed seeder
 * negative shows silencing it would be wrong.
 *
 * ## The strata, pre-registered before the pool was drawn
 *
 *   - **table** — every site sits wholly inside a statement-free array-literal
 *     frame, **or** inside one and the same run of literal appends to one array.
 *     The dead rule 9's *scope*, reused whole and with **no floor**: the
 *     literal-overlap statistic, its undrivable threshold and the counterfactual
 *     are all discarded, and what is kept is a membership test carrying no
 *     constant. The second spelling was added because data does not have to be
 *     written as an array: a seeder builds the same table out of `$rows[] = […];`
 *     statements, which no statement-free frame can contain. Two sites in two
 *     *different* runs are not in scope — that identity is
 *     {@see \LucianoPereira\PhpcpdNext\Facts\RegionStructure::sameLiteralTable()}'s,
 *     and the M5 round called copied seeders genuine duplication.
 *   - **registration** — every site sits in **one** registration-role file
 *     ({@see \LucianoPereira\PhpcpdNext\Facts\FileRole}), which is decided from
 *     the file's own top-level statements and never from its path. One file,
 *     not merely all-of-them-registration: see *A registration file repeating
 *     itself* below.
 *
 * ## Every site, not any site
 *
 * A finding is in a stratum only when **all** of its sites satisfy that
 * stratum's test. A clone with one site in a route file and one in a controller
 * is a copy of registration *into* program text, and the controller's copy is
 * exactly what a reader wants asserted. It is also the direction the project's
 * cost asymmetry names: a wrongly demoted finding loses a real clone's
 * prominence, a wrongly asserted one costs one noisy line.
 *
 * The composition is the dead rule 9's own — *"only when every pair of its sites
 * is in scope"* — reused unchanged, because for a per-pair test "every pair" and
 * "every site" are the same statement.
 *
 * ## A registration file repeating itself
 *
 * Ruling H keeps one span-level discriminator alive by asking what a thing *is*
 * rather than how much of something it has: "a table matching itself ... its
 * second run is not a copy anyone could remove, it is the regularity that makes
 * it a table". The same question separates two things the registration stratum
 * would otherwise treat alike.
 *
 * A route file repeating itself is a route file. Two *different* route files
 * agreeing is a copy someone could remove, and the reader may well want it.
 * Measured on firefly-iii, the split is 112 self-repeating against 1 crossing
 * for the unified engine and 16 against 5 for the token bag — and every one of
 * those six crossings is the same pair, `routes/api.php` against
 * `routes/web.php`, the same route block written into both surfaces.
 *
 * So the composition is one file rather than all files. It costs one finding of
 * 113 and keeps the whole class the wider reading would have got wrong, which is
 * the direction this project's cost asymmetry always points: six findings kept
 * asserted cost six noisy lines, six wrongly demoted cost a real clone its
 * prominence.
 *
 * ## Strata do not compose
 *
 * A finding whose first site is a table and whose second is a registration file
 * is **asserted**. Demotion requires one stratum to hold over the whole finding.
 * A union of partial evidence would be exactly the hiding place the auditor's
 * condition on the stratified bar forbids.
 */
final readonly class Strata
{
    /** Every site sits wholly inside a statement-free array-literal frame. */
    public const string TABLE = 'table';

    /** Every site sits in a registration-role file. */
    public const string REGISTRATION = 'registration';

    /**
     * The fixed order strata are reported in, so two runs — and two readers —
     * see one order.
     *
     * @var list<string>
     */
    public const array NAMES = [self::TABLE, self::REGISTRATION];

    public function __construct(private FileFactsIndex $facts) {}

    /**
     * Which strata hold over every site of this finding, in {@see NAMES} order.
     *
     * @return list<string>
     */
    public function of(CodeClone $clone): array
    {
        $table        = true;
        $registration = true;
        $lines        = $clone->numberOfLines();
        /** @var array<string, true> $names the files the finding names */
        $names = [];
        /** @var array<string, true> $runs the literal-append runs the finding sits in */
        $runs = [];

        foreach ($clone->files() as $file) {
            $names[$file->name] = true;

            if (!$table && !$registration) {
                break;
            }

            $facts = $this->facts->for($file->name);

            if ($facts === null) {
                // No facts is no evidence, and no evidence keeps the finding
                // asserted.
                $table        = false;
                $registration = false;

                continue;
            }

            $registration = $registration && $facts->role->registration;

            if (!$table) {
                continue;
            }

            $span = $facts->occurrence($clone, $file);

            if ($span === null) {
                $table = false;

                continue;
            }

            // Data written as an array literal, or data written as statements.
            // A seeder builds the same table with a run of `$rows[] = […];`
            // appends, and `literalTable()` cannot see it — that frame has to be
            // statement-free and a run of appends is nothing but statements.
            // The two spellings are one thing to a reader.
            $run = $facts->statements->literalAppendRun($span[0], $span[1]);

            if ($run !== null) {
                $runs[$file->name . ':' . $run] = true;

                continue;
            }

            $table = $facts->regions->literalTable($span[0], $span[1]) !== null;
        }

        $held = [];

        // A finding inside a literal-append run is in scope only when every one
        // of its sites is in the SAME run. That identity is
        // `sameLiteralTable()`'s, transferred: a table matching itself is the
        // regularity that makes it a table, and two *different* seeders
        // agreeing is not — the M5 rating round called that shape genuine
        // duplication, and half of what it vindicated in the demoted stratum
        // was "copied seeder-like tables".
        if ($runs !== []) {
            $table = $table && count($runs) === 1 && count($names) === 1;
        }

        if ($table) {
            $held[] = self::TABLE;
        }

        // Ruling H's distinction, asked of registration files. A registration
        // file repeating itself is the regularity that makes it one — its second
        // run is not a copy anybody could remove — but two *different*
        // registration files agreeing is a copy, and somebody may want it gone.
        // On firefly-iii every crossing of that kind is the same pair,
        // `routes/api.php` against `routes/web.php`: the same route block
        // written into both surfaces, which is exactly the thing a reader would
        // want to see. Six findings stay asserted for it, out of 118 the wider
        // composition would have taken.
        if ($registration && count($names) === 1) {
            $held[] = self::REGISTRATION;
        }

        return $held;
    }
}
