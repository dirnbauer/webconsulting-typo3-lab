<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\AiStack;

/**
 * Values in config/system/settings.php, addressed by path
 * (EXTENSIONS/nr_mcp_agent/llmTaskUid).
 */
interface SettingStore
{
    /** The stored value, or null when the path does not exist. */
    public function get(string $path): ?string;

    public function set(string $path, string $value): void;
}
