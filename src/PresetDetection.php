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

use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function file_get_contents;
use function dirname;
use function realpath;

/**
 * Recognising the framework a tree is built with, from the tool's own evidence.
 *
 * A preset encodes knowledge the tool already ships — that `storage/` is a
 * framework's scratch space and not the programmer's source. Requiring
 * `--preset=laravel` to obtain it means the default invocation reports noise the
 * tool already knows how to name, so detection makes the shipped rule the
 * default and leaves the flag for overriding rather than for opting in.
 *
 * ## Evidence, not path guesses
 *
 * Ruling K's ban on path patterns governs classifier features and
 * measurement-time exclusions; it is not a ban on a *declared* project
 * convention. Even so, "there is a directory called `app/`" would be a guess,
 * and this class does not make it. The evidence is what the project itself
 * declares:
 *
 *   1. a `composer.json` at or above the scan root **requires the framework
 *      package** — the project's own statement of what it is built on; and
 *   2. that manifest's directory carries one of the framework's **structural
 *      markers** — Laravel's `artisan` console entry point or its
 *      `bootstrap/app.php`.
 *
 * Both are required. The manifest alone would fire on a library that merely
 * supports the framework (`require-dev` on `laravel/framework` is how a package
 * tests against it), and a marker alone is a filename anyone may choose. Read
 * together they are a project saying, in two independent places, what it is.
 *
 * `require-dev` is deliberately **not** consulted for the same reason: a package
 * that tests against a framework is not an application built on it.
 *
 * ## Adding a framework
 *
 * One row in {@see EVIDENCE}, whose key is the preset name in {@see Presets}.
 * The rule generalises only where detection is unambiguous; a framework whose
 * presence cannot be established from a declared dependency plus a structural
 * marker does not get a row, and its preset stays opt-in.
 */
final class PresetDetection
{
    /**
     * How each shipped preset is recognised. Iterated in declaration order, so
     * detection is deterministic when a tree somehow satisfies two rows.
     *
     * @var array<string, array{label: string, package: string, markers: list<string>}>
     */
    private const array EVIDENCE = [
        'laravel' => [
            'label'   => 'Laravel',
            'package' => 'laravel/framework',
            'markers' => ['artisan', 'bootstrap/app.php'],
        ],
    ];

    /**
     * The preset the scanned tree's own manifest and layout call for, or null.
     *
     * @param list<string> $roots the scan paths
     */
    public static function detect(array $roots): ?Preset
    {
        $directory = self::nearestManifestDirectory($roots);

        if ($directory === null) {
            return null;
        }

        $required = self::requiredPackages($directory . '/composer.json');

        foreach (self::EVIDENCE as $name => $evidence) {
            if (!isset($required[$evidence['package']])) {
                continue;
            }

            foreach ($evidence['markers'] as $marker) {
                if (is_file($directory . '/' . $marker)) {
                    return Presets::get($name);
                }
            }
        }

        return null;
    }

    /** The human-facing name of a detected preset, for the announcement. */
    public static function label(?string $preset): string
    {
        return self::EVIDENCE[$preset ?? '']['label'] ?? ($preset ?? '');
    }

    /**
     * The nearest directory at or above the scan roots holding a composer.json.
     *
     * Walking up matters: `phpcpd app/` inside a framework project is an
     * ordinary invocation, and the manifest that describes it sits at the
     * project root rather than in the directory being scanned. The walk mirrors
     * {@see \LucianoPereira\PhpcpdNext\Orphan\ComposerManifest::locate()}, whose
     * reasoning is the same.
     *
     * @param list<string> $roots
     */
    private static function nearestManifestDirectory(array $roots): ?string
    {
        foreach ($roots as $root) {
            $directory = realpath(is_dir($root) ? $root : dirname($root));

            while ($directory !== false) {
                if (is_file($directory . '/composer.json')) {
                    return $directory;
                }

                $parent = dirname($directory);

                if ($parent === $directory) {
                    break;
                }

                $directory = $parent;
            }
        }

        return null;
    }

    /**
     * The manifest's `require` section as a set. A manifest that cannot be read
     * or parsed yields nothing, so a malformed file makes detection decline
     * rather than throw: detection is a convenience, and it must never be the
     * reason a scan fails.
     *
     * @return array<string, true>
     */
    private static function requiredPackages(string $manifest): array
    {
        $raw = @file_get_contents($manifest);

        if (!is_string($raw)) {
            return [];
        }

        $data = json_decode($raw, true);

        if (!is_array($data) || !isset($data['require']) || !is_array($data['require'])) {
            return [];
        }

        $packages = [];

        foreach ($data['require'] as $package => $_constraint) {
            if (is_string($package)) {
                $packages[$package] = true;
            }
        }

        return $packages;
    }
}
