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
    // Long enough for a capped subagent fan-out (tree-audit) to settle; the run row
    // is the source of truth, so a too-short poll would falsely mark a live run failed.
    private const DRAIN_TIMEOUT_SECONDS = 600;
    private const DRAIN_INTERVAL_US = 2_000_000;

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
     * Polls the sidecar's plain-JSON run record (GET /runs/<id>?meta) until the
     * run settles, then mirrors the outcome into the durable store. Blocking.
     * An optional $tap receives status + terminal events (e.g. to echo SSE to a
     * browser). Live Durable-Streams tailing is a later upgrade; the run record
     * is the source of truth either way.
     *
     * @param callable(FlueEvent): void|null $tap
     */
    public function drainRun(int $runUid, ?callable $tap = null): void
    {
        $run = $this->runStore->load($runUid);
        if ($run === null || $run->flueRunId === '') {
            return;
        }
        if ($run->status->isTerminal()) {
            return;
        }
        $this->runStore->markRunning($runUid);

        $deadline = time() + self::DRAIN_TIMEOUT_SECONDS;
        $lastStatus = '';
        $seq = 0;
        while (time() < $deadline) {
            $record = $this->flueClient->getRunRecord($run->flueRunId);
            if ($record === []) {
                usleep(self::DRAIN_INTERVAL_US);
                continue;
            }

            $status = Typed::string($record['status'] ?? '');
            if ($tap !== null && $status !== '' && $status !== $lastStatus) {
                $progress = new FlueEvent($seq++, time(), 'status', ['status' => $status]);
                $this->runStore->appendEvent($runUid, $progress);
                $tap($progress);
                $lastStatus = $status;
            }

            $isError = ($record['isError'] ?? null) === true;
            $terminal = $isError
                || Typed::string($record['endedAt'] ?? '') !== ''
                || array_key_exists('result', $record);
            if (!$terminal) {
                usleep(self::DRAIN_INTERVAL_US);
                continue;
            }

            if ($isError || isset($record['error'])) {
                $errorArr = is_array($record['error'] ?? null) ? $record['error'] : [];
                $message = Typed::string($errorArr['message'] ?? '') ?: 'Flue run errored.';
                $event = new FlueEvent($seq++, time(), 'session.error', ['message' => $message]);
                $this->runStore->appendEvent($runUid, $event);
                if ($tap !== null) {
                    $tap($event);
                }
                $this->runStore->markFailed($runUid, $message);
            } else {
                $result = $record['result'] ?? null;
                $output = $this->extractResult($result);
                [$verdict, $resultJson, $usageJson] = $this->extractMeta($result);
                $event = new FlueEvent($seq++, time(), 'submission_settled', ['text' => $output]);
                $this->runStore->appendEvent($runUid, $event);
                if ($tap !== null) {
                    $tap($event);
                }
                $this->runStore->markSettled($runUid, $output, $usageJson, $resultJson, $verdict);
            }

            return;
        }

        $this->runStore->markFailed($runUid, 'Timed out waiting for the Flue run to settle.');
    }

    /**
     * The QA workflows return `{ report, qa, usage, pageUid }`; fall back to a JSON dump.
     */
    private function extractResult(mixed $result): string
    {
        if (is_array($result)) {
            $report = $result['report'] ?? null;
            if (is_string($report) && $report !== '') {
                return $report;
            }
            $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return is_string($json) ? $json : '';
        }

        return is_string($result) ? $result : '';
    }

    /**
     * Pull the structured verdict + result + usage out of a workflow result so the
     * run row carries a queryable verdict and the full QA JSON.
     *
     * @return array{0: string, 1: string, 2: string} [verdict, resultJson, usageJson]
     */
    private function extractMeta(mixed $result): array
    {
        if (!is_array($result)) {
            return ['', '', ''];
        }
        $qa = is_array($result['qa'] ?? null) ? $result['qa'] : null;
        $verdict = $qa !== null ? Typed::string($qa['verdict'] ?? '') : '';
        $resultJson = $qa !== null
            ? Typed::string(json_encode($qa, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '')
            : '';
        $usage = $result['usage'] ?? null;
        $usageJson = is_array($usage)
            ? Typed::string(json_encode($usage, JSON_UNESCAPED_SLASHES) ?: '')
            : '';

        return [$verdict, $resultJson, $usageJson];
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
