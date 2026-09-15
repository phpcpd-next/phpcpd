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
use function dirname;
use function getcwd;
use function implode;
use function is_dir;
use function is_file;
use function realpath;
use function str_starts_with;

use const PHP_EOL;

use LucianoPereira\PhpcpdNext\Orphan\ProjectContext;
use LucianoPereira\PhpcpdNext\Util\FileFinder;

/**
 * What a run is actually looking at, stated before it reports what it found.
 *
 * Every failure this class guards against shares one shape: a wrong answer that
 * reads as a right one. `phpcpd /` differs from `phpcpd ./` by one character and
 * several million files, and announces the difference with a stack trace rather
 * than a message. `--preset=laravel` on a project with no `app/` directory scans
 * 2% of the source and prints `No code clones found`, which is indistinguishable
 * from `I did not look`. An `--orphans` run over one subdirectory calls nine live
 * controllers unwired, because the code importing them was outside the scan.
 *
 * None of those is a detection bug. Each is the tool staying silent about its own
 * scope, so each is answered here — refuse the runaway root, name the paths a
 * preset declared but could not find, name the resolved root in the scope line,
 * and say when an orphan scan cannot see the whole project.
 */
use LucianoPereira\PhpcpdNext\Strings\Catalogue;

final class ScanScope
{
    /**
     * Files that mark the top of a PHP project. A scan root above the nearest one
     * of these is scanning something larger than the project the user is standing
     * in, which is almost always a typo.
     *
     * @var list<string>
     */
    private const array PROJECT_MARKERS = ['composer.json', 'phpcpd.ini'];

    /**
     * Why this scan root is refused, or null when it is fine. Refusing beats
     * warning here: the run that follows a mistyped root is not merely noisy, it
     * walks the filesystem for minutes and then reports totals nobody asked for.
     */
    public static function refusal(Settings $settings): ?string
    {
        if ($settings->allowRootScan) {
            return null;
        }

        foreach ($settings->directories as $directory) {
            $resolved = realpath($directory);

            if ($resolved === false) {
                continue;
            }

            if ($resolved === '/' || $resolved === '\\') {
                return self::strings()->withHint(
                    self::strings()->get('refuse.outOfScope.filesystemRoot', ['path' => $directory]),
                    self::strings()->get('advise.scan.allowRoot'),
                );
            }

            $project = self::nearestProjectRoot();

            if ($project !== null && self::isStrictAncestor($resolved, $project)) {
                return self::strings()->withHint(
                    self::strings()->get('refuse.outOfScope.aboveProject', ['path' => $resolved, 'project' => $project]),
                    self::strings()->get('advise.scan.allowOutside'),
                );
            }
        }

        return null;
    }

    /**
     * Scan paths a preset declared that do not exist. A preset encodes one
     * framework's conventional layout; a project that does not follow it gets a
     * scan of whatever happened to match, and no indication that the rest was
     * never there.
     *
     * Only preset-supplied paths are checked. A path the user typed is their
     * claim about their own project, and `find()` already ignores what is missing.
     *
     * @return list<string>
     */
    public static function missingPresetPaths(Settings $settings): array
    {
        if (!$settings->directoriesFromPreset) {
            return [];
        }

        $missing = [];

        foreach ($settings->directories as $directory) {
            if (!is_dir($directory)) {
                $missing[] = $directory;
            }
        }

        return $missing;
    }

    /**
     * The warning for {@see missingPresetPaths()}, or null when every declared
     * path exists. Comparing declared paths against resolved ones costs nothing
     * and turns a silent false pass into an obvious misconfiguration.
     */
    public static function presetWarning(Settings $settings): ?string
    {
        $missing = self::missingPresetPaths($settings);

        if ($missing === []) {
            return null;
        }

        return self::strings()->get('warn.preset.missingPaths', [
            'preset'   => $settings->preset ?? '?',
            'declared' => count($settings->directories),
            'missing'  => count($missing),
            'paths'    => implode(', ', $missing),
        ]);
    }

    /**
     * The warning an `--orphans` run over a subtree needs, or null when the scan
     * covers the project.
     *
     * Orphan detection is only sound when the scan can see every possible
     * referencing site: a symbol is dead if *nothing* references it, and nothing
     * is a claim about the whole project. Measured on the adopting codebase,
     * scanning one controller directory reported nine live controllers as "whole
     * file is unwired" — every one of them imported from a different package.
     */
    public static function subtreeWarning(ProjectContext $context): ?string
    {
        if ($context->manifest === null) {
            return null;
        }

        $strings  = self::strings();
        $preamble = $strings->warning($strings->get('warn.orphan.partialProject')) . PHP_EOL;
        $closing  = PHP_EOL . $strings->get('explain.orphan.partialProject');

        // Two distinct shapes, and the second is the one a modular monolith hits.
        // `manifestApplies` is false only when EVERY root sits below every
        // autoload root; it is true as soon as ONE is covered, which is the right
        // question for "does this scan belong to the project" and the wrong one
        // for "can this scan see every caller".
        if (!$context->manifestApplies) {
            return $preamble
                . $strings->get('warn.orphan.belowRoots', ['manifest' => $context->manifest->file])
                . $closing . PHP_EOL
                . $strings->get('advise.orphan.scanProjectRoot');
        }

        if ($context->uncoveredRoots === []) {
            return null;
        }

        return $preamble
            . $strings->get('warn.orphan.uncoveredRoots', [
                'count'    => count($context->uncoveredRoots),
                'manifest' => $context->manifest->file,
            ]) . PHP_EOL
            . '    ' . implode(PHP_EOL . '    ', $context->uncoveredRoots)
            . $closing;
    }

    /**
     * State the scope of the run: which root, how many files, how many patterns
     * pruned the walk, and how much of the tree could not be read.
     *
     * Silence about scope is what let a scan of a generated cache directory
     * inflate a run 52x and still report a green gate. A file count wildly out of
     * step with the project is obvious at a glance — but only if it is printed,
     * and only if the root it counted is printed beside it.
     */
    public static function render(
        Settings $settings,
        int $files,
        int $skippedDirectories,
        int $skippedGenerated = 0,
    ): string {
        $patterns = count($settings->exclude)
            + ($settings->defaultExcludes ? FileFinder::defaultExcludeCount() : 0);

        $roots = [];

        foreach ($settings->directories as $directory) {
            $resolved = realpath($directory);
            $roots[]  = $resolved === false ? $directory . ' (missing)' : $resolved;
        }

        $strings = self::strings();
        $parts = [
            $strings->get('report.scan.count.file', ['count' => $files]),
            $strings->get('report.scan.count.directory', ['count' => count($settings->directories)]),
            $strings->get('report.scan.count.pattern', ['count' => $patterns]),
        ];

        if ($skippedDirectories > 0) {
            $parts[] = $strings->get('report.scan.count.unreadable', ['count' => $skippedDirectories]);
        }

        if ($skippedGenerated > 0) {
            $parts[] = $strings->get('report.scan.count.generated', ['count' => $skippedGenerated]);
        }

        $counts = implode(', ', $parts);

        // One root reads best inline; several read best as a list, because the
        // point of printing them is that a wrong one is spotted at a glance.
        $heading = count($roots) === 1
            ? $strings->get('report.scan.root', ['root' => $roots[0]])
            : $strings->get('report.scan.roots') . PHP_EOL . '  ' . implode(PHP_EOL . '  ', $roots);

        return $heading . PHP_EOL
            . $strings->get('report.scan.counts', ['counts' => $counts]) . PHP_EOL . PHP_EOL;
    }

    /**
     * The nearest directory at or above the working directory holding a project
     * marker, or null when there is none.
     *
     * The search starts at the working directory rather than at the scan root on
     * purpose: "above the project" means above the project the user is standing
     * in, and a scan root that has already escaped upward carries no trace of
     * where it escaped from.
     */
    private static function nearestProjectRoot(): ?string
    {
        $directory = getcwd();

        if ($directory === false) {
            return null;
        }

        while (true) {
            foreach (self::PROJECT_MARKERS as $marker) {
                if (is_file($directory . '/' . $marker)) {
                    return $directory;
                }
            }

            $parent = dirname($directory);

            if ($parent === $directory) {
                return null;
            }

            $directory = $parent;
        }
    }

    /** Is $ancestor strictly above $path — the same directory does not count? */
    private static function isStrictAncestor(string $ancestor, string $path): bool
    {
        return $path !== $ancestor && str_starts_with($path . '/', $ancestor . '/');
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
