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

namespace LucianoPereira\PhpcpdNext\Tests;

require_once __DIR__ . '/_guard.php';

use LucianoPereira\PhpcpdNext\Util\ResourceUsageFormatter;
use LucianoPereira\PhpcpdNext\Util\Timer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceUsageFormatter::class)]
#[CoversClass(Timer::class)]
final class ResourceUsageFormatterTest extends TestCase
{
    #[Test]
    public function reports_time_and_memory(): void
    {
        $line = (new ResourceUsageFormatter())->format(0.5);

        self::assertStringContainsString('Time: 0.500s', $line);
        self::assertStringContainsString('Memory:', $line);
        self::assertStringContainsString('MB', $line);
        self::assertStringNotContainsString('files', $line);
    }

    #[Test]
    public function adds_throughput_when_files_were_scanned(): void
    {
        // The domain improvement over a generic timer: files and files/second.
        $line = (new ResourceUsageFormatter())->format(0.5, 100);

        self::assertStringContainsString('100 files', $line);
        self::assertStringContainsString('200.0 files/s', $line);
    }

    #[Test]
    public function omits_rate_when_no_time_elapsed(): void
    {
        $line = (new ResourceUsageFormatter())->format(0.0, 5);

        self::assertStringContainsString('5 files', $line);
        self::assertStringNotContainsString('files/s', $line);
    }

    #[Test]
    public function formats_durations_over_a_minute_with_minutes(): void
    {
        self::assertStringContainsString('Time: 1m ', (new ResourceUsageFormatter())->format(65.0));
    }

    #[Test]
    public function timer_measures_a_non_negative_elapsed_time(): void
    {
        $timer = new Timer();
        $timer->start();

        self::assertGreaterThanOrEqual(0.0, $timer->seconds());
    }
}
