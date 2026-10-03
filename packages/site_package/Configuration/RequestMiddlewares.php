<?php

declare(strict_types=1);

use Webconsulting\SitePackage\Middleware\BasicAuthSafeBackendDenial;
use Webconsulting\SitePackage\Middleware\SplitCombinedSolrTypeFilter;

return [
    'frontend' => [
        // Old search links with a combined content-type filter: a redirect,
        // decided from the query string alone, before a page is resolved.
        'webconsulting/site-package/split-combined-solr-type-filter' => [
            'target' => SplitCombinedSolrTypeFilter::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
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
