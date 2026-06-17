<?php

declare(strict_types=1);

namespace Webconsulting\Flue\Domain\Model;

use Webconsulting\Flue\Support\Typed;

/**
 * One entry of a run's append-only event log, mirroring a Flue durable event
 * (e.g. agent.message, agent.mcp_tool_use, run_end, submission_settled).
 */
final class FlueEvent
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly int $seq,
        public readonly int $ts,
        public readonly string $type,
        public readonly array $data = [],
    ) {
    }

    /**
     * @return array{seq: int, ts: int, type: string, data: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['seq' => $this->seq, 'ts' => $this->ts, 'type' => $this->type, 'data' => $this->data];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            Typed::int($row['seq'] ?? 0),
            Typed::int($row['ts'] ?? 0),
            Typed::string($row['type'] ?? ''),
            Typed::stringKeyedArray($row['data'] ?? []),
        );
    }
}
