<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Puts the overview films (Build/Film, render-films.mjs) on their pages of
 * the Desiderio site: a Video Feature Section right after the page's hero
 * (or at the top of a page without one) and, below it, an accordion with the
 * transcript. On the Themes page, the site package's DesiderioThemes template
 * renders the two above the preset overview.
 *
 * Every film is a folder in Resources/Private/Fixtures/Films/ whose film.json
 * names the MP4, the WebP poster and the WebVTT captions and holds the page
 * slug, the texts and the transcript. The files go into FAL (fileadmin/films/
 * <id>/); a file with the same content there is reused.
 *
 * Idempotent by comparison: the two elements are found by the marker in
 * rowDescription, only values that differ are written, so a second run
 * reports 0 changes. A value edited in the backend is set back. Everything
 * goes through DataHandler in the live workspace; sitepackage:content:translate
 * then adds the de/zh/hu versions from the translation memories.
 *
 *     ddev exec vendor/bin/typo3 sitepackage:films:seed --dry-run
 *     ddev exec vendor/bin/typo3 sitepackage:films:seed --film=docx-editor
 */
#[AsCommand(
    name: 'sitepackage:films:seed',
    description: 'Add the overview films (video after the hero, transcript below) to their Desiderio pages through DataHandler.',
)]
final class SeedFilmsCommand extends Command
{
    public const string FILMS = __DIR__ . '/../../Resources/Private/Fixtures/Films';
    private const string SITE = 'desiderio';
    private const string FOLDER = 'films';
    private const string TRANSCRIPT_HEADER = 'What the film says';
    private const string TRANSCRIPT_TITLE = 'Read the transcript';

    private bool $dryRun = false;
    private int $rootUid = 0;
    private int $newCounter = 0;
    private OutputInterface $output;

    /** @var array<string, int> */
    private array $counts = ['created' => 0, 'updated' => 0, 'moved' => 0, 'files' => 0, 'imported' => 0];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
        private readonly StorageRepository $storageRepository,
    ) {
        parent::__construct();
        $this->output = new NullOutput();
    }

    protected function configure(): void
    {
        $this
            ->addOption('film', null, InputOption::VALUE_REQUIRED, 'Only these films (ids, comma-separated)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change, write nothing')
            ->addOption('allow-production', null, InputOption::VALUE_NONE, 'Allow running in the Production context');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $this->dryRun = (bool)$input->getOption('dry-run');
        if (!$this->dryRun && Environment::getContext()->isProduction() && !$input->getOption('allow-production')) {
            $io->error('Refusing to write content in the Production context. Pass --allow-production if you mean it.');
            return Command::FAILURE;
        }
        $filter = $input->getOption('film');
        $only = is_string($filter) && $filter !== '' ? array_map('trim', explode(',', $filter)) : [];

        Bootstrap::initializeBackendAuthentication();
        $this->rootUid = $this->siteFinder->getSiteByIdentifier(self::SITE)->getRootPageId();

        $films = glob(self::FILMS . '/*/film.json') ?: [];
        $failed = false;
        foreach ($films as $manifestFile) {
            $film = json_decode((string)file_get_contents($manifestFile), true);
            if (!is_array($film) || !is_string($film['id'] ?? null)) {
                $io->warning(sprintf('%s is not a film manifest.', $manifestFile));
                $failed = true;
                continue;
            }
            if ($only !== [] && !in_array($film['id'], $only, true)) {
                continue;
            }
            try {
                $this->seed($film, dirname($manifestFile));
            } catch (\RuntimeException $exception) {
                $io->error(sprintf('%s: %s', $film['id'], $exception->getMessage()));
                $failed = true;
            }
        }

        $changes = $this->counts['created'] + $this->counts['updated'] + $this->counts['moved'] + $this->counts['files'];
        $io->success(sprintf(
            '%s%d change(s): %d created, %d updated, %d moved, %d file field(s) (%d file(s) imported).',
            $this->dryRun ? 'Dry run, ' : '',
            $changes,
            $this->counts['created'],
            $this->counts['updated'],
            $this->counts['moved'],
            $this->counts['files'],
            $this->counts['imported'],
        ));

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $film
     */
    private function seed(array $film, string $directory): void
    {
        $id = (string)$film['id'];
        $slug = $this->text($film, 'slug');
        $page = $this->pageBySlug($slug);
        if ($page === null) {
            throw new \RuntimeException(sprintf('No page %s below the Desiderio root %d.', $slug, $this->rootUid));
        }
        foreach (['mp4', 'poster', 'vtt'] as $key) {
            if (!is_file($directory . '/' . $this->text($film, $key))) {
                throw new \RuntimeException(sprintf('film.json names %s, which is not in %s.', $this->text($film, $key), $directory));
            }
        }

        $videoFields = [
            'CType' => 'desiderio_featurevideo',
            'colPos' => '0',
            'eyebrow' => 'Film',
            'header' => $this->text($film, 'header'),
            'description' => $this->text($film, 'description'),
            'video_url' => '',
        ];
        $transcriptFields = [
            'CType' => 'desiderio_accordion',
            'colPos' => '0',
            'header' => self::TRANSCRIPT_HEADER,
            'allow_multiple' => '1',
        ];
        $item = ['title' => self::TRANSCRIPT_TITLE, 'open_by_default' => '0', 'content' => $this->transcript($film)];

        $video = $this->element($page, $id, $videoFields, [], $this->intro($page));
        $transcript = $this->element($page, $id . '-transcript', $transcriptFields, ['desiderio_accordion_items' => $item], $video);
        if ($video === null || $transcript === null) {
            return;
        }

        $alt = $this->text($film, 'alt');
        $title = $this->text($film, 'title');
        $this->files($video, 'video_file', $directory . '/' . $this->text($film, 'mp4'), $id, ['alternative' => $alt, 'title' => $title, 'autoplay' => 1]);
        $this->files($video, 'poster', $directory . '/' . $this->text($film, 'poster'), $id, ['alternative' => $alt, 'title' => $title]);
        $this->files($video, 'captions_file', $directory . '/' . $this->text($film, 'vtt'), $id, []);
        $this->collection($transcript, 'desiderio_accordion_items', $item, $id);
        $this->order($page, $video, $transcript, $id);
    }

    /**
     * The element with the marker, brought to $fields; created after $after
     * (at the top without it) when it is missing, with its collection item.
     *
     * @param array<string, string> $fields
     * @param array<string, array<string, string>> $collections
     */
    private function element(int $page, string $key, array $fields, array $collections, ?int $after): ?int
    {
        $marker = sprintf('Seeded by sitepackage:films:seed (%s). A reseed replaces it.', $key);
        $fields['rowDescription'] = $marker;
        $uid = $this->byMarker($page, $marker);
        if ($uid !== null) {
            $this->update('tt_content', $uid, $fields, $key);
            return $uid;
        }

        $this->counts['created']++;
        $this->output->writeln(sprintf('create  tt_content %s on page %d', $key, $page));
        if ($this->dryRun) {
            return null;
        }
        $id = $this->newId();
        $dataMap = ['tt_content' => [$id => $fields + ['pid' => $after !== null ? -$after : $page, 'sys_language_uid' => 0]]];
        foreach ($collections as $field => $row) {
            $childId = $this->newId();
            $dataMap[$this->foreignTable($field)][$childId] = $row + ['pid' => $page, 'sys_language_uid' => 0];
            $dataMap['tt_content'][$id][$field] = $childId;
        }
        $handler = $this->process($dataMap);
        $uid = (int)($handler->substNEWwithIDs[$id] ?? 0);
        if ($uid <= 0) {
            throw new \RuntimeException(sprintf('DataHandler did not create %s.', $key));
        }

        return $uid;
    }

    /**
     * The accordion's one item: updated, created when missing; further items
     * (added in the backend) are deleted.
     *
     * @param array<string, string> $fields
     */
    private function collection(int $parent, string $field, array $fields, string $label): void
    {
        $table = $this->foreignTable($field);
        $children = $this->children($table, $parent);
        if ($children !== []) {
            $this->update($table, $children[0], $fields, $label . ' transcript');
        }
        if (count($children) === 1) {
            return;
        }
        $dataMap = [];
        $commandMap = [];
        if ($children === []) {
            $this->counts['created']++;
            $this->output->writeln(sprintf('create  %s %s', $table, $label));
            $id = $this->newId();
            $dataMap[$table][$id] = $fields + ['pid' => $this->pid($parent), 'sys_language_uid' => 0];
            $dataMap['tt_content'][$parent][$field] = $id;
        }
        foreach (array_slice($children, 1) as $surplus) {
            $this->counts['updated']++;
            $this->output->writeln(sprintf('delete  %s %d (%s)', $table, $surplus, $label));
            $commandMap[$table][$surplus]['delete'] = 1;
        }
        if (!$this->dryRun) {
            $this->process($dataMap, $commandMap);
        }
    }

    /**
     * Video, then transcript, right after the page's intro (a hero or a
     * header section as its first element), or at the top without one.
     */
    private function order(int $page, int $video, int $transcript, string $label): void
    {
        $intro = $this->intro($page, [$video, $transcript]);
        $wanted = [$video, $transcript];
        $all = $this->contentOf($page);
        if (array_slice($all, $intro === null ? 0 : 1, 2) === $wanted) {
            return;
        }
        $this->counts['moved']++;
        $this->output->writeln(sprintf('move    %s below %s', $label, $intro === null ? 'the top of the page' : 'tt_content ' . $intro));
        if ($this->dryRun) {
            return;
        }
        $this->process([], ['tt_content' => [$video => ['move' => $intro === null ? $page : -$intro]]]);
        $this->process([], ['tt_content' => [$transcript => ['move' => -$video]]]);
    }

    /**
     * Replaces a file field's reference when the file or its texts differ.
     *
     * @param array{alternative?: string, title?: string, autoplay?: int} $texts
     */
    private function files(int $uid, string $field, string $path, string $film, array $texts): void
    {
        $sha1 = (string)sha1_file($path);
        $existing = $this->references($uid, $field);
        if (count($existing) === 1
            && $existing[0]['sha1'] === $sha1
            && $existing[0]['alternative'] === ($texts['alternative'] ?? '')
            && $existing[0]['title'] === ($texts['title'] ?? '')
            && (int)$existing[0]['autoplay'] === ($texts['autoplay'] ?? 0)
        ) {
            return;
        }
        $this->counts['files']++;
        $this->output->writeln(sprintf('files   tt_content %d.%s: %s', $uid, $field, basename($path)));
        if ($this->dryRun) {
            return;
        }
        $file = $this->import($path, $film, $sha1);
        $id = $this->newId();
        $reference = ['uid_local' => $file->getUid(), 'uid_foreign' => $uid, 'tablenames' => 'tt_content', 'fieldname' => $field, 'pid' => $this->pid($uid), 'sys_language_uid' => 0] + $texts;
        $commandMap = [];
        foreach ($existing as $old) {
            $commandMap['sys_file_reference'][$old['uid']]['delete'] = 1;
        }
        $this->process(['sys_file_reference' => [$id => $reference], 'tt_content' => [$uid => [$field => $id]]], $commandMap);
    }

    private function import(string $path, string $film, string $sha1): File
    {
        $storage = $this->storageRepository->getDefaultStorage();
        if (!$storage instanceof ResourceStorage) {
            throw new \RuntimeException('No default file storage.');
        }
        $folderPath = '/' . self::FOLDER . '/' . $film . '/';
        $folder = $storage->hasFolder($folderPath) ? $storage->getFolder($folderPath) : $storage->createFolder(self::FOLDER . '/' . $film);
        foreach ($folder->getFiles() as $candidate) {
            if ($candidate->getSha1() === $sha1) {
                return $candidate;
            }
        }
        // A copy, so the fixture in the package is never moved.
        $temporary = GeneralUtility::tempnam('site-film-');
        try {
            copy($path, $temporary);
            $file = $storage->addFile($temporary, $folder, basename($path), DuplicationBehavior::RENAME, false);
        } finally {
            GeneralUtility::unlink_tempfile($temporary);
        }
        $this->counts['imported']++;

        return $file;
    }

    /**
     * @param array<string, mixed> $film
     */
    private function transcript(array $film): string
    {
        $lines = is_array($film['transcript'] ?? null) ? $film['transcript'] : [];
        $html = '';
        foreach ($lines as $line) {
            if (is_array($line) && is_string($line['at'] ?? null) && is_string($line['text'] ?? null)) {
                $html .= sprintf('<p><strong>%s</strong> %s</p>', htmlspecialchars($line['at']), htmlspecialchars($line['text'], ENT_NOQUOTES));
            }
        }

        return $html;
    }

    // ------------------------------------------------------------- records

    /**
     * @param array<string, string> $fields
     */
    private function update(string $table, int $uid, array $fields, string $label): void
    {
        $row = $this->queryBuilder($table)->select('*')->from($table)->where('uid = ' . $uid)->executeQuery()->fetchAssociative() ?: [];
        $changed = [];
        foreach ($fields as $name => $value) {
            if (!array_key_exists($name, $row)) {
                throw new \RuntimeException(sprintf('%s has no column %s.', $table, $name));
            }
            if (str_replace("\r\n", "\n", (string)$row[$name]) !== $value) {
                $changed[$name] = $value;
            }
        }
        if ($changed === []) {
            return;
        }
        $this->counts['updated']++;
        $this->output->writeln(sprintf('update  %s %d (%s): %s', $table, $uid, $label, implode(', ', array_keys($changed))));
        if (!$this->dryRun) {
            $this->process([$table => [$uid => $changed]]);
        }
    }

    private function byMarker(int $page, string $marker): ?int
    {
        $queryBuilder = $this->queryBuilder('tt_content');
        $uid = $queryBuilder->select('uid')->from('tt_content')->where(
            $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($page, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('rowDescription', $queryBuilder->createNamedParameter($marker)),
            ...$this->live($queryBuilder),
        )->orderBy('uid')->setMaxResults(1)->executeQuery()->fetchOne();

        return $uid === false ? null : (int)$uid;
    }

    /**
     * Uids of the page's default-language elements in colPos 0, in order.
     *
     * @param list<int> $without
     * @return list<int>
     */
    private function contentOf(int $page, array $without = []): array
    {
        $queryBuilder = $this->queryBuilder('tt_content');
        $uids = $queryBuilder->select('uid')->from('tt_content')->where(
            $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($page, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('colPos', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            ...$this->live($queryBuilder),
        )->orderBy('sorting')->addOrderBy('uid')->executeQuery()->fetchFirstColumn();

        return array_values(array_diff(array_map('intval', $uids), $without));
    }

    /**
     * The page's first element when it is a hero or a header section.
     *
     * @param list<int> $without
     */
    private function intro(int $page, array $without = []): ?int
    {
        $first = $this->contentOf($page, $without)[0] ?? null;
        if ($first === null) {
            return null;
        }
        $type = (string)$this->queryBuilder('tt_content')->select('CType')->from('tt_content')->where('uid = ' . $first)->executeQuery()->fetchOne();

        return preg_match('/^desiderio_(hero|headersection)/', $type) === 1 ? $first : null;
    }

    /**
     * @return list<int>
     */
    private function children(string $table, int $parent): array
    {
        $queryBuilder = $this->queryBuilder($table);
        $uids = $queryBuilder->select('uid')->from($table)->where(
            $queryBuilder->expr()->eq('foreign_table_parent_uid', $queryBuilder->createNamedParameter($parent, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            ...$this->live($queryBuilder),
        )->orderBy('sorting')->addOrderBy('uid')->executeQuery()->fetchFirstColumn();

        return array_map('intval', $uids);
    }

    /**
     * @return list<array{uid: int, sha1: string, alternative: string, title: string, autoplay: int}>
     */
    private function references(int $uid, string $field): array
    {
        $queryBuilder = $this->queryBuilder('sys_file_reference');
        $rows = $queryBuilder
            ->select('r.uid', 'r.alternative', 'r.title', 'r.autoplay', 'f.sha1')
            ->from('sys_file_reference', 'r')
            ->leftJoin('r', 'sys_file', 'f', $queryBuilder->expr()->eq('f.uid', $queryBuilder->quoteIdentifier('r.uid_local')))
            ->where(
                $queryBuilder->expr()->eq('r.uid_foreign', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('r.tablenames', $queryBuilder->createNamedParameter('tt_content')),
                $queryBuilder->expr()->eq('r.fieldname', $queryBuilder->createNamedParameter($field)),
                $queryBuilder->expr()->eq('r.sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('r.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('r.t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->orderBy('r.sorting_foreign')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn(array $row): array => [
            'uid' => (int)$row['uid'],
            'sha1' => (string)$row['sha1'],
            'alternative' => (string)$row['alternative'],
            'title' => (string)$row['title'],
            'autoplay' => (int)$row['autoplay'],
        ], $rows);
    }

    private function pageBySlug(string $slug): ?int
    {
        $queryBuilder = $this->queryBuilder('pages');
        $candidates = $queryBuilder->select('uid')->from('pages')->where(
            $queryBuilder->expr()->eq('slug', $queryBuilder->createNamedParameter($slug)),
            $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            ...$this->live($queryBuilder),
        )->executeQuery()->fetchFirstColumn();
        foreach ($candidates as $candidate) {
            $uid = (int)$candidate;
            $seen = [];
            for ($cursor = $uid; $cursor > 0 && !isset($seen[$cursor]); $cursor = $this->pagePid($cursor)) {
                if ($cursor === $this->rootUid) {
                    return $uid;
                }
                $seen[$cursor] = true;
            }
        }

        return null;
    }

    private function pagePid(int $uid): int
    {
        return (int)$this->queryBuilder('pages')->select('pid')->from('pages')->where('uid = ' . $uid)->executeQuery()->fetchOne();
    }

    private function pid(int $uid): int
    {
        return (int)$this->queryBuilder('tt_content')->select('pid')->from('tt_content')->where('uid = ' . $uid)->executeQuery()->fetchOne();
    }

    private function foreignTable(string $field): string
    {
        $table = $GLOBALS['TCA']['tt_content']['columns'][$field]['config']['foreign_table'] ?? null;
        if (!is_string($table) || $table === '') {
            throw new \RuntimeException(sprintf('tt_content.%s has no foreign_table.', $field));
        }

        return $table;
    }

    private function queryBuilder(string $table): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    /**
     * Live, not deleted rows; hidden ones count (an editor may have hidden one).
     *
     * @return list<string>
     */
    private function live(QueryBuilder $queryBuilder): array
    {
        return [
            (string)$queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            (string)$queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
        ];
    }

    /**
     * @param array<string, mixed> $film
     */
    private function text(array $film, string $key): string
    {
        $value = $film[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException(sprintf('film.json has no %s.', $key));
        }

        return $value;
    }

    private function newId(): string
    {
        // No "_" in NEW ids: DataHandler's remap stack reads <table>_<uid> out of them.
        return 'NEW' . substr(hash('sha256', spl_object_id($this) . '|' . (++$this->newCounter) . '|' . microtime()), 0, 24);
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $dataMap
     * @param array<string, array<int|string, array<string, mixed>>> $commandMap
     */
    private function process(array $dataMap, array $commandMap = []): DataHandler
    {
        $handler = GeneralUtility::makeInstance(DataHandler::class);
        $handler->isImporting = true;
        // Rich text is stored as written, so the next run compares equal.
        $handler->dontProcessTransformations = true;
        $handler->start($dataMap, $commandMap);
        $handler->process_datamap();
        $handler->process_cmdmap();
        if ($handler->errorLog !== []) {
            throw new \RuntimeException('DataHandler refused a write: ' . implode(' | ', array_map(static fn(mixed $error): string => is_scalar($error) ? (string)$error : '?', $handler->errorLog)));
        }

        return $handler;
    }
}
