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

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/integration',
    ])
    // Fixtures are hand-crafted inputs for the clone detector: reformatting them
    // would shift the line/token counts the tests assert on. Never touch them.
    ->exclude('fixtures')
    ->append([__FILE__]);

// This config codifies the style the codebase already follows (notably the
// function → const → class import grouping), so a fresh `composer lint` run is
// green. Add rules deliberately, verifying with `--dry-run` that they don't
// trigger an unrelated mass reformat.
return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        'declare_strict_types'             => true,
        'no_unused_imports'                => true,
        'ordered_imports'                  => ['imports_order' => ['function', 'const', 'class'], 'sort_algorithm' => 'none'],
        'blank_line_between_import_groups' => true,
        'single_quote'                     => true,
        'array_syntax'                     => ['syntax' => 'short'],
        'no_trailing_whitespace'           => true,
        'single_blank_line_at_eof'         => true,
    ])
    ->setFinder($finder);
