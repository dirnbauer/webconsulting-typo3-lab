<?php

declare(strict_types=1);

namespace Webconsulting\Flue\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\NullResponse;
use Webconsulting\Flue\Domain\Model\FlueEvent;
use Webconsulting\Flue\Service\FlowTriggerService;
use Webconsulting\Flue\Service\RunStore;
use Webconsulting\Flue\Support\Typed;

/**
 * Backend AJAX endpoints driving the live module experience:
 *  - trigger: start a flow, return { runUid, runKey, status } (JSON)
 *  - stream:  drain the run's durable events as Server-Sent Events
 *  - resume:  resume a paused/failed run
 *
 * Every event is persisted (RunStore) AND echoed, so a dropped SSE connection
 * is recoverable from the durable `events` column.
 */
final class FlueApiController
{
    public function __construct(
        private readonly FlowTriggerService $flowTriggerService,
        private readonly RunStore $runStore,
    ) {
    }

    public function trigger(ServerRequestInterface $request): ResponseInterface
    {
        $body = Typed::stringKeyedArray($request->getParsedBody());
        $flowUid = Typed::int($body['flow'] ?? 0);
        $pageUid = Typed::int($body['page'] ?? 0);
        $instructions = Typed::string($body['instructions'] ?? '');
        if ($flowUid <= 0 || $pageUid <= 0) {
            return new JsonResponse(['error' => 'A flow and a page uid are required.'], 400);
        }

        try {
            $result = $this->flowTriggerService->trigger(
                $flowUid,
                'pages',
                $pageUid,
                (int)$this->getBackendUser()->workspace,
                $instructions,
                (int)$this->getBackendUser()->getUserId(),
            );
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse($result);
    }

    public function stream(ServerRequestInterface $request): ResponseInterface
    {
        $runUid = Typed::int($request->getQueryParams()['runUid'] ?? 0);
        if ($runUid <= 0 || $this->runStore->load($runUid) === null) {
            return new JsonResponse(['error' => 'Unknown run.'], 404);
        }

        if (!headers_sent()) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');
        }
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $emit = static function (string $type, FlueEvent|array $data): void {
            $payload = $data instanceof FlueEvent ? $data->toArray() : $data;
            echo 'event: ' . $type . "\n" . 'data: ' . (string)json_encode($payload) . "\n\n";
            flush();
        };

        try {
            $this->flowTriggerService->drainRun($runUid, static function (FlueEvent $event) use ($emit): void {
                $emit('event', $event);
            });
            $emit('done', ['runUid' => $runUid]);
        } catch (\Throwable $e) {
            $emit('error', ['message' => $e->getMessage()]);
        }

        return new NullResponse();
    }

    public function resume(ServerRequestInterface $request): ResponseInterface
    {
        $body = Typed::stringKeyedArray($request->getParsedBody());
        $runUid = Typed::int($body['runUid'] ?? 0);
        $run = $runUid > 0 ? $this->runStore->load($runUid) : null;
        if ($run === null) {
            return new JsonResponse(['error' => 'Unknown run.'], 404);
        }
        // Re-drain the existing run (the sidecar resumes durable submissions itself).
        try {
            $this->flowTriggerService->drainRun($runUid);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse(['runUid' => $runUid, 'status' => 'resumed']);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication) {
            throw new \RuntimeException('No backend user available', 1760001300);
        }
        return $backendUser;
    }
}
