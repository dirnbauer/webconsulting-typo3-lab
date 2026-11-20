<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Deployment;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The public snapshot (/downloads/) is scanned for credentials before it stays
 * online (Build/Scripts/lib/scan-snapshot.php). Since Powermail 14 comes from
 * in2code's Early Access Packagist, its token and Composer auth blocks are
 * credentials of their own. Every sample below is made up.
 */
final class SnapshotSecretPatternsTest extends TestCase
{
    /** @var array<string, string>|null */
    private static ?array $patterns = null;

    /**
     * Read once: the script declares functions, a second include would
     * declare them again.
     *
     * @return array<string, string>
     */
    private static function patterns(): array
    {
        if (self::$patterns === null) {
            if (!defined('SCAN_SNAPSHOT_PATTERNS_ONLY')) {
                define('SCAN_SNAPSHOT_PATTERNS_ONLY', true);
            }
            $patterns = require dirname(__DIR__, 3) . '/Build/Scripts/lib/scan-snapshot.php';
            self::assertIsArray($patterns);
            self::$patterns = $patterns;
        }

        return self::$patterns;
    }

    /** @return iterable<string, array{string, string}> */
    public static function leaks(): iterable
    {
        $token = str_repeat('0123456789abcdef', 4);
        $token = substr($token, 0, 60);
        yield 'bare token in a text field' => ['packagist_token', "('note', '" . $token . "')"];
        yield 'token at the end of a line' => ['packagist_token', 'token=' . $token];
        yield 'COMPOSER_AUTH in a dump (escaped quotes)' => ['composer_auth', '{\\"http-basic\\":{\\"in2code.repo.packagist.com\\":{}}}'];
        yield 'auth.json content' => ['composer_auth', "{\n  \"http-basic\": {\n    \"in2code.repo.packagist.com\": {}"];
        yield 'credentials in a repository URL' => ['composer_auth', 'https://token:s3cr3tvalue@in2code.repo.packagist.com/webconsulting/'];
    }

    #[DataProvider('leaks')]
    public function testTheCredentialIsFound(string $pattern, string $text): void
    {
        self::assertSame(1, preg_match(self::patterns()[$pattern], $text));
    }

    /** @return iterable<string, array{string}> */
    public static function harmless(): iterable
    {
        yield 'MD5 (sys_refindex, cache keys)' => [md5('lab')];
        yield 'SHA-1 (sys_file)' => [sha1('lab')];
        yield 'SHA-256 (checksums, nr-vault)' => [hash('sha256', 'lab')];
        yield 'the dist URL composer.lock records' => ['https://in2code.repo.packagist.com/webconsulting/dists/in2code/powermail/14.2.0.0/b3784319874ef6b410af57098b2c6dab16b12901.zip'];
        yield 'a page about Composer' => ['Composer reads credentials from auth.json (http-basic, bearer).'];
    }

    #[DataProvider('harmless')]
    public function testOrdinaryValuesAreNotCredentials(string $text): void
    {
        foreach (['packagist_token', 'composer_auth'] as $pattern) {
            self::assertSame(0, preg_match(self::patterns()[$pattern], $text), $pattern);
        }
    }
}
