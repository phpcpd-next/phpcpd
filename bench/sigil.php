#!/usr/bin/env php
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
 * Sigil — the command. See bench/sigil-parse.php for the syntax and the reason
 * documents carry references instead of numbers.
 *
 * Usage:
 *   php bench/sigil.php [--check] [--audit] [--audit-all] [--version-literals]
 *                       [--facts=<path>] [<file.md> ...]
 *
 * Files default to every registered document. --check renders nothing and exits
 * non-zero if any document disagrees with the facts, naming the reference and
 * both values; that is the CI gate. Without it, the documents are rewritten in
 * place.
 *
 * Either mode also fails on a number a document states in its own voice that is
 * exactly a value in the fact table — it will rot, and it must be a reference.
 * --audit additionally reports unreferenced numbers, grouped by value and
 * ranked by how many places state them; --audit-all also lists the singletons.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/sigil-parse.php';

/** Documents under sigil control, relative to the repository root. */
const SIGIL_DOCUMENTS = [
    'README.md',
    'ROADMAP.md',
    'docs/orphans.md',
    'docs/internal/releasing.md',
    'docs/research/deferred-engine-work.md',
    'docs/research/interpretation.md',
    'docs/research/unified-engine-plan.md',
];

const SIGIL_DEFAULT_FACTS = 'bench/results/facts.toml';

exit(sigil_main(array_slice(bcb_argv(), 1)));

/**
 * @param list<string> $argv
 */
function sigil_main(array $argv): int
{
    $root      = dirname(__DIR__);
    $check     = false;
    $audit     = false;
    $auditAll  = false;
    $literals  = false;
    $factsPath = SIGIL_DEFAULT_FACTS;
    $documents = [];

    foreach ($argv as $arg) {
        if ($arg === '--check') {
            $check = true;
        } elseif ($arg === '--audit') {
            $audit = true;
        } elseif ($arg === '--version-literals') {
            $literals = true;
        } elseif ($arg === '--audit-all') {
            $audit    = true;
            $auditAll = true;
        } elseif (str_starts_with($arg, '--facts=')) {
            $factsPath = substr($arg, 8);
        } elseif (str_starts_with($arg, '-')) {
            fwrite(STDERR, sprintf("unknown option: %s\n", $arg));

            return 2;
        } else {
            $documents[] = $arg;
        }
    }

    if ($documents === []) {
        $documents = SIGIL_DOCUMENTS;
    }

    $absoluteFacts = str_starts_with($factsPath, '/') ? $factsPath : $root . '/' . $factsPath;

    if (!is_file($absoluteFacts)) {
        fwrite(STDERR, sprintf("no fact table at %s — run bench/collect-facts.php first\n", $factsPath));

        return 2;
    }

    $rawFacts = file_get_contents($absoluteFacts);

    if ($rawFacts === false) {
        fwrite(STDERR, sprintf("could not read %s\n", $factsPath));

        return 2;
    }

    try {
        $facts = sigil_parse_facts($rawFacts, $factsPath);
    } catch (SigilError $e) {
        fwrite(STDERR, $e->getMessage() . "\n");

        return 2;
    }

    $stale   = 0;
    $written = 0;
    $bound   = 0;

    if ($literals) {
        return sigil_version_literals($root, $facts);
    }

    foreach ($documents as $relative) {
        $path = str_starts_with($relative, '/') ? $relative : $root . '/' . $relative;

        if (!is_file($path)) {
            fwrite(STDERR, sprintf("no such document: %s\n", $relative));

            return 2;
        }

        $before = file_get_contents($path);

        if ($before === false) {
            fwrite(STDERR, sprintf("could not read %s\n", $relative));

            return 2;
        }

        try {
            $after = sigil_render($before, $facts, $root, $relative);
        } catch (SigilError $e) {
            fwrite(STDERR, $e->getMessage() . "\n");

            return 2;
        }

        $bare    = sigil_bare_numbers($after, $facts);
        $binding = array_values(array_filter($bare, static fn(array $b): bool => $b['severity'] === 'binding'));

        if ($binding !== []) {
            $bound += count($binding);
            printf("  BARE    %s\n", $relative);

            foreach ($binding as $b) {
                printf("            line %d: %s is %s — state it as a reference\n", $b['line'], $b['number'], $b['ref']);
            }
        }

        if ($audit) {
            $groups = sigil_group_loose($bare);
            $copies = array_values(array_filter($groups, static fn(array $g): bool => $g['count'] > 1));
            $once   = count($groups) - count($copies);

            printf(
                "  audit   %s — %d value(s) stated more than once, %d stated once\n",
                $relative,
                count($copies),
                $once,
            );

            /*
             * Copies first, and with the line each sits on. A value stated once
             * may be a year or a section number; a value stated four times is a
             * fact that has been duplicated, and whoever updates it next will
             * update one copy and leave the rest quietly wrong.
             */
            foreach ($copies as $group) {
                printf(
                    "            %s — %d places%s\n",
                    $group['number'],
                    $group['count'],
                    count($group['spellings']) > 1 ? sprintf(' (written %s)', implode(', ', $group['spellings'])) : '',
                );

                foreach ($group['lines'] as $i => $line) {
                    printf("              line %-4d %s\n", $line, sigil_excerpt($group['texts'][$i], $group['number']));
                }
            }

            /*
             * Restatements last, because they are the finding a reader cannot
             * get any other way: two spellings of one measurement at different
             * precisions, which never group and never compare equal.
             */
            $prose = array_map(static fn(array $g): string => $g['number'], $groups);

            /** @var array<string, array{line: int, text: string}> $byNumber */
            $byNumber = [];

            foreach ($groups as $group) {
                $byNumber[$group['number']] ??= ['line' => $group['lines'][0], 'text' => $group['texts'][0]];
            }

            $frozen = sigil_frozen_numbers($after);
            $round  = sigil_restatements($prose, array_merge($prose, $frozen));

            if ($round !== []) {
                printf("            %d possible restatement(s) — one measurement at two precisions:\n", count($round));

                /*
                 * With the line and its words, because no numeric rule can
                 * separate the last of these from the first two. 26 restating
                 * 26.4 and 13 "restating" 12.74 differ by 1.5% and 2.0%; one is
                 * a paraphrase and one is a line number, and only the sentence
                 * says which. Reporting the number alone hands the reader a
                 * puzzle they have to solve in the file.
                 */
                foreach ($round as $rounded => $source) {
                    $where = $byNumber[(string) $rounded] ?? null;

                    printf(
                        "              %-10s may round %-10s %s\n",
                        $rounded,
                        $source,
                        $where === null ? '' : sprintf('line %-4d %s', $where['line'], sigil_excerpt($where['text'], (string) $rounded)),
                    );
                }
            }

            if ($once > 0) {
                printf("            (%d value(s) stated once, not listed — pass --audit-all)\n", $once);
            }

            if ($auditAll) {
                foreach ($groups as $group) {
                    if ($group['count'] > 1) {
                        continue;
                    }

                    printf("            %-10s line %-4d %s\n", $group['number'], $group['lines'][0], sigil_excerpt($group['texts'][0], $group['number']));
                }
            }
        }

        if ($after === $before) {
            if ($binding === []) {
                printf("  ok      %s\n", $relative);
            }

            continue;
        }

        if ($check) {
            $stale++;
            printf("  STALE   %s\n", $relative);

            foreach (sigil_differences($before, $after) as $line) {
                printf("            %s\n", $line);
            }

            continue;
        }

        file_put_contents($path, $after);
        $written++;
        printf("  written %s\n", $relative);
    }

    if ($check || $audit) {
        printf(
            "\nsigil: %d document(s) checked, %d stale, %d number(s) stated in prose that are facts\n",
            count($documents),
            $stale,
            $bound,
        );

        return $stale === 0 && $bound === 0 ? 0 : 1;
    }

    printf("\nsigil: %d document(s) rendered, %d rewritten\n", count($documents), $written);

    return 0;
}

/**
 * Name what changed, per reference, so a failing gate says which claim moved.
 *
 * @return list<string>
 */
function sigil_differences(string $before, string $after): array
{
    $pattern = '/<!--\s*\[\[\s*(?P<ref>[^\]]*?)\s*\]\]\s*-->(?P<body>.*?)<!--\/-->/s';

    preg_match_all($pattern, $before, $was, PREG_SET_ORDER);
    preg_match_all($pattern, $after, $now, PREG_SET_ORDER);

    $out = [];

    foreach ($was as $i => $match) {
        $old = trim($match['body']);
        $new = trim($now[$i]['body'] ?? '');

        if ($old !== $new) {
            $out[] = sprintf('%s: document says "%s", facts say "%s"', trim($match['ref']), $old, $new);
        }
    }

    return $out;
}

/**
 * The number in the words around it, clipped to something a terminal can show.
 *
 * A line number and a digit is a report the reader has to go and reconstruct.
 * The point of an audit line is to be judged without opening the file: "1,024
 * anchor pairs from a single seed" is obviously arithmetic worth binding, and
 * "PHP 8.5" is obviously not, and neither judgement can be made from "8.5" on
 * its own.
 */
function sigil_excerpt(string $text, string $number): string
{
    $at = strpos($text, $number);

    if ($at === false) {
        return mb_strimwidth($text, 0, 72, '…');
    }

    $from = max(0, $at - 28);

    return ($from > 0 ? '…' : '') . mb_strimwidth(substr($text, $from), 0, 72, '…');
}

/**
 * Every place the release number is typed out instead of read.
 *
 * The version is the most-copied fact in this tree — forty-odd sites across
 * source comments, test docblocks, the README and the paper — and not one of
 * them was bound to {@see \LucianoPereira\PhpcpdNext\Version::NUMBER}. That
 * went unnoticed until a renumber was proposed and the answer turned out to be
 * "hand-edit forty files", which is the practice this whole mechanism exists to
 * end. The mechanism did not cover its own most duplicated fact.
 *
 * Markdown can bind a reference and does. PHP comments cannot — a sigil marker
 * is an HTML comment, and putting one inside a docblock would be nonsense — so
 * for source files the honest tool is a lint: find the literal, name it, and
 * let a person decide whether it means "the release now being built" (bind or
 * update it) or "the release in which this happened" (a record, leave it).
 *
 * That distinction is why this reports rather than rewrites. A number in a
 * record is correct precisely because it does not track the present.
 *
 * @param array<string, array<string, string>> $facts
 */
function sigil_version_literals(string $root, array $facts): int
{
    $version = $facts['code']['version'] ?? null;

    if ($version === null) {
        fwrite(STDERR, "no [code] version fact — run bench/collect-facts.php first\n");

        return 2;
    }

    // The release line, not the exact build: 2.0.0 and 2.0.0 are the same
    // claim about which release someone is talking about.
    $release = preg_replace('/-.*$/', '', $version) ?? $version;
    $pattern = '/(?<![\w.])' . preg_quote($release, '/') . '(?![\w])/';

    $found = 0;
    $files = [];

    foreach (['src', 'tests', 'bench', 'integration'] as $tree) {
        $directory = $root . '/' . $tree;

        if (!is_dir($directory)) {
            continue;
        }

        /** @var Iterator<string, SplFileInfo> $walk */
        $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        foreach ($walk as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php' || str_contains($file->getPathname(), '/corpus/')) {
                continue;
            }

            $files[] = $file->getPathname();
        }
    }

    sort($files);

    foreach ($files as $path) {
        $relative = str_replace($root . '/', '', $path);

        // Version.php is where the number lives. Everywhere else is a copy.
        if ($relative === 'src/Version.php') {
            continue;
        }

        foreach (explode("\n", (string) file_get_contents($path)) as $number => $line) {
            if (preg_match($pattern, $line) !== 1) {
                continue;
            }

            $found++;
            printf("  %s:%d  %s\n", $relative, $number + 1, trim(substr(trim($line), 0, 88)));
        }
    }

    printf("\nsigil: %d source site(s) state \"%s\" instead of reading Version::NUMBER\n", $found, $release);

    if ($found > 0) {
        printf("        Each is either a claim about the release being built (update it) or a\n");
        printf("        record of when something happened (leave it). This cannot tell them apart.\n");
    }

    return $found === 0 ? 0 : 1;
}
