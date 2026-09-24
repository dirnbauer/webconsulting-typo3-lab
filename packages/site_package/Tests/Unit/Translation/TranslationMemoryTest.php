<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Translation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\Translation\TranslationMemory;

final class TranslationMemoryTest extends TestCase
{
    public function testTranslatesTheSourceText(): void
    {
        $memory = new TranslationMemory('de', ['Get started free' => 'Kostenlos starten']);

        self::assertSame('Kostenlos starten', $memory->translate('Get started free'));
    }

    public function testMatchesSourcesThatDifferOnlyInWhitespace(): void
    {
        $memory = new TranslationMemory('de', ['<p>One line.</p><ul><li>Item</li></ul>' => '<p>Eine Zeile.</p><ul><li>Punkt</li></ul>']);

        self::assertSame(
            '<p>Eine Zeile.</p><ul><li>Punkt</li></ul>',
            $memory->translate("<p>One  line.</p>\n<ul>\n  <li>Item</li>\n</ul>\n")
        );
    }

    public function testLinksKeepPointingWhereTheCurrentSourcePoints(): void
    {
        $memory = new TranslationMemory('de', [
            '<p>See <a href="t3://page?uid=10">forms</a> and <a href="t3://page?uid=11">news</a>.</p>'
                => '<p><a href="t3://page?uid=11">News</a> und <a href="t3://page?uid=10">Formulare</a> ansehen.</p>',
        ]);

        self::assertSame(
            '<p><a href="t3://page?uid=21">News</a> und <a href="t3://page?uid=20">Formulare</a> ansehen.</p>',
            $memory->translate('<p>See <a href="t3://page?uid=20">forms</a> and <a href="t3://page?uid=21">news</a>.</p>')
        );
    }

    public function testReportsMissingTranslationsAsNull(): void
    {
        self::assertNull(new TranslationMemory('de')->translate('Book a call'));
    }

    public function testEmptySourceStaysEmpty(): void
    {
        self::assertSame('', new TranslationMemory('de')->translate(''));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function neutralTexts(): iterable
    {
        yield 'number' => ['244', true];
        yield 'percentage' => ['99.9 %', true];
        yield 'year range' => ['2024–2026', true];
        yield 'arrow' => ['→', true];
        yield 'amount' => ['€49', false];
        yield 'word' => ['Pro', false];
        yield 'number in markup' => ['<strong>15</strong>', true];
    }

    #[DataProvider('neutralTexts')]
    public function testTextWithoutLettersNeedsNoTranslationButAmountsDo(string $text, bool $neutral): void
    {
        self::assertSame($neutral, TranslationMemory::isLanguageNeutral($text));
        self::assertSame($neutral ? $text : null, new TranslationMemory('de')->translate($text));
    }

    public function testAnEntryForANeutralTextWins(): void
    {
        self::assertSame('99,9 %', new TranslationMemory('de', ['99.9 %' => '99,9 %'])->translate('99.9 %'));
    }

    public function testListsEntriesNoRecordAskedFor(): void
    {
        $memory = new TranslationMemory('de', ['Pricing' => 'Preise', 'Old headline' => 'Alte Überschrift']);
        $memory->translate('Pricing');

        self::assertSame(['Old headline'], $memory->unused());
    }

    public function testAMissingFileIsAnEmptyMemory(): void
    {
        $memory = TranslationMemory::fromFile(__DIR__ . '/does-not-exist.json', 'hu');

        self::assertSame(0, $memory->count());
        self::assertSame('hu', $memory->language);
    }

    public function testRejectsAFileWithoutStrings(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tm');
        self::assertIsString($path);
        file_put_contents($path, '{"language": "de"}');

        try {
            $this->expectException(\InvalidArgumentException::class);
            TranslationMemory::fromFile($path, 'de');
        } finally {
            unlink($path);
        }
    }
}
