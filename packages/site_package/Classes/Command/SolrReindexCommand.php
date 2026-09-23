<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Index\IndexService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueInitializationService;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Starts a full re-index of the Solr sites.
 *
 * Seeders and a database push write past the Solr record monitor, so the
 * index keeps documents of pages that no longer exist and misses new ones.
 * This does what the backend's "re-index" does, for every Solr site (or
 * the ones given): delete the site's documents from each core, then queue
 * every enabled indexing configuration again. With --index it also works
 * through each site's queue, in batches, until nothing is pending - what
 * the scheduler's IndexQueueWorkerTask does, without needing one per site:
 *
 *     vendor/bin/typo3 sitepackage:solr:reindex --index
 *
 * EXT:solr ships no console command for this.
 */
#[AsCommand(
    name: 'sitepackage:solr:reindex',
    description: 'Clear the Solr documents of each Solr site and queue all its indexing configurations again.',
)]
final class SolrReindexCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('site', null, InputOption::VALUE_REQUIRED, 'Only these site root page uids, comma-separated');
        $this->addOption('index', null, InputOption::VALUE_NONE, 'Index the queued items right away, in batches of 50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $option = $input->getOption('site');
        $only = is_string($option) && $option !== '' ? array_map('intval', explode(',', $option)) : [];

        $sites = GeneralUtility::makeInstance(SiteRepository::class)->getAvailableSites();
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
        $initialization = GeneralUtility::makeInstance(QueueInitializationService::class);
        $failed = false;

        foreach ($sites as $site) {
            if ($only !== [] && !in_array($site->getRootPageId(), $only, true)) {
                continue;
            }
            $configuration = $site->getSolrConfiguration();
            $names = array_values(array_filter(
                $configuration->getEnabledIndexQueueConfigurationNames(),
                static fn (mixed $name): bool => is_string($name)
            ));
            if ($names === []) {
                continue;
            }

            $types = array_map(
                static fn (string $name): string => $configuration->getIndexQueueTypeOrFallbackToConfigurationName($name),
                $names
            );
            $deleteQuery = 'type:(' . implode(' OR ', array_unique($types)) . ') AND siteHash:' . $site->getSiteHash();
            foreach ($connectionManager->getConnectionsBySite($site) as $connection) {
                $connection->getWriteService()->deleteByQuery($deleteQuery);
                if ($configuration->getEnableCommits()) {
                    $connection->getWriteService()->commit(false, false);
                }
            }

            $results = $initialization->initializeBySiteAndIndexConfigurations($site, $names);
            $ok = !in_array(false, $results, true);
            $failed = $failed || !$ok;
            $io->writeln(sprintf(
                '%s site %d: cleared and queued %s',
                $ok ? '✓' : '✗',
                $site->getRootPageId(),
                implode(', ', $names)
            ));

            if ($ok && $input->getOption('index')) {
                $queue = GeneralUtility::makeInstance(Queue::class);
                $indexService = GeneralUtility::makeInstance(IndexService::class, $site);
                // A batch that makes no progress (every item failing) ends the loop.
                $pending = $queue->getStatisticsBySite($site)->getPendingCount();
                while ($pending > 0) {
                    $indexService->indexItems(50);
                    $left = $queue->getStatisticsBySite($site)->getPendingCount();
                    if ($left >= $pending) {
                        break;
                    }
                    $pending = $left;
                }
                $statistic = $queue->getStatisticsBySite($site);
                $io->writeln(sprintf(
                    '  indexed %d, failed %d, pending %d',
                    $statistic->getSuccessCount(),
                    $statistic->getFailedCount(),
                    $statistic->getPendingCount()
                ));
            }
        }

        if ($failed) {
            $io->error('At least one indexing configuration could not be queued.');
            return Command::FAILURE;
        }
        $io->success($input->getOption('index') ? 'Re-indexed.' : 'Queued. Run each site\'s IndexQueueWorkerTask, or this command with --index.');

        return Command::SUCCESS;
    }
}
