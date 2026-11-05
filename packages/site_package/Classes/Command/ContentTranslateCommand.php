<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webconsulting\SitePackage\ContentAudit\Canon;
use Webconsulting\SitePackage\Translation\SiteTranslator;
use Webconsulting\SitePackage\Translation\TranslationFilter;
use Webconsulting\SitePackage\Translation\TranslationMemory;
use Webconsulting\SitePackage\Translation\TranslationScope;

/**
 * Translates a seeded site from its translation memories.
 *
 * The memories live in `Resources/Private/Data/Translations/<site>/<iso>.json`
 * (source text => translation), next to a `scope.json` that says which pages
 * other commands own and which record tables belong to the site. Because the
 * memory is keyed by text, not by uid, it survives a reseed: run the command
 * after the seeders and every page, element, collection item, image text and
 * record gets its translation again.
 *
 *     ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio
 *     ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio --languages=de --dry-run
 *
 * Targeted runs (combinable; everything outside the filter is not touched):
 *
 * - `--site=astryx`, `--site=1290` or `--site=astryx-typo3`: canon key, root
 *   page uid or site identifier; all three name the same site
 * - `--languages=de` or `--languages=1,3` (`--lang` is the same option):
 *   ISO codes or language ids of the site, mixed allowed
 * - `--pages=1296,1297`: only these pages and what is stored on them;
 *   with `--recursive` also every page below them (`--pages=1291 --recursive`)
 * - `--tables=tt_content`: only these record kinds: `pages` (the page rows),
 *   `tt_content` (the content of the pages) or a record table of the scope
 *   (`tx_news_domain_model_news` …); collection items and file references
 *   follow their parent
 *
 *     ddev exec vendor/bin/typo3 sitepackage:content:translate --site=1290 --languages=de --pages=1296 --recursive
 *     ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio --languages=de --tables=tx_news_domain_model_news --dry-run
 *
 * Source texts without a translation keep the source text and are reported.
 * `--missing=-` prints them as JSON (the input for a translator), and
 * `--fail-on-missing` turns them into a failure. The same JSON lists, under
 * `copied`, texts that were kept because their field name marks an
 * identifier but that read like copy: add those fields to `translateFields`
 * in the scope.
 *
 * The run does not update the Solr index queue record by record; reindex the
 * site afterwards (sitepackage:solr:reindex --index).
 */
#[AsCommand(
    name: 'sitepackage:content:translate',
    description: 'Translate a seeded site\'s pages, content and records from its translation memories (DataHandler localize, live workspace).',
)]
final class ContentTranslateCommand extends Command
{
    public const MEMORY_DIRECTORY = __DIR__ . '/../../Resources/Private/Data/Translations';

    public function __construct(
        private readonly SiteTranslator $translator,
        private readonly SiteFinder $siteFinder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'The site: canon key (desiderio), root page uid (505) or site identifier (astryx-typo3)')
            ->addOption('languages', null, InputOption::VALUE_REQUIRED, 'Target languages as ISO codes or language ids, comma-separated, e.g. de or 1,2 (default: every language of the site but the default one)')
            ->addOption('lang', null, InputOption::VALUE_REQUIRED, 'Same as --languages')
            ->addOption('pages', null, InputOption::VALUE_REQUIRED, 'Only these page uids, comma-separated (and the records stored on them)')
            ->addOption('recursive', null, InputOption::VALUE_NONE, 'With --pages: also every page below the listed pages')
            ->addOption('tables', null, InputOption::VALUE_REQUIRED, 'Only these record kinds, comma-separated: pages, tt_content and the record tables of the scope (default: all)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change, write nothing')
            ->addOption('missing', null, InputOption::VALUE_REQUIRED, 'Write the source texts without a translation as JSON to this file ("-" for standard output)')
            ->addOption('fail-on-missing', null, InputOption::VALUE_NONE, 'Exit with 1 if any source text has no translation')
            ->addOption('allow-production', null, InputOption::VALUE_NONE, 'Allow running in the Production context');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $missingTarget = $this->option($input, 'missing');
        // With --missing=- the JSON owns standard output; the report goes to stderr.
        $reportOutput = $missingTarget === '-' && $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $io = new SymfonyStyle($input, $reportOutput);

        $dryRun = (bool)$input->getOption('dry-run');
        if (!$dryRun && Environment::getContext()->isProduction() && !$input->getOption('allow-production')) {
            $io->error('Refusing to write content in the Production context. Pass --allow-production if you mean it.');
            return Command::FAILURE;
        }

        $canon = Canon::fromFile();
        $siteKey = $this->resolveSiteKey($canon, $this->option($input, 'site'));
        if ($siteKey === null) {
            $io->error(sprintf(
                'Pass --site with a site key from the canon (%s), the root page uid or the identifier of such a site.',
                implode(', ', array_keys(array_filter($canon->sites, static fn (array $site): bool => $site['root'] > 0)))
            ));
            return Command::FAILURE;
        }
        $root = $canon->sites[$siteKey]['root'];
        try {
            $site = $this->siteFinder->getSiteByRootPageId($root);
        } catch (SiteNotFoundException) {
            $io->error(sprintf('No site configuration with root page %d.', $root));
            return Command::FAILURE;
        }

        $languages = [];
        foreach ($site->getLanguages() as $siteLanguage) {
            if ($siteLanguage->getLanguageId() > 0) {
                $languages[$siteLanguage->getLocale()->getLanguageCode()] = $siteLanguage->getLanguageId();
            }
        }
        $requested = $this->option($input, 'languages');
        $alias = $this->option($input, 'lang');
        if ($requested !== '' && $alias !== '' && $requested !== $alias) {
            $io->error('Pass the languages once: --languages and --lang are the same option.');
            return Command::FAILURE;
        }
        try {
            $languages = self::resolveLanguages($requested !== '' ? $requested : $alias, $languages);
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        try {
            $filter = TranslationFilter::fromOptions($this->option($input, 'pages'), (bool)$input->getOption('recursive'), $this->option($input, 'tables'));
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        $directory = self::MEMORY_DIRECTORY . '/' . $siteKey;
        $scope = TranslationScope::fromFile($directory . '/scope.json');
        $unknownTables = $filter->unknownTables($scope->recordTables);
        if ($unknownTables !== []) {
            $io->error(sprintf(
                'Unknown --tables %s (the site has pages, tt_content%s).',
                implode(', ', $unknownTables),
                $scope->recordTables === [] ? '' : ', ' . implode(', ', $scope->recordTables)
            ));
            return Command::FAILURE;
        }
        $io->writeln(sprintf('Site %s (root %d), languages %s, %s.', $siteKey, $root, implode(', ', array_keys($languages)), $filter->describe()));
        $otherRoots = array_values(array_filter(
            array_map(static fn (array $other): int => $other['root'], $canon->sites),
            static fn (int $other): bool => $other !== $root && $other > 0
        ));

        if (!$dryRun) {
            Bootstrap::initializeBackendAuthentication();
            $backendUser = $GLOBALS['BE_USER'] ?? null;
            if (!$backendUser instanceof CommandLineUserAuthentication) {
                $io->error('No CLI backend user available.');
                return Command::FAILURE;
            }
            // Explicitly live; DataHandler would otherwise inherit the CLI user's workspace.
            $backendUser->setWorkspace(0);
            // A translation of a visible record is visible, as in the source.
            $backendUser->uc['neverHideAtCopy'] = true;
            // EXT:solr's record monitor builds the page's frontend TypoScript for
            // every written record: thousands of translations took hours. The
            // index is rebuilt after the run instead (see the message below).
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr']['monitoringType'] = 2;
        }

        $missingReport = ['site' => $siteKey, 'root' => $root, 'languages' => []];
        $missingTotal = 0;
        $rows = [];
        foreach ($languages as $code => $languageId) {
            $memory = TranslationMemory::fromFile($directory . '/' . $code . '.json', $code);
            $result = $this->translator->translate($root, $otherRoots, $languageId, $code, $memory, $scope, $dryRun, $filter);
            $rows[] = [
                $code . ' (' . $languageId . ')',
                $memory->count(),
                $result->counts['pagesLocalized'],
                $result->counts['recordsLocalized'],
                $result->counts['childrenSynchronized'],
                $result->counts['recordsUpdated'] . ' / ' . $result->counts['fieldsUpdated'],
                count($result->missing),
                // A filtered run asks for a part of the memory only.
                $filter->isActive() ? '–' : count($result->unused),
            ];
            $missingReport['languages'][$code] = $result->missing;
            $missingReport['copied'][$code] = $result->copied;
            $missingTotal += count($result->missing);
        }

        $io->table(['Language', 'Memory', 'New pages', 'New records', 'Synchronized', 'Updated rows / fields', 'Missing texts', 'Unused entries'], $rows);

        if ($missingTarget !== '') {
            $json = (string)json_encode($missingReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($missingTarget === '-') {
                $output->writeln($json, OutputInterface::OUTPUT_RAW);
            } else {
                file_put_contents($missingTarget, $json . "\n");
                $io->writeln(sprintf('Missing texts written to %s.', $missingTarget));
            }
        }

        if ($dryRun) {
            $io->success('Dry run: nothing was written.');
        } else {
            $io->success('Done. Flush caches next, then reindex Solr (sitepackage:solr:reindex --index).');
        }

        return $input->getOption('fail-on-missing') && $missingTotal > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Requested languages, as ISO codes or language ids, mixed: "de", "1,2",
     * "de,3". Empty = every language of the site.
     *
     * @param array<string, int> $languages ISO code => language id of the site's translations
     * @return array<string, int>
     */
    public static function resolveLanguages(string $requested, array $languages): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $requested)), static fn (string $part): bool => $part !== ''));
        if ($parts === []) {
            return $languages;
        }
        $selected = [];
        $unknown = [];
        foreach ($parts as $part) {
            $code = ctype_digit($part) ? array_search((int)$part, $languages, true) : (isset($languages[strtolower($part)]) ? strtolower($part) : false);
            if (!is_string($code)) {
                $unknown[] = $part;
                continue;
            }
            $selected[$code] = $languages[$code];
        }
        if ($unknown !== []) {
            $available = [];
            foreach ($languages as $code => $id) {
                $available[] = sprintf('%s (%d)', $code, $id);
            }
            throw new \InvalidArgumentException(sprintf(
                'The site has no language %s (it has %s).',
                implode(', ', $unknown),
                $available === [] ? 'none but the default' : implode(', ', $available)
            ), 1791900011);
        }

        // In the site's order, so a run reads the same however it was asked for.
        return array_intersect_key($languages, $selected);
    }

    /**
     * The canon key of a site given as canon key, root page uid or site identifier.
     */
    private function resolveSiteKey(Canon $canon, string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (isset($canon->sites[$value]) && $canon->sites[$value]['root'] > 0) {
            return $value;
        }
        if (ctype_digit($value)) {
            return $canon->siteKeyForRoot((int)$value);
        }
        try {
            return $canon->siteKeyForRoot($this->siteFinder->getSiteByIdentifier($value)->getRootPageId());
        } catch (SiteNotFoundException) {
            return null;
        }
    }

    private function option(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);

        return is_scalar($value) ? trim((string)$value) : '';
    }
}
