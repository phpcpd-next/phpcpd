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

use function memory_get_peak_usage;
use function sprintf;

/**
 * Formats run time + peak memory, and — unlike the generic phpunit/php-timer
 * formatter — domain throughput (files scanned and files/second), which is the
 * number that actually tells you how a clone scan performed on a given codebase.
 */
final class ResourceUsageFormatter
{
    private const float BYTES_PER_MEGABYTE = 1_048_576.0;
    private const float SECONDS_PER_MINUTE = 60.0;

    public function format(float $seconds, int $filesScanned = 0): string
    {
        $line = sprintf(
            'Time: %s, Memory: %.2f MB',
            $this->duration($seconds),
            memory_get_peak_usage(true) / self::BYTES_PER_MEGABYTE,
        );

        if ($filesScanned <= 0) {
            return $line;
        }

        if ($seconds > 0.0) {
            return $line . sprintf(' — %d files (%.1f files/s)', $filesScanned, $filesScanned / $seconds);
        }

        return $line . sprintf(' — %d files', $filesScanned);
    }

    private function duration(float $seconds): string
    {
        if ($seconds < self::SECONDS_PER_MINUTE) {
            return sprintf('%.3fs', $seconds);
        }

        $minutes = (int) ($seconds / self::SECONDS_PER_MINUTE);

        return sprintf('%dm %.3fs', $minutes, $seconds - ($minutes * self::SECONDS_PER_MINUTE));
    }
}
