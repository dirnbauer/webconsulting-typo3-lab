<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use Webconsulting\SitePackage\EventListener\CrossSiteLinkLanguage;

/**
 * A cross-site link takes the target site's language with the reader's
 * language code, or the target site's default language.
 */
final class CrossSiteLinkLanguageTest extends TestCase
{
    /**
     * @return iterable<string, array{0: list<array{id: int, locale: string}>, 1: string, 2: int}>
     */
    public static function cases(): iterable
    {
        $camp = [['id' => 0, 'locale' => 'de_DE'], ['id' => 1, 'locale' => 'en_US'], ['id' => 2, 'locale' => 'zh_CN'], ['id' => 3, 'locale' => 'hu_HU']];
        $astryx = [['id' => 0, 'locale' => 'en_US'], ['id' => 1, 'locale' => 'de_AT']];
        $englishOnly = [['id' => 0, 'locale' => 'en_US']];

        yield 'German reader, German default on the target' => [$camp, 'de_AT', 0];
        yield 'German reader, German as language 1 on the target' => [$astryx, 'de_AT', 1];
        yield 'Chinese reader finds Chinese' => [$camp, 'zh_CN', 2];
        yield 'Hungarian reader on an English-only site gets the default' => [$englishOnly, 'hu_HU', 0];
        yield 'English reader finds English as language 1' => [$camp, 'en_US', 1];
    }

    /**
     * @param list<array{id: int, locale: string}> $targetLanguages
     */
    #[Test]
    #[DataProvider('cases')]
    public function matchesByLanguageCode(array $targetLanguages, string $readerLocale, int $expected): void
    {
        $target = self::site('target', $targetLanguages);
        $reader = self::site('reader', [['id' => 0, 'locale' => 'en_US'], ['id' => 7, 'locale' => $readerLocale]]);

        self::assertSame($expected, CrossSiteLinkLanguage::matchingLanguage($target, $reader->getLanguageById(7))->getLanguageId());
    }

    /**
     * @param list<array{id: int, locale: string}> $languages
     */
    private static function site(string $identifier, array $languages): Site
    {
        return new Site($identifier, 1, [
            'base' => 'https://example.org/' . $identifier . '/',
            'languages' => array_map(static fn(array $language): array => [
                'languageId' => $language['id'],
                'locale' => $language['locale'],
                'base' => '/' . $language['id'] . '/',
                'title' => $language['locale'],
                'enabled' => true,
            ], $languages),
        ]);
    }
}
