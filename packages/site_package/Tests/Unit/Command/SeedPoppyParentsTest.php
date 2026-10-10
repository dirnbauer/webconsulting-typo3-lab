<?php
declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Command;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\EmptyRestrictionContainer;
use Webconsulting\SitePackage\Command\SeedPoppyCommand;

final class SeedPoppyParentsTest extends TestCase
{
    private Connection $connection;
    private SeedPoppyCommand $command;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => Connection::class]);
        $this->connection->defaultRestrictionContainer = EmptyRestrictionContainer::class;
        $this->connection->executeStatement('CREATE TABLE pages (uid INTEGER PRIMARY KEY, pid INTEGER, slug TEXT, deleted INTEGER DEFAULT 0, sys_language_uid INTEGER DEFAULT 0)');
        // Same slug in a different site, translated slug, and the production IDs.
        foreach ([[505, 0, '/', 0], [740, 0, '/other', 0], [878, 740, '/resources', 0], [2158, 505, '/resources', 0], [2163, 505, '/resources', 1]] as [$uid, $pid, $slug, $language]) {
            $this->connection->executeStatement('INSERT INTO pages (uid, pid, slug, sys_language_uid) VALUES (?, ?, ?, ?)', [$uid, $pid, $slug, $language]);
        }
        $pool = $this->createMock(ConnectionPool::class);
        $pool->expects(self::atLeastOnce())->method('getConnectionForTable')->with('pages')->willReturn($this->connection);
        $this->command = new SeedPoppyCommand($pool);
    }

    private function resolve(string $slug): int
    {
        return (new \ReflectionMethod($this->command, 'pageWithinRoot'))->invoke($this->command, $slug, 505);
    }

    public function testProductionParentIsResolvedWithoutUsingTheLocalUidOrAnotherSite(): void
    {
        self::assertSame(2158, $this->resolve('/resources'));
    }

    public function testAmbiguousParentsStopPublishing(): void
    {
        $this->connection->executeStatement("INSERT INTO pages (uid, pid, slug) VALUES (9999, 505, '/resources')");
        $this->expectException(\RuntimeException::class);
        $this->resolve('/resources');
    }

    public function testMissingParentStopsPublishing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->resolve('/missing');
    }
}
