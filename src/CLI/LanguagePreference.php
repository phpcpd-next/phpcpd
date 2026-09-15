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

use function count;
use function in_array;
use function str_starts_with;
use function strlen;
use function substr;

use LucianoPereira\PhpcpdNext\Strings\Catalogue;
use Throwable;

/**
 * Which language to print a refusal in, before the settings that would name one
 * exist: `Settings::fromArgv()` prints its own refusals while it is still
 * parsing.
 *
 * Not a parser, and must never become one. It looks for one setting, ignores
 * what it does not recognise, and never raises — a malformed command line is
 * reported properly a moment later. {@see Application::run()} replaces this
 * answer with `$settings->language` as soon as the parse succeeds.
 */
final class LanguagePreference
{
    /**
     * The language argv and the config files ask for, or the fallback.
     *
     * @param list<string> $argv
     */
    public static function fromArgv(array $argv): string
    {
        $fromCli = self::scan($argv);

        // The command line wins, as it does in the real fold.
        $language = $fromCli['language'] ?? self::fromConfig($argv, $fromCli);

        // An unknown language is not refused here: the real parse has a better
        // refusal for it, against the option's allowed values.
        if ($language === null || !in_array($language, self::available(), true)) {
            return Catalogue::FALLBACK;
        }

        return $language;
    }

    /**
     * The three long options that decide the answer. Matched literally: an
     * option name is spelled the same way whatever the language.
     *
     * @param list<string> $argv
     * @return array{language: ?string, config: ?string, noConfig: bool}
     */
    private static function scan(array $argv): array
    {
        $found = ['language' => null, 'config' => null, 'noConfig' => false];
        $count = count($argv);

        for ($i = 1; $i < $count; $i++) {
            $arg = $argv[$i];

            if ($arg === '--no-config') {
                $found['noConfig'] = true;

                continue;
            }

            foreach (['language', 'config'] as $name) {
                $prefix = '--' . $name;

                if ($arg === $prefix) {
                    if ($i + 1 < $count) {
                        $found[$name] = $argv[++$i];
                    }

                    continue 2;
                }

                if (str_starts_with($arg, $prefix . '=')) {
                    $found[$name] = substr($arg, strlen($prefix) + 1);

                    continue 2;
                }
            }
        }

        return $found;
    }

    /**
     * `language` as the config files in force declare it. Read through
     * `ConfigFile`, never re-read, so the two cannot disagree. Failures are
     * swallowed — the real parse is about to raise them properly.
     *
     * @param list<string>                                          $argv
     * @param array{language: ?string, config: ?string, noConfig: bool} $found
     */
    private static function fromConfig(array $argv, array $found): ?string
    {
        if ($found['noConfig']) {
            return null;
        }

        $cliOptions = [];

        if ($found['config'] !== null) {
            $cliOptions[] = ['config', $found['config']];
        }

        try {
            $language = null;

            foreach (ConfigFile::settings($cliOptions, self::directories($argv), Options::definitions()) as [$name, $value]) {
                if ($name === 'language' && $value !== null) {
                    $language = $value;
                }
            }

            return $language;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The scan roots, which is how a project config file is discovered. A value
     * that followed an option is counted too; at worst that looks for a config
     * file somewhere there is none.
     *
     * @param list<string>           $argv
     * @return list<non-empty-string>
     */
    private static function directories(array $argv): array
    {
        $directories = [];
        $count       = count($argv);

        for ($i = 1; $i < $count; $i++) {
            $arg = $argv[$i];

            if ($arg !== '' && !str_starts_with($arg, '-')) {
                $directories[] = $arg;
            }
        }

        return $directories;
    }

    /** @return list<string> */
    private static function available(): array
    {
        try {
            return Catalogue::available();
        } catch (Throwable) {
            return [];
        }
    }
}
