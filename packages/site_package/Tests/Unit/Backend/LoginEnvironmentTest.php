<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Backend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\SitePackage\Backend\LoginEnvironment;

/**
 * The application context names the environment on the backend login and in
 * the site name: DDEV, Staging or Live, and nothing else is passed off as one of them.
 */
final class LoginEnvironmentTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: bool, 2: string, 3: string}>
     */
    public static function contexts(): iterable
    {
        yield 'DDEV (.ddev/config.yaml)' => ['Development/DDEV', true, LoginEnvironment::DDEV, 'DDEV'];
        yield 'the DDEV context alone' => ['Development/DDEV', false, LoginEnvironment::DDEV, 'DDEV'];
        yield 'a DDEV container is never Live' => ['Production', true, LoginEnvironment::DDEV, 'DDEV'];
        yield 'Coolify (Production)' => ['Production', false, LoginEnvironment::LIVE, 'Live'];
        yield 'Production/Live' => ['Production/Live', false, LoginEnvironment::LIVE, 'Live'];
        yield 'a Live sub-context' => ['Production/Live/Server1', false, LoginEnvironment::LIVE, 'Live'];
        yield 'Production/Staging' => ['Production/Staging', false, LoginEnvironment::STAGING, 'Staging'];
        yield 'a Staging sub-context' => ['Production/Staging/Preview', false, LoginEnvironment::STAGING, 'Staging'];
        yield 'plain Development' => ['Development', false, LoginEnvironment::DEVELOPMENT, 'Development'];
        yield 'another Development sub-context' => ['Development/Docker', false, LoginEnvironment::DEVELOPMENT, 'Development'];
        yield 'Testing' => ['Testing/Functional', false, LoginEnvironment::TESTING, 'Testing'];
        yield 'an unknown Production sub-context is named, not called Live' => ['Production/Preview', false, LoginEnvironment::OTHER, 'Production · Preview'];
        yield 'a context that only starts like Live' => ['Production/Lively', false, LoginEnvironment::OTHER, 'Production · Lively'];
        yield 'no context' => ['', false, LoginEnvironment::OTHER, 'Unknown context'];
    }

    #[Test]
    #[DataProvider('contexts')]
    public function mapsTheApplicationContext(string $context, bool $isDdevProject, string $key, string $label): void
    {
        $environment = LoginEnvironment::fromContext($context, $isDdevProject);

        self::assertSame($key, $environment->key);
        self::assertSame($label, $environment->label);
    }

    #[Test]
    public function namesTheLiveSiteAndItsMaker(): void
    {
        $live = LoginEnvironment::fromContext('Production');

        self::assertSame('webconsulting TYPO3 Lab · Live', $live->siteName());
        self::assertSame('webconsulting TYPO3 Lab · Live · Website by webconsulting.at', $live->footnote());
        self::assertSame(
            [
                'key' => 'live',
                'label' => 'Live',
                'project' => 'webconsulting TYPO3 Lab',
                'websiteBy' => 'webconsulting.at',
                'websiteByUrl' => 'https://webconsulting.at/',
            ],
            $live->toArray()
        );
    }

    #[Test]
    public function namesTheDdevSite(): void
    {
        self::assertSame('webconsulting TYPO3 Lab · DDEV', LoginEnvironment::fromContext('Development/DDEV', true)->siteName());
    }
}
