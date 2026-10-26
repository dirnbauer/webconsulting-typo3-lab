<?php

declare(strict_types=1);

// Backend ES modules of the site package: the light/dark switch of the backend
// login (Resources/Private/TemplateOverrides/typo3/cms-backend/Layouts/Login.fluid.html).
return [
    'dependencies' => ['backend'],
    'imports' => [
        '@webconsulting/site-package/' => 'EXT:site_package/Resources/Public/Backend/JavaScript/',
    ],
];
