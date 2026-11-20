<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\AiStack;

use TYPO3\CMS\Core\Configuration\ConfigurationManager;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\Exception\MissingArrayPathException;

/**
 * TYPO3's own settings API: the file the backend's Settings > Extension
 * Configuration writes. Only the one path is written, so values that
 * ext_localconf.php or additional.php set at runtime never land in the file.
 * The running process sees the new value at once.
 */
final readonly class SystemSettingStore implements SettingStore
{
    public function __construct(private ConfigurationManager $configurationManager) {}

    public function get(string $path): ?string
    {
        try {
            $value = $this->configurationManager->getLocalConfigurationValueByPath($path);
        } catch (MissingArrayPathException) {
            return null;
        }

        return is_scalar($value) ? (string)$value : null;
    }

    public function set(string $path, string $value): void
    {
        if (!$this->configurationManager->setLocalConfigurationValueByPath($path, $value)) {
            throw new \RuntimeException(sprintf('TYPO3 could not write %s to its settings file.', $path), 1795600003);
        }
        $configuration = is_array($GLOBALS['TYPO3_CONF_VARS'] ?? null) ? $GLOBALS['TYPO3_CONF_VARS'] : [];
        $GLOBALS['TYPO3_CONF_VARS'] = ArrayUtility::setValueByPath($configuration, $path, $value);
    }
}
