<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\ViewHelpers\StageWall;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Page\ContentArea;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use Webconsulting\SitePackage\StartPage\StageWallPlan;

/**
 * The "Stage and Wall" plan of a content area (see StageWallPlan).
 *
 * The width of a tile is the editor's choice in the default language: a
 * translation shows its element where the default language does, whatever
 * its own copy of the "Layout" field says.
 *
 * ```
 *   <f:variable name="plan" value="{sp:stageWall.plan(area: content.main)}"/>
 *   {plan.stage}, {plan.cells}
 * ```
 */
final class PlanViewHelper extends AbstractViewHelper
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly PageRepository $pageRepository,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('area', ContentArea::class, 'The content area of the start page', true);
    }

    /**
     * @return array{stage: 'film'|'hero'|'none', cells: array<int, array<string, string>>}
     */
    public function render(): array
    {
        $area = $this->arguments['area'];
        if (!$area instanceof ContentArea) {
            return StageWallPlan::plan([]);
        }
        $records = array_values(array_filter(
            $area->getRecords(),
            static fn (mixed $record): bool => $record instanceof RecordInterface
        ));
        $layouts = $this->defaultLayouts(array_map(static fn (RecordInterface $record): int => $record->getUid(), $records));
        $elements = [];
        foreach ($records as $record) {
            $elements[] = [
                'uid' => $record->getUid(),
                'type' => (string)$record->getRecordType(),
                'layout' => $layouts[$record->getUid()] ?? 0,
            ];
        }
        return StageWallPlan::plan($elements);
    }

    /**
     * The "Layout" value of each element in the default language (or of a
     * translation without a default record), in the current workspace.
     *
     * @param list<int> $uids
     * @return array<int, int>
     */
    private function defaultLayouts(array $uids): array
    {
        if ($uids === []) {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)))
            ->executeQuery()
            ->fetchAllAssociative();
        $layouts = [];
        foreach ($rows as $row) {
            $uid = is_numeric($row['uid'] ?? null) ? (int)$row['uid'] : 0;
            $this->pageRepository->versionOL('tt_content', $row);
            $layout = is_array($row) && is_numeric($row['layout'] ?? null) ? (int)$row['layout'] : 0;
            $layouts[$uid] = $layout;
        }
        return $layouts;
    }
}
