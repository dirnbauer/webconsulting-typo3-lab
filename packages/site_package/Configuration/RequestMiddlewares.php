<?php

declare(strict_types=1);

use Webconsulting\SitePackage\Middleware\BasicAuthSafeBackendDenial;

return [
    'backend' => [
        // Wraps the authentication and everything after it, and needs the
        // route the routing middleware has resolved.
        'webconsulting/site-package/basic-auth-safe-backend-denial' => [
            'target' => BasicAuthSafeBackendDenial::class,
            'after' => [
                'typo3/cms-backend/backend-routing',
            ],
            'before' => [
                'typo3/cms-backend/authentication',
            ],
        ],
    ],
];
