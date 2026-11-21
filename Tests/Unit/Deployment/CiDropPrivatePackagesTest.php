<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Deployment;

use PHPUnit\Framework\TestCase;
use Webconsulting\Lab\Build\CiDropPrivatePackages;

/**
 * GitHub has no copy of the in2code token, so the Quality job drops Powermail
 * and Powermail Conditions from its throw-away checkout
 * (Build/Scripts/ci-drop-private-packages.php). The expected hashes of the
 * fixtures were computed with Composer's own Locker::getContentHash() and
 * cweagans' Locker::getCollectionHash(), not with the script.
 */
final class CiDropPrivatePackagesTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/Fixtures/CiDropPrivatePackages';

    public static function setUpBeforeClass(): void
    {
        require_once self::root() . '/Build/Scripts/ci-drop-private-packages.php';
    }

    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function read(string $path): string
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents, $path);

        return $contents;
    }

    public function testFixtureHashesAreComposersOwn(): void
    {
        self::assertSame('cad1b1841182300d34153fff4597ee0c', CiDropPrivatePackages::composerContentHash(self::read(self::FIXTURES . '/input/composer.json')));
        self::assertSame('0144bdfb81aadc6eb0fbeacacae1e9f7', CiDropPrivatePackages::composerContentHash(self::read(self::FIXTURES . '/expected/composer.json')));
        self::assertSame('e4db34acbd92172567a0e94991b04a5220349ad1a5c6cf0d840cd9439b550d9f', CiDropPrivatePackages::patchesLockHash(self::read(self::FIXTURES . '/input/patches.lock.json')));
        self::assertSame('4b93e2269abc2cc5ab9823b5e7891a7f4bf5a7b0bb375cd95279aee1dabb5de2', CiDropPrivatePackages::patchesLockHash(self::read(self::FIXTURES . '/expected/patches.lock.json')));
    }

    /**
     * The repository's own files, before the drop (developer checkout) and
     * after it (CI checkout): the hash they carry is the one the script computes.
     */
    public function testRepositoryHashesMatchTheScript(): void
    {
        $lock = json_decode(self::read(self::root() . '/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($lock);
        self::assertSame($lock['content-hash'], CiDropPrivatePackages::composerContentHash(self::read(self::root() . '/composer.json')));

        $patchesLock = self::read(self::root() . '/patches.lock.json');
        $patches = json_decode($patchesLock, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($patches);
        self::assertSame($patches['_hash'], CiDropPrivatePackages::patchesLockHash($patchesLock));
    }

    public function testDropProducesTheExpectedFiles(): void
    {
        $result = CiDropPrivatePackages::drop(
            self::read(self::FIXTURES . '/input/composer.json'),
            self::read(self::FIXTURES . '/input/composer.lock'),
            self::read(self::FIXTURES . '/input/patches.lock.json'),
        );

        self::assertSame(self::read(self::FIXTURES . '/expected/composer.json'), $result['composerJson']);
        self::assertSame(self::read(self::FIXTURES . '/expected/composer.lock'), $result['composerLock']);
        self::assertSame(self::read(self::FIXTURES . '/expected/patches.lock.json'), $result['patchesLock']);
        self::assertStringNotContainsString('in2code', $result['composerJson']);
        self::assertStringNotContainsString('in2code', $result['composerLock']);
        self::assertStringNotContainsString('in2code', (string)$result['patchesLock']);
        // Empty objects stay objects: Composer writes {} and so must we.
        self::assertStringContainsString('"stability-flags": {}', $result['composerLock']);
    }

    public function testASecondRunChangesNothing(): void
    {
        $composerJson = self::read(self::FIXTURES . '/expected/composer.json');
        $composerLock = self::read(self::FIXTURES . '/expected/composer.lock');
        $patchesLock = self::read(self::FIXTURES . '/expected/patches.lock.json');

        $result = CiDropPrivatePackages::drop($composerJson, $composerLock, $patchesLock);

        self::assertSame($composerJson, $result['composerJson']);
        self::assertSame($composerLock, $result['composerLock']);
        self::assertSame($patchesLock, $result['patchesLock']);
        self::assertSame([], $result['messages']);
    }

    public function testTheRepositoryLockRoundTripsByteForByte(): void
    {
        $lock = self::read(self::root() . '/composer.lock');
        $decoded = json_decode($lock, false, 512, JSON_THROW_ON_ERROR);

        self::assertSame($lock, CiDropPrivatePackages::encode($decoded, '    '));
    }

    public function testAnotherPackageRequiringPowermailStopsTheDrop(): void
    {
        $lock = json_decode(self::read(self::FIXTURES . '/input/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($lock);
        $lock['packages'][2]['require']['in2code/powermail'] = '^14.0';

        $this->expectExceptionCode(1790200006);
        CiDropPrivatePackages::drop(
            self::read(self::FIXTURES . '/input/composer.json'),
            (string)json_encode($lock),
            null,
        );
    }

    public function testAPackageRequiringAReplacedNameStopsTheDrop(): void
    {
        $lock = json_decode(self::read(self::FIXTURES . '/input/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($lock);
        $lock['packages'][2]['require']['typo3-ter/powermail'] = '*';

        $this->expectExceptionCode(1790200006);
        CiDropPrivatePackages::drop(
            self::read(self::FIXTURES . '/input/composer.json'),
            (string)json_encode($lock),
            null,
        );
    }

    public function testAnUnknownComposerRepositoryStopsTheDrop(): void
    {
        $json = json_decode(self::read(self::FIXTURES . '/input/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($json);
        $json['repositories'][] = ['type' => 'composer', 'url' => 'https://example.repo.packagist.com/someone/'];

        $this->expectExceptionCode(1790200004);
        CiDropPrivatePackages::drop(
            (string)json_encode($json),
            self::read(self::FIXTURES . '/input/composer.lock'),
            null,
        );
    }

    public function testTheIn2codeRepositoryMustServeOnlyThePrivatePackages(): void
    {
        $json = json_decode(self::read(self::FIXTURES . '/input/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($json);
        $json['repositories'][0]['only'][] = 'in2code/femanager';

        $this->expectExceptionCode(1790200005);
        CiDropPrivatePackages::drop(
            (string)json_encode($json),
            self::read(self::FIXTURES . '/input/composer.lock'),
            null,
        );
    }

    public function testPublicPackagistMayStay(): void
    {
        $json = json_decode(self::read(self::FIXTURES . '/input/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($json);
        $json['repositories'][] = ['type' => 'composer', 'url' => 'https://repo.packagist.org'];

        $result = CiDropPrivatePackages::drop(
            (string)json_encode($json),
            self::read(self::FIXTURES . '/input/composer.lock'),
            null,
        );

        self::assertStringContainsString('https://repo.packagist.org', $result['composerJson']);
        self::assertNull($result['patchesLock']);
    }

    public function testItRefusesToRunOutsideCiWithoutForce(): void
    {
        $directory = sys_get_temp_dir() . '/ci-drop-' . bin2hex(random_bytes(6));
        mkdir($directory);
        try {
            foreach (['composer.json', 'composer.lock', 'patches.lock.json'] as $file) {
                copy(self::FIXTURES . '/input/' . $file, $directory . '/' . $file);
            }
            $before = self::read($directory . '/composer.lock');

            self::assertSame(1, CiDropPrivatePackages::main(['--root=' . $directory], [], $directory));
            self::assertSame(1, CiDropPrivatePackages::main(['--root=' . $directory], ['CI' => 'true'], $directory));
            self::assertSame($before, self::read($directory . '/composer.lock'));

            self::assertSame(0, CiDropPrivatePackages::main(['--root=' . $directory], ['CI' => 'true', 'GITHUB_ACTIONS' => 'true'], $directory));
            self::assertSame(self::read(self::FIXTURES . '/expected/composer.lock'), self::read($directory . '/composer.lock'));
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    /**
     * The Quality workflow drops the packages instead of reading a token, and
     * the Docker build never sees the script.
     */
    public function testWorkflowAndImageBuildWiring(): void
    {
        $workflow = self::read(self::root() . '/.github/workflows/quality.yml');
        // The name may appear in a comment; no step may read the secret or an env of that name.
        self::assertDoesNotMatchRegularExpression('/secrets\.IN2CODE|\$\{?IN2CODE_PACKAGIST_TOKEN|IN2CODE_PACKAGIST_TOKEN\s*:/', $workflow);
        self::assertStringNotContainsString('in2code.repo.packagist.com', $workflow);
        self::assertStringNotContainsString('composer config --global --auth', $workflow);

        $drop = strpos($workflow, 'php Build/Scripts/ci-drop-private-packages.php');
        $install = strpos($workflow, 'composer install');
        self::assertIsInt($drop, 'The Quality job must drop the in2code packages.');
        self::assertIsInt($install);
        self::assertLessThan($install, $drop, 'The drop must run before composer install.');
        self::assertStringNotContainsString('--force', substr($workflow, $drop, 80));

        self::assertStringNotContainsString('ci-drop-private-packages', self::read(self::root() . '/Dockerfile.coolify'));
        self::assertMatchesRegularExpression('#^Build/Scripts/ci-drop-private-packages\.php$#m', self::read(self::root() . '/.dockerignore'));
    }
}
