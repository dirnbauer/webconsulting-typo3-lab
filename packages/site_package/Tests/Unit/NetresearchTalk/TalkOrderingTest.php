<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\NetresearchTalk;

use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\NetresearchTalk\TalkOrdering;

final class TalkOrderingTest extends TestCase
{
    public function testNothingMovesWhenTheWantedOrderIsInPlace(): void
    {
        // An editor's element (99) between ours does not count as disorder.
        self::assertSame([], TalkOrdering::moves([10, 99, 11, 12], [10, 11, 12]));
    }

    public function testCreatedElementsAreMovedIntoPayloadOrder(): void
    {
        // DataHandler puts each new element at the top, so a first run stores them reversed.
        self::assertSame(
            [['uid' => 10, 'after' => null], ['uid' => 11, 'after' => 10], ['uid' => 12, 'after' => 11]],
            TalkOrdering::moves([12, 11, 10], [10, 11, 12])
        );
    }

    public function testACollectionIsLinkedWhenItHoldsTheItemsInOrderWithSorting1ToN(): void
    {
        self::assertTrue(TalkOrdering::collectionLinked([['uid' => 5, 'sorting' => 1], ['uid' => 6, 'sorting' => 2]], [5, 6]));
        // Items created in this run still carry DataHandler's sorting.
        self::assertFalse(TalkOrdering::collectionLinked([['uid' => 6, 'sorting' => 128], ['uid' => 5, 'sorting' => 256]], [5, 6]));
        self::assertFalse(TalkOrdering::collectionLinked([['uid' => 5, 'sorting' => 2], ['uid' => 6, 'sorting' => 1]], [5, 6]));
        self::assertFalse(TalkOrdering::collectionLinked([['uid' => 5, 'sorting' => 1]], [5, 6]));
        self::assertFalse(TalkOrdering::collectionLinked([], []));
    }

    public function testLeftoversAreItemsThePayloadNoLongerNames(): void
    {
        self::assertSame([7], TalkOrdering::leftovers([['uid' => 5, 'sorting' => 1], ['uid' => 7, 'sorting' => 2]], [5]));
    }

    public function testACardFollowsTheFilmTranscriptOrTheFirstElement(): void
    {
        $withFilm = [
            ['uid' => 1, 'rowDescription' => ''],
            ['uid' => 2, 'rowDescription' => 'Seeded by sitepackage:films:seed (ai). A reseed replaces it.'],
            ['uid' => 3, 'rowDescription' => 'Seeded by sitepackage:films:seed (ai-transcript). A reseed replaces it.'],
            ['uid' => 4, 'rowDescription' => ''],
            ['uid' => 50, 'rowDescription' => 'nrtalk:hub-ai'],
        ];
        self::assertSame(3, TalkOrdering::cardAnchor($withFilm, 50));
        self::assertFalse(TalkOrdering::cardInPlace($withFilm, 50, 3));

        $withoutFilm = [['uid' => 50, 'rowDescription' => 'nrtalk:hub-ai'], ['uid' => 1, 'rowDescription' => ''], ['uid' => 4, 'rowDescription' => '']];
        self::assertSame(1, TalkOrdering::cardAnchor($withoutFilm, 50));
        self::assertSame(0, TalkOrdering::cardAnchor([['uid' => 50, 'rowDescription' => 'nrtalk:hub-ai']], 50));

        $inPlace = [['uid' => 1, 'rowDescription' => ''], ['uid' => 50, 'rowDescription' => 'nrtalk:hub-ai'], ['uid' => 4, 'rowDescription' => '']];
        self::assertTrue(TalkOrdering::cardInPlace($inPlace, 50, 1));
    }
}
