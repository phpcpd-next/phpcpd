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

namespace LucianoPereira\PhpcpdNext\Util;

use function hrtime;

/**
 * Minimal monotonic stopwatch. Uses hrtime() (nanosecond, monotonic — unaffected
 * by wall-clock adjustments), replacing phpunit/php-timer for our one use.
 */
final class Timer
{
    private const float NANOSECONDS_PER_SECOND = 1_000_000_000.0;

    private int $startedAt = 0;

    public function start(): void
    {
        $this->startedAt = hrtime(true);
    }

    public function seconds(): float
    {
        return (hrtime(true) - $this->startedAt) / self::NANOSECONDS_PER_SECOND;
    }
}
