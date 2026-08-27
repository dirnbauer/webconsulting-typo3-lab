<?php

declare(strict_types=1);

if (getenv('IS_DDEV_PROJECT') !== 'true') {
    $encryptionKeyFile = '/run/typo3-secrets/encryption-key';
    $encryptionKey = is_readable($encryptionKeyFile)
        ? trim((string)file_get_contents($encryptionKeyFile))
        : trim((string)getenv('TYPO3_ENCRYPTION_KEY'));

    if ($encryptionKey === '') {
        throw new RuntimeException('TYPO3 encryption key is not configured.');
    }

    $mailTransport = trim((string)getenv('TYPO3_MAIL_TRANSPORT')) ?: 'mbox';

    $GLOBALS['TYPO3_CONF_VARS'] = array_replace_recursive(
        $GLOBALS['TYPO3_CONF_VARS'],
        [
            'BE' => [
                'debug' => false,
                'lockSSL' => true,
            ],
            'DB' => [
                'Connections' => [
                    'Default' => [
                        'charset' => 'utf8mb4',
                        'dbname' => (string)getenv('TYPO3_DB_DATABASE'),
                        'defaultTableOptions' => [
                            'charset' => 'utf8mb4',
                            'collation' => 'utf8mb4_unicode_ci',
                        ],
                        'driver' => 'mysqli',
                        'host' => (string)getenv('TYPO3_DB_HOST'),
                        'password' => (string)getenv('TYPO3_DB_PASSWORD'),
                        'port' => (int)(getenv('TYPO3_DB_PORT') ?: 3306),
                        'user' => (string)getenv('TYPO3_DB_USER'),
                    ],
                ],
            ],
            'FE' => [
                'debug' => false,
            ],
            'GFX' => [
                'processor' => 'GraphicsMagick',
                'processor_enabled' => true,
                'processor_path' => '/usr/bin/',
            ],
            'MAIL' => [
                'transport' => $mailTransport,
                'transport_mbox_file' => '/var/www/html/var/log/mail.mbox',
            ],
            'SYS' => [
                'devIPmask' => '',
                'displayErrors' => 0,
                'encryptionKey' => $encryptionKey,
                'reverseProxyHeaderMultiValue' => 'first',
                'reverseProxyIP' => '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16',
                'reverseProxySSL' => '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16',
                'sitename' => 'Webconsulting TYPO3 Lab',
                'trustedHostsPattern' => '^typo3-lab\\.webconsulting\\.at$',
            ],
        ]
    );
}
