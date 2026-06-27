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

/**
 * Guard against direct PHP execution of test files.
 *
 * PHPUnit test classes are not standalone scripts. This guard loads the
 * Composer autoloader (so class definitions succeed) then exits with a
 * helpful message when a file is run via `php tests/SomeTest.php` instead
 * of through the PHPUnit runner.
 *
 * Because require_once tracks by path, this file is executed only once
 * per process even though every test file includes it.
 */

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoload) && !class_exists(\PHPUnit\Framework\TestCase::class)) {
    require_once $autoload;
}

// Detect direct invocation: argv[0] is the test file, not the PHPUnit binary.
$runner = basename((string) ($_SERVER['argv'][0] ?? ''));

if (!str_contains($runner, 'phpunit') && !str_contains($runner, 'pest')) {
    $testFile = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? 'the test file'));
    fwrite(STDERR, sprintf(
        'This is a PHPUnit test class, not a standalone script.%1$s' .
        'Run it via:%1$s' .
        '  ./vendor/bin/phpunit              (full suite)%1$s' .
        '  ./vendor/bin/phpunit tests/%2$s%1$s',
        PHP_EOL,
        $testFile,
    ));
    exit(1);
}
