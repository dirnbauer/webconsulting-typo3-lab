<?php

declare(strict_types=1);

namespace Webconsulting\Lab\Tests\Unit\Deployment;

use Closure;
use Netresearch\NrVault\Configuration\ExtensionConfigurationInterface;
use Netresearch\NrVault\Crypto\FileMasterKeyProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class VaultFileProviderTest extends TestCase
{
    private string $root;
    private string $fixture;
    private string $key;
    private Closure $configure;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
        $this->fixture = $this->root . '/var/vault-file-test-' . bin2hex(random_bytes(6));
        mkdir($this->fixture . '/public', 0700, true);
        mkdir($this->fixture . '/custody', 0700);
        $this->fixture = (string)realpath($this->fixture);
        $this->key = $this->fixture . '/custody/master-key';
        // Fixed, disposable test material only; never use a real application key.
        file_put_contents($this->key, base64_encode(str_repeat('B', 32)) . "\n");
        chmod($this->key, 0400);
        $this->configure = require $this->root . '/config/system/vault-file-provider.php';
        FileMasterKeyProvider::clearCachedKey();
    }

    protected function tearDown(): void
    {
        FileMasterKeyProvider::clearCachedKey();
        $remove = static function (string $path) use (&$remove): void {
            if (is_link($path) || !is_dir($path)) {
                @chmod($path, 0600);
                unlink($path);
                return;
            }
            foreach (new \FilesystemIterator($path) as $entry) {
                $remove($entry->getPathname());
            }
            rmdir($path);
        };
        $remove($this->fixture);
    }

    public function testOptOutPreservesAllConfigurationAndNeedsNoFilesystemPath(): void
    {
        $before = ['SYS' => ['encryptionKey' => 'synthetic-signing-key'], 'EXTENSIONS' => ['nr_vault' => ['masterKeyProvider' => 'typo3']]];
        self::assertSame($before, ($this->configure)($before, '', '/nonexistent/public'));
    }

    public function testExplicitFileChangesOnlyNativeProviderAndSource(): void
    {
        $before = ['SYS' => ['encryptionKey' => 'synthetic-signing-key'], 'EXTENSIONS' => ['nr_vault' => ['auditHmacEpoch' => 3, 'masterKeyProvider' => 'typo3']]];
        $expected = $before;
        $expected['EXTENSIONS']['nr_vault']['masterKeyProvider'] = 'file';
        $expected['EXTENSIONS']['nr_vault']['masterKeySource'] = $this->key;
        self::assertSame($expected, ($this->configure)($before, $this->key, $this->fixture . '/public', $this->fixture . '/custody'));
    }

    public function testNativeProviderReadsExactSyntheticBytesIndependentlyOfSigningKey(): void
    {
        foreach (['old-synthetic-signing-key', 'new-synthetic-signing-key'] as $signingKey) {
            $configured = ($this->configure)(['SYS' => ['encryptionKey' => $signingKey]], $this->key, $this->fixture . '/public', $this->fixture . '/custody');
            $config = $this->createStub(ExtensionConfigurationInterface::class);
            $config->method('getMasterKeySource')->willReturn($configured['EXTENSIONS']['nr_vault']['masterKeySource']);
            $provider = new FileMasterKeyProvider($config);
            self::assertSame(str_repeat('B', 32), $provider->getMasterKey());
            self::assertSame($signingKey, $configured['SYS']['encryptionKey']);
            unset($provider);
        }
    }

    #[DataProvider('validFormats')]
    public function testCanonicalBase64AllowsOnlyOptionalSingleFinalNewline(string $ending): void
    {
        chmod($this->key, 0600);
        file_put_contents($this->key, base64_encode(str_repeat('B', 32)) . $ending);
        chmod($this->key, 0400);
        self::assertSame('file', ($this->configure)([], $this->key, $this->fixture . '/public', $this->fixture . '/custody')['EXTENSIONS']['nr_vault']['masterKeyProvider']);
    }

    public static function validFormats(): array
    {
        return [[''], ["\n"], ["\r\n"]];
    }

    public function testMissingFileFailsWithoutGeneratingOrSelectingAnotherKey(): void
    {
        $missing = $this->fixture . '/missing.key';
        $files = scandir($this->fixture);
        try {
            ($this->configure)([], $missing, $this->fixture . '/public', $this->fixture . '/custody');
            self::fail('An explicitly missing key must fail closed.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('missing', $error->getMessage());
        }
        self::assertSame($files, scandir($this->fixture));
        self::assertFileDoesNotExist($missing);
    }

    public function testDisappearingFileFailsOnNextBootstrapInsteadOfNativeAutoFallback(): void
    {
        ($this->configure)([], $this->key, $this->fixture . '/public', $this->fixture . '/custody');
        unlink($this->key);
        $this->expectException(RuntimeException::class);
        ($this->configure)([], $this->key, $this->fixture . '/public', $this->fixture . '/custody');
    }

    #[DataProvider('invalidFormats')]
    public function testInvalidFormatFailsClosed(string $contents): void
    {
        chmod($this->key, 0600);
        file_put_contents($this->key, $contents);
        chmod($this->key, 0400);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('canonical base64');
        ($this->configure)([], $this->key, $this->fixture . '/public', $this->fixture . '/custody');
    }

    public static function invalidFormats(): array
    {
        return [
            'empty' => [''],
            'raw binary length32' => [str_repeat('B', 32)],
            '31 bytes' => [base64_encode(str_repeat('B', 31))],
            '33 bytes' => [base64_encode(str_repeat('B', 33))],
            'invalid alphabet' => [str_repeat('!', 44)],
            'missing canonical padding' => [rtrim(base64_encode(str_repeat('B', 32)), '=')],
            'embedded whitespace' => [substr_replace(base64_encode(str_repeat('B', 32)), ' ', 4, 0)],
            'leading NUL' => ["\0" . base64_encode(str_repeat('B', 32))],
            'trailing NUL' => [base64_encode(str_repeat('B', 32)) . "\0"],
            'leading space' => [' ' . base64_encode(str_repeat('B', 32))],
            'trailing space' => [base64_encode(str_repeat('B', 32)) . ' '],
            'leading tab' => ["\t" . base64_encode(str_repeat('B', 32))],
            'trailing tab' => [base64_encode(str_repeat('B', 32)) . "\t"],
            'two newlines' => [base64_encode(str_repeat('B', 32)) . "\n\n"],
            'oversized file' => [base64_encode(str_repeat('B', 32)) . str_repeat("\n", 200)],
        ];
    }

    #[DataProvider('unsafeModes')]
    public function testUnreadableOrUnsafePermissionsFailClosed(int $mode): void
    {
        chmod($this->key, $mode);
        $this->expectException(RuntimeException::class);
        ($this->configure)([], $this->key, $this->fixture . '/public', $this->fixture . '/custody');
    }

    public static function unsafeModes(): array
    {
        return [[0000], [0600], [0440], [0444], [0640], [0401], [0500]];
    }

    #[DataProvider('unsafePaths')]
    public function testUnsafePathSyntaxFailsClosed(string $file): void
    {
        $this->expectException(RuntimeException::class);
        ($this->configure)([], $file, $this->fixture . '/public', $this->fixture . '/custody');
    }

    public static function unsafePaths(): array
    {
        return [['relative.key'], ['file:///tmp/master.key'], ['php://memory'], [' /tmp/master.key'], ["/tmp/master\n.key"], ["/tmp/master\0.key"]];
    }

    public function testWebAccessibleKeyFailsClosed(): void
    {
        $inside = $this->fixture . '/public/master.key';
        rename($this->key, $inside);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outside the web root');
        ($this->configure)([], $inside, $this->fixture . '/public', $this->fixture . '/custody');
    }

    public function testSymlinkAndParentTraversalAliasesFailClosed(): void
    {
        symlink($this->key, $this->fixture . '/alias.key');
        foreach ([$this->fixture . '/alias.key', $this->fixture . '/custody/../custody/master-key'] as $file) {
            try {
                ($this->configure)([], $file, $this->fixture . '/public', $this->fixture . '/custody');
                self::fail('Path aliases must fail.');
            } catch (RuntimeException $error) {
                self::assertStringContainsString('path aliases', $error->getMessage());
            }
        }
    }

    public function testValidKeyOutsideDedicatedCustodyIsRejectedBeforeItCanBeSelected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dedicated read-only custody volume');
        ($this->configure)([], $this->key, $this->fixture . '/public');
    }

    public function testDirectoryIsNotAKeyFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('regular file');
        ($this->configure)([], $this->fixture, $this->fixture . '/public', $this->fixture . '/custody');
    }

    public function testStandaloneEntrypointValidatorOptsOutAndRejectsUnapprovedCustodyPaths(): void
    {
        foreach ([['', 0], [$this->key, 1], [$this->fixture . '/missing.key', 1]] as [$file, $expected]) {
            $process = proc_open([PHP_BINARY, $this->root . '/docker/coolify/validate-vault-master-key.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root, ['TYPO3_VAULT_MASTER_KEY_FILE' => $file]);
            self::assertIsResource($process);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame($expected, proc_close($process), $error);
            self::assertSame('', $output, 'The validator must never emit key material.');
        }
    }

    public function testEntrypointRunsValidatorAsRuntimeUserBeforeAnyStartupWrites(): void
    {
        $entrypoint = file_get_contents($this->root . '/docker/coolify/entrypoint.sh');
        self::assertStringContainsString('su -s /bin/sh -c \'exec php /var/www/html/docker/coolify/validate-vault-master-key.php\' www-data', $entrypoint);
        self::assertLessThan(strpos($entrypoint, 'htpasswd -bcB'), strpos($entrypoint, 'validate-vault-master-key.php'));
        self::assertLessThan(strpos($entrypoint, 'validate-vault-master-key.php'), strpos($entrypoint, '[ ! -s /run/typo3-secrets/encryption-key ]'));
        self::assertStringContainsString('existing TYPO3 signing key file is required before enabling Vault file custody', $entrypoint);
        self::assertStringNotContainsString('/run/typo3-vault-secrets', $entrypoint, 'The entrypoint must not create/chown/write the read-only custody mount.');
    }

    public function testComposeKeepsCustodyReadOnlyAndOptInEmpty(): void
    {
        $compose = Yaml::parseFile($this->root . '/docker-compose.coolify.yml');
        self::assertSame('${TYPO3_VAULT_MASTER_KEY_FILE:-}', $compose['services']['web']['environment']['TYPO3_VAULT_MASTER_KEY_FILE']);
        self::assertContains('typo3_vault_keys:/run/typo3-vault-secrets:ro', $compose['services']['web']['volumes']);
        self::assertContains('typo3_secrets:/run/typo3-secrets', $compose['services']['web']['volumes']);
        self::assertArrayHasKey('typo3_vault_keys', $compose['volumes']);
    }
}
