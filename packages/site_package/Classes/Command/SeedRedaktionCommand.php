<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Lab-owned, idempotent native Desiderio content. All writes use ContentApply/DataHandler.
 *
 * The app was renamed from Redaktion to Typewright (2026-11-08). Internal names
 * (command, payload path, `redaktion:*` markers, film id) stay; the page moves
 * from /software/redaktion to /software/typewright on the first run after the
 * rename (DataHandler adds the redirects, translations follow).
 */
#[AsCommand(name: 'sitepackage:seed-redaktion', description: 'Publish the Typewright (formerly Redaktion) software page and its hub entry.')]
final class SeedRedaktionCommand extends Command
{
    private const SLUG = '/software/typewright';
    private const LEGACY_SLUG = '/software/redaktion';
    /** Elements of the 2.2 page that the three feature tab groups replace: hidden, not deleted. */
    private const RETIRED = ['redaktion:tour', 'redaktion:shot_alt', 'redaktion:shot_editor', 'redaktion:shot_draft', 'redaktion:shot_preview', 'redaktion:shot_dark'];

    public function __construct(private readonly ConnectionPool $pool, private readonly SiteFinder $sites)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without writing.')
            ->addOption('allow-production', null, InputOption::VALUE_NONE, 'Publish in Production.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (Environment::getContext()->isProduction() && !$input->getOption('allow-production')) {
            throw new \RuntimeException('Production publishing requires --allow-production.');
        }
        $path = GeneralUtility::getFileAbsFileName('EXT:site_package/Resources/Private/Data/Content/redaktion/redaktion.payload.json');
        $payload = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $root = $this->sites->getSiteByIdentifier('desiderio')->getRootPageId();
        $parent = $this->pageWithinRoot('/software', $root);
        $payload['root'] = $root;
        // First run after the rename: find the page by its old slug; the payload sets the new one.
        $legacy = $this->pageWithinRootOrNull(self::LEGACY_SLUG, $root);
        $renaming = $legacy !== null && $this->pageWithinRootOrNull(self::SLUG, $root) === null;
        foreach ($payload['records'] as &$record) {
            if ($record['pid'] === 0) {
                $record['pid'] = $parent;
            }
            if (($record['match']['pid'] ?? null) === 0) {
                $record['match']['pid'] = $parent;
            }
            if ($renaming && ($record['table'] ?? '') === 'pages' && ($record['match']['slug'] ?? null) === self::SLUG) {
                $record['match']['slug'] = self::LEGACY_SLUG;
            }
        }
        unset($record);
        $page = $renaming ? $legacy : $this->pageWithinRootOrNull(self::SLUG, $root);
        if ($page !== null) {
            foreach ($this->retiredElements($page) as $uid) {
                $payload['records'][] = ['table' => 'tt_content', 'uid' => $uid, 'action' => 'hide'];
            }
        }
        // Remove hard-coded product counts now that the section can grow.
        $replacements = [
            'Software by webconsulting: four products we run' => 'Software by webconsulting: tools for real work',
            'Four products built on this lab’s stack: T3 Ops for TYPO3 fleets, Ledger for the month-end, MedAtlas Austria for medicine shortages and CapexOne Plus.' => 'Tools built by webconsulting: T3 Ops, Ledger, MedAtlas Austria, CapexOne Plus and Typewright, a calm Mac workspace for TYPO3 content.',
            // The 2.2 wording, before the app was renamed to Typewright.
            'Tools built by webconsulting: T3 Ops, Ledger, MedAtlas Austria, CapexOne Plus and Redaktion, a focused desktop workspace for TYPO3 news.' => 'Tools built by webconsulting: T3 Ops, Ledger, MedAtlas Austria, CapexOne Plus and Typewright, a calm Mac workspace for TYPO3 content.',
            'Four products on this lab’s stack: T3 Ops runs TYPO3 fleets, Ledger closes the month, MedAtlas tracks shortages, CapexOne Plus governs capital projects.' => 'Software for TYPO3 operations, accounting, medicine availability, capital projects and focused editorial work.',
            'Four products from the same stack as this lab: Next.js, PostgreSQL, Coolify on EU servers, AI with people in charge.' => 'Web services and desktop tools built around real workflows. Open interfaces, clear responsibilities and people deciding what goes live.',
            'Four products' => 'Our software',
            'Four products, each one running' => 'Tools we build and use',
            'What the four products share, from the database to the people who approve.' => 'Shared principles, from the database to the people who approve.',
            'Each product is a Next.js app on a PostgreSQL database, written in TypeScript.' => 'Our web services use Next.js and PostgreSQL. Typewright brings a calm Electron workspace to the Mac and connects to TYPO3 through its API.',
            'Our web services use Next.js and PostgreSQL. Redaktion brings a focused Electron workspace to the Mac and connects to TYPO3 through its API.' => 'Our web services use Next.js and PostgreSQL. Typewright brings a calm Electron workspace to the Mac and connects to TYPO3 through its API.',
        ];
        foreach (['pages', 'tt_content', 'feature_cards_items'] as $table) {
            $connection = $this->pool->getConnectionForTable($table);
            $rows = $connection->select(['*'], $table, [$table === 'pages' ? 'uid' : 'pid' => $parent, 'sys_language_uid' => 0, 'deleted' => 0])->fetchAllAssociative();
            foreach ($rows as $row) {
                $expect = $set = [];
                foreach ($row as $field => $value) {
                    if (is_string($value) && isset($replacements[$value])) {
                        $expect[$field] = $value;
                        $set[$field] = $replacements[$value];
                    }
                }
                if ($set !== []) {
                    $payload['records'][] = ['table' => $table, 'uid' => (int)$row['uid'], 'pid' => $table === 'pages' ? $root : $parent, 'expect' => $expect, 'set' => $set];
                }
            }
        }
        $temporary = tempnam(Environment::getVarPath(), 'redaktion-content-');
        if ($temporary === false) {
            throw new \RuntimeException('Cannot prepare content payload.');
        }
        try {
            file_put_contents($temporary, json_encode($payload, JSON_THROW_ON_ERROR));
            $command = $this->getApplication()?->find('sitepackage:content:apply');
            if ($command === null) {
                throw new \RuntimeException('ContentApply is unavailable.');
            }
            $status = $command->run(new ArrayInput(['payload' => $temporary, '--dry-run' => (bool)$input->getOption('dry-run'), '--allow-production' => (bool)$input->getOption('allow-production')]), $output);
            if ($status === Command::SUCCESS && !$input->getOption('dry-run')) {
                $this->renameTranslationSlugs($root);
                $this->permanentRenameRedirects($root);
                $this->orderContent($payload['records'], $parent, $root);
            }
            return $status;
        } finally {
            unlink($temporary);
        }
    }


    /** @param list<array<string, mixed>> $records */
    private function orderContent(array $records, int $software, int $root): void
    {
        Bootstrap::initializeBackendAuthentication();
        $page = $this->pageWithinRoot(self::SLUG, $root);
        $connection = $this->pool->getConnectionForTable('tt_content');
        $rows = $connection->select(['uid', 'rowDescription'], 'tt_content', ['pid' => $page, 'sys_language_uid' => 0, 'deleted' => 0, 't3ver_wsid' => 0], [], ['sorting' => 'ASC'])->fetchAllAssociative();
        $byMarker = [];
        foreach ($rows as $row) {
            $byMarker[(string)$row['rowDescription']] = (int)$row['uid'];
        }
        $keys = ['NEW_redaktion_page' => $page];
        foreach ($records as $record) {
            if (($record['action'] ?? '') !== 'create' || ($record['table'] ?? '') === 'pages') { continue; }
            $match = $record['match'];
            foreach ($match as &$value) {
                if (is_string($value) && str_starts_with($value, '@')) { $value = $keys[substr($value, 1)]; }
            }
            unset($value);
            $match['deleted'] = 0;
            $ids = $this->pool->getConnectionForTable($record['table'])->select(['uid'], $record['table'], $match)->fetchFirstColumn();
            if (count($ids) !== 1) { throw new \RuntimeException('Expected one Typewright record: ' . $record['key']); }
            $keys[$record['key']] = (int)$ids[0];
        }
        foreach ($records as $record) {
            if (!isset($record['_collection'])) { continue; }
            $collection = $record['_collection'];
            $children = array_map(static fn(string $key): int => $keys[$key], $collection['keys']);
            $handler = GeneralUtility::makeInstance(DataHandler::class);
            $handler->start(['tt_content' => [$keys[$record['key']] => [$collection['field'] => implode(',', $children)]]], []);
            $handler->process_datamap();
            if ($handler->errorLog !== []) { throw new \RuntimeException('Cannot link Typewright collection.'); }
        }
        $wanted = [];
        foreach ($records as $record) {
            if (($record['table'] ?? '') === 'tt_content' && ($record['pid'] ?? '') === '@NEW_redaktion_page') {
                $marker = $record['set']['rowDescription'] ?? '';
                if (!is_string($marker) || !isset($byMarker[$marker])) {
                    throw new \RuntimeException('Missing Typewright content marker.');
                }
                $wanted[] = $byMarker[$marker];
            }
        }
        $current = array_values(array_filter(array_map(static fn(array $row): int => (int)$row['uid'], $rows), static fn(int $uid): bool => in_array($uid, $wanted, true)));
        if ($current !== $wanted) {
            $previous = null;
            foreach ($wanted as $uid) {
                $this->move('tt_content', $uid, $previous === null ? $page : -$previous);
                $previous = $uid;
            }
        }
        $hub = $connection->select(['uid', 'rowDescription'], 'tt_content', ['pid' => $software, 'sys_language_uid' => 0, 'deleted' => 0], [], ['sorting' => 'ASC'])->fetchAllAssociative();
        $hubUid = 0;
        foreach ($hub as $row) {
            if ($row['rowDescription'] === 'redaktion:hub') {
                $hubUid = (int)$row['uid'];
            }
        }
        $last = $hub === [] ? 0 : (int)$hub[array_key_last($hub)]['uid'];
        if ($hubUid > 0 && $last > 0 && $hubUid !== $last) {
            $this->move('tt_content', $hubUid, -$last);
        }
        $pages = $this->pool->getConnectionForTable('pages')->select(['uid'], 'pages', ['pid' => $software, 'sys_language_uid' => 0, 'deleted' => 0], [], ['sorting' => 'ASC'])->fetchFirstColumn();
        $lastPage = $pages === [] ? 0 : (int)$pages[array_key_last($pages)];
        if ($lastPage > 0 && $lastPage !== $page) {
            $this->move('pages', $page, -$lastPage);
        }
    }

    private function move(string $table, int $uid, int $destination): void
    {
        $handler = GeneralUtility::makeInstance(DataHandler::class);
        $handler->start([], [$table => [$uid => ['move' => $destination]]]);
        $handler->process_cmdmap();
        if ($handler->errorLog !== []) {
            throw new \RuntimeException('DataHandler could not order Typewright content.');
        }
    }

    /**
     * Elements of the page with a retired marker and their translations: hiding
     * the default-language record does not hide a translation, which a site in
     * free mode (and the backend) would still show.
     *
     * @return list<int>
     */
    private function retiredElements(int $page): array
    {
        $queryBuilder = $this->pool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder->select('uid', 'rowDescription')->from('tt_content')->where(
            $queryBuilder->expr()->eq('pid', $page),
            $queryBuilder->expr()->eq('sys_language_uid', 0),
            $queryBuilder->expr()->eq('deleted', 0),
            $queryBuilder->expr()->eq('t3ver_wsid', 0),
        )->executeQuery()->fetchAllAssociative();
        $uids = [];
        foreach ($rows as $row) {
            if (in_array((string)$row['rowDescription'], self::RETIRED, true)) {
                $uids[] = (int)$row['uid'];
            }
        }
        if ($uids === []) {
            return [];
        }
        $queryBuilder = $this->pool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        $translations = $queryBuilder->select('uid')->from('tt_content')->where(
            $queryBuilder->expr()->in('l18n_parent', $uids),
            $queryBuilder->expr()->gt('sys_language_uid', 0),
            $queryBuilder->expr()->eq('deleted', 0),
            $queryBuilder->expr()->eq('hidden', 0),
            $queryBuilder->expr()->eq('t3ver_wsid', 0),
        )->executeQuery()->fetchFirstColumn();
        return array_merge($uids, array_map('intval', $translations));
    }

    /**
     * Translations keep their own slug: after the rename their last segment
     * still says "redaktion". DataHandler rewrites it (and adds a redirect).
     */
    private function renameTranslationSlugs(int $root): void
    {
        Bootstrap::initializeBackendAuthentication();
        $page = $this->pageWithinRoot(self::SLUG, $root);
        $rows = $this->pool->getConnectionForTable('pages')
            ->select(['uid', 'slug'], 'pages', ['l10n_parent' => $page, 'deleted' => 0, 't3ver_wsid' => 0])
            ->fetchAllAssociative();
        $data = [];
        foreach ($rows as $row) {
            $slug = (string)$row['slug'];
            if (preg_match('#/redaktion$#', $slug) === 1) {
                $data['pages'][(int)$row['uid']] = ['slug' => (string)preg_replace('#/redaktion$#', '/typewright', $slug)];
            }
        }
        if ($data === []) {
            return;
        }
        $handler = GeneralUtility::makeInstance(DataHandler::class);
        $handler->start($data, []);
        $handler->process_datamap();
        if ($handler->errorLog !== []) {
            throw new \RuntimeException('DataHandler could not rename the Typewright page translations: ' . implode(' ', $handler->errorLog));
        }
    }

    /**
     * The rename is permanent: the redirects TYPO3 made from the /software/redaktion
     * addresses (307 by default) answer 301, so search engines move the page.
     */
    private function permanentRenameRedirects(int $root): void
    {
        $page = $this->pageWithinRoot(self::SLUG, $root);
        $queryBuilder = $this->pool->getQueryBuilderForTable('sys_redirect');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->update('sys_redirect')
            ->set('target_statuscode', 301)
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('target_statuscode', 307),
                $queryBuilder->expr()->like('source_path', $queryBuilder->createNamedParameter('%/software/redaktion%')),
                $queryBuilder->expr()->like('target', $queryBuilder->createNamedParameter('t3://page?uid=' . $page . '&%')),
            )
            ->executeStatement();
    }

    private function pageWithinRootOrNull(string $slug, int $root): ?int
    {
        try {
            return $this->pageWithinRoot($slug, $root);
        } catch (\RuntimeException) {
            return null;
        }
    }

    private function pageWithinRoot(string $slug, int $root): int
    {
        $connection = $this->pool->getConnectionForTable('pages');
        // Live records only: a workspace version carries the same slug.
        $candidates = $connection->select(['uid'], 'pages', ['slug' => $slug, 'deleted' => 0, 'sys_language_uid' => 0, 't3ver_wsid' => 0])->fetchFirstColumn();
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
            throw new \RuntimeException('Expected one Software page within the lab.');
        }
        return $matches[0];
    }
}
