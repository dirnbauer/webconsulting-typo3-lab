<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\Translation\SiteTranslator;

/**
 * DataHandler takes one inlineLocalizeSynchronize per record and command map.
 * A record whose translation lacks both an image and a collection item needs
 * a round for each field, or the second command silently replaces the first.
 */
final class SiteTranslatorSynchronizeRoundsTest extends TestCase
{
    public function testARecordWithTwoFieldsGetsOneRoundPerField(): void
    {
        $rounds = SiteTranslator::synchronizeRounds([
            'tt_content' => [136040 => ['images', 'product_gallery_highlights'], 136041 => ['items']],
        ], 2);

        self::assertCount(2, $rounds);
        self::assertSame('images', $rounds[0]['tt_content'][136040]['inlineLocalizeSynchronize']['field']);
        self::assertSame('items', $rounds[0]['tt_content'][136041]['inlineLocalizeSynchronize']['field']);
        self::assertSame('product_gallery_highlights', $rounds[1]['tt_content'][136040]['inlineLocalizeSynchronize']['field']);
        self::assertArrayNotHasKey(136041, $rounds[1]['tt_content']);
    }

    public function testEveryCommandSynchronizesIntoTheRequestedLanguage(): void
    {
        $rounds = SiteTranslator::synchronizeRounds(['pages' => [12 => ['media']]], 3);

        self::assertSame(
            [['pages' => [12 => ['inlineLocalizeSynchronize' => ['field' => 'media', 'language' => 3, 'action' => 'synchronize']]]]],
            $rounds
        );
    }

    public function testAFieldNamedTwiceIsSynchronizedOnce(): void
    {
        $rounds = SiteTranslator::synchronizeRounds(['tt_content' => [7 => ['images', 'images']]], 1);

        self::assertCount(1, $rounds);
    }

    public function testNothingToSynchronizeMeansNoRounds(): void
    {
        self::assertSame([], SiteTranslator::synchronizeRounds([], 2));
    }
}
