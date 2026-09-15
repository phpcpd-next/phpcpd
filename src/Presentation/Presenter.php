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

use function usort;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Facts\FileFactsIndex;

/**
 * The presentation tier: it turns a {@see CodeCloneMap} into {@see Findings}.
 *
 * *Posture follows epistemics at the report layer.* The engine's job is to find
 * duplication and it is not changed by anything here; this tier's job is to say
 * how confident the tool is in what it found, and it does that by **labelling**,
 * never by removing. Every clone in goes to exactly one finding out.
 *
 * The whole tier is a pure function of the map plus the files it names, so two
 * runs over one tree present identically — the determinism the gates check of
 * the detector holds of the report for the same reason.
 *
 * ## Ranking, and the line it may not cross
 *
 * Findings are ordered by {@see ConfidenceModel}'s log-odds, highest first. The
 * ranking **never filters**: the output holds exactly the clones the map held,
 * and `PresentationTest` asserts the two sets are equal rather than trusting it.
 * Ties break on the clone's own identity, so the order is total and two runs
 * agree — a ranking whose ties fell out of iteration order would quietly break
 * the determinism gate.
 */
final class Presenter
{
    private readonly Strata $strata;

    private readonly LiteralDivergence $literals;

    /**
     * @param ?AcknowledgmentLedger $ledger null when the run consulted none
     * @param ?float $minConfidence the reader's threshold, carried to
     *        {@see Findings} so that the partition is the report's rather than
     *        this tier's: nothing is dropped on the way out, and the tier's
     *        every-clone-to-one-finding invariant is untouched.
     */
    public function __construct(
        private readonly FileFactsIndex $facts = new FileFactsIndex(),
        private readonly ConfidenceModel $model = new ConfidenceModel(),
        private readonly ?AcknowledgmentLedger $ledger = null,
        private readonly ?float $minConfidence = null,
    ) {
        $this->strata   = new Strata($this->facts);
        $this->literals = new LiteralDivergence();
    }

    /**
     * The functions the lead site's range lands in.
     *
     * The lead site, because that is the range the report prints: the others are
     * listed under it and a reader following one of those is reading a different
     * file anyway.
     *
     * @return list<string>
     */
    private function functionsOf(CodeClone $clone): array
    {
        $lead = null;

        foreach ($clone->files() as $site) {
            $lead = $site;

            break;
        }

        if ($lead === null) {
            return [];
        }

        $facts = $this->facts->for($lead->name);

        if ($facts === null) {
            return [];
        }

        $span = $facts->occurrence($clone, $lead);

        if ($span === null) {
            return [];
        }

        return $facts->regions->functionsTouching($span[0], $span[1]);
    }

    /**
     * Every occurrence's extent in whole lines — {@see LineSnap} for why.
     *
     * A site the tier cannot read, or whose run holds no whole line, is simply
     * absent, and {@see Finding::span()} falls back to the engine's own numbers.
     *
     * @return array<string, array{0: int, 1: int}>
     */
    private function spansOf(CodeClone $clone): array
    {
        $spans = [];

        foreach ($clone->files() as $site) {
            $facts = $this->facts->for($site->name);

            if ($facts === null) {
                continue;
            }

            $occurrence = $facts->occurrence($clone, $site);

            if ($occurrence === null) {
                continue;
            }

            $snapped = LineSnap::of($facts, $occurrence);

            if ($snapped !== null) {
                $spans[$site->id] = $snapped;
            }
        }

        return $spans;
    }

    public function present(CodeCloneMap $clones): Findings
    {
        $findings = [];
        /** @var array<string, true> $matched */
        $matched = [];

        foreach ($clones as $clone) {
            $finding  = new Finding($clone, $this->strata->of($clone));
            $features = ConfidenceFeatures::of($finding, $this->facts);

            $acknowledged = false;

            if ($this->ledger !== null) {
                $key = AcknowledgmentLedger::keyOf($clone);

                if ($this->ledger->has($key)) {
                    $matched[$key] = true;
                    $acknowledged  = true;
                }
            }

            $findings[] = new Finding(
                $clone,
                $finding->strata,
                $this->model->score($features),
                $this->model->terms($features),
                $acknowledged,
                $this->literals->of($clone),
                $this->functionsOf($clone),
                $this->spansOf($clone),
            );
        }

        // Highest confidence first, and a total order: two findings the model
        // cannot separate fall back on the clone's own identity, which is a
        // content hash and therefore stable across runs and machines.
        usort(
            $findings,
            static fn(Finding $a, Finding $b): int
                => [$b->confidence, $a->clone->id()] <=> [$a->confidence, $b->clone->id()],
        );

        return new Findings(
            $clones,
            $findings,
            $this->ledger?->stale($matched) ?? [],
            $this->ledger !== null,
            $this->minConfidence,
        );
    }

    /** How many files the tier had to read to say what it said. */
    public function filesAnalysed(): int
    {
        return $this->facts->analysed();
    }
}
