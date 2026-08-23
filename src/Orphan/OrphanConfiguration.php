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

use function in_array;

/**
 * How one orphan scan is configured: which trees it was pointed at, which
 * suppression rules are live, which tiers gate the exit code, and whether
 * suppressed symbols are listed or only counted.
 *
 * $roots matters beyond bookkeeping. Path-shaped rules judge a file by its path
 * RELATIVE to the roots, never by its absolute path — pointing the scan straight
 * at a fixture directory makes that directory the project, so its contents are
 * ordinary code rather than self-suppressing. It also keeps a scan reproducible
 * across machines, where an absolute path is not.
 */
final readonly class OrphanConfiguration
{
    public const string TIER_DEAD       = 'dead';
    public const string TIER_POSSIBLE   = 'possible';
    public const string TIER_PLANNED    = 'planned';
    public const string TIER_SUPPRESSED = 'suppressed';

    /**
     * @param list<string> $roots         directories the scan was pointed at
     * @param list<string> $disabledRules rule names from --no-suppress, or ['all']
     * @param list<string> $failOn        tiers that make the run exit non-zero
     */
    public function __construct(
        public array $roots = [],
        public array $disabledRules = [],
        public array $failOn = [self::TIER_DEAD],
        public bool $explain = false,
    ) {}

    public function ruleEnabled(string $rule): bool
    {
        return !in_array('all', $this->disabledRules, true)
            && !in_array($rule, $this->disabledRules, true);
    }

    public function gatesOn(string $tier): bool
    {
        return in_array($tier, $this->failOn, true);
    }
}
