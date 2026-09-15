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

/*
 * English, and the fallback every other locale falls back to.
 *
 * Keys, placeholders, counts, and what belongs in here at all: see
 * docs/localization.md. Translating means copying this file and replacing the
 * leaves — nothing below needs to be read first.
 */

return [
    // Each slot holds a whole message, never a word.
    'frame' => [
        'error'   => 'ERROR: :message',
        'warning' => 'WARNING: :message',
        'hint'    => ":message\n:hint",
        'inFile'  => 'In :file: :message',
    ],

    // Result first, subject second, so every way of saying one thing is adjacent.
    'refuse' => [
        'notFound' => [
            'config' => 'Config file not found: :path',
        ],
        'unparsable' => [
            'config' => 'Config file could not be parsed: :path',
        ],
        'unknown' => [
            'option'  => 'Unknown option :flag.',
            'setting' => 'Unknown setting ":name" in :file',
        ],
        'needsValue' => [
            'option'  => 'Option :flag needs a value.',
            'setting' => 'Setting ":name" in :file needs a value.',
        ],
        'takesNoValue' => [
            'option' => 'Option :flag does not take a value.',
        ],
        'invalidValue' => [
            'option' => 'Invalid value ":value" for :flag (allowed: :allowed).',
        ],
        'belowFloor' => [
            'minTokens' => 'The unified engine needs :flag of at least :floor (given: :given). Below that the winnow window drops under 4 and the index stops being a sample, so the engine refuses rather than quietly degrading to an exhaustive scan.',
        ],
        // Spelled correctly, but wired to nothing: a defect, not a typo.
        'unwired' => [
            'option' => 'Option :flag has no settings binding.',
        ],
        'outOfScope' => [
            'filesystemRoot' => 'Refusing to scan the filesystem root (:path). Did you mean "./"?',
            'aboveProject'   => 'Refusing to scan :path: it is above the project root :project.',
        ],
        // An empty set, not a named thing absent — hence not "not found".
        'nothingToScan' => [
            'files'       => 'No files found to scan.',
            'afterTriage' => 'No files left to scan after triage.',
        ],
        'missingArgument' => [
            'directory' => 'No directory specified.',
        ],
        'writeFailed' => [
            'report' => 'Could not write the report to :path:detail',
        ],
        'writePartial' => [
            'report' => 'Incomplete write to :path: bytes (:written) of (:total).',
        ],
    ],

    // The run continued, but something about it is not what the reader
    // probably wants.
    'warn' => [
        'preset' => [
            'missingPaths' => 'preset ":preset" declares scan paths (:declared), missing (:missing): :paths.',
        ],
        'orphan' => [
            'partialProject' => '--orphans cannot see the whole project.',
            'belowRoots'     => '  Every scan root is below the autoload roots of :manifest.',
            'uncoveredRoots' => '  autoload paths declared in :manifest but never opened (:count):',
        ],
    ],

    // Something the tool did that the reader did not ask for and should know
    // about — a preset applied, a flag ignored, a cache used.
    'notice' => [
        'preset' => [
            'detected' => ':preset detected — preset applied (--no-preset to disable)',
        ],
        'cache' => [
            'hit' => '(cache hit)',
        ],
        'engine' => [
            'tokenbagDeprecated' => '(--algorithm=tokenbag is deprecated; use --algorithm=unified — which does not yet report every location the token bag does, so this stays selectable)',
        ],
        'incremental' => [
            'combined'    => '(--incremental ignored in combined mode)',
            'unsupported' => '(--incremental ignored: only the rabin-karp and unified algorithms have an incremental index)',
            'index'       => '(incremental index: :reused reused, :scanned scanned)',
        ],
    ],

    // The findings themselves, and the frame around them.
    'report' => [
        'clones' => [
            'none'           => 'No code clones found.',
            'heading'        => 'Found clones (:clones):gapped:reordered, duplicated lines (:lines), files (:files):',
            'gapped'         => ', inconsistent (:count)',
            'reorderedCount' => ', reordered (:count)',
            'unreadable'     => 'unreadable files (:count) — in neither total:',
            'strataAsserted' => ':asserted asserted, 0 demoted.',
            'settled'        => 'readings dropped as already described (:count).',
            // A different removal from the one above, and a stronger one: a
            // settled reading described real duplication another finding
            // described better, an unfounded one described duplication that was
            // not there.
            'unfounded'      => 'findings dropped as unverified (:count).',
            'hiddenLine'     => ':count of :total findings hidden below confidence :threshold — --hidden lists them.',
            'hiddenHeading'  => 'Hidden below confidence :threshold (:count):',
            'strataSplit'    => ':asserted asserted, :demoted demoted (:detail).',
            'coverage'       => ':percentage of scanned lines (:lines) are duplicated code.',
            'literals'       => '[literals differ (:count)]',
            // Two different claims, and the reader acts on them differently.
            // `inconsistent` says the copies diverge — one patched, its sibling
            // not. `reordered` says the material is all present in a different
            // sequence, which is what an order-free engine establishes and the
            // only thing it can establish.
            'reordered'      => '[reordered]',
            'functions'      => 'in :names',
            'sizes'          => 'Clone lines: average (:average), largest (:largest).',
            // The term names inside `:terms` are the model's own coefficients,
            // not prose, and stay in English wherever this is read.
            'confidence'     => 'confidence :score (:terms)',
        ],
        'ledger' => [
            'line'      => 'Acknowledged by the ledger: demoted (:acknowledged) of findings (:total), stale (:stale).',
                        'staleNote' => 'stale (the code it acknowledged has changed): :note',
            'wrote'     => 'acknowledgments written (:count): :path',
        ],
        'config' => [
            'missingPath' => ':path (missing)',
            'source' => [
                'default'     => 'default',
                'commandLine' => 'command line',
                'builtIn'     => '    built-in defaults',
            ],
            'fallback'    => ' falling back to the built-in default',
            'layers'      => '  Layers, lowest precedence first:',
        ],
        'run' => [
            'usage'  => 'Time: :duration, Memory: :memory MB',
            'banner' => 'phpcpd :version by :author — after :origin by :originAuthor.',
            // Appended to the line above when a scan actually read files.
            'throughput' => ' — files (:count) at :rate/s',
            'files'      => ' — files (:count)',
        ],
        'scan' => [
            'root'               => 'Scan root: :root',
            'roots'              => 'Scan roots:',
            // Label first, count in brackets: no plural rule to get wrong.
            'count' => [
                'file'       => 'files (:count)',
                'directory'  => 'roots (:count)',
                'pattern'    => 'excludes (:count)',
                'unreadable' => 'unreadable (:count)',
                'generated'  => 'generated (:count)',
            ],
            'counts'             => 'Scanned :counts',
        ],
        'triage' => [
            'nothing'  => 'Triage: nothing labelled; every file is program text.',
            'removed'  => 'Triage: files (:total), removed (:removed)',
            'labelled' => 'Triage: files (:total), labelled (:labelled), none removed',
        ],
        'orphan' => [
            'none'         => 'No orphaned symbols found — symbols (:symbols), files (:files).',
            'found'        => 'orphaned symbols (:count):',
            'possible'     => 'possible orphans (:count) — review before removing:',
            'advisory'     => 'orphaned symbols (:count) — advisory, does not affect exit code:',
            'notShown'     => 'further orphan findings not shown (:count) — run --orphans to review.',
            'suppressed'   => 'Suppressed (:count): :census',
            'explainHint'  => '  → --explain to list them',
            'wholeFile'    => '    ⤷ whole file is unwired — no symbol declared here is referenced',
            'supersededBy' => '    ⤷ looks like a superseded copy of :name',
            'summary'      => 'Scanned symbols (:symbols), files (:files); orphaned (:orphaned), possible (:possible), suppressed (:suppressed), planned (:planned).',
        ],
    ],

    // Why the tool decided what it decided; shown behind `--explain`.
    'explain' => [
        'triage' => [
            'generatedTree' => 'outside the file set this project\'s own default excludes leave — a generated tree, which may not witness wiring',
            'declaredHere'   => 'symbols declared here (:count), none referenced anywhere in the project',
            'foreignNs'     => 'declares :namespaces — a namespace no composer.json above it declares, outside every directory they wire',
        ],
        'role' => [
            'noStatements' => 'no top-level statements past the preamble',
            'coupled'      => ':registrations of :statements top-level statements are registration expressions, but they share a variable',
            'independent'  => ':registrations of :statements top-level statements are dataflow-independent registration expressions',
        ],
        'orphan' => [
            'guard'          => 'declared inside an existence guard — polyfill or compatibility shim',
            'entrypoint'     => 'declared in :namespace — invoked by framework convention',
            'partialProject' => '  A symbol is called dead when *nothing* references it, which is a claim about the
  whole project. Code outside this scan can still reference what is reported here.',
            'evidence' => [
                'nameAt'   => 'name appears at',
                'loopAt'   => 'discovered by the loop at',
                'suffixAt' => 'suffix declared at',
                'namedIn'  => 'named in',
            ],
            'plannedServed'  => 'referenced now — @phpcpd-planned has served its purpose and can be removed',
            'manifest'       => 'declared in a composer autoload.files entry point',
            'foreignNs'      => 'declared outside the project\'s own namespaces (compatibility shim)',
            'fixture'        => 'test fixture — loaded by path or named as a string, never referenced',
            'discovery'      => 'discovered by a directory scan — instantiated from its filename behind class_exists',
            'convention'     => 'companion class — :base uses :trait, which resolves this name by suffix at runtime',
            'interface'      => 'never referenced (interface — may be implemented outside the scanned set)',
            'trait'          => 'never referenced (trait — may be used by classes outside the scanned set)',
            'abstract'       => 'never referenced (abstract — may be extended outside the scanned set)',
            'inString'       => 'never referenced in code; name appears in a string literal (possible dynamic use)',
        ],
    ],

    // Headings for the suppression census: noun phrases, where the matching
    // `explain.orphan.*` is the verb phrase for one symbol.
    'label' => [
        'orphan' => [
            'conditional' => 'Conditionally declared (polyfill / compatibility shim)',
            'fixtures'    => 'Test fixtures (loaded by path or by name)',
            'config'      => 'Registered in a config file',
            'template'    => 'Referenced from a template (blade / twig / latte)',
            'manifest'    => 'Referenced from composer.json',
            'namespace'   => 'Declared outside the project\'s own namespaces (compatibility shim)',
            'keep'        => 'Marked as kept (@api / @phpcpd-keep)',
            'entrypoint'  => 'Framework entry points (attribute / test class)',
            'discovery'   => 'Discovered by a directory scan (instantiated from its filename)',
            'convention'  => 'Companion class named by convention (suffix declared by a trait)',
            'planned'     => 'Planned, not yet wired',
            'none'        => 'No reference found',
        ],
    ],

    // What to do next, kept apart from the refusal it follows so a
    // translation can put the remedy where its grammar wants it.
    'advise' => [
        'scan' => [
            'allowRoot'    => 'Pass --allow-root-scan if you really meant the whole filesystem.',
            'allowOutside' => 'Pass --allow-root-scan to scan outside the project anyway.',
        ],
        'orphan' => [
            'scanProjectRoot' => '  Scan the project root for a result worth acting on.',
        ],
        'triage' => [
            'posture' => '  (--triage-posture=discard acts on the labels; --no-triage skips the stage)',
            'explain' => '  (--explain lists each file and the evidence for or against it)',
        ],
        'clone' => [
            'gapped'  => 'Near-miss clone — consider parameterizing the diverging part or aligning both copies.',
            'demoted' => 'Demoted as :stratum — this is the shape that stratum describes, so extract it only if the repetition is not the point.',
            'extract' => 'Consider extracting the shared lines into a reusable method, class, or trait.',
        ],
    ],

    // The `--help` screen.
    'help' => [
        'frame' => [
            'usage'      => 'Usage:',
            'invocation' => '  phpcpd [options] <directory>',
        ],
        'group' => [
            'selecting' => 'Options for selecting files',
            'orphans'   => 'Orphan detection (dead code)',
            'analysing' => 'Options for analysing files',
            'general'   => 'General options',
            'reporting' => 'Options for report generation',
            'ci'        => 'Options for CI integration',
        ],
        'option' => [
            'suffix'              => 'Include files with names ending in <suffix> (default: :default; repeatable)',
            'exclude'             => 'Exclude files with <path> in their path (repeatable)',
            'preset'              => 'Apply a framework preset (e.g. laravel): sets sensible paths, suffixes, and excludes',
            'triage'              => 'Run Stage 0 corpus triage before detection (on by default; this asks for it explicitly)',
            'no_triage'           => 'Skip Stage 0 entirely: no file is labelled unwired, shadowed, vendored or derived',
            'triage_posture'      => 'What triage does with a file it labels: discard it from the scan (default), or label it and nothing else',
            'no_preset'           => 'Do not auto-apply a framework preset when one is detected (detection announces itself; --preset= overrides it)',
            'no_default_excludes' => 'Scan generated and cache trees too (vendor, node_modules, .phpstan.cache, build, ...), which are skipped by default',
            'allow_root_scan'     => 'Permit a scan root of / or a root above the nearest composer.json (refused by default: `phpcpd /` is almost always a typo for `phpcpd ./`)',
            'orphans'             => 'Detect orphaned symbols (unreferenced classes, interfaces, traits, enums, functions) instead of clones',
            'no_suppress'         => 'Turn off suppression rules by name, comma-separated, or "all" (:rules)',
            'fail_on'             => 'Result tiers that make the run exit non-zero, comma-separated (default: :default)',
            'explain'             => 'List every suppressed symbol and the rule that suppressed it, instead of only counting them',
            'rk'                  => 'Rabin-Karp only (exact/Type-1 clones; faster, no reorder detection). Default runs both Rabin-Karp and TokenBag.',
            'min_lines'           => 'Minimum number of identical lines (default: :default)',
            'min_tokens'          => 'Minimum number of identical tokens (default: :default)',
            'language'            => 'Language for the report (default: :default)',
            'verbose'             => 'Print the duplicated code for each clone',
            'algorithm'           => 'Single algorithm override (rabin-karp | tokenbag | unified)',
            'raw'                 => 'Match raw text: identifiers must agree too (turns off the default normalization)',
            'fuzzy'               => 'Name-blind normalization: like the default but without the type anchor (research; E2 measured it dominated)',
            'type_anchored'       => 'Keep type keywords concrete under normalization (on by default; --fuzzy turns it off)',
            'min_similarity'      => 'TokenBag overlap threshold (default: :default)',
            'min_confidence'      => 'List only findings the model scores at or above <log-odds>; the rest are counted and readable with --hidden, never dropped, and still gate --fail-on',
            'hidden'              => 'List the findings --min-confidence held back',
            'acknowledged'        => 'Read a committed acknowledgment ledger from <file>: listed duplication is demoted, never hidden, and entries whose code changed expire and are reported',
            'write_acknowledged'  => 'Write this run\'s findings to <file> as an acknowledgment ledger, for review and commit',
            'log_pmd'             => 'Write log in PMD-CPD XML format to <file>',
            'log_json'            => 'Write log in JSON format to <file>',
            'log_sarif'           => 'Write log in SARIF 2.1.0 format to <file> (for GitHub Code Scanning)',
            'cache'               => 'Cache results in \'.phpcpd-cache/\' — a hit needs every file unchanged, so it serves a re-run of one commit rather than the next commit',
            'cache_dir'           => 'Read/write cache from <path> (implies --cache; overrides default directory)',
            'incremental'         => 'Per-file incremental index: re-tokenize only changed files (rabin-karp or unified, not the combined default; uses the cache directory)',
            'config'              => 'Read settings from <file> (default: ./phpcpd.ini when present); keys are the long option names',
            'show_config'         => 'Print the settings in force, where each came from, and exit',
            'no_config'           => 'Ignore ./phpcpd.ini',
            'help'                => 'Print this help',
            'version'             => 'Print version information',
        ],
    ],

    // Written into a file rather than printed.
    'document' => [
        'ledger' => [
            'title' => 'phpcpd-next acknowledgment ledger',
            'what'  => 'Each line records one duplication this project has looked at and decided to
live with. An acknowledged finding is DEMOTED, never hidden: it is still
reported, still counted, and still gates the exit code.',
            'key'   => 'The key is the content of every side of the duplication, hashed — not a path
and not a line number. So editing either copy expires the entry and the
finding is asserted again, while moving the code changes nothing. An entry
that no longer matches anything is reported as stale so it can be deleted.',
            'note'  => 'The text after the tab is a human note. It is never matched on.',
        ],
    ],
];
