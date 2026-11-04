<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\Translation\SiteTranslator;

/**
 * Chart elements keep their rows twice: a `Label | Value` list for the editor
 * and the same rows as JSON for the canvas. The JSON labels are translated
 * too, or a German page draws English month names.
 */
final class SiteTranslatorJsonLabelsTest extends TestCase
{
    public function testLabelsOfChartRowsAreTranslatedAndValuesKept(): void
    {
        $memory = ['January' => 'Jänner', 'Manufacturing delay' => 'Verzögerung in der Herstellung'];
        $translated = SiteTranslator::translateJsonLabels(
            '[{"label":"January","value":15},{"label":"Manufacturing delay","value":197,"color":"chart-2"}]',
            static fn(string $label): string => $memory[$label] ?? $label,
        );

        self::assertSame(
            '[{"label":"Jänner","value":15},{"label":"Verzögerung in der Herstellung","value":197,"color":"chart-2"}]',
            $translated,
        );
    }

    public function testUnchangedRowsKeepTheStoredJson(): void
    {
        $json = '[ {"label": "2026", "value": 1} ]';

        self::assertSame($json, SiteTranslator::translateJsonLabels($json, static fn(string $label): string => $label));
    }

    public function testJsonThatIsNoRowListIsLeftToTheCaller(): void
    {
        $identity = static fn(string $label): string => strtoupper($label);

        self::assertNull(SiteTranslator::translateJsonLabels('{"label":"x"}', $identity));
        self::assertNull(SiteTranslator::translateJsonLabels('[{"name":"x"}]', $identity));
        self::assertNull(SiteTranslator::translateJsonLabels('[1, 2]', $identity));
        self::assertNull(SiteTranslator::translateJsonLabels('[', $identity));
        self::assertNull(SiteTranslator::translateJsonLabels('[]', $identity));
    }
}
