<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:tx_flue_run',
        'label' => 'target_table',
        'label_alt' => 'status',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'rootLevel' => 1,
        'hideTable' => true,
        'versioningWS' => false,
        'default_sortby' => 'crdate DESC',
        'typeicon_classes' => [
            'default' => 'flue-run',
        ],
    ],
    'columns' => [
        'flow' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:run.flow',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_flue_flow',
                'items' => [['label' => '', 'value' => 0]],
                'default' => 0,
                'readOnly' => true,
            ],
        ],
        'run_key' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:run.run_key',
            'config' => ['type' => 'input', 'max' => 64, 'readOnly' => true],
        ],
        'flue_run_id' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:run.flue_run_id',
            'config' => ['type' => 'input', 'max' => 64, 'readOnly' => true],
        ],
        'target_table' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:run.target_table',
            'config' => ['type' => 'input', 'max' => 255, 'readOnly' => true],
        ],
        'target_uid' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:run.target_uid',
            'config' => ['type' => 'number', 'readOnly' => true],
        ],
        'workspace_uid' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:run.workspace_uid',
            'config' => ['type' => 'number', 'readOnly' => true],
        ],
        'instructions' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:run.instructions',
            'config' => ['type' => 'text', 'rows' => 3, 'readOnly' => true],
        ],
        'status' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:run.status',
            'config' => ['type' => 'input', 'max' => 20, 'readOnly' => true],
        ],
        'output' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:run.output',
            'config' => ['type' => 'text', 'rows' => 20, 'readOnly' => true],
        ],
        'error_message' => [
            'label' => 'LLL:EXT:flue/Resources/Private/Language/locallang_db.xlf:run.error_message',
            'config' => ['type' => 'text', 'rows' => 3, 'readOnly' => true],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'flow, status, run_key, flue_run_id, target_table, target_uid, workspace_uid, instructions, output, error_message',
        ],
    ],
];
