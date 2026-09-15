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

use function count;
use function explode;
use function max;
use function mb_str_pad;
use function mb_strlen;
use function rtrim;
use function str_repeat;
use function wordwrap;

use const PHP_EOL;

/**
 * Columns that fit the terminal.
 *
 * Aligning by the widest cell is right until one cell is wide. The settings
 * table padded every VALUE to the width of the longest exclude list, so a run
 * with four `--exclude` flags pushed twenty rows of trailing whitespace and the
 * whole SOURCE column off the right edge of an eighty-column screen — rows whose
 * value was the word `false`.
 *
 * So: give each column what it needs, and when the total does not fit, take the
 * space back from the widest column and wrap its cells. The last column is never
 * padded, and every line is right-trimmed, because trailing whitespace is
 * invisible until someone diffs it.
 *
 * No borders, no separators, no styles to choose between. This is the alignment
 * the reports already do by hand, done once and against a known width.
 */
final readonly class Table
{
    /** Space between columns. Two, so a padded cell still reads as a gap. */
    private const int GUTTER = 2;

    /**
     * A column narrower than this wraps into a stack of fragments and stops
     * being a column, so shrinking stops here and the table is allowed to
     * overhang instead. An unreadable table that fits is not the goal.
     */
    private const int NARROWEST_COLUMN = 8;

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows each the same length as $headers
     */
    public function __construct(
        private array $headers,
        private array $rows,
    ) {}

    public function render(Terminal $terminal, string $indent = '  '): string
    {
        if ($this->rows === []) {
            return '';
        }

        $widths = $this->fitted($terminal->room() - mb_strlen($indent));
        $table  = $this->line($this->headers, $widths, $indent);

        foreach ($this->rows as $row) {
            $table .= $this->line($row, $widths, $indent);
        }

        return $table;
    }

    /**
     * Natural widths, then space taken back from the widest column until the
     * table fits or no column can give more.
     *
     * @return list<int>
     */
    private function fitted(int $budget): array
    {
        $widths = [];

        // Walked by column rather than by cell, because the width of column
        // three is a fact about column three in every row.
        foreach ($this->headers as $column => $header) {
            $width = mb_strlen($header);

            foreach ($this->rows as $row) {
                $width = max($width, mb_strlen($row[$column] ?? ''));
            }

            $widths[] = $width;
        }

        $budget -= self::GUTTER * (count($this->headers) - 1);

        while ($this->total($widths) > $budget) {
            $widest = 0;

            foreach ($widths as $column => $width) {
                if ($width > $widths[$widest]) {
                    $widest = $column;
                }
            }

            if ($widths[$widest] <= self::NARROWEST_COLUMN) {
                break;
            }

            --$widths[$widest];
        }

        return $widths;
    }

    /** @param list<int> $widths */
    private function total(array $widths): int
    {
        $total = 0;

        foreach ($widths as $width) {
            $total += $width;
        }

        return $total;
    }

    /**
     * One row, as the however-many physical lines its tallest cell needs.
     *
     * A wrapped cell's continuation sits under itself, so the row still reads
     * across: the columns to its left are blank on the continuation lines
     * rather than repeated.
     *
     * @param list<string> $row
     * @param list<int> $widths
     */
    private function line(array $row, array $widths, string $indent): string
    {
        $wrapped = [];
        $height  = 1;

        foreach ($row as $column => $cell) {
            $fragments        = explode(PHP_EOL, wordwrap($cell, $widths[$column], PHP_EOL, true));
            $wrapped[$column] = $fragments;
            $height           = max($height, count($fragments));
        }

        $lines = '';

        for ($at = 0; $at < $height; ++$at) {
            $line = $indent;
            $last = count($row) - 1;

            foreach ($row as $column => $_) {
                $fragment = $wrapped[$column][$at] ?? '';

                $line .= $column === $last
                    ? $fragment
                    : mb_str_pad($fragment, $widths[$column]) . str_repeat(' ', self::GUTTER);
            }

            $lines .= rtrim($line) . PHP_EOL;
        }

        return $lines;
    }
}
