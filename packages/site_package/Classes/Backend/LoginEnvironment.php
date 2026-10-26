<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Backend;

use TYPO3\CMS\Core\Core\Environment;

/**
 * Which installation a backend login belongs to (DDEV, Staging or Live), the
 * project it serves and who built the website.
 *
 * It reads the application context (TYPO3_CONTEXT: Development/DDEV from
 * .ddev/config.yaml, Production from Dockerfile.coolify and
 * docker-compose.coolify.yml) and DDEV's own IS_DDEV_PROJECT marker, so a DDEV
 * container is never shown as Live, whatever context it runs in. Contexts the
 * lab does not know are named as they are and are never passed off as one of
 * the three environments.
 *
 * ext_localconf.php sets SYS.sitename and the Core login settings from it; the
 * backend login layout (Resources/Private/TemplateOverrides/typo3/cms-backend/
 * Layouts/Login.fluid.html) renders the badge and the footnote through
 * LoginEnvironmentViewHelper.
 */
final readonly class LoginEnvironment
{
    public const string PROJECT = 'webconsulting TYPO3 Lab';
    public const string WEBSITE_BY = 'webconsulting.at';
    public const string WEBSITE_BY_URL = 'https://webconsulting.at/';

    public const string DDEV = 'ddev';
    public const string STAGING = 'staging';
    public const string LIVE = 'live';
    public const string DEVELOPMENT = 'development';
    public const string TESTING = 'testing';
    public const string OTHER = 'other';

    private function __construct(
        /** One of the constants above; the login layout picks the badge colour and label by it. */
        public string $key,
        /** English label for configuration values (SYS.sitename, loginFootnote); the layout translates by key. */
        public string $label,
    ) {}

    public static function current(): self
    {
        return self::fromContext((string)Environment::getContext(), getenv('IS_DDEV_PROJECT') === 'true');
    }

    public static function fromContext(string $context, bool $isDdevProject = false): self
    {
        $context = trim($context, " \t\n\r\0\x0B/");

        return match (true) {
            $isDdevProject, self::isWithin($context, 'Development/DDEV') => new self(self::DDEV, 'DDEV'),
            self::isWithin($context, 'Production/Staging') => new self(self::STAGING, 'Staging'),
            $context === 'Production', self::isWithin($context, 'Production/Live') => new self(self::LIVE, 'Live'),
            self::isWithin($context, 'Testing') => new self(self::TESTING, 'Testing'),
            self::isWithin($context, 'Development') => new self(self::DEVELOPMENT, 'Development'),
            default => new self(self::OTHER, $context === '' ? 'Unknown context' : str_replace('/', ' · ', $context)),
        };
    }

    /**
     * "webconsulting TYPO3 Lab · DDEV": the backend site name (browser title, top bar).
     */
    public function siteName(): string
    {
        return self::PROJECT . ' · ' . $this->label;
    }

    /**
     * "webconsulting TYPO3 Lab · DDEV · Website by webconsulting.at": Core's loginFootnote.
     */
    public function footnote(): string
    {
        return $this->siteName() . ' · Website by ' . self::WEBSITE_BY;
    }

    /**
     * @return array{key: string, label: string, project: string, websiteBy: string, websiteByUrl: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'project' => self::PROJECT,
            'websiteBy' => self::WEBSITE_BY,
            'websiteByUrl' => self::WEBSITE_BY_URL,
        ];
    }

    /**
     * The context itself or one of its sub-contexts: Production/Live/Server1 is within Production/Live.
     */
    private static function isWithin(string $context, string $parent): bool
    {
        return $context === $parent || str_starts_with($context, $parent . '/');
    }
}
