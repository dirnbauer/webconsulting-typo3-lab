<?php

declare(strict_types=1);

namespace Webconsulting\Flue\Enum;

/**
 * Lifecycle of a durable Flue run, mirrored from the sidecar's append-only log.
 * idle -> submitted -> running -> settled | failed; resumable is a recoverable
 * intermediate state used by the resume seam.
 */
enum RunStatus: string
{
    case Idle = 'idle';
    case Submitted = 'submitted';
    case Running = 'running';
    case Settled = 'settled';
    case Failed = 'failed';
    case Resumable = 'resumable';

    public function isTerminal(): bool
    {
        return $this === self::Settled || $this === self::Failed;
    }

    public function isResumable(): bool
    {
        return $this === self::Running || $this === self::Failed || $this === self::Resumable;
    }

    public static function fromStringSafe(string $value): self
    {
        return self::tryFrom($value) ?? self::Idle;
    }
}
