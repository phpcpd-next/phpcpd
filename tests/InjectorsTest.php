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

namespace LucianoPereira\PhpcpdNext\Tests;

use function bcb_all_operator_names;
use function bcb_apply_gapped;
use function bcb_apply_permutation;
use function bcb_edit_positions;
use function bcb_function_statements;
use function bcb_has_bool_type;
use function bcb_has_int_type;
use function bcb_inject;
use function bcb_op_ssdiff;
use function bcb_op_ssdiff_bool;
use function bcb_op_type1;
use function bcb_op_type2;
use function bcb_op_type3;
use function bcb_operator_spec;
use function bcb_operators;
use function bcb_parses;
use function bcb_target_function;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bench/injectors.php';

/**
 * The injectors are the measuring instrument for every recall number the project
 * publishes, so they are tested like one: not "does it run" but "does it produce
 * exactly the mutation it claims, and does it refuse when it cannot".
 *
 * The statement-segmentation cases below are not hypothetical. Each is a
 * construct that broke an earlier version of the segmenter when the operators
 * were run across ~4,100 real third-party files and every variant was parsed —
 * a `for` header's semicolons, an interpolated string's braces, an array of
 * closures, a `$this->{$name}` expression. They are here so those four cannot
 * come back silently.
 */
final class InjectorsTest extends TestCase
{
    private const string SRC = "<?php\nfunction f(int \$a, bool \$b): float\n{\n    \$x = \$a;\n    return 1.0;\n}\n";

    /** A function with eight plain statements — enough room for d = 1, 2 and 3. */
    private const string EIGHT = <<<'PHP'
        <?php
        function eight(): int
        {
            $a = 1;
            $b = 2;
            $c = 3;
            $d = 4;
            $e = 5;
            $f = 6;
            $g = 7;
            return $a;
        }
        PHP;

    // ── The five E2 operators, unchanged ──────────────────────────────────

    #[Test]
    public function ssdiff_rewrites_int_and_float_hints_to_string(): void
    {
        $out = bcb_op_ssdiff(self::SRC);

        self::assertStringContainsString('function f(string $a, bool $b): string', $out);
        self::assertStringContainsString('$x = $a;', $out); // body unchanged
    }

    #[Test]
    public function ssdiff_bool_rewrites_bool_hint_to_int(): void
    {
        self::assertStringContainsString('function f(int $a, int $b): float', bcb_op_ssdiff_bool(self::SRC));
    }

    #[Test]
    public function ssdiff_bool_is_a_no_op_without_a_bool_hint(): void
    {
        $src = "<?php\nfunction g(int \$a): int { return \$a; }\n";

        self::assertSame($src, bcb_op_ssdiff_bool($src));
    }

    #[Test]
    public function eligibility_predicates_detect_their_type_kinds(): void
    {
        self::assertTrue(bcb_has_int_type(self::SRC));
        self::assertTrue(bcb_has_bool_type(self::SRC));
        self::assertFalse(bcb_has_bool_type('<?php function h(int $a): int { return $a; }'));
    }

    #[Test]
    public function type2_renames_variables_to_fresh_consistent_names(): void
    {
        $out = bcb_op_type2('<?php function f($alpha, $beta) { return $alpha + $beta + $alpha; }');

        self::assertStringNotContainsString('$alpha', $out);
        self::assertStringNotContainsString('$beta', $out);
        self::assertStringContainsString('$_v0', $out);
        self::assertStringContainsString('$_v1', $out);
    }

    #[Test]
    public function type3_inserts_exactly_one_statement(): void
    {
        self::assertSame(1, substr_count(bcb_op_type3(self::SRC), 'BCB-PHP Type-3 insertion'));
    }

    #[Test]
    public function type1_changes_layout_but_keeps_the_code(): void
    {
        $out = bcb_op_type1(self::SRC);

        self::assertNotSame(self::SRC, $out);
        self::assertStringContainsString('function f(int $a, bool $b): float', $out);
    }

    #[Test]
    public function the_e2_operator_set_is_exactly_the_five_it_has_always_been(): void
    {
        // E2's published results came from iterating this set. Widening it here
        // rather than in bcb_all_operator_names() would silently change what a
        // re-run of an established experiment measures.
        self::assertSame(
            ['type1', 'type2', 'type3', 'ssdiff', 'ssdiff_bool'],
            array_keys(bcb_operators()),
        );
    }

    // ── Statement segmentation ────────────────────────────────────────────

    #[Test]
    public function statements_are_segmented_at_the_top_level_of_a_function_body(): void
    {
        $functions = bcb_function_statements(self::EIGHT);

        self::assertCount(1, $functions);
        self::assertSame('eight', $functions[0]['name']);
        self::assertCount(8, $functions[0]['statements']);
    }

    #[Test]
    public function a_compound_statement_is_one_statement_not_two(): void
    {
        $code = "<?php\nfunction f() {\n    \$a = 1;\n    if (\$a) {\n        \$b = 2;\n    } else {\n        \$b = 3;\n    }\n    return \$b;\n}\n";

        // Three: the assignment, the whole if/else, and the return. An `else`
        // read as a fresh statement would make it four.
        self::assertCount(3, bcb_function_statements($code)[0]['statements']);
    }

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function trickyConstructs(): iterable
    {
        yield 'for-header semicolons' => [
            "<?php\nfunction f() {\n    \$t = 0;\n    for (\$i = 0; \$i < 3; \$i++) {\n        \$t += \$i;\n    }\n    return \$t;\n}\n",
            3,
        ];

        yield 'interpolated braces' => [
            "<?php\nfunction f(\$o) {\n    \$a = 1;\n    \$s = \"x{\$o->name()}y\";\n    return \$s;\n}\n",
            3,
        ];

        yield 'array of closures' => [
            "<?php\nfunction f() {\n    \$a = 1;\n    \$m = [\n        1 => static function () { return 1; },\n        2 => static function () { return 2; },\n    ];\n    return \$m;\n}\n",
            3,
        ];

        yield 'variable-name brace' => [
            "<?php\nfunction f(\$o, \$p) {\n    \$a = 1;\n    return \$o->{\$p}[0];\n}\n",
            2,
        ];

        yield 'do-while trailer' => [
            "<?php\nfunction f() {\n    \$i = 0;\n    do {\n        \$i++;\n    } while (\$i < 3);\n    return \$i;\n}\n",
            3,
        ];

        yield 'try/catch/finally' => [
            "<?php\nfunction f() {\n    \$a = 1;\n    try {\n        \$a = 2;\n    } catch (\\Throwable \$e) {\n        \$a = 3;\n    } finally {\n        \$a = 4;\n    }\n    return \$a;\n}\n",
            3,
        ];

        yield 'closure assignment' => [
            "<?php\nfunction f() {\n    \$a = 1;\n    \$c = function () {\n        return 2;\n    };\n    return \$c;\n}\n",
            3,
        ];
    }

    #[Test]
    #[DataProvider('trickyConstructs')]
    public function segmentation_survives_the_constructs_that_once_broke_it(string $code, int $expected): void
    {
        self::assertTrue(bcb_parses($code), 'the fixture itself must be valid PHP');
        self::assertCount($expected, bcb_function_statements($code)[0]['statements']);
    }

    #[Test]
    public function the_target_function_is_the_largest_one(): void
    {
        $code = "<?php\nfunction small() { \$a = 1; return \$a; }\nfunction big() { \$a = 1; \$b = 2; \$c = 3; return \$a; }\n";

        self::assertSame('big', bcb_target_function($code, 2)['name']);
        self::assertNull(bcb_target_function($code, 9));
    }

    // ── Edit placement ────────────────────────────────────────────────────

    /**
     * @return iterable<string, array{0: int, 1: int, 2: list<int>}>
     */
    public static function densities(): iterable
    {
        yield 'd=1 of 8' => [8, 1, [4]];
        yield 'd=2 of 8' => [8, 2, [2, 5]];
        yield 'd=3 of 8' => [8, 3, [2, 4, 5]];
        yield 'too few statements' => [3, 3, []];
    }

    #[Test]
    #[DataProvider('densities')]
    public function edit_positions_are_evenly_spaced_distinct_and_interior(int $count, int $edits, array $expected): void
    {
        $positions = bcb_edit_positions($count, $edits);

        self::assertSame($expected, $positions);

        foreach ($positions as $p) {
            self::assertGreaterThan(0, $p, 'the first statement is left alone so the clone keeps a head');
            self::assertLessThan($count - 1, $p, 'the last statement is left alone so the clone keeps a tail');
        }
    }

    // ── The gapped family ─────────────────────────────────────────────────

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function gappedOperators(): iterable
    {
        foreach (['insert', 'delete', 'substitute'] as $kind) {
            foreach ([1, 2, 3] as $d) {
                yield "{$kind} d={$d}" => [$kind, $d];
            }
        }
    }

    #[Test]
    #[DataProvider('gappedOperators')]
    public function a_gapped_operator_makes_exactly_d_recorded_edits(string $kind, int $edits): void
    {
        $result = bcb_apply_gapped(self::EIGHT, $kind, $edits);

        self::assertTrue($result['eligible'], $result['reason']);
        self::assertCount($edits, $result['injections']);
        self::assertNotSame(self::EIGHT, $result['code']);
        self::assertTrue(bcb_parses($result['code']), 'a mutation that does not parse is not a clone');

        foreach ($result['injections'] as $injection) {
            self::assertSame($kind, $injection['kind']);
            self::assertSame('eight', $injection['function']);

            // The record must point at the bytes it claims, on both sides.
            self::assertSame(
                $injection['base_text'],
                substr(self::EIGHT, $injection['base_offset'], $injection['base_length']),
            );
            self::assertSame(
                $injection['variant_text'],
                substr($result['code'], $injection['variant_offset'], strlen($injection['variant_text'])),
            );
        }
    }

    #[Test]
    public function deletion_removes_a_statement_and_insertion_adds_one(): void
    {
        $before = count(bcb_function_statements(self::EIGHT)[0]['statements']);

        $deleted  = bcb_apply_gapped(self::EIGHT, 'delete', 2);
        $inserted = bcb_apply_gapped(self::EIGHT, 'insert', 2);

        self::assertSame($before - 2, count(bcb_function_statements($deleted['code'])[0]['statements']));
        self::assertSame($before + 2, count(bcb_function_statements($inserted['code'])[0]['statements']));
    }

    #[Test]
    public function substitution_keeps_the_statement_count_and_changes_the_bytes(): void
    {
        $result = bcb_apply_gapped(self::EIGHT, 'substitute', 3);

        self::assertSame(8, count(bcb_function_statements($result['code'])[0]['statements']));
        self::assertSame(3, substr_count($result['code'], 'BCB-PHP gapped substitution'));
    }

    #[Test]
    public function a_function_with_no_room_is_reported_ineligible_rather_than_mutated(): void
    {
        // Silently returning the input would put an identical copy in the corpus
        // wearing a gapped operator's label, and score as a clone the detector
        // "found" without any gap ever being injected.
        $tiny   = "<?php\nfunction t() {\n    \$a = 1;\n    return \$a;\n}\n";
        $result = bcb_apply_gapped($tiny, 'delete', 3);

        self::assertFalse($result['eligible']);
        self::assertSame([], $result['injections']);
        self::assertSame($tiny, $result['code']);
        self::assertStringContainsString('top-level statements', $result['reason']);
    }

    #[Test]
    public function an_unknown_gapped_kind_is_refused(): void
    {
        self::assertFalse(bcb_apply_gapped(self::EIGHT, 'rotate', 1)['eligible']);
    }

    // ── The permutation family ────────────────────────────────────────────

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function permutations(): iterable
    {
        yield 'adjacent' => ['adjacent', 1];
        yield 'distant'  => ['distant', 5];
    }

    #[Test]
    #[DataProvider('permutations')]
    public function a_permutation_swaps_two_statements_at_the_expected_distance(string $mode, int $distance): void
    {
        $result = bcb_apply_permutation(self::EIGHT, $mode);

        self::assertTrue($result['eligible'], $result['reason']);
        self::assertCount(2, $result['injections']);
        self::assertTrue(bcb_parses($result['code']));

        // A swap moves bytes without adding or removing any.
        self::assertSame(strlen(self::EIGHT), strlen($result['code']));
        self::assertNotSame(self::EIGHT, $result['code']);

        [$first, $second] = $result['injections'];
        self::assertSame($distance, $second['statement_index'] - $first['statement_index']);

        // Each site receives exactly the other site's original text.
        self::assertSame($first['base_text'], $second['variant_text']);
        self::assertSame($second['base_text'], $first['variant_text']);
    }

    #[Test]
    public function a_permutation_preserves_the_statement_multiset(): void
    {
        $original = array_map(
            static fn(array $s): string => substr(self::EIGHT, $s['start'], $s['end'] - $s['start']),
            bcb_function_statements(self::EIGHT)[0]['statements'],
        );

        $result  = bcb_apply_permutation(self::EIGHT, 'distant');
        $swapped = array_map(
            static fn(array $s): string => substr($result['code'], $s['start'], $s['end'] - $s['start']),
            bcb_function_statements($result['code'])[0]['statements'],
        );

        self::assertNotSame($original, $swapped, 'the order must actually change');

        sort($original);
        sort($swapped);
        self::assertSame($original, $swapped, 'but nothing may be added or lost');
    }

    #[Test]
    public function a_permutation_needs_room_for_a_head_and_a_tail(): void
    {
        $four = "<?php\nfunction f() {\n    \$a = 1;\n    \$b = 2;\n    \$c = 3;\n    return \$a;\n}\n";

        self::assertTrue(bcb_apply_permutation($four, 'adjacent')['eligible']);
        self::assertFalse(bcb_apply_permutation($four, 'distant')['eligible']);
    }

    // ── The registry and the single entry point ───────────────────────────

    #[Test]
    public function every_operator_name_resolves_and_applies(): void
    {
        $names = bcb_all_operator_names();

        self::assertCount(16, $names, '5 E2 + 9 gapped + 2 permutation');
        self::assertSame($names, array_unique($names));

        foreach ($names as $name) {
            $result = bcb_inject(self::EIGHT, $name);
            self::assertTrue($result['eligible'], $name . ': ' . $result['reason']);
            self::assertTrue(bcb_parses($result['code']), $name . ' produced unparseable PHP');
        }
    }

    #[Test]
    public function an_operator_name_carries_its_whole_specification(): void
    {
        // The manifest records only the name, so the name has to be enough to
        // re-derive the variant.
        self::assertSame(['family' => 'gapped', 'kind' => 'substitute', 'edits' => 2], bcb_operator_spec('gapped_substitute_d2'));
        self::assertSame(['family' => 'permutation', 'kind' => 'distant', 'edits' => 1], bcb_operator_spec('permute_distant'));
        self::assertNull(bcb_operator_spec('type1'));
        self::assertNull(bcb_operator_spec('gapped_insert_d9'));
        self::assertNull(bcb_operator_spec('gapped_reverse_d1'));
    }

    #[Test]
    public function an_unknown_operator_is_refused_rather_than_ignored(): void
    {
        $result = bcb_inject(self::EIGHT, 'no_such_operator');

        self::assertFalse($result['eligible']);
        self::assertStringContainsString('unknown operator', $result['reason']);
    }

    #[Test]
    public function every_operator_is_deterministic(): void
    {
        // Recall curves are compared across runs; an operator that varied would
        // make two runs of the same benchmark incomparable for reasons that have
        // nothing to do with the detector.
        foreach (bcb_all_operator_names() as $name) {
            self::assertSame(
                bcb_inject(self::EIGHT, $name)['code'],
                bcb_inject(self::EIGHT, $name)['code'],
                $name . ' is not deterministic',
            );
        }
    }
}
