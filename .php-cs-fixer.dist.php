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

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/bench')
    ->in(__DIR__ . '/integration')
    // The locale files are PHP, and were held to no whitespace rule at all
    // until five translations arrived at once with trailing-newline drift.
    ->in(__DIR__ . '/locale')
    ->exclude(['fixtures', 'corpus', 'vendor', 'results']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setFinder($finder)
    ->setRules([
        'encoding'                                   => true,
        'full_opening_tag'                           => true,
        'line_ending'                                => true,
        'blank_line_after_opening_tag'               => true,
        'no_leading_namespace_whitespace'            => true,
        'no_trailing_whitespace'                     => true,
        'no_whitespace_in_blank_line'                => true,
        'single_blank_line_at_eof'                   => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'no_empty_statement'                         => true,
        'no_unused_imports'                          => true,
        'array_syntax'                               => ['syntax' => 'short'],
        'normalize_index_brace'                      => true,
        'trim_array_spaces'                          => true,
        'whitespace_after_comma_in_array'            => true,
        'standardize_not_equals'                     => true,
    ]);
