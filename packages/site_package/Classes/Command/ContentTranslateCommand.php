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
 *     ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio --lang=de --dry-run
 *
 * Source texts without a translation keep the source text and are reported.
 * `--missing=-` prints them as JSON (the input for a translator), and
 * `--fail-on-missing` turns them into a failure. The same JSON lists, under
 * `copied`, texts that were kept because their field name marks an
 * identifier but that read like copy: add those fields to `translateFields`
 * in the scope.
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
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Site key from the canon, e.g. desiderio')
            ->addOption('lang', null, InputOption::VALUE_REQUIRED, 'ISO codes of the target languages, comma-separated (default: every language of the site but the default one)')
            ->addOption('pages', null, InputOption::VALUE_REQUIRED, 'Only these page uids, comma-separated (and the records stored on them)')
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
        $siteKey = $this->option($input, 'site');
        if (!isset($canon->sites[$siteKey])) {
            $io->error(sprintf('Pass --site with a site key from the canon (%s).', implode(', ', array_keys($canon->sites))));
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
        $requested = $this->option($input, 'lang');
        if ($requested !== '') {
            $codes = array_map('trim', explode(',', $requested));
            $unknown = array_diff($codes, array_keys($languages));
            if ($unknown !== []) {
                $io->error(sprintf('The site has no language %s (it has %s).', implode(', ', $unknown), implode(', ', array_keys($languages))));
                return Command::FAILURE;
            }
            $languages = array_intersect_key($languages, array_flip($codes));
        }

        $pagesOption = $this->option($input, 'pages');
        $onlyPages = $pagesOption === '' ? [] : array_values(array_map('intval', explode(',', $pagesOption)));

        $directory = self::MEMORY_DIRECTORY . '/' . $siteKey;
        $scope = TranslationScope::fromFile($directory . '/scope.json');
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
        }

        $missingReport = ['site' => $siteKey, 'root' => $root, 'languages' => []];
        $missingTotal = 0;
        $rows = [];
        foreach ($languages as $code => $languageId) {
            $memory = TranslationMemory::fromFile($directory . '/' . $code . '.json', $code);
            $result = $this->translator->translate($root, $otherRoots, $languageId, $code, $memory, $scope, $dryRun, $onlyPages);
            $rows[] = [
                $code . ' (' . $languageId . ')',
                $memory->count(),
                $result->counts['pagesLocalized'],
                $result->counts['recordsLocalized'],
                $result->counts['childrenSynchronized'],
                $result->counts['recordsUpdated'] . ' / ' . $result->counts['fieldsUpdated'],
                count($result->missing),
                count($result->unused),
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

    private function option(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);

        return is_scalar($value) ? trim((string)$value) : '';
    }
}
