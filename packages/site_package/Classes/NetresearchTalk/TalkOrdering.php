<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\NetresearchTalk;

/**
 * Where the talk's records belong, decided without the database: the
 * command reads the current state, these functions say what to change, and
 * nothing changes when everything is already in place (a second run).
 */
final class TalkOrdering
{
    /**
     * DataHandler moves that bring the wanted records into the wanted order.
     * Other records on the page keep their places relative to each other.
     * `after` null means the first position of the page.
     *
     * @param list<int> $current uids on the page in their stored order
     * @param list<int> $wanted the payload's records in payload order
     * @return list<array{uid: int, after: int|null}>
     */
    public static function moves(array $current, array $wanted): array
    {
        $present = array_values(array_filter($current, static fn (int $uid): bool => in_array($uid, $wanted, true)));
        if ($present === $wanted) {
            return [];
        }
        $moves = [];
        $previous = null;
        foreach ($wanted as $uid) {
            $moves[] = ['uid' => $uid, 'after' => $previous];
            $previous = $uid;
        }

        return $moves;
    }

    /**
     * Whether a collection already holds exactly the wanted items in the
     * wanted order with the sorting linking writes (1…n). Then linking again
     * would change nothing, and a second run leaves it alone.
     *
     * @param list<array{uid: int, sorting: int}> $rows the parent's items now
     * @param list<int> $wanted the payload's items in payload order
     */
    public static function collectionLinked(array $rows, array $wanted): bool
    {
        usort($rows, static fn (array $a, array $b): int => [$a['sorting'], $a['uid']] <=> [$b['sorting'], $b['uid']]);

        return $wanted !== []
            && array_column($rows, 'uid') === $wanted
            && array_column($rows, 'sorting') === range(1, count($wanted));
    }

    /**
     * Items the parent still has but the payload no longer names.
     *
     * @param list<array{uid: int, sorting: int}> $rows
     * @param list<int> $wanted
     * @return list<int>
     */
    public static function leftovers(array $rows, array $wanted): array
    {
        return array_values(array_diff(array_column($rows, 'uid'), $wanted));
    }

    /**
     * The element a hub card follows: the last element the films seeder put
     * on the page (the film's transcript), otherwise the page's first
     * element. 0 when the card is the only element.
     *
     * @param list<array{uid: int, rowDescription: string}> $rows the page's elements in their stored order
     */
    public static function cardAnchor(array $rows, int $card): int
    {
        $others = array_values(array_filter($rows, static fn (array $row): bool => $row['uid'] !== $card));
        if ($others === []) {
            return 0;
        }
        $anchor = $others[0]['uid'];
        foreach ($others as $row) {
            if (str_starts_with($row['rowDescription'], 'Seeded by sitepackage:films:seed')) {
                $anchor = $row['uid'];
            }
        }

        return $anchor;
    }

    /**
     * Whether the card already sits right after its anchor.
     *
     * @param list<array{uid: int, rowDescription: string}> $rows
     */
    public static function cardInPlace(array $rows, int $card, int $anchor): bool
    {
        $uids = array_column($rows, 'uid');
        $position = array_search($anchor, $uids, true);

        return $position !== false && ($uids[$position + 1] ?? null) === $card;
    }
}
