<?php

declare(strict_types=1);

namespace Webconsulting\Flue\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;
use Webconsulting\Flue\Domain\Model\FlueEvent;
use Webconsulting\Flue\Support\Typed;

/**
 * HTTP client for the real @flue/runtime sidecar. Verified routes (1.0.0-beta.1):
 *   POST <base>/workflows/<name>   -> 202 { runId, streamUrl, offset } (async admission)
 *   GET  <base>/runs/<runId>?meta  -> plain-JSON run record { status, result, error, endedAt }
 *   GET  <base>/runs/<runId>       -> Durable-Streams event stream (live; not consumed yet)
 *
 * A workflow `page-report` admits async; FlowTriggerService::drainRun() polls the
 * run-record view to settlement (Durable-Streams live tailing is a later upgrade).
 * Uses TYPO3's RequestFactory (Guzzle) like skillflow's AnthropicApiRunner, with
 * http_errors disabled and defensive status handling.
 */
final class FlueClient implements FlueClientInterface
{
    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function trigger(string $workflowName, array $payload, array $headers = []): array
    {
        $uri = $this->baseUrl() . '/workflows/' . rawurlencode($workflowName);
        return $this->postJson($uri, $payload, $headers);
    }

    public function streamEvents(string $runId, callable $onEvent, array $headers = []): void
    {
        $uri = $this->baseUrl() . '/workflows/runs/' . rawurlencode($runId) . '/events';
        try {
            $response = $this->requestFactory->request($uri, 'GET', [
                'headers' => ['Accept' => 'text/event-stream'] + $headers,
                'stream' => true,
                'http_errors' => false,
                'timeout' => 0,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Flue stream connection failed', ['uri' => $uri, 'error' => $e->getMessage()]);
            return;
        }

        $body = $response->getBody();
        $buffer = '';
        $seq = 0;
        while (!$body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk === '') {
                continue;
            }
            $buffer .= $chunk;
            // SSE frames are separated by a blank line.
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $frame = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);
                $event = $this->parseFrame($frame, $seq);
                if ($event !== null) {
                    $seq++;
                    $onEvent($event);
                    if (in_array($event->type, ['submission_settled', 'run_end', 'session.error'], true)) {
                        return;
                    }
                }
            }
        }
    }

    public function resume(string $runId, array $payload, array $headers = []): array
    {
        $uri = $this->baseUrl() . '/workflows/runs/' . rawurlencode($runId) . '/resume';
        return $this->postJson($uri, $payload, $headers);
    }

    public function getRunRecord(string $runId, array $headers = []): array
    {
        // flue's plain-JSON run-record view (status/result/error) — no Durable-Streams framing.
        $uri = $this->baseUrl() . '/runs/' . rawurlencode($runId) . '?meta';
        try {
            $response = $this->requestFactory->request($uri, 'GET', [
                'headers' => ['Accept' => 'application/json'] + $headers,
                'http_errors' => false,
                'timeout' => $this->requestTimeout(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Flue run-record fetch failed', ['uri' => $uri, 'error' => $e->getMessage()]);
            return [];
        }
        if ($response->getStatusCode() >= 400) {
            return [];
        }
        $decoded = json_decode((string)$response->getBody(), true);

        return Typed::stringKeyedArray($decoded);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array{runId: string, status: string}
     */
    private function postJson(string $uri, array $payload, array $headers): array
    {
        try {
            $response = $this->requestFactory->request($uri, 'POST', [
                'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'] + $headers,
                'body' => (string)json_encode($payload, JSON_THROW_ON_ERROR),
                'http_errors' => false,
                'timeout' => $this->requestTimeout(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Flue trigger failed', ['uri' => $uri, 'error' => $e->getMessage()]);
            return ['runId' => '', 'status' => 'failed'];
        }

        if ($response->getStatusCode() >= 400) {
            $this->logger->error('Flue sidecar returned an error', ['uri' => $uri, 'status' => $response->getStatusCode()]);
            return ['runId' => '', 'status' => 'failed'];
        }

        $decoded = json_decode((string)$response->getBody(), true);
        $decoded = Typed::stringKeyedArray($decoded);
        $runId = Typed::string($decoded['runId'] ?? $decoded['runKey'] ?? $decoded['id'] ?? '');
        $status = Typed::string($decoded['status'] ?? 'running');

        return ['runId' => $runId, 'status' => $status !== '' ? $status : 'running'];
    }

    private function parseFrame(string $frame, int $seq): ?FlueEvent
    {
        $data = '';
        foreach (explode("\n", $frame) as $line) {
            $line = rtrim($line, "\r");
            if (str_starts_with($line, 'data:')) {
                $data .= ltrim(substr($line, 5));
            }
        }
        if ($data === '') {
            return null;
        }
        $decoded = json_decode($data, true);
        if (!is_array($decoded)) {
            return new FlueEvent($seq, time(), 'message', ['raw' => $data]);
        }
        $decoded = Typed::stringKeyedArray($decoded);

        return new FlueEvent(
            $seq,
            time(),
            Typed::string($decoded['type'] ?? 'message'),
            $decoded,
        );
    }

    private function baseUrl(): string
    {
        try {
            $value = $this->extensionConfiguration->get('flue', 'sidecarBaseUrl');
        } catch (\Throwable) {
            $value = '';
        }
        $base = Typed::string($value);
        return rtrim($base !== '' ? $base : 'http://localhost:3000', '/');
    }

    private function requestTimeout(): int
    {
        try {
            $value = $this->extensionConfiguration->get('flue', 'requestTimeout');
        } catch (\Throwable) {
            $value = 30;
        }
        $timeout = Typed::int($value);
        return $timeout > 0 ? $timeout : 30;
    }
}
