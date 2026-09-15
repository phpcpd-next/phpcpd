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

use function explode;
use function array_slice;
use function file;
use function implode;
use function md5;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A clone's excerpt, and the file-read cache behind it.
 *
 * The cache exists because a clone's identity is the md5 of its own text, so
 * constructing one reads its first file — and on a corpus with one heavily
 * duplicated file that is the same read thousands of times (measured: 6,961
 * clones sharing one 5,782-line file). It holds only a couple of entries, so the
 * case that matters is eviction: reading more distinct files than the cache can
 * hold, interleaved, must still give every clone the right lines.
 */
#[CoversClass(CodeClone::class)]
final class CodeCloneTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/fixtures/type3';

    /** The excerpt a clone should produce, computed without the cache. */
    private static function excerpt(string $path, int $startLine, int $numberOfLines): string
    {
        return implode('', array_slice((array) file($path), $startLine - 1, $numberOfLines));
    }

    #[Test]
    public function a_clone_excerpts_the_lines_it_names(): void
    {
        $path  = self::FIXTURES . '/clone_base.php';
        $clone = new CodeClone(new CodeCloneFile($path, 3), new CodeCloneFile($path, 3), 6, 40);

        self::assertSame(self::excerpt($path, 3, 6), $clone->lines());
    }

    #[Test]
    public function reading_more_files_than_the_cache_holds_still_gives_every_clone_its_own_lines(): void
    {
        $files = [
            self::FIXTURES . '/clone_base.php',
            self::FIXTURES . '/clone_exact_copy.php',
            self::FIXTURES . '/clone_gapped.php',
            self::FIXTURES . '/clone_wide_gap.php',
        ];

        // Four distinct files against a two-entry cache, interleaved so the
        // eviction path runs repeatedly and an entry that was evicted is asked
        // for again.
        $clones = [];

        foreach ([0, 1, 2, 3, 0, 2, 1, 3, 0] as $index) {
            $path      = $files[$index];
            $clones[] = [$path, new CodeClone(new CodeCloneFile($path, 4), new CodeCloneFile($path, 4), 5, 30)];
        }

        foreach ($clones as [$path, $clone]) {
            self::assertSame(
                self::excerpt($path, 4, 5),
                $clone->lines(),
                'a cache eviction handed a clone another file\'s lines',
            );
        }
    }

    #[Test]
    public function the_identity_of_a_clone_is_the_hash_of_its_own_text(): void
    {
        // Identity is what clone classes merge on, so a cache that returned stale
        // lines would silently merge unrelated clones. Two clones over the same
        // text must agree; one over different text must not.
        $path = self::FIXTURES . '/clone_base.php';

        $first  = new CodeClone(new CodeCloneFile($path, 3), new CodeCloneFile($path, 3), 6, 40);
        $second = new CodeClone(new CodeCloneFile($path, 3), new CodeCloneFile($path, 3), 6, 40);
        $other  = new CodeClone(new CodeCloneFile($path, 10), new CodeCloneFile($path, 10), 6, 40);

        self::assertSame(md5(self::excerpt($path, 3, 6)), $first->id());
        self::assertSame($first->id(), $second->id());
        self::assertNotSame($first->id(), $other->id());
    }

    #[Test]
    public function editing_a_file_and_scanning_again_in_one_process_does_not_read_stale_lines(): void
    {
        // The cache is static, so it outlives a scan. An embedder — a test suite
        // asserting on duplication, a watcher, a long-running command — can edit
        // a file and scan again in the same process, and a path-keyed cache would
        // hand the second scan the first scan's bytes and build clone identities
        // out of code that is no longer there.
        $path = sys_get_temp_dir() . '/bcb-clone-cache-' . getmypid() . '.php';

        try {
            file_put_contents($path, "<?php\n\$a = 1;\n\$b = 2;\n\$c = 3;\n");
            $before = new CodeClone(new CodeCloneFile($path, 2), new CodeCloneFile($path, 2), 3, 12);

            self::assertStringContainsString('$a = 1;', $before->lines());

            // Change both the contents and the size, and make sure the
            // modification time really moved even on a coarse clock.
            file_put_contents($path, "<?php\n\$z = 99; // rewritten\n\$y = 98;\n\$x = 97;\n");
            touch($path, time() + 2);

            $after = new CodeClone(new CodeCloneFile($path, 2), new CodeCloneFile($path, 2), 3, 12);

            self::assertStringContainsString('$z = 99;', $after->lines());
            self::assertStringNotContainsString('$a = 1;', $after->lines());
            self::assertNotSame($before->id(), $after->id(), 'a stale read would give the two clones one identity');
        } finally {
            @unlink($path);
        }
    }

    /**
     * A clone whose source cannot be read is still itself.
     *
     * Identity is a digest of the fragment's own text, and `md5('')` is not an
     * identity: it is the same value for every clone whose text could not be
     * read. `CodeCloneMap` keys on it, so two unrelated findings came back as
     * one naming four unrelated sites — which does not lose a finding, it
     * invents one. A fragment with no readable text is identified by where it
     * is instead, because two findings that cannot be shown to be the same
     * should be kept apart.
     */
    #[Test]
    public function unreadable_clones_do_not_collapse_into_one(): void
    {
        $a = new CodeClone(
            new CodeCloneFile(self::FIXTURES . '/no-such-alpha.php', 10),
            new CodeCloneFile(self::FIXTURES . '/no-such-beta.php', 10),
            5,
            40,
        );

        $b = new CodeClone(
            new CodeCloneFile(self::FIXTURES . '/no-such-gamma.php', 99),
            new CodeCloneFile(self::FIXTURES . '/no-such-delta.php', 99),
            7,
            60,
        );

        self::assertNotSame($a->id(), $b->id(), 'no text is not the same text');

        $map = new CodeCloneMap();
        $map->add($a);
        $map->add($b);

        self::assertCount(2, $map->clones());
    }

    #[Test]
    public function lines_indents_every_line_it_is_asked_to(): void
    {
        // This assertion used to run the other way. `lines()` memoized the
        // finished string, and the constructor calls it with no indent to
        // compute the clone's id — so the un-indented copy was already cached
        // by the time anyone asked for an indented one, and `Log\Text` asked
        // for four spaces on every --verbose clone and never once got them.
        //
        // The defect was recorded here rather than fixed, deliberately, "so a
        // future fix has to come past it". It came past it: the memo holds the
        // excerpt's lines now and the indent is applied per call, so the id and
        // the report can ask the same clone for different things.
        $path  = self::FIXTURES . '/clone_base.php';
        $clone = new CodeClone(new CodeCloneFile($path, 3), new CodeCloneFile($path, 3), 4, 30);

        self::assertStringStartsWith('>> ', $clone->lines('>> '));

        // Asking twice gives the same answer, and asking for a different indent
        // gives a different one — the memo is the source text, not the result.
        self::assertSame($clone->lines('>> '), $clone->lines('>> '));
        self::assertSame(self::excerpt($path, 3, 4), $clone->lines());

        // A blank line stays blank. Indenting one emits the indent and nothing
        // else, which is trailing whitespace.
        foreach (explode(PHP_EOL, $clone->lines('>> ')) as $line) {
            self::assertNotSame('>> ', $line, 'a blank line was given a prefix and nothing to prefix');
        }
    }

    #[Test]
    public function an_occurrence_covers_its_own_lines_rather_than_the_clone_length(): void
    {
        $path = self::FIXTURES . '/clone_base.php';

        // A clone whose lead spans 6 lines and whose second occurrence spans 4:
        // token-identical, separated by a different number of comment or blank
        // lines. Before occurrences carried their own extent, the second was
        // recorded as covering 6 lines, two of which it does not reach.
        $lead   = new CodeCloneFile($path, 10, 6);
        $second = new CodeCloneFile($path, 40, 4);

        self::assertSame(15, $lead->lastLine(6));
        self::assertSame(43, $second->lastLine(6), 'its own 4 lines, not the clone\'s 6');
    }

    #[Test]
    public function an_unmeasured_occurrence_falls_back_to_the_clone_length(): void
    {
        // Rabin-Karp cannot measure its earlier occurrence, and says so with
        // null rather than guessing. The clone's length then stands in, which is
        // what every occurrence got before any of them were measured.
        $unmeasured = new CodeCloneFile(self::FIXTURES . '/clone_base.php', 10);

        self::assertNull($unmeasured->numberOfLines);
        self::assertSame(15, $unmeasured->lastLine(6));
    }
}
