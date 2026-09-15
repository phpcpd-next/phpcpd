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

use LucianoPereira\PhpcpdNext\Util\CodeLines;

use function array_keys;
use function count;
use function max;
use function min;
use function sprintf;
use function usort;

/**
 * What a scan found, and what it is a fraction of.
 *
 * A set rather than a list: clones are keyed by identity, so the same fragment
 * found by two strategies is stored once and the second sighting only adds
 * whatever occurrences the first had not seen. The totals are accumulated as
 * clones arrive, because the expensive part — deciding which source lines a
 * finding covers that no earlier finding already covered — is cheapest done
 * once per occurrence rather than recomputed over the whole set on demand.
 *
 * That holds while a map is being built and stops holding the moment a finding
 * is taken away, so {@see settle()} charges the coverage again from what is
 * left. Any future pass that removes findings owes the same recount: a total
 * that outlives the finding it was charged for is not a summary of the report,
 * and the duplicated-line percentage is built on it.
 *
 * @implements \IteratorAggregate<int, CodeClone>
 */
final class CodeCloneMap implements \Countable, \IteratorAggregate
{
    /** @var list<CodeClone> */
    private array $clones = [];

    /** @var array<string, CodeClone> keyed by {@see CodeClone::id()} */
    private array $clonesById = [];

    /** @var array<string, bool> */
    private array $filesWithClones = [];

    /** @var array<string, bool> files the scan could not open */
    private array $unreadable = [];

    /**
     * The source lines already counted toward $numberOfDuplicatedLines, as
     * merged, disjoint [firstLine, lastLine] ranges per file.
     *
     * A *range*, because the previous key was a point — `file:startLine` — and a
     * point cannot tell that two clones cover the same code. Rabin-Karp reports
     * `MetadataTest.php` at 996-1011, 996-1010 and 996-1008: one duplicated
     * block, three start lines, three separate keys, counted three times. The
     * unified engine's larger and more accurate classes made the sum *worse*
     * rather than better, so the metric was rewarding fragmentation and
     * punishing correctness.
     *
     * Counting the union instead is the clone-coverage definition used by
     * ConQAT and Teamscale — the share of lines belonging to at least one clone
     * — and it bounds the total by the size of the code by construction. The
     * 440% this tool could previously print for a single file is no longer
     * expressible.
     *
     * @var array<string, list<array{0: int, 1: int}>>
     */
    private array $countedLines = [];

    /**
     * Which lines hold a token the matchers can see, per file, read once.
     *
     * A clone is measured in tokens and reported in lines, and the line range
     * from the first matched token to the last sweeps up every docblock and
     * blank line between them. Counting those charged files with duplication of
     * material that was never compared — see {@see CodeLines} for how much, and
     * for the copy whose "duplicated" comments say `method` on one side and
     * `property` on the other.
     *
     * Injectable so a test can hand in a reader over source it never wrote to
     * disk; the default is the one that reads files.
     */
    public function __construct(private readonly CodeLines $codeLines = new CodeLines()) {}

    /**
     * How many findings the map removed after charging them.
     *
     * Reported rather than kept quiet. A run that scans and then settles is
     * doing two things — finding duplication and deciding which readings of it
     * to keep — and a reader shown only the survivors cannot tell a corpus with
     * little duplication from one whose readings collapsed into each other.
     * `settle()` drops 4 of php-parser's 41 findings at the shipped threshold,
     * 14 of symfony/string's 55 and 9 of symfony-console's 97, and until this
     * existed the report said nothing about any of them.
     */
    private int $numberOfSettledClones = 0;

    /**
     * How many findings were removed as unfounded — a class whose sites did not
     * agree with the one it was named beside.
     *
     * Kept apart from the settled count because the two say different things. A
     * settled reading described real duplication that another finding described
     * better; an unfounded one described duplication that was not there.
     */
    private int $numberOfUnfoundedClones = 0;

    private int $numberOfDuplicatedLines = 0;
    private int $numberOfLines           = 0;
    private int $largestCloneSize        = 0;

    // ── Building ────────────────────────────────────────────────────────────

    public function add(CodeClone $clone): void
    {
        $id = $clone->id();

        if (isset($this->clonesById[$id])) {
            // Seen before: keep the one occurrence set, and let this sighting
            // contribute any site the first did not name.
            foreach ($clone->files() as $file) {
                $this->clonesById[$id]->add($file);
            }
        } else {
            $this->clones[]        = $clone;
            $this->clonesById[$id] = $clone;
        }

        $this->countCoverage($clone);

        $this->largestCloneSize = max($this->largestCloneSize, $clone->numberOfLines());
    }

    /**
     * Merge another map's findings into this one.
     *
     * Clones and unreadable files, but never `numberOfLines`: both maps were
     * built over the same files, so the strategy that built this one has
     * already counted them and adding them again would double the denominator.
     *
     * The unreadable list is carried because both passes of the default
     * pipeline walk the same file list, so today they fail on the same files
     * and this changes nothing — which is exactly the kind of agreement that
     * stops holding quietly. A merged map that dropped half the failures would
     * report a clean scan over a file nobody managed to open.
     */
    public function mergeFrom(CodeCloneMap $other): void
    {
        foreach ($other->clones() as $clone) {
            $this->add($clone);
        }

        foreach ($other->unreadableFiles() as $file) {
            $this->couldNotRead($file);
        }

        $this->dropClonesSeenTwice();
    }

    /**
     * Settle the map: drop what is already described elsewhere.
     *
     * Called once when a run has finished adding clones. {@see mergeFrom()}
     * does it for the merged default pipeline, and a single-engine run has to
     * do it too — which it did not, because this was reachable only through the
     * merge and `--algorithm=unified` never goes that way. The result was a
     * report showing the same duplication twice by the project's own
     * definition: 8 findings of php-parser's 82, 37 of symfony-console's 227.
     */
    public function settle(): void
    {
        $before = count($this->clones);

        $this->dropClonesSeenTwice();
        $this->dropCoarserReadingsOfOneRegion();
        $this->recountCoverage();

        $this->numberOfSettledClones += $before - count($this->clones);
    }

    /**
     * Charge coverage again, from the clones that are left.
     *
     * Coverage is accumulated as clones arrive, which is right while a map is
     * being built and wrong the moment one is taken away. Both passes above
     * remove findings, and neither gave the lines back: the duplicated-line
     * total, the files-with-clones count and the percentage derived from them
     * all went on describing a report that no longer existed. Measured at the
     * shipped threshold, `settle()` dropped 4 of php-parser's 41 findings, 14
     * of symfony/string's 55 and 9 of symfony-console's 97, and the totals did
     * not move by a single line.
     *
     * A number that survives the finding it was charged for is not a summary of
     * the report, and it is the number the percentage is built on — so this runs
     * wherever findings are removed, and anything that learns to remove findings
     * later has to reach it too.
     *
     * Rebuilding rather than subtracting is deliberate. The ranges are merged as
     * they are charged, so a line covered by two clones is one entry and
     * unpicking one clone's share of it is not a subtraction anybody can do
     * correctly. Re-charging the survivors in order reaches the same answer the
     * map would have reached had the dropped findings never been added.
     */
    /** How many readings `settle()` removed as already described elsewhere. */
    public function numberOfSettledClones(): int
    {
        return $this->numberOfSettledClones;
    }

    /** How many findings were removed because nothing verified them. */
    public function numberOfUnfoundedClones(): int
    {
        return $this->numberOfUnfoundedClones;
    }

    public function recordUnfoundedClones(int $count): void
    {
        $this->numberOfUnfoundedClones += $count;
    }

    /**
     * Carry another map's removal counts onto this one.
     *
     * Two passes rebuild a map rather than editing it — suppression and the
     * coherence clamp — because coverage is charged as clones arrive and
     * re-adding the survivors is the only way the totals describe what comes
     * out. A fresh map starts with fresh counters, though, and that silently
     * threw away the tally of what earlier passes had removed: the report
     * stopped saying anything about readings `settle()` had dropped the moment
     * a later pass rebuilt the map.
     *
     * What a run removed is a fact about the run, not about whichever object
     * happens to be holding the survivors.
     */
    public function carryRemovalsFrom(self $other): void
    {
        $this->numberOfSettledClones   += $other->numberOfSettledClones;
        $this->numberOfUnfoundedClones += $other->numberOfUnfoundedClones;
    }

    private function recountCoverage(): void
    {
        $this->numberOfDuplicatedLines = 0;
        $this->countedLines            = [];
        $this->filesWithClones         = [];

        foreach ($this->clones as $clone) {
            $this->countCoverage($clone);
        }
    }

    /**
     * EXPERIMENT: one finding per self-similar region.
     */
    private function dropCoarserReadingsOfOneRegion(): void
    {
        $spans = [];

        foreach ($this->clones as $index => $clone) {
            $spans[$index] = self::spansOf($clone);
        }

        // Only two findings that name a file in common can ever be two readings
        // of one region, and most pairs in a corpus share nothing. Without this
        // the pass is quadratic in the number of findings and costs more on a
        // corpus where it drops nothing than it saves on one where it does.
        $byFile = [];

        foreach ($spans as $index => $indexSpans) {
            foreach ($indexSpans as [$name]) {
                $byFile[$name][$index] = true;
            }
        }

        $drop = [];

        foreach ($spans as $coarse => $coarseSpans) {
            $neighbours = [];

            foreach ($coarseSpans as [$name]) {
                foreach (array_keys($byFile[$name] ?? []) as $candidate) {
                    $neighbours[$candidate] = true;
                }
            }

            foreach (array_keys($neighbours) as $fine) {
                $fineSpans = $spans[$fine];

                if ($coarse === $fine || isset($drop[$fine]) || isset($drop[$coarse])) {
                    continue;
                }

                // Finer means more sites, each shorter.
                if (count($fineSpans) <= count($coarseSpans)
                    || $this->clones[$fine]->numberOfLines() >= $this->clones[$coarse]->numberOfLines()) {
                    continue;
                }

                // The region is pinned by the finer class's own extremes: from
                // where its first block starts to where its last block ends. A
                // self-similar run is one fact — "these N blocks agree" — and
                // any coarser reading that lives inside those bounds is that
                // same fact with the blocks glued together at some multiple of
                // the period.
                $region = [];

                foreach ($fineSpans as [$fineName, $fineFirst, $fineLast]) {
                    $region[$fineName][0] = isset($region[$fineName])
                        ? min($region[$fineName][0], $fineFirst)
                        : $fineFirst;
                    $region[$fineName][1] = isset($region[$fineName][1])
                        ? max($region[$fineName][1], $fineLast)
                        : $fineLast;
                }

                foreach ($coarseSpans as [$name, $first, $last]) {
                    if (!isset($region[$name]) || $first < $region[$name][0] || $last > $region[$name][1]) {
                        continue 2;
                    }
                }

                $drop[$coarse] = true;
            }
        }

        $kept = [];

        foreach ($this->clones as $index => $clone) {
            if (!isset($drop[$index])) {
                $kept[] = $clone;
            }
        }

        $this->clones = $kept;
    }

    /**
     * Drop a clone that describes a region another already describes.
     *
     * Merging two engines produces these, and so does one engine on its own —
     * a claim this said the other way round until it was measured.
     *
     * Both halves of the default pipeline see the same duplication and need not
     * agree on where it begins or ends: on two Laravel controllers sharing a
     * block, Rabin-Karp reported lines 8-42 and the token bag 9-42. Identity is
     * a content hash, so two spans differing by a line are two clones, and the
     * reader was shown the same duplication twice. Measured on `bench/corpus/
     * phpunit`: 215 clones from Rabin-Karp alone and 110 from the token bag
     * alone, neither overlapping any other, and 325 merged, of which fifteen
     * did.
     *
     * **Overlap, not a fraction of it.** Two findings over the same files whose
     * every site meets the other's describe one region; the question is a
     * membership test and there is no proportion to tune, which ruling 5 is
     * about. What the two engines disagree on is granularity: the nine on
     * phpunit are all a Rabin-Karp run inside a token-bag block — a 311-line
     * region and a 155-line one within it, over the same pair of test classes.
     * The longer is kept, because it is the region; the shorter is one reading
     * of it.
     *
     * Sites that merely touch are not this. Two duplications in one file pair
     * with a line between them do not meet, and both stay: measured, three
     * corpora drop nothing at all, and only phpunit's tandem-repeat test files
     * drop anything.
     *
     * The totals are unaffected either way. Duplicated lines are the union of
     * the lines clones cover, counted as each was added, and dropping a finding
     * afterwards does not un-cover a line the other still holds or that this one
     * already contributed.
     */
    private function dropClonesSeenTwice(): void
    {
        $spans = [];

        foreach ($this->clones as $index => $clone) {
            $spans[$index] = self::spansOf($clone);
        }

        $drop = [];

        foreach ($spans as $inner => $innerSpans) {
            foreach ($spans as $outer => $outerSpans) {
                if ($inner === $outer || isset($drop[$outer])) {
                    continue;
                }

                if (!self::meets($outerSpans, $innerSpans)) {
                    continue;
                }

                $keptLines    = $this->clones[$outer]->numberOfLines();
                $droppedLines = $this->clones[$inner]->numberOfLines();

                // The longer reading is the region. Where they are the same
                // length the content hash decides, so that two runs — and a
                // reversed file list — drop the same one.
                if ($keptLines < $droppedLines
                    || ($keptLines === $droppedLines
                        && $this->clones[$outer]->id() > $this->clones[$inner]->id())) {
                    continue;
                }

                $drop[$inner] = true;

                break;
            }
        }

        if ($drop === []) {
            return;
        }

        $kept = [];

        foreach ($this->clones as $index => $clone) {
            if (isset($drop[$index])) {
                unset($this->clonesById[$clone->id()]);

                continue;
            }

            $kept[] = $clone;
        }

        $this->clones = $kept;
    }

    /**
     * A clone's occurrences as `[file, firstLine, lastLine]`, ordered so that
     * two clones over the same sites compare position by position.
     *
     * @return list<array{0: string, 1: int, 2: int}>
     */
    private static function spansOf(CodeClone $clone): array
    {
        $spans = [];

        foreach ($clone->files() as $file) {
            $spans[] = [$file->name, $file->startLine, $file->lastLine($clone->numberOfLines())];
        }

        usort($spans, static fn(array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return $spans;
    }

    /**
     * Whether every site of one finding meets the site of the other beside it.
     *
     * Same count and same files: a clone naming three sites is not the same
     * finding as one naming two, however the lines fall.
     *
     * @param list<array{0: string, 1: int, 2: int}> $one
     * @param list<array{0: string, 1: int, 2: int}> $other
     */
    private static function meets(array $one, array $other): bool
    {
        if (count($one) !== count($other)) {
            return false;
        }

        foreach ($one as $i => [$name, $first, $last]) {
            [$otherName, $otherFirst, $otherLast] = $other[$i];

            if ($name !== $otherName || min($last, $otherLast) < max($first, $otherFirst)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A file the scan could not open.
     *
     * It contributes no clones and no lines, and the second of those is why it
     * has to be said out loud: the run still prints a percentage, and its
     * denominator quietly lost a file the user believes was scanned. A strategy
     * that returns early on an unreadable file is right to keep going and wrong
     * to keep it to itself.
     */
    public function couldNotRead(string $file): void
    {
        $this->unreadable[$file] = true;
    }

    public function addToNumberOfLines(int $numberOfLines): void
    {
        $this->numberOfLines += $numberOfLines;
    }

    /**
     * Restore a total that was computed elsewhere.
     *
     * For {@see \LucianoPereira\PhpcpdNext\Cache\CloneCache} alone, rebuilding a
     * map from a cached run: its clones are added back one by one, but their
     * coverage was already resolved when the cache was written, and re-deriving
     * it here would count each occurrence a second time.
     */
    public function setNumberOfDuplicatedLines(int $lines): void
    {
        $this->numberOfDuplicatedLines = $lines;
    }

    // ── Coverage ────────────────────────────────────────────────────────────

    /**
     * Charge a clone's occurrences to the files they sit in.
     *
     * Every copy but one is duplication: the first site a class names is the
     * original, the rest are what a reader would delete. Only lines not already
     * counted are added, so overlapping clones over one region count that
     * region once.
     *
     * This keeps the answer the old arithmetic got right. A block in A, B and C
     * reported as the three pairs {A,B}, {A,C}, {B,C} still totals 2N: the
     * first pair counts B, the second counts C, and the third counts nothing,
     * because B and C are already spoken for. A single three-file class reaches
     * the same 2N by skipping A and counting B and C.
     */
    private function countCoverage(CodeClone $clone): void
    {
        $first = true;

        foreach ($clone->files() as $file) {
            $this->filesWithClones[$file->name] = true;

            if ($first) {
                $first = false;

                continue;
            }

            // The occurrence's own extent where the strategy measured it, and
            // the clone's length only as the fallback it always was. Copies need
            // not span equally many lines — the matchers compare significant
            // tokens, and comments and blank lines are not that — so charging
            // every site the lead site's length recorded coverage over source
            // the occurrence does not reach.
            $this->numberOfDuplicatedLines += $this->cover(
                $file->name,
                $file->startLine,
                $file->lastLine($clone->numberOfLines()),
            );
        }
    }

    /**
     * Record a line range as duplicated and return how much of it was new code.
     *
     * The ranges held per file are kept disjoint and sorted: an incoming range
     * absorbs every range it touches, so a line already spoken for is not
     * charged twice and a block reported in halves leaves no seam behind.
     *
     * What is *returned*, though, is not the width of the new part but how many
     * of its lines hold a token — the lines the engines actually compared. The
     * range still covers what it covered, because a clone's extent is where the
     * matcher put it and a reader's excerpt has to stay contiguous; only the
     * total changes, and it changes to something the tool can stand behind.
     */
    private function cover(string $file, int $firstLine, int $lastLine): int
    {
        if ($lastLine < $firstLine) {
            return 0;
        }

        $existing = $this->countedLines[$file] ?? [];
        $keep     = [];
        $from     = $firstLine;
        $to       = $lastLine;

        foreach ($existing as [$rangeFrom, $rangeTo]) {
            // Disjoint and not even touching: keep it as it stands.
            if ($rangeTo < $firstLine - 1 || $rangeFrom > $lastLine + 1) {
                $keep[] = [$rangeFrom, $rangeTo];

                continue;
            }

            $from = min($from, $rangeFrom);
            $to   = max($to, $rangeTo);
        }

        $keep[] = [$from, $to];

        usort($keep, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

        $this->countedLines[$file] = $keep;

        $counted = 0;

        for ($line = $firstLine; $line <= $lastLine; $line++) {
            if (!$this->codeLines->isCode($file, $line)) {
                continue;
            }

            foreach ($existing as [$rangeFrom, $rangeTo]) {
                if ($line >= $rangeFrom && $line <= $rangeTo) {
                    continue 2;
                }
            }

            $counted++;
        }

        return $counted;
    }

    // ── Reading ─────────────────────────────────────────────────────────────

    /** @return list<CodeClone> */
    public function clones(): array
    {
        return $this->clones;
    }

    #[\Override]
    public function getIterator(): CodeCloneMapIterator
    {
        return new CodeCloneMapIterator($this);
    }

    #[\Override]
    public function count(): int
    {
        return count($this->clones);
    }

    public function isEmpty(): bool
    {
        return $this->clones === [];
    }

    public function numberOfFilesWithClones(): int
    {
        return count($this->filesWithClones);
    }

    /** @return list<string> */
    public function unreadableFiles(): array
    {
        return array_keys($this->unreadable);
    }

    public function numberOfLines(): int
    {
        return $this->numberOfLines;
    }

    public function numberOfDuplicatedLines(): int
    {
        return $this->numberOfDuplicatedLines;
    }

    /**
     * Number of gapped (Type-3 / inconsistent) clones — copies that share a
     * skeleton but diverge. These carry the bug risk: one copy patched, the
     * sibling not. See {@see CodeClone::isGapped()}.
     */
    public function numberOfGappedClones(): int
    {
        $gapped = 0;

        foreach ($this->clones as $clone) {
            // Reordered clones are gapped too — the contract says so — but they
            // are counted on their own line, because the two say different
            // things to a reader. This one is "the copies diverge"; that one is
            // "the same material, in another order".
            $gapped += $clone->isGapped() && !$clone->isReordered() ? 1 : 0;
        }

        return $gapped;
    }

    /** Clones whose copies hold the same material in a different order. */
    public function numberOfReorderedClones(): int
    {
        $reordered = 0;

        foreach ($this->clones as $clone) {
            $reordered += $clone->isReordered() ? 1 : 0;
        }

        return $reordered;
    }

    /** The duplicated share of the scanned lines, as a percentage. */
    public function percentage(): string
    {
        $percent = $this->numberOfLines > 0
            ? ($this->numberOfDuplicatedLines / $this->numberOfLines) * 100
            : 0;

        return sprintf('%01.2F%%', $percent);
    }

    /**
     * The mean size of a reported clone, in lines.
     *
     * The mean of the clones' own sizes, not duplicated lines per clone. The
     * latter is a ratio between two different things — a class naming eighty
     * sites contributes eighty sites' worth of lines and one clone — so it could
     * exceed {@see largestSize()} and did: a run over a file of repeated test
     * methods reported an average of 435 lines beside a largest of 145. An
     * average above the maximum is not a number anyone can read.
     */
    public function averageSize(): float
    {
        if ($this->clones === []) {
            return 0.0;
        }

        $total = 0;

        foreach ($this->clones as $clone) {
            $total += $clone->numberOfLines();
        }

        return $total / count($this->clones);
    }

    public function largestSize(): int
    {
        return $this->largestCloneSize;
    }
}
