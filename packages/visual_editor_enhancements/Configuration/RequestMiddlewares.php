<?php

declare(strict_types=1);

use Webconsulting\VisualEditorEnhancements\Middleware\EditModeEnhancementsMiddleware;

return [
    'frontend' => [
        'webconsulting/visual-editor-enhancements/edit-mode-assets' => [
            'target' => EditModeEnhancementsMiddleware::class,
            'after' => [
                'typo3/cms-visual-editor/persistence-middleware',
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
