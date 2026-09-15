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

use function array_key_exists;
use function count;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

/**
 * Owned getopt-style option parser. Replaces sebastian/cli-parser and adds value
 * validation. Driven by OptionDefinition[] so short/long forms map to one
 * canonical name and unknown options / missing or invalid values fail fast.
 */
use LucianoPereira\PhpcpdNext\Strings\Catalogue;

final class OptionParser
{
    /**
     * @param list<OptionDefinition> $definitions
     * @param list<string> $argv argv[0] (the program name) is ignored
     *
     * @throws SettingsException
     * @return array{options: list<array{0: string, 1: ?string}>, arguments: list<string>}
     */
    public function parse(array $definitions, array $argv): array
    {
        $byLong  = [];
        $byShort = [];

        foreach ($definitions as $definition) {
            $byLong[$definition->name] = $definition;

            if ($definition->short !== null) {
                $byShort[$definition->short] = $definition;
            }
        }

        $options   = [];
        $arguments = [];
        $count     = count($argv);

        for ($i = 1; $i < $count; $i++) {
            $arg = $argv[$i];

            if (str_starts_with($arg, '--')) {
                $body  = substr($arg, 2);
                $name  = $body;
                $value = null;
                $eq    = strpos($body, '=');

                if ($eq !== false) {
                    $name  = substr($body, 0, $eq);
                    $value = substr($body, $eq + 1);
                }

                if (!array_key_exists($name, $byLong)) {
                    throw new SettingsException(self::strings()->get('refuse.unknown.option', ['flag' => '--' . $name]));
                }

                $definition = $byLong[$name];

                if ($definition->takesValue) {
                    if ($value === null) {
                        $i++;

                        if ($i >= $count) {
                            throw new SettingsException(self::strings()->get('refuse.needsValue.option', ['flag' => '--' . $name]));
                        }

                        $value = $argv[$i];
                    }

                    $this->validateValue($definition, $value);
                    $options[] = [$definition->name, $value];
                } else {
                    if ($value !== null) {
                        throw new SettingsException(self::strings()->get('refuse.takesNoValue.option', ['flag' => '--' . $name]));
                    }

                    $options[] = [$definition->name, null];
                }

                continue;
            }

            if (strlen($arg) > 1 && $arg[0] === '-') {
                $chars  = substr($arg, 1);
                $length = strlen($chars);

                for ($j = 0; $j < $length; $j++) {
                    $char = $chars[$j];

                    if (!array_key_exists($char, $byShort)) {
                        throw new SettingsException(self::strings()->get('refuse.unknown.option', ['flag' => '-' . $char]));
                    }

                    $definition = $byShort[$char];

                    if ($definition->takesValue) {
                        $rest = substr($chars, $j + 1);

                        if ($rest === '') {
                            $i++;

                            if ($i >= $count) {
                                throw new SettingsException(self::strings()->get('refuse.needsValue.option', ['flag' => '-' . $char]));
                            }

                            $rest = $argv[$i];
                        }

                        $this->validateValue($definition, $rest);
                        $options[] = [$definition->name, $rest];

                        break;
                    }

                    $options[] = [$definition->name, null];
                }

                continue;
            }

            $arguments[] = $arg;
        }

        return ['options' => $options, 'arguments' => $arguments];
    }

    /**
     * @throws SettingsException
     */
    private function validateValue(OptionDefinition $definition, string $value): void
    {
        $invalid = $definition->firstInvalid($value);

        if ($invalid !== null) {
            throw new SettingsException($definition->invalidValueMessage($invalid));
        }
    }

    /**
     * The catalogue, made once.
     *
     * A static holder rather than a constructor parameter because these are
     * static entry points reached from the argument parser, where there is no
     * object to inject into and no caller who would want a different language
     * than the run does.
     */
    /** Built fresh, not cached: the first caller runs before the language is known. */
    private static function strings(): Catalogue
    {
        return new Catalogue();
    }
}
