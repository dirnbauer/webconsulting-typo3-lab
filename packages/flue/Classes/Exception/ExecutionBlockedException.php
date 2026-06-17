<?php

declare(strict_types=1);

namespace Webconsulting\Flue\Exception;

/**
 * Thrown when the environment guard refuses to trigger a flow (e.g. not a local
 * DDEV/Development install). Surfaced to the operator, never escapes as a fatal.
 */
final class ExecutionBlockedException extends \RuntimeException
{
}
