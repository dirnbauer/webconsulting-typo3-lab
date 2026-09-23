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
 * Normalizes and translates the desiderio site's utility pages:
 * - standard 404 source content plus de/zh/hu translations (uid 737)
 * - de/zh/hu for the search page (uid 738)
 * - de for the footer accessibility page (uid 736)
 *
 * WHY A COMMAND (and not raw SQL)
 * -------------------------------
 * The 404 page's content is owned by desiderio's `desiderio:seed-styleguide-pages`,
 * which re-creates the elements with NEW uids on every run. The desiderio product
 * seeder is English-only by design and may restore promotional 404 copy, so the
 * lab-specific source normalization and translations live here and must be
 * re-applied after each reseed:
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
 * the source values and translations it owns.
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
        'description' => 'This page does not exist or has moved. Check the web address, or use the list of main pages below to find what you need.',
        'hs_eyebrow' => 'Error 404',
        'hs_header' => 'Page not found',
        'hs_subheadline' => 'The link may be out of date, or the address may contain a typo.',
        'ch_header' => 'Find the page you need',
        'ch_content' => '<p>The list below shows the main pages of this site, grouped by topic. You can also start again on the homepage.</p>',
        'ch_link_text' => 'Open the homepage',
        'sg_header' => 'Main pages of this site',
        'cta_header' => 'Start with the overview',
        'cta_description' => 'The homepage gives an overview of this site. The main menu at the top leads to its sections.',
        'cta_text' => 'Visit the homepage',
    ];

    /** sys_language_uid => translated strings */
    private const T = [
        1 => [
            'searchHeader' => 'Suche',
            'hs_eyebrow' => 'Fehler 404',
            'hs_header' => 'Seite nicht gefunden',
            'hs_subheadline' => 'Der Link ist vielleicht veraltet, oder die Adresse enthält einen Tippfehler.',
            'ch_header' => 'Die passende Seite finden',
            'ch_content' => '<p>Die Liste unten zeigt die wichtigsten Seiten dieser Website, nach Thema geordnet. Sie können auch auf der Startseite neu beginnen.</p>',
            'ch_link_text' => 'Startseite öffnen',
            'sg_header' => 'Die wichtigsten Seiten',
            'cta_header' => 'Mit dem Überblick beginnen',
            'cta_description' => 'Die Startseite gibt einen Überblick über diese Website. Das Hauptmenü oben führt zu den einzelnen Bereichen.',
            'cta_text' => 'Zur Startseite',
            'groups' => ['Hier starten', 'Für Ihr Team', 'Elementgruppen', 'Recht und Projekt'],
            'links' => ['Startseite', 'Technische Funktionen', 'GEO und KI-Suche', 'Erfolgsgeschichten',
                'Zielgruppen', 'Agenturen und Integratoren', 'Inhouse-Teams', 'Freelancer',
                'Hero-Bereiche und Intros', 'Funktionen und Vorteile', 'Tarife und Preise', 'Daten und Dashboards', 'Vertrauen und Referenzen',
                'Impressum', 'Datenschutz', 'Barrierefreiheit', 'GitHub-Repository'],
        ],
        2 => [
            'searchHeader' => '搜索',
            'hs_eyebrow' => '错误 404',
            'hs_header' => '页面未找到',
            'hs_subheadline' => '链接可能已过期，或网址中有拼写错误。',
            'ch_header' => '查找您需要的页面',
            'ch_content' => '<p>下方列表按主题列出本站的主要页面。您也可以从首页重新开始。</p>',
            'ch_link_text' => '打开首页',
            'sg_header' => '本站主要页面',
            'cta_header' => '从概览开始',
            'cta_description' => '首页概述了本站内容。顶部的主菜单可通往各个栏目。',
            'cta_text' => '前往首页',
            'groups' => ['从这里开始', '为您的团队', '元素分组', '法律与项目'],
            'links' => ['首页', '技术特性', 'GEO 与 AI 搜索', '成功案例',
                '目标群体', '代理商与集成商', '内部团队', '自由职业者',
                '主视觉与页面开篇', '功能与优势', '套餐与定价', '数据与仪表盘', '信任与社会认同',
                '法律声明', '隐私声明', '无障碍', 'GitHub 代码库'],
        ],
        3 => [
            'searchHeader' => 'Keresés',
            'hs_eyebrow' => '404-es hiba',
            'hs_header' => 'Az oldal nem található',
            'hs_subheadline' => 'Lehet, hogy a hivatkozás elavult, vagy elírás van a címben.',
            'ch_header' => 'Keresse meg a kívánt oldalt',
            'ch_content' => '<p>Az alábbi lista témák szerint csoportosítva mutatja a webhely fő oldalait. A kezdőlapon is újrakezdheti.</p>',
            'ch_link_text' => 'Kezdőlap megnyitása',
            'sg_header' => 'A webhely fő oldalai',
            'cta_header' => 'Kezdje az áttekintéssel',
            'cta_description' => 'A kezdőlap áttekintést ad a webhelyről. A felső főmenü a webhely egyes részeihez vezet.',
            'cta_text' => 'Ugrás a kezdőlapra',
            'groups' => ['Kezdje itt', 'A csapatának', 'Elemcsoportok', 'Jog és projekt'],
            'links' => ['Kezdőlap', 'Technikai jellemzők', 'GEO és AI-keresés', 'Sikertörténetek',
                'Célcsoportok', 'Ügynökségek és integrátorok', 'Belső csapatok', 'Szabadúszók',
                'Hero és oldalbevezetők', 'Funkciók és előnyök', 'Csomagok és árazás', 'Adatok és irányítópultok', 'Bizalom és társas bizonyíték',
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
        'description' => 'Accessibility statement for the Desiderio demo site. It sums up the WCAG 2.2 AA audit of 10 pages on 7 July 2026 and the checks still to be done by hand.',
        'statementHeader' => 'Accessibility statement and WCAG 2.2 audit',
        'lastUpdated' => '7 July 2026',
        'contactEmail' => 'accessibility@desiderio.example',
        'statementContent' => <<<'HTML'
<p>Desiderio aims to make this demo website accessible. The benchmark for this statement is <strong>WCAG 2.2, conformance level AA</strong>.</p>
<h3>Audit scope</h3>
<p>The audit ran on 7 July 2026 against the local Desiderio site at <code>https://webconsulting-typo3-lab.ddev.site/</code>. The automated part covered 10 representative pages in two viewport sizes: desktop <code>1280x900</code> and mobile <code>390x844</code>.</p>
<ul><li><a href="/">Home</a></li><li><a href="/accessibility/">Accessibility statement</a></li><li><a href="/technical-features/">Technical features</a></li><li><a href="/features/">The Desiderio ecosystem</a></li><li><a href="/content-types/">Content types</a></li><li><a href="/target-groups/">Target groups</a></li><li><a href="/geo-ai-search/">GEO and AI search</a></li><li><a href="/success-stories/">Success stories</a></li><li><a href="/desiderio-powermail-lab/">Powermail Lab</a></li><li><a href="/search/">Search</a></li></ul>
<h3>Tools and test method</h3>
<p>The technical audit used Chrome through Playwright and axe-core 4.12.1, with the WCAG 2.0, 2.1 and 2.2 A/AA rule tags. Additional deterministic checks covered:</p>
<ul><li><strong>Structure:</strong> page language, page title, landmarks, exactly one main heading, heading order and an available skip link</li><li><strong>Images:</strong> alternative text and image dimensions</li><li><strong>Operation:</strong> viewport zoom settings, keyboard focusability, visible focus indicators and target-size candidates</li><li><strong>Other content:</strong> document links, embedded content, audio and video</li></ul>
<h3>Automated and code-inspected result</h3>
<p>After remediation, the tested sample produced <strong>0 automated axe violations</strong>. It also had:</p>
<ul><li><strong>0 unresolved small-target candidates</strong></li><li><strong>0 missing image alt attributes</strong></li><li><strong>0 missing image dimensions</strong></li><li><strong>0 detected document links</strong></li><li><strong>0 detected embeds</strong></li><li><strong>0 detected audio/video elements</strong></li></ul>
<p>All tested pages had one main <code>h1</code>, a page language, a skip link and interactive controls that can be reached with the keyboard.</p>
<p>The audit found focus and target-size issues in shared patterns for buttons, galleries, links and blog lists. A Desiderio-scoped CSS override fixed them before this statement was updated. The audit also found badge contrast issues on the ecosystem page. Stronger foreground colours fixed them.</p>
<h3>Items to be tested manually</h3>
<p>Automated tests cannot fully prove the points below. They are <strong>to be tested manually</strong> before a formal conformance claim is made for a production site.</p>
<ul><li><strong>Screen reader behaviour:</strong> reading order, landmark announcements, form announcements and dynamic search suggestions. Test them with the supported combinations, for example VoiceOver/Safari, NVDA/Firefox and JAWS/Chrome.</li><li><strong>Focus not obscured and use on real devices:</strong> keyboard and touch use on the supported devices, including sticky headers and footers and browser UI overlays.</li><li><strong>Zoom, reflow and forced colours:</strong> 200% and 400% zoom, text resizing in the browser, high-contrast or forced-colours modes and reduced-motion settings in the operating system.</li><li><strong>Code-block contrast on the Technical features page:</strong> the automated calculation measured passing contrast ratios. The code block has a decorative background, so a final visual check is required.</li><li><strong>Forms and validation:</strong> a full Powermail submission, validation errors, CAPTCHA behaviour and confirmation messages, all with assistive technology.</li><li><strong>Legal statement data:</strong> the responsible organisation, a real contact address, the enforcement body, the publication date and wording for the jurisdiction.</li><li><strong>Future media, PDFs, office documents and third-party embeds:</strong> the tested sample had none. Any material added later needs its own review.</li></ul>
<h3>Current conformance status</h3>
<p>The tested Desiderio pages have no unresolved automated WCAG 2.2 A/AA violations. This result is based on the automated and code-inspected audit sample. A final legal conformance statement still requires the manual checks listed above.</p>
<h3>Feedback and contact</h3>
<p>If you find an accessibility barrier or need information in another format, email <a href="mailto:accessibility@desiderio.example">accessibility@desiderio.example</a>. Please include the affected page, your device and browser, and the assistive technology you use, if any.</p>
HTML,
        'highlightHeader' => 'What was tested and what remains manual',
        'highlightContent' => <<<'HTML'
<p>The audit checked 10 representative Desiderio pages on desktop and mobile with axe-core, structural DOM checks and real Tab navigation. The tested sample has no unresolved automated violations. Still to be tested manually: screen readers, real-device focus visibility, zoom and reflow, forced colours mode, full form validation and legal approval of this statement.</p>
HTML,
        'highlightLinkText' => 'See technical features',
    ];

    private const ACCESSIBILITY_DE = [
        'pageTitle' => 'Barrierefreiheit',
        'slug' => '/barrierefreiheit',
        'description' => 'Erklärung zur Barrierefreiheit der Desiderio-Demo-Website: WCAG-2.2-AA-Prüfung von 10 Seiten am 7. Juli 2026, Ergebnisse und offene manuelle Prüfungen.',
        'statementHeader' => 'Erklärung zur Barrierefreiheit und WCAG-2.2-Prüfung',
        'lastUpdated' => '7. Juli 2026',
        'contactEmail' => 'accessibility@desiderio.example',
        'statementContent' => <<<'HTML'
<p>Desiderio möchte diese Demo-Website barrierefrei zugänglich machen. Maßstab dieser Erklärung sind die <strong>Web Content Accessibility Guidelines (WCAG) 2.2, Konformitätsstufe AA</strong>.</p>
<h3>Prüfumfang</h3>
<p>Die Prüfung lief am 7. Juli 2026 auf der lokalen Desiderio-Website unter <code>https://webconsulting-typo3-lab.ddev.site/</code>. Der automatisierte Teil umfasste 10 repräsentative Seiten in zwei Viewport-Größen: Desktop <code>1280x900</code> und Mobil <code>390x844</code>.</p>
<ul><li><a href="/">Startseite</a></li><li><a href="/de/barrierefreiheit/">Barrierefreiheit</a></li><li><a href="/technical-features/">Technical features</a></li><li><a href="/features/">Desiderio ecosystem</a></li><li><a href="/content-types/">Inhaltstypen</a></li><li><a href="/target-groups/">Zielgruppen</a></li><li><a href="/geo-ai-search/">GEO and AI search</a></li><li><a href="/success-stories/">Success stories</a></li><li><a href="/desiderio-powermail-lab/">Powermail Labor</a></li><li><a href="/search/">Suche</a></li></ul>
<h3>Werkzeuge und Prüfmethode</h3>
<p>Die technische Prüfung nutzte Chrome über Playwright und axe-core 4.12.1 mit den Regeln für WCAG 2.0, 2.1 und 2.2 der Stufen A und AA. Zusätzliche deterministische Prüfungen betrafen:</p>
<ul><li><strong>Struktur:</strong> Seitensprache, Seitentitel, Landmarks, genau eine Hauptüberschrift, Reihenfolge der Überschriften und einen verfügbaren Skip-Link</li><li><strong>Bilder:</strong> Alternativtexte und Bildabmessungen</li><li><strong>Bedienung:</strong> Zoom-Einstellungen des Viewports, Fokussierbarkeit per Tastatur, sichtbare Fokusindikatoren und mögliche zu kleine Zielflächen</li><li><strong>Weitere Inhalte:</strong> Dokumentlinks, eingebettete Inhalte, Audio und Video</li></ul>
<h3>Automatisiertes und codebasiertes Ergebnis</h3>
<p>Nach den Korrekturen ergab die geprüfte Stichprobe <strong>0 automatisierte axe-Verstöße</strong>. Außerdem enthielt die Stichprobe:</p>
<ul><li><strong>0 ungelöste Kandidaten für zu kleine Zielflächen</strong></li><li><strong>0 fehlende Alternativtexte bei Bildern</strong></li><li><strong>0 fehlende Bildabmessungen</strong></li><li><strong>0 erkannte Dokumentlinks</strong></li><li><strong>0 erkannte eingebettete Inhalte</strong></li><li><strong>0 erkannte Audio- oder Video-Elemente</strong></li></ul>
<p>Alle geprüften Seiten hatten genau eine Hauptüberschrift (<code>h1</code>), eine Seitensprache, einen Skip-Link und per Tastatur erreichbare Bedienelemente.</p>
<p>Die Prüfung fand Probleme mit Fokus und Zielgröße in gemeinsam genutzten Mustern für Buttons, Galerien, Links und Blog-Listen. Eine CSS-Korrektur speziell für Desiderio hat sie behoben, bevor diese Erklärung aktualisiert wurde. Außerdem fand die Prüfung Kontrastprobleme bei Badges auf der Ecosystem-Seite. Stärkere Vordergrundfarben haben sie behoben.</p>
<h3>Manuell zu prüfende Punkte</h3>
<p>Die folgenden Punkte lassen sich automatisiert nicht vollständig nachweisen. Sie sind <strong>manuell zu prüfen</strong>, bevor eine formale Konformitätsaussage für eine produktive Website getroffen wird.</p>
<ul><li><strong>Verhalten mit Screenreadern:</strong> Lesereihenfolge, Ansage von Landmarks, Ansagen in Formularen und dynamische Suchvorschläge. Zu prüfen mit den unterstützten Kombinationen, zum Beispiel VoiceOver/Safari, NVDA/Firefox und JAWS/Chrome.</li><li><strong>Nicht verdeckter Fokus und Bedienung auf echten Geräten:</strong> Bedienung per Tastatur und Touch auf den unterstützten Geräten, auch mit fixierter Kopf- und Fußzeile und eingeblendeter Browser-Oberfläche.</li><li><strong>Zoom, Reflow und erzwungene Farben:</strong> Zoom auf 200 % und 400 %, Textvergrößerung im Browser, Hochkontrast- oder Forced-Colors-Modus und die Einstellung des Betriebssystems für reduzierte Bewegung.</li><li><strong>Kontrast des Codeblocks auf der Seite „Technical features“:</strong> Die automatisierte Berechnung ergab ausreichende Kontrastwerte. Der Codeblock hat aber einen dekorativen Hintergrund. Deshalb ist eine abschließende Sichtprüfung nötig.</li><li><strong>Formulare und Validierung:</strong> vollständiges Absenden eines Powermail-Formulars, Validierungsfehler, CAPTCHA-Verhalten und Bestätigungsmeldungen, jeweils mit assistiver Technologie.</li><li><strong>Rechtliche Angaben:</strong> verantwortliche Organisation, echte Kontaktadresse, Durchsetzungsstelle, Veröffentlichungsdatum und Formulierungen für den jeweiligen Rechtsraum.</li><li><strong>Künftige Medien, PDFs, Office-Dokumente und Einbettungen von Drittanbietern:</strong> Die geprüfte Stichprobe enthielt keine. Jedes neu hinzugefügte Material braucht eine eigene Prüfung.</li></ul>
<h3>Aktueller Konformitätsstatus</h3>
<p>Die geprüften Desiderio-Seiten haben keine ungelösten, automatisiert erkannten Verstöße gegen WCAG 2.2 A/AA. Grundlage ist die automatisierte und codebasierte Prüfung der Stichprobe. Eine abschließende rechtliche Konformitätserklärung erfordert weiterhin die oben genannten manuellen Prüfungen.</p>
<h3>Feedback und Kontakt</h3>
<p>Wenn Sie auf eine Barriere stoßen oder Informationen in einem anderen Format benötigen, schreiben Sie an <a href="mailto:accessibility@desiderio.example">accessibility@desiderio.example</a>. Bitte nennen Sie die betroffene Seite, Ihr Gerät, Ihren Browser und gegebenenfalls Ihre assistive Technologie.</p>
HTML,
        'highlightHeader' => 'Was geprüft wurde und was manuell bleibt',
        'highlightContent' => <<<'HTML'
<p>Die Prüfung umfasste 10 repräsentative Desiderio-Seiten auf Desktop und Mobilgeräten, mit axe-core, strukturellen DOM-Prüfungen und echter Tab-Navigation. Die Stichprobe hat keine ungelösten, automatisiert erkannten Verstöße. Noch manuell zu prüfen sind Screenreader, die Fokus-Sichtbarkeit auf echten Geräten, Zoom und Reflow, der Forced-Colors-Modus, die vollständige Formularvalidierung und die rechtliche Freigabe dieser Erklärung.</p>
HTML,
        'highlightLinkText' => 'Technische Details ansehen',
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
                $qb->expr()->eq('t3ver_wsid', 0),
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
                $qb->expr()->eq('t3ver_wsid', 0),
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
                $qb->expr()->eq('t3ver_wsid', 0),
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
                "DELETE FROM `$table` WHERE `$parentField` IN ($in) AND sys_language_uid = :l AND t3ver_wsid = 0",
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
            "DELETE FROM tt_content WHERE l18n_parent IN ($in) AND sys_language_uid = :l AND t3ver_wsid = 0",
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
            $fields = [
                'hidden' => in_array($ctype, self::NOTFOUND_VISIBLE_ELEMENTS, true) ? 0 : 1,
            ];
            foreach (self::ELEMENT_FIELDS[$ctype] as $column => $key) {
                $fields[$column] = self::NOTFOUND_EN[$key];
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
        if ($row) {
            $beUser->fetchGroupData();
        }
        // After fetchGroupData(), which restores the workspace the admin last
        // used in the backend: the seed must write live, never drafts.
        // setTemporaryWorkspace() leaves the admin's own choice untouched.
        $beUser->setTemporaryWorkspace(0);
        $GLOBALS['BE_USER'] = $beUser;
    }
}
