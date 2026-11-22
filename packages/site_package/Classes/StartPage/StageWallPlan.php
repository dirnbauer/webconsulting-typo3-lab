<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\StartPage;

/**
 * Where each content element of the Desiderio start page "Stage and Wall"
 * goes (template Pages/StageWall, backend layout pagets__StageWall).
 *
 * Stage: a hero as the first element stands on the stage; a product film
 * directly after it becomes the object on the stage. Everything else is the
 * wall, a 12-column grid of tiles. An editor picks a tile's width in the
 * element's "Layout" field (204-208: 4 to 8 of 12 columns, 0: full width)
 * and docks a call to action under the tile above it with 290 (a
 * connector). The plan never leaves a hole: a row that its tiles do not
 * fill widens its last tile, and a tile without connectors spans the rows
 * of its neighbour's connectors.
 *
 * The plan comes out as data attributes for the element's root tag and its
 * anchors (see StageWallCellViewHelper). The CSS places by those
 * attributes, so it never depends on the position or number of elements.
 */
final class StageWallPlan
{
    public const string HERO_TYPE = 'desiderio_hero';
    public const string FILM_TYPE = 'desiderio_featurevideo';

    /** Layout values offered on the start page (page TSconfig of the site). */
    public const array SPANS = [204 => 4, 205 => 5, 206 => 6, 207 => 7, 208 => 8];
    public const int DOCKED = 290;

    public const int COLUMNS = 12;

    /** The stylesheet has placement rules up to this desktop row. */
    public const int MAX_ROW = 60;

    /**
     * @param list<array{uid: int, type: string, layout: int}> $elements in page order
     * @return array{stage: 'film'|'hero'|'none', cells: array<int, array<string, string>>}
     */
    public static function plan(array $elements): array
    {
        $cells = [];
        $stage = 'none';
        $index = 0;
        if (($elements[0]['type'] ?? '') === self::HERO_TYPE) {
            $stage = 'hero';
            $cells[$elements[0]['uid']] = ['data-sw' => 'hero'];
            $index = 1;
            if (($elements[1]['type'] ?? '') === self::FILM_TYPE) {
                $stage = 'film';
                $cells[$elements[1]['uid']] = ['data-sw' => 'film'];
                $index = 2;
            }
        }

        // Tiles with the connectors docked under them.
        /** @var list<array{tile: int, span: int, connectors: list<int>}> $groups */
        $groups = [];
        foreach (array_slice($elements, $index) as $element) {
            $last = array_key_last($groups);
            if ($element['layout'] === self::DOCKED && $last !== null) {
                $group = $groups[$last];
                $group['connectors'][] = $element['uid'];
                $groups[$last] = $group;
                continue;
            }
            $groups[] = [
                'tile' => $element['uid'],
                'span' => self::SPANS[$element['layout']] ?? self::COLUMNS,
                'connectors' => [],
            ];
        }

        // Rows: tiles side by side while they fit; the last tile of a row
        // that is not full takes the rest.
        /** @var list<list<array{tile: int, span: int, connectors: list<int>}>> $rows */
        $rows = [];
        /** @var list<array{tile: int, span: int, connectors: list<int>}> $row */
        $row = [];
        $used = 0;
        foreach ($groups as $group) {
            if ($row !== [] && $used + $group['span'] > self::COLUMNS) {
                $rows[] = self::fill($row, $used);
                $row = [];
                $used = 0;
            }
            $row[] = $group;
            $used += $group['span'];
        }
        if ($row !== []) {
            $rows[] = self::fill($row, $used);
        }

        $line = ['film' => 4, 'hero' => 3, 'none' => 1][$stage];
        $first = true;
        foreach ($rows as $rowIndex => $row) {
            $height = 1 + max(0, ...array_map(static fn (array $group): int => count($group['connectors']), $row));
            $column = 1;
            foreach ($row as $group) {
                $connectors = $group['connectors'];
                $base = [
                    'data-sw-col' => (string)$column,
                    'data-sw-span' => (string)$group['span'],
                    'data-sw-size' => self::size($group['span']),
                ];
                $tile = ['data-sw' => 'tile'] + $base + self::rows($line, $height - count($connectors));
                if ($rowIndex === 0) {
                    $tile['data-sw-lead'] = $first ? 'first' : 'row';
                    $first = false;
                }
                if ($connectors !== []) {
                    $tile['data-sw-dock'] = '';
                }
                $cells[$group['tile']] = $tile;
                $connectorLine = $line + $height - count($connectors);
                foreach ($connectors as $position => $uid) {
                    $connector = ['data-sw' => 'connector'] + $base + self::rows($connectorLine + $position, 1);
                    if ($position < count($connectors) - 1) {
                        $connector['data-sw-dock'] = '';
                    }
                    $cells[$uid] = $connector;
                }
                $column += $group['span'];
            }
            $line += $height;
        }

        return ['stage' => $stage, 'cells' => $cells];
    }

    /**
     * @param list<array{tile: int, span: int, connectors: list<int>}> $row
     * @return list<array{tile: int, span: int, connectors: list<int>}>
     */
    private static function fill(array $row, int $used): array
    {
        $last = array_key_last($row);
        if ($last !== null) {
            $group = $row[$last];
            $group['span'] += self::COLUMNS - $used;
            $row[$last] = $group;
        }
        return $row;
    }

    /**
     * @return array<string, string>
     */
    private static function rows(int $line, int $height): array
    {
        if ($line + $height - 1 > self::MAX_ROW) {
            // Past the stylesheet's rules the element flows on its own.
            return [];
        }
        return ['data-sw-row' => (string)$line] + ($height > 1 ? ['data-sw-rows' => (string)$height] : []);
    }

    private static function size(int $span): string
    {
        return match (true) {
            $span >= self::COLUMNS => 'full',
            $span >= 6 => 'medium',
            default => 'narrow',
        };
    }
}
