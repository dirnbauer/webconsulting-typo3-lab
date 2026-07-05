<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Seeds the de/zh/hu translations of the desiderio site's two
 * utility pages — the search page (uid 738) and the 404 page (uid 737).
 *
 * WHY A COMMAND (and not raw SQL)
 * -------------------------------
 * The 404 page's content is owned by desiderio's `desiderio:seed-styleguide-pages`,
 * which re-creates the elements with NEW uids on every run. The desiderio product
 * seeder is English-only by design, so these lab-specific translations live here
 * and must be re-applied after each reseed:
 *
 *     ddev exec vendor/bin/typo3 sitepackage:seed-utility-translations
 *     ddev exec vendor/bin/typo3 cache:flush
 *
 * The 404 sitemap-grid element carries a nested Content Blocks Collection
 * (sitemap_grid_groups -> sitemap_grid_pages). Translating those child records
 * with raw INSERTs does NOT render — TYPO3 v14's connected-mode inline overlay
 * needs the exact wiring DataHandler produces. So content translations go through
 * DataHandler `localize` (correct wiring guaranteed); only the page rows, which
 * are trivial and stable, are written directly. Idempotent: re-running replaces
 * the translations it owns.
 */
#[AsCommand(
    name: 'sitepackage:seed-utility-translations',
    description: 'Seed de/zh/hu translations for the vienna-camp search + 404 pages (run after a styleguide reseed).',
)]
final class SeedUtilityTranslationsCommand extends Command
{
    private const SEARCH_PAGE = 738;
    private const NOTFOUND_PAGE = 737;

    /** sys_language_uid => page-level metadata */
    private const LANG_META = [
        1 => ['slugSearch' => '/suche', 'pageTitleSearch' => 'Suche', 'pageTitle404' => 'Seite nicht gefunden'],
        2 => ['slugSearch' => '/search', 'pageTitleSearch' => '搜索', 'pageTitle404' => '页面未找到'],
        3 => ['slugSearch' => '/kereses', 'pageTitleSearch' => 'Keresés', 'pageTitle404' => 'Az oldal nem található'],
    ];

    /** sys_language_uid => translated strings */
    private const T = [
        1 => [
            'searchHeader' => 'Suche',
            'hs_eyebrow' => 'Fehler 404',
            'hs_header' => 'Diese Seite hat heute frei',
            'hs_subheadline' => 'Die aufgerufene Adresse existiert nicht (mehr). Die gute Nachricht: Alles Sehenswerte ist nur einen Klick entfernt — und ja, sogar diese Fehlerseite besteht aus geseedeten Desiderio-Elementen.',
            'ch_header' => 'Was vermutlich passiert ist',
            'ch_content' => '<p>Eine vertippte Adresse, ein veraltetes Lesezeichen oder ein Link auf Inhalte, die beim Neuaufbau dieses Styleguides umgezogen sind. Nutzen Sie die Übersicht unten – oder gehen Sie direkt zurück zur Startseite.</p>',
            'ch_link_text' => 'Zurück zur Startseite',
            'sg_header' => 'Von hier geht\'s weiter',
            'cta_header' => 'Hier nichts — dort alles',
            'cta_description' => 'Die Startseite erzählt die ganze Geschichte: 255 Elemente, 15 Themes und der eine Befehl, der diese Website geseedet hat (404-Seite inklusive).',
            'cta_text' => 'Bring mich zur Startseite',
            'groups' => ['Hier starten', 'Für Ihr Team', 'Element-Kapitel', 'Recht & Projekt'],
            'links' => ['Startseite', 'Technische Funktionen', 'GEO & KI-Suche', 'Erfolgsgeschichten',
                'Agenturen & Integratoren', 'Inhouse-Teams', 'Freelancer & Solo-Devs',
                'Hero & Landing-Intros', 'Features & Nutzen', 'Pläne & Preise', 'Daten & Dashboards', 'Vertrauen & Social Proof',
                'Impressum', 'Datenschutzerklärung', 'Barrierefreiheit', 'GitHub-Repository'],
        ],
        2 => [
            'searchHeader' => '搜索',
            'hs_eyebrow' => '错误 404',
            'hs_header' => '这个页面今天休息了',
            'hs_subheadline' => '您打开的地址不存在（或已不存在）。好消息是：值得一看的内容都只需一次点击即可到达——是的，连这个错误页面也是由预置的 Desiderio 元素搭建而成。',
            'ch_header' => '可能发生了什么',
            'ch_content' => '<p>可能是地址输入有误、书签已过期，或是指向某个内容的链接——而该内容在本样式指南重新预置时已被移动。请使用下方的导航，或直接返回首页。</p>',
            'ch_link_text' => '返回首页',
            'sg_header' => '从这里找到方向',
            'cta_header' => '这里什么都没有 — 精彩都在那边',
            'cta_description' => '首页讲述了完整的故事：255 个元素、15 套主题，以及那条预置了整个网站（包括这个 404 页面）的命令。',
            'cta_text' => '带我回首页',
            'groups' => ['从这里开始', '为你的团队', '元素章节', '法律与项目'],
            'links' => ['首页', '技术特性', 'GEO 与 AI 搜索', '成功案例',
                '代理商与集成商', '内部团队', '自由职业者与独立开发者',
                '主视觉与落地页开篇', '功能与优势', '套餐与定价', '数据与仪表盘', '信任与社会认同',
                '法律声明', '隐私声明', '无障碍', 'GitHub 代码库'],
        ],
        3 => [
            'searchHeader' => 'Keresés',
            'hs_eyebrow' => '404-es hiba',
            'hs_header' => 'Ez az oldal ma szabadnapot vett ki',
            'hs_subheadline' => 'A megnyitott cím nem (már nem) létezik. A jó hír: minden, amit érdemes megnézni, egyetlen kattintásnyira van — és igen, még ez a hibaoldal is előre betöltött Desiderio-elemekből épült fel.',
            'ch_header' => 'Mi történhetett',
            'ch_content' => '<p>Elgépelt cím, elavult könyvjelző, vagy egy olyan tartalomra mutató hivatkozás, amely a styleguide újratöltésekor áthelyeződött. Használja az alábbi áttekintést, vagy térjen vissza egyenesen a kezdőlapra.</p>',
            'ch_link_text' => 'Vissza a kezdőlapra',
            'sg_header' => 'Innen megtalálja az utat',
            'cta_header' => 'Itt semmi — ott minden',
            'cta_description' => 'A kezdőlapon ott a teljes történet: 255 elem, 15 téma, és az az egyetlen parancs, amely az egész oldalt feltöltötte (a 404-es oldallal együtt).',
            'cta_text' => 'Vigyél a kezdőlapra',
            'groups' => ['Kezdje itt', 'A csapatának', 'Elem-fejezetek', 'Jog és projekt'],
            'links' => ['Kezdőlap', 'Technikai jellemzők', 'GEO és AI-keresés', 'Sikertörténetek',
                'Ügynökségek és integrátorok', 'Belső csapatok', 'Szabadúszók és egyéni fejlesztők',
                'Hero és landing bevezetők', 'Funkciók és előnyök', 'Csomagok és árazás', 'Adatok és irányítópultok', 'Bizalom és társas bizonyíték',
                'Impresszum', 'Adatvédelmi tájékoztató', 'Akadálymentesség', 'GitHub-tár'],
        ],
    ];

    /** CType => [content column => translation key] */
    private const ELEMENT_FIELDS = [
        'desiderio_headersection' => ['eyebrow' => 'hs_eyebrow', 'header' => 'hs_header', 'subheadline' => 'hs_subheadline'],
        'desiderio_contenthighlight' => ['header' => 'ch_header', 'content' => 'ch_content', 'link_text' => 'ch_link_text'],
        'desiderio_sitemapgrid' => ['header' => 'sg_header'],
        'desiderio_ctabanner' => ['header' => 'cta_header', 'description' => 'cta_description', 'cta_text' => 'cta_text'],
    ];
    private const ELEMENT_ORDER = ['desiderio_headersection', 'desiderio_contenthighlight', 'desiderio_sitemapgrid', 'desiderio_ctabanner'];

    public function __construct(private readonly ConnectionPool $connectionPool)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->initBackendUser();

        // Resolve current default-language source uids dynamically (they change on every reseed).
        $searchPlugin = $this->findContent(self::SEARCH_PAGE, 'solr_pi_results');
        $elements = [];
        foreach (self::ELEMENT_ORDER as $ctype) {
            $elements[$ctype] = $this->findContent(self::NOTFOUND_PAGE, $ctype);
        }
        $gridUid = $elements['desiderio_sitemapgrid'] ?? null;
        $groupUids = $gridUid ? $this->childUids('sitemap_grid_groups', $gridUid) : [];
        $linkUidsByGroup = [];
        foreach ($groupUids as $g) {
            $linkUidsByGroup[$g] = $this->childUids('sitemap_grid_pages', $g);
        }

        $io->writeln(sprintf('Sources: search plugin %s, 404 elements [%s], %d groups.',
            $searchPlugin ?? 'MISSING',
            implode(',', array_map(static fn ($u) => $u ?? 'MISSING', $elements)),
            count($groupUids)));

        foreach (array_keys(self::T) as $lang) {
            $this->cleanLanguage($lang, $searchPlugin, $elements, $groupUids, $linkUidsByGroup);
            $this->seedPageRows($lang);

            // Content + nested collections: DataHandler localize (correct wiring), then set values.
            $contentSources = array_values(array_filter([$searchPlugin, ...array_values($elements)]));
            $this->localize('tt_content', $contentSources, $lang);

            /** @var array<string, array<int, array<string, mixed>>> $data */
            $data = [];
            // search plugin
            if ($searchPlugin !== null) {
                $tUid = $this->translationUid('tt_content', $searchPlugin, $lang);
                if ($tUid) {
                    // hidden=0: DataHandler localize copies the record and tt_content
                    // has hideAtCopy, so the translation is created hidden.
                    $data['tt_content'][$tUid] = ['header' => self::T[$lang]['searchHeader'], 'hidden' => 0];
                }
            }
            // 404 elements
            foreach (self::ELEMENT_ORDER as $ctype) {
                $src = $elements[$ctype] ?? null;
                if ($src === null) {
                    continue;
                }
                $tUid = $this->translationUid('tt_content', $src, $lang);
                if (!$tUid) {
                    continue;
                }
                $fields = ['hidden' => 0];
                foreach (self::ELEMENT_FIELDS[$ctype] as $col => $key) {
                    $fields[$col] = self::T[$lang][$key];
                }
                $data['tt_content'][$tUid] = $fields;
            }
            // groups + links (localized automatically by the element localize above)
            $linkIndex = 0;
            foreach ($groupUids as $gi => $g) {
                $tg = $this->translationUid('sitemap_grid_groups', $g, $lang);
                if ($tg) {
                    $data['sitemap_grid_groups'][$tg] = ['title' => self::T[$lang]['groups'][$gi] ?? '', 'hidden' => 0];
                }
                foreach ($linkUidsByGroup[$g] as $l) {
                    $tl = $this->translationUid('sitemap_grid_pages', $l, $lang);
                    if ($tl) {
                        $data['sitemap_grid_pages'][$tl] = ['label' => self::T[$lang]['links'][$linkIndex] ?? '', 'hidden' => 0];
                    }
                    $linkIndex++;
                }
            }
            $this->applyData($data);

            $io->writeln(sprintf('Language %d: localized %d elements, %d groups, %d links.',
                $lang, count($contentSources), count($groupUids), $linkIndex));
        }

        $io->success('Done. Now flush caches: vendor/bin/typo3 cache:flush');
        return Command::SUCCESS;
    }

    private function findContent(int $pid, string $ctype): ?int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $qb->getRestrictions()->removeAll();
        $uid = $qb->select('uid')->from('tt_content')
            ->where(
                $qb->expr()->eq('pid', $pid),
                $qb->expr()->eq('CType', $qb->createNamedParameter($ctype)),
                $qb->expr()->eq('sys_language_uid', 0),
                $qb->expr()->eq('deleted', 0),
            )->orderBy('sorting')->setMaxResults(1)->executeQuery()->fetchOne();
        return is_numeric($uid) ? (int)$uid : null;
    }

    /** @return int[] */
    private function childUids(string $table, int $parentUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable($table);
        $qb->getRestrictions()->removeAll();
        $rows = $qb->select('uid')->from($table)
            ->where(
                $qb->expr()->eq('foreign_table_parent_uid', $parentUid),
                $qb->expr()->eq('sys_language_uid', 0),
                $qb->expr()->eq('deleted', 0),
            )->orderBy('sorting')->executeQuery()->fetchFirstColumn();
        return array_map(static fn (mixed $v): int => is_numeric($v) ? (int)$v : 0, $rows);
    }

    private function translationUid(string $table, int $sourceUid, int $lang): ?int
    {
        $parentField = $table === 'tt_content' ? 'l18n_parent' : 'l10n_parent';
        $qb = $this->connectionPool->getQueryBuilderForTable($table);
        $qb->getRestrictions()->removeAll();
        $uid = $qb->select('uid')->from($table)
            ->where(
                $qb->expr()->eq($parentField, $sourceUid),
                $qb->expr()->eq('sys_language_uid', $lang),
                $qb->expr()->eq('deleted', 0),
            )->setMaxResults(1)->executeQuery()->fetchOne();
        return is_numeric($uid) ? (int)$uid : null;
    }

    /**
     * @param array<string,?int> $elements
     * @param int[] $groupUids
     * @param array<int,int[]> $linkUidsByGroup
     */
    private function cleanLanguage(int $lang, ?int $searchPlugin, array $elements, array $groupUids, array $linkUidsByGroup): void
    {
        // Hard-delete translations this command owns so DataHandler localize starts clean.
        $delete = function (string $table, string $parentField, array $parents) use ($lang): void {
            $parents = array_values(array_filter($parents));
            if ($parents === []) {
                return;
            }
            $conn = $this->connectionPool->getConnectionForTable($table);
            $in = implode(',', array_map(static fn (mixed $v): int => is_numeric($v) ? (int)$v : 0, $parents));
            $conn->executeStatement(
                "DELETE FROM `$table` WHERE `$parentField` IN ($in) AND sys_language_uid = :l",
                ['l' => $lang]
            );
        };
        $delete('tt_content', 'l18n_parent', [$searchPlugin, ...array_values($elements)]);
        $delete('sitemap_grid_groups', 'l10n_parent', $groupUids);
        $delete('sitemap_grid_pages', 'l10n_parent', array_merge([], ...array_values($linkUidsByGroup)));
    }

    /** @param int[] $sourceUids */
    private function localize(string $table, array $sourceUids, int $lang): void
    {
        if ($sourceUids === []) {
            return;
        }
        $cmd = [$table => []];
        foreach ($sourceUids as $uid) {
            $cmd[$table][$uid] = ['localize' => $lang];
        }
        $dh = GeneralUtility::makeInstance(DataHandler::class);
        $dh->start([], $cmd);
        $dh->process_cmdmap();
    }

    /** @param array<string,array<int,array<string,mixed>>> $data */
    private function applyData(array $data): void
    {
        if ($data === []) {
            return;
        }
        $dh = GeneralUtility::makeInstance(DataHandler::class);
        $dh->start($data, []);
        $dh->process_datamap();
    }

    private function seedPageRows(int $lang): void
    {
        $meta = self::LANG_META[$lang];
        $this->upsertPageTranslation(self::SEARCH_PAGE, $lang, $meta['pageTitleSearch'], $meta['slugSearch']);
        $this->upsertPageTranslation(self::NOTFOUND_PAGE, $lang, $meta['pageTitle404'], '/404');
    }

    private function upsertPageTranslation(int $parent, int $lang, string $title, string $slug): void
    {
        $conn = $this->connectionPool->getConnectionForTable('pages');
        $conn->executeStatement(
            'DELETE FROM pages WHERE l10n_parent = :p AND sys_language_uid = :l',
            ['p' => $parent, 'l' => $lang]
        );
        $cols = $this->columns('pages');
        $now = time();
        $select = [];
        $params = ['src' => $parent];
        $overrides = ['title' => $title, 'slug' => $slug, 'nav_title' => $title];
        foreach ($cols as $col) {
            if ($col === 'uid') {
                continue;
            }
            if (array_key_exists($col, $overrides)) {
                $select[] = ':o_' . $col . ' AS `' . $col . '`';
                $params['o_' . $col] = $overrides[$col];
            } elseif ($col === 'sys_language_uid') {
                $select[] = (string)$lang . ' AS `' . $col . '`';
            } elseif ($col === 'l10n_parent' || $col === 'l10n_source') {
                $select[] = (string)$parent . ' AS `' . $col . '`';
            } elseif ($col === 'l10n_diffsource') {
                $select[] = "'' AS `" . $col . '`';
            } elseif ($col === 'tstamp' || $col === 'crdate') {
                $select[] = (string)$now . ' AS `' . $col . '`';
            } else {
                $select[] = '`' . $col . '`';
            }
        }
        $insertCols = array_values(array_filter($cols, static fn (string $c): bool => $c !== 'uid'));
        $conn->executeStatement(
            'INSERT INTO pages (`' . implode('`, `', $insertCols) . '`) SELECT ' . implode(', ', $select) . ' FROM pages WHERE uid = :src',
            $params
        );
    }

    /** @return string[] */
    private function columns(string $table): array
    {
        $cols = [];
        foreach ($this->connectionPool->getConnectionForTable($table)
                     ->createSchemaManager()->listTableColumns($table) as $column) {
            $cols[] = $column->getName();
        }
        return $cols;
    }

    private function initBackendUser(): void
    {
        $row = $this->connectionPool->getConnectionForTable('be_users')
            ->select(['*'], 'be_users', ['admin' => 1, 'deleted' => 0, 'disable' => 0])
            ->fetchAssociative();
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $beUser->user = $row ?: ['uid' => 1, 'admin' => 1, 'username' => '_cli_seed_', 'workspace_id' => 0];
        $beUser->workspace = 0;
        if ($row) {
            $beUser->fetchGroupData();
        }
        $GLOBALS['BE_USER'] = $beUser;
    }
}
