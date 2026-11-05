<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\Translation\TranslationFilter;

final class TranslationFilterTest extends TestCase
{
    public function testNoFilterIncludesEveryPageAndTable(): void
    {
        $filter = TranslationFilter::fromOptions('', false, '');

        self::assertFalse($filter->isActive());
        self::assertTrue($filter->includesPage([1296, 1291, 1290]));
        self::assertTrue($filter->includesTable('pages'));
        self::assertTrue($filter->includesTable('tx_news_domain_model_news'));
        self::assertSame('whole site', $filter->describe());
    }

    public function testListedPagesOnlyWithoutRecursive(): void
    {
        $filter = TranslationFilter::fromOptions('1296, 1297', false, '');

        self::assertTrue($filter->isActive());
        self::assertTrue($filter->includesPage([1296, 1291, 1290]));
        self::assertTrue($filter->includesPage([1297, 1291, 1290]));
        self::assertFalse($filter->includesPage([1312, 1296, 1291, 1290]), 'a subpage is not included without --recursive');
        self::assertFalse($filter->includesPage([1290]));
    }

    public function testRecursiveIncludesTheWholeSubtree(): void
    {
        $filter = TranslationFilter::fromOptions('1291', true, '');

        self::assertTrue($filter->includesPage([1291, 1290]));
        self::assertTrue($filter->includesPage([1296, 1291, 1290]));
        self::assertTrue($filter->includesPage([1312, 1296, 1291, 1290]));
        self::assertFalse($filter->includesPage([1304, 1290]), 'a sibling subtree stays out');
        self::assertFalse($filter->includesPage([1290]), 'the parent stays out');
        self::assertSame('pages 1291 and below', $filter->describe());
    }

    public function testTablesLimitTheRecordKinds(): void
    {
        $filter = TranslationFilter::fromOptions('', false, 'tt_content,tx_news_domain_model_news');

        self::assertTrue($filter->isActive());
        self::assertTrue($filter->includesPage([505]), 'a table filter alone keeps every page');
        self::assertFalse($filter->includesTable('pages'));
        self::assertTrue($filter->includesTable('tt_content'));
        self::assertTrue($filter->includesTable('tx_news_domain_model_news'));
        self::assertFalse($filter->includesTable('sys_category'));
    }

    public function testPagesAndTablesCombine(): void
    {
        $filter = TranslationFilter::fromOptions('987', true, 'pages');

        self::assertTrue($filter->includesPage([988, 987, 505]));
        self::assertFalse($filter->includesPage([600, 505]));
        self::assertTrue($filter->includesTable('pages'));
        self::assertFalse($filter->includesTable('tt_content'));
        self::assertSame('pages 987 and below · tables pages', $filter->describe());
    }

    public function testUnknownTablesAreReported(): void
    {
        $filter = TranslationFilter::fromOptions('', false, 'pages,tt_contnet,sys_category');

        self::assertSame(['tt_contnet'], $filter->unknownTables(['sys_category']));
        self::assertSame(['tt_contnet', 'sys_category'], $filter->unknownTables([]));
    }

    public function testDuplicatesAndBlanksAreDropped(): void
    {
        $filter = TranslationFilter::fromOptions('12,,12, 13', false, 'pages, ,pages');

        self::assertSame([12, 13], $filter->pages);
        self::assertSame(['pages'], $filter->tables);
    }

    public function testRejectsAPageThatIsNotAUid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1791900001);
        TranslationFilter::fromOptions('/pricing', false, '');
    }

    public function testRejectsRecursiveWithoutPages(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1791900002);
        TranslationFilter::fromOptions('', true, '');
    }
}
