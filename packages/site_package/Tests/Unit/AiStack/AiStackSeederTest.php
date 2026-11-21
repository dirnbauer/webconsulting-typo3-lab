<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\AiStack;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\EmptyRestrictionContainer;
use Webconsulting\SitePackage\AiStack\AiStackSeeder;
use Webconsulting\SitePackage\AiStack\RecordWriter;
use Webconsulting\SitePackage\AiStack\SettingStore;
use Webconsulting\SitePackage\AiStack\ToolDescriptor;
use Webconsulting\SitePackage\AiStack\ToolStates;

/**
 * The seed must give the same result locally and in production: it finds its
 * records by identifier (never by uid), and a second run changes nothing.
 */
final class AiStackSeederTest extends TestCase
{
    private Connection $connection;
    private SqliteRecordWriter $writer;
    private MemoryToolStates $tools;
    private MemorySettingStore $settings;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => Connection::class]);
        $this->connection->defaultRestrictionContainer = EmptyRestrictionContainer::class;
        $common = 'uid INTEGER PRIMARY KEY AUTOINCREMENT, pid INTEGER DEFAULT 0, deleted INTEGER DEFAULT 0, hidden INTEGER DEFAULT 0, sorting INTEGER DEFAULT 0, tstamp INTEGER DEFAULT 0, crdate INTEGER DEFAULT 0';
        $this->connection->executeStatement("CREATE TABLE tx_nrllm_model ($common, identifier TEXT, capabilities TEXT, is_active INTEGER DEFAULT 1, is_default INTEGER DEFAULT 0)");
        $this->connection->executeStatement("CREATE TABLE tx_nrllm_configuration ($common, identifier TEXT, name TEXT, description TEXT, model_uid INTEGER DEFAULT 0, model_selection_mode TEXT DEFAULT '', system_prompt TEXT DEFAULT '', temperature NUMERIC DEFAULT 0.7, max_tokens INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1, is_default INTEGER DEFAULT 0)");
        $this->connection->executeStatement("CREATE TABLE tx_nrllm_task ($common, identifier TEXT, name TEXT, description TEXT, category TEXT, configuration_uid INTEGER DEFAULT 0, prompt_template TEXT, input_type TEXT, output_format TEXT, is_active INTEGER DEFAULT 1, is_system INTEGER DEFAULT 0)");
        $this->connection->executeStatement('CREATE TABLE be_groups (uid INTEGER PRIMARY KEY AUTOINCREMENT, pid INTEGER DEFAULT 0, deleted INTEGER DEFAULT 0, hidden INTEGER DEFAULT 0, title TEXT, groupMods TEXT, custom_options TEXT)');

        // Uids as in no particular installation: the seed must not care.
        $this->connection->executeStatement("INSERT INTO tx_nrllm_model (uid, identifier, capabilities, is_active, is_default) VALUES (31, 'image', 'image', 1, 0), (32, 'chat-only', 'chat', 1, 1), (33, 'tool-model', 'chat,tools', 1, 0), (34, 'retired', 'chat,tools', 0, 0)");
        $this->connection->executeStatement("INSERT INTO tx_nrllm_configuration (uid, identifier, name, model_uid) VALUES (77, 'content-assistant', 'Content Assistant', 32)");
        $this->connection->executeStatement("INSERT INTO be_groups (uid, title, groupMods, custom_options) VALUES (5, 'main', 'web_layout', ''), (6, 'Vault provisioning', '', '')");

        $this->writer = new SqliteRecordWriter($this->connection);
        $this->tools = new MemoryToolStates([
            new ToolDescriptor('get_page_content', 'content', true, false),
            new ToolDescriptor('ask_choice', 'content', false, false),
            new ToolDescriptor('update_page_metadata', 'editing', false, true),
            new ToolDescriptor('adopt_client_file', 'files', false, true),
        ]);
        $this->settings = new MemorySettingStore(['EXTENSIONS/nr_mcp_agent/llmTaskUid' => '0']);
    }

    private function seeder(): AiStackSeeder
    {
        $pool = $this->createStub(ConnectionPool::class);
        $pool->method('getQueryBuilderForTable')->willReturnCallback(fn() => $this->connection->createQueryBuilder());
        $pool->method('getConnectionForTable')->willReturn($this->connection);

        return new AiStackSeeder($pool, $this->writer, $this->tools, $this->settings);
    }

    public function testTheFirstRunCreatesEverythingAndTheSecondChangesNothing(): void
    {
        $first = $this->seeder()->seed(false);
        self::assertNotSame([], $first);

        $configuration = $this->connection->fetchAssociative("SELECT * FROM tx_nrllm_configuration WHERE identifier = 'backend-ai-chat'");
        self::assertIsArray($configuration);
        self::assertSame(33, (int)$configuration['model_uid'], 'the first active tool-capable model, the default one cannot call tools');
        self::assertEqualsWithDelta(0.3, (float)$configuration['temperature'], 0.0001);
        self::assertSame(4096, (int)$configuration['max_tokens']);
        self::assertStringContainsString('webconsulting TYPO3 Lab', (string)$configuration['system_prompt']);

        $task = $this->connection->fetchAssociative("SELECT * FROM tx_nrllm_task WHERE identifier = 'backend-ai-chat'");
        self::assertIsArray($task);
        self::assertSame((int)$configuration['uid'], (int)$task['configuration_uid']);
        self::assertSame((string)$task['uid'], $this->settings->values['EXTENSIONS/nr_mcp_agent/llmTaskUid']);
        self::assertSame(AiStackSeeder::CHAT_TASK_PROMPT, $task['prompt_template']);
        self::assertStringContainsString('When you answer in German, address the user formally with "Sie", never with "du"', (string)$task['prompt_template'], 'the lab\'s German copy says "Sie"');
        $translateDe = $this->connection->fetchOne("SELECT prompt_template FROM tx_nrllm_task WHERE identifier = 'translate-de'");
        self::assertStringContainsString('address the reader formally with "Sie"', (string)$translateDe);

        $cowriter = $this->connection->fetchAllAssociative("SELECT identifier, configuration_uid, category FROM tx_nrllm_task WHERE category = 'content' ORDER BY sorting, uid");
        self::assertSame(['improve-text', 'summarize', 'extend', 'fix-grammar', 'translate-en', 'translate-de'], array_column($cowriter, 'identifier'));
        self::assertSame([77], array_values(array_unique(array_map('intval', array_column($cowriter, 'configuration_uid')))));

        self::assertSame(['ask_choice' => true, 'update_page_metadata' => true], $this->tools->toolOverrides());

        $main = $this->connection->fetchAssociative('SELECT groupMods, custom_options FROM be_groups WHERE uid = 5');
        self::assertIsArray($main);
        self::assertSame('web_layout,' . implode(',', AiStackSeeder::EDITOR_MODULES), $main['groupMods'], 'additive: the group keeps its modules');
        self::assertSame('tx_nrmcpagent:approve_own_changes', $main['custom_options']);
        $vault = $this->connection->fetchAssociative('SELECT groupMods FROM be_groups WHERE uid = 6');
        self::assertSame(['groupMods' => ''], $vault, 'technical groups get nothing');

        $writes = $this->writer->writes;
        self::assertSame([], $this->seeder()->seed(false), 'a second run must report no change');
        self::assertSame($writes, $this->writer->writes, 'and write nothing');
    }

    public function testTheLabsToolModelIsPreferredOverTheDefault(): void
    {
        $this->connection->executeStatement("INSERT INTO tx_nrllm_model (uid, identifier, capabilities, is_active, is_default, sorting) VALUES (35, 'gpt-5-mini', 'chat,tools', 1, 0, 99)");
        $this->connection->executeStatement("UPDATE tx_nrllm_model SET is_default = 1, capabilities = 'chat,tools' WHERE uid = 32");

        $this->seeder()->seed(false);

        self::assertSame(35, (int)$this->connection->fetchOne("SELECT model_uid FROM tx_nrllm_configuration WHERE identifier = 'backend-ai-chat'"));
    }

    public function testADryRunWritesNothing(): void
    {
        $changes = $this->seeder()->seed(true);

        self::assertNotSame([], $changes);
        self::assertSame(0, $this->writer->writes);
        self::assertSame([], $this->tools->toolOverrides());
        self::assertSame('0', $this->settings->values['EXTENSIONS/nr_mcp_agent/llmTaskUid']);
        self::assertSame(0, (int)$this->connection->fetchOne("SELECT COUNT(*) FROM tx_nrllm_task"));
    }

    public function testExistingRecordsAreFoundByIdentifierAndOnlyDifferencesAreWritten(): void
    {
        $this->seeder()->seed(false);
        $this->connection->executeStatement("UPDATE tx_nrllm_configuration SET temperature = 1.0, hidden = 1 WHERE identifier = 'backend-ai-chat'");
        $this->connection->executeStatement("UPDATE tx_nrllm_task SET prompt_template = 'edited' WHERE identifier = 'summarize'");

        $changes = $this->seeder()->seed(false);

        self::assertCount(2, $changes, implode("\n", $changes));
        self::assertStringContainsString('update temperature, hidden', $changes[0]);
        self::assertStringContainsString('"summarize"', $changes[1]);
        self::assertSame(1, (int)$this->connection->fetchOne("SELECT COUNT(*) FROM tx_nrllm_configuration WHERE identifier = 'backend-ai-chat'"), 'no duplicate');
    }

    public function testAnInstallationSeededBeforeTheSieRuleGetsOnlyTheTwoPrompts(): void
    {
        $this->seeder()->seed(false);
        $this->connection->executeStatement("UPDATE tx_nrllm_task SET prompt_template = 'Look facts up with the tools before you answer. Every change waits for the approval of the person in the chat; say what will change before you propose it.' WHERE identifier = 'backend-ai-chat'");
        $this->connection->executeStatement("UPDATE tx_nrllm_task SET prompt_template = 'Translate the following text into German. Keep its tone and formatting, and return only the translation.' WHERE identifier = 'translate-de'");

        $changes = $this->seeder()->seed(false);

        self::assertCount(2, $changes, implode("\n", $changes));
        self::assertStringContainsString('"backend-ai-chat"', $changes[0]);
        self::assertStringContainsString('update prompt_template', $changes[0]);
        self::assertStringContainsString('"translate-de"', $changes[1]);
        self::assertSame([], $this->seeder()->seed(false), 'and the next run changes nothing');
    }

    public function testTwoLiveRecordsWithTheSameIdentifierStopTheSeed(): void
    {
        $this->connection->executeStatement("INSERT INTO tx_nrllm_configuration (identifier) VALUES ('backend-ai-chat'), ('backend-ai-chat')");

        $this->expectException(\RuntimeException::class);
        $this->seeder()->seed(false);
    }

    public function testADeletedRecordIsNotReused(): void
    {
        $this->connection->executeStatement("INSERT INTO tx_nrllm_task (uid, identifier, deleted) VALUES (900, 'backend-ai-chat', 1)");

        $this->seeder()->seed(false);

        self::assertNotSame('900', $this->settings->values['EXTENSIONS/nr_mcp_agent/llmTaskUid']);
    }

    public function testTheSettingsSyncAloneReadsTheTaskAndWritesOnlyTheSetting(): void
    {
        self::assertSame([], $this->seeder()->synchronizeSettings(false), 'without the task there is nothing to point at');

        $this->seeder()->seed(false);
        $this->settings->values['EXTENSIONS/nr_mcp_agent/llmTaskUid'] = '0';
        $writes = $this->writer->writes;

        self::assertCount(1, $this->seeder()->synchronizeSettings(false));
        self::assertSame($writes, $this->writer->writes);
        self::assertSame([], $this->seeder()->synchronizeSettings(false));
    }

    public function testWithoutAToolCapableModelNothingIsWritten(): void
    {
        $this->connection->executeStatement("UPDATE tx_nrllm_model SET is_active = 0 WHERE capabilities LIKE '%tools%'");

        $this->expectException(\RuntimeException::class);
        try {
            $this->seeder()->seed(false);
        } finally {
            self::assertSame(0, $this->writer->writes);
        }
    }

    public function testChangedFieldsComparesNumbersAsNumbers(): void
    {
        self::assertSame([], AiStackSeeder::changedFields(['temperature' => 0.3, 'max_tokens' => 4096, 'name' => 'A'], ['temperature' => '0.30', 'max_tokens' => '4096', 'name' => 'A']));
        self::assertSame(['name' => 'B', 'is_active' => 1], AiStackSeeder::changedFields(['name' => 'B', 'is_active' => 1], ['name' => 'A', 'is_active' => '0']));
    }

    public function testWithItemsAppendsOnlyMissingItems(): void
    {
        self::assertSame('a,b,c', AiStackSeeder::withItems('a, b', ['b', 'c']));
        self::assertNull(AiStackSeeder::withItems('c,b', ['b', 'c']));
        self::assertSame('b', AiStackSeeder::withItems('', ['b']));
    }
}

/** Writes into the test database the way DataHandler would, and counts. */
final class SqliteRecordWriter implements RecordWriter
{
    public int $writes = 0;

    public function __construct(private readonly Connection $connection) {}

    public function create(string $table, array $fields): int
    {
        ++$this->writes;
        // Plain SQL: TYPO3's Connection::insert() needs the runtime cache.
        // Like DataHandler on a page, a new record goes to the top.
        if ($table === 'tx_nrllm_task') {
            $fields['sorting'] = (int)$this->connection->fetchOne('SELECT COALESCE(MIN(sorting), 1000) FROM tx_nrllm_task') - 1;
        }
        $this->connection->executeStatement(
            sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', array_keys($fields)), implode(', ', array_fill(0, count($fields), '?'))),
            array_values($fields),
        );

        return (int)$this->connection->lastInsertId();
    }

    public function update(string $table, int $uid, array $fields): void
    {
        ++$this->writes;
        $assignments = implode(', ', array_map(static fn(string $field): string => $field . ' = ?', array_keys($fields)));
        $this->connection->executeStatement(sprintf('UPDATE %s SET %s WHERE uid = ?', $table, $assignments), [...array_values($fields), $uid]);
    }

    public function moveAfter(string $table, int $uid, int $afterUid): void
    {
        ++$this->writes;
        $this->connection->executeStatement(sprintf('UPDATE %1$s SET sorting = (SELECT sorting FROM %1$s WHERE uid = ?) + 1 WHERE uid = ?', $table), [$afterUid, $uid]);
    }
}

final class MemoryToolStates implements ToolStates
{
    /** @var array<string, bool> */
    private array $tools = [];
    /** @var array<string, bool> */
    private array $groups = [];

    /** @param list<ToolDescriptor> $descriptors */
    public function __construct(private readonly array $descriptors) {}

    public function tools(): array
    {
        return $this->descriptors;
    }

    public function toolOverrides(): array
    {
        ksort($this->tools);

        return $this->tools;
    }

    public function groupOverrides(): array
    {
        return $this->groups;
    }

    public function setTool(string $name, bool $enabled): void
    {
        $this->tools[$name] = $enabled;
    }

    public function setGroup(string $name, bool $enabled): void
    {
        $this->groups[$name] = $enabled;
    }
}

final class MemorySettingStore implements SettingStore
{
    /** @param array<string, string> $values */
    public function __construct(public array $values) {}

    public function get(string $path): ?string
    {
        return $this->values[$path] ?? null;
    }

    public function set(string $path, string $value): void
    {
        $this->values[$path] = $value;
    }
}
