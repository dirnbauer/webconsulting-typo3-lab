<?php

declare(strict_types=1);

use Dirnbauer\SitePackage\Middleware\CowriterPreloadMiddleware;

return [
    'frontend' => [
        'dirnbauer/site-package/cowriter-preload' => [
            'target' => CowriterPreloadMiddleware::class,
            // Run BEFORE visual-editor's PersistenceMiddleware so the
            // PageRenderer already has t3-cowriter queued when the
            // editMode rendering picks up the import map.
            'before' => [
                'typo3/cms-visual-editor/persistence-middleware',
            ],
            // ...but after the FE auth / page-resolver so we're sure
            // there's a request context.
            'after' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
