<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\NetresearchTalk;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Webconsulting\SitePackage\ContentAudit\Canon;
use Webconsulting\SitePackage\ContentAudit\CanonChecker;
use Webconsulting\SitePackage\ContentAudit\CopyMetrics;
use Webconsulting\SitePackage\ContentAudit\FieldRole;
use Webconsulting\SitePackage\NetresearchTalk\TalkPayload;

/**
 * The shipped Netresearch talk payload: consistent keys and markers, only
 * fields the Desiderio elements have, well-formed links, every image slot
 * declared, the copy canon met (quotations excepted) and the attribution on
 * every page.
 */
final class TalkPayloadTest extends TestCase
{
    private const SLIDES = 'https://netresearch.github.io/modularer-ki-stack-page/vortraege/mtug-2026-11-09/';

    private static ?TalkPayload $talk = null;

    private static function talk(): TalkPayload
    {
        return self::$talk ??= TalkPayload::fromDirectory();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function records(): array
    {
        return self::talk()->records;
    }

    public function testThePayloadIsConsistent(): void
    {
        self::assertSame([], self::talk()->problems());
        self::assertSame('00-hub.payload.json', self::talk()->files[0], 'the hub file comes first: it creates the page the others refer to');
    }

    public function testPagesComeFirstSoEveryPageReferenceResolves(): void
    {
        $tables = array_map(static fn (array $record): string => (string)$record['table'], self::records());
        $firstOther = array_search('tt_content', $tables, true);
        self::assertIsInt($firstOther);
        self::assertSame([], array_values(array_filter(array_slice($tables, $firstOther), static fn (string $table): bool => $table === 'pages')));
        self::assertSame(9, $firstOther, 'hub + eight pages');
    }

    public function testEveryTalkPageStartsWithItsHeadingAndTheAttributionAndEndsWithTheSourceOrPager(): void
    {
        $byPage = [];
        foreach (self::records() as $record) {
            if ($record['table'] === 'tt_content' && str_starts_with((string)$record['pid'], '@NEW_nrtalk_page')) {
                $byPage[(string)$record['pid']][] = (string)$record['set']['rowDescription'];
            }
        }
        self::assertCount(9, $byPage);
        foreach ($byPage as $page => $markers) {
            $names = array_map(static fn (string $marker): string => (string)substr($marker, (int)strrpos($marker, ':') + 1), $markers);
            self::assertSame(['hero', 'credit'], array_slice($names, 0, 2), $page . ' opens with its heading and the attribution');
            self::assertContains('source', $names, $page . ' names the slide range it comes from');
            self::assertContains(end($names), ['next', 'pager'], $page . ' ends with the way to the next page');
        }
    }

    public function testTheAttributionNamesSpeakerEventAndAllFourSources(): void
    {
        $credits = array_values(array_filter(self::records(), static fn (array $record): bool => str_ends_with((string)($record['set']['rowDescription'] ?? ''), ':credit')));
        self::assertCount(9, $credits);
        foreach ($credits as $credit) {
            self::assertSame('muted', $credit['set']['frame_class']);
            self::assertStringContainsString('Sebastian Mendel, Netresearch DTT GmbH', (string)$credit['set']['subheadline']);
            self::assertStringContainsString('MTUG – TYPO3 User Group München on 9 November 2026', (string)$credit['set']['subheadline']);
            $cards = $this->children((string)$credit['key']);
            self::assertSame([
                self::SLIDES,
                self::SLIDES . 'MTUG-KI-in-TYPO3.pdf',
                'https://github.com/netresearch/modularer-ki-stack-page',
                'https://netresearch.github.io/modularer-ki-stack-page/',
            ], array_map(static fn (array $card): string => (string)$card['set']['link'], $cards));
        }
        $thanks = $this->record('NEW_nrtalk_hub_thanks');
        foreach (['Content, structure and the screenshot concept are by Netresearch', 'The English translation and the page layout are by webconsulting', 'The opinions are the speaker’s'] as $statement) {
            self::assertStringContainsString($statement, (string)$thanks['set']['content']);
        }
    }

    public function testEverySourceLineLinksTheSlideRange(): void
    {
        $sources = array_values(array_filter(self::records(), static fn (array $record): bool => str_ends_with((string)($record['set']['rowDescription'] ?? ''), ':source')));
        self::assertCount(9, $sources);
        foreach ($sources as $source) {
            $content = (string)$source['set']['content'];
            if ($source['key'] === 'NEW_nrtalk_stack_source') {
                self::assertStringContainsString('https://netresearch.github.io/modularer-ki-stack-page/en/', $content);
                continue;
            }
            self::assertSame(1, preg_match('#slides <a href="' . preg_quote(self::SLIDES, '#') . '\#/(\d+)">\1</a> to <a href="' . preg_quote(self::SLIDES, '#') . '\#/(\d+)">\2</a>#', $content), $content);
        }
    }

    public function testElementsUseOnlyFieldsAndValuesTheirContentBlockHas(): void
    {
        $blocks = dirname(__DIR__, 5) . '/vendor/webconsulting/desiderio/ContentBlocks';
        if (!is_dir($blocks . '/ContentElements')) {
            self::markTestSkipped('Desiderio is not installed in vendor/.');
        }
        $schema = $this->schema($blocks);
        self::assertGreaterThan(200, count($schema['types']), 'the Desiderio elements were read');
        $problems = [];
        foreach (self::records() as $record) {
            $table = (string)$record['table'];
            if ($table === 'pages') {
                continue;
            }
            $set = $record['set'];
            self::assertIsArray($set);
            $definition = $table === 'tt_content' ? ($schema['types'][(string)$set['CType']] ?? null) : ($schema['tables'][$table] ?? null);
            if ($definition === null) {
                $problems[] = sprintf('%s: unknown %s', $record['key'], $table === 'tt_content' ? 'CType ' . $set['CType'] : 'table ' . $table);
                continue;
            }
            $allowed = $table === 'tt_content'
                ? ['CType', 'colPos', 'hidden', 'sys_language_uid', 'rowDescription', 'header', 'header_layout', 'tx_desiderio_anchor', 'layout', 'frame_class', 'space_before_class', 'space_after_class']
                : ['foreign_table_parent_uid', 'tablenames', 'fieldname', 'sys_language_uid', 'hidden'];
            foreach ($set as $field => $value) {
                if (in_array($field, $allowed, true)) {
                    continue;
                }
                if (!isset($definition['fields'][$field])) {
                    $problems[] = sprintf('%s: %s has no field %s', $record['key'], $table === 'tt_content' ? $set['CType'] : $table, $field);
                    continue;
                }
                $items = $definition['fields'][$field]['items'];
                if ($items !== [] && !in_array((string)$value, $items, true)) {
                    $problems[] = sprintf('%s.%s: "%s" is not one of %s', $record['key'], $field, (string)$value, implode(', ', $items));
                }
            }
            foreach ($definition['fields'] as $field => $config) {
                if ($config['required'] && trim((string)($set[$field] ?? '')) === '') {
                    $problems[] = sprintf('%s: required field %s is empty', $record['key'], $field);
                }
            }
            foreach (TalkPayload::collections($record) as $collection) {
                $expected = $definition['collections'][$collection['field']] ?? null;
                if ($expected === null) {
                    $problems[] = sprintf('%s: no collection %s', $record['key'], $collection['field']);
                    continue;
                }
                foreach ($collection['keys'] as $child) {
                    if ($this->record($child)['table'] !== $expected['table']) {
                        $problems[] = sprintf('%s: item %s is not a %s record', $record['key'], $child, $expected['table']);
                    }
                }
                if (count($collection['keys']) < $expected['minitems']) {
                    $problems[] = sprintf('%s.%s: at least %d items', $record['key'], $collection['field'], $expected['minitems']);
                }
            }
        }

        self::assertSame([], $problems);
    }

    public function testIconsAreOnesTheSiteAlreadyRenders(): void
    {
        // Icons other seeded elements use on the site (IconItemsProcessor accepts more).
        $known = ['monitor', 'file-text', 'git-branch', 'layers', 'sparkles', 'messages', 'scale', 'bot', 'compass', 'code', 'book-open', 'badge-check', 'settings', 'database', 'user-check'];
        foreach (self::records() as $record) {
            if (isset($record['set']['icon'])) {
                self::assertContains($record['set']['icon'], $known, (string)$record['key']);
            }
        }
    }

    public function testCardGridsHaveNoOrphans(): void
    {
        foreach (self::records() as $record) {
            $columns = (int)($record['set']['columns'] ?? 0);
            foreach (TalkPayload::collections($record) as $collection) {
                $count = count($collection['keys']);
                if (($record['set']['CType'] ?? '') === 'desiderio_benefitcards') {
                    self::assertSame(0, $count % max(1, $columns), sprintf('%s: %d cards in %d columns', $record['key'], $count, $columns));
                }
                if (($record['set']['CType'] ?? '') === 'desiderio_resourcelibrary') {
                    self::assertSame(0, $count % 3, sprintf('%s: the library shows three columns', $record['key']));
                }
            }
        }
    }

    public function testEveryLinkIsWellFormed(): void
    {
        $keys = array_fill_keys(array_map(static fn (array $record): string => (string)$record['key'], self::records()), true);
        $problems = [];
        foreach (self::talk()->links() as $link) {
            $target = $link['target'];
            $where = $link['record'] . '.' . $link['field'] . ': ' . $target;
            if (str_starts_with($target, '@')) {
                isset($keys[substr($target, 1)]) || $problems[] = $where . ' (no such record)';
                continue;
            }
            if (preg_match('#^\{\{page:/[a-z0-9/-]*(\|/[a-z0-9/-]*)*\}\}$#', $target) === 1) {
                continue;
            }
            $parts = parse_url($target);
            if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') === '' || preg_match('/\s/', $target) === 1) {
                $problems[] = $where . ' (not an https URL)';
            }
            if (in_array($target, ['https://typo3-demo.netresearch.de/extensions/ai-search', 'https://typo3-demo.netresearch.de/?id=10114'], true)) {
                $problems[] = $where . ' (dead link of the talk, dropped on purpose)';
            }
        }

        self::assertSame([], $problems);
    }

    public function testLinksIntoTheDemoBackendSayALoginIsNeeded(): void
    {
        foreach (self::records() as $record) {
            $text = implode(' ', array_map(static fn (mixed $value): string => is_scalar($value) ? (string)$value : '', (array)$record['set']));
            if (str_contains($text, 'https://typo3-demo.netresearch.de/typo3/')) {
                self::assertMatchesRegularExpression('/login required/i', $text, (string)$record['key']);
            }
        }
    }

    public function testEveryImageSlotIsDeclaredAndNoScreenshotIsCopied(): void
    {
        $slots = json_decode((string)file_get_contents(TalkPayload::DIRECTORY . '/image-slots.json'), true);
        self::assertIsArray($slots);
        $declared = array_map(static fn (array $slot): string => (string)$slot['file'], $slots['slots']);
        $used = [];
        foreach (self::records() as $record) {
            foreach ((array)($record['files'] ?? []) as $references) {
                foreach ((array)$references as $reference) {
                    self::assertIsArray($reference);
                    self::assertStringStartsWith('EXT:site_package/Resources/Public/Images/NetresearchTalk/', (string)$reference['source']);
                    self::assertSame('1:/user_upload/netresearch-talk/', $reference['folder']);
                    self::assertNotSame('', trim((string)$reference['alternative']), 'every screenshot has an alt text');
                    self::assertLessThanOrEqual(125, mb_strlen((string)$reference['alternative']));
                    $used[] = basename((string)$reference['source']);
                }
            }
        }
        sort($used);
        sort($declared);
        self::assertSame($declared, $used);
        self::assertCount(count($declared), array_unique($declared));
        foreach ($slots['slots'] as $slot) {
            self::assertNotSame('', trim((string)$slot['shows']), $slot['file'] . ' says what the capture must show');
        }
        // Netresearch's own captures are never shipped (their file names start like this).
        foreach (glob(dirname(TalkPayload::DIRECTORY, 4) . '/Public/Images/NetresearchTalk/*') ?: [] as $file) {
            self::assertDoesNotMatchRegularExpression('/-light-2026-|^04-ai-search|landingpage-preview-detail-2026/', basename($file));
        }
    }

    /**
     * The seeder replaces a stored screenshot whose content changed; it finds
     * every one through images(): each slot once, with its fileadmin folder.
     */
    public function testImagesListsEveryScreenshotOnceWithItsFolder(): void
    {
        $slots = json_decode((string)file_get_contents(TalkPayload::DIRECTORY . '/image-slots.json'), true);
        self::assertIsArray($slots);
        $images = self::talk()->images();
        $names = array_map(static fn (array $image): string => basename($image['source']), $images);
        $declared = array_map(static fn (array $slot): string => (string)$slot['file'], $slots['slots']);
        sort($names);
        sort($declared);
        self::assertSame($declared, $names);
        self::assertSame(['1:/user_upload/netresearch-talk/'], array_values(array_unique(array_column($images, 'folder'))));
        self::assertContains('EXT:site_package/Resources/Public/Images/NetresearchTalk/agent-chat.png', array_column($images, 'source'));
    }

    /**
     * DataHandler renumbers a collection's sorting when it localizes the
     * items, so a match on sorting found nothing after the first translate
     * run and the next seed created every item a second time (lab-nr,
     * 2026-11-21: 278 duplicates). Items are found by their position.
     */
    public function testCollectionItemsAreMatchedByPositionNeverBySorting(): void
    {
        $byKey = [];
        foreach (self::records() as $record) {
            self::assertArrayNotHasKey('sorting', (array)($record['match'] ?? []), (string)$record['key']);
            $byKey[(string)$record['key']] = $record;
        }
        foreach (self::records() as $record) {
            foreach (TalkPayload::collections($record) as $collection) {
                foreach ($collection['keys'] as $index => $key) {
                    $match = (array)($byKey[$key]['match'] ?? []);
                    self::assertSame($index + 1, $match['_position'] ?? null, $key . ' is item ' . ($index + 1) . ' of ' . $record['key']);
                    self::assertSame('@' . $record['key'], $match['foreign_table_parent_uid'] ?? null, $key);
                }
            }
        }
    }

    public function testEveryLabStatusTokenHasAStatus(): void
    {
        $status = TalkPayload::labStatus();
        foreach (self::records() as $record) {
            foreach ((array)$record['set'] as $value) {
                if (is_string($value) && preg_match('/\{\{labstatus:([a-z0-9-]+)\}\}/', $value, $match) === 1) {
                    self::assertArrayHasKey($match[1], $status);
                }
            }
        }
        self::assertSame('Installed', $status['nr-llm']);
        self::assertSame('Not publicly available', $status['ai-search']);
    }

    public function testCopyMeetsTheCanonExceptListedQuotations(): void
    {
        $checker = new CanonChecker(Canon::fromFile());
        $errors = [];
        foreach (self::records() as $record) {
            $table = (string)$record['table'];
            foreach ((array)$record['set'] as $field => $value) {
                if (!is_string($value) || trim($value) === '' || preg_match('#^(\S+://|t3://|/|EXT:|\{|\[|@|[a-z0-9_-]+:[a-z0-9_-]+$|\#)#i', trim($value)) === 1) {
                    continue;
                }
                $waived = $checker->quotedRules($value, 'en', (string)$record['key'], (string)$field);
                foreach ($checker->check(CopyMetrics::plainText($value), FieldRole::fromField((string)$field, $table), 'en', 'desiderio', false, $waived) as $finding) {
                    if ($finding->isError()) {
                        $errors[] = sprintf('%s.%s %s: %s', $record['key'], $field, $finding->rule, $finding->message);
                    }
                }
            }
        }

        self::assertSame([], $errors);
    }

    public function testQuotationExceptionsPointAtTheirPayloadText(): void
    {
        $data = json_decode((string)file_get_contents(TalkPayload::DIRECTORY . '/quoted-copy.json'), true);
        self::assertIsArray($data);
        self::assertNotEmpty($data['entries']);
        foreach ($data['entries'] as $entry) {
            $record = $this->record((string)$entry['record']);
            self::assertArrayHasKey((string)$entry['field'], $record['set']);
            if ($entry['language'] === 'en') {
                self::assertSame($record['set'][$entry['field']], $entry['text'], 'an English exception quotes the payload exactly');
                self::assertSame(['limits'], $entry['rules']);
            } else {
                self::assertSame('de', $entry['language']);
                self::assertNotEmpty(array_intersect(['limits', 'address'], $entry['rules']));
            }
        }
    }

    public function testResolveReplacesInstallationSpecificValues(): void
    {
        $directory = sys_get_temp_dir() . '/nrtalk-' . bin2hex(random_bytes(4));
        mkdir($directory);
        try {
            file_put_contents($directory . '/lab-status.json', json_encode(['labels' => ['installed' => 'Installed'], 'modules' => ['nr-llm' => 'installed']]));
            file_put_contents($directory . '/01-b.payload.json', json_encode(['site' => 'desiderio', 'languageCode' => 'en', 'records' => [
                ['table' => 'tt_content', 'action' => 'create', 'key' => 'NEW_nrtalk_x_card', 'pid' => '{{page:/ai}}', 'match' => ['pid' => '{{page:/ai}}', 'rowDescription' => 'nrtalk:hub-x', 'sys_language_uid' => 0],
                    'set' => ['rowDescription' => 'nrtalk:hub-x', 'content' => '<p><a href="{{page:/missing|/ai/chat}}">chat</a> {{labstatus:nr-llm}}</p>', 'link' => '@NEW_nrtalk_page'],
                    'files' => ['media' => [['source' => 'EXT:site_package/missing.png'], ['source' => 'EXT:site_package/there.png']]]],
            ]]));
            file_put_contents($directory . '/00-a.payload.json', json_encode(['site' => 'desiderio', 'languageCode' => 'en', 'records' => [
                ['table' => 'pages', 'action' => 'create', 'key' => 'NEW_nrtalk_page', 'pid' => 0, 'match' => ['pid' => 0, 'slug' => '/ai/talk', 'sys_language_uid' => 0], 'set' => ['slug' => '/ai/talk']],
            ]]));
            $talk = TalkPayload::fromDirectory($directory);
            self::assertSame(['00-a.payload.json', '01-b.payload.json'], $talk->files);
            self::assertSame([], $talk->problems());
            $notices = [];
            $payload = $talk->resolve(
                2229,
                static fn (string $slug): ?int => ['/ai' => 2229, '/ai/chat' => 2084][$slug] ?? null,
                static fn (string $source): bool => $source === 'EXT:site_package/there.png',
                TalkPayload::labStatus($directory),
                $notices
            );
            self::assertSame(2229, $payload['records'][0]['pid']);
            self::assertSame(2229, $payload['records'][0]['match']['pid']);
            self::assertSame(2229, $payload['records'][1]['pid']);
            self::assertSame(2229, $payload['records'][1]['match']['pid']);
            self::assertSame('<p><a href="t3://page?uid=2084">chat</a> Installed</p>', $payload['records'][1]['set']['content']);
            self::assertSame('@NEW_nrtalk_page', $payload['records'][1]['set']['link']);
            self::assertSame([['source' => 'EXT:site_package/there.png']], $payload['records'][1]['files']['media']);
            self::assertCount(1, $notices);
            self::assertStringContainsString('missing.png', $notices[0]);

            $this->expectExceptionCode(1794000006);
            $talk->resolve(2229, static fn (string $slug): ?int => null, static fn (string $source): bool => true, ['nr-llm' => 'Installed']);
        } finally {
            array_map('unlink', glob($directory . '/*') ?: []);
            rmdir($directory);
        }
    }

    public function testProblemsFindDuplicateMarkersAndForwardReferences(): void
    {
        $directory = sys_get_temp_dir() . '/nrtalk-' . bin2hex(random_bytes(4));
        mkdir($directory);
        try {
            $element = static fn (string $key, string $marker, string $pid): array => ['table' => 'tt_content', 'action' => 'create', 'key' => $key, 'pid' => $pid,
                'match' => ['pid' => $pid, 'rowDescription' => $marker, 'sys_language_uid' => 0], 'set' => ['rowDescription' => $marker]];
            file_put_contents($directory . '/00.payload.json', json_encode(['site' => 'desiderio', 'languageCode' => 'en', 'records' => [
                $element('NEW_nrtalk_a', 'nrtalk:x:a', '@NEW_nrtalk_page'),
                $element('NEW_nrtalk_b', 'nrtalk:x:a', '@NEW_nrtalk_page'),
            ]]));
            $problems = TalkPayload::fromDirectory($directory)->problems();
            self::assertContains('00.payload.json record 0: @NEW_nrtalk_page is used before a record creates it', $problems);
            self::assertContains('00.payload.json record 1: duplicate marker nrtalk:x:a', $problems);
        } finally {
            array_map('unlink', glob($directory . '/*') ?: []);
            rmdir($directory);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function record(string $key): array
    {
        foreach (self::records() as $record) {
            if ($record['key'] === $key) {
                return $record;
            }
        }
        self::fail('No record ' . $key);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function children(string $key): array
    {
        $collections = TalkPayload::collections($this->record($key));

        return array_map(fn (string $child): array => $this->record($child), $collections[0]['keys'] ?? []);
    }

    /**
     * Field names, select values and collections of the Desiderio elements and child tables.
     *
     * @return array{types: array<string, array{fields: array<string, array{items: list<string>, required: bool}>, collections: array<string, array{table: string, minitems: int}>}>, tables: array<string, array{fields: array<string, array{items: list<string>, required: bool}>, collections: array<string, array{table: string, minitems: int}>}>}
     */
    private function schema(string $blocks): array
    {
        $types = [];
        $tables = [];
        $shared = [];
        foreach (glob($blocks . '/RecordTypes/*/config.yaml') ?: [] as $file) {
            $config = Yaml::parseFile($file);
            self::assertIsArray($config);
            if (isset($config['table'])) {
                $shared[(string)$config['table']] = $config['fields'] ?? [];
            }
        }
        $read = static function (array $fields, string $prefix) use (&$read, &$tables, $shared): array {
            $definition = ['fields' => [], 'collections' => []];
            foreach ($fields as $field) {
                if (!is_array($field) || !isset($field['identifier']) || in_array($field['type'] ?? '', ['Tab', 'Palette', 'Linebreak'], true)) {
                    if (is_array($field) && ($field['type'] ?? '') === 'Palette') {
                        $inner = $read($field['fields'] ?? [], $prefix);
                        $definition['fields'] += $inner['fields'];
                    }
                    continue;
                }
                $name = ($field['prefixField'] ?? false) === true && $prefix !== '' ? $prefix . '_' . $field['identifier'] : (string)$field['identifier'];
                if (($field['type'] ?? '') === 'Collection') {
                    $table = (string)($field['table'] ?? $field['foreign_table'] ?? '');
                    $definition['collections'][$name] = ['table' => $table, 'minitems' => (int)($field['minitems'] ?? 0)];
                    if (!isset($tables[$table])) {
                        $tables[$table] = $read($shared[$table] ?? ($field['fields'] ?? []), '');
                    }
                    continue;
                }
                $items = [];
                if (($field['type'] ?? '') === 'Select' && is_array($field['items'] ?? null)) {
                    $items = array_map(static fn (array $item): string => (string)$item['value'], $field['items']);
                }
                $definition['fields'][$name] = ['items' => $items, 'required' => ($field['required'] ?? false) === true];
            }

            return $definition;
        };
        foreach (glob($blocks . '/ContentElements/*/config.yaml') ?: [] as $file) {
            $config = Yaml::parseFile($file);
            self::assertIsArray($config);
            $typeName = (string)$config['typeName'];
            $types[$typeName] = $read($config['fields'] ?? [], $typeName);
        }

        return ['types' => $types, 'tables' => $tables];
    }
}
