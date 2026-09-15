<?php

declare(strict_types=1);

/*
 * BCB-PHP shared library.
 *
 * The benchmark drives phpcpd as a *library* — it constructs the real Detector
 * and strategies and reads CodeCloneMap objects directly. There is no shelling
 * out to the binary and no scraping of human-readable text output: the runners
 * get structured results from the same engine the CLI uses. This is both more
 * robust (no output-format coupling) and dramatically faster for repeated runs
 * (no per-invocation process spawn).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\CloneSuppressions;
use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\AbstractStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\FileTokens;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Engine;
use LucianoPereira\PhpcpdNext\PresetDetection;
use LucianoPereira\PhpcpdNext\Util\FileFinder;

/**
 * The width of one token in a FileTokens signature.
 *
 * It was written down as a literal 5, and then the hash widened to xxh64 and the
 * literal did not. A signature is a flat byte string, so a stale stride does not
 * fail loudly: it reads each token at an offset three bytes short of where the
 * token begins, and two sides of a comparison drift apart by three bytes for
 * every token their start indices differ by. When both sides happen to start at
 * the same index they stay in step and the reading looks right, which is why the
 * gates kept passing while their adjudicator was measuring nothing.
 *
 * Still written down, because PHPStan cannot see a define() and a bench file
 * that cannot be analysed is a worse trade than a literal. What has changed is
 * that disagreeing with the product is now fatal rather than invisible.
 */
const BCB_TOKEN_BYTES = 8;

if (BCB_TOKEN_BYTES !== strlen(FileTokens::token(T_STRING, ''))) {
    fwrite(STDERR, sprintf(
        "bench: a signature token is %d bytes in the product but %d here; "
        . "every offset this harness computes would be wrong.\n",
        strlen(FileTokens::token(T_STRING, '')),
        BCB_TOKEN_BYTES,
    ));

    exit(1);
}

/**
 * Build a detection configuration from a small option array. Only the knobs the
 * benchmark varies are exposed; everything else takes the CLI default.
 *
 * Since 2.0.0 the engine's slice of the settings is its own record rather than
 * a projection of the whole command line, so this builds a StrategyConfiguration
 * directly. The benchmark's own defaults (minTokens 50 / minLines 1) are kept as
 * they were, so previously recorded runs stay comparable.
 *
 * Normalization follows the shipped CLI default rather than being pinned off, so
 * that a gate reading "default (rk+tokenbag)" reads the configuration the tool
 * actually ships. Pinned off, the subsumption and walltime gates were measuring
 * a mode no user runs, and the reporter goldens were captured from one.
 *
 * @param array{minTokens?:int, minLines?:int, fuzzy?:bool, typeAnchored?:bool, normalization?:Normalization, similarity?:float, algorithm?:string} $o
 */
function bcb_config(array $o): StrategyConfiguration
{
    // Omitting both keys means "whatever ships"; naming both means a stated
    // configuration. Naming exactly one used to mean "this one on, the other
    // off", which was only ever true because the shipped default had them both
    // off — and when normalization became the default that reading silently
    // inverted. It took `rk` and `rk+fuzzy` in the comparison matrix to the same
    // run, and both arms of E2 to the same configuration, with no error: a
    // variant table whose variants had quietly become one variant.
    // A benchmark names the view it is measuring, or takes the shipped one.
    //
    // This used to police a pair of booleans: naming exactly one of `fuzzy` and
    // `typeAnchored` meant "this on, the other off", which was only ever true
    // because the shipped default had them both off, and the reading silently
    // inverted when normalization became the default. It took `rk` and
    // `rk+fuzzy` in the comparison matrix to the same run, and both arms of E2
    // to the same configuration, with no error.
    //
    // The pair is now one {@see Normalization}, so there is no combination to
    // get wrong and nothing left to police. The old keys are still accepted so
    // that recorded runs stay reproducible.
    $normalization = $o['normalization'] ?? null;

    if ($normalization === null) {
        $fuzzy        = $o['fuzzy'] ?? null;
        $typeAnchored = $o['typeAnchored'] ?? null;

        if ($fuzzy === null && $typeAnchored === null) {
            $normalization = Normalization::TypeAnchored; // whatever ships
        } elseif ($typeAnchored) {
            $normalization = Normalization::TypeAnchored;
        } elseif ($fuzzy) {
            $normalization = Normalization::Fuzzy;
        } else {
            $normalization = Normalization::Raw;
        }
    }

    return new StrategyConfiguration(
        minLines:      $o['minLines'] ?? 1,
        minTokens:     $o['minTokens'] ?? 50,
        normalization: $normalization,
        minSimilarity: $o['similarity'] ?? 0.7,
    );
}

/**
 * Resolve an algorithm name to a strategy through the shipped Engine, so the
 * benchmark can never run an engine wired differently from the CLI's.
 *
 * @throws \LucianoPereira\PhpcpdNext\InvalidStrategyException
 */
function bcb_strategy(string $algorithm, StrategyConfiguration $config): AbstractStrategy
{
    return (new Engine($config))->strategyFor($algorithm);
}

/**
 * Detect clones in a set of files and return the CodeCloneMap — the same object
 * the CLI builds. Read ->clones(), ->numberOfDuplicatedLines(), isGapped() etc.
 *
 * @param list<string> $files
 * @param array{minTokens?:int, minLines?:int, fuzzy?:bool, typeAnchored?:bool, normalization?:Normalization, similarity?:float, algorithm?:string} $o
 */
function bcb_detect(array $files, array $o = []): CodeCloneMap
{
    $config = bcb_config($o);

    $map = (new Detector(bcb_strategy($o['algorithm'] ?? 'rabin-karp', $config)))
        ->copyPasteDetection($files);

    // What the CLI does, in the order it does it. `Engine::detect()` settles a
    // map before suppression and this path does not go through it, so every
    // harness built on this function was measuring maps holding duplicate
    // readings of one region — findings the shipped tool removes. A benchmark
    // that measures something the user never sees is measuring the wrong thing,
    // and ten harnesses are built on this one.
    $map->settle();

    return CloneSuppressions::applyTo($map);
}

/**
 * Gather the PHP files of a directory using phpcpd's own FileFinder — the exact
 * selection the tool applies (excluded dirs pruned during traversal).
 *
 * @param list<string> $exclude
 * @return list<string>
 */
function bcb_files(string $dir, array $exclude = ['vendor', 'node_modules', 'storage', 'bootstrap/cache']): array
{
    return (new FileFinder())->find([$dir], ['.php'], $exclude);
}

/**
 * Gather the PHP files of one or more directories for a *gate* — the corpus as
 * the product itself defines it, sorted into a total order.
 *
 * Every check that records a number reads its file list from here. The rule is
 * ruling K's principle carried down to the bench layer: the tool already knows
 * what is not program text, so the benchmark asks it rather than maintaining a
 * hand list that drifts. Concretely this means FileFinder's default excludes
 * are ON — the four checks that record phpunit numbers had them OFF, and so
 * scanned 175 files the product would never scan: 117 under `tools/.phpstan`
 * (a dumped static-analysis tool tree), 35 vendor stubs inside end-to-end test
 * fixtures, 12 other vendor files, and 11 build scripts. Every phpunit number
 * from M1 through M3 was measured over that set; see the M4 packet for the
 * corrections.
 *
 * The previous justification for turning them off — that a check pointed at a
 * directory *under* vendor/ would see the defaults prune everything and compare
 * two empty reports — does not hold: `find()` prunes descendants of the roots it
 * is given, never the roots themselves, so `find(['vendor/phpunit'], ...)`
 * returns the same 1,039 files either way. The escape hatch it was protecting
 * was not needed, and it cost four gates their corpus definition.
 *
 * @param list<string> $dirs
 * @return list<string>
 */
function bcb_gate_files(array $dirs): array
{
    $files = [];

    foreach ($dirs as $dir) {
        // And the preset, where the product would apply one. Ruling K's
        // principle carried one step further than it was: an ordinary run
        // auto-detects a framework and scans what that framework's preset says
        // is source, so a gate that skips detection measures a corpus the
        // product would not scan. On firefly-iii that is 1,445 files against
        // the 1,291 a user sees, and the 154 include `database/migrations` —
        // excluded by the preset with its reason written beside it, "up()/down()
        // boilerplate is duplicate by design", and rated by hand as a family of
        // false positives before anyone noticed the benchmark was looking at
        // them at all.
        //
        // No corpus but firefly-iii detects a preset, so no other number moves.
        $preset = PresetDetection::detect([$dir]);

        $found = $preset === null
            ? (new FileFinder())->find([$dir], ['.php'], [])
            : (new FileFinder())->find([$dir], $preset->suffixes, $preset->exclude);

        foreach ($found as $file) {
            $files[] = $file;
        }
    }

    sort($files);

    return $files;
}

/**
 * Number of SIGNIFICANT tokens in a source string, counted by the detector's own
 * tokenizer (the same unit --min-tokens is measured in). Used by E2 to keep only
 * functions large enough to form a clone, so recall reflects the detector rather
 * than units that are simply shorter than the threshold.
 */
/**
 * How many source lines a unit occupies.
 *
 * The sample population of a recall experiment has to be defined without
 * reference to the tokenizer, or the experiment cannot be compared against
 * itself across a change to it. Selecting by token count looks stable and is
 * not: when a token stops meaning what it meant, the same threshold admits a
 * different set of functions, and a recall figure measured over a different
 * population is a measurement of the ruler.
 *
 * Lines are the coarser unit and the honest one here, because nothing this
 * project changes will alter what a line is.
 */
function bcb_line_count(string $code): int
{
    return substr_count($code, "\n") + 1;
}

/**
 * How many tokens a unit holds, under a definition that is frozen here.
 *
 * Eligibility for a recall sample has to answer two questions at once, and they
 * pull against each other. The unit must be large enough that a miss means the
 * detector missed rather than the unit being under the reporting threshold —
 * which is a question about tokens. And the population must not move when the
 * encoder changes, or the experiment cannot be compared with itself — which
 * says not to ask the encoder.
 *
 * So the count is taken here, by a rule that will not change: array tokens,
 * less the ones the encoder has always ignored. It is not the encoder's count
 * and is not meant to be. It exists to hold one population still.
 */
function bcb_frozen_token_count(string $code): int
{
    /** @var array<int, true> $ignored */
    static $ignored = [
        T_INLINE_HTML => true, T_COMMENT => true, T_DOC_COMMENT => true,
        T_OPEN_TAG => true, T_OPEN_TAG_WITH_ECHO => true, T_CLOSE_TAG => true,
        T_WHITESPACE => true, T_USE => true, T_NS_SEPARATOR => true,
    ];

    $count = 0;

    /** @var array<int, array{0: int, 1: string, 2: int}|string> $tokens */
    $tokens = @token_get_all($code);

    foreach ($tokens as $token) {
        if (is_array($token) && !isset($ignored[$token[0]])) {
            ++$count;
        }
    }

    return $count;
}

function bcb_token_count(string $code): int
{
    /** @var DefaultStrategy|null $strategy */
    static $strategy = null;
    $strategy ??= new DefaultStrategy(bcb_config([]));

    return count($strategy->tokenize($code)->tokenLines);
}

/**
 * Extract the source text of each function/method body (signature through the
 * matching closing brace) from a PHP source string.
 *
 * Function granularity is what E2 needs: a type hint is a meaningful fraction of
 * a short function but a rounding error in a whole file, so injecting at file
 * scale lets the surrounding type-free code swamp the type signal. Interpolation
 * braces (T_CURLY_OPEN, "{$x}") are counted so a function containing an
 * interpolated string does not desync the brace matcher.
 *
 * @return list<string>
 */
function bcb_extract_functions(string $code): array
{
    $tokens = token_get_all($code);
    $n      = count($tokens);
    $funcs  = [];

    // Tokens that open a brace level. Built from defined() so an absent constant
    // (e.g. a removed legacy token) never fatals the way an undefined one would.
    $openTokens = [];

    foreach (['T_CURLY_OPEN', 'T_DOLLAR_OPEN_CURLY_BRACES'] as $name) {
        if (defined($name)) {
            $openTokens[(int) constant($name)] = true;
        }
    }

    for ($i = 0; $i < $n; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }

        // Walk to the body '{', bailing on ';' (abstract / interface method).
        $j        = $i + 1;
        $hasBody  = false;

        for (; $j < $n; $j++) {
            if ($tokens[$j] === '{') {
                $hasBody = true;
                break;
            }

            if ($tokens[$j] === ';') {
                break;
            }
        }

        if (!$hasBody) {
            continue;
        }

        // Brace-match to the end of the body.
        $depth = 0;
        $end   = $j;

        for ($k = $j; $k < $n; $k++) {
            $t = $tokens[$k];

            if ($t === '{' || (is_array($t) && isset($openTokens[$t[0]]))) {
                $depth++;
            } elseif ($t === '}') {
                $depth--;

                if ($depth === 0) {
                    $end = $k;
                    break;
                }
            }
        }

        $src = '';

        for ($m = $i; $m <= $end; $m++) {
            $src .= is_array($tokens[$m]) ? $tokens[$m][1] : $tokens[$m];
        }

        $funcs[] = $src;
    }

    return $funcs;
}

/**
 * Refuse a corpus argument that is not a directory.
 *
 * Every harness here reads its corpus arguments as paths, so a bare name —
 * `php-parser` rather than `bench/corpus/php-parser`, which is the natural
 * thing to type — named nothing, scanned nothing, and was reported as an
 * empty *result*. `run-recall.php` printed "0 pairs inside the guarantee" and
 * exited FAIL, which reads like the engine regressing rather than the argument
 * being wrong; the failure has to name the typo instead of describing its
 * consequences.
 *
 * Checked once here rather than at seven call sites, because it is one rule.
 *
 * @param list<string> $dirs
 */
function bcb_require_dirs(array $dirs): void
{
    $missing = [];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            $missing[] = $dir;
        }
    }

    if ($missing === []) {
        return;
    }

    foreach ($missing as $dir) {
        fwrite(STDERR, sprintf("not a directory: %s\n", $dir));

        // A bare corpus name is the likely mistake, so name the path that works.
        if (is_dir(__DIR__ . '/corpus/' . $dir)) {
            fwrite(STDERR, sprintf("  did you mean bench/corpus/%s ?\n", $dir));
        }
    }

    exit(1);
}

// ---------------------------------------------------------------------------
// The recorded evidence: manifests, worksheets and site tables
// ---------------------------------------------------------------------------

/**
 * The data rows of one of the bench's tab-separated records: blank lines and
 * `#` comments dropped, everything else split on tabs.
 *
 * Every reader below is one loop over this, because "which lines carry data"
 * is a property of the format and not of the file being read — three scripts
 * answering it three times is three chances to answer it differently.
 *
 * @return list<list<string>>
 */
function bcb_tsv_rows(string $path): array
{
    $rows = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $rows[] = explode("\t", $line);
    }

    return $rows;
}

/**
 * Ruling P's pinned manifest, as a path => content-hash map. A file that is no
 * longer at its pinned hash is not evidence about the tree a worksheet was
 * rated against, and the callers refuse to read it rather than score it.
 *
 * @return array<string, string>
 */
function bcb_read_pin(string $path): array
{
    $pinned = [];

    foreach (bcb_tsv_rows($path) as $columns) {
        if (count($columns) >= 2) {
            $pinned[$columns[0]] = $columns[1];
        }
    }

    return $pinned;
}

/**
 * The relocated per-site table: finding id => [relative path, start line, line
 * count].
 *
 * @return array<string, list<array{0: string, 1: int, 2: int}>>
 */
function bcb_read_site_table(string $path): array
{
    $rows = [];

    foreach (bcb_tsv_rows($path) as $columns) {
        if (count($columns) < 6) {
            continue;
        }

        $rows[$columns[0]][] = [$columns[3], (int) $columns[4], (int) $columns[5]];
    }

    return $rows;
}

/**
 * A rated worksheet: finding id => the rater's verdict and the shape recorded
 * beside it.
 *
 * Both counts are read for every caller, whether or not that caller asks about
 * them. A worksheet row is one record, and a reader that returns a different
 * record per script is a reader each script has to be checked against.
 *
 * @return array<string, array{verdict: string, sites: int, lines: int}>
 */
function bcb_read_worksheet(string $path): array
{
    $rows = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (!str_starts_with($line, 'FINDING')) {
            continue;
        }

        $columns = explode("\t", $line);

        $rows[$columns[1]] = [
            'verdict' => trim($columns[2] ?? ''),
            'sites'   => bcb_counted($columns[5] ?? '', 'site'),
            'lines'   => bcb_counted($columns[3] ?? '', 'line'),
        ];
    }

    return $rows;
}

/**
 * The count in a `12 sites` / `12 lines` worksheet cell, or 0 where the cell
 * does not read as one — an unrecorded shape, not a shape of zero.
 */
function bcb_counted(string $cell, string $noun): int
{
    if (preg_match('/^(\d+) ' . $noun . 's?$/', $cell, $matches) !== 1) {
        return 0;
    }

    return (int) $matches[1];
}
