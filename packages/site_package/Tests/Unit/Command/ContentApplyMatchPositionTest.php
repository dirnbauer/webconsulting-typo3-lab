<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Command;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\EmptyRestrictionContainer;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Schema\Field\FieldCollection;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use Webconsulting\SitePackage\Command\ContentApplyCommand;

/**
 * `"_position": n` finds the n-th matching row in stored order, so a second
 * run finds collection items again after DataHandler renumbered their sorting.
 */
final class ContentApplyMatchPositionTest extends TestCase
{
    private ?ContentApplyCommand $command = null;

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => Connection::class]);
        $connection->defaultRestrictionContainer = EmptyRestrictionContainer::class;
        $connection->executeStatement('CREATE TABLE data_table_cells (uid INTEGER PRIMARY KEY, pid INTEGER, foreign_table_parent_uid INTEGER, sorting INTEGER, value TEXT, sys_language_uid INTEGER DEFAULT 0, deleted INTEGER DEFAULT 0, t3ver_wsid INTEGER DEFAULT 0)');
        // Row 7's cells after a translate run: renumbered, two equal values, one deleted, one translation.
        foreach ([
            [11, 7, 1, 'AI Search', 0, 0], [12, 7, 9730, '–', 0, 0], [13, 7, 15875, '–', 0, 0],
            [14, 7, 5000, 'gone', 0, 1], [15, 7, 2, '–', 1, 0], [16, 8, 1, 'other row', 0, 0],
        ] as [$uid, $parent, $sorting, $value, $language, $deleted]) {
            $connection->executeStatement(
                'INSERT INTO data_table_cells (uid, pid, foreign_table_parent_uid, sorting, value, sys_language_uid, deleted) VALUES (?, 1, ?, ?, ?, ?, ?)',
                [$uid, $parent, $sorting, $value, $language, $deleted]
            );
        }
        $pool = $this->createStub(ConnectionPool::class);
        $pool->method('getQueryBuilderForTable')->willReturnCallback(static fn (): QueryBuilder => $connection->createQueryBuilder());
        $schemas = $this->createStub(TcaSchemaFactory::class);
        $schemas->method('get')->willReturn(new TcaSchema('data_table_cells', new FieldCollection([]), ['sortby' => 'sorting', 'delete' => 'deleted']));
        $this->command = new ContentApplyCommand($pool, $schemas, $this->createStub(StorageRepository::class));
    }

    /**
     * @param array<string, mixed> $match
     */
    private function find(array $match): ?int
    {
        self::assertNotNull($this->command);
        $uid = (new \ReflectionMethod($this->command, 'findMatch'))->invoke($this->command, 'data_table_cells', $match);
        self::assertTrue($uid === null || is_int($uid));

        return $uid;
    }

    public function testThePositionCountsLiveRowsInStoredOrder(): void
    {
        $row = ['foreign_table_parent_uid' => 7, 'sys_language_uid' => 0];
        self::assertSame(11, $this->find($row + ['_position' => 1]));
        self::assertSame(12, $this->find($row + ['_position' => 2]));
        self::assertSame(13, $this->find($row + ['_position' => 3]));
        self::assertNull($this->find($row + ['_position' => 4]));
    }

    public function testAMatchOnTheOldSortingFindsNothingAfterRenumbering(): void
    {
        self::assertNull($this->find(['foreign_table_parent_uid' => 7, 'sys_language_uid' => 0, 'sorting' => 2]));
    }

    public function testAPositionMustBeAnIntegerFromOne(): void
    {
        self::assertNull(ContentApplyCommand::matchPosition(['pid' => 1]));
        self::assertSame(3, ContentApplyCommand::matchPosition(['_position' => 3]));
        $this->expectExceptionCode(1794000301);
        ContentApplyCommand::matchPosition(['_position' => 0]);
    }
}
