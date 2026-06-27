<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Visual Editor Enhancements',
    'description' => 'Local Visual Editor enhancements for element library and editable links.',
    'category' => 'be',
    'author' => 'webconsulting GmbH',
    'author_email' => 'office@webconsulting.at',
    'state' => 'experimental',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.9.99',
            'visual_editor' => '1.8.0-1.99.99',
        ],
    ],
];
