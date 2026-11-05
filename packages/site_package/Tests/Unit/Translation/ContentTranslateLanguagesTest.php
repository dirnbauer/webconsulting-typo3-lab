<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\Command\ContentTranslateCommand;

final class ContentTranslateLanguagesTest extends TestCase
{
    private const array SITE = ['de' => 1, 'zh' => 2, 'hu' => 3];

    public function testEmptyMeansEveryLanguage(): void
    {
        self::assertSame(self::SITE, ContentTranslateCommand::resolveLanguages('', self::SITE));
        self::assertSame(self::SITE, ContentTranslateCommand::resolveLanguages(' , ', self::SITE));
    }

    public function testIsoCodes(): void
    {
        self::assertSame(['de' => 1], ContentTranslateCommand::resolveLanguages('de', self::SITE));
        self::assertSame(['de' => 1, 'hu' => 3], ContentTranslateCommand::resolveLanguages('hu, DE', self::SITE));
    }

    public function testLanguageIds(): void
    {
        self::assertSame(['de' => 1, 'zh' => 2], ContentTranslateCommand::resolveLanguages('1,2', self::SITE));
    }

    public function testCodesAndIdsMixedInSiteOrder(): void
    {
        self::assertSame(['zh' => 2, 'hu' => 3], ContentTranslateCommand::resolveLanguages('3,zh,hu', self::SITE));
    }

    public function testUnknownLanguageListsTheSitesLanguages(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The site has no language fr, 4 (it has de (1), zh (2), hu (3)).');
        ContentTranslateCommand::resolveLanguages('de,fr,4', self::SITE);
    }

    public function testTheDefaultLanguageIsNoTarget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ContentTranslateCommand::resolveLanguages('0', ['de' => 1]);
    }
}
