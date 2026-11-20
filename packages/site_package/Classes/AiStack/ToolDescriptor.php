<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\AiStack;

/**
 * What the tool policy needs to know about one registered nr-llm tool. Built
 * from nr-llm's own registry, so the policy itself stays free of nr-llm
 * classes and can be tested with plain values.
 */
final readonly class ToolDescriptor
{
    public function __construct(
        public string $name,
        public string $group,
        public bool $enabledByDefault,
        /** nr-llm stops the run for a person's approval before every call. */
        public bool $requiresApproval,
        /** Imported from an MCP server: judged by the server row, not here. */
        public bool $remote = false,
    ) {}
}
