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

/**
 * Applies a {@see Style}, or does not.
 *
 * Colourless by construction. Only the composition root sees a real terminal
 * and only it passes `of()`; a reporter built anywhere else — a golden capture,
 * a benchmark, a test — is plain without having to arrange for a pipe or an
 * environment variable. That is the difference between output that is stable
 * because it was proven so and output that is stable because the machine
 * running it happened not to have a terminal attached.
 */
final readonly class Ink
{
    private function __construct(private bool $colour) {}

    /** Colour if this terminal takes it. */
    public static function of(Terminal $terminal): self
    {
        return new self($terminal->colour);
    }

    public static function plain(): self
    {
        return new self(false);
    }

    public function paint(Style $style, string $text): string
    {
        if (!$this->colour || $text === '') {
            return $text;
        }

        return "\e[" . $style->sequence() . 'm' . $text . "\e[0m";
    }
}
