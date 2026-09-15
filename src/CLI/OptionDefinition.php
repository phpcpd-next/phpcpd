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

use function explode;
use function implode;
use function in_array;
use function trim;

/**
 * One CLI option, declared once and used for parsing, validation, and help
 * generation — so the parser config and the --help text cannot drift apart.
 */
use LucianoPereira\PhpcpdNext\Strings\Catalogue;

final readonly class OptionDefinition
{
    /**
     * @param ?non-empty-list<string> $allowedValues restrict the value to this set (validation)
     * @param bool $listValue the value is a comma-separated list; each element is validated
     */
    public function __construct(
        public string $name,
        public bool $takesValue = false,
        public ?string $short = null,
        public bool $repeatable = false,
        public ?array $allowedValues = null,
        public string $valuePlaceholder = '',
        public string $description = '',
        /**
         * Values the description's own placeholders need.
         *
         * Almost every description is a fixed sentence. `--no-suppress` names
         * the suppression rules a user can actually type, which the code knows
         * and a translator does not, so it arrives as `:rules` rather than
         * being spelled out in the language file where it would go stale the
         * next time a rule is added.
         *
         * @var array<string, string|int>
         */
        public array $descriptionParameters = [],
        public string $group = '',
        public bool $advanced = false,
        public bool $listValue = false,
    ) {}

    /**
     * The first element of $value this option does not allow, or null when the
     * whole value is acceptable. Shared by the argv parser and the config file
     * reader so a setting cannot slip past a check a flag would have failed.
     */
    public function firstInvalid(string $value): ?string
    {
        if ($this->allowedValues === null) {
            return null;
        }

        foreach ($this->listValue ? explode(',', $value) : [$value] as $candidate) {
            $candidate = trim($candidate);

            if (!in_array($candidate, $this->allowedValues, true)) {
                return $candidate;
            }
        }

        return null;
    }

    public function invalidValueMessage(string $candidate): string
    {
        return (new Catalogue())->get('refuse.invalidValue.option', [
            'value'   => $candidate,
            'flag'    => '--' . $this->name,
            'allowed' => implode(', ', $this->allowedValues ?? []),
        ]);
    }
}
