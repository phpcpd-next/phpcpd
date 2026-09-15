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

use function array_values;
use function basename;
use function count;
use function file;
use function str_contains;
use function trim;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\Phpcpd;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Each occurrence of a clone is measured over the lines it actually covers.
 *
 * The two fixtures hold the same statements and therefore the same significant
 * tokens: comments and blank lines are not tokens, so `padded.php` differs from
 * `compact.php` only in things the matchers cannot see. The copies must be found
 * as one clone, and they must **not** be reported at one length -- `compact.php`
 * occupies 49 lines where `padded.php` occupies 130.
 *
 * The assertions are on source content rather than on line numbers, because a
 * number pinned from a run only says the code still does what it did. Both
 * occurrences run from `declare(strict_types=1);` to the function's closing
 * brace; that is
 * checkable by reading the fixtures, and it stays true if the engine later
 * chooses a different but still correct span.
 */
#[CoversClass(CodeClone::class)]
final class CloneExtentTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/fixtures/extents';

    /** The clone whose occurrences are the two different files. */
    private function crossFileClone(): CodeClone
    {
        $map = Phpcpd::detect(
            paths: [self::FIXTURES],
            minLines: 5,
            minTokens: 70,
            algorithm: 'unified',
        );

        foreach ($map->clones() as $clone) {
            $names = [];

            foreach ($clone->files() as $file) {
                $names[basename($file->name)] = true;
            }

            if (count($names) > 1) {
                return $clone;
            }
        }

        self::fail('the two fixtures are token-identical and must be found as one clone');
    }

    /**
     * The two occurrences, as (basename, line count) — **read off the fixture**
     * rather than pinned from a run.
     *
     * These used to be the literals 49 and 130, and both were one short. A
     * closing brace that opens a line carries no line number of its own and was
     * dated to the line above it, so every span ending on one was reported a
     * line shy; the numbers here recorded that. The sibling test could not catch
     * it either, because the two candidate last lines are `    }` and `}` and
     * both trim to the same string.
     *
     * So the span is derived from the file: first line of real content through
     * last, which is what this test's own docblock says is checkable by reading
     * the fixtures.
     *
     * @return list<array{0: string, 1: int}>
     */
    public static function occurrences(): array
    {
        $occurrences = [];

        foreach (['compact.php', 'padded.php'] as $name) {
            $lines = array_values((array) file(self::FIXTURES . '/' . $name));
            $first = 0;
            $last  = count($lines);

            foreach ($lines as $at => $line) {
                if (str_contains((string) $line, 'declare(strict_types=1);')) {
                    $first = $at + 1;

                    break;
                }
            }

            while ($last > 0 && trim((string) $lines[$last - 1]) === '') {
                --$last;
            }

            $occurrences[] = [$name, $last - $first + 1];
        }

        return $occurrences;
    }

    #[Test]
    #[DataProvider('occurrences')]
    public function each_occurrence_spans_its_own_lines(string $file, int $expected): void
    {
        $clone = $this->crossFileClone();

        foreach ($clone->files() as $occurrence) {
            if (basename($occurrence->name) !== $file) {
                continue;
            }

            self::assertSame(
                $expected,
                $occurrence->numberOfLines,
                $file . ' spans ' . $expected . ' lines of its own',
            );

            return;
        }

        self::fail($file . ' is not among the clone\'s occurrences');
    }

    #[Test]
    #[DataProvider('occurrences')]
    public function each_occurrence_ends_on_the_statement_it_really_ends_on(string $file, int $expected): void
    {
        $clone = $this->crossFileClone();

        foreach ($clone->files() as $occurrence) {
            if (basename($occurrence->name) !== $file) {
                continue;
            }

            $lines = (array) file($occurrence->name);
            $first = (string) $lines[$occurrence->startLine - 1];
            $last  = (string) $lines[$occurrence->lastLine($clone->numberOfLines()) - 1];

            self::assertStringContainsString('declare(strict_types=1);', $first);

            // The closing brace, not `return $out;` before it. Both copies end
            // with that brace and always did; the matcher could not see it,
            // because a single-character token carries no line number and was
            // dropped for want of one. Now that it counts them, the run reaches
            // the end of the function it duplicates.
            self::assertSame('}', trim($last), 'the occurrence must end where its copy ends');

            return;
        }

        self::fail($file . ' is not among the clone\'s occurrences');
    }

    #[Test]
    public function the_padded_copy_is_not_reported_at_the_compact_copy_s_length(): void
    {
        $clone       = $this->crossFileClone();
        $occurrences = array_values($clone->files());
        $spans       = [];

        foreach ($occurrences as $occurrence) {
            $spans[basename($occurrence->name)] = $occurrence->numberOfLines;
        }

        self::assertNotSame(
            $spans['compact.php'],
            $spans['padded.php'],
            'one length for both is the defect: the copies do not occupy the same number of lines',
        );
    }
}
