<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\StartPage;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\Command\ApplyStageWallCommand;
use Webconsulting\SitePackage\StartPage\StageWallPlan;

/**
 * "Stage and Wall" places elements by the editor's widths, never by their
 * position: any order and any number of elements gives whole rows.
 */
final class StageWallPlanTest extends TestCase
{
    #[Test]
    public function placesTheSeededStartPageLikeTheDesign(): void
    {
        $elements = [];
        foreach (ApplyStageWallCommand::DESIGN as $index => [$type, $layout]) {
            $elements[] = ['uid' => 100 + $index, 'type' => $type, 'layout' => $layout];
        }
        $plan = StageWallPlan::plan($elements);

        self::assertSame('film', $plan['stage']);
        self::assertSame(['data-sw' => 'hero'], $plan['cells'][100]);
        self::assertSame(['data-sw' => 'film'], $plan['cells'][101]);
        // Problems | theme steps, the first row after the three stage rows.
        self::assertSame(['data-sw' => 'tile', 'data-sw-col' => '1', 'data-sw-span' => '5', 'data-sw-size' => 'narrow', 'data-sw-row' => '4', 'data-sw-lead' => 'first'], $plan['cells'][102]);
        self::assertSame(['data-sw' => 'tile', 'data-sw-col' => '6', 'data-sw-span' => '7', 'data-sw-size' => 'medium', 'data-sw-row' => '4', 'data-sw-lead' => 'row'], $plan['cells'][103]);
        // Open source, full width, with its call to action docked under it.
        self::assertSame(['data-sw' => 'tile', 'data-sw-col' => '1', 'data-sw-span' => '12', 'data-sw-size' => 'full', 'data-sw-row' => '5', 'data-sw-dock' => ''], $plan['cells'][104]);
        self::assertSame(['data-sw' => 'connector', 'data-sw-col' => '1', 'data-sw-span' => '12', 'data-sw-size' => 'full', 'data-sw-row' => '6'], $plan['cells'][105]);
        // Teams | pricing.
        self::assertSame('9', $plan['cells'][109]['data-sw-row']);
        self::assertSame(['1', '4'], [$plan['cells'][109]['data-sw-col'], $plan['cells'][109]['data-sw-span']]);
        self::assertSame(['5', '8'], [$plan['cells'][110]['data-sw-col'], $plan['cells'][110]['data-sw-span']]);
        // Strategy + connector | lab overview + connector.
        self::assertSame(['10', '1', '7'], [$plan['cells'][111]['data-sw-row'], $plan['cells'][111]['data-sw-col'], $plan['cells'][111]['data-sw-span']]);
        self::assertSame(['11', '1', '7'], [$plan['cells'][112]['data-sw-row'], $plan['cells'][112]['data-sw-col'], $plan['cells'][112]['data-sw-span']]);
        self::assertSame(['10', '8', '5'], [$plan['cells'][113]['data-sw-row'], $plan['cells'][113]['data-sw-col'], $plan['cells'][113]['data-sw-span']]);
        self::assertSame(['11', '8', '5'], [$plan['cells'][114]['data-sw-row'], $plan['cells'][114]['data-sw-col'], $plan['cells'][114]['data-sw-span']]);
        // Steps and the closing band.
        self::assertSame('12', $plan['cells'][115]['data-sw-row']);
        self::assertSame('13', $plan['cells'][116]['data-sw-row']);
        self::assertCount(17, $plan['cells']);
    }

    #[Test]
    public function aRowThatIsNotFullWidensItsLastTile(): void
    {
        $plan = StageWallPlan::plan([
            ['uid' => 1, 'type' => 'x', 'layout' => 205],
            ['uid' => 2, 'type' => 'x', 'layout' => 205],
            ['uid' => 3, 'type' => 'x', 'layout' => 0],
            ['uid' => 4, 'type' => 'x', 'layout' => 204],
        ]);

        self::assertSame('none', $plan['stage']);
        self::assertSame(['1', '5', '1'], [$plan['cells'][1]['data-sw-col'], $plan['cells'][1]['data-sw-span'], $plan['cells'][1]['data-sw-row']]);
        self::assertSame(['6', '7', '1'], [$plan['cells'][2]['data-sw-col'], $plan['cells'][2]['data-sw-span'], $plan['cells'][2]['data-sw-row']]);
        self::assertSame(['12', '2'], [$plan['cells'][3]['data-sw-span'], $plan['cells'][3]['data-sw-row']]);
        self::assertSame(['12', 'full', '3'], [$plan['cells'][4]['data-sw-span'], $plan['cells'][4]['data-sw-size'], $plan['cells'][4]['data-sw-row']]);
    }

    #[Test]
    public function aTileWithoutConnectorsSpansTheRowsOfItsNeighboursConnectors(): void
    {
        $plan = StageWallPlan::plan([
            ['uid' => 1, 'type' => StageWallPlan::HERO_TYPE, 'layout' => 0],
            ['uid' => 2, 'type' => 'x', 'layout' => 207],
            ['uid' => 3, 'type' => 'x', 'layout' => StageWallPlan::DOCKED],
            ['uid' => 4, 'type' => 'x', 'layout' => StageWallPlan::DOCKED],
            ['uid' => 5, 'type' => 'x', 'layout' => 205],
            ['uid' => 6, 'type' => 'x', 'layout' => 0],
        ]);

        self::assertSame('hero', $plan['stage']);
        self::assertSame(['3', '7'], [$plan['cells'][2]['data-sw-row'], $plan['cells'][2]['data-sw-span']]);
        self::assertArrayNotHasKey('data-sw-rows', $plan['cells'][2]);
        self::assertSame(['connector', '4', ''], [$plan['cells'][3]['data-sw'], $plan['cells'][3]['data-sw-row'], $plan['cells'][3]['data-sw-dock']]);
        self::assertSame(['connector', '5'], [$plan['cells'][4]['data-sw'], $plan['cells'][4]['data-sw-row']]);
        self::assertArrayNotHasKey('data-sw-dock', $plan['cells'][4]);
        self::assertSame(['3', '3', '8'], [$plan['cells'][5]['data-sw-row'], $plan['cells'][5]['data-sw-rows'], $plan['cells'][5]['data-sw-col']]);
        self::assertSame('6', $plan['cells'][6]['data-sw-row']);
    }

    #[Test]
    public function aFilmOnlyStandsOnTheStageRightAfterTheHero(): void
    {
        $plan = StageWallPlan::plan([
            ['uid' => 1, 'type' => StageWallPlan::FILM_TYPE, 'layout' => 0],
            ['uid' => 2, 'type' => StageWallPlan::HERO_TYPE, 'layout' => 0],
            ['uid' => 3, 'type' => 'cta', 'layout' => StageWallPlan::DOCKED],
        ]);

        self::assertSame('none', $plan['stage']);
        self::assertSame('tile', $plan['cells'][1]['data-sw']);
        self::assertSame('tile', $plan['cells'][2]['data-sw']);
        self::assertSame('connector', $plan['cells'][3]['data-sw']);
    }

    #[Test]
    public function aConnectorWithoutATileAboveIsATile(): void
    {
        $plan = StageWallPlan::plan([
            ['uid' => 1, 'type' => StageWallPlan::HERO_TYPE, 'layout' => 0],
            ['uid' => 2, 'type' => 'cta', 'layout' => StageWallPlan::DOCKED],
        ]);

        self::assertSame(['tile', '12', '3'], [$plan['cells'][2]['data-sw'], $plan['cells'][2]['data-sw-span'], $plan['cells'][2]['data-sw-row']]);
    }

    #[Test]
    public function rowsPastTheStylesheetFlowOnTheirOwn(): void
    {
        $elements = [];
        for ($uid = 1; $uid <= StageWallPlan::MAX_ROW + 2; $uid++) {
            $elements[] = ['uid' => $uid, 'type' => 'x', 'layout' => 0];
        }
        $plan = StageWallPlan::plan($elements);

        self::assertSame((string)StageWallPlan::MAX_ROW, $plan['cells'][StageWallPlan::MAX_ROW]['data-sw-row']);
        self::assertArrayNotHasKey('data-sw-row', $plan['cells'][StageWallPlan::MAX_ROW + 1]);
        self::assertSame('12', $plan['cells'][StageWallPlan::MAX_ROW + 1]['data-sw-span']);
    }

    #[Test]
    public function anEmptyPageHasNoStage(): void
    {
        self::assertSame(['stage' => 'none', 'cells' => []], StageWallPlan::plan([]));
    }
}
