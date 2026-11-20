<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\AiStack;

use Netresearch\NrLlm\Service\Tool\RemoteToolInterface;
use Netresearch\NrLlm\Service\Tool\ToolApprovalRule;
use Netresearch\NrLlm\Service\Tool\ToolGroupStateRepository;
use Netresearch\NrLlm\Service\Tool\ToolRegistry;
use Netresearch\NrLlm\Service\Tool\ToolStateRepository;

/**
 * nr-llm's own registry and state repositories: the same ones its AI > Tools
 * module uses for its switches.
 *
 * Whether a tool waits for approval is asked of nr-llm's ToolApprovalRule, the
 * rule its agent loop applies before every call. It is marked internal; it is
 * still the only statement of that rule, and copying it here would let the two
 * drift apart.
 */
final readonly class NrLlmToolStates implements ToolStates
{
    public function __construct(
        private ToolRegistry $registry,
        private ToolStateRepository $toolStates,
        private ToolGroupStateRepository $groupStates,
    ) {}

    public function tools(): array
    {
        $tools = [];
        foreach ($this->registry->names() as $name) {
            $tool = $this->registry->get($name);
            if ($tool === null) {
                continue;
            }
            $tools[] = new ToolDescriptor(
                $name,
                $tool->getGroup(),
                $tool->isEnabledByDefault(),
                ToolApprovalRule::requiresApproval($tool),
                $tool instanceof RemoteToolInterface,
            );
        }

        return $tools;
    }

    public function toolOverrides(): array
    {
        return $this->toolStates->overrides();
    }

    public function groupOverrides(): array
    {
        return $this->groupStates->overrides();
    }

    public function setTool(string $name, bool $enabled): void
    {
        $this->toolStates->setEnabled($name, $enabled);
    }

    public function setGroup(string $name, bool $enabled): void
    {
        $this->groupStates->setEnabled($name, $enabled);
    }
}
