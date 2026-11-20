<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\AiStack;

/**
 * The registered nr-llm tools and their stored on/off overrides.
 */
interface ToolStates
{
    /** @return list<ToolDescriptor> */
    public function tools(): array;

    /** @return array<string, bool> tool name => enabled */
    public function toolOverrides(): array;

    /** @return array<string, bool> group name => enabled */
    public function groupOverrides(): array;

    public function setTool(string $name, bool $enabled): void;

    public function setGroup(string $name, bool $enabled): void;
}
