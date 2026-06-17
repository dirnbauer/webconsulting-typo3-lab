<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'Flue control plane',
    'description' => 'TYPO3 control plane for the Flue agent framework: define/trigger durable AI flows, inject page context, export skillflow skills, mirror runs — driven from a backend module.',
    'category' => 'module',
    'author' => 'webconsulting GmbH',
    'author_email' => 'office@webconsulting.at',
    'state' => 'beta',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.4.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'skillflow' => '',
            'typo3_mcp_server' => '',
            'nr_vault' => '',
        ],
    ],
];
