<?php
declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsCommand(name: 'sitepackage:seed-poppy', description: 'Apply the native Poppy feature/help content and order it with DataHandler.')]
final class SeedPoppyCommand extends Command
{
    public function __construct(private readonly ConnectionPool $pool) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('allow-production', null, InputOption::VALUE_NONE, 'Publish the lab-owned content in Production.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Resolve parents and preview content changes without writing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = 'EXT:site_package/Resources/Private/Data/Content/poppy/poppy.payload.json';
        $json = file_get_contents(GeneralUtility::getFileAbsFileName($path));
        if ($json === false) {
            throw new \RuntimeException('Poppy content payload could not be read.');
        }
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        // The local seed and production tree have different page IDs.
        $parents = [];
        foreach ([1068 => '/features', 1149 => '/technical-features', 2232 => '/resources'] as $oldPid => $slug) {
            $parents[$oldPid] = $this->pageWithinRoot($slug, (int)$payload['root']);
        }
        foreach ($payload['records'] as &$record) {
            if (is_int($record['pid'])) {
                $record['pid'] = $parents[$record['pid']] ?? $record['pid'];
            }
            if (is_int($record['match']['pid'])) {
                $record['match']['pid'] = $parents[$record['match']['pid']] ?? $record['match']['pid'];
            }
        }
        unset($record);
        $temporary = tempnam(Environment::getVarPath(), 'poppy-content-');
        if ($temporary === false) {
            throw new \RuntimeException('Could not prepare resolved Poppy content.');
        }
        try {
            file_put_contents($temporary, json_encode($payload, JSON_THROW_ON_ERROR));
            $command = $this->getApplication()?->find('sitepackage:content:apply');
            $arguments = ['payload' => $temporary, '--allow-production' => (bool)$input->getOption('allow-production'), '--dry-run' => (bool)$input->getOption('dry-run')];
            if ($command === null || $command->run(new ArrayInput($arguments), $output) !== Command::SUCCESS) {
                return Command::FAILURE;
            }
        } finally {
            unlink($temporary);
        }
        if ($input->getOption('dry-run')) {
            return Command::SUCCESS;
        }
        Bootstrap::initializeBackendAuthentication();
        $keys = [];
        $groups = [];
        foreach ($payload['records'] as $record) {
            $match = $record['match'];
            if (is_string($match['pid']) && str_starts_with($match['pid'], '@')) {
                $match['pid'] = $keys[substr($match['pid'], 1)];
            }
            $uid = (int)$this->pool->getConnectionForTable($record['table'])->select(
                ['uid'], $record['table'], $match + ['deleted' => 0],
            )->fetchOne();
            if ($uid <= 0) {
                throw new \RuntimeException('Poppy content record missing: ' . $record['key']);
            }
            $keys[$record['key']] = $uid;
            if ($record['table'] === 'tt_content') {
                $groups[$match['pid']][] = $uid;
            }
        }
        foreach ($groups as $pid => $uids) {
            $previous = null;
            // Hub links belong after the existing editorial content.
            if (count($uids) === 1) {
                $rows = $this->pool->getConnectionForTable('tt_content')->select(
                    ['uid'], 'tt_content', ['pid' => $pid, 'deleted' => 0, 'sys_language_uid' => 0], [], ['sorting' => 'DESC'],
                )->fetchFirstColumn();
                foreach ($rows as $row) {
                    if ((int)$row !== $uids[0]) { $previous = (int)$row; break; }
                }
            }
            foreach ($uids as $uid) {
                $handler = GeneralUtility::makeInstance(DataHandler::class);
                $handler->start([], ['tt_content' => [$uid => ['move' => $previous === null ? $pid : -$previous]]]);
                $handler->process_cmdmap();
                if ($handler->errorLog !== []) {
                    throw new \RuntimeException('DataHandler could not order Poppy content.');
                }
                $previous = $uid;
            }
        }
        $output->writeln('Poppy feature, Pi Durable example and help are ready.');
        return Command::SUCCESS;
    }

    private function pageWithinRoot(string $slug, int $root): int
    {
        $connection = $this->pool->getConnectionForTable('pages');
        $candidates = $connection->select(['uid'], 'pages', ['slug' => $slug, 'deleted' => 0, 'sys_language_uid' => 0])->fetchFirstColumn();
        $matches = [];
        foreach ($candidates as $candidate) {
            $uid = (int)$candidate;
            $visited = [];
            while ($uid > 0 && !isset($visited[$uid])) {
                if ($uid === $root) {
                    $matches[] = (int)$candidate;
                    break;
                }
                $visited[$uid] = true;
                $uid = (int)$connection->select(['pid'], 'pages', ['uid' => $uid, 'deleted' => 0])->fetchOne();
            }
        }
        if (count($matches) !== 1) {
            throw new \RuntimeException('Expected exactly one English parent within the lab root: ' . $slug);
        }
        return $matches[0];
    }
}
