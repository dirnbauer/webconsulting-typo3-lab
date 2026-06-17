<?php

declare(strict_types=1);

namespace Webconsulting\Flue\Domain\Model;

use Webconsulting\Flue\Enum\RunStatus;
use Webconsulting\Flue\Support\Typed;

/**
 * Read model for one durable run row (tx_flue_run), including its decoded
 * append-only event log. The TYPO3 run row mirrors the sidecar's Flue run.
 */
final class FlueRun
{
    /**
     * @param list<FlueEvent> $events
     */
    public function __construct(
        public readonly int $uid,
        public readonly int $flow,
        public readonly string $runKey,
        public readonly string $flueRunId,
        public readonly string $targetTable,
        public readonly int $targetUid,
        public readonly int $workspaceUid,
        public readonly string $instructions,
        public readonly RunStatus $status,
        public readonly string $output,
        public readonly string $errorMessage,
        public readonly int $crdate,
        public readonly array $events,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $rawEvents = json_decode(Typed::string($row['events'] ?? ''), true);
        $events = [];
        if (is_array($rawEvents)) {
            foreach ($rawEvents as $entry) {
                if (is_array($entry)) {
                    $events[] = FlueEvent::fromArray(Typed::stringKeyedArray($entry));
                }
            }
        }

        return new self(
            Typed::int($row['uid'] ?? 0),
            Typed::int($row['flow'] ?? 0),
            Typed::string($row['run_key'] ?? ''),
            Typed::string($row['flue_run_id'] ?? ''),
            Typed::string($row['target_table'] ?? ''),
            Typed::int($row['target_uid'] ?? 0),
            Typed::int($row['workspace_uid'] ?? 0),
            Typed::string($row['instructions'] ?? ''),
            RunStatus::fromStringSafe(Typed::string($row['status'] ?? 'idle')),
            Typed::string($row['output'] ?? ''),
            Typed::string($row['error_message'] ?? ''),
            Typed::int($row['crdate'] ?? 0),
            $events,
        );
    }

    public function isResumable(): bool
    {
        return $this->status->isResumable();
    }
}
