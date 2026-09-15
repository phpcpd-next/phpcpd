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

use function getenv;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function preg_match;
use function shell_exec;
use function defined;
use function stream_isatty;

use const STDOUT;

/**
 * What the terminal this run is writing to can do.
 *
 * A value object rather than a static accessor, because everything that reads
 * it has to be testable against a terminal it does not have: a 40-column one, a
 * pipe, a machine with `NO_COLOR` set. A global with a `reset()` is how that
 * gets tested badly.
 *
 * The questions are only the two this tool actually asks — how wide, and may I
 * use colour. phpcpd never reads a keystroke, so there is no interactivity here
 * and no `stty -icanon`; asking whether *stdin* is a terminal, which an
 * interactive prompt must, would cost a CI job its colour for no reason.
 */
final readonly class Terminal
{
    /**
     * Below this, wrapping produces more indentation than text.
     */
    private const int MINIMUM_WIDTH = 40;

    /**
     * Help text is prose, and prose set to the full width of a wide terminal is
     * hard to read — the eye loses the line on the way back. Typography's answer
     * is a measure of roughly 60 to 90 characters; this is the top of that,
     * chosen once here rather than argued about at each call site.
     */
    private const int COMFORTABLE_WIDTH = 90;

    public function __construct(
        public int $width = 80,
        public bool $colour = false,
    ) {}

    /**
     * The terminal this process is actually attached to.
     *
     * The stream matters: stdout and stderr are redirected independently, and
     * `phpcpd src > report.txt` in a terminal has a piped stdout and a terminal
     * stderr. Asking about the wrong one either strips colour from a diagnostic
     * the user is watching or writes escape sequences into their report.
     *
     * @param ?resource $stream defaults to stdout
     */
    public static function detect(mixed $stream = null): self
    {
        $stream ??= defined('STDOUT') ? STDOUT : null;

        return new self(self::detectWidth($stream), self::detectColour($stream));
    }

    /**
     * A width for prose: the terminal's, but never so narrow that wrapping is
     * pointless nor so wide that the text stops being readable.
     */
    public function measure(): int
    {
        return max(self::MINIMUM_WIDTH, min($this->width, self::COMFORTABLE_WIDTH));
    }

    /**
     * A width for data: all the terminal has, floored where a table stops
     * being readable. Prose gets `measure()` and its cap; a table does not
     * want one, because the columns it cannot fit are the ones it must wrap.
     */
    public function room(): int
    {
        return max(self::MINIMUM_WIDTH, $this->width);
    }

    /** @param ?resource $stream */
    private static function detectWidth(mixed $stream): int
    {
        $columns = getenv('COLUMNS');

        if (is_numeric($columns) && (int) $columns > 0) {
            return (int) $columns;
        }

        // Only worth a subprocess when there is a terminal to ask about.
        if (!self::attached($stream)) {
            return 80;
        }

        $size = shell_exec('stty size 2>/dev/null');

        if (is_string($size) && preg_match('/^\d+\s+(\d+)/', $size, $match) === 1) {
            return max(1, (int) $match[1]);
        }

        return 80;
    }

    /**
     * `NO_COLOR` before `FORCE_COLOR` before detection.
     *
     * The precedence is the convention's: an explicit refusal outranks an
     * explicit request, and both outrank what the stream happens to be. A pipe
     * gets no colour, which is what keeps a redirected report byte-identical to
     * the goldens.
     */
    /** @param ?resource $stream */
    private static function detectColour(mixed $stream): bool
    {
        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        if (getenv('FORCE_COLOR') !== false) {
            return true;
        }

        return self::attached($stream);
    }

    /** @param ?resource $stream */
    private static function attached(mixed $stream): bool
    {
        return $stream !== null && stream_isatty($stream);
    }
}
