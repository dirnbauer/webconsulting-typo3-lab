<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Command;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\EmptyRestrictionContainer;
use Webconsulting\SitePackage\Command\ReleaseInterruptedChatTurnsCommand;

final class ReleaseInterruptedChatTurnsCommandTest extends TestCase
{
    private const START = 1_800_000_000;

    private ?Connection $connection = null;

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => Connection::class]);
        $connection->defaultRestrictionContainer = EmptyRestrictionContainer::class;
        $connection->executeStatement(
            'CREATE TABLE tx_nrmcpagent_conversation (uid INTEGER PRIMARY KEY, status TEXT, tstamp INTEGER, deleted INTEGER DEFAULT 0,'
            . " error_message TEXT DEFAULT '', error_code TEXT DEFAULT '', approval_run_uuid TEXT DEFAULT '',"
            . " approval_decision TEXT DEFAULT '', approval_turn_digest TEXT DEFAULT '')"
        );
        foreach ([
            // uid, status, tstamp, deleted
            [1, 'locked', self::START - 60, 0],       // killed mid-turn by the stop
            [2, 'tool_loop', self::START - 3600, 0],  // the same, in a tool loop
            [3, 'processing', self::START - 60, 0],   // queued: the workers take it
            [4, 'locked', self::START + 5, 0],        // claimed after the start
            [5, 'idle', self::START - 60, 0],
            [6, 'awaiting_approval', self::START - 60, 0],
            [7, 'locked', self::START - 60, 1],       // deleted
        ] as [$uid, $status, $tstamp, $deleted]) {
            $connection->executeStatement(
                "INSERT INTO tx_nrmcpagent_conversation (uid, status, tstamp, deleted, approval_run_uuid, approval_decision) VALUES (?, ?, ?, ?, 'run', 'grant')",
                [$uid, $status, $tstamp, $deleted]
            );
        }
        $this->connection = $connection;
    }

    private function tester(): CommandTester
    {
        $connection = $this->connection;
        self::assertNotNull($connection);
        $pool = $this->createStub(ConnectionPool::class);
        $pool->method('getQueryBuilderForTable')->willReturnCallback(static fn () => $connection->createQueryBuilder());

        return new CommandTester(new ReleaseInterruptedChatTurnsCommand($pool));
    }

    /**
     * @return array<int, string>
     */
    private function statuses(): array
    {
        self::assertNotNull($this->connection);
        $statuses = [];
        foreach ($this->connection->executeQuery('SELECT uid, status FROM tx_nrmcpagent_conversation ORDER BY uid')->fetchAllAssociative() as $row) {
            $statuses[(int)$row['uid']] = (string)$row['status'];
        }

        return $statuses;
    }

    public function testOnlyRunningTurnsFromBeforeTheStartAreReleased(): void
    {
        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['--before' => (string)self::START]));
        self::assertStringContainsString('2 interrupted chat turn(s) released.', $tester->getDisplay());
        self::assertSame(
            [1 => 'failed', 2 => 'failed', 3 => 'processing', 4 => 'locked', 5 => 'idle', 6 => 'awaiting_approval', 7 => 'locked'],
            $this->statuses()
        );

        self::assertNotNull($this->connection);
        $released = $this->connection->executeQuery('SELECT * FROM tx_nrmcpagent_conversation WHERE uid = 1')->fetchAssociative();
        self::assertIsArray($released);
        self::assertSame(ReleaseInterruptedChatTurnsCommand::MESSAGE, $released['error_message']);
        // A decision left on a failed row would be carried out by its Retry.
        self::assertSame('', $released['approval_run_uuid']);
        self::assertSame('', $released['approval_decision']);
    }

    public function testADryRunCountsAndChangesNothing(): void
    {
        $before = $this->statuses();
        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['--before' => (string)self::START, '--dry-run' => true]));
        self::assertStringContainsString('2 interrupted chat turn(s) would be released', $tester->getDisplay());
        self::assertSame($before, $this->statuses());
    }

    public function testAnInvalidMomentIsRefused(): void
    {
        $before = $this->statuses();
        self::assertSame(Command::INVALID, $this->tester()->execute(['--before' => 'yesterday']));
        self::assertSame($before, $this->statuses());
    }
}
