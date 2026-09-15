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

namespace LucianoPereira\PhpcpdNext\Console;

use function fwrite;
use function intdiv;
use function max;
use function mb_str_pad;
use function mb_strlen;
use function sprintf;
use function str_repeat;
use function stream_isatty;

use const STR_PAD_LEFT;

/**
 * What a long scan is doing, while it does it.
 *
 * A unified run over phpunit takes half a minute and printed nothing until it
 * was finished, which is indistinguishable from a hang — and the honest reading
 * of a tool that appears hung is to kill it, which costs the whole run.
 *
 * On stderr, because the report is on stdout and a progress bar in a redirected
 * report is corruption. On a terminal only, because the bar is written with
 * carriage returns and overwrites itself; in a log file that is a single line of
 * accumulated garbage. Both of those are the same rule the diagnostics follow:
 * ask the stream you are about to write to, not the other one.
 *
 * It erases itself when the scan ends. Progress is not a result, and nothing it
 * printed should survive into what the user reads afterwards.
 */
final class Progress
{
    /** Below this there is no room for a bar, so only the counts are shown. */
    private const int NARROWEST_BAR = 8;

    /**
     * The column the counts start in, wide enough for the longest pass name the
     * engine runs — `rabin-karp`. A shorter name is padded into it rather than
     * shortening the line, because the passes draw over each other and a label
     * that changes width moves every column to its right.
     *
     * A longer name than this only widens its own pass's label; it cannot
     * corrupt the line.
     */
    private const int PHASE_WIDTH = 10;

    private int $drawn = -1;
    private int $length = 0;

    /**
     * Public so a test can point one at a stream it can read back. Callers in
     * the product use `on()`, which is the one that decides whether there is
     * anything worth drawing on.
     *
     * @param resource $stream
     */
    public function __construct(
        private readonly mixed $stream,
        private readonly int $width,
    ) {}

    /**
     * A progress bar, or nothing at all when there is no terminal watching.
     *
     * @param ?resource $stream
     */
    public static function on(mixed $stream, ?Terminal $terminal = null): ?self
    {
        if ($stream === null || !stream_isatty($stream)) {
            return null;
        }

        return new self($stream, ($terminal ?? Terminal::detect($stream))->room());
    }

    /**
     * Redrawn only when the percentage changes.
     *
     * This is called once per file, and a write syscall per file on a 2,689-file
     * corpus is time spent on the bar rather than on the scan the bar is about.
     * A hundred redraws is as much motion as an eye reads.
     */
    public function file(string $phase, int $done, int $total): void
    {
        $percent = $total === 0 ? 100 : intdiv($done * 100, $total);

        if ($percent === $this->drawn) {
            return;
        }

        $this->drawn = $percent;

        // Every column is set to the width it will ever need, so that no frame
        // in the run is a different shape from any other.
        //
        // The bar is drawn over itself with carriage returns, and a column that
        // changes width drags everything to its right along with it. Three
        // things change width during a run and all three are normalised here:
        // the pass name, which is four characters shorter in `exact` than in
        // `reordered`; the counter, which gains a digit at 10, 100 and 1,000;
        // and the percentage, which gains one at 10 % and 100 %. Left alone
        // they moved the closing bracket four columns when the second pass
        // began and one column three times within each pass.
        //
        // Normalising the name is what makes the bar's width depend on the file
        // count alone — which is the same for both passes — so the segments are
        // in the same places from the first frame to the last.
        $digits = mb_strlen((string) $total);

        $label = sprintf(
            '  %s %s/%d %3d%%',
            mb_str_pad($phase, self::PHASE_WIDTH),
            mb_str_pad((string) $done, $digits, ' ', STR_PAD_LEFT),
            $total,
            $percent,
        );

        // Two for the brackets, two for the gap, one to keep the cursor off the
        // last column — a terminal that wraps there scrolls the bar away.
        $room = $this->width - mb_strlen($label) - 5;
        $bar  = '';

        if ($room >= self::NARROWEST_BAR) {
            $filled = intdiv($percent * $room, 100);
            $bar    = '  [' . str_repeat('#', $filled) . str_repeat('.', $room - $filled) . ']';
        }

        $line         = $label . $bar;
        $this->length = max($this->length, mb_strlen($line));

        fwrite($this->stream, "\r" . $line);
    }

    /** Erase the bar. What is left on the screen should be the report. */
    public function done(): void
    {
        if ($this->length === 0) {
            return;
        }

        fwrite($this->stream, "\r" . str_repeat(' ', $this->length) . "\r");

        $this->length = 0;
        $this->drawn  = -1;
    }
}
