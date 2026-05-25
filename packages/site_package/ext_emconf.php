<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Site Package',
    'description' => 'Shared theme and provider configuration for the Visual Editor demo setup.',
    'category' => 'templates',
    'author' => 'webconsulting',
    'author_email' => 'office@webconsulting.at',
    'state' => 'beta',
    'version' => '14.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.99.99',
            'adminpanel' => '14.3.0-14.99.99',
            'visual_editor' => '1.4.0-1.99.99',
            'solr' => '14.0.0-14.99.99',
            'solr_numbered_pagination' => '14.0.0-14.99.99',
            'blog' => '14.0.0-14.99.99',
            'news' => '14.0.0-14.99.99',
            'theme_camino' => '14.3.0-14.99.99',
            'desiderio' => '2.0.0-2.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
