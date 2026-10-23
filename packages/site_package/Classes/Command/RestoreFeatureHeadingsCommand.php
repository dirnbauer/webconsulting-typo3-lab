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
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsCommand(
    name: 'sitepackage:restore-feature-headings',
    description: 'Restore the visible page headings of the Records List and Blog demos.',
)]
final class RestoreFeatureHeadingsCommand extends Command
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Apply the changes; otherwise show a read-only preview.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $query = $this->connectionPool->getQueryBuilderForTable('pages');
        $rows = $query->select('uid', 'title', 'slug', 'tx_desiderio_h1_sronly')
            ->from('pages')
            ->where(
                $query->expr()->eq('sys_language_uid', 0),
                $query->expr()->eq('t3ver_wsid', 0),
                $query->expr()->in('slug', $query->createNamedParameter([
                    '/features/records-list', '/features/records-list/',
                    '/features/blog', '/features/blog/',
                ], Connection::PARAM_STR_ARRAY)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $data = [];
        $preview = [];
        foreach ($rows as $row) {
            $uid = (int)$row['uid'];
            if ($this->siteFinder->getSiteByPageId($uid)->getIdentifier() !== 'desiderio') {
                continue;
            }
            $hiddenHeading = (int)$row['tx_desiderio_h1_sronly'] !== 0;
            $preview[] = [$uid, (string)$row['title'], $hiddenHeading ? 'restore visible heading' : 'already visible'];
            if ($hiddenHeading) {
                $data[$uid] = ['tx_desiderio_h1_sronly' => 0];
            }
        }

        if (count($preview) !== 2) {
            $io->error('Expected exactly the Records List and Blog pages in the Desiderio site. No records changed.');
            return Command::FAILURE;
        }
        $io->table(['Page', 'Title', 'Action'], $preview);
        if (!$input->getOption('apply') || $data === []) {
            $io->success($data === [] ? 'Both headings are already visible.' : 'Preview only. Use --apply to restore these headings.');
            return Command::SUCCESS;
        }

        Bootstrap::initializeBackendAuthentication();
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof CommandLineUserAuthentication) {
            $io->error('No CLI backend user is available. No records changed.');
            return Command::FAILURE;
        }
        $backendUser->setWorkspace(0);
        $handler = GeneralUtility::makeInstance(DataHandler::class);
        $handler->start(['pages' => $data], [], $backendUser);
        $handler->process_datamap();
        if ($handler->errorLog !== []) {
            $io->error($handler->errorLog);
            return Command::FAILURE;
        }

        $connection = $this->connectionPool->getConnectionForTable('pages');
        foreach (array_keys($data) as $uid) {
            $value = $connection->fetchOne('SELECT tx_desiderio_h1_sronly FROM pages WHERE uid = ?', [$uid]);
            if ($value === false || (int)$value !== 0) {
                $io->error(sprintf('Page %d did not retain the requested heading setting.', $uid));
                return Command::FAILURE;
            }
        }
        $io->success('Restored both feature page headings through DataHandler.');
        return Command::SUCCESS;
    }
}
