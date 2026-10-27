<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The styleguide seed builds the breadcrumb demo and the RTE regression page
 * with the core Text element. These pages belong to the Desiderio site, so
 * this turns each of those elements into Split Text & Media and keeps the
 * HTML: bodytext becomes content, subheader becomes subheadline. Every
 * language is converted, and a second run finds nothing left to do.
 *
 * Run it after desiderio:styleguide:seed and before
 * sitepackage:content:translate. A reseed recreates the core elements, and
 * this command puts the Desiderio element back before the translations.
 *
 *     ddev exec vendor/bin/typo3 sitepackage:classic-text:convert --dry-run
 */
#[AsCommand(
    name: 'sitepackage:classic-text:convert',
    description: 'Turn the remaining core Text elements on Desiderio subpages into Split Text & Media.',
)]
final class ConvertClassicTextCommand extends Command
{
    private const string SITE = 'desiderio';

    /** @var list<string> */
    private const array SLUGS = [
        '/content-types/navigation-wayfinding/wayfinding-patterns',
        '/content-types/navigation-wayfinding/wayfinding-patterns/breadcrumb-trails',
        '/content-types/navigation-wayfinding/wayfinding-patterns/breadcrumb-trails/truncation-behaviour',
        '/content-types/navigation-wayfinding/wayfinding-patterns/breadcrumb-trails/truncation-behaviour/deeply-nested-example',
        '/content-types/rte-combinations',
    ];

    private bool $dryRun = false;
    private int $converted = 0;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change, write nothing')
            ->addOption('allow-production', null, InputOption::VALUE_NONE, 'Allow running in the Production context');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->dryRun = (bool)$input->getOption('dry-run');
        if (!$this->dryRun && Environment::getContext()->isProduction() && !$input->getOption('allow-production')) {
            $io->error('Refusing to write content in the Production context. Pass --allow-production if you mean it.');
            return Command::FAILURE;
        }

        $root = $this->siteFinder->getSiteByIdentifier(self::SITE)->getRootPageId();
        if (!$this->dryRun) {
            Bootstrap::initializeBackendAuthentication();
        }

        $failed = false;
        foreach (self::SLUGS as $slug) {
            $page = $this->pageBySlug($slug, $root);
            if ($page === null) {
                $io->warning(sprintf('No page %s below the Desiderio root %d.', $slug, $root));
                $failed = true;
                continue;
            }
            foreach ($this->textElements($page) as $row) {
                $this->convert($row, $slug, $output);
            }
        }

        $io->success(sprintf('%s%d element(s) converted to Split Text & Media.', $this->dryRun ? 'Dry run, ' : '', $this->converted));

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function convert(array $row, string $slug, OutputInterface $output): void
    {
        $uid = (int)$row['uid'];
        $fields = [
            'CType' => 'desiderio_textmedia',
            'content' => (string)$row['bodytext'],
            'bodytext' => '',
            'shadcn_layout' => 'media-above',
        ];
        $subheader = trim((string)$row['subheader']);
        if ($subheader !== '' && trim((string)$row['subheadline']) === '') {
            $fields['subheadline'] = $subheader;
            $fields['subheader'] = '';
        }
        $this->converted++;
        $output->writeln(sprintf(
            'convert tt_content %d (%s, language %d)',
            $uid,
            $slug,
            (int)$row['sys_language_uid'],
        ));
        if ($this->dryRun) {
            return;
        }

        $handler = GeneralUtility::makeInstance(DataHandler::class);
        $handler->isImporting = true;
        $handler->dontProcessTransformations = true;
        $handler->start(['tt_content' => [$uid => $fields]], []);
        $handler->process_datamap();
        if ($handler->errorLog !== []) {
            throw new \RuntimeException(sprintf(
                'DataHandler refused tt_content %d: %s',
                $uid,
                implode(' | ', array_map(static fn (mixed $error): string => is_scalar($error) ? (string)$error : '?', $handler->errorLog)),
            ));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function textElements(int $page): array
    {
        $queryBuilder = $this->queryBuilder('tt_content');

        return $queryBuilder
            ->select('uid', 'sys_language_uid', 'bodytext', 'subheader', 'subheadline')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($page, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('text')),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->orderBy('sys_language_uid')
            ->addOrderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function pageBySlug(string $slug, int $root): ?int
    {
        $queryBuilder = $this->queryBuilder('pages');
        $candidates = $queryBuilder->select('uid')->from('pages')->where(
            $queryBuilder->expr()->eq('slug', $queryBuilder->createNamedParameter($slug)),
            $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
        )->executeQuery()->fetchFirstColumn();
        foreach ($candidates as $candidate) {
            $uid = (int)$candidate;
            $seen = [];
            for ($cursor = $uid; $cursor > 0 && !isset($seen[$cursor]); $cursor = $this->pagePid($cursor)) {
                if ($cursor === $root) {
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

    private function queryBuilder(string $table): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }
}
