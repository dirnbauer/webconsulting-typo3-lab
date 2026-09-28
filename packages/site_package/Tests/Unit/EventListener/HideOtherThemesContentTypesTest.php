<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\EventListener;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\EventListener\HideOtherThemesContentTypes;

final class HideOtherThemesContentTypesTest extends TestCase
{
    private const array TYPES = [
        'text', 'textmedia', 'desiderio_hero', 'desiderio_faq', 'astryx_typo3_hero', 'astryx_typo3_faq', 'innesto_gallery',
    ];

    #[Test]
    public function aDesiderioSiteOffersNoAstryxTypes(): void
    {
        self::assertSame(
            ['astryx_typo3_hero', 'astryx_typo3_faq'],
            HideOtherThemesContentTypes::removedTypes(['webconsulting/solr-defaults', 'webconsulting/desiderio-content-elements', 'webconsulting/innesto'], self::TYPES),
        );
    }

    #[Test]
    public function anAstryxSiteOffersNoDesiderioTypes(): void
    {
        self::assertSame(
            ['desiderio_hero', 'desiderio_faq'],
            HideOtherThemesContentTypes::removedTypes(['webconsulting/astryx-typo3', 'webconsulting/astryx-typo3-search'], self::TYPES),
        );
    }

    #[Test]
    public function aSiteWithoutOrWithBothThemesKeepsEverything(): void
    {
        self::assertSame([], HideOtherThemesContentTypes::removedTypes(['webconsulting/site-package-search'], self::TYPES));
        self::assertSame([], HideOtherThemesContentTypes::removedTypes(['webconsulting/desiderio', 'webconsulting/astryx-typo3'], self::TYPES));
    }
}
