<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Doctrine\DBAL\ParameterType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Apply a page and its single content element from a JSON definition, in LIVE.
 *
 * The Downloads page is a database record, so a deployment (code only) does not
 * carry it to the live site and neither does publishing the download snapshot
 * (files only). This is what puts it there.
 *
 * Deliberately not routed through EXT:mcp_server's WriteTable. Outside DDEV that
 * tool stages every write in a draft workspace, and the Staging workspace on
 * this project already carries unrelated pending records - 28 of them when this
 * was written. Publishing the workspace to land one page would have pushed
 * somebody else's drafts live with it.
 */
#[AsCommand(
    name: 'sitepackage:apply-downloads-page',
    description: 'Create or update the Downloads page in live from a JSON definition.',
)]
final class ApplyDownloadsPageCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('definition', InputArgument::REQUIRED, 'Path to the JSON definition')
            ->addArgument('rootPage', InputArgument::REQUIRED, 'Uid of the site root the page belongs under')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change and write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $definitionPath = $input->getArgument('definition');
        $rootArgument = $input->getArgument('rootPage');
        $rootPage = is_numeric($rootArgument) ? (int)$rootArgument : 0;
        $dryRun = (bool)$input->getOption('dry-run');

        if (!is_string($definitionPath) || !is_file($definitionPath)) {
            $io->error(sprintf('No such definition: %s', is_string($definitionPath) ? $definitionPath : '(none)'));
            return Command::FAILURE;
        }
        if ($rootPage <= 0) {
            $io->error('The root page uid must be a positive integer.');
            return Command::FAILURE;
        }

        $definition = json_decode((string)file_get_contents($definitionPath), true);
        $page = is_array($definition) ? ($definition['page'] ?? null) : null;
        $content = is_array($definition) ? ($definition['content'] ?? null) : null;
        if (!is_array($page) || !is_array($content)) {
            $io->error('The definition must hold a "page" and a "content" object.');
            return Command::FAILURE;
        }

        $slug = $page['slug'] ?? null;
        if (!is_string($slug) || $slug === '') {
            $io->error('The definition has no page.slug.');
            return Command::FAILURE;
        }

        // Matched by slug under the root, never by uid: uids differ between the
        // DDEV database and the live one, so a uid from one is meaningless here.
        $pageUid = $this->findPageBySlug($slug, $rootPage);

        if ($dryRun) {
            foreach ($this->findDraftPages($slug, $rootPage) as $draft) {
                $io->writeln(sprintf('Would discard draft page %d (workspace %d).', $draft['uid'], $draft['t3ver_wsid']));
            }
            $io->writeln($pageUid === null
                ? sprintf('Would create %s under root %d.', $slug, $rootPage)
                : sprintf('Would update page %d at %s.', $pageUid, $slug));
            $contentUid = $pageUid === null ? null : $this->findFirstContent($pageUid);
            $io->writeln($contentUid === null
                ? 'Would create its content element.'
                : sprintf('Would update content element %d.', $contentUid));
            return Command::SUCCESS;
        }

        Bootstrap::initializeBackendAuthentication();
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof CommandLineUserAuthentication) {
            $io->error('No CLI backend user is available; cannot write through DataHandler.');
            return Command::FAILURE;
        }
        if (!$this->discardDrafts($slug, $rootPage, $backendUser, $io)) {
            return Command::FAILURE;
        }

        // Explicitly live. DataHandler would otherwise inherit whatever
        // workspace the CLI user happens to sit in.
        $backendUser->setWorkspace(0);

        if ($pageUid === null) {
            $placeholder = 'NEW_downloads_page';
            $this->runDataHandler(
                ['pages' => [$placeholder => $page + ['pid' => $rootPage]]],
                $io
            );
            $pageUid = $this->findPageBySlug($slug, $rootPage);
            if ($pageUid === null) {
                $io->error('The page was not created.');
                return Command::FAILURE;
            }
            $io->success(sprintf('Created page %d at %s.', $pageUid, $slug));
        } else {
            $this->runDataHandler(['pages' => [$pageUid => $page]], $io);
            $io->success(sprintf('Updated page %d at %s.', $pageUid, $slug));
        }

        // Reuse the first element rather than appending, so repeated runs keep
        // one element on the page instead of stacking duplicates.
        $contentUid = $this->findFirstContent($pageUid);
        if ($contentUid === null) {
            $this->runDataHandler(
                ['tt_content' => ['NEW_downloads_ce' => $content + ['pid' => $pageUid]]],
                $io
            );
            $io->success('Created its content element.');
        } else {
            $this->runDataHandler(['tt_content' => [$contentUid => $content]], $io);
            $io->success(sprintf('Updated content element %d.', $contentUid));
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, array<int|string, array<mixed>>> $dataMap
     */
    private function runDataHandler(array $dataMap, SymfonyStyle $io): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($dataMap, []);
        $dataHandler->process_datamap();

        if ($dataHandler->errorLog !== []) {
            foreach ($dataHandler->errorLog as $error) {
                $io->error($error);
            }
            throw new \RuntimeException('DataHandler refused the write.', 1758300000);
        }
    }

    /**
     * Discard drafts of this page left in any workspace.
     *
     * The first attempt to carry this page to live went through MCP WriteTable,
     * which outside DDEV stages into a draft workspace; it created the page
     * there and stopped. Left alone, that draft becomes a second /downloads the
     * day anyone publishes the workspace. Only drafts at this slug under this
     * root are touched, together with their content, and they are discarded
     * through DataHandler rather than deleted, so the workspace stays
     * consistent. Everything else pending in the workspace is left alone.
     */
    private function discardDrafts(string $slug, int $rootPage, CommandLineUserAuthentication $backendUser, SymfonyStyle $io): bool
    {
        $drafts = $this->findDraftPages($slug, $rootPage);
        if ($drafts === []) {
            return true;
        }

        // Discard only works from inside the draft's own workspace: in core,
        // DataHandler::discard() returns without a word when the acting user
        // sits in live. Issued from workspace 0 this reported success and
        // changed nothing, so each workspace is entered in turn.
        $byWorkspace = [];
        foreach ($drafts as $draft) {
            $byWorkspace[(int)$draft['t3ver_wsid']][] = (int)$draft['uid'];
        }

        foreach ($byWorkspace as $workspaceId => $pageUids) {
            $backendUser->setWorkspace($workspaceId);
            $commandMap = [];
            foreach ($pageUids as $pageUid) {
                $commandMap['pages'][$pageUid]['discard'] = true;
                foreach ($this->findDraftContent($pageUid) as $contentUid) {
                    $commandMap['tt_content'][$contentUid]['discard'] = true;
                }
            }

            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start([], $commandMap);
            $dataHandler->process_cmdmap();
            foreach ($dataHandler->errorLog as $error) {
                $io->error($error);
            }
        }

        // DataHandler stays silent on a no-op, so success is read back from
        // the database rather than inferred from the absence of errors.
        $remaining = $this->findDraftPages($slug, $rootPage);
        if ($remaining !== []) {
            $io->error(sprintf(
                'Could not discard draft page(s) %s; nothing was written to live.',
                implode(', ', array_map(static fn(array $row): string => (string)$row['uid'], $remaining))
            ));
            return false;
        }

        foreach ($drafts as $draft) {
            $io->writeln(sprintf('Discarded draft page %d from workspace %d.', $draft['uid'], $draft['t3ver_wsid']));
        }
        return true;
    }

    /**
     * @return list<array{uid: int|string, t3ver_wsid: int|string}>
     */
    private function findDraftPages(string $slug, int $rootPage): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        /** @var list<array{uid: int|string, t3ver_wsid: int|string}> $rows */
        $rows = $queryBuilder
            ->select('uid', 't3ver_wsid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($rootPage, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('slug', $queryBuilder->createNamedParameter($slug)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                $queryBuilder->expr()->gt('t3ver_wsid', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return $rows;
    }

    /**
     * @return list<int>
     */
    private function findDraftContent(int $pageUid): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();

        $uids = [];
        foreach ($queryBuilder
            ->select('uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                $queryBuilder->expr()->gt('t3ver_wsid', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchFirstColumn() as $uid) {
            if (is_numeric($uid)) {
                $uids[] = (int)$uid;
            }
        }
        return $uids;
    }

    private function findPageBySlug(string $slug, int $rootPage): ?int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        $uid = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($rootPage, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('slug', $queryBuilder->createNamedParameter($slug)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchOne();

        return is_numeric($uid) && (int)$uid > 0 ? (int)$uid : null;
    }

    private function findFirstContent(int $pageUid): ?int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();

        $uid = $queryBuilder
            ->select('uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER))
            )
            ->orderBy('sorting')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return is_numeric($uid) && (int)$uid > 0 ? (int)$uid : null;
    }
}
