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

use function array_key_first;
use function count;

use LucianoPereira\PhpcpdNext\Facts\FileFactsIndex;

/**
 * What a finding looks like, as the bucketed categorical values the confidence
 * ranking counts.
 *
 * The questions are **pre-registered** (M5 packet §2.1, committed before any
 * count was taken); only the counts are measured. That order is the whole point:
 * a feature set chosen after seeing which features helped is not evidence about
 * findings, it is a description of one label set.
 *
 * ## The four questions
 *
 * | feature | buckets |
 * |---|---|
 * | `sites`   | `two` · `three` · `many` |
 * | `scope`   | `same-file` · `cross-file` |
 * | `lines`   | `small` · `medium` · `large` · `huge` |
 * | `literal` | `none` · `trace` · `low` · `medium` · `high` · `dominant` |
 *
 * Bucket edges are fixed and inspectable rather than fitted. `sites` splits at 2
 * and 3, which are counts and not thresholds. `lines` splits at 10, 50 and 100 —
 * two decade landmarks and the tool's own "large block" landmark, which
 * {@see \LucianoPereira\PhpcpdNext\Log\Text} uses at 50 lines. `literal`'s
 * 0 / 10 / 25 / 50 / 75 % edges are a share scale, the same measurement asked of
 * a span rather than of a file.
 *
 * ## What is deliberately absent
 *
 * **The demote strata are not features.** The ranking is an independent lens on
 * the same finding, so a reader who distrusts one mechanism still has the other;
 * making the strata features would make two instruments into one instrument
 * reported twice.
 *
 * **No refuted discriminator, in any wording.** Anchor multiplicity and
 * tokens-per-line sparseness are dead and have no feature here; `literal` is the
 * authorised literal-mass question, not the refuted "logic share".
 *
 * **`kind` was pre-registered and could not be counted** — the two preserved
 * worksheets do not record whether a finding was exact, gapped or reordered, and
 * the pool is multi-engine so the finding has no single engine's classification
 * to recover. Dropped before any count was taken, and recorded in the packet's
 * Interpretations rather than quietly replaced.
 */
final readonly class ConfidenceFeatures
{
    public const string SITES   = 'sites';
    public const string SCOPE   = 'scope';
    public const string LINES   = 'lines';
    public const string LITERAL = 'literal';

    /**
     * The feature order, fixed so the model table, the printed log-odds line and
     * two runs of the ranking all read the same way.
     *
     * @var list<string>
     */
    public const array NAMES = [self::SITES, self::SCOPE, self::LINES, self::LITERAL];

    /** @param array<string, string> $values feature name => bucket */
    public function __construct(public array $values) {}

    /**
     * The features of a finding whose parts have already been resolved — the
     * form the training instrument uses, so train time and run time compute one
     * feature vector from one code path and cannot drift apart.
     *
     * @param int    $sites        how many occurrences the class holds
     * @param bool   $sameFile     do all occurrences sit in one file
     * @param int    $lines        the finding's line count
     * @param ?float $literalShare the literal share of the lead span, 0..1, or
     *                             null when the lead span could not be read
     */
    public static function describe(int $sites, bool $sameFile, int $lines, ?float $literalShare): self
    {
        return new self([
            self::SITES   => self::bucket($sites, [2, 3], ['two', 'three', 'many']),
            self::SCOPE   => $sameFile ? 'same-file' : 'cross-file',
            self::LINES   => self::bucket($lines, [10, 50, 100], ['small', 'medium', 'large', 'huge']),
            self::LITERAL => $literalShare === null
                ? 'unknown'
                : self::bucket(
                    (int) (100 * $literalShare),
                    [0, 10, 25, 50, 75],
                    ['none', 'trace', 'low', 'medium', 'high', 'dominant'],
                ),
        ]);
    }

    /** The features of a finding in a live run. */
    public static function of(Finding $finding, FileFactsIndex $index): self
    {
        $clone = $finding->clone;
        $files = $clone->files();
        $names = [];

        foreach ($files as $file) {
            $names[$file->name] = true;
        }

        $first = array_key_first($files);
        $lead  = $first === null ? null : $files[$first];
        $share = null;

        if ($lead !== null) {
            $facts = $index->for($lead->name);
            // The line-derived span, deliberately, and not the occurrence's own
            // token range that `Strata` now asks for.
            //
            // The two ask different kinds of question. `Strata` asks a fact
            // about the code — is this span a table — and a fact has to be
            // asked of what the clone actually covers. This is a *fitted*
            // feature: `bench/train-confidence.php` computes the literal share
            // from the line span a rated worksheet records, because a worksheet
            // records lines, and the model's weights are the weights of that
            // definition. Scoring a different definition with them is a
            // train/serve mismatch, not a correction.
            //
            // Measured, so the size of it is known rather than assumed: the raw
            // share differs on about a third of lead sites, and the bucket the
            // model actually reads differs on 8 of 348 — one on
            // symfony-console, seven on phpunit, none on php-parser or
            // symfony-string.
            //
            // Aligning them properly means training on the token range, which
            // means a worksheet that records it, which means a fresh rating
            // round. That is the second-rater item on the roadmap, and this
            // line changes with it and not before.
            $span  = $facts?->span($lead->startLine, $clone->linesOf($lead));

            if ($facts !== null && $span !== null && $span[1] > 0) {
                $share = $facts->statements->literals($span[0], $span[1]) / $span[1];
            }
        }

        return self::describe(count($files), count($names) === 1, $clone->numberOfLines(), $share);
    }

    /**
     * @param list<int>    $edges  ascending; a value greater than edge i lands in
     *                             bucket i+1
     * @param list<string> $labels one longer than $edges
     */
    private static function bucket(int $value, array $edges, array $labels): string
    {
        $index = 0;

        foreach ($edges as $edge) {
            if ($value > $edge) {
                $index++;
            }
        }

        return $labels[$index];
    }
}
