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

use function count;
use function printf;

use const PHP_EOL;

use LucianoPereira\PhpcpdNext\Cache\CloneCache;
use LucianoPereira\PhpcpdNext\Cache\IncrementalIndex;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Log\Json;
use LucianoPereira\PhpcpdNext\Log\Logger;
use LucianoPereira\PhpcpdNext\Log\PMD;
use LucianoPereira\PhpcpdNext\Log\Sarif;
use LucianoPereira\PhpcpdNext\Log\Text;
use LucianoPereira\PhpcpdNext\Util\FileFinder;
use LucianoPereira\PhpcpdNext\Util\ResourceUsageFormatter;
use LucianoPereira\PhpcpdNext\Util\Timer;

final class Application
{
    private const string VERSION         = Version::NUMBER;
    private const string UPSTREAM        = '7.0-dev';
    private const string UPSTREAM_AUTHOR = 'Sebastian Bergmann';
    private const string AUTHOR          = 'Luciano Federico Pereira';

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        $this->printVersion();

        try {
            $arguments = (new ArgumentsBuilder())->build($argv);
        } catch (Exception $e) {
            print PHP_EOL . $e->getMessage() . PHP_EOL;

            return 1;
        }

        if ($arguments->version()) {
            return 0;
        }

        print PHP_EOL;

        if ($arguments->help()) {
            $this->help();

            return 0;
        }

        $files = (new FileFinder())->find(
            $arguments->directories(),
            $arguments->suffixes(),
            $arguments->exclude(),
        );

        if (empty($files)) {
            print 'No files found to scan' . PHP_EOL;

            return 1;
        }

        $config = new StrategyConfiguration($arguments);

        $timer = new Timer();
        $timer->start();

        $isCombined = ($arguments->algorithm() === null);

        if (!$isCombined && $this->usesIncrementalIndex($arguments)) {
            $index = new IncrementalIndex(
                $arguments->cacheDir() ?? '.phpcpd-cache',
                CloneCache::configFingerprint($arguments),
                $config,
            );

            $result = $index->detect($files);
            $clones = $result->clones;

            printf('(incremental index: %d reused, %d scanned)' . PHP_EOL, $result->reused, $result->scanned);
        } elseif ($isCombined) {
            if ($arguments->incremental()) {
                print '(--incremental ignored in combined mode)' . PHP_EOL;
            }

            // Default: Rabin-Karp (exact clones) + TokenBag (reordered clones), merged.
            $clones = (new Engine($config))->detect($files);
        } else {
            if ($arguments->incremental()) {
                print '(--incremental ignored: only the rabin-karp algorithm has an incremental index)' . PHP_EOL;
            }

            $cache = $arguments->cacheDir() !== null
                ? new CloneCache($arguments->cacheDir(), CloneCache::configFingerprint($arguments))
                : null;

            $clones = $cache?->get($files);

            if ($clones === null) {
                try {
                    $clones = (new Engine($config, $arguments->algorithm()))->detect($files);
                } catch (InvalidStrategyException $e) {
                    print $e->getMessage() . PHP_EOL;

                    return 1;
                }

                $cache?->put($files, $clones);
            } else {
                print '(cache hit)' . PHP_EOL;
            }
        }

        (new Text())->printResult($clones, $arguments->verbose());

        foreach ($this->fileLoggers($arguments) as $logger) {
            $logger->process($clones);
        }

        print (new ResourceUsageFormatter())->format($timer->seconds(), count($files)) . PHP_EOL;

        return count($clones) > 0 ? 1 : 0;
    }

    private function printVersion(): void
    {
        printf(
            'phpcpd %s by %s based on phpcpd %s by %s.' . PHP_EOL,
            self::VERSION,
            self::AUTHOR,
            self::UPSTREAM,
            self::UPSTREAM_AUTHOR,
        );
    }

    /**
     * The incremental index is a Rabin–Karp construction, so it only applies when
     * that algorithm is selected (the default). Requested for any other algorithm,
     * the run falls back to the coarse cache / plain detection.
     */
    private function usesIncrementalIndex(Arguments $arguments): bool
    {
        return $arguments->incremental()
            && $arguments->algorithm() === 'rabin-karp';
    }

    /** @return list<Logger> */
    private function fileLoggers(Arguments $arguments): array
    {
        $loggers = [];

        if ($arguments->pmdCpdXmlLogfile() !== null) {
            $loggers[] = new PMD($arguments->pmdCpdXmlLogfile());
        }

        if ($arguments->jsonLogfile() !== null) {
            $loggers[] = new Json($arguments->jsonLogfile());
        }

        if ($arguments->sarifLogfile() !== null) {
            $loggers[] = new Sarif($arguments->sarifLogfile());
        }

        return $loggers;
    }

    private function help(): void
    {
        print Options::help();
    }
}
