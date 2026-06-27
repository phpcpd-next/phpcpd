<?php

declare(strict_types=1);
/*
 * This file is part of PHP Copy/Paste Detector (PHPCPD).
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 * (c) 2026 Luciano Federico Pereira
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LucianoPereira\PhpcpdNext;

final class ArgumentsBuilder
{
    /**
     * @param list<string> $argv
     * @throws ArgumentsBuilderException
     */
    public function build(array $argv): Arguments
    {
        $parsed = (new OptionParser())->parse(Options::definitions(), $argv);

        $directories = [];

        foreach ($parsed['arguments'] as $directory) {
            if ($directory !== '') {
                $directories[] = $directory;
            }
        }

        $exclude          = [];
        $suffixes         = ['.php'];
        $pmdCpdXmlLogfile = null;
        $jsonLogfile      = null;
        $sarifLogfile     = null;
        $cacheDir         = null;
        $linesThreshold   = 5;
        $tokensThreshold  = 70;
        $editDistance     = 5;
        $headEquality     = 10;
        $fuzzy            = false;
        $typeAnchored     = false;
        $incremental      = false;
        $verbose          = false;
        $help             = false;
        $version          = false;
        $algorithm        = null; // null = combined rk+tb (default)
        $similarity       = 0.7;

        foreach ($parsed['options'] as [$name, $value]) {
            switch ($name) {
                case 'suffix':
                    if ($value !== null && $value !== '') {
                        $suffixes[] = $value;
                    }
                    break;
                case 'exclude':
                    if ($value !== null && $value !== '') {
                        $exclude[] = $value;
                    }
                    break;
                case 'log-pmd':
                    $pmdCpdXmlLogfile = $value;
                    break;
                case 'log-json':
                    $jsonLogfile = $value;
                    break;
                case 'log-sarif':
                    $sarifLogfile = $value;
                    break;
                case 'fuzzy':
                    $fuzzy = true;
                    break;
                case 'type-anchored':
                    $typeAnchored = true;
                    break;
                case 'min-lines':
                    $linesThreshold = (int) $value;
                    break;
                case 'min-tokens':
                    $tokensThreshold = (int) $value;
                    break;
                case 'head-equality':
                    $headEquality = (int) $value;
                    break;
                case 'edit-distance':
                    $editDistance = (int) $value;
                    break;
                case 'verbose':
                    $verbose = true;
                    break;
                case 'help':
                    $help = true;
                    break;
                case 'version':
                    $version = true;
                    break;
                case 'rk':
                    $algorithm = 'rabin-karp';
                    break;
                case 'algorithm':
                    $algorithm = (string) $value;
                    break;
                case 'min-similarity':
                    $similarity = (float) $value;
                    break;
                case 'cache':
                    if ($cacheDir === null) {
                        $cacheDir = '.phpcpd-cache';
                    }
                    break;
                case 'cache-dir':
                    if ($value !== null && $value !== '') {
                        $cacheDir = $value;
                    }
                    break;
                case 'incremental':
                    $incremental = true;
                    break;
            }
        }

        if (empty($directories) && !$help && !$version) {
            throw new ArgumentsBuilderException('No directory specified');
        }

        return new Arguments(
            directories: $directories,
            suffixes: $suffixes,
            exclude: $exclude,
            pmdCpdXmlLogfile: $pmdCpdXmlLogfile,
            linesThreshold: $linesThreshold,
            tokensThreshold: $tokensThreshold,
            fuzzy: $fuzzy,
            typeAnchored: $typeAnchored,
            verbose: $verbose,
            help: $help,
            version: $version,
            algorithm: $algorithm,
            editDistance: $editDistance,
            headEquality: $headEquality,
            jsonLogfile: $jsonLogfile,
            sarifLogfile: $sarifLogfile,
            similarity: $similarity,
            cacheDir: $cacheDir,
            incremental: $incremental,
        );
    }
}
