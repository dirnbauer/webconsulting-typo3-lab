<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Command;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\EmptyRestrictionContainer;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webconsulting\SitePackage\Command\ContentApplyCommand;
use Webconsulting\SitePackage\Command\SeedNetresearchTalkCommand;

final class SeedNetresearchTalkCommandTest extends TestCase
{
    private ?SeedNetresearchTalkCommand $seeder = null;

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => Connection::class]);
        $connection->defaultRestrictionContainer = EmptyRestrictionContainer::class;
        $connection->executeStatement('CREATE TABLE pages (uid INTEGER PRIMARY KEY, pid INTEGER, slug TEXT, deleted INTEGER DEFAULT 0, sys_language_uid INTEGER DEFAULT 0, t3ver_wsid INTEGER DEFAULT 0)');
        // References of a screenshot (sys_file 7) on two pages, a translation
        // on the same page, a deleted and a workspace reference.
        $connection->executeStatement('CREATE TABLE sys_file_reference (uid INTEGER PRIMARY KEY, pid INTEGER, uid_local INTEGER, deleted INTEGER DEFAULT 0, t3ver_wsid INTEGER DEFAULT 0)');
        foreach ([[1, 9196, 7, 0, 0], [2, 9196, 7, 0, 0], [3, 9198, 7, 0, 0], [4, 9300, 7, 1, 0], [5, 9400, 7, 0, 3], [6, 9500, 8, 0, 0]] as [$uid, $pid, $file, $deleted, $workspace]) {
            $connection->executeStatement('INSERT INTO sys_file_reference (uid, pid, uid_local, deleted, t3ver_wsid) VALUES (?, ?, ?, ?, ?)', [$uid, $pid, $file, $deleted, $workspace]);
        }
        // Desiderio (505) on production uids, another site with the same slug,
        // a translation, a workspace version and a deleted page.
        foreach ([
            [505, 0, '/', 0, 0, 0], [740, 0, '/', 0, 0, 0], [878, 740, '/ai', 0, 0, 0], [2229, 505, '/ai', 0, 0, 0],
            [2240, 505, '/ai', 1, 0, 0], [2250, 505, '/ai', 0, 4, 0], [2260, 505, '/ai', 0, 0, 1], [9001, 2229, '/ai/netresearch-talk', 0, 0, 0],
        ] as [$uid, $pid, $slug, $language, $workspace, $deleted]) {
            $connection->executeStatement('INSERT INTO pages (uid, pid, slug, sys_language_uid, t3ver_wsid, deleted) VALUES (?, ?, ?, ?, ?, ?)', [$uid, $pid, $slug, $language, $workspace, $deleted]);
        }
        $pool = $this->createStub(ConnectionPool::class);
        $pool->method('getConnectionForTable')->willReturn($connection);
        // A new builder per query, as the real pool hands out.
        $pool->method('getQueryBuilderForTable')->willReturnCallback(static fn () => $connection->createQueryBuilder());
        $this->seeder = new SeedNetresearchTalkCommand($pool, $this->createStub(SiteFinder::class), $this->createStub(StorageRepository::class), $this->createStub(CacheManager::class));
    }

    private function command(): SeedNetresearchTalkCommand
    {
        self::assertNotNull($this->seeder);

        return $this->seeder;
    }

    private function resolve(string $slug): int
    {
        $result = (new \ReflectionMethod($this->command(), 'pageWithinRoot'))->invoke($this->command(), $slug, 505);
        self::assertIsInt($result);

        return $result;
    }

    public function testAReplacedScreenshotFlushesEveryLivePageThatShowsIt(): void
    {
        $pages = (new \ReflectionMethod($this->command(), 'pagesShowing'))->invoke($this->command(), 7);

        self::assertSame([9196, 9198], $pages);
    }

    public function testTheParentIsFoundBySlugWithinTheDesideroSiteOnly(): void
    {
        self::assertSame(2229, $this->resolve('/ai'));
        self::assertSame(9001, $this->resolve('/ai/netresearch-talk'));
    }

    public function testAMissingPageFailsLoudly(): void
    {
        $this->expectExceptionCode(1794000207);
        $this->resolve('/ai/nowhere');
    }

    public function testAMissingFallbackPageIsNull(): void
    {
        self::assertNull((new \ReflectionMethod($this->command(), 'pageWithinRootOrNull'))->invoke($this->command(), '/ai/nowhere', 505));
    }

    public function testTheCommandOffersADryRunAndTheProductionSwitch(): void
    {
        $definition = $this->command()->getDefinition();
        self::assertTrue($definition->hasOption('dry-run'));
        self::assertTrue($definition->hasOption('allow-production'));
        self::assertSame('sitepackage:seed-netresearch-talk', $this->command()->getName());
        self::assertInstanceOf(CommandTester::class, new CommandTester($this->command()));
    }

    public function testContentApplyReportsTheUidOfEveryKey(): void
    {
        self::assertSame(
            ['NEW_a' => 12, 'NEW_b' => 40, 'NEW_d' => 41],
            ContentApplyCommand::resolvedKeys(['NEW_a' => 12, 'NEW_b' => 'NEW_b', 'NEW_c' => 'NEW_c', 'NEW_d' => 'NEW_d'], ['NEW_b' => 40, 'NEW_d' => '41'])
        );
    }
}
