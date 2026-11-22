<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\ViewHelpers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\ViewHelpers\StageWall\CellViewHelper;

/**
 * The plan goes onto the element's own tags: no wrapper, nothing moves.
 */
final class StageWallCellViewHelperTest extends TestCase
{
    private const ATTRIBUTES = ['data-sw' => 'tile', 'data-sw-col' => '1', 'data-sw-span' => '5', 'data-sw-dock' => ''];

    #[Test]
    public function marksTheAnchorsAndTheRootTag(): void
    {
        $html = "\n<a id=\"c12\"></a><a id=\"pricing\"></a><section class=\"desiderio-section\" data-d-section=\"true\"><div><a id=\"inner\"></a></div></section>";

        self::assertSame(
            "\n<a id=\"c12\" data-sw-anchor=\"tile\" data-sw-col=\"1\" data-sw-span=\"5\"></a>"
            . '<a id="pricing" data-sw-anchor="tile" data-sw-col="1" data-sw-span="5"></a>'
            . '<section class="desiderio-section" data-d-section="true" data-sw="tile" data-sw-col="1" data-sw-span="5" data-sw-dock><div><a id="inner"></a></div></section>',
            CellViewHelper::annotate($html, self::ATTRIBUTES)
        );
    }

    #[Test]
    public function looksInsideTheVisualEditorsWrapper(): void
    {
        $html = '<ve-content-element id="tt_content:12" uid="12"><a id="c12"></a><!-- x --><section class="a"></section></ve-content-element>';

        self::assertSame(
            '<ve-content-element id="tt_content:12" uid="12"><a id="c12" data-sw-anchor="tile" data-sw-col="1" data-sw-span="5"></a><!-- x --><section class="a" data-sw="tile" data-sw-col="1" data-sw-span="5" data-sw-dock></section></ve-content-element>',
            CellViewHelper::annotate($html, self::ATTRIBUTES)
        );
    }

    #[Test]
    public function escapesValuesAndIgnoresOtherAttributes(): void
    {
        self::assertSame(
            '<div data-sw="&quot;&gt;"></div>',
            CellViewHelper::annotate('<div></div>', ['data-sw' => '">', 'onclick' => 'x', 'data-other' => 'y'])
        );
        self::assertSame('<div></div>', CellViewHelper::annotate('<div></div>', []));
        self::assertSame('plain text', CellViewHelper::annotate('plain text', self::ATTRIBUTES));
    }
}
