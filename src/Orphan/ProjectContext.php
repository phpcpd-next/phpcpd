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

use function array_keys;
use function dirname;
use function file_get_contents;
use function fnmatch;
use function realpath;
use function str_contains;
use function str_starts_with;
use function strpbrk;

use LucianoPereira\PhpcpdNext\Util\FileFinder;

/**
 * What the project says about itself, from the files a `.php` scan never opens:
 * composer.json and the config formats that wire classes by name.
 *
 * Project-level rules stand down when the scan is pointed BELOW the directories
 * the manifest maps. Aiming a scan at one subtree is a statement that the subtree
 * is the world for this run — its files are ordinary code, not "code sitting in
 * someone else's project" — so a namespace or fixture rule derived from the
 * enclosing project would be judging files by a structure the user opted out of.
 * The same principle governs the fixture-path rule in {@see OrphanDetector}.
 *
 * The config and template maps are swept lazily — see {@see NameSweep} for why,
 * and for why this class is no longer a `readonly` class (a hooked property
 * cannot be readonly; every stored property still is).
 */
final class ProjectContext
{
    /** @var list<string> */
    private const array CONFIG_SUFFIXES = ['.neon', '.yaml', '.yml', '.xml', '.dist'];

    /**
     * The directory name whose `.php` files are read as configuration.
     *
     * Laravel's native config format is PHP: `config/*.php` returns an array of
     * `Provider::class` entries and fully-qualified name strings, which is where
     * a Laravel project's wiring actually lives. With `.php` absent from the
     * suffix list above, none of it was visible — measured on the audited
     * monolith, the config rule suppressed exactly ONE symbol repo-wide, while
     * the classes it should have accounted for sat in `config/*.php` arrays.
     *
     * **Boundary — only `config/` directories.** Arbitrary `.php` files are not
     * swept as configuration, and must not be: every `.php` file in the project
     * is already read as *source*, where a class name is a reference or a string
     * mention and is scored as such. Reading them a second time as configuration
     * would promote every string literal anywhere to a config registration, which
     * is a suppression rule that fires on everything. A `config` path segment is
     * a structural statement about the file's role, which is the standard every
     * other rule here meets.
     */
    private const string CONFIG_DIRECTORY = '/config/';

    /**
     * Template languages read for symbol mentions.
     *
     * Excluding Blade from *clone* detection is right — templates are repetitive
     * by nature and would flood the report. Excluding it from *reference*
     * detection is not: in a Laravel application a view is a primary call site,
     * so a class called only from a template was reported dead while the file
     * holding the reference was never opened. The two file sets are therefore
     * decoupled: clone detection keeps its excludes, and reference scanning
     * additionally reads these.
     *
     * @var list<string>
     */
    private const array TEMPLATE_SUFFIXES = ['.blade.php', '.twig', '.latte', '.tpl'];

    private readonly NameSweep $configSweep;

    private readonly NameSweep $templateSweep;

    /**
     * name => "file:line", from neon/yaml/xml. Read straight through to the sweep,
     * so the files behind it are opened on the first read and never again.
     *
     * @var array<string, string>
     */
    public array $configNames {
        get => $this->configSweep->names();
    }

    /**
     * name => "file:line", from templates. Lazy on the same terms as
     * {@see self::$configNames}.
     *
     * @var array<string, string>
     */
    public array $templateNames {
        get => $this->templateSweep->names();
    }

    /**
     * `$configNames` and `$templateNames` accept either a map — the eager form,
     * kept so a caller that already has the names can hand them over — or a
     * {@see NameSweep} that will produce one on first read.
     *
     * @param array<string, string>            $manifestNames   name => location, from composer.json
     * @param array<string, string>|NameSweep  $configNames     name => "file:line", from neon/yaml/xml
     * @param array<string, true>              $entryPointFiles files whose declarations are entry points
     * @param array<string, string>|NameSweep  $templateNames   name => "file:line", from templates
     * @param list<string>                     $uncoveredRoots  autoload directories this scan never opened
     */
    public function __construct(
        public readonly ?ComposerManifest $manifest = null,
        public readonly bool $manifestApplies = false,
        public readonly array $manifestNames = [],
        array|NameSweep $configNames = [],
        public readonly array $entryPointFiles = [],
        array|NameSweep $templateNames = [],
        public readonly array $uncoveredRoots = [],
    ) {
        $this->configSweep   = $configNames instanceof NameSweep ? $configNames : NameSweep::resolved($configNames);
        $this->templateSweep = $templateNames instanceof NameSweep ? $templateNames : NameSweep::resolved($templateNames);
    }

    /**
     * @param list<string> $roots
     * @param list<string> $excludes
     */
    public static function discover(array $roots, array $excludes, OrphanConfiguration $config): self
    {
        $manifest = ComposerManifest::locate($roots);
        $applies  = $manifest !== null && self::coversProject($roots, $manifest);

        $manifestNames = [];
        $entries       = [];
        $configRoots   = $roots;

        if ($manifest !== null && $applies) {
            $configRoots = [dirname($manifest->file)];

            if ($config->ruleEnabled(Rule::MANIFEST)) {
                $manifestNames = $manifest->references;
                $entries       = $manifest->entryPointFiles;
            }
        }

        // Both sweeps are described here and run later — or never. The roots and
        // excludes are fixed now, so a deferred sweep reads exactly the tree this
        // call resolved, and the answer cannot depend on when it is asked for.
        $configNames = $config->ruleEnabled(Rule::CONFIG)
            ? new NameSweep(static fn(): array => self::scanConfigNames($configRoots, $excludes))
            : NameSweep::resolved([]);

        $templateNames = $config->ruleEnabled(Rule::TEMPLATE)
            ? new NameSweep(static fn(): array => self::scanNames(
                $configRoots,
                self::withoutTemplateExcludes($excludes),
                self::TEMPLATE_SUFFIXES,
            ))
            : NameSweep::resolved([]);

        return new self(
            $manifest,
            $applies,
            $manifestNames,
            $configNames,
            $entries,
            $templateNames,
            $manifest === null ? [] : self::uncoveredRoots($roots, $manifest),
        );
    }

    /**
     * Autoload directories that exist on disk but sit outside every scan root.
     *
     * A modular monolith is the case this exists for. Pointing a scan at
     * `packages/` covers 60-odd psr-4 prefixes and looks complete — but this
     * project also maps `App\ => app/` and `Database\Seeders\ => database/seeders/`,
     * and the code in them is exactly the top-level wiring that references what
     * the packages declare. Measured here: `packages/` alone reported 163 orphans
     * where the full set of autoload roots reported 158.
     *
     * `manifestApplies` cannot see this: it is true as soon as *one* root is
     * covered, which is the right question for "does this scan belong to the
     * project" and the wrong one for "can this scan see every caller".
     *
     * @param list<string> $roots
     * @return list<string>
     */
    private static function uncoveredRoots(array $roots, ComposerManifest $manifest): array
    {
        $uncovered = [];

        foreach ($manifest->mappedDirectories() as $mapped) {
            $target = realpath($mapped);

            if ($target === false) {
                continue;
            }

            foreach ($roots as $root) {
                $resolved = realpath($root);

                if ($resolved !== false && self::isAncestorOrSelf($resolved, $target)) {
                    continue 2;
                }
            }

            $uncovered[$target] = true;
        }

        return array_keys($uncovered);
    }

    /**
     * Is $file one of the manifest's `autoload.files`? Its top-level declarations
     * are loaded for side effects into every consuming project, so they are entry
     * points for code this scan cannot see.
     */
    public function isEntryPointFile(string $file): bool
    {
        $resolved = realpath($file);

        return $resolved !== false && isset($this->entryPointFiles[$resolved]);
    }

    /** Does the project declare ownership of $namespace? */
    public function ownsNamespace(string $namespace): bool
    {
        return !$this->manifestApplies
            || $this->manifest === null
            || $this->manifest->owns($namespace);
    }

    /**
     * Is at least one scan root at or above a directory the manifest maps?
     *
     * @param list<string> $roots
     */
    private static function coversProject(array $roots, ComposerManifest $manifest): bool
    {
        foreach ($roots as $root) {
            $resolved = realpath($root);

            if ($resolved === false) {
                continue;
            }

            foreach ($manifest->mappedDirectories() as $mapped) {
                $target = realpath($mapped);

                if ($target !== false && self::isAncestorOrSelf($resolved, $target)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function isAncestorOrSelf(string $ancestor, string $path): bool
    {
        return $path === $ancestor || str_starts_with($path . '/', $ancestor . '/');
    }

    /**
     * Every name a config file wires, from the declarative formats AND from
     * Laravel-style `config/*.php` arrays.
     *
     * The two passes are ordered, not merged, so the first location recorded for
     * a name is stable: {@see FqnScanner} keeps the first sighting, and a
     * declarative file is the more precise citation when both name the same
     * class. Within each pass the file list is sorted, so two runs cite the same
     * `file:line`.
     *
     * `.php` cannot simply join {@see self::CONFIG_SUFFIXES}: that would sweep
     * the whole source tree as configuration. It is a second, path-restricted
     * pass instead — see {@see self::CONFIG_DIRECTORY} for why the restriction is
     * the rule rather than an optimisation. The cost of the extra walk is why
     * this sits behind {@see NameSweep} and runs only when a symbol actually
     * reaches the config rule.
     *
     * @param list<string> $roots
     * @param list<string> $excludes
     * @return array<string, string>
     */
    private static function scanConfigNames(array $roots, array $excludes): array
    {
        $names = self::scanNames($roots, $excludes, self::CONFIG_SUFFIXES);

        foreach ((new FileFinder())->find($roots, ['.php'], $excludes) as $file) {
            if (str_contains($file, self::CONFIG_DIRECTORY)) {
                self::collectNames($file, $names);
            }
        }

        return $names;
    }

    /**
     * @param list<string> $roots
     * @param list<string> $excludes
     * @param list<string> $suffixes
     * @return array<string, string>
     */
    private static function scanNames(array $roots, array $excludes, array $suffixes): array
    {
        $names = [];

        foreach ((new FileFinder())->find($roots, $suffixes, $excludes) as $file) {
            self::collectNames($file, $names);
        }

        return $names;
    }

    /** @param array<string, string> $names */
    private static function collectNames(string $file, array &$names): void
    {
        $contents = file_get_contents($file);

        if ($contents !== false) {
            // Shape-based, so `Acme\Handlers\Prune::class` and the quoted FQN
            // `'Acme\Handlers\Prune'` are both picked up, with no need to know
            // which of the two a given config file happens to use.
            FqnScanner::collect($contents, $file, $names);
        }
    }

    /**
     * The exclude list with template-blinding patterns removed.
     *
     * `*.blade.php` sits in the Laravel preset's excludes, and belongs there — it
     * keeps repetitive markup out of clone detection. Applied to the reference
     * scan it does the opposite of its purpose: it hides the call sites, so a
     * class used only from a view is reported dead.
     *
     * A pattern is dropped only when it blinds templates *specifically* — it
     * matches a template filename but not a plain `.php` one. A general exclude
     * such as `vendor` or `demo` matches neither probe and survives; one such as
     * `php` matches both and survives too, since dropping it would widen the scan
     * beyond what the user asked for.
     *
     * @param list<string> $excludes
     * @return list<string>
     */
    private static function withoutTemplateExcludes(array $excludes): array
    {
        $kept = [];

        foreach ($excludes as $exclude) {
            if (!self::blindsTemplates($exclude)) {
                $kept[] = $exclude;
            }
        }

        return $kept;
    }

    private static function blindsTemplates(string $exclude): bool
    {
        if (self::matchesProbe($exclude, 'probe.php')) {
            return false;
        }

        foreach (self::TEMPLATE_SUFFIXES as $suffix) {
            if (self::matchesProbe($exclude, 'probe' . $suffix)) {
                return true;
            }
        }

        return false;
    }

    /** Would $exclude skip a file named $probe, by {@see FileFinder}'s own rules? */
    private static function matchesProbe(string $exclude, string $probe): bool
    {
        return strpbrk($exclude, '*?[') !== false
            ? fnmatch($exclude, $probe)
            : str_contains($probe, $exclude);
    }
}
