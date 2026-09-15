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

namespace LucianoPereira\PhpcpdNext\Triage;

use function array_keys;
use function in_array;
use function ksort;
use function realpath;
use function sort;

use LucianoPereira\PhpcpdNext\Orphan\ComposerManifest;
use LucianoPereira\PhpcpdNext\Orphan\Symbol;

/**
 * The shadowed-duplicate rung of the corpus definition: a file whose every
 * declared symbol is also declared by another file that the autoloader — and not
 * this one — actually maps.
 *
 * Such a file is not program text. Nothing can load it, nothing can call it, and
 * measuring duplication between it and the copy that *is* wired measures a
 * project's housekeeping rather than its code. It is also invisible to every
 * other rung: an abandoned copy declares real classes, is referenced by name from
 * the live call sites of its twin, and sits under a directory name no exclude
 * list can anticipate. Measured on the private corpus, the rungs that precede
 * this one (default excludes, preset excludes, entire-file orphans) removed none
 * of the candidates it finds.
 *
 * **The discriminator is the autoloader, never a path.** PSR-4 and PSR-0 map a
 * name to one path per rule, so of two files declaring `Acme\Ledger` at most one
 * is reachable through the map the project itself publishes. That is a structural
 * fact about wiring, checkable by a reader against composer.json, and it is the
 * standard ruling K sets for every rung: "backup", "old", "copy" and every other
 * name a person might use are not evidence, and are not consulted here.
 *
 * The bias is toward keeping, as everywhere in triage — a wrong discard costs a
 * real clone, a wrong keep costs one noisy finding. So a file is kept whenever
 * the question cannot be answered: no manifest, a namespace the project does not
 * map, both copies mapped (a prefix with two directories), or a single symbol of
 * its own that no other file declares. A file that declares nothing at all — a
 * script, a bootstrap, a config array — is kept for the same reason and by
 * construction: "every symbol it declares is declared elsewhere" is vacuously
 * true of a file with no symbols, so the walk below is over declaring files only.
 */
final class ShadowedDuplicates
{
    /**
     * @param list<Symbol>      $definitions the symbol table for the scanned set
     * @param ?ComposerManifest $manifest    what the project says it autoloads;
     *                                       null (no composer.json) means the
     *                                       question cannot be asked and nothing
     *                                       is shadowed
     * @return list<ShadowedFile> sorted by file, so two runs report one order
     */
    public function detect(array $definitions, ?ComposerManifest $manifest): array
    {
        if ($manifest === null) {
            return [];
        }

        /** @var array<string, list<string>> $declares fqn per file */
        $declares = [];
        /** @var array<string, list<string>> $declaredBy files per fqn */
        $declaredBy = [];
        /** @var array<string, string> $canonical scan path => resolved path */
        $canonical = [];

        foreach ($definitions as $symbol) {
            $canonical[$symbol->file] ??= self::canonical($symbol->file);

            if (!in_array($symbol->fqn, $declares[$symbol->file] ?? [], true)) {
                $declares[$symbol->file][] = $symbol->fqn;
            }

            if (!in_array($symbol->file, $declaredBy[$symbol->fqn] ?? [], true)) {
                $declaredBy[$symbol->fqn][] = $symbol->file;
            }
        }

        ksort($declares);

        $shadowed = [];

        foreach ($declares as $file => $symbols) {
            $wiredElsewhere = $this->wiredElsewhere($file, $symbols, $declaredBy, $canonical, $manifest);

            if ($wiredElsewhere !== null) {
                sort($symbols);
                $shadowed[] = new ShadowedFile($file, $symbols, $wiredElsewhere);
            }
        }

        return $shadowed;
    }

    /**
     * The wired files $file's declarations are loaded from instead, or null when
     * $file is not shadowed — because one of its symbols is unique to it, because
     * the autoloader maps a symbol here as well, or because no other file holding
     * the symbol is mapped either.
     *
     * Every symbol must fail the same way. A file that declares one shadowed class
     * and one class of its own is a file with code in it, and this rung says
     * nothing about it; that is the whole force of the ruling's word "all".
     *
     * @param list<string>                $symbols
     * @param array<string, list<string>> $declaredBy
     * @param array<string, string>       $canonical
     * @return ?list<string>
     */
    private function wiredElsewhere(
        string $file,
        array $symbols,
        array $declaredBy,
        array $canonical,
        ComposerManifest $manifest,
    ): ?array {
        $wired = [];

        foreach ($symbols as $fqn) {
            $mapped = $manifest->pathsFor($fqn);

            if ($mapped === []) {
                return null;
            }

            $resolved = [];

            foreach ($mapped as $path) {
                $resolved[] = self::canonical($path);
            }

            // Mapped here too: the autoloader can reach this copy, so the premise
            // "only one of the two" fails and the file is kept.
            if (in_array($canonical[$file] ?? $file, $resolved, true)) {
                return null;
            }

            $holders = [];

            foreach ($declaredBy[$fqn] ?? [] as $other) {
                if ($other !== $file && in_array($canonical[$other] ?? $other, $resolved, true)) {
                    $holders[$other] = true;
                }
            }

            if ($holders === []) {
                return null;
            }

            foreach (array_keys($holders) as $holder) {
                $wired[$holder] = true;
            }
        }

        $files = array_keys($wired);
        sort($files);

        return $files;
    }

    /**
     * A path both sides of a comparison can be trusted to spell the same way: the
     * symbol table carries paths as the walk produced them (often relative), the
     * autoload map builds them from composer.json's own directory. `realpath()`
     * settles `./`, `..` and symlinks; a path it cannot resolve is compared as
     * written, which is the conservative answer — an unresolvable path matches
     * nothing and so shadows nothing.
     */
    private static function canonical(string $path): string
    {
        $resolved = realpath($path);

        return $resolved === false ? $path : $resolved;
    }
}
