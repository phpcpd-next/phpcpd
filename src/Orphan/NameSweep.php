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

use Closure;

/**
 * A name map that is read from disk at most once, and only if something asks.
 *
 * The config and template maps in {@see ProjectContext} are the last things
 * {@see OrphanDetector::classify()} consults: a symbol reaches them only after a
 * code reference, an author tag, the manifest, the namespace rule and the fixture
 * rule have all declined. On a healthy project almost nothing gets that far, so
 * sweeping the config and template trees at construction billed every run for an
 * answer most runs never read.
 *
 * Deferring is only half of it — the map must still be swept exactly once, no
 * matter how many symbols reach it, which is what the memo below buys. Holding
 * the memo in a small mutable object is also what lets {@see ProjectContext} keep
 * `configNames` and `templateNames` as plain array reads: the context stays
 * immutable to its callers while the cache underneath it fills in.
 */
final class NameSweep
{
    /**
     * The swept map, or null while the sweep has not run. Null is the "not yet"
     * marker rather than an empty array, so a sweep that legitimately finds
     * nothing is still remembered and never repeated.
     *
     * @var ?array<string, string>
     */
    private ?array $names = null;

    /** @param Closure(): array<string, string> $sweep */
    public function __construct(private readonly Closure $sweep) {}

    /**
     * A sweep whose answer is already in hand — a caller-supplied map, or the
     * empty map a disabled rule contributes. Costs nothing to hold.
     *
     * @param array<string, string> $names
     */
    public static function resolved(array $names): self
    {
        $sweep = new self(static fn(): array => $names);

        $sweep->names = $names;

        return $sweep;
    }

    /** @return array<string, string> */
    public function names(): array
    {
        return $this->names ??= ($this->sweep)();
    }
}
