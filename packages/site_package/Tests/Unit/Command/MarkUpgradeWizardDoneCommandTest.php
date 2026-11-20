<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;
use Webconsulting\SitePackage\Command\MarkUpgradeWizardDoneCommand;

final class MarkUpgradeWizardDoneCommandTest extends TestCase
{
    public function testAWizardIsMarkedOnceAndNeverRun(): void
    {
        $stored = [];
        $registry = $this->createMock(Registry::class);
        $registry->method('get')->willReturnCallback(static function (string $namespace, string $key, mixed $default = null) use (&$stored): mixed {
            return $stored[$namespace][$key] ?? $default;
        });
        $registry->expects(self::once())->method('set')->willReturnCallback(static function (string $namespace, string $key, mixed $value) use (&$stored): void {
            $stored[$namespace][$key] = $value;
        });
        $tester = new CommandTester(new MarkUpgradeWizardDoneCommand($registry));

        self::assertSame(0, $tester->execute(['wizards' => [MarkDoneFixtureWizard::class]]));
        self::assertStringContainsString('fixtureWizard', $tester->getDisplay());
        self::assertSame(1, $stored['installUpdate'][MarkDoneFixtureWizard::class]);
        self::assertFalse(MarkDoneFixtureWizard::$ran);

        self::assertSame(0, $tester->execute(['wizards' => ['\\' . MarkDoneFixtureWizard::class]]));
        self::assertStringContainsString('already marked as done', $tester->getDisplay());
    }

    public function testAClassThatIsNoWizardFails(): void
    {
        $registry = $this->createMock(Registry::class);
        $registry->expects(self::never())->method('set');
        $tester = new CommandTester(new MarkUpgradeWizardDoneCommand($registry));

        self::assertSame(1, $tester->execute(['wizards' => [self::class, 'Missing\\Wizard']]));
    }
}

#[UpgradeWizard('fixtureWizard')]
final class MarkDoneFixtureWizard implements UpgradeWizardInterface
{
    public static bool $ran = false;

    public function getTitle(): string
    {
        return 'Fixture';
    }

    public function getDescription(): string
    {
        return '';
    }

    public function executeUpdate(): bool
    {
        self::$ran = true;

        return true;
    }

    public function updateNecessary(): bool
    {
        return true;
    }

    public function getPrerequisites(): array
    {
        return [];
    }
}
