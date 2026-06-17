<?php

declare(strict_types=1);

use Webconsulting\Flue\Controller\FlueApiController;

/**
 * Backend AJAX routes (CSRF-protected, behind BE auth). The module JS calls
 * these to trigger a flow, stream its durable events (SSE), and resume a run.
 */
return [
    'flue_trigger' => [
        'path' => '/flue/trigger',
        'target' => FlueApiController::class . '::trigger',
        'methods' => ['POST'],
    ],
    'flue_stream' => [
        'path' => '/flue/stream',
        'target' => FlueApiController::class . '::stream',
        'methods' => ['GET'],
    ],
    'flue_resume' => [
        'path' => '/flue/resume',
        'target' => FlueApiController::class . '::resume',
        'methods' => ['POST'],
    ],
];
