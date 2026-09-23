<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webconsulting\SitePackage\ContentAudit\Canon;
use Webconsulting\SitePackage\ContentAudit\ContentCollector;
use Webconsulting\SitePackage\ContentAudit\TextItem;

/**
 * Writes a site's current copy as a content payload skeleton.
 *
 * Each record carries `expect` (the values as they are now, so a later apply
 * refuses to overwrite rows that changed in between) and `set` (the same
 * values, for a writer to edit). `_context` says which page and element the
 * text belongs to and what each field is for; apply ignores it.
 *
 *     ddev exec vendor/bin/typo3 sitepackage:content:export --site=camino \
 *       > packages/site_package/Resources/Private/Data/Content/camino/camino.payload.json
 *
 * Apply the edited file with sitepackage:content:apply.
 */
#[AsCommand(
    name: 'sitepackage:content:export',
    description: 'Export a site\'s copy as an editable content payload (expect/set per record).',
)]
final class ContentExportCommand extends Command
{
    public function __construct(private readonly ContentCollector $collector)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Site key from the canon or a root page uid')
            ->addOption('pages', null, InputOption::VALUE_REQUIRED, 'Only these page uids (comma-separated)')
            ->addOption('lang', null, InputOption::VALUE_REQUIRED, 'Language id', '0')
            ->addOption('include-library', null, InputOption::VALUE_NONE, 'Also export content elements in sysfolders');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $canon = Canon::fromFile();
        $selection = is_scalar($input->getOption('site')) ? (string)$input->getOption('site') : '';
        $siteKey = isset($canon->sites[$selection]) ? $selection : (ctype_digit($selection) ? $canon->siteKeyForRoot((int)$selection) : null);
        if ($siteKey === null) {
            $output->writeln('<error>Pass --site with a site key from the canon or its root page uid.</error>');
            return Command::FAILURE;
        }
        $site = $canon->sites[$siteKey];
        $language = (int)(is_scalar($input->getOption('lang')) ? $input->getOption('lang') : 0);
        $pagesOption = is_scalar($input->getOption('pages')) ? (string)$input->getOption('pages') : '';
        $onlyPages = $pagesOption === '' ? [] : array_map('intval', explode(',', $pagesOption));
        $includeLibrary = (bool)$input->getOption('include-library');
        $otherRoots = array_values(array_filter(
            array_map(static fn (array $other): int => $other['root'], $canon->sites),
            static fn (int $root): bool => $root !== $site['root']
        ));

        $records = [];
        $roles = [];
        foreach ($this->collector->pageTree($site['root'], $otherRoots) as $page) {
            if ($onlyPages !== [] && !in_array($page['uid'], $onlyPages, true)) {
                continue;
            }
            $isFolder = $page['doktype'] === 254;
            foreach ($this->collector->collectPage($page['uid'], $language, !$isFolder || $includeLibrary) as $item) {
                $key = $item->table . ':' . $item->uid;
                $records[$key] ??= $this->record($item, $page['uid'], $page['title'], $page['hidden']);
                $records[$key]['expect'][$item->field] = $item->value;
                $records[$key]['set'][$item->field] = $item->value;
                $roles[$key][$item->field] = $item->role->value;
            }
        }

        foreach ($roles as $key => $fieldRoles) {
            $records[$key]['_context']['roles'] = $fieldRoles;
        }

        $payload = [
            'version' => 1,
            'site' => $siteKey,
            'root' => $site['root'],
            'language' => $language,
            'languageCode' => $language === 0 ? $site['language'] : '',
            'generated' => date('c'),
            'records' => array_values($records),
        ];
        $output->writeln((string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }

    /**
     * @return array{table: string, uid: int, expect: array<string, string>, set: array<string, string>, _context: array<string, mixed>}
     */
    private function record(TextItem $item, int $pageUid, string $pageTitle, bool $pageHidden): array
    {
        $context = ['page' => $pageUid, 'pageTitle' => $pageTitle, 'element' => $item->elementUid, 'type' => $item->elementType, 'roles' => []];
        if ($pageHidden) {
            $context['pageHidden'] = true;
        }
        if ($item->file !== '') {
            $context['file'] = $item->file;
        }
        $expect = [];
        if ($item->table === 'tt_content') {
            $expect['CType'] = $item->elementType;
        }

        return ['table' => $item->table, 'uid' => $item->uid, 'expect' => $expect, 'set' => [], '_context' => $context];
    }
}
