<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webconsulting\SitePackage\ContentAudit\Canon;
use Webconsulting\SitePackage\ContentAudit\CanonChecker;
use Webconsulting\SitePackage\ContentAudit\ContentCollector;
use Webconsulting\SitePackage\ContentAudit\CopyMetrics;
use Webconsulting\SitePackage\ContentAudit\FieldRole;
use Webconsulting\SitePackage\ContentAudit\Finding;
use Webconsulting\SitePackage\ContentAudit\TextItem;

/**
 * Measures what each site says against the content canon.
 *
 * Read-only. Reports, per site and page, how much text there is, how long
 * the sentences are, the LIX readability score, and every place a string
 * breaks a rule in Resources/Private/Data/Content/canon.yaml. A content hash
 * per site shows whether two installs render the same copy.
 *
 *     ddev exec vendor/bin/typo3 sitepackage:content:audit --site=all
 *     ddev exec vendor/bin/typo3 sitepackage:content:audit --site=camino --details
 *     ddev exec vendor/bin/typo3 sitepackage:content:audit --format=json > var-host/audit.json
 *
 * `--baseline=<file>` takes an earlier JSON run and prints the change. The
 * command exits non-zero when `--fail-on-errors` is set and any error remains.
 */
#[AsCommand(
    name: 'sitepackage:content:audit',
    description: 'Measure every site\'s copy against the content canon (length, readability, facts, spelling).',
)]
final class ContentAuditCommand extends Command
{
    public function __construct(
        private readonly ContentCollector $collector,
        private readonly SiteFinder $siteFinder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Site key from the canon, a root page uid, a comma-separated list, or "all"', 'all')
            ->addOption('lang', null, InputOption::VALUE_REQUIRED, 'Language id', '0')
            ->addOption('include-library', null, InputOption::VALUE_NONE, 'Also read content elements in sysfolders (the element library)')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'md or json', 'md')
            ->addOption('baseline', null, InputOption::VALUE_REQUIRED, 'Earlier JSON output to compare with')
            ->addOption('details', null, InputOption::VALUE_NONE, 'List every finding (md format)')
            ->addOption('fail-on-errors', null, InputOption::VALUE_NONE, 'Exit with 1 if any error remains');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $canon = Canon::fromFile();
        $checker = new CanonChecker($canon);
        $language = (int)$this->option($input, 'lang');
        $includeLibrary = (bool)$input->getOption('include-library');

        $siteKeys = $this->resolveSites($canon, $this->option($input, 'site'));
        if ($siteKeys === []) {
            $output->writeln('<error>No matching site in the canon.</error>');
            return Command::FAILURE;
        }
        $allRoots = array_map(static fn (array $site): int => $site['root'], $canon->sites);

        $report = ['generated' => date('c'), 'language' => $language, 'sites' => []];
        foreach ($siteKeys as $siteKey) {
            $site = $canon->sites[$siteKey];
            $otherRoots = array_values(array_filter($allRoots, static fn (int $root): bool => $root !== $site['root']));
            $languageCode = $this->languageCode($site['root'], $language, $site['language']);
            $report['sites'][$siteKey] = $this->auditSite($checker, $siteKey, $site['root'], $otherRoots, $language, $languageCode, $includeLibrary);
        }

        $baselinePath = $this->option($input, 'baseline');
        $baseline = [];
        if ($baselinePath !== '') {
            $decoded = is_file($baselinePath) ? json_decode((string)file_get_contents($baselinePath), true) : null;
            $baseline = is_array($decoded) && is_array($decoded['sites'] ?? null) ? $decoded['sites'] : [];
        }

        if ($this->option($input, 'format') === 'json') {
            $output->writeln((string)json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->writeMarkdown($output, $report['sites'], $baseline, (bool)$input->getOption('details'));
        }

        $errors = array_sum(array_map(static fn (array $site): int => $site['errors'], $report['sites']));

        return $input->getOption('fail-on-errors') && $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param list<int> $otherRoots
     * @return array{root: int, language: string, pages: list<array<string, mixed>>, items: int, words: int, medianWordsPerPage: int, avgSentenceWords: float, lix: float, longSentenceShare: float, errors: int, warnings: int, rules: array<string, int>, findings: list<array<string, mixed>>, hash: string}
     */
    private function auditSite(CanonChecker $checker, string $siteKey, int $root, array $otherRoots, int $language, string $languageCode, bool $includeLibrary): array
    {
        $pages = [];
        $findings = [];
        $rules = [];
        $hashLines = [];
        $allText = [];
        $itemCount = 0;

        foreach ($this->collector->pageTree($root, $otherRoots) as $page) {
            if ($page['hidden']) {
                continue;
            }
            $isFolder = $page['doktype'] === 254;
            $items = $this->collector->collectPage($page['uid'], $language, !$isFolder || $includeLibrary);
            if ($items === []) {
                continue;
            }
            $pageText = [];
            $pageErrors = 0;
            $pageWarnings = 0;
            foreach ($items as $item) {
                $itemCount++;
                $plain = $item->plainText();
                $hashLines[] = $item->key() . '=' . $plain;
                if ($item->table !== 'pages' && $item->role !== FieldRole::Alt && $item->role !== FieldRole::Meta) {
                    $pageText[] = $plain;
                }
                $itemFindings = $item->role === FieldRole::Alt && $plain === ''
                    ? [new Finding('warning', 'alt_missing', 'image has no alt text', $item->file)]
                    : $checker->check($plain, $item->role, $languageCode, $siteKey, $page['legal']);
                foreach ($itemFindings as $finding) {
                    $finding->isError() ? $pageErrors++ : $pageWarnings++;
                    $rules[$finding->severity . ':' . $finding->rule] = ($rules[$finding->severity . ':' . $finding->rule] ?? 0) + 1;
                    $findings[] = $this->locate($finding, $item, $page['uid']);
                }
            }
            $metrics = CopyMetrics::measure(implode("\n", $pageText));
            $allText[] = implode("\n", $pageText);
            $pages[] = [
                'uid' => $page['uid'],
                'title' => $page['title'],
                'slug' => $page['slug'],
                'folder' => $isFolder,
                'legal' => $page['legal'],
                'items' => count($items),
                'words' => $metrics['words'],
                'avgSentenceWords' => $metrics['avgSentenceWords'],
                'maxSentenceWords' => $metrics['maxSentenceWords'],
                'lix' => $metrics['lix'],
                'errors' => $pageErrors,
                'warnings' => $pageWarnings,
            ];
        }

        $siteMetrics = CopyMetrics::measure(implode("\n", $allText));
        $sentences = CopyMetrics::sentences(implode("\n", $allText));
        $long = count(array_filter($sentences, static fn (string $sentence): bool => count(CopyMetrics::words($sentence)) > 20));
        $contentPages = array_values(array_filter($pages, static fn (array $page): bool => !$page['folder'] && !$page['legal']));
        $wordsPerPage = array_map(static fn (array $page): int => $page['words'], $contentPages);
        sort($wordsPerPage);
        sort($hashLines);
        ksort($rules);

        return [
            'root' => $root,
            'language' => $languageCode,
            'pages' => $pages,
            'items' => $itemCount,
            'words' => $siteMetrics['words'],
            'medianWordsPerPage' => $wordsPerPage === [] ? 0 : $wordsPerPage[intdiv(count($wordsPerPage), 2)],
            'avgSentenceWords' => $siteMetrics['avgSentenceWords'],
            'lix' => $siteMetrics['lix'],
            'longSentenceShare' => $sentences === [] ? 0.0 : round(100 * $long / count($sentences), 1),
            'errors' => array_sum(array_map(static fn (array $page): int => $page['errors'], $pages)),
            'warnings' => array_sum(array_map(static fn (array $page): int => $page['warnings'], $pages)),
            'rules' => $rules,
            'findings' => $findings,
            'hash' => hash('sha256', implode("\n", $hashLines)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function locate(Finding $finding, TextItem $item, int $pageUid): array
    {
        return $finding->toArray() + [
            'page' => $pageUid,
            'table' => $item->table,
            'uid' => $item->uid,
            'field' => $item->field,
            'element' => $item->elementUid,
            'type' => $item->elementType,
            'role' => $item->role->value,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $sites
     * @param array<mixed> $baseline
     */
    private function writeMarkdown(OutputInterface $output, array $sites, array $baseline, bool $details): void
    {
        $output->writeln('| Site | Pages | Words | Median words/page | Avg sentence | LIX | Long sentences | Errors | Warnings |');
        $output->writeln('|---|---:|---:|---:|---:|---:|---:|---:|---:|');
        foreach ($sites as $key => $site) {
            $before = is_array($baseline[$key] ?? null) ? $baseline[$key] : [];
            $pages = is_array($site['pages'] ?? null) ? $site['pages'] : [];
            $output->writeln(sprintf(
                '| %s | %d | %s | %s | %s | %s | %s%% | %s | %s |',
                $key,
                count($pages),
                $this->withBefore($site['words'] ?? 0, $before['words'] ?? null),
                $this->withBefore($site['medianWordsPerPage'] ?? 0, $before['medianWordsPerPage'] ?? null),
                $this->withBefore($site['avgSentenceWords'] ?? 0, $before['avgSentenceWords'] ?? null),
                $this->withBefore($site['lix'] ?? 0, $before['lix'] ?? null),
                $this->withBefore($site['longSentenceShare'] ?? 0, $before['longSentenceShare'] ?? null),
                $this->withBefore($site['errors'] ?? 0, $before['errors'] ?? null),
                $this->withBefore($site['warnings'] ?? 0, $before['warnings'] ?? null),
            ));
        }

        foreach ($sites as $key => $site) {
            $rules = is_array($site['rules'] ?? null) ? $site['rules'] : [];
            if ($rules === []) {
                continue;
            }
            $output->writeln('');
            $output->writeln(sprintf('**%s** — %s', $key, implode(', ', array_map(
                static fn (string $rule, mixed $count): string => $rule . ' ' . (is_scalar($count) ? (string)$count : ''),
                array_keys($rules),
                $rules
            ))));
            if (!$details) {
                continue;
            }
            $findings = is_array($site['findings'] ?? null) ? $site['findings'] : [];
            foreach ($findings as $finding) {
                if (!is_array($finding)) {
                    continue;
                }
                $output->writeln(sprintf(
                    '- %s `%s` page %s %s:%s.%s (%s): %s — "%s"',
                    $this->text($finding['severity'] ?? ''),
                    $this->text($finding['rule'] ?? ''),
                    $this->text($finding['page'] ?? ''),
                    $this->text($finding['table'] ?? ''),
                    $this->text($finding['uid'] ?? ''),
                    $this->text($finding['field'] ?? ''),
                    $this->text($finding['type'] ?? ''),
                    $this->text($finding['message'] ?? ''),
                    mb_strimwidth($this->text($finding['excerpt'] ?? ''), 0, 90, '…')
                ));
            }
        }
    }

    private function withBefore(mixed $now, mixed $before): string
    {
        $now = is_scalar($now) ? (string)$now : '';
        if (!is_scalar($before)) {
            return $now;
        }

        return (string)$before === $now ? $now : sprintf('%s → %s', (string)$before, $now);
    }

    /**
     * @return list<string>
     */
    private function resolveSites(Canon $canon, string $selection): array
    {
        if ($selection === '' || $selection === 'all') {
            return array_keys($canon->sites);
        }
        $keys = [];
        foreach (array_map('trim', explode(',', $selection)) as $part) {
            if (isset($canon->sites[$part])) {
                $keys[] = $part;
            } elseif (ctype_digit($part) && ($key = $canon->siteKeyForRoot((int)$part)) !== null) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    private function languageCode(int $root, int $language, string $default): string
    {
        if ($language === 0) {
            return $default;
        }
        try {
            return $this->siteFinder->getSiteByRootPageId($root)->getLanguageById($language)->getLocale()->getLanguageCode();
        } catch (\Throwable) {
            return $default;
        }
    }

    private function option(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);

        return is_scalar($value) ? (string)$value : '';
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }
}
