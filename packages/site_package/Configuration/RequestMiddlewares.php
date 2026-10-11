<?php

declare(strict_types=1);

use Webconsulting\SitePackage\Middleware\BasicAuthSafeBackendDenial;
use Webconsulting\SitePackage\Middleware\BasicAuthSafeFrontendEditDenial;
use Webconsulting\SitePackage\Middleware\PreserveFrontendEditorSession;
use Webconsulting\SitePackage\Middleware\SplitCombinedSolrTypeFilter;

return [
    'frontend' => [
        'webconsulting/site-package/preserve-frontend-editor-session' => [
            'target' => PreserveFrontendEditorSession::class,
            'after' => [
                'typo3/cms-frontend/backend-user-authentication',
            ],
            'before' => [
                'typo3/cms-workspaces/preview',
                'webconsulting/desiderio-element-library',
                'webconsulting/visual-editor-enhancements/field-options',
            ],
        ],
        // The visual editor iframe uses the frontend stack, including two
        // editing endpoints that return before the page resolver.
        'webconsulting/site-package/basic-auth-safe-frontend-edit-denial' => [
            'target' => BasicAuthSafeFrontendEditDenial::class,
            'after' => [
                'typo3/cms-frontend/backend-user-authentication',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
                'webconsulting/desiderio-element-library',
                'webconsulting/visual-editor-enhancements/field-options',
            ],
        ],
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
