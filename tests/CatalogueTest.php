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

use function file_exists;
use function file_put_contents;
use function implode;
use function unlink;

use LucianoPereira\PhpcpdNext\Exceptions\MissingStringException;
use LucianoPereira\PhpcpdNext\Strings\Catalogue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** How the catalogue behaves at its edges, where a translation is wrong or unfinished. */
#[CoversClass(Catalogue::class)]
final class CatalogueTest extends TestCase
{
    private const string PARTIAL = __DIR__ . '/../locale/zz.php';

    protected function tearDown(): void
    {
        if (file_exists(self::PARTIAL)) {
            unlink(self::PARTIAL);
        }
    }

    #[Test]
    public function anUnfinishedTranslationFallsBackPerKeyRatherThanFailing(): void
    {
        // A translator who has done one sentence should be able to use the file.
        // Requiring all of them before anything works is how translations do not
        // get started.
        file_put_contents(self::PARTIAL, "<?php\n\nreturn ['report' => ['clones' => ['none' => 'Ingen kodekloner fundet.']]];\n");

        $catalogue = new Catalogue('zz');

        self::assertSame('Ingen kodekloner fundet.', $catalogue->get('report.clones.none'));
        self::assertSame(
            'Consider extracting the shared lines into a reusable method, class, or trait.',
            $catalogue->get('advise.clone.extract'),
            'a key the translation has not reached reads as English rather than stopping the run',
        );
    }

    #[Test]
    public function aMisspelledLanguageIsRefusedAndTheRealOnesNamed(): void
    {
        // Serving English for `--language=engllish` makes a typo look like a
        // working run. The list is derived, not pinned: a test that must be
        // edited to add a language ends up edited to agree with a bug.
        $this->expectException(MissingStringException::class);
        $this->expectExceptionMessage('No catalogue for language "engllish". Available: ' . implode(', ', Catalogue::available()) . '.');

        new Catalogue('engllish');
    }

    #[Test]
    public function aKeyMissingEverywhereRaisesRatherThanPrintingItself(): void
    {
        $this->expectException(MissingStringException::class);
        $this->expectExceptionMessage('No string "report.nope" in the en catalogue.');

        (new Catalogue())->get('report.nope');
    }

    #[Test]
    public function onlyTheParametersGivenAreSubstituted(): void
    {
        // An unrecognised `:word` survives as text. A translation that mentions
        // a colon, or names a placeholder that no longer exists, should degrade
        // to something visible rather than to a hole in the sentence.
        file_put_contents(self::PARTIAL, "<?php\n\nreturn ['t' => 'a :known and a :unknown'];\n");

        self::assertSame('a yes and a :unknown', (new Catalogue('zz'))->get('t', ['known' => 'yes']));
    }

    #[Test]
    public function aNonStringLeafIsRejectedWhenTheFileLoads(): void
    {
        // Not where it prints. `1.5` would otherwise become a message.
        file_put_contents(self::PARTIAL, "<?php\n\nreturn ['n' => 1.5];\n");

        $this->expectException(MissingStringException::class);

        new Catalogue('zz');
    }
}
