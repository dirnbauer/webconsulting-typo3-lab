<?php

declare(strict_types=1);

namespace Webconsulting\Flue\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use Webconsulting\Flue\Exception\ExecutionBlockedException;

/**
 * Gates flow execution to local DDEV/Development installs (matching skillflow's
 * posture), unless `requireLocalEnvironment` is disabled in the extension config.
 */
final class EnvironmentGuard
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    public function assertExecutionAllowed(): void
    {
        $reason = $this->getBlockReason();
        if ($reason !== null) {
            throw new ExecutionBlockedException($reason, 1760001000);
        }
    }

    public function getBlockReason(): ?string
    {
        if (!$this->requireLocalEnvironment()) {
            return null;
        }
        if (!Environment::getContext()->isDevelopment()) {
            return 'Flows run only in TYPO3 "Development" application context.';
        }
        if (getenv('IS_DDEV_PROJECT') !== 'true') {
            return 'Flows run only inside a local DDEV project.';
        }
        return null;
    }

    private function requireLocalEnvironment(): bool
    {
        try {
            $value = $this->extensionConfiguration->get('flue', 'requireLocalEnvironment');
        } catch (\Throwable) {
            return true;
        }
        return (bool)$value;
    }
}
