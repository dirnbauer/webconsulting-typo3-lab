<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Applies a content payload to the live workspace.
 *
 * A payload (see sitepackage:content:export) lists records with what they
 * are expected to hold now (`expect`) and what they should hold (`set`).
 * A record whose current values differ from `expect` is skipped and reported,
 * so a payload never overwrites an edit made after it was exported. Only
 * fields that actually differ are written, so a second run changes nothing.
 *
 * Record actions: `update` (default), `hide`, `delete` (soft delete through
 * DataHandler) and `create` (with `key`, `pid` and optional `match`, which
 * finds an existing row to update instead of creating a second one).
 * `files: {field: [{source, folder, alternative, title}]}` replaces the file
 * references of a field; images are imported into `folder` by file name.
 *
 * Everything goes through DataHandler in workspace 0, so history, the
 * reference index, cache tags and the Solr record monitor all see the change.
 *
 *     ddev exec vendor/bin/typo3 sitepackage:content:apply \
 *       EXT:site_package/Resources/Private/Data/Content/camino/camino.payload.json --dry-run
 */
#[AsCommand(
    name: 'sitepackage:content:apply',
    description: 'Apply a content payload (expect/set per record) to the live workspace through DataHandler.',
)]
final class ContentApplyCommand extends Command
{
    /**
     * Columns a payload may set although they are not text: visibility and,
     * for fixing mislabelled records, the language.
     */
    private const EXTRA_COLUMNS = ['hidden', 'sys_language_uid', 'slug', 'nav_hide', 'CType', 'colPos', 'sorting', 'pid'];

    /** @var array<string, int> */
    private array $counts = ['updated' => 0, 'created' => 0, 'hidden' => 0, 'deleted' => 0, 'unchanged' => 0, 'skipped' => 0, 'files' => 0];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly TcaSchemaFactory $tcaSchemaFactory,
        private readonly StorageRepository $storageRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('payload', InputArgument::REQUIRED, 'Payload path (EXT:… or relative to the project)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change, write nothing')
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Only these records, e.g. tt_content:12,pages:4')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Write even where current values differ from expect')
            ->addOption('allow-production', null, InputOption::VALUE_NONE, 'Allow running in the Production context');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (Environment::getContext()->isProduction() && !$input->getOption('allow-production')) {
            $io->error('Refusing to write content in the Production context. Pass --allow-production if you mean it.');
            return Command::FAILURE;
        }

        $argument = $input->getArgument('payload');
        $path = GeneralUtility::getFileAbsFileName(is_string($argument) ? $argument : '');
        $payload = $path !== '' && is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
        if (!is_array($payload) || !is_array($payload['records'] ?? null)) {
            $io->error('No readable payload with a "records" list.');
            return Command::FAILURE;
        }
        $dryRun = (bool)$input->getOption('dry-run');
        $force = (bool)$input->getOption('force');
        $onlyOption = $input->getOption('only');
        $only = is_string($onlyOption) && $onlyOption !== '' ? array_map('trim', explode(',', $onlyOption)) : [];

        Bootstrap::initializeBackendAuthentication();
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof CommandLineUserAuthentication) {
            $io->error('No CLI backend user available.');
            return Command::FAILURE;
        }
        // Explicitly live; DataHandler would otherwise inherit the CLI user's workspace.
        $backendUser->setWorkspace(0);

        $dataMap = [];
        $commandMap = [];
        foreach ($payload['records'] as $index => $record) {
            if (!is_array($record)) {
                continue;
            }
            $table = $this->text($record['table'] ?? '');
            $uid = (int)$this->scalar($record['uid'] ?? 0);
            if ($only !== [] && !in_array($table . ':' . $uid, $only, true)) {
                continue;
            }
            if ($table === '' || !$this->tcaSchemaFactory->has($table)) {
                $io->warning(sprintf('Record %s: unknown table "%s".', (string)$index, $table));
                $this->counts['skipped']++;
                continue;
            }
            $this->planRecord($record, $table, $uid, $force, $dataMap, $commandMap, $io, $dryRun);
        }

        if ($dryRun) {
            $io->success(sprintf('Dry run: %s.', $this->summary()));
            return Command::SUCCESS;
        }
        if ($dataMap !== [] || $commandMap !== []) {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($dataMap, $commandMap);
            $dataHandler->process_datamap();
            $dataHandler->process_cmdmap();
            if ($dataHandler->errorLog !== []) {
                foreach ($dataHandler->errorLog as $error) {
                    $io->error($error);
                }
                return Command::FAILURE;
            }
        }
        $io->success(sprintf('%s. Flush caches next.', ucfirst($this->summary())));

        return Command::SUCCESS;
    }

    /**
     * @param array<mixed> $record
     * @param array<string, array<int|string, array<string, mixed>>> $dataMap
     * @param array<string, array<int, array<string, mixed>>> $commandMap
     */
    private function planRecord(array $record, string $table, int $uid, bool $force, array &$dataMap, array &$commandMap, SymfonyStyle $io, bool $dryRun): void
    {
        $action = $this->text($record['action'] ?? 'update');
        $set = $this->fields($record['set'] ?? null);

        if ($action === 'create') {
            $matched = $this->findMatch($table, $this->fields($record['match'] ?? null));
            if ($matched === null) {
                $key = $this->text($record['key'] ?? '');
                $key = str_starts_with($key, 'NEW') ? $key : 'NEW_' . substr(md5(serialize($record)), 0, 10);
                $pid = $record['pid'] ?? 0;
                $dataMap[$table][$key] = $this->validFields($table, $set, $io) + ['pid' => is_string($pid) ? $pid : (int)$this->scalar($pid)];
                $this->planFiles($record, $table, $key, (int)$this->scalar($pid), $dataMap, $commandMap, $io, $dryRun);
                $this->counts['created']++;
                $io->writeln(sprintf('  create %s %s', $table, $key));
                return;
            }
            $uid = $matched;
        }

        $current = $uid > 0 ? $this->currentRow($table, $uid) : null;
        if ($current === null) {
            if ($action === 'delete' && $this->isDeleted($table, $uid)) {
                $this->counts['unchanged']++;
                return;
            }
            $io->warning(sprintf('%s:%d does not exist (or is deleted / not live); skipped.', $table, $uid));
            $this->counts['skipped']++;
            return;
        }

        // A row that already holds the new values was applied before: its
        // expect values are gone, which is not a conflict. The file check
        // below still runs.
        if (!$force && !$this->alreadyApplied($action, $set, $current)) {
            foreach ($this->fields($record['expect'] ?? null) as $field => $expected) {
                if (!array_key_exists($field, $current) || $this->normalise($current[$field]) !== $this->normalise($expected)) {
                    $io->warning(sprintf('%s:%d.%s changed since export; skipped (use --force to overwrite).', $table, $uid, $field));
                    $this->counts['skipped']++;
                    return;
                }
            }
        }

        if ($action === 'delete') {
            $commandMap[$table][$uid]['delete'] = 1;
            $this->counts['deleted']++;
            $io->writeln(sprintf('  delete %s:%d', $table, $uid));
            return;
        }
        if ($action === 'hide') {
            $set['hidden'] = 1;
        }

        $changes = [];
        foreach ($this->validFields($table, $set, $io) as $field => $value) {
            if ($this->normalise($current[$field] ?? null) !== $this->normalise($value)) {
                $changes[$field] = $value;
            }
        }
        $filesChanged = $this->planFiles($record, $table, $uid, (int)$this->scalar($current['pid'] ?? 0), $dataMap, $commandMap, $io, $dryRun);

        if ($changes === [] && !$filesChanged) {
            $this->counts['unchanged']++;
            return;
        }
        if ($changes !== []) {
            $dataMap[$table][$uid] = ($dataMap[$table][$uid] ?? []) + $changes;
            foreach ($changes as $field => $value) {
                $io->writeln(sprintf('  %s:%d.%s: "%s" → "%s"', $table, $uid, $field, $this->excerpt($current[$field] ?? ''), $this->excerpt($value)));
            }
        }
        $action === 'hide' ? $this->counts['hidden']++ : $this->counts['updated']++;
    }

    /**
     * Replaces the file references of each listed field when the files or
     * their alt texts differ from what is attached now.
     *
     * @param array<mixed> $record
     * @param array<string, array<int|string, array<string, mixed>>> $dataMap
     * @param array<string, array<int, array<string, mixed>>> $commandMap
     */
    private function planFiles(array $record, string $table, int|string $uid, int $pid, array &$dataMap, array &$commandMap, SymfonyStyle $io, bool $dryRun): bool
    {
        $changed = false;
        foreach ($this->fields($record['files'] ?? null) as $field => $files) {
            if (!is_array($files)) {
                continue;
            }
            $wanted = [];
            foreach ($files as $file) {
                if (!is_array($file)) {
                    continue;
                }
                $source = $this->text($file['source'] ?? '');
                $folder = $this->text($file['folder'] ?? '1:/content-images/');
                $wanted[] = [
                    'source' => $source,
                    'folder' => $folder,
                    'name' => basename($source),
                    'alternative' => $this->text($file['alternative'] ?? ''),
                    'title' => $this->text($file['title'] ?? ''),
                ];
            }
            $existing = is_int($uid) ? $this->references($table, $uid, $field) : [];
            $same = count($existing) === count($wanted);
            foreach ($wanted as $position => $file) {
                $ref = $existing[$position] ?? null;
                if ($ref === null || $ref['name'] !== $file['name'] || $ref['alternative'] !== $file['alternative'] || $ref['title'] !== $file['title']) {
                    $same = false;
                }
            }
            if ($same) {
                continue;
            }
            $changed = true;
            $this->counts['files'] += count($wanted);
            $io->writeln(sprintf('  %s:%s.%s: %d file(s) → %s', $table, (string)$uid, $field, count($existing), implode(', ', array_column($wanted, 'name'))));
            if ($dryRun) {
                continue;
            }

            $newKeys = [];
            foreach ($wanted as $position => $file) {
                $fileUid = $this->importFile($file['source'], $file['folder']);
                $key = sprintf('NEW_ref_%s_%s_%d', $table, is_int($uid) ? (string)$uid : $uid, $position);
                // DataHandler does not fill the parent side of a new file
                // reference from the parent's field value: without these
                // three columns the reference is stored, but belongs to nothing.
                $dataMap['sys_file_reference'][$key] = [
                    'uid_local' => $fileUid,
                    'uid_foreign' => $uid,
                    'tablenames' => $table,
                    'fieldname' => $field,
                    'pid' => $pid,
                    'alternative' => $file['alternative'],
                    'title' => $file['title'],
                ];
                $newKeys[] = $key;
            }
            $dataMap[$table][$uid][$field] = implode(',', $newKeys);
            // Old references are deleted explicitly: they are found by
            // uid_foreign, so leaving them would keep them attached.
            foreach ($existing as $ref) {
                $commandMap['sys_file_reference'][$ref['uid']]['delete'] = 1;
            }
        }

        return $changed;
    }

    private function importFile(string $source, string $folderIdentifier): int
    {
        $absolute = GeneralUtility::getFileAbsFileName($source);
        if ($absolute === '' || !is_file($absolute)) {
            throw new \RuntimeException(sprintf('Image source not found: %s', $source), 1790000101);
        }
        [$storageUid, $folderPath] = array_pad(explode(':', $folderIdentifier, 2), 2, '/');
        $storage = $this->storageRepository->findByUid((int)$storageUid);
        if ($storage === null) {
            throw new \RuntimeException(sprintf('No file storage %s', $storageUid), 1790000103);
        }
        $folderPath = '/' . trim($folderPath, '/') . '/';
        if (!$storage->hasFolder($folderPath)) {
            $parent = $storage->getRootLevelFolder(false);
            foreach (array_filter(explode('/', $folderPath)) as $segment) {
                $parent = $parent->hasFolder($segment) ? $parent->getSubfolder($segment) : $parent->createFolder($segment);
            }
        }
        $folder = $storage->getFolder($folderPath);
        $name = basename($absolute);
        // Hashed file names make an existing file with the same name the same image.
        $file = $folder->hasFile($name) ? $storage->getFileInFolder($name, $folder) : null;
        if ($file === null) {
            $file = $storage->addFile($absolute, $folder, $name, DuplicationBehavior::CANCEL, false);
        }

        return $file->getUid();
    }

    /**
     * @return list<array{uid: int, name: string, alternative: string, title: string}>
     */
    private function references(string $table, int $uid, string $field): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder
            ->select('r.uid', 'r.alternative', 'r.title', 'f.name')
            ->from('sys_file_reference', 'r')
            ->leftJoin('r', 'sys_file', 'f', 'f.uid = r.uid_local')
            ->where(
                $queryBuilder->expr()->eq('r.tablenames', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('r.uid_foreign', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('r.fieldname', $queryBuilder->createNamedParameter($field)),
                $queryBuilder->expr()->eq('r.deleted', 0),
                $queryBuilder->expr()->eq('r.t3ver_wsid', 0),
            )
            ->orderBy('r.sorting_foreign')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn (array $row): array => [
            'uid' => (int)$this->scalar($row['uid'] ?? 0),
            'name' => $this->text($row['name'] ?? ''),
            'alternative' => $this->text($row['alternative'] ?? ''),
            'title' => $this->text($row['title'] ?? ''),
        ], array_values($rows));
    }

    /**
     * @param array<string, mixed> $match
     */
    private function findMatch(string $table, array $match): ?int
    {
        if ($match === []) {
            return null;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->select('uid')->from($table)->where($queryBuilder->expr()->eq('deleted', 0));
        if ($this->tcaSchemaFactory->get($table)->isWorkspaceAware()) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('t3ver_wsid', 0));
        }
        foreach ($match as $field => $value) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter(
                is_int($value) ? $value : $this->text($value),
                is_int($value) ? Connection::PARAM_INT : Connection::PARAM_STR
            )));
        }
        $uid = $queryBuilder->setMaxResults(1)->executeQuery()->fetchOne();

        return $uid === false ? null : (int)$this->scalar($uid);
    }

    /**
     * @param array<string, mixed> $set
     * @param array<string, mixed> $current
     */
    private function alreadyApplied(string $action, array $set, array $current): bool
    {
        if ($action === 'delete' || $set === []) {
            return false;
        }
        if ($action === 'hide') {
            $set['hidden'] = 1;
        }
        foreach ($set as $field => $value) {
            if (!array_key_exists($field, $current) || $this->normalise($current[$field]) !== $this->normalise($value)) {
                return false;
            }
        }

        return true;
    }

    private function isDeleted(string $table, int $uid): bool
    {
        if ($uid <= 0) {
            return false;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->count('uid')->from($table)->where(
            $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('deleted', 1),
        );

        $count = $queryBuilder->executeQuery()->fetchOne();

        return is_numeric($count) && (int)$count > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentRow(string $table, int $uid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->select('*')->from($table)->where(
            $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('deleted', 0),
        );
        if ($this->tcaSchemaFactory->get($table)->isWorkspaceAware()) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('t3ver_wsid', 0));
        }
        $row = $queryBuilder->executeQuery()->fetchAssociative();

        return is_array($row) ? $row : null;
    }

    /**
     * Only columns the table's TCA knows (plus a few structural ones) may be set.
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function validFields(string $table, array $fields, SymfonyStyle $io): array
    {
        $schema = $this->tcaSchemaFactory->get($table);
        $valid = [];
        foreach ($fields as $field => $value) {
            if ($schema->hasField($field) || in_array($field, self::EXTRA_COLUMNS, true)) {
                $valid[$field] = $value;
            } else {
                $io->warning(sprintf('%s has no field "%s"; ignored.', $table, $field));
            }
        }

        return $valid;
    }

    private function normalise(mixed $value): string
    {
        $text = is_scalar($value) ? (string)$value : '';

        // The RTE transformation rewrites whitespace between tags and lines,
        // so a stored text can differ from its payload only in whitespace.
        return trim((string)preg_replace(['/>\s+</u', '/\s+/u'], ['><', ' '], $text));
    }

    private function excerpt(mixed $value): string
    {
        return mb_strimwidth(str_replace("\n", ' ', $this->normalise($value)), 0, 70, '…');
    }

    private function summary(): string
    {
        return implode(', ', array_map(static fn (string $key, int $count): string => $count . ' ' . $key, array_keys($this->counts), $this->counts));
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $fields = [];
        foreach ($value as $key => $item) {
            $fields[(string)$key] = $item;
        }

        return $fields;
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }

    private function scalar(mixed $value): int|float|string|bool
    {
        return is_scalar($value) ? $value : 0;
    }
}
