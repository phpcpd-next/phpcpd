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

use LucianoPereira\PhpcpdNext\Arguments;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\AbstractStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTreeStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenBagStrategy;
use LucianoPereira\PhpcpdNext\Util\FileFinder;

/**
 * Build a detection configuration from a small option array. Only the knobs the
 * benchmark varies are exposed; everything else takes the CLI default.
 *
 * @param array{minTokens?:int, minLines?:int, fuzzy?:bool, typeAnchored?:bool, editDistance?:int, headEquality?:int, similarity?:float, algorithm?:string} $o
 */
function bcb_config(array $o): StrategyConfiguration
{
    $args = new Arguments(
        directories:      [],
        suffixes:         ['.php'],
        exclude:          [],
        pmdCpdXmlLogfile: null,
        linesThreshold:   $o['minLines'] ?? 1,
        tokensThreshold:  $o['minTokens'] ?? 50,
        fuzzy:            $o['fuzzy'] ?? false,
        verbose:          false,
        help:             false,
        version:          false,
        algorithm:        $o['algorithm'] ?? 'rabin-karp',
        editDistance:     $o['editDistance'] ?? 5,
        headEquality:     $o['headEquality'] ?? 10,
        similarity:       $o['similarity'] ?? 0.7,
        typeAnchored:     $o['typeAnchored'] ?? false,
    );

    return new StrategyConfiguration($args);
}

function bcb_strategy(string $algorithm, StrategyConfiguration $config): AbstractStrategy
{
    return match ($algorithm) {
        'suffixtree' => new SuffixTreeStrategy($config),
        'tokenbag'   => new TokenBagStrategy($config),
        default      => new DefaultStrategy($config),
    };
}

/**
 * Detect clones in a set of files and return the CodeCloneMap — the same object
 * the CLI builds. Read ->clones(), ->numberOfDuplicatedLines(), isGapped() etc.
 *
 * @param list<string> $files
 * @param array{minTokens?:int, minLines?:int, fuzzy?:bool, typeAnchored?:bool, editDistance?:int, headEquality?:int, similarity?:float, algorithm?:string} $o
 */
function bcb_detect(array $files, array $o = []): CodeCloneMap
{
    $config = bcb_config($o);

    return (new Detector(bcb_strategy($o['algorithm'] ?? 'rabin-karp', $config)))
        ->copyPasteDetection($files);
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
 * Number of SIGNIFICANT tokens in a source string, counted by the detector's own
 * tokenizer (the same unit --min-tokens is measured in). Used by E2 to keep only
 * functions large enough to form a clone, so recall reflects the detector rather
 * than units that are simply shorter than the threshold.
 */
function bcb_token_count(string $code): int
{
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
