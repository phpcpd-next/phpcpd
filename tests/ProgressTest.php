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

use function array_shift;
use function count;
use function explode;
use function fopen;
use function mb_strlen;
use function mb_strpos;
use function rewind;
use function str_contains;
use function stream_get_contents;
use function substr_count;

use LucianoPereira\PhpcpdNext\Console\Progress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Progress::class)]
final class ProgressTest extends TestCase
{
    /** @return array{0: Progress, 1: resource} */
    private function bar(int $width = 80): array
    {
        $stream = fopen('php://memory', 'r+');

        self::assertIsResource($stream);

        return [new Progress($stream, $width), $stream];
    }

    /** @param resource $stream */
    private function read(mixed $stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    /**
     * A pipe is not watching. `on()` is the gate, and it is what keeps the
     * per-file cost of a redirected run at nothing at all.
     */
    #[Test]
    public function thereIsNoBarWhenNothingIsWatching(): void
    {
        $stream = fopen('php://memory', 'r+');

        self::assertIsResource($stream);
        self::assertNull(Progress::on($stream));
        self::assertNull(Progress::on(null));
    }

    /**
     * Once per percentage point, not once per file: a write syscall per file is
     * time spent on the bar rather than on the scan it is about.
     */
    #[Test]
    public function aThousandFilesDrawOneFramePerPercentagePoint(): void
    {
        [$bar, $stream] = $this->bar();

        for ($file = 1; $file <= 1000; ++$file) {
            $bar->file('exact', $file, 1000);
        }

        // Nought through a hundred: 101 frames for 1,000 files.
        self::assertSame(101, substr_count($this->read($stream), "\r"));
    }

    /** Every frame fits, and the right bracket does not move as counts grow. */
    #[Test]
    public function everyFrameIsTheSameWidthAndFitsTheTerminal(): void
    {
        [$bar, $stream] = $this->bar(60);

        for ($file = 1; $file <= 300; ++$file) {
            $bar->file($file % 2 === 0 ? 'exact' : 'reordered', $file, 300);
        }

        $frames = explode("\r", $this->read($stream));
        array_shift($frames);

        self::assertCount(101, $frames);

        $widths = [];

        foreach ($frames as $frame) {
            self::assertLessThanOrEqual(60, mb_strlen($frame));
            $widths[mb_strlen($frame)] = true;
        }

        self::assertCount(1, $widths, 'the bar should not change width mid-pass');
    }

    /**
     * The guarantee across passes, not just within one. `exact` and `reordered`
     * differ by four characters, and the default run draws both over the same
     * line: a label that changes width moves the closing bracket with it.
     */
    #[Test]
    public function bothPassesDrawTheSameShape(): void
    {
        [$bar, $stream] = $this->bar(70);

        $bar->file('exact', 7, 340);
        $bar->file('reordered', 340, 340);

        $frames = explode("\r", $this->read($stream));
        array_shift($frames);

        self::assertCount(2, $frames);
        self::assertSame(mb_strlen($frames[0]), mb_strlen($frames[1]));

        // And the bracket is in the same column in both.
        self::assertSame(mb_strpos($frames[0], '['), mb_strpos($frames[1], '['));
        self::assertSame(mb_strpos($frames[0], ']'), mb_strpos($frames[1], ']'));
    }

    /** No room for a bar is not a reason to print nothing. */
    #[Test]
    public function aNarrowTerminalGetsTheCountsWithoutABar(): void
    {
        [$bar, $stream] = $this->bar(20);

        $bar->file('reordered', 5, 10);

        $frame = $this->read($stream);

        self::assertStringContainsString('5/10', $frame);
        self::assertStringNotContainsString('[', $frame);
    }

    /**
     * Progress is not a result. What is left on the screen afterwards should be
     * the report, so the bar wipes the columns it wrote and nothing else.
     */
    #[Test]
    public function theBarErasesItself(): void
    {
        [$bar, $stream] = $this->bar(60);

        $bar->file('exact', 1, 2);
        $bar->done();

        $written = $this->read($stream);
        $last    = explode("\r", $written);

        self::assertSame('', $last[count($last) - 1]);
        self::assertFalse(str_contains($last[count($last) - 2], '#'));
    }

    #[Test]
    public function erasingTwiceWritesNothingTheSecondTime(): void
    {
        [$bar, $stream] = $this->bar();

        $bar->file('exact', 1, 2);
        $bar->done();
        $before = $this->read($stream);

        $bar->done();

        self::assertSame($before, $this->read($stream));
    }
}
