<?php

declare(strict_types=1);

namespace Webconsulting\Flue\Service;

use Webconsulting\Flue\Domain\Model\FlueEvent;

/**
 * Talks to the Node @flue/runtime sidecar: trigger a flow, stream its durable
 * events, resume a run. Implementations must never let a transport failure
 * escape as a fatal into the calling backend request.
 */
interface FlueClientInterface
{
    /**
     * Fire a flow on the sidecar.
     *
     * @param array<string, mixed> $payload   the dispatch payload (resolved context, skills, mcp auth)
     * @param array<string, string> $headers  per-request secrets (e.g. X-Flue-Anthropic-Key), never logged
     * @return array{runId: string, status: string}
     */
    public function trigger(string $workflowName, array $payload, array $headers = []): array;

    /**
     * Open the sidecar's SSE event stream for a run and invoke $onEvent per
     * decoded event. Blocking until the stream ends or the run settles.
     *
     * @param callable(FlueEvent): void $onEvent
     * @param array<string, string> $headers
     */
    public function streamEvents(string $runId, callable $onEvent, array $headers = []): void;

    /**
     * Resume a paused/failed run with new input.
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array{runId: string, status: string}
     */
    public function resume(string $runId, array $payload, array $headers = []): array;

    /**
     * Fetch a run's record (status/result/error) as plain JSON via GET /runs/<id>?meta.
     * Returns [] on any transport or decode failure (never throws into the request).
     *
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function getRunRecord(string $runId, array $headers = []): array;
}
