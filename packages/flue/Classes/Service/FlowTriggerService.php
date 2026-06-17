<?php

declare(strict_types=1);

namespace Webconsulting\Flue\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webconsulting\Flue\Domain\Model\FlueEvent;
use Webconsulting\Flue\Domain\Repository\FlowRepository;
use Webconsulting\Flue\Exception\ExecutionBlockedException;
use Webconsulting\Flue\Support\Typed;

/**
 * Orchestrates triggering one flow on the sidecar:
 *  guard -> resolve {uid} context -> export skills -> mint MCP PAT -> retrieve
 *  the LLM key from nr-vault -> POST to the sidecar -> persist the durable run.
 *
 * Soft-couples to typo3-mcp-server (PAT) and nr-vault (secret) via class_exists
 * guards, so flue installs without them (the flow simply runs key-less / token-less).
 */
final class FlowTriggerService
{
    public function __construct(
        private readonly FlueClientInterface $flueClient,
        private readonly ContextResolver $contextResolver,
        private readonly SkillExporter $skillExporter,
        private readonly RunStore $runStore,
        private readonly FlowRepository $flowRepository,
        private readonly EnvironmentGuard $environmentGuard,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{runUid: int, runKey: string, status: string}
     */
    public function trigger(int $flowUid, string $table, int $uid, int $workspaceId, string $instructions, int $beUser): array
    {
        $this->environmentGuard->assertExecutionAllowed();

        $flow = $this->flowRepository->findByUid($flowUid);
        if ($flow === null) {
            throw new ExecutionBlockedException('Flow ' . $flowUid . ' not found.', 1760001100);
        }

        $tokens = $this->contextResolver->resolveTokens($table, $uid, $workspaceId);
        $flowInstructions = $this->contextResolver->apply(Typed::string($flow['instructions'] ?? ''), $tokens);
        $runInstructions = $this->contextResolver->apply($instructions, $tokens);
        $combinedInstructions = trim($flowInstructions . "\n\n" . $runInstructions);

        $skillUids = $this->intList(Typed::string($flow['skills'] ?? ''));
        if ($skillUids !== []) {
            $this->skillExporter->export($skillUids, $this->skillsTargetDir());
        }
        $skillIdentifiers = $this->skillIdentifiers($skillUids);

        $mcpToken = $this->mintMcpToken($beUser);
        $apiKey = $this->retrieveApiKey();
        $runKey = bin2hex(random_bytes(16));

        $payload = [
            'pageUid' => $uid,
            'table' => $table,
            'workspace' => $workspaceId,
            'runKey' => $runKey,
            'instructions' => $combinedInstructions,
            'skills' => $skillIdentifiers,
            'mcpUrl' => $this->mcpUrl(),
            'mcpToken' => $mcpToken,
            'model' => Typed::string($flow['default_model'] ?? '') ?: $this->defaultModel(),
            'mcpTools' => $this->csvList(Typed::string($flow['mcp_tools'] ?? '')),
        ];

        $headers = [];
        if ($apiKey !== null && $apiKey !== '') {
            // Per request only; never persisted, never in the run row, never logged.
            $headers['X-Flue-Anthropic-Key'] = $apiKey;
        }

        $runUid = $this->runStore->create($flowUid, $runKey, $table, $uid, $workspaceId, $combinedInstructions, $payload, $beUser);

        $workflowName = Typed::string($flow['workflow_name'] ?? '') ?: 'page-report';
        $result = $this->flueClient->trigger($workflowName, $payload, $headers);
        if ($result['runId'] !== '') {
            $this->runStore->setFlueRunId($runUid, $result['runId']);
        }
        if ($result['status'] === 'failed') {
            $this->runStore->markFailed($runUid, 'Sidecar did not accept the flow (is the Flue sidecar running?).');
        }

        return ['runUid' => $runUid, 'runKey' => $runKey, 'status' => $result['status']];
    }

    /**
     * Drains the sidecar's event stream for a run into the durable store and
     * settles it. Blocking. An optional $tap receives each event (e.g. to also
     * echo SSE to a browser). Accumulates assistant message text as the output.
     *
     * @param callable(FlueEvent): void|null $tap
     */
    public function drainRun(int $runUid, ?callable $tap = null): void
    {
        $run = $this->runStore->load($runUid);
        if ($run === null || $run->flueRunId === '') {
            return;
        }
        $this->runStore->markRunning($runUid);

        $output = '';
        $this->flueClient->streamEvents($run->flueRunId, function (FlueEvent $event) use ($runUid, $tap, &$output): void {
            $this->runStore->appendEvent($runUid, $event);
            if ($tap !== null) {
                $tap($event);
            }
            $output .= $this->extractText($event);
            if (in_array($event->type, ['submission_settled', 'run_end'], true)) {
                $this->runStore->markSettled($runUid, $output);
            }
            if ($event->type === 'session.error') {
                $this->runStore->markFailed($runUid, Typed::string($event->data['message'] ?? 'Flue run errored.'));
            }
        });

        // If the stream ended without a terminal event, settle with what we have.
        $after = $this->runStore->load($runUid);
        if ($after !== null && !$after->status->isTerminal()) {
            $this->runStore->markSettled($runUid, $output);
        }
    }

    private function extractText(FlueEvent $event): string
    {
        if (!in_array($event->type, ['agent.message', 'message', 'text'], true)) {
            return '';
        }
        foreach (['text', 'content', 'delta', 'message'] as $key) {
            $value = $event->data[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
        return '';
    }

    /**
     * @param list<int> $skillUids
     * @return list<string>
     */
    private function skillIdentifiers(array $skillUids): array
    {
        if ($skillUids === [] || !$this->skillExporter->isAvailable()) {
            return [];
        }
        $qb = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)
            ->getQueryBuilderForTable('tx_skillflow_skill');
        $rows = $qb->select('identifier')->from('tx_skillflow_skill')
            ->where($qb->expr()->in('uid', $qb->createNamedParameter($skillUids, \TYPO3\CMS\Core\Database\Connection::PARAM_INT_ARRAY)))
            ->executeQuery()->fetchFirstColumn();

        return array_values(array_filter(array_map(static fn (mixed $v): string => Typed::string($v), $rows), static fn (string $s): bool => $s !== ''));
    }

    private function mintMcpToken(int $beUser): string
    {
        $serviceClass = 'Hn\\McpServer\\Service\\OAuthService';
        if ($beUser <= 0 || !class_exists($serviceClass)) {
            return '';
        }
        try {
            /** @var object $service */
            $service = GeneralUtility::makeInstance($serviceClass);
            if (method_exists($service, 'createDirectAccessToken')) {
                return Typed::string($service->createDirectAccessToken($beUser, 'flue-bridge'));
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Could not mint a typo3-mcp PAT', ['error' => $e->getMessage()]);
        }
        return '';
    }

    private function retrieveApiKey(): ?string
    {
        $vaultId = $this->config('apiKeyVaultId');
        $serviceClass = 'Netresearch\\NrVault\\Service\\VaultService';
        if ($vaultId === '' || !class_exists($serviceClass)) {
            return null;
        }
        try {
            /** @var object $service */
            $service = GeneralUtility::makeInstance($serviceClass);
            if (method_exists($service, 'retrieve')) {
                $secret = $service->retrieve($vaultId);
                return is_string($secret) ? $secret : null;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Could not retrieve the LLM key from nr-vault', ['error' => $e->getMessage()]);
        }
        return null;
    }

    private function skillsTargetDir(): string
    {
        // The DDEV shared volume the flue container reads at /app/skills.
        $shared = '/mnt/flue-skills';
        if (is_dir($shared) || @mkdir($shared, 0775, true) || is_dir($shared)) {
            return $shared;
        }
        $fallback = Environment::getVarPath() . '/flue-skills';
        GeneralUtility::mkdir_deep($fallback);
        return $fallback;
    }

    /**
     * @return list<int>
     */
    private function intList(string $csv): array
    {
        $out = [];
        foreach (GeneralUtility::trimExplode(',', $csv, true) as $part) {
            $int = (int)$part;
            if ($int > 0) {
                $out[] = $int;
            }
        }
        return $out;
    }

    /**
     * @return list<string>
     */
    private function csvList(string $csv): array
    {
        return array_values(GeneralUtility::trimExplode(',', $csv, true));
    }

    private function mcpUrl(): string
    {
        return $this->config('mcpUrl') ?: 'http://web/mcp';
    }

    private function defaultModel(): string
    {
        return $this->config('defaultModel') ?: 'anthropic/claude-sonnet-4-6';
    }

    private function config(string $key): string
    {
        try {
            return Typed::string($this->extensionConfiguration->get('flue', $key));
        } catch (\Throwable) {
            return '';
        }
    }
}
