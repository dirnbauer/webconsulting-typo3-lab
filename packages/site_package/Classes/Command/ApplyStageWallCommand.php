<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webconsulting\SitePackage\StartPage\StageWallPlan;

/**
 * Makes "Stage and Wall" the start page of the Desiderio site, or takes it
 * back: the root page's backend layout (pagets__StageWall, or Desiderio's
 * pagets__DesiderioStartpage with --revert; the field is the same in every
 * language, so the DataHandler copies it to the translations) and the tile
 * widths of the start page's elements (tt_content.layout, default language;
 * translations follow it when the page renders).
 *
 * The widths are the ones the design was made with, for the 17 elements the
 * Desiderio showcase seeds on its start page, matched by element type in page
 * order. When the start page holds other elements, only the backend layout
 * is set: every element then is a full-width tile, which is always a whole
 * page, and an editor picks widths in the "Layout" field.
 *
 * Idempotent: it writes only fields that differ. The root page is found by
 * the site, so the command needs no uid and runs the same in every
 * installation.
 */
#[AsCommand(
    name: 'sitepackage:home:stage-wall',
    description: 'Make "Stage and Wall" the start page of the Desiderio site (backend layout and tile widths), or take it back with --revert.',
)]
final class ApplyStageWallCommand extends Command
{
    public const string SITE = 'desiderio';
    public const string LAYOUT = 'pagets__StageWall';
    public const string CLASSIC_LAYOUT = 'pagets__DesiderioStartpage';

    /**
     * The start page as Desiderio seeds it (StyleguideShowcasePages::homeContent)
     * and the "Layout" value of each element.
     */
    public const array DESIGN = [
        ['desiderio_hero', 0],
        ['desiderio_featurevideo', 0],
        ['desiderio_featurecards', 205],
        ['desiderio_featurealternating', 207],
        ['desiderio_featurecards', 0],
        ['desiderio_ctainline', StageWallPlan::DOCKED],
        ['desiderio_featurecards', 207],
        ['desiderio_featurecards', 205],
        ['desiderio_gallery', 0],
        ['desiderio_usecasegrid', 204],
        ['desiderio_pricingthreetier', 208],
        ['desiderio_featurechecklist', 207],
        ['desiderio_ctainline', StageWallPlan::DOCKED],
        ['desiderio_categorycards', 205],
        ['desiderio_ctainline', StageWallPlan::DOCKED],
        ['desiderio_howtosteps', 0],
        ['desiderio_ctabanner', 0],
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change, change nothing.')
            ->addOption('revert', null, InputOption::VALUE_NONE, 'Back to Desiderio\'s start page layout; the tile widths of the design are reset to 0.')
            ->addOption('allow-production', null, InputOption::VALUE_NONE, 'Run in the Production context.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run') === true;
        $revert = $input->getOption('revert') === true;
        if (!$dryRun && Environment::getContext()->isProduction() && $input->getOption('allow-production') !== true) {
            $io->error('Production requires --allow-production.');
            return Command::FAILURE;
        }
        try {
            $root = $this->siteFinder->getSiteByIdentifier(self::SITE)->getRootPageId();
        } catch (SiteNotFoundException) {
            $io->error('There is no site "' . self::SITE . '".');
            return Command::FAILURE;
        }

        $page = $this->row('pages', $root, ['uid', 'slug', 'backend_layout']);
        if ($page === null) {
            $io->error(sprintf('The root page %d of site "%s" does not exist.', $root, self::SITE));
            return Command::FAILURE;
        }
        $dataMap = [];
        $changes = [];
        $layout = $revert ? self::CLASSIC_LAYOUT : self::LAYOUT;
        if (($page['backend_layout'] ?? '') !== $layout) {
            $dataMap['pages'][$root]['backend_layout'] = $layout;
            $changes[] = ['page ' . $root . ' (' . self::string($page['slug'] ?? '') . ')', 'backend_layout', self::string($page['backend_layout'] ?? '') . ' → ' . $layout];
        }

        $elements = $this->elements($root);
        $types = array_map(static fn (array $element): string => $element['CType'], $elements);
        $design = array_map(static fn (array $entry): string => $entry[0], self::DESIGN);
        if ($types === $design) {
            foreach ($elements as $index => $element) {
                $width = $revert ? 0 : self::DESIGN[$index][1];
                if ($element['layout'] !== $width) {
                    $dataMap['tt_content'][$element['uid']]['layout'] = $width;
                    $changes[] = ['element ' . $element['uid'] . ' (' . $element['CType'] . ')', 'layout', $element['layout'] . ' → ' . $width];
                }
            }
        } else {
            $io->warning(sprintf(
                'The start page holds %d elements that are not the seeded 17; the tile widths stay as they are (an element without one is a full-width tile).',
                count($elements)
            ));
        }

        if ($changes === []) {
            $io->success(sprintf('%sNothing to do: the start page %s.', $dryRun ? '[dry-run] ' : '', $revert ? 'uses Desiderio\'s layout' : 'is "Stage and Wall"'));
            return Command::SUCCESS;
        }
        $io->table(['Record', 'Field', 'Change'], $changes);
        if ($dryRun) {
            $io->success(sprintf('[dry-run] %d change(s).', count($changes)));
            return Command::SUCCESS;
        }

        Bootstrap::initializeBackendAuthentication();
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof CommandLineUserAuthentication) {
            $io->error('No CLI backend user available.');
            return Command::FAILURE;
        }
        // Live records; nothing here is indexed content, so EXT:solr's record
        // monitor (it builds the page's frontend TypoScript per write) is off.
        $backendUser->setWorkspace(0);
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr']['monitoringType'] = 2;
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($dataMap, []);
        $dataHandler->process_datamap();
        if ($dataHandler->errorLog !== []) {
            $io->error(array_map(static fn (mixed $entry): string => self::string($entry), $dataHandler->errorLog));
            return Command::FAILURE;
        }
        $dataHandler->clear_cacheCmd('pages');
        $io->success(sprintf('%d change(s) written; the page cache is flushed.', count($changes)));
        return Command::SUCCESS;
    }

    /**
     * The live elements of the start page's main column, default language,
     * in page order.
     *
     * @return list<array{uid: int, CType: string, layout: int}>
     */
    private function elements(int $page): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder
            ->select('uid', 'CType', 'layout')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($page, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('colPos', 0),
                $queryBuilder->expr()->eq('sys_language_uid', 0),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('hidden', 0),
                $queryBuilder->expr()->eq('t3ver_wsid', 0),
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();
        return array_map(static fn (array $row): array => [
            'uid' => is_numeric($row['uid'] ?? null) ? (int)$row['uid'] : 0,
            'CType' => self::string($row['CType'] ?? ''),
            'layout' => is_numeric($row['layout'] ?? null) ? (int)$row['layout'] : 0,
        ], $rows);
    }

    /**
     * @param list<string> $fields
     * @return array<string, mixed>|null
     */
    private function row(string $table, int $uid, array $fields): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select(...$fields)
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0),
            )
            ->executeQuery()
            ->fetchAssociative();
        return is_array($row) ? $row : null;
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }
}
