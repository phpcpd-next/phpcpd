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

namespace LucianoPereira\PhpcpdNext\Detector;

use function array_slice;
use function count;
use function file_get_contents;
use function substr;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\FileTokens;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;

/**
 * Every site a class names is a copy of the site it was named beside.
 *
 * A clone *class* is assembled from many candidate pairs, and a site can be
 * seated into one without ever having been compared to the sites already
 * there. The result is a finding that asserts more than anything verified it:
 * `Standard.php` was reported as one exact clone of `444-467`, `479-500` and
 * `510-533` where **no pair of the three agrees** — 0 of 166 tokens, 0 of 166,
 * and 17 of 166. Each of them has a real duplicate elsewhere in the file (the
 * first at token 3905, the second at 3836 and 3873); none of them is a copy of
 * either other.
 *
 * That is worse than the overlap NEXT-TASKS item 2 records. An overlapping site
 * misstates an extent; this invents the finding.
 *
 * ## Only where exactness is claimed
 *
 * A gapped clone's copies differ by construction, and a reordered clone's
 * differ in order by definition — neither is asked to be token-identical, and
 * asking would delete the engines that find them. So this reads only the
 * classes that claim to be exact, which since the token bag stopped claiming it
 * means Rabin-Karp's and the unified engine's gapless ones. Measured there, it
 * has almost nothing to do: 1 class on php-parser, 0 on symfony/string, 1 on
 * symfony-console in the default pipeline, and 2, 1 and 2 under
 * `--algorithm=unified`.
 *
 * That smallness is the point. Item 2 records dropping sites being tried and
 * reverted, at a cost of 16 of one class's 88 sites and 226 of the token bag's
 * pairs turned into misses. It was aimed at the wrong classes: the bag's, which
 * were never exact and had been mislabelled as such.
 *
 * ## Either view, because the unified engine uses both
 *
 * Its gapless candidates are exact in the raw view *or* in the normalized one —
 * that is the invariant its own suite pins — so a site is kept if it agrees
 * under either. Checking one view alone would drop findings that are perfectly
 * sound in the other.
 */
final class CoherentClasses
{
    /** @var array<string, array{0: string, 1: string}> path => [raw signature, normalized signature] */
    private array $signatures = [];

    private function __construct(private readonly StrategyConfiguration $config) {}

    /**
     * Rebuild a map with every class reduced to the sites that agree.
     *
     * A fresh map rather than a surgery on this one: coverage is charged as
     * clones arrive, so re-adding the survivors is what makes the
     * duplicated-line total describe the report that comes out — the same
     * reason {@see CloneSuppressions::filter()} builds one.
     */
    public static function applyTo(CodeCloneMap $map, StrategyConfiguration $config): CodeCloneMap
    {
        $coherent = new self($config);
        $rebuilt  = new CodeCloneMap();
        $rebuilt->addToNumberOfLines($map->numberOfLines());

        foreach ($map->unreadableFiles() as $file) {
            $rebuilt->couldNotRead($file);
        }

        $unfounded = 0;

        foreach ($map->clones() as $clone) {
            $kept = $coherent->agreeingSites($clone);

            if (count($kept) < 2) {
                ++$unfounded;
                // Nothing verified this finding at all. A class whose members
                // do not agree with the one they were named beside is not a
                // smaller class, it is an unfounded one.
                continue;
            }

            $rebuilt->add($coherent->rebuild($clone, $kept));
        }

        $rebuilt->carryRemovalsFrom($map);
        $rebuilt->recordUnfoundedClones($unfounded);

        return $rebuilt;
    }

    /**
     * The sites that hold the same tokens as the one the class leads with.
     *
     * @return list<CodeCloneFile>
     */
    private function agreeingSites(CodeClone $clone): array
    {
        $sites = [];

        foreach ($clone->files() as $site) {
            $sites[] = $site;
        }

        // Only exactness is checkable here; everything else keeps every site.
        if ($clone->isGapped() || $clone->isReordered() || count($sites) < 2) {
            return $sites;
        }

        $lead = $sites[0];

        if ($lead->startToken === null) {
            return $sites;
        }

        $kept = [$lead];

        foreach (array_slice($sites, 1) as $site) {
            // A site that records no token position cannot be checked, and an
            // unverifiable site is not a disagreeing one.
            if ($site->startToken === null || $this->agree($clone, $lead, $site)) {
                $kept[] = $site;
            }
        }

        return $kept;
    }

    /** Do two occurrences hold the same tokens, in either view? */
    private function agree(CodeClone $clone, CodeCloneFile $lead, CodeCloneFile $site): bool
    {
        $length = $clone->tokensOf($lead);

        if ($length < 1 || $clone->tokensOf($site) !== $length) {
            return false;
        }

        [$rawA, $normA] = $this->signaturesOf($lead->name);
        [$rawB, $normB] = $this->signaturesOf($site->name);

        return self::span($rawA, $lead->startToken ?? 0, $length) === self::span($rawB, $site->startToken ?? 0, $length)
            || self::span($normA, $lead->startToken ?? 0, $length) === self::span($normB, $site->startToken ?? 0, $length);
    }

    private static function span(string $signature, int $first, int $length): string
    {
        return substr($signature, $first * FileTokens::TOKEN_BYTES, $length * FileTokens::TOKEN_BYTES);
    }

    /**
     * One file's two signatures, read once.
     *
     * Only files that carry a finding are ever read, which is the same bargain
     * the facts layer strikes: the expensive step is bounded by the size of the
     * report rather than by the size of the scan.
     *
     * @return array{0: string, 1: string}
     */
    private function signaturesOf(string $path): array
    {
        if (isset($this->signatures[$path])) {
            return $this->signatures[$path];
        }

        $source = (string) file_get_contents($path);

        return $this->signatures[$path] = [
            (new DefaultStrategy($this->view(Normalization::Raw)))->tokenize($source)->signature,
            (new DefaultStrategy($this->view($this->config->normalization->folds()
                ? $this->config->normalization
                : Normalization::TypeAnchored)))->tokenize($source)->signature,
        ];
    }

    private function view(Normalization $normalization): StrategyConfiguration
    {
        return new StrategyConfiguration(
            minLines: $this->config->minLines,
            minTokens: $this->config->minTokens,
            normalization: $normalization,
            minSimilarity: $this->config->minSimilarity,
        );
    }

    /**
     * The same finding, naming only the sites that agree.
     *
     * @param list<CodeCloneFile> $sites
     */
    private function rebuild(CodeClone $clone, array $sites): CodeClone
    {
        $rebuilt = new CodeClone(
            $sites[0],
            $sites[1],
            $clone->numberOfLines(),
            $clone->numberOfTokens(),
            $clone->isGapped(),
            $clone->divergences(),
            $clone->isReordered(),
        );

        foreach (array_slice($sites, 2) as $site) {
            $rebuilt->add($site);
        }

        return $rebuilt;
    }
}
