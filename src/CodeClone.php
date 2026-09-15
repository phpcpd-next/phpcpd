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

use function array_key_first;
use function array_keys;
use function array_map;
use function array_slice;
use function array_values;
use function clearstatcache;
use function count;
use function file;
use function filemtime;
use function filesize;
use function implode;
use function md5;
use function sprintf;
use function trim;

/**
 * One duplicated fragment, and every place it occurs.
 *
 * A clone is identified by what it *is* rather than by where it was found, so
 * the same fragment reported by two engines is one clone with the occurrences
 * of both. That identity is a digest of the fragment's own source, which is why
 * constructing one reads a file — and why {@see readLines()} caches.
 */
final class CodeClone
{
    /**
     * How many files' contents to keep in {@see readLines()}'s cache.
     *
     * Every clone reads its first file to build its identity, and reports arrive
     * grouped by that file — the engines emit them in file order — so a cache of
     * two entries turns a run's worth of re-reads into one read per file while
     * holding at most two files in memory. Measured on a 2,870-file corpus where
     * 6,961 clones shared one 5,782-line first file: 1.09s of clone construction
     * became 0.03s, with byte-identical output.
     */
    private const int LINE_CACHE_ENTRIES = 2;

    /** @var array<string, array{stamp: string, lines: list<string>}> path => contents and the stamp they were read at */
    private static array $lineCache = [];

    /** @var array<string, CodeCloneFile> keyed by {@see CodeCloneFile::$id} */
    private array $files = [];

    /** @var ?list<string> the fragment's own lines, unindented, read once */
    private ?array $excerpt = null;

    private readonly string $id;
    private readonly int $numberOfLines;
    private readonly int $numberOfTokens;
    private readonly bool $gapped;
    private readonly bool $reordered;

    /** @var list<CloneDivergence> */
    private readonly array $divergences;

    /**
     * @param list<CloneDivergence> $divergences where the copies stop agreeing
     *        (or, for a reordered clone, where the displaced material sits),
     *        one entry per side; empty for an exact clone
     * @param bool $reordered Type-3 reordered rather than Type-3 gapped — the
     *        material is all present but not in the same order. Only the
     *        unified engine can tell the two apart; every other engine leaves
     *        this false. Implies $gapped: a reordered clone is never an exact
     *        copy either.
     */
    public function __construct(
        CodeCloneFile $fileA,
        CodeCloneFile $fileB,
        int $numberOfLines,
        int $numberOfTokens,
        bool $gapped = false,
        array $divergences = [],
        bool $reordered = false,
    ) {
        $this->add($fileA);
        $this->add($fileB);

        $this->numberOfLines  = $numberOfLines;
        $this->numberOfTokens = $numberOfTokens;
        $this->gapped         = $gapped;
        $this->divergences    = $divergences;
        $this->reordered      = $reordered;
        $this->id             = $this->identity();
    }

    /**
     * What makes this clone the same clone as another.
     *
     * The fragment's source, digested — so two engines that find one duplication
     * produce one clone carrying both sets of occurrences, which is what
     * {@see CodeCloneMap} keys on.
     *
     * Unless there is no source to digest. A file that cannot be read gives an
     * empty excerpt, and `md5('')` is not this clone's identity: it is the same
     * value for *every* clone whose text could not be read, so a map keyed on it
     * merges findings that share nothing. Two unreadable clones came back as one
     * finding naming four unrelated sites — which does not lose a finding, it
     * invents one, and merging is the destructive direction because it attaches
     * one clone's occurrences to another.
     *
     * So a fragment with no readable text is identified by where it is instead.
     * Two findings that cannot be shown to be the same are kept apart, which is
     * the safe answer when the evidence for merging is missing.
     */
    private function identity(): string
    {
        $excerpt = $this->lines();

        if (trim($excerpt) !== '') {
            return md5($excerpt);
        }

        return md5(sprintf(
            "\0%s\0%d\0%d",
            implode('|', array_keys($this->files)),
            $this->numberOfLines,
            $this->numberOfTokens,
        ));
    }

    /**
     * Add an occurrence, if this clone does not already name that place.
     *
     * {@see CodeCloneFile::$id} is the file and the start line, so a site
     * reported twice is stored once and the first report's measurements are the
     * ones kept.
     */
    public function add(CodeCloneFile $file): void
    {
        $this->files[$file->id] ??= $file;
    }

    /** @return array<string, CodeCloneFile> */
    public function files(): array
    {
        return $this->files;
    }

    /**
     * The clone's own source, optionally indented.
     *
     * The excerpt is memoised; the indent is not, and that distinction is the
     * whole of it. The memo used to hold the finished string, so the first
     * caller's indent became every caller's — and the first caller is the
     * constructor, computing this clone's identity with no indent at all.
     * `--verbose` therefore asked for four spaces, was handed the constructor's
     * copy, and printed every excerpt hard against the left margin.
     *
     * Keeping the lines rather than the string also means `empty()` is no longer
     * asked to distinguish "not read yet" from "read, and empty" — two states it
     * cannot tell apart, and one of them makes every call re-read the file.
     */
    public function lines(string $indent = ''): string
    {
        $this->excerpt ??= $this->read();

        $text = '';

        foreach ($this->excerpt as $line) {
            // A blank line is left blank. Indenting one emits the indent and
            // nothing else, which is trailing whitespace: invisible until
            // someone diffs the report, or greps it, or commits it.
            $text .= trim($line) === '' ? $line : $indent . $line;
        }

        return $text;
    }

    /** @return list<string> */
    private function read(): array
    {
        $file = array_values($this->files)[0];

        return array_slice(
            self::readLines($file->name),
            $file->startLine - 1,
            // This occurrence's own length where it has one. It is the lead
            // here — the class is sized by the site it was led by, and that site
            // is the one this excerpt reads — so the two agree today. They agree
            // by an invariant nothing asserts, though, and spelling the rule out
            // a third time is exactly how `CloneSuppressions` and
            // `bench/check-superset.php` came to read a class's lead length as
            // though it were every member's.
            $file->numberOfLines ?? $this->numberOfLines,
        );
    }

    /**
     * The lines of a file, read at most once per file while the file is unchanged.
     *
     * A clone's identity is a digest of its own text, so building one means
     * reading its first file — and a corpus with one heavily duplicated file
     * makes that the same read, thousands of times.
     *
     * The cache is static, so it outlives a single scan, and a long-running
     * embedder can perfectly well edit a file and scan again in the same process.
     * A cache keyed on the path alone would then hand the second scan the first
     * scan's bytes and quietly build clone identities out of code that is no
     * longer there. So each entry records the file's modification time and size
     * and is revalidated on every hit: one `stat` per clone, against the full
     * read it replaces.
     *
     * The pair is not a content hash — a file rewritten to the same size within
     * the same clock second can defeat it — but hashing means reading, which is
     * the cost being avoided. The residual case needs a sub-second same-size edit
     * between two scans inside one process; the alternative is that every
     * embedder pays a full re-read per clone.
     *
     * @return list<string>
     */
    private static function readLines(string $path): array
    {
        $stamp = self::stamp($path);
        $entry = self::$lineCache[$path] ?? null;

        if ($entry !== null && $entry['stamp'] === $stamp) {
            return $entry['lines'];
        }

        if ($entry === null && count(self::$lineCache) >= self::LINE_CACHE_ENTRIES) {
            // Evict the least recently inserted; PHP arrays keep insertion
            // order, so the first key is the oldest.
            unset(self::$lineCache[array_key_first(self::$lineCache)]);
        }

        $lines                  = @file($path) ?: [];
        self::$lineCache[$path] = ['stamp' => $stamp, 'lines' => $lines];

        return $lines;
    }

    /** A cheap identity for a file's current contents: modification time and size. */
    private static function stamp(string $path): string
    {
        clearstatcache(true, $path);

        return ((string) @filemtime($path)) . ':' . ((string) @filesize($path));
    }

    // ── What the engines decided ────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function numberOfLines(): int
    {
        return $this->numberOfLines;
    }

    /**
     * How far one of this clone's occurrences reaches, so that a caller holding the clone
     * does not have to know which of the two figures applies.
     *
     * {@see CodeCloneFile::lines()} states the rule; this only supplies the
     * clone's own number to it.
     */
    public function linesOf(CodeCloneFile $site): int
    {
        return $site->lines($this->numberOfLines);
    }

    /** {@see linesOf()}, in significant tokens. */
    public function tokensOf(CodeCloneFile $site): int
    {
        return $site->tokens($this->numberOfTokens);
    }

    public function numberOfTokens(): int
    {
        return $this->numberOfTokens;
    }

    /**
     * Whether the two copies are NOT token-identical — a gapped (Type-3) clone,
     * where the unified engine's banded alignment bridged a difference and names
     * the divergent ranges on both sides. Exact (Type-1) clones, and all
     * Rabin-Karp clones, return false.
     */
    public function isGapped(): bool
    {
        return $this->gapped;
    }

    /**
     * Whether this is a Type-3 **reordered** clone rather than a Type-3
     * **gapped** one: the material is all present in both copies but not in
     * the same order, and {@see divergences()} names the displaced blocks
     * rather than a place the copies stop agreeing. Only the unified engine
     * classifies this; every other engine returns false here.
     */
    public function isReordered(): bool
    {
        return $this->reordered;
    }

    /**
     * Where the two copies differ, when the engine that found them could say.
     *
     * Empty for an exact clone, and empty for a gapped clone found by the suffix
     * tree, which reports only that a divergence exists. The unified engine fills
     * it in: its chain's gaps *are* the divergences, bounded by exact matches on
     * both sides.
     *
     * @return list<CloneDivergence>
     */
    public function divergences(): array
    {
        return $this->divergences;
    }

    /**
     * Canonical array representation shared by JSON output and the clone cache.
     * Field names: path/line (public-facing convention).
     *
     * The `divergences` key appears only when there are any, and `reordered`
     * only when true, so the reports of engines that cannot produce them are
     * unchanged byte for byte.
     *
     * @return array{lines:int, tokens:int, gapped:bool, files:list<array{path:string, line:int, lines?:int, tokens?:int}>, divergences?:list<array{path:string, startLine:int, endLine:int, startToken:int, tokens:int}>, reordered?:bool}
     */
    public function toArray(): array
    {
        $files = [];

        foreach ($this->files as $file) {
            $entry = ['path' => $file->name, 'line' => $file->startLine];

            // Only when the strategy measured it. Absent stays absent, so a
            // reader still falls back to the clone's own length and an entry
            // written by a strategy that does not measure occurrences is not
            // given a number it never had.
            if ($file->numberOfLines !== null) {
                $entry['lines'] = $file->numberOfLines;
            }

            if ($file->numberOfTokens !== null) {
                $entry['tokens'] = $file->numberOfTokens;
            }


            $files[] = $entry;
        }

        $data = [
            'lines'  => $this->numberOfLines,
            'tokens' => $this->numberOfTokens,
            'gapped' => $this->gapped,
            'files'  => $files,
        ];

        if ($this->reordered) {
            $data['reordered'] = true;
        }

        if ($this->divergences !== []) {
            $data['divergences'] = array_map(
                static fn(CloneDivergence $divergence): array => $divergence->toArray(),
                $this->divergences,
            );
        }

        return $data;
    }
}
