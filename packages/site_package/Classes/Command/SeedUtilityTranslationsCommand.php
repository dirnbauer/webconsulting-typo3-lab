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
 * Seeds translations for the desiderio site's utility pages:
 * - de/zh/hu for the search page (uid 738) and the 404 page (uid 737)
 * - de for the footer accessibility page (uid 736)
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
    description: 'Seed translations for the desiderio utility pages (run after a styleguide reseed).',
)]
final class SeedUtilityTranslationsCommand extends Command
{
    private const ACCESSIBILITY_PAGE = 736;
    private const SEARCH_PAGE = 738;
    private const NOTFOUND_PAGE = 737;

    /** sys_language_uid => page-level metadata */
    private const LANG_META = [
        1 => [
            'slugSearch' => '/suche',
            'pageTitleSearch' => 'Suche',
            'pageTitle404' => 'Seite nicht gefunden',
            'pageDescription404' => 'Die angeforderte Seite wurde nicht gefunden. Prüfen Sie die Webadresse oder nutzen Sie einen der Links unten, um fortzufahren.',
        ],
        2 => [
            'slugSearch' => '/search',
            'pageTitleSearch' => '搜索',
            'pageTitle404' => '页面未找到',
            'pageDescription404' => '找不到您请求的页面。请检查网址，或使用下方的链接继续浏览。',
        ],
        3 => [
            'slugSearch' => '/kereses',
            'pageTitleSearch' => 'Keresés',
            'pageTitle404' => 'Az oldal nem található',
            'pageDescription404' => 'A kért oldal nem található. Ellenőrizze a webcímet, vagy folytassa az alábbi hivatkozások egyikével.',
        ],
    ];

    private const NOTFOUND_EN = [
        'pageTitle' => 'Page not found',
        'description' => 'The page you requested could not be found. Check the web address, or use one of the links below to continue.',
        'sitemapHeader' => 'Important pages',
    ];

    /** sys_language_uid => translated strings */
    private const T = [
        1 => [
            'searchHeader' => 'Suche',
            'hs_eyebrow' => 'Fehler 404',
            'hs_header' => 'Seite nicht gefunden',
            'hs_subheadline' => 'Die angeforderte Seite wurde nicht gefunden.',
            'ch_header' => 'Zurück zur Startseite',
            'ch_content' => '<p>Nutzen Sie die Übersicht unten oder kehren Sie zur Startseite zurück.</p>',
            'ch_link_text' => 'Zurück zur Startseite',
            'sg_header' => 'Wichtige Seiten',
            'cta_header' => 'Zur Startseite',
            'cta_description' => 'Kehren Sie zur Startseite zurück.',
            'cta_text' => 'Zur Startseite',
            'groups' => ['Hier starten', 'Für Ihr Team', 'Element-Kapitel', 'Recht & Projekt'],
            'links' => ['Startseite', 'Technische Funktionen', 'GEO & KI-Suche', 'Erfolgsgeschichten',
                'Agenturen & Integratoren', 'Inhouse-Teams', 'Freelancer & Solo-Devs',
                'Hero & Landing-Intros', 'Features & Nutzen', 'Pläne & Preise', 'Daten & Dashboards', 'Vertrauen & Social Proof',
                'Impressum', 'Datenschutzerklärung', 'Barrierefreiheit', 'GitHub-Repository'],
        ],
        2 => [
            'searchHeader' => '搜索',
            'hs_eyebrow' => '错误 404',
            'hs_header' => '页面未找到',
            'hs_subheadline' => '找不到您请求的页面。',
            'ch_header' => '返回首页',
            'ch_content' => '<p>请使用下方的页面列表，或返回首页。</p>',
            'ch_link_text' => '返回首页',
            'sg_header' => '重要页面',
            'cta_header' => '返回首页',
            'cta_description' => '返回网站首页。',
            'cta_text' => '返回首页',
            'groups' => ['从这里开始', '为你的团队', '元素章节', '法律与项目'],
            'links' => ['首页', '技术特性', 'GEO 与 AI 搜索', '成功案例',
                '代理商与集成商', '内部团队', '自由职业者与独立开发者',
                '主视觉与落地页开篇', '功能与优势', '套餐与定价', '数据与仪表盘', '信任与社会认同',
                '法律声明', '隐私声明', '无障碍', 'GitHub 代码库'],
        ],
        3 => [
            'searchHeader' => 'Keresés',
            'hs_eyebrow' => '404-es hiba',
            'hs_header' => 'Az oldal nem található',
            'hs_subheadline' => 'A kért oldal nem található.',
            'ch_header' => 'Vissza a kezdőlapra',
            'ch_content' => '<p>Használja az alábbi oldallistát, vagy térjen vissza a kezdőlapra.</p>',
            'ch_link_text' => 'Vissza a kezdőlapra',
            'sg_header' => 'Fontos oldalak',
            'cta_header' => 'Vissza a kezdőlapra',
            'cta_description' => 'Térjen vissza a webhely kezdőlapjára.',
            'cta_text' => 'Vissza a kezdőlapra',
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
    private const NOTFOUND_VISIBLE_ELEMENTS = ['desiderio_sitemapgrid'];

    private const ACCESSIBILITY_EN = [
        'pageTitle' => 'Accessibility statement',
        'slug' => '/accessibility',
        'description' => 'Accessibility statement and WCAG 2.2 AA audit summary for the Desiderio demo website.',
        'statementHeader' => 'Accessibility statement and WCAG 2.2 audit',
        'lastUpdated' => '7 July 2026',
        'contactEmail' => 'accessibility@desiderio.example',
        'statementContent' => <<<'HTML'
<p>Desiderio aims to make this demo website accessible. The benchmark for this statement is <strong>WCAG 2.2, conformance level AA</strong>. This page now includes a real technical audit of representative Desiderio pages, not only a generic template text.</p>
<h3>Audit scope</h3>
<p>The audit was run on 7 July 2026 against the local Desiderio site at <code>https://webconsulting-typo3-lab.ddev.site/</code>. The automated part covered 10 representative pages in two viewport sizes: desktop <code>1280x900</code> and mobile <code>390x844</code>.</p>
<ul><li><a href="/">Home</a></li><li><a href="/accessibility/">Accessibility statement</a></li><li><a href="/technical-features/">Technical features</a></li><li><a href="/features/">The Desiderio ecosystem</a></li><li><a href="/content-types/">Content types</a></li><li><a href="/target-groups/">Target groups</a></li><li><a href="/geo-ai-search/">GEO and AI search</a></li><li><a href="/success-stories/">Success stories</a></li><li><a href="/desiderio-powermail-lab/">Powermail Lab</a></li><li><a href="/search/">Search</a></li></ul>
<h3>Tools and test method</h3>
<p>The technical audit used Chrome through Playwright and axe-core 4.12.1 with WCAG 2.0, 2.1 and 2.2 A/AA rule tags. Additional deterministic checks inspected page language, page title, landmarks, exactly one main heading, heading order, skip link availability, image alternative text, image dimensions, viewport zoom settings, keyboard focusability, visible focus indicators, target-size candidates, document links, embedded content, audio and video.</p>
<h3>Automated and code-inspected result</h3>
<p>The tested sample produced <strong>0 automated axe violations</strong> after remediation. The audit also found <strong>0 unresolved small-target candidates</strong>, <strong>0 missing image alt attributes</strong>, <strong>0 missing image dimensions</strong>, <strong>0 detected document links</strong>, <strong>0 detected embeds</strong> and <strong>0 detected audio/video elements</strong> in the tested sample. All tested pages had one main <code>h1</code>, a page language, a skip link and keyboard-reachable interactive controls.</p>
<p>The audit found focus and target-size issues in shared button, gallery, link and blog-list patterns. These were remediated with a Desiderio-scoped CSS override before this statement was updated. It also found badge contrast issues in the ecosystem page; these were remediated with stronger foreground colors.</p>
<h3>Items marked “to be tested manually”</h3>
<p>The following points cannot be proven completely by automation and are therefore explicitly marked <strong>to be tested manually</strong> before a formal production conformance claim is made:</p>
<ul><li><strong>Screen reader behaviour — to be tested manually:</strong> reading order, landmark announcements, form announcements and dynamic search suggestions with the supported combinations, for example VoiceOver/Safari, NVDA/Firefox and JAWS/Chrome.</li><li><strong>Focus not obscured and real-device operation — to be tested manually:</strong> keyboard and touch use on the supported devices, including sticky header/footer situations and browser UI overlays.</li><li><strong>Zoom, reflow and forced-colors modes — to be tested manually:</strong> 200% and 400% zoom, browser text resizing, high-contrast or forced-colors modes and operating-system reduced-motion settings.</li><li><strong>Code-block contrast on the technical-features page — to be tested manually:</strong> automated computation measured passing ratios, but the code block uses a decorative background, so final visual confirmation is required.</li><li><strong>Forms and validation — to be tested manually:</strong> full Powermail submission, validation errors, CAPTCHA behaviour and confirmation messages with assistive technology.</li><li><strong>Legal statement data — to be tested manually:</strong> responsible organization, real contact address, enforcement body, publication date and jurisdiction-specific wording.</li><li><strong>Future media, PDFs, office documents and third-party embeds — to be tested manually:</strong> none were detected in the tested sample, but any added material needs its own review.</li></ul>
<h3>Current conformance status</h3>
<p>Based on the automated and code-inspected audit sample, the tested Desiderio pages have no unresolved automated WCAG 2.2 A/AA violations. A final legal conformance statement still requires the manual checks listed above.</p>
<h3>Feedback and contact</h3>
<p>If you find an accessibility barrier or need information in another format, email <a href="mailto:accessibility@desiderio.example">accessibility@desiderio.example</a>. Please include the affected page, device, browser and assistive technology if available.</p>
HTML,
        'highlightHeader' => 'What was tested and what remains manual',
        'highlightContent' => <<<'HTML'
<p>The audit covered 10 representative Desiderio pages across desktop and mobile viewports with axe-core, structural DOM checks and real Tab navigation. The tested sample now has no unresolved automated violations. Manual confirmation is still required for screen readers, real-device focus visibility, zoom/reflow, forced-colors mode, full form validation and legal approval of this statement.</p>
HTML,
        'highlightLinkText' => 'More engineering facts',
    ];

    private const ACCESSIBILITY_DE = [
        'pageTitle' => 'Barrierefreiheit',
        'slug' => '/barrierefreiheit',
        'description' => 'Erklärung zur Barrierefreiheit und WCAG-2.2-AA-Prüfbericht für die Desiderio Demo-Website.',
        'statementHeader' => 'Erklärung zur Barrierefreiheit und WCAG-2.2-Prüfung',
        'lastUpdated' => '7. Juli 2026',
        'contactEmail' => 'accessibility@desiderio.example',
        'statementContent' => <<<'HTML'
<p>Desiderio ist bestrebt, diese Demo-Website barrierefrei zugänglich zu machen. Maßstab dieser Erklärung sind die <strong>Web Content Accessibility Guidelines (WCAG) 2.2 auf Konformitätsstufe AA</strong>. Diese Seite enthält jetzt eine echte technische Prüfung repräsentativer Desiderio-Seiten und nicht nur einen allgemeinen Vorlagentext.</p>
<h3>Prüfumfang</h3>
<p>Die Prüfung wurde am 7. Juli 2026 gegen die lokale Desiderio-Website unter <code>https://webconsulting-typo3-lab.ddev.site/</code> durchgeführt. Der automatisierte Teil umfasste 10 repräsentative Seiten in zwei Viewports: Desktop <code>1280x900</code> und Mobil <code>390x844</code>.</p>
<ul><li><a href="/">Startseite</a></li><li><a href="/de/barrierefreiheit/">Barrierefreiheit</a></li><li><a href="/technical-features/">Technical features</a></li><li><a href="/features/">Desiderio ecosystem</a></li><li><a href="/content-types/">Content types</a></li><li><a href="/target-groups/">Target groups</a></li><li><a href="/geo-ai-search/">GEO and AI search</a></li><li><a href="/success-stories/">Success stories</a></li><li><a href="/desiderio-powermail-lab/">Powermail Lab</a></li><li><a href="/search/">Search</a></li></ul>
<h3>Werkzeuge und Prüfmethode</h3>
<p>Die technische Prüfung verwendete Chrome über Playwright und axe-core 4.12.1 mit WCAG-2.0-, WCAG-2.1- und WCAG-2.2-Regeln für A und AA. Zusätzlich wurden DOM-Prüfungen für Seitensprache, Seitentitel, Landmarken, genau eine Hauptüberschrift, Überschriftenreihenfolge, Skip-Link, Alternativtexte, Bildabmessungen, Zoom-Einstellungen, Tastaturfokus, sichtbare Fokusindikatoren, Zielgrößen, Dokumentlinks, eingebettete Inhalte sowie Audio und Video durchgeführt.</p>
<h3>Automatisiertes und codebasiertes Ergebnis</h3>
<p>Die geprüfte Stichprobe ergab nach den Korrekturen <strong>0 automatisierte axe-Verstöße</strong>. Außerdem fand die Prüfung <strong>0 ungelöste Zielgrößen-Kandidaten</strong>, <strong>0 fehlende Bild-Alternativtexte</strong>, <strong>0 fehlende Bildabmessungen</strong>, <strong>0 erkannte Dokumentlinks</strong>, <strong>0 erkannte eingebettete Inhalte</strong> und <strong>0 erkannte Audio-/Video-Elemente</strong> in der Stichprobe. Alle geprüften Seiten hatten genau ein Haupt-<code>h1</code>, eine Seitensprache, einen Skip-Link und per Tastatur erreichbare Bedienelemente.</p>
<p>Die Prüfung fand Fokus- und Zielgrößenprobleme in gemeinsamen Button-, Galerie-, Link- und Bloglisten-Mustern. Diese wurden vor der Aktualisierung dieser Erklärung mit einer Desiderio-spezifischen CSS-Korrektur behoben. Außerdem wurden Kontrastprobleme bei Badges auf der Ecosystem-Seite gefunden und mit stärkeren Vordergrundfarben behoben.</p>
<h3>Punkte mit Hinweis „to be tested manually“</h3>
<p>Die folgenden Punkte können nicht vollständig automatisiert bewiesen werden und sind daher ausdrücklich als <strong>to be tested manually</strong> markiert, bevor eine formale produktive Konformitätsaussage getroffen wird:</p>
<ul><li><strong>Screenreader-Verhalten — to be tested manually:</strong> Lesereihenfolge, Landmark-Ansagen, Formularansagen und dynamische Suchvorschläge mit den unterstützten Kombinationen, zum Beispiel VoiceOver/Safari, NVDA/Firefox und JAWS/Chrome.</li><li><strong>Fokus nicht verdeckt und Bedienung auf echten Geräten — to be tested manually:</strong> Tastatur- und Touch-Bedienung auf unterstützten Geräten, inklusive Sticky Header/Footer und Browser-Overlays.</li><li><strong>Zoom, Reflow und Forced-Colors-Modus — to be tested manually:</strong> 200% und 400% Zoom, Textvergrößerung, High-Contrast/Forced-Colors und Betriebssystem-Einstellung für reduzierte Bewegung.</li><li><strong>Codeblock-Kontrast auf der Technical-features-Seite — to be tested manually:</strong> Die automatisierte Berechnung ergab ausreichende Kontrastwerte, der Codeblock verwendet aber einen dekorativen Hintergrund. Deshalb ist eine finale Sichtprüfung erforderlich.</li><li><strong>Formulare und Validierung — to be tested manually:</strong> vollständige Powermail-Übermittlung, Validierungsfehler, CAPTCHA-Verhalten und Bestätigungsmeldungen mit assistiver Technologie.</li><li><strong>Rechtliche Angaben — to be tested manually:</strong> verantwortliche Organisation, echte Kontaktadresse, Durchsetzungsstelle, Veröffentlichungsdatum und rechtlicher Geltungsbereich.</li><li><strong>Zukünftige Medien, PDFs, Office-Dokumente und Drittanbieter-Einbettungen — to be tested manually:</strong> In der geprüften Stichprobe wurden keine gefunden; neu hinzugefügte Inhalte benötigen eine eigene Prüfung.</li></ul>
<h3>Aktueller Konformitätsstatus</h3>
<p>Auf Basis der automatisierten und codebasierten Stichprobe haben die geprüften Desiderio-Seiten keine ungelösten automatisierten WCAG-2.2-A/AA-Verstöße. Eine abschließende rechtliche Konformitätserklärung setzt weiterhin die oben genannten manuellen Prüfungen voraus.</p>
<h3>Feedback und Kontakt</h3>
<p>Wenn Ihnen eine Barriere auffällt oder Sie Informationen in einem anderen Format benötigen, schreiben Sie bitte an <a href="mailto:accessibility@desiderio.example">accessibility@desiderio.example</a>. Bitte nennen Sie die betroffene Seite, das verwendete Gerät, den Browser und gegebenenfalls die eingesetzte assistive Technologie.</p>
HTML,
        'highlightHeader' => 'Was geprüft wurde und was manuell bleibt',
        'highlightContent' => <<<'HTML'
<p>Die Prüfung umfasste 10 repräsentative Desiderio-Seiten in Desktop- und Mobil-Viewports mit axe-core, strukturellen DOM-Prüfungen und echter Tab-Navigation. Die geprüfte Stichprobe hat aktuell keine ungelösten automatisierten Verstöße. Manuell zu bestätigen bleiben Screenreader, echte Geräte, Fokus-Sichtbarkeit, Zoom/Reflow, Forced-Colors-Modus, vollständige Formularvalidierung und rechtliche Freigabe dieser Erklärung.</p>
HTML,
        'highlightLinkText' => 'Mehr zu den Elementen',
    ];

    public function __construct(private readonly ConnectionPool $connectionPool)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->initBackendUser();

        // Resolve current default-language source uids dynamically (they change on every reseed).
        $accessibilityElements = [
            'desiderio_accessibilitystatement' => $this->findContent(self::ACCESSIBILITY_PAGE, 'desiderio_accessibilitystatement'),
            'desiderio_contenthighlight' => $this->findContent(self::ACCESSIBILITY_PAGE, 'desiderio_contenthighlight'),
        ];
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

        $this->applyNotFoundSourceData($elements);
        $this->applyAccessibilitySourceData($accessibilityElements);

        foreach (array_keys(self::T) as $lang) {
            $this->cleanLanguage($lang, $searchPlugin, $elements, $groupUids, $linkUidsByGroup);
            if ($lang === 1) {
                $this->cleanAccessibilityLanguage($lang, $accessibilityElements);
            }
            $this->seedPageRows($lang);

            // Content + nested collections: DataHandler localize (correct wiring), then set values.
            $contentSources = array_values(array_filter([$searchPlugin, ...array_values($elements)]));
            if ($lang === 1) {
                $contentSources = array_values(array_filter([...$contentSources, ...array_values($accessibilityElements)]));
            }
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
                $fields = ['hidden' => in_array($ctype, self::NOTFOUND_VISIBLE_ELEMENTS, true) ? 0 : 1];
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
            if ($lang === 1) {
                $this->addAccessibilityTranslationData($data, $accessibilityElements);
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

    /** @param array<string,?int> $elements */
    private function cleanAccessibilityLanguage(int $lang, array $elements): void
    {
        $parents = array_values(array_filter($elements));
        if ($parents === []) {
            return;
        }
        $in = implode(',', array_map(static fn (mixed $v): int => is_numeric($v) ? (int)$v : 0, $parents));
        $this->connectionPool->getConnectionForTable('tt_content')->executeStatement(
            "DELETE FROM tt_content WHERE l18n_parent IN ($in) AND sys_language_uid = :l",
            ['l' => $lang]
        );
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
        $this->upsertPageTranslation(
            self::NOTFOUND_PAGE,
            $lang,
            $meta['pageTitle404'],
            '/404',
            [
                'description' => $meta['pageDescription404'],
                'abstract' => $meta['pageDescription404'],
                'seo_title' => $meta['pageTitle404'],
                'og_title' => $meta['pageTitle404'],
                'og_description' => $meta['pageDescription404'],
                'twitter_title' => $meta['pageTitle404'],
                'twitter_description' => $meta['pageDescription404'],
            ],
        );
        if ($lang === 1) {
            $this->upsertPageTranslation(
                self::ACCESSIBILITY_PAGE,
                $lang,
                self::ACCESSIBILITY_DE['pageTitle'],
                self::ACCESSIBILITY_DE['slug'],
                [
                    'description' => self::ACCESSIBILITY_DE['description'],
                    'seo_title' => self::ACCESSIBILITY_DE['statementHeader'],
                    'og_title' => self::ACCESSIBILITY_DE['pageTitle'],
                    'og_description' => self::ACCESSIBILITY_DE['description'],
                    'twitter_title' => self::ACCESSIBILITY_DE['pageTitle'],
                    'twitter_description' => self::ACCESSIBILITY_DE['description'],
                ],
            );
        }
    }

    /**
     * @param array<string,string> $extraOverrides
     */
    private function upsertPageTranslation(int $parent, int $lang, string $title, string $slug, array $extraOverrides = []): void
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
        $overrides = ['title' => $title, 'slug' => $slug, 'nav_title' => $title, ...$extraOverrides];
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

    /** @param array<string,?int> $elements */
    private function applyNotFoundSourceData(array $elements): void
    {
        $data = [
            'pages' => [
                self::NOTFOUND_PAGE => [
                    'title' => self::NOTFOUND_EN['pageTitle'],
                    'nav_title' => self::NOTFOUND_EN['pageTitle'],
                    'description' => self::NOTFOUND_EN['description'],
                    'abstract' => self::NOTFOUND_EN['description'],
                    'seo_title' => self::NOTFOUND_EN['pageTitle'],
                    'og_title' => self::NOTFOUND_EN['pageTitle'],
                    'og_description' => self::NOTFOUND_EN['description'],
                    'twitter_title' => self::NOTFOUND_EN['pageTitle'],
                    'twitter_description' => self::NOTFOUND_EN['description'],
                ],
            ],
        ];

        foreach ($elements as $ctype => $uid) {
            if ($uid === null) {
                continue;
            }
            $fields = ['hidden' => in_array($ctype, self::NOTFOUND_VISIBLE_ELEMENTS, true) ? 0 : 1];
            if ($ctype === 'desiderio_sitemapgrid') {
                $fields['header'] = self::NOTFOUND_EN['sitemapHeader'];
            }
            $data['tt_content'][$uid] = $fields;
        }

        $this->applyData($data);
    }

    /** @param array<string,?int> $elements */
    private function applyAccessibilitySourceData(array $elements): void
    {
        $data = [
            'pages' => [
                self::ACCESSIBILITY_PAGE => [
                    'title' => self::ACCESSIBILITY_EN['pageTitle'],
                    'nav_title' => 'Accessibility',
                    'slug' => self::ACCESSIBILITY_EN['slug'],
                    'description' => self::ACCESSIBILITY_EN['description'],
                    'seo_title' => self::ACCESSIBILITY_EN['statementHeader'],
                    'og_title' => self::ACCESSIBILITY_EN['pageTitle'],
                    'og_description' => self::ACCESSIBILITY_EN['description'],
                    'twitter_title' => self::ACCESSIBILITY_EN['pageTitle'],
                    'twitter_description' => self::ACCESSIBILITY_EN['description'],
                ],
            ],
            'tt_content' => [],
        ];

        $statementUid = $elements['desiderio_accessibilitystatement'] ?? null;
        if ($statementUid) {
            $data['tt_content'][$statementUid] = [
                'header' => self::ACCESSIBILITY_EN['statementHeader'],
                'conformance_level' => 'aa',
                'content' => self::ACCESSIBILITY_EN['statementContent'],
                'contact_email' => self::ACCESSIBILITY_EN['contactEmail'],
                'last_updated' => self::ACCESSIBILITY_EN['lastUpdated'],
                'hidden' => 0,
            ];
        }

        $highlightUid = $elements['desiderio_contenthighlight'] ?? null;
        if ($highlightUid) {
            $data['tt_content'][$highlightUid] = [
                'header' => self::ACCESSIBILITY_EN['highlightHeader'],
                'content' => self::ACCESSIBILITY_EN['highlightContent'],
                'link_text' => self::ACCESSIBILITY_EN['highlightLinkText'],
                'hidden' => 0,
            ];
        }

        $this->applyData($data);
    }

    /**
     * @param array<string,array<int,array<string,mixed>>> $data
     * @param array<string,?int> $elements
     */
    private function addAccessibilityTranslationData(array &$data, array $elements): void
    {
        $statementUid = $elements['desiderio_accessibilitystatement'] ?? null;
        if ($statementUid) {
            $translatedUid = $this->translationUid('tt_content', $statementUid, 1);
            if ($translatedUid) {
                $data['tt_content'][$translatedUid] = [
                    'header' => self::ACCESSIBILITY_DE['statementHeader'],
                    'conformance_level' => 'aa',
                    'content' => self::ACCESSIBILITY_DE['statementContent'],
                    'contact_email' => self::ACCESSIBILITY_DE['contactEmail'],
                    'last_updated' => self::ACCESSIBILITY_DE['lastUpdated'],
                    'hidden' => 0,
                ];
            }
        }

        $highlightUid = $elements['desiderio_contenthighlight'] ?? null;
        if ($highlightUid) {
            $translatedUid = $this->translationUid('tt_content', $highlightUid, 1);
            if ($translatedUid) {
                $data['tt_content'][$translatedUid] = [
                    'header' => self::ACCESSIBILITY_DE['highlightHeader'],
                    'content' => self::ACCESSIBILITY_DE['highlightContent'],
                    'link_text' => self::ACCESSIBILITY_DE['highlightLinkText'],
                    'hidden' => 0,
                ];
            }
        }
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
