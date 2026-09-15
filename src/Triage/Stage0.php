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
use function count;
use function implode;
use function strrpos;
use function substr;

use LucianoPereira\PhpcpdNext\Orphan\ComposerManifest;
use LucianoPereira\PhpcpdNext\Orphan\OrphanConfiguration;
use LucianoPereira\PhpcpdNext\Orphan\OrphanDetector;
use LucianoPereira\PhpcpdNext\Orphan\ProjectContext;
use LucianoPereira\PhpcpdNext\Orphan\SymbolCollector;

/**
 * Stage 0 — corpus triage, ruling T. What is program text, decided once, before
 * any view is fingerprinted.
 *
 * The argument for the stage is a measurement, not a preference: analysing
 * unwired code measures a corpus's housekeeping rather than its duplication.
 * M3 found entire-file orphans at 3.9 % of files and 77 % of Rabin-Karp's
 * findings, and the rated pool's false positives concentrated in *files* rather
 * than in spans. Removing a file is therefore the cheapest correct fix
 * available, and the most dangerous one to get wrong — which is why every part
 * of this stage is biased toward keeping and none of it is silent.
 *
 * Four rungs, in order of how much each can prove — **and every one of them
 * proves**. The estimator that used to sit below them was retired at the 2.0.0
 * landing on its own pre-registered condition; see the note at the end.
 *
 *   0. **Derived** — not program text by the project's own default excludes:
 *      a generated tree, a compiled cache, a dependency. Supplied by the caller
 *      as the witness set rather than derived here, because the default excludes
 *      are a product convention the walker already owns and Stage 0 does not
 *      re-implement. See the ruling V note below.
 *   1. **Unwired** — nothing in the project references anything the file
 *      declares. Decided by the existing orphan machinery, with every
 *      suppression rule it already carries (framework attributes, config
 *      registrations, template references, `@api`), so a controller reached only
 *      from a route file is not called dead.
 *   2. **Shadowed** — every name it declares is autoloaded from somewhere else.
 *      {@see ShadowedDuplicates}, from the project's own manifest.
 *   3. **Foreign** — someone else's code, vendored into the tree: every namespace
 *      it declares is one the manifest does not own, and it sits outside every
 *      directory the manifest wires. This rung exists because the alternative was
 *      to ask the classifier, and the classifier cannot answer: a vendored tool
 *      tree is indistinguishable from program text by content *because it is
 *      program text*. Measured at M4 — trained on that class alone, the model
 *      separated one file in 117.
 *
 * **Never a path.** Ruling K's ban holds across every rung that *judges*: a
 * manifest, a symbol table and a token stream are evidence a reader can check,
 * and a directory called `backup` is not. The derived rung judges nothing — it
 * is the caller's own program-text walk, handed in.
 *
 * ## Ruling V — what this stage reads is not what it believes
 *
 * Stage 0 is pointed at the *raw* tree, because ruling T's acceptance is that it
 * reproduces the frozen definition's fourth rung from the first one, and it
 * cannot triage a cache it is not shown. But it must not take *evidence* from
 * files it is about to discard. It did: the reference graph was built over the
 * whole raw tree, so a file whose only referents lived in a compiled cache blob
 * was judged wired, and the unwired rung's count tracked whether the developer
 * happened to have a warm cache (156 → 157 → 156 across one deletion and one
 * regeneration) while the frozen rung did not move at all. Two runs over one
 * tree agreed; two runs over the same *project* in two cache states did not,
 * which is the property a user actually has.
 *
 * So `$witnesses` names the files that may witness wiring — the rung-2
 * survivors, the product's own default excludes applied. Everything handed in is
 * still triaged and still accounted for; only a survivor may be evidence that
 * something else is alive. A derived artifact is judged, and never a judge.
 *
 * ## The fifth rung is gone, and ruling T's lifecycle is what closed it
 *
 * A naive-Bayes fishiness estimator used to judge whatever the four proofs left.
 * It shipped under a pre-registered condition — decided at the next rating round
 * — and that round decided it: every demotion it got right was already produced
 * by the table stratum's proof test, and both demotions it alone produced were
 * findings both raters called genuine duplication. Ruling T's own lifecycle for
 * an estimator is scout, promote what it teaches into proofs, retire; the model,
 * its trainer, its margin and the `fishy` stratum were deleted at the 2.0.0
 * landing, and the labels it was trained on are preserved outside this
 * repository. Stage 0 is all proof.
 */
use LucianoPereira\PhpcpdNext\Strings\Catalogue;

final class Stage0
{
    public function __construct(
        private ShadowedDuplicates $shadowed = new ShadowedDuplicates(),
    ) {}

    /**
     * @param list<string>  $files
     * @param ?list<string> $witnesses ruling V: the files allowed to witness that
     *                                 another file is wired — the rung-2
     *                                 survivors. Null means every file handed in,
     *                                 which is the right answer for a caller
     *                                 whose walk already applied the excludes.
     */
    public function triage(
        array $files,
        ?ComposerManifest $manifest = null,
        ?ProjectContext $context = null,
        ?OrphanConfiguration $config = null,
        ?array $witnesses = null,
    ): TriageResult {
        $config = $config ?? new OrphanConfiguration();

        $discarded = [];
        $program   = $files;

        // Rung 0 — derived. Removed before any evidence is gathered, so that the
        // gathering below cannot see them at all: this is ruling V's mechanism,
        // not merely its outcome.
        if ($witnesses !== null) {
            $eligible = [];

            foreach ($witnesses as $witness) {
                $eligible[$witness] = true;
            }

            $program = [];

            foreach ($files as $file) {
                if (isset($eligible[$file])) {
                    $program[] = $file;

                    continue;
                }

                $discarded[] = new TriageDecision(
                    $file,
                    TriageDecision::DERIVED,
                    self::strings()->get('explain.triage.generatedTree'),
                );
            }
        }

        $collected = (new SymbolCollector())->collect($program, $config);

        $declared   = [];
        $namespaces = [];

        foreach ($collected->definitions as $symbol) {
            $declared[$symbol->file][] = $symbol->name;
            $split                     = strrpos($symbol->fqn, '\\');
            $namespaces[$symbol->file][$split === false ? '' : substr($symbol->fqn, 0, $split)] = true;
        }

        $dropped = [];

        // Rung 1 — unwired. A whole file whose every declaration is a finding.
        foreach ((new OrphanDetector())->detect($program, null, $config, $context)->all() as $orphan) {
            if ($orphan->entireFileOrphaned && !isset($dropped[$orphan->symbol->file])) {
                $dropped[$orphan->symbol->file] = true;
                $discarded[]                    = new TriageDecision(
                    $orphan->symbol->file,
                    TriageDecision::UNWIRED,
                    self::strings()->get('explain.triage.declaredHere', [
                        'count' => count($declared[$orphan->symbol->file] ?? [$orphan->symbol->name]),
                    ]),
                );
            }
        }

        // Rung 2 — shadowed. Declared here, autoloadable only from elsewhere.
        foreach ($this->shadowed->detect($collected->definitions, $manifest) as $shadow) {
            if (!isset($dropped[$shadow->file])) {
                $dropped[$shadow->file] = true;
                $discarded[]            = new TriageDecision($shadow->file, TriageDecision::SHADOWED, $shadow->reason());
            }
        }

        // Rung 3 — foreign. Someone else's code, vendored into the tree: no
        // manifest above the file claims it, by namespace or by wired directory.
        // Both halves are needed, and so is the whole chain. A namespace test
        // alone would condemn Laravel's seeders, which live in a classmap root
        // under a namespace no psr-4 prefix covers; a directory test alone would
        // condemn every script outside src/; and consulting only the root
        // manifest would condemn a monorepo package's own tests, which is what
        // the first run of this rung did.
        $chain = new ManifestChain($manifest === null ? [] : [$manifest]);

        foreach ($program as $file) {
            if (isset($dropped[$file])) {
                continue;
            }

            $declaredIn = array_keys($namespaces[$file] ?? []);

            if ($chain->foreign($file, $declaredIn)) {
                $dropped[$file] = true;
                $discarded[]    = new TriageDecision(
                    $file,
                    TriageDecision::FOREIGN,
                    self::strings()->get('explain.triage.foreignNs', ['namespaces' => implode(', ', $declaredIn)]),
                );
            }
        }

        // Everything the four proofs left is program text.
        $kept = [];

        foreach ($program as $file) {
            if (!isset($dropped[$file])) {
                $kept[] = $file;
            }
        }

        return new TriageResult($kept, $discarded);
    }

    /**
     * The catalogue, made once.
     *
     * A static holder because this is a static entry point in the scan path,
     * with no object to inject into.
     */
    /** Built fresh, not cached: the first caller runs before the language is known. */
    private static function strings(): Catalogue
    {
        return new Catalogue();
    }
}
