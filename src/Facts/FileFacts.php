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

namespace LucianoPereira\PhpcpdNext\Facts;

use function file_get_contents;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;

/**
 * One file's facts, read once: what its regions are, what its statements are,
 * what role it plays, and which source line each significant token sits on.
 *
 * Ruling R's integrated shape asks for the facts layer to be *"computed once and
 * read by both tiers rather than twice under two definitions"*, and this is the
 * unit that makes that true across the engine and the report: the detector reads
 * {@see RegionStructure} directly while it works, and the presentation tier reads
 * all four here, afterwards, over the handful of files that actually carry a
 * finding.
 *
 * ## Why the line table is here
 *
 * A reported site is a **first line and a line count**; every question the strata
 * ask is about **token indices**. The bridge is the encoder's own line-per-token
 * table, so a span means the same thing to the report as it does to the engine.
 * It is the same mapping `bench/triage.php`'s span tier and the M5 experiment
 * both use, which is what lets a rated worksheet and a shipped run be scored
 * against identical spans.
 */
final readonly class FileFacts
{
    /** @param list<int> $tokenLines significant index => real source line */
    private function __construct(
        public RegionStructure $regions,
        public FileStatements $statements,
        public FileRole $role,
        public array $tokenLines,
    ) {}

    /**
     * Read and analyse one file, or null when it cannot be read.
     *
     * A file that cannot be read is evidence nobody has, not evidence of
     * anything: every caller in this package treats null as "no stratum holds",
     * which keeps the finding asserted.
     */
    public static function read(string $path): ?self
    {
        $source = @file_get_contents($path);

        if ($source === false) {
            return null;
        }

        return self::fromSource($source);
    }

    public static function fromSource(string $source): self
    {
        $statements = FileStatements::fromSource($source);

        // Only the encoder's tokenizer is used, and only for the line-per-token
        // table, so the detection knobs are inert here.
        $encoder = new DefaultStrategy(new StrategyConfiguration(5, 70, Normalization::Raw, 1.0));

        return new self(
            RegionStructure::fromSource($source),
            $statements,
            FileRole::of($statements),
            $encoder->tokenize($source)->tokenRealLines,
        );
    }

    /**
     * The token range an occurrence covers — the one question every reader of a
     * finding's *contents* has to ask, and the one place to ask it.
     *
     * It was asked in four places and answered the same wrong way in all four,
     * because the occurrence's own token range had nowhere to live and each
     * caller re-derived it from the reported lines. It does not round-trip: a
     * match begins where the tokens agreed, routinely partway through a line,
     * and {@see span()} then hands back every token those lines hold. On
     * symfony-console that widened 520 of 585 sites, by 846 tokens at worst.
     *
     * How long the occurrence is is {@see CodeClone::tokensOf()}'s to say, so
     * this takes the clone rather than numbers a caller had to remember to
     * thread through.
     *
     * @return ?array{0: int, 1: int} [first index, token count]
     */
    public function occurrence(CodeClone $clone, CodeCloneFile $site): ?array
    {
        $tokens = $clone->tokensOf($site);

        if ($site->startToken !== null && $tokens > 0) {
            return [$site->startToken, $tokens];
        }

        // A strategy that did not record where its match began in tokens leaves
        // the lines as the only thing to go on, which is what every caller did
        // before occurrences carried their own.
        return $this->span($site->startLine, $clone->linesOf($site));
    }

    /**
     * Every token whose real source line falls inside a line range.
     *
     * The fallback behind {@see occurrence()}, and not the question a caller
     * holding an occurrence should be asking.
     *
     * @return ?array{0: int, 1: int} [first index, token count], or null when the
     *                                range holds no significant token at all
     */
    public function span(int $startLine, int $lineCount): ?array
    {
        $last  = $startLine + $lineCount - 1;
        $first = null;
        $end   = null;

        foreach ($this->tokenLines as $index => $line) {
            if ($line >= $startLine && $line <= $last) {
                $first ??= $index;
                $end = $index;
            }
        }

        return $first === null || $end === null ? null : [$first, $end - $first + 1];
    }
}
