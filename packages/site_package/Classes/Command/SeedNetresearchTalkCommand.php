<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webconsulting\SitePackage\NetresearchTalk\TalkOrdering;
use Webconsulting\SitePackage\NetresearchTalk\TalkPayload;

/**
 * The Netresearch talk "KI in TYPO3" (Sebastian Mendel, MTUG München,
 * 9 November 2026) as lab-owned Desiderio pages below /ai, plus a card on
 * /ai and on /resources.
 *
 * Lab-owned, not part of Desiderio: the payload lives in
 * Resources/Private/Data/Content/netresearch-talk/ (one file per page).
 * Parents are found by slug within the Desiderio site, so the same payload
 * runs on the lab and on production. All writes go through
 * sitepackage:content:apply (DataHandler, live workspace); afterwards this
 * command links the collection items, orders the elements by their
 * `nrtalk:*` markers, keeps the hub first below /ai and puts the two cards
 * back after a Desiderio reseed removed them. A second run changes nothing.
 *
 *     ddev exec vendor/bin/typo3 sitepackage:seed-netresearch-talk --dry-run
 *     ddev exec vendor/bin/typo3 sitepackage:seed-netresearch-talk
 *
 * Run it after desiderio:styleguide:seed and sitepackage:films:seed, then
 * translate the touched pages (docs/content/seeding.md).
 */
#[AsCommand(name: 'sitepackage:seed-netresearch-talk', description: 'Publish the Netresearch talk pages below /ai and their cards on /ai and /resources.')]
final class SeedNetresearchTalkCommand extends Command
{
    public const PARENT_SLUG = '/ai';
    public const HUB_KEY = 'NEW_nrtalk_page';

    public function __construct(private readonly ConnectionPool $pool, private readonly SiteFinder $sites)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what content:apply would change; write nothing.')
            ->addOption('allow-production', null, InputOption::VALUE_NONE, 'Publish in the Production context.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');
        if (Environment::getContext()->isProduction() && !$input->getOption('allow-production')) {
            $io->error('Production publishing requires --allow-production.');
            return Command::FAILURE;
        }
        $talk = TalkPayload::fromDirectory();
        $problems = $talk->problems();
        if ($problems !== []) {
            $io->error(array_merge(['The talk payload is inconsistent:'], $problems));
            return Command::FAILURE;
        }
        $root = $this->sites->getSiteByIdentifier('desiderio')->getRootPageId();
        $parent = $this->pageWithinRoot(self::PARENT_SLUG, $root);
        $notices = [];
        $payload = $talk->resolve(
            $parent,
            fn (string $slug): ?int => $this->pageWithinRootOrNull($slug, $root),
            static function (string $source): bool {
                $path = GeneralUtility::getFileAbsFileName($source);
                return $path !== '' && is_file($path);
            },
            TalkPayload::labStatus(),
            $notices
        );
        foreach ($notices as $notice) {
            $io->note($notice);
        }

        $payloadFile = tempnam(Environment::getVarPath(), 'nrtalk-content-');
        $keysFile = tempnam(Environment::getVarPath(), 'nrtalk-keys-');
        if ($payloadFile === false || $keysFile === false) {
            throw new \RuntimeException('Cannot prepare the content payload.', 1794000201);
        }
        try {
            file_put_contents($payloadFile, json_encode($payload, JSON_THROW_ON_ERROR));
            $apply = $this->getApplication()?->find('sitepackage:content:apply');
            if ($apply === null) {
                throw new \RuntimeException('sitepackage:content:apply is unavailable.', 1794000202);
            }
            $arguments = ['payload' => $payloadFile, '--dry-run' => $dryRun, '--allow-production' => (bool)$input->getOption('allow-production')];
            if (!$dryRun) {
                $arguments['--keys-file'] = $keysFile;
            }
            $status = $apply->run(new ArrayInput($arguments), $output);
            if ($status !== Command::SUCCESS) {
                return $status;
            }
            if ($dryRun) {
                $io->writeln('Dry run: collection links, element order and the place of the two cards are checked by a real run.');
                return Command::SUCCESS;
            }
            $keys = json_decode((string)file_get_contents($keysFile), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($keys)) {
                throw new \RuntimeException('content:apply wrote no record keys.', 1794000203);
            }
            $uids = [];
            foreach ($keys as $key => $uid) {
                $uids[(string)$key] = is_numeric($uid) ? (int)$uid : 0;
            }
            $changes = $this->finish($payload['records'], $uids, $parent, $io);
            $io->success(sprintf('Talk in place: %d collection(s) linked, %d move(s). Translate the talk pages next (docs/content/seeding.md).', $changes['linked'], $changes['moves']));

            return Command::SUCCESS;
        } finally {
            @unlink($payloadFile);
            @unlink($keysFile);
        }
    }

    /**
     * Links the collections, orders elements and pages, places the cards.
     *
     * @param list<array<string, mixed>> $records the resolved payload
     * @param array<string, int> $uids uid per record key, from content:apply
     * @return array{linked: int, moves: int}
     */
    private function finish(array $records, array $uids, int $parent, SymfonyStyle $io): array
    {
        $byKey = [];
        foreach ($records as $record) {
            $key = self::text($record['key'] ?? '');
            if (($uids[$key] ?? 0) <= 0) {
                throw new \RuntimeException(sprintf('content:apply reported no uid for %s.', $key), 1794000204);
            }
            $byKey[$key] = $record;
        }
        $linked = 0;
        $moves = 0;

        // 1. Collections, parents before their items (the payload order), so
        //    nested collections (table rows and their cells) work too.
        foreach ($records as $record) {
            $table = self::text($record['table'] ?? '');
            $parentUid = $uids[self::text($record['key'] ?? '')];
            foreach (TalkPayload::collections($record) as $collection) {
                $wanted = array_map(static fn (string $key): int => $uids[$key] ?? 0, $collection['keys']);
                $first = $byKey[$collection['keys'][0] ?? ''] ?? null;
                if ($first === null || in_array(0, $wanted, true)) {
                    throw new \RuntimeException(sprintf('%s.%s names an item content:apply did not create.', $table, $collection['field']), 1794000205);
                }
                $childTable = self::text($first['table'] ?? '');
                $set = is_array($first['set'] ?? null) ? $first['set'] : [];
                $rows = $this->items($childTable, $parentUid, self::text($set['tablenames'] ?? ''), self::text($set['fieldname'] ?? ''));
                foreach (TalkOrdering::leftovers($rows, $wanted) as $leftover) {
                    $io->warning(sprintf('%s:%d still belongs to %s:%d but is not in the payload; delete it in the backend if it is obsolete.', $childTable, $leftover, $table, $parentUid));
                }
                if (TalkOrdering::collectionLinked($rows, $wanted)) {
                    continue;
                }
                $this->dataHandler([$table => [$parentUid => [$collection['field'] => implode(',', $wanted)]]], []);
                $linked++;
            }
        }

        // 2. Elements of each talk page in payload order; the cards after their anchor.
        $pages = [];
        foreach ($records as $record) {
            if (($record['table'] ?? '') !== 'tt_content') {
                continue;
            }
            $pid = $record['pid'] ?? null;
            $pageUid = is_string($pid) && str_starts_with($pid, '@') ? ($uids[substr($pid, 1)] ?? 0) : (is_int($pid) ? $pid : 0);
            $set = is_array($record['set'] ?? null) ? $record['set'] : [];
            $marker = self::text($set['rowDescription'] ?? '');
            if (str_starts_with($marker, TalkPayload::MARKER_PREFIX . 'hub-')) {
                $moves += $this->placeCard($pageUid, $uids[self::text($record['key'] ?? '')]);
                continue;
            }
            $pages[$pageUid][] = $uids[self::text($record['key'] ?? '')];
        }
        foreach ($pages as $pageUid => $wanted) {
            $moves += $this->order('tt_content', $pageUid, $this->elements($pageUid), $wanted);
        }

        // 3. Sub-pages in payload order below the hub; the hub first below /ai.
        $hub = $uids[self::HUB_KEY] ?? 0;
        $subPages = [];
        foreach ($records as $record) {
            $key = self::text($record['key'] ?? '');
            if (($record['table'] ?? '') === 'pages' && $key !== self::HUB_KEY) {
                $subPages[] = $uids[$key];
            }
        }
        $moves += $this->order('pages', $hub, $this->children($hub), $subPages);
        $siblings = $this->children($parent);
        if (($siblings[0] ?? $hub) !== $hub) {
            $this->move('pages', $hub, $parent);
            $moves++;
        }

        return ['linked' => $linked, 'moves' => $moves];
    }

    /**
     * @param list<int> $current
     * @param list<int> $wanted
     */
    private function order(string $table, int $container, array $current, array $wanted): int
    {
        $moves = TalkOrdering::moves($current, $wanted);
        foreach ($moves as $move) {
            $this->move($table, $move['uid'], $move['after'] === null ? $container : -$move['after']);
        }

        return count($moves);
    }

    private function placeCard(int $page, int $card): int
    {
        $rows = $this->elementRows($page);
        $anchor = TalkOrdering::cardAnchor($rows, $card);
        if ($anchor === 0 || TalkOrdering::cardInPlace($rows, $card, $anchor)) {
            return 0;
        }
        $this->move('tt_content', $card, -$anchor);

        return 1;
    }

    /**
     * @return list<array{uid: int, sorting: int}>
     */
    private function items(string $table, int $parent, string $tablenames, string $fieldname): array
    {
        $queryBuilder = $this->pool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->select('uid', 'sorting')->from($table)->where(
            $queryBuilder->expr()->eq('foreign_table_parent_uid', $queryBuilder->createNamedParameter($parent, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('sys_language_uid', 0),
            $queryBuilder->expr()->eq('deleted', 0),
            $queryBuilder->expr()->eq('t3ver_wsid', 0),
        );
        if ($tablenames !== '') {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter($tablenames)));
        }
        if ($fieldname !== '') {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter($fieldname)));
        }
        $rows = [];
        foreach ($queryBuilder->orderBy('sorting')->addOrderBy('uid')->executeQuery()->fetchAllAssociative() as $row) {
            $rows[] = ['uid' => (int)self::text($row['uid'] ?? 0), 'sorting' => (int)self::text($row['sorting'] ?? 0)];
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private function elements(int $page): array
    {
        return array_column($this->elementRows($page), 'uid');
    }

    /**
     * Default-language elements of the main column, in their stored order.
     *
     * @return list<array{uid: int, rowDescription: string}>
     */
    private function elementRows(int $page): array
    {
        $queryBuilder = $this->pool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder->select('uid', 'rowDescription')->from('tt_content')->where(
            $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($page, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('colPos', 0),
            $queryBuilder->expr()->eq('sys_language_uid', 0),
            $queryBuilder->expr()->eq('deleted', 0),
            $queryBuilder->expr()->eq('t3ver_wsid', 0),
        )->orderBy('sorting')->executeQuery();
        $rows = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $rows[] = ['uid' => (int)self::text($row['uid'] ?? 0), 'rowDescription' => self::text($row['rowDescription'] ?? '')];
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private function children(int $page): array
    {
        $queryBuilder = $this->pool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder->select('uid')->from('pages')->where(
            $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($page, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('sys_language_uid', 0),
            $queryBuilder->expr()->eq('deleted', 0),
            $queryBuilder->expr()->eq('t3ver_wsid', 0),
        )->orderBy('sorting')->executeQuery();

        return array_map(static fn (mixed $uid): int => (int)self::text($uid), $result->fetchFirstColumn());
    }

    private function move(string $table, int $uid, int $destination): void
    {
        $this->dataHandler([], [$table => [$uid => ['move' => $destination]]]);
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $dataMap
     * @param array<string, array<int, array<string, mixed>>> $commandMap
     */
    private function dataHandler(array $dataMap, array $commandMap): void
    {
        $handler = GeneralUtility::makeInstance(DataHandler::class);
        $handler->start($dataMap, $commandMap);
        $handler->process_datamap();
        $handler->process_cmdmap();
        if ($handler->errorLog !== []) {
            throw new \RuntimeException('DataHandler: ' . implode(' ', $handler->errorLog), 1794000206);
        }
    }

    private function pageWithinRootOrNull(string $slug, int $root): ?int
    {
        try {
            return $this->pageWithinRoot($slug, $root);
        } catch (\RuntimeException) {
            return null;
        }
    }

    /**
     * The one live default-language page with this slug below the site root.
     * Slugs repeat across the installation's sites, and uids differ between
     * the lab and production.
     */
    private function pageWithinRoot(string $slug, int $root): int
    {
        $connection = $this->pool->getConnectionForTable('pages');
        $candidates = $connection->select(['uid'], 'pages', ['slug' => $slug, 'deleted' => 0, 'sys_language_uid' => 0, 't3ver_wsid' => 0])->fetchFirstColumn();
        $matches = [];
        foreach ($candidates as $candidate) {
            $uid = (int)self::text($candidate);
            $visited = [];
            while ($uid > 0 && !isset($visited[$uid])) {
                if ($uid === $root) {
                    $matches[] = (int)self::text($candidate);
                    break;
                }
                $visited[$uid] = true;
                $uid = (int)self::text($connection->select(['pid'], 'pages', ['uid' => $uid, 'deleted' => 0])->fetchOne());
            }
        }
        if (count($matches) !== 1) {
            throw new \RuntimeException(sprintf('Expected one page %s within the Desiderio site, found %d.', $slug, count($matches)), 1794000207);
        }

        return $matches[0];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }
}
