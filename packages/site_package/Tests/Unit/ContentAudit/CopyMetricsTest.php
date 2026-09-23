<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\ContentAudit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\ContentAudit\CopyMetrics;
use Webconsulting\SitePackage\ContentAudit\FieldRole;

final class CopyMetricsTest extends TestCase
{
    public function testPlainTextTurnsBlocksIntoLinesAndDecodesEntities(): void
    {
        self::assertSame(
            "Plans & pricing\nOne item\nTwo items",
            CopyMetrics::plainText('<h2>Plans &amp; pricing</h2><ul><li>One item</li><li>Two&nbsp;items</li></ul>')
        );
    }

    public function testSentencesKeepAbbreviationsPricesAndGermanDatesTogether(): void
    {
        self::assertSame(
            ['Use a preset, e.g. Aurora.', 'It costs €49.', 'Das Camp war am 11. September 2026 in München.'],
            CopyMetrics::sentences('Use a preset, e.g. Aurora. It costs €49. Das Camp war am 11. September 2026 in München.')
        );
    }

    public function testGermanOrdinalRangesStayOneSentence(): void
    {
        self::assertCount(1, CopyMetrics::sentences('Das Camp fand vom 11. bis 13. September in Puchheim statt.'));
    }

    public function testNumbersWithSeparatorsAreOneWord(): void
    {
        self::assertSame(['21,6', 'QoQ'], CopyMetrics::words('+21,6 % QoQ'));
        self::assertSame(['Ab', '1.490', 'im', 'Jahr'], CopyMetrics::words('Ab 1.490 € im Jahr'));
        self::assertSame(['It', 'costs', '1,490', 'a', 'year'], CopyMetrics::words('It costs €1,490 a year.'));
    }

    public function testLinesWithoutPunctuationCountAsSentences(): void
    {
        self::assertCount(3, CopyMetrics::sentences("First item\nSecond item\nThird item"));
    }

    public function testMeasureReportsLix(): void
    {
        // 5 words, 2 sentences, 2 words longer than six letters: 2.5 + 100 * 2/5.
        $metrics = CopyMetrics::measure('Pick a theme. Publish everything.');

        self::assertSame(5, $metrics['words']);
        self::assertSame(2, $metrics['sentences']);
        self::assertSame(2, $metrics['longWords']);
        self::assertSame(42.5, $metrics['lix']);
        self::assertSame(3, $metrics['maxSentenceWords']);
    }

    public function testEmptyTextMeasuresZero(): void
    {
        $metrics = CopyMetrics::measure('');

        self::assertSame(0, $metrics['words']);
        self::assertSame(0, $metrics['sentences']);
        self::assertSame(0.0, $metrics['lix']);
    }

    /**
     * @return iterable<string, array{string, string, FieldRole}>
     */
    public static function roles(): iterable
    {
        yield 'header' => ['header', 'tt_content', FieldRole::Headline];
        yield 'button text' => ['primary_button_text', 'tt_content', FieldRole::Button];
        yield 'badge' => ['badge_text', 'tt_content', FieldRole::Eyebrow];
        yield 'subheadline' => ['subheadline', 'tt_content', FieldRole::Lead];
        yield 'card description' => ['description', 'feature_cards_items', FieldRole::Card];
        yield 'faq answer' => ['answer', 'faq_items', FieldRole::FaqAnswer];
        yield 'bodytext' => ['bodytext', 'tt_content', FieldRole::Body];
        yield 'icon is skipped' => ['icon', 'tt_content', FieldRole::Skip];
        yield 'variant is skipped' => ['hero_variant', 'tt_content', FieldRole::Skip];
        yield 'link text is a button' => ['link_text', 'tt_content', FieldRole::Button];
        yield 'page title' => ['title', 'pages', FieldRole::PageTitle];
        yield 'meta description' => ['description', 'pages', FieldRole::Meta];
        yield 'alt text' => ['alternative', 'sys_file_reference', FieldRole::Alt];
        yield 'job title is not a headline' => ['author_title', 'tt_content', FieldRole::Other];
        yield 'chart data is skipped' => ['chart_data', 'tt_content', FieldRole::Skip];
    }

    #[DataProvider('roles')]
    public function testFieldRoles(string $field, string $table, FieldRole $expected): void
    {
        self::assertSame($expected, FieldRole::fromField($field, $table));
    }
}
