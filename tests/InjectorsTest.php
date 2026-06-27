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

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../bench/injectors.php';

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bcb_has_bool_type;
use function bcb_has_int_type;
use function bcb_op_ssdiff;
use function bcb_op_ssdiff_bool;
use function bcb_op_type1;
use function bcb_op_type2;
use function bcb_op_type3;
use function bcb_operators;
use function substr_count;

final class InjectorsTest extends TestCase
{
    private const string SRC = "<?php\nfunction f(int \$a, bool \$b): float\n{\n    \$x = \$a;\n    return 1.0;\n}\n";

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
        $out = bcb_op_ssdiff_bool(self::SRC);

        self::assertStringContainsString('function f(int $a, int $b): float', $out);
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
        self::assertFalse(bcb_has_bool_type("<?php function h(int \$a): int { return \$a; }"));
    }

    #[Test]
    public function type2_renames_variables_to_fresh_consistent_names(): void
    {
        $out = bcb_op_type2("<?php function f(\$alpha, \$beta) { return \$alpha + \$beta + \$alpha; }");

        self::assertStringNotContainsString('$alpha', $out);
        self::assertStringNotContainsString('$beta', $out);
        self::assertStringContainsString('$_v0', $out);
        self::assertStringContainsString('$_v1', $out);
    }

    #[Test]
    public function type3_inserts_exactly_one_statement(): void
    {
        $out = bcb_op_type3(self::SRC);

        self::assertSame(1, substr_count($out, 'BCB-PHP Type-3 insertion'));
    }

    #[Test]
    public function type1_changes_layout_but_keeps_the_code(): void
    {
        $out = bcb_op_type1(self::SRC);

        self::assertNotSame(self::SRC, $out);
        self::assertStringContainsString('function f(int $a, bool $b): float', $out);
    }

    #[Test]
    public function every_registered_operator_is_callable(): void
    {
        foreach (bcb_operators() as $name => $fn) {
            self::assertIsCallable($fn, "operator {$name} must be callable");
        }
    }
}
