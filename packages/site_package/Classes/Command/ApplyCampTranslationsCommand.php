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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Rebuild the en/zh/hu translations of the TYPO3 Camp München site from a JSON payload.
 *
 * Why a command and not raw SQL: the home page elements carry Content Blocks
 * Collections (stats, feature items, gallery items, how-to steps, FAQ items,
 * checklist items) and FAL references. TYPO3 v14's connected-mode inline
 * overlay only renders when the wiring is exactly what DataHandler's `localize`
 * command produces, so every translation is created by localizing the German
 * source and then filled with translated values.
 *
 * Everything is written in the LIVE workspace: the CLI backend user is put into
 * workspace 0 explicitly before DataHandler runs.
 *
 * The payload that produced the current site content lives at
 * `Resources/Private/Data/mtug-camp-translations.json` in this package. Because
 * `var/` is not shared with the DDEV container, copy it in before running:
 *
 *     cat packages/site_package/Resources/Private/Data/mtug-camp-translations.json \
 *       | ddev exec bash -c 'cat > /var/www/html/var/camp-translations.json'
 *     ddev exec vendor/bin/typo3 sitepackage:apply-camp-translations var/camp-translations.json
 *     ddev exec vendor/bin/typo3 cache:flush
 *
 * Re-running is idempotent: the en/zh/hu rows it owns are deleted and rebuilt,
 * so the translation uids change on every run. Draft rows in a workspace are
 * never touched.
 */
#[AsCommand(
    name: 'sitepackage:apply-camp-translations',
    description: 'Rebuild en/zh/hu translations for the mtug-camp-munich-2026 site from a JSON payload.',
)]
final class ApplyCampTranslationsCommand extends Command
{
    /** @var int[] */
    private const LANGUAGES = [1, 2, 3];

    public function __construct(private readonly ConnectionPool $connectionPool)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('payload', InputArgument::REQUIRED, 'Path to the JSON payload')
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Restrict to a comma separated list of source uids');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $argument = $input->getArgument('payload');
        $path = is_string($argument) ? $argument : '';
        if (!is_file($path)) {
            $io->error(sprintf('No such payload: %s', $path));
            return Command::FAILURE;
        }
        $payload = json_decode((string)file_get_contents($path), true);
        if (!is_array($payload)) {
            $io->error('The payload is not valid JSON.');
            return Command::FAILURE;
        }

        $only = $input->getOption('only');
        $onlyUids = is_string($only) && $only !== ''
            ? array_map(static fn (string $v): int => (int)trim($v), explode(',', $only))
            : null;

        Bootstrap::initializeBackendAuthentication();
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof CommandLineUserAuthentication) {
            $io->error('No CLI backend user available.');
            return Command::FAILURE;
        }
        // Explicitly live; DataHandler would otherwise inherit the CLI user's workspace.
        $backendUser->setWorkspace(0);

        foreach ($this->items($payload['elements'] ?? null) as $item) {
            $element = $this->fields($item);
            $source = $this->number($element['source'] ?? null);
            if ($source <= 0 || ($onlyUids !== null && !in_array($source, $onlyUids, true))) {
                continue;
            }
            $this->handleElement($element, $source, $io);
        }

        $pages = $this->fields($payload['pages'] ?? null);
        if ($pages !== []) {
            $data = [];
            foreach ($pages as $uid => $fields) {
                $data['pages'][(int)$uid] = $this->fields($fields);
            }
            $this->applyData($data, $io);
            $io->writeln(sprintf('Updated %d page rows.', count($pages)));
        }

        $io->success('Done. Flush caches next.');
        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $element
     */
    private function handleElement(array $element, int $source, SymfonyStyle $io): void
    {
        $childTable = $this->text($element['childTable'] ?? null);
        $childTable = $childTable === '' ? null : $childTable;
        $germanChildren = $childTable !== null ? $this->childUids($childTable, $source) : [];

        $this->purgeTranslations($source, $childTable, $germanChildren);

        $langPayloads = $this->fields($element['lang'] ?? null);
        foreach (self::LANGUAGES as $lang) {
            $spec = $this->fields($langPayloads[(string)$lang] ?? null);
            if ($spec === []) {
                continue;
            }

            $this->localize('tt_content', $source, $lang);
            $translation = $this->translationUid('tt_content', 'l18n_parent', $source, $lang);
            if ($translation === null) {
                $io->error(sprintf('Localizing tt_content %d into language %d produced nothing.', $source, $lang));
                continue;
            }

            $fields = $this->fields($spec['fields'] ?? null);
            $fields['hidden'] = 0;
            /** @var array<string, array<int, array<string, mixed>>> $data */
            $data = ['tt_content' => [$translation => $fields]];

            // FAL references on the element itself.
            foreach ($this->fields($spec['files'] ?? null) as $field => $refSpecs) {
                $germanRefs = $this->fileReferenceUids('tt_content', $source, $field);
                foreach ($this->items($refSpecs) as $i => $refFields) {
                    $germanRef = $germanRefs[$i] ?? null;
                    if ($germanRef === null) {
                        continue;
                    }
                    $localRef = $this->translationUid('sys_file_reference', 'l10n_parent', $germanRef, $lang);
                    if ($localRef !== null) {
                        $data['sys_file_reference'][$localRef] = $this->fields($refFields);
                    } else {
                        $io->warning(sprintf('No localized sys_file_reference for %d (%s, lang %d).', $germanRef, $field, $lang));
                    }
                }
            }

            // Collection children, matched positionally against the German ones.
            $childSpecs = $this->items($spec['children'] ?? null);
            if ($childTable !== null && $childSpecs !== []) {
                foreach ($childSpecs as $i => $item) {
                    $childSpec = $this->fields($item);
                    $germanChild = $germanChildren[$i] ?? null;
                    if ($germanChild === null) {
                        continue;
                    }
                    $localChild = $this->translationUid($childTable, 'l10n_parent', $germanChild, $lang);
                    if ($localChild === null) {
                        $io->warning(sprintf('No localized %s for child %d (lang %d).', $childTable, $germanChild, $lang));
                        continue;
                    }
                    $childFields = $this->fields($childSpec['fields'] ?? null);
                    $childFields['hidden'] = 0;
                    $data[$childTable][$localChild] = $childFields;

                    foreach ($this->fields($childSpec['files'] ?? null) as $field => $refSpecs) {
                        $germanRefs = $this->fileReferenceUids($childTable, $germanChild, $field);
                        foreach ($this->items($refSpecs) as $j => $refFields) {
                            $germanRef = $germanRefs[$j] ?? null;
                            if ($germanRef === null) {
                                continue;
                            }
                            $localRef = $this->translationUid('sys_file_reference', 'l10n_parent', $germanRef, $lang);
                            if ($localRef !== null) {
                                $data['sys_file_reference'][$localRef] = $this->fields($refFields);
                            } else {
                                $io->warning(sprintf('No localized sys_file_reference for child ref %d (lang %d).', $germanRef, $lang));
                            }
                        }
                    }
                }
            }

            $this->applyData($data, $io);
            $io->writeln(sprintf(
                'Source %d -> language %d: tt_content %d, %d children, %d file references.',
                $source,
                $lang,
                $translation,
                count($data[$childTable] ?? []),
                count($data['sys_file_reference'] ?? [])
            ));
        }
    }

    /**
     * Hard-delete the translations this command owns so `localize` starts clean.
     *
     * @param int[] $germanChildren
     */
    private function purgeTranslations(int $source, ?string $childTable, array $germanChildren): void
    {
        $oldTranslations = $this->translationUids('tt_content', 'l18n_parent', $source);
        $oldChildren = [];
        foreach ($oldTranslations as $old) {
            if ($childTable !== null) {
                $oldChildren = array_merge($oldChildren, $this->childUids($childTable, $old, false));
            }
        }
        foreach ($germanChildren as $germanChild) {
            $oldChildren = array_merge($oldChildren, $this->translationUids($childTable ?? '', 'l10n_parent', $germanChild));
        }
        $oldChildren = array_values(array_unique($oldChildren));

        $this->deleteFileReferences('tt_content', $oldTranslations);
        if ($childTable !== null && $oldChildren !== []) {
            $this->deleteFileReferences($childTable, $oldChildren);
            $this->deleteRows($childTable, $oldChildren);
        }
        $this->deleteRows('tt_content', $oldTranslations);
    }

    /** @param int[] $uids */
    private function deleteRows(string $table, array $uids): void
    {
        if ($table === '' || $uids === []) {
            return;
        }
        $in = implode(',', array_map($this->number(...), $uids));
        $this->connectionPool->getConnectionForTable($table)
            ->executeStatement("DELETE FROM `$table` WHERE uid IN ($in)");
    }

    /** @param int[] $uids */
    private function deleteFileReferences(string $table, array $uids): void
    {
        if ($uids === []) {
            return;
        }
        $in = implode(',', array_map($this->number(...), $uids));
        $this->connectionPool->getConnectionForTable('sys_file_reference')->executeStatement(
            "DELETE FROM `sys_file_reference` WHERE tablenames = :t AND uid_foreign IN ($in)",
            ['t' => $table]
        );
    }

    /** @return int[] */
    private function childUids(string $table, int $parentUid, bool $defaultLanguageOnly = true): array
    {
        if ($table === '') {
            return [];
        }
        $qb = $this->connectionPool->getQueryBuilderForTable($table);
        $qb->getRestrictions()->removeAll();
        $qb->select('uid')->from($table)
            ->where(
                $qb->expr()->eq('foreign_table_parent_uid', $parentUid),
                $qb->expr()->eq('deleted', 0),
                $qb->expr()->eq('t3ver_wsid', 0),
            )->orderBy('sorting');
        if ($defaultLanguageOnly) {
            $qb->andWhere($qb->expr()->eq('sys_language_uid', 0));
        }
        return array_map($this->number(...), $qb->executeQuery()->fetchFirstColumn());
    }

    /** @return int[] */
    private function fileReferenceUids(string $table, int $uid, string $field): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $qb->getRestrictions()->removeAll();
        return array_map($this->number(...), $qb->select('uid')->from('sys_file_reference')
            ->where(
                $qb->expr()->eq('tablenames', $qb->createNamedParameter($table)),
                $qb->expr()->eq('fieldname', $qb->createNamedParameter($field)),
                $qb->expr()->eq('uid_foreign', $uid),
                $qb->expr()->eq('sys_language_uid', 0),
                $qb->expr()->eq('deleted', 0),
                $qb->expr()->eq('t3ver_wsid', 0),
            )->orderBy('sorting_foreign')->executeQuery()->fetchFirstColumn());
    }

    /** @return int[] */
    private function translationUids(string $table, string $parentField, int $sourceUid): array
    {
        if ($table === '') {
            return [];
        }
        $qb = $this->connectionPool->getQueryBuilderForTable($table);
        $qb->getRestrictions()->removeAll();
        // t3ver_wsid = 0 on purpose: draft rows in a workspace belong to whoever
        // staged them and must survive a rebuild of the live translations.
        return array_map($this->number(...), $qb->select('uid')->from($table)
            ->where(
                $qb->expr()->eq($parentField, $sourceUid),
                $qb->expr()->in('sys_language_uid', self::LANGUAGES),
                $qb->expr()->eq('t3ver_wsid', 0),
            )->executeQuery()->fetchFirstColumn());
    }

    private function translationUid(string $table, string $parentField, int $sourceUid, int $lang): ?int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable($table);
        $qb->getRestrictions()->removeAll();
        $uid = $qb->select('uid')->from($table)
            ->where(
                $qb->expr()->eq($parentField, $sourceUid),
                $qb->expr()->eq('sys_language_uid', $lang),
                $qb->expr()->eq('deleted', 0),
                $qb->expr()->eq('t3ver_wsid', 0),
            )->orderBy('uid', 'DESC')->setMaxResults(1)->executeQuery()->fetchOne();
        return is_numeric($uid) ? (int)$uid : null;
    }

    private function localize(string $table, int $uid, int $lang): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [$table => [$uid => ['localize' => $lang]]]);
        $dataHandler->process_cmdmap();
        if ($dataHandler->errorLog !== []) {
            throw new \RuntimeException(
                sprintf('localize %s:%d -> %d failed: %s', $table, $uid, $lang, implode(' | ', $dataHandler->errorLog)),
                1758400001
            );
        }
    }

    /**
     * The payload is decoded JSON, so everything read out of it is mixed. These
     * four narrow it once, at the point of use, instead of asserting a shape the
     * file may not have: a hand-edited payload then skips the offending entry
     * rather than reaching DataHandler as the wrong type.
     *
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

    /** @return list<mixed> */
    private function items(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }

    private function number(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    /** @param array<string, array<int, array<string, mixed>>> $data */
    private function applyData(array $data, SymfonyStyle $io): void
    {
        if ($data === []) {
            return;
        }
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
        if ($dataHandler->errorLog !== []) {
            foreach ($dataHandler->errorLog as $error) {
                $io->error($error);
            }
            throw new \RuntimeException('DataHandler refused the write.', 1758400002);
        }
    }
}
