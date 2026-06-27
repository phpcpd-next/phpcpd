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

namespace LucianoPereira\PhpcpdNext;

/**
 * One CLI option, declared once and used for parsing, validation, and help
 * generation — so the parser config and the --help text cannot drift apart.
 */
final readonly class OptionDefinition
{
    /**
     * @param ?non-empty-list<string> $allowedValues restrict the value to this set (validation)
     */
    public function __construct(
        public string $name,
        public bool $takesValue = false,
        public ?string $short = null,
        public bool $repeatable = false,
        public ?array $allowedValues = null,
        public string $valuePlaceholder = '',
        public string $description = '',
        public string $group = '',
        public bool $advanced = false,
    ) {}
}
