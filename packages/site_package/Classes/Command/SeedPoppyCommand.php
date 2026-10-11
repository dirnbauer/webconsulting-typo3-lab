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
use TYPO3\CMS\Core\Site\SiteFinder;

#[AsCommand(name: 'sitepackage:seed-poppy', description: 'Apply the native Poppy feature/help content and order it with DataHandler.')]
final class SeedPoppyCommand extends Command
{
    public function __construct(private readonly ConnectionPool $pool, private readonly SiteFinder $siteFinder) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('allow-production', null, InputOption::VALUE_NONE, 'Publish the lab-owned content in Production.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Resolve parents and preview content changes without writing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (Environment::getContext()->isProduction() && !$input->getOption('allow-production')) {
            throw new \RuntimeException('Publishing content in Production requires --allow-production.');
        }
        $path = 'EXT:site_package/Resources/Private/Data/Content/poppy/poppy.payload.json';
        $json = file_get_contents(GeneralUtility::getFileAbsFileName($path));
        if ($json === false) {
            throw new \RuntimeException('Poppy content payload could not be read.');
        }
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        // The local seed and production tree have different page IDs.
        $parents = [];
        foreach ([1068 => '/features', 1149 => '/technical-features', 2232 => '/resources', 2229 => '/ai'] as $oldPid => $slug) {
            $parents[$oldPid] = $this->pageWithinRoot($slug, (int)$payload['root']);
        }
        $nexusRoot = $this->siteFinder->getSiteByIdentifier('agent-nexus')->getRootPageId();
        foreach ([1400 => '/', 1407 => '/playground', 1408 => '/docs'] as $oldPid => $slug) {
            $parents[$oldPid] = $this->pageWithinRoot($slug, $nexusRoot);
        }
        $this->migratePages((int)$payload['root'], $nexusRoot, (bool)$input->getOption('dry-run'), $output);
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
            foreach ($match as &$value) {
                if (is_string($value) && str_starts_with($value, '@')) {
                    $value = $keys[substr($value, 1)];
                }
            }
            unset($value);
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
        // Connect native repeatable items after new parent and child IDs are known.
        foreach ($payload['records'] as $record) {
            if (!isset($record['_collection'])) {
                continue;
            }
            $collection = $record['_collection'];
            $children = array_map(static fn (string $key): int => $keys[$key], $collection['keys']);
            $handler = GeneralUtility::makeInstance(DataHandler::class);
            $handler->start([$record['table'] => [$keys[$record['key']] => [$collection['field'] => implode(',', $children)]]], []);
            $handler->process_datamap();
            if ($handler->errorLog !== []) {
                throw new \RuntimeException('DataHandler could not connect the Poppy content steps.');
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
        $after = $this->pageWithinRoot('/ap2', $nexusRoot);
        $handler = GeneralUtility::makeInstance(DataHandler::class);
        $handler->start([], ['pages' => [$keys['NEW_poppy_feature'] => ['move' => -$after]]]);
        $handler->process_cmdmap();
        if ($handler->errorLog !== []) {
            throw new \RuntimeException('DataHandler could not order the Poppy navigation.');
        }
        $this->redirects($keys);
        $output->writeln('Agent Nexus overview, Poppy, Pi Durable and help are ready.');
        return Command::SUCCESS;
    }

    private function migratePages(int $oldRoot, int $nexusRoot, bool $dryRun, OutputInterface $output): void
    {
        $definitions = [
            '/features/poppy' => '/poppy',
            '/features/poppy/pi-durable' => '/poppy/pi-durable',
            '/resources/poppy-help' => '/poppy/help',
        ];
        $legacy = [];
        foreach ($definitions as $old => $new) {
            $uid = $this->pageWithinRoot($old, $oldRoot, false);
            $current = $this->pageWithinRoot($new, $nexusRoot, false);
            if ($uid > 0 && $current > 0 && $uid !== $current) {
                throw new \RuntimeException('Both legacy and Agent Nexus Poppy pages exist: ' . $old);
            }
            if ($uid > 0) {
                $legacy[$old] = $uid;
            }
        }
        if ($legacy === []) {
            return;
        }
        if ($dryRun) {
            $output->writeln('Would move the existing Poppy pages to Agent Nexus and preserve their URLs.');
            return;
        }
        $feature = $legacy['/features/poppy'] ?? $this->pageWithinRoot('/poppy', $nexusRoot);
        $commands = ['pages' => []];
        $data = ['pages' => []];
        foreach ($legacy as $old => $uid) {
            $commands['pages'][$uid] = ['move' => $old === '/features/poppy' ? $nexusRoot : $feature];
            $data['pages'][$uid] = ['slug' => $definitions[$old]];
        }
        Bootstrap::initializeBackendAuthentication();
        $handler = GeneralUtility::makeInstance(DataHandler::class);
        $handler->start([], $commands);
        $handler->process_cmdmap();
        if ($handler->errorLog !== []) {
            throw new \RuntimeException('DataHandler could not move the existing Poppy pages.');
        }
        $handler = GeneralUtility::makeInstance(DataHandler::class);
        $handler->start($data, []);
        $handler->process_datamap();
        if ($handler->errorLog !== []) {
            throw new \RuntimeException('DataHandler could not update the moved Poppy slugs.');
        }
    }

    /** @param array<string, int> $keys */
    private function redirects(array $keys): void
    {
        $targets = [
            '/poppy' => $keys['NEW_poppy_feature'],
            '/features/poppy' => $keys['NEW_poppy_feature'],
            '/features/poppy/pi-durable' => $keys['NEW_poppy_pi'],
            '/resources/poppy-help' => $keys['NEW_poppy_help'],
        ];
        $data = [];
        $connection = $this->pool->getConnectionForTable('sys_redirect');
        foreach ($targets as $path => $page) {
            foreach ([$path, $path . '/'] as $source) {
                $uid = (int)$connection->select(['uid'], 'sys_redirect', ['source_host' => '*', 'source_path' => $source, 'deleted' => 0])->fetchOne();
                $target = 't3://page?uid=' . $page;
                $data['sys_redirect'][$uid > 0 ? $uid : 'NEW_redirect_' . count($data['sys_redirect'] ?? [])] = [
                    'pid' => 0, 'source_host' => '*', 'source_path' => $source,
                    'target' => $target, 'target_statuscode' => 301, 'disabled' => 0,
                ];
            }
        }
        $handler = GeneralUtility::makeInstance(DataHandler::class);
        $handler->start($data, []);
        $handler->process_datamap();
        if ($handler->errorLog !== []) {
            throw new \RuntimeException('DataHandler could not preserve former Poppy URLs.');
        }
    }

    private function pageWithinRoot(string $slug, int $root, bool $required = true): int
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
        if ($matches === [] && !$required) {
            return 0;
        }
        if (count($matches) !== 1) {
            throw new \RuntimeException('Expected exactly one English parent within the lab root: ' . $slug);
        }
        return $matches[0];
    }
}
