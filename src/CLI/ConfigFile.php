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
use function is_array;
use function is_bool;
use function dirname;
use function is_dir;
use function is_file;
use function is_scalar;
use function realpath;
use function parse_ini_file;
use function sprintf;

use const INI_SCANNER_TYPED;

/**
 * phpcpd.ini — the persistent form of the command line.
 *
 * Its keys ARE the long option names, so the file needs no vocabulary of its
 * own: whatever `--help` documents is what a project writes down, and an option
 * added to {@see Options} is configurable the day it ships.
 *
 * Settings are layered, each one overriding the last:
 *
 *   built-in defaults  →  user config  →  project config  →  command line
 *
 * The user config is ~/.config/phpcpd/phpcpd.ini (or ~/.phpcpd.ini); the project
 * config is the nearest phpcpd.ini at or above a scanned path. A key the project
 * does not set keeps whatever the user config gave it, and a key neither sets
 * keeps the built-in default — so a project file states only its differences.
 *
 * Two kinds of key behave differently, matching how presets and flags already
 * combine: a single-valued key (min-tokens) is REPLACED by the closer layer,
 * while a repeatable one (exclude, suffix) APPENDS to it, because a project
 * adding one exclude means "and also this", not "forget the others".
 *
 *   ; phpcpd.ini
 *   min-tokens = 60
 *   exclude[] = build
 *   exclude[] = "*.blade.php"
 *   no-suppress = fixtures
 *   fail-on = dead,planned
 */
final class ConfigFile
{
    public const string DEFAULT_NAME = 'phpcpd.ini';

    private const int MAX_ASCENT = 8;

    /**
     * Read $file into the parser's option shape.
     *
     * @param list<OptionDefinition> $definitions
     * @throws ArgumentsBuilderException
     * @return list<array{0: string, 1: ?string}>
     */
    public static function read(string $file, array $definitions): array
    {
        if (!is_file($file)) {
            throw new ArgumentsBuilderException('Config file not found: ' . $file);
        }

        $parsed = @parse_ini_file($file, false, INI_SCANNER_TYPED);

        if ($parsed === false) {
            throw new ArgumentsBuilderException('Could not parse config file: ' . $file);
        }

        $known = [];

        foreach ($definitions as $definition) {
            $known[$definition->name] = $definition;
        }

        $options = [];

        /** @var mixed $value */
        foreach ($parsed as $key => $value) {
            if (!array_key_exists($key, $known)) {
                throw new ArgumentsBuilderException(sprintf('Unknown setting "%s" in %s', $key, $file));
            }

            foreach (self::valuesFor($known[$key], $key, $value, $file) as $option) {
                $options[] = $option;
            }
        }

        return $options;
    }

    /**
     * Every config file in force, lowest precedence first.
     *
     * @param list<string> $paths the directories being scanned
     * @return list<string>
     */
    public static function discoverAll(array $paths = []): array
    {
        $files  = [];
        $user   = self::userFile();
        $project = self::discover($paths);

        if ($user !== null) {
            $files[] = $user;
        }

        if ($project !== null && $project !== $user) {
            $files[] = $project;
        }

        return $files;
    }

    /**
     * The user-wide defaults, if any. XDG first, then a dotfile in $HOME.
     */
    public static function userFile(): ?string
    {
        $home = getenv('HOME');
        $xdg  = getenv('XDG_CONFIG_HOME');

        $candidates = [];

        if (is_string($xdg) && $xdg !== '') {
            $candidates[] = $xdg . '/phpcpd/' . self::DEFAULT_NAME;
        }

        if (is_string($home) && $home !== '') {
            $candidates[] = $home . '/.config/phpcpd/' . self::DEFAULT_NAME;
            $candidates[] = $home . '/.' . self::DEFAULT_NAME;
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The project's own file: the nearest phpcpd.ini at or above a scanned path,
     * else one in the working directory.
     *
     * Searching from the scanned paths is what makes the file belong to the
     * PROJECT rather than to wherever the command happened to be typed — running
     * `phpcpd ../other-project/src` picks up that project's settings, not this
     * shell's.
     *
     * @param list<string> $paths
     */
    public static function discover(array $paths = []): ?string
    {
        foreach ($paths as $path) {
            $dir = realpath($path);

            if ($dir === false) {
                continue;
            }

            $dir = is_dir($dir) ? $dir : dirname($dir);

            for ($i = 0; $i < self::MAX_ASCENT; $i++) {
                $candidate = $dir . '/' . self::DEFAULT_NAME;

                if (is_file($candidate)) {
                    return $candidate;
                }

                $parent = dirname($dir);

                if ($parent === $dir) {
                    break;
                }

                $dir = $parent;
            }
        }

        return is_file(self::DEFAULT_NAME) ? self::DEFAULT_NAME : null;
    }

    /**
     * @throws ArgumentsBuilderException
     * @return list<array{0: string, 1: ?string}>
     */
    private static function valuesFor(OptionDefinition $definition, string $key, mixed $value, string $file): array
    {
        if (is_array($value)) {
            $options = [];

            /** @var mixed $item */
            foreach ($value as $item) {
                foreach (self::valuesFor($definition, $key, $item, $file) as $option) {
                    $options[] = $option;
                }
            }

            return $options;
        }

        if (!$definition->takesValue) {
            // A flag is on when the setting is truthy; `orphans = false` is the
            // same as not writing the line at all.
            return $value === false ? [] : [[$key, null]];
        }

        if (is_bool($value) || !is_scalar($value)) {
            throw new ArgumentsBuilderException(sprintf('Setting "%s" in %s needs a value', $key, $file));
        }

        $invalid = $definition->firstInvalid((string) $value);

        if ($invalid !== null) {
            throw new ArgumentsBuilderException($definition->invalidValueMessage($invalid) . ' in ' . $file);
        }

        return [[$key, (string) $value]];
    }
}
