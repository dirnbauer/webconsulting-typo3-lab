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

    // Unset keeps the standalone Install Tool locked; system maintainers still
    // reach the Admin Tools through the backend.
    $installToolPasswordFile = '/run/typo3-secrets/install-tool-password';
    $installToolPassword = is_readable($installToolPasswordFile)
        ? trim((string)file_get_contents($installToolPasswordFile))
        : trim((string)getenv('TYPO3_INSTALL_TOOL_PASSWORD_HASH'));

    // Mail leaves the server only through SMTP. The account comes from Coolify's
    // variables, never from this repository. Without a server, mail is written to
    // var/log/mail.mbox: forms still save their submissions, but nobody gets an email.
    $mailTransport = trim((string)getenv('TYPO3_MAIL_TRANSPORT')) ?: 'mbox';
    $smtpServer = trim((string)getenv('TYPO3_MAIL_SMTP_SERVER'));
    if ($mailTransport === 'smtp' && $smtpServer === '') {
        $mailTransport = 'mbox';
    }
    $mail = [
        'transport' => $mailTransport,
        'transport_mbox_file' => '/var/www/html/var/log/mail.mbox',
    ];
    if ($mailTransport === 'smtp') {
        $mail['transport_smtp_server'] = $smtpServer;
        // true = implicit TLS (smtps, usually port 465); false still upgrades a
        // plain connection with STARTTLS when the server offers it (port 587).
        $mail['transport_smtp_encrypt'] = filter_var(getenv('TYPO3_MAIL_SMTP_ENCRYPT'), FILTER_VALIDATE_BOOL);
        $mail['transport_smtp_username'] = (string)getenv('TYPO3_MAIL_SMTP_USERNAME');
        $mail['transport_smtp_password'] = (string)getenv('TYPO3_MAIL_SMTP_PASSWORD');
    }
    $mailFromAddress = trim((string)getenv('TYPO3_MAIL_FROM_ADDRESS'));
    if ($mailFromAddress !== '') {
        $mail['defaultMailFromAddress'] = $mailFromAddress;
        $mail['defaultMailFromName'] = trim((string)getenv('TYPO3_MAIL_FROM_NAME')) ?: 'webconsulting';
    }

    $GLOBALS['TYPO3_CONF_VARS'] = array_replace_recursive(
        $GLOBALS['TYPO3_CONF_VARS'],
        [
            'BE' => [
                'debug' => false,
                'installToolPassword' => $installToolPassword,
                'lockSSL' => true,
                // Admins only: editors created by WorkOS sign-in keep a one-step login.
                'requireMfa' => 3,
                'passwordHashing' => [
                    'className' => \TYPO3\CMS\Core\Crypto\PasswordHashing\Argon2idPasswordHash::class,
                    'options' => [],
                ],
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
                'passwordHashing' => [
                    'className' => \TYPO3\CMS\Core\Crypto\PasswordHashing\Argon2idPasswordHash::class,
                    'options' => [],
                ],
            ],
            'GFX' => [
                'processor' => 'GraphicsMagick',
                'processor_enabled' => true,
                'processor_path' => '/usr/bin/',
            ],
            'MAIL' => $mail,
            'SYS' => [
                'devIPmask' => '',
                'displayErrors' => 0,
                'encryptionKey' => $encryptionKey,
                // Report-only until the reports (Admin Tools > Content Security
                // Policy) show which inline scripts and sources need a policy.
                'features' => [
                    'security.frontend.reportContentSecurityPolicy' => true,
                ],
                'reverseProxyHeaderMultiValue' => 'first',
                'reverseProxyIP' => '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16',
                'reverseProxySSL' => '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16',
                // SYS.sitename ("webconsulting TYPO3 Lab · Live") comes from
                // packages/site_package/ext_localconf.php, the same on DDEV and here.
                'trustedHostsPattern' => '^typo3-lab\\.webconsulting\\.at$',
            ],
        ]
    );

    // Skillflow refuses to run outside a local DDEV install in Development
    // context. This lab instance is allowed to run skills anyway, but only
    // through the API runner: the CLI runner would start Claude Code with tool
    // access on this server. Local installs keep the guard.
    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['skillflow']['requireLocalEnvironment'] = '0';
    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['skillflow']['runner'] = 'api';

    // The demo APIs publish every content element's bodytext without
    // authentication, paywalled pages included. The public API and its docs
    // stay registered without them.
    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['sg_apicore']['activateDemoApis'] = '0';
    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['extensionmanager']['automaticInstallation'] = '0';

    // Empty keeps the configured provider unchanged. An explicit file is
    // validated on every bootstrap, before the native provider can fall back
    // to an auto-key. Provisioning/copying the exact current Vault master key
    // and rotating SYS.encryptionKey are separate, approved operations.
    $configureVaultFile = require __DIR__ . '/vault-file-provider.php';
    $GLOBALS['TYPO3_CONF_VARS'] = $configureVaultFile(
        $GLOBALS['TYPO3_CONF_VARS'],
        (string)getenv('TYPO3_VAULT_MASTER_KEY_FILE'),
        dirname(__DIR__, 2) . '/public'
    );
}
