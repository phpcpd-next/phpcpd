<?php

declare(strict_types=1);

/*
 * Builds a self-contained phpcpd-next.phar from src/.
 *
 * Run via the wrapper (handles the phar.readonly ini setting):
 *     bash bin/build-phar.sh
 * or directly:
 *     php -d phar.readonly=0 bin/build-phar.php
 *
 * The phar bundles only src/ (the tool has no runtime Composer dependencies);
 * its stub registers a minimal PSR-4 autoloader mirroring composer.json and
 * runs the Application. Output: build/phpcpd-next.phar.
 */

if (ini_get('phar.readonly')) {
    fwrite(STDERR, "phar.readonly is on. Run: php -d phar.readonly=0 bin/build-phar.php\n");
    exit(1);
}

$root = dirname(__DIR__);
$pharName = 'phpcpd-next.phar';
$buildDir = $root . '/build';
$outFile  = $buildDir . '/' . $pharName;

if (!is_dir($buildDir)) {
    mkdir($buildDir, 0o755, true);
}

if (file_exists($outFile)) {
    unlink($outFile);
}

$phar = new Phar($outFile, 0, $pharName);
$phar->startBuffering();

// Bundle the source tree; paths inside the phar become src/...
$phar->buildFromIterator(
    new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS),
    ),
    $root . '/',
);

// Stub: PHP-version guard + PSR-4 autoloader (matches composer.json) + entry point.
$stub = <<<'STUB'
#!/usr/bin/env php
<?php
Phar::mapPhar('phpcpd-next.phar');

if (version_compare('8.5.0', PHP_VERSION, '>')) {
    fwrite(STDERR, 'phpcpd-next requires PHP 8.5 or later; you are using ' . PHP_VERSION . PHP_EOL);
    exit(1);
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'LucianoPereira\\PhpcpdNext\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    foreach (['src/', 'src/CLI/', 'src/Exceptions/'] as $base) {
        $file = 'phar://phpcpd-next.phar/' . $base . $relative;

        if (is_file($file)) {
            require $file;

            return;
        }
    }
});

exit((new LucianoPereira\PhpcpdNext\Application())->run($_SERVER['argv']));
__HALT_COMPILER();
STUB;

$phar->setStub($stub);
$phar->stopBuffering();
chmod($outFile, 0o755);

printf("Built %s (%d files, %d bytes)\n", $outFile, count($phar), filesize($outFile));
