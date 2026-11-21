<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Deployment;

use PHPUnit\Framework\TestCase;
use Webconsulting\Lab\Build\PowermailCheck;

/**
 * The logic of `ddev check-powermail` (Build/Scripts/check-powermail.php):
 * installed vs. locked Powermail, the reverse application of the lab patches,
 * and the scan that proves the in2code token stayed out of every file.
 */
final class PowermailCheckTest extends TestCase
{
    private string $root = '';

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/Build/Scripts/check-powermail.php';
    }

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/powermail-check-test-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0o777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function package(string $name, array $overrides = []): array
    {
        return array_merge([
            'name' => $name,
            'version' => '14.2.0',
            'dist' => [
                'type' => 'zip',
                'url' => 'https://in2code.repo.packagist.com/webconsulting/dists/' . $name . '/14.2.0.0/abc.zip',
                'reference' => 'abc',
            ],
        ], $overrides);
    }

    /** @param array<string, mixed> ...$packages */
    private static function lock(array ...$packages): string
    {
        return (string)json_encode(['packages' => $packages, 'packages-dev' => []]);
    }

    public function testMatchingInstallHasNoProblems(): void
    {
        $lock = self::lock(self::package('in2code/powermail'), self::package('in2code/powermail_cond'));
        $installed = (string)json_encode(['packages' => [
            self::package('in2code/powermail', ['installation-source' => 'dist']),
            self::package('in2code/powermail_cond', ['installation-source' => 'dist']),
        ]]);

        self::assertSame([], PowermailCheck::installedProblems($lock, $installed));
    }

    public function testAnotherVersionOrReferenceIsReported(): void
    {
        $lock = self::lock(self::package('in2code/powermail'), self::package('in2code/powermail_cond'));
        $installed = (string)json_encode(['packages' => [
            self::package('in2code/powermail', ['version' => '14.1.0']),
            self::package('in2code/powermail_cond', ['dist' => ['type' => 'zip', 'url' => 'x', 'reference' => 'def']]),
        ]]);

        $problems = implode("\n", PowermailCheck::installedProblems($lock, $installed));
        self::assertStringContainsString('in2code/powermail: installed 14.1.0, composer.lock says 14.2.0', $problems);
        self::assertStringContainsString('in2code/powermail_cond: installed reference def, composer.lock says abc', $problems);
    }

    public function testAMissingPackageAndASourceInstallAreReported(): void
    {
        $lock = self::lock(self::package('in2code/powermail'), self::package('in2code/powermail_cond'));
        $installed = (string)json_encode(['packages' => [
            self::package('in2code/powermail', ['installation-source' => 'source']),
        ]]);

        $problems = implode("\n", PowermailCheck::installedProblems($lock, $installed));
        self::assertStringContainsString('installed from source', $problems);
        self::assertStringContainsString('in2code/powermail_cond 14.2.0 is locked but not installed', $problems);
    }

    public function testCredentialsInALockUrlAreReported(): void
    {
        $lock = self::lock(
            self::package('in2code/powermail', ['dist' => ['type' => 'zip', 'url' => 'https://token:secret@in2code.repo.packagist.com/x.zip', 'reference' => 'abc']]),
            self::package('in2code/powermail_cond'),
        );
        $installed = (string)json_encode(['packages' => [self::package('in2code/powermail'), self::package('in2code/powermail_cond')]]);

        self::assertStringContainsString('carries credentials', implode("\n", PowermailCheck::installedProblems($lock, $installed)));
        self::assertTrue(PowermailCheck::urlHasCredentials('https://example.org/x.zip?access_token=1'));
        self::assertFalse(PowermailCheck::urlHasCredentials('https://in2code.repo.packagist.com/webconsulting/dists/in2code/powermail/14.2.0.0/b37.zip'));
    }

    public function testTheRepositoryLockIsCredentialFree(): void
    {
        $lock = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($lock);
        foreach ([...$lock['packages'], ...$lock['packages-dev']] as $package) {
            foreach (['dist', 'source'] as $kind) {
                $url = $package[$kind]['url'] ?? '';
                self::assertFalse($url !== '' && PowermailCheck::urlHasCredentials($url), $package['name'] . ' ' . $kind);
            }
        }
    }

    public function testInstallPathFollowsInstalledJson(): void
    {
        $installed = (string)json_encode(['packages' => [['name' => 'in2code/powermail', 'install-path' => '../in2code/powermail']]]);

        self::assertSame('/project/vendor/in2code/powermail', PowermailCheck::installPath('/project', $installed, 'in2code/powermail'));
        self::assertSame('/project/vendor/in2code/powermail_cond', PowermailCheck::installPath('/project', $installed, 'in2code/powermail_cond'));
    }

    public function testJevSourceIsReadFromTheLock(): void
    {
        $lock = self::lock(['name' => 'webconsulting/webcon-jev', 'source' => ['type' => 'git', 'url' => 'https://github.com/dirnbauer/typo3-webcon-jev.git', 'reference' => '0d220f0']]);

        self::assertSame(['url' => 'https://github.com/dirnbauer/typo3-webcon-jev.git', 'reference' => '0d220f0'], PowermailCheck::jevSource($lock));
        self::assertNull(PowermailCheck::jevSource(self::lock()));
    }

    /**
     * A tiny package with two patches that touch the same file: applied in
     * order they pass, a missing one is reported, and a patch whose content
     * differs from the lock's sha256 is reported before it is tried.
     */
    public function testPatchesAreProvenByReverseApplication(): void
    {
        if (!is_executable('/usr/bin/patch') && trim((string)shell_exec('command -v patch')) === '') {
            self::markTestSkipped('GNU patch is not installed.');
        }
        $package = $this->root . '/vendor/in2code/powermail';
        mkdir($package . '/Classes', 0o777, true);
        mkdir($this->root . '/vendor/composer', 0o777, true);
        mkdir($this->root . '/patches');
        file_put_contents($this->root . '/vendor/composer/installed.json', (string)json_encode(['packages' => [['name' => 'in2code/powermail', 'install-path' => '../in2code/powermail']]]));

        $first = "--- a/Classes/A.php\n+++ b/Classes/A.php\n@@ -1,3 +1,3 @@\n one\n-two\n+TWO\n three\n";
        $second = "--- a/Classes/A.php\n+++ b/Classes/A.php\n@@ -1,3 +1,3 @@\n one\n TWO\n-three\n+THREE\n";
        file_put_contents($this->root . '/patches/first.patch', $first);
        file_put_contents($this->root . '/patches/second.patch', $second);
        $lock = static fn(string $secondHash): string => (string)json_encode(['patches' => ['in2code/powermail' => [
            ['package' => 'in2code/powermail', 'url' => 'patches/first.patch', 'sha256' => hash('sha256', $first), 'depth' => 1],
            ['package' => 'in2code/powermail', 'url' => 'patches/second.patch', 'sha256' => $secondHash, 'depth' => 1],
        ]]]);
        file_put_contents($this->root . '/patches.lock.json', $lock(hash('sha256', $second)));

        // Both applied: the second patch only applies on top of the first.
        file_put_contents($package . '/Classes/A.php', "one\nTWO\nTHREE\n");
        $result = PowermailCheck::patchProblems($this->root);
        self::assertSame([], $result['problems']);
        self::assertSame(['in2code/powermail: patches/first.patch', 'in2code/powermail: patches/second.patch'], $result['applied']);
        self::assertSame("one\nTWO\nTHREE\n", file_get_contents($package . '/Classes/A.php'), 'The installed file stays untouched.');

        // The second one is missing.
        file_put_contents($package . '/Classes/A.php', "one\nTWO\nthree\n");
        $result = PowermailCheck::patchProblems($this->root);
        self::assertCount(1, $result['problems']);
        self::assertStringContainsString('patches/second.patch is NOT applied', $result['problems'][0]);

        // The patch file differs from what patches.lock.json recorded.
        file_put_contents($this->root . '/patches.lock.json', $lock(str_repeat('0', 64)));
        $result = PowermailCheck::patchProblems($this->root);
        self::assertStringContainsString('patches.lock.json says 000', implode("\n", $result['problems']));
    }

    public function testTheTokenScanFindsTheSecretAcrossChunkBordersAndSkipsSymlinks(): void
    {
        $secret = 'aB3dE5gH7jK9mN1pQ3sT5vX7zA9cE1gI3kM5oQ7sU9wY1aC3eG5iK7mO9qS1uW3y';
        mkdir($this->root . '/var/phpstan/cache', 0o777, true);
        mkdir($this->root . '/node_modules');
        mkdir($this->root . '/elsewhere');
        file_put_contents($this->root . '/var/phpstan/cache/Container.php', '<?php $env = ' . var_export(['COMPOSER_AUTH' => $secret], true) . ';');
        // The secret straddles the 1 MiB read chunk.
        file_put_contents($this->root . '/var/big.log', str_repeat('x', (1 << 20) - 10) . $secret . "\n");
        file_put_contents($this->root . '/var/clean.log', 'nothing here');
        file_put_contents($this->root . '/node_modules/skipped.js', $secret);
        file_put_contents($this->root . '/elsewhere/outside.txt', $secret);
        symlink($this->root . '/elsewhere', $this->root . '/var/link');

        self::assertSame(
            [$this->root . '/var/big.log', $this->root . '/var/phpstan/cache/Container.php'],
            PowermailCheck::filesContaining($secret, [$this->root . '/var', $this->root . '/node_modules/../var/clean.log']),
        );
        self::assertSame([], PowermailCheck::filesContaining($secret, [$this->root . '/does-not-exist']));
    }

    public function testTheTokenScanRefusesATooShortSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PowermailCheck::filesContaining('', [$this->root]);
    }
}
