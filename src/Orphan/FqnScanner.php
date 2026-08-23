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

use function preg_match_all;
use function strrpos;
use function substr;
use function substr_count;

use const PREG_OFFSET_CAPTURE;

/**
 * Pulls fully-qualified-name-shaped strings out of arbitrary text.
 *
 * Config formats that wire classes by name — neon, yaml, xml, json — do not need
 * real parsers for this job: a name is recognisable by shape alone, and every
 * such format quotes it the same way. Over-matching costs a false negative (an
 * orphan we stay quiet about); under-matching tells someone to delete live code,
 * so the shape test stays generous on purpose.
 */
final class FqnScanner
{
    private const string PATTERN = '/[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)+/';

    /**
     * Every FQN-shaped run in $text, mapped to "$label:$line" for the first
     * occurrence — both the full name and its last segment, since a config file
     * cites the FQN while the scan indexes symbols by short name.
     *
     * @param array<string, string> $into name => location
     */
    public static function collect(string $text, string $label, array &$into): void
    {
        if (preg_match_all(self::PATTERN, $text, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return;
        }

        foreach ($matches[0] as [$name, $offset]) {
            $where = $label . ':' . (substr_count($text, "\n", 0, $offset) + 1);

            $into[$name] ??= $where;

            $pos = strrpos($name, '\\');

            if ($pos !== false) {
                $into[substr($name, $pos + 1)] ??= $where;
            }
        }
    }
}
