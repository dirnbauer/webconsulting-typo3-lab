<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:tx_flue_flow',
        'label' => 'title',
        'label_alt' => 'identifier',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'rootLevel' => 1,
        'default_sortby' => 'title',
        'typeicon_classes' => [
            'default' => 'flue-module',
        ],
        'searchFields' => 'title,identifier,workflow_name',
    ],
    'columns' => [
        'title' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:flow.title',
            'config' => ['type' => 'input', 'max' => 255, 'required' => true, 'eval' => 'trim'],
        ],
        'identifier' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:flow.identifier',
            'config' => [
                'type' => 'slug',
                'generatorOptions' => ['fields' => ['title'], 'replacements' => ['-' => '_']],
                'fallbackCharacter' => '_',
                'eval' => 'unique',
            ],
        ],
        'workflow_name' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:flow.workflow_name',
            'description' => 'Flue workflow/agent name on the sidecar (e.g. page-report).',
            'config' => ['type' => 'input', 'max' => 190, 'eval' => 'trim', 'default' => 'page-report'],
        ],
        'default_agent' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:flow.default_agent',
            'config' => ['type' => 'input', 'max' => 190, 'eval' => 'trim', 'default' => 'page-report'],
        ],
        'default_model' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:flow.default_model',
            'config' => ['type' => 'input', 'max' => 190, 'eval' => 'trim'],
        ],
        'skills' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:flow.skills',
            'description' => 'skillflow skills exported to the sidecar for this flow.',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_skillflow_skill',
                'size' => 6,
                'minitems' => 0,
            ],
        ],
        'mcp_tools' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:flow.mcp_tools',
            'description' => 'Comma-separated typo3-mcp-server tool names the agent may use (read-only for the MVP).',
            'config' => ['type' => 'text', 'rows' => 3, 'default' => 'GetPage,ReadTable,RenderRecord,GetPageTree'],
        ],
        'instructions' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:flow.instructions',
            'config' => ['type' => 'text', 'rows' => 4],
        ],
        'input_schema' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:flow.input_schema',
            'config' => ['type' => 'text', 'rows' => 4, 'renderType' => 'codeEditor', 'format' => 'json'],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'title, identifier, workflow_name, default_agent, default_model, instructions, skills, mcp_tools, input_schema',
        ],
    ],
];
