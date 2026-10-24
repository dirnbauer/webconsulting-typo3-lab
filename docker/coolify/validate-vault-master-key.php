<?php

declare(strict_types=1);

// Called as www-data before the entrypoint changes any state. Do not bootstrap
// TYPO3, read the signing key, generate a key or invoke a Vault write operation.
$root = dirname(__DIR__, 2);
$configure = require $root . '/config/system/vault-file-provider.php';

try {
    $configure([], (string)getenv('TYPO3_VAULT_MASTER_KEY_FILE'), $root . '/public');
} catch (Throwable $error) {
    fwrite(STDERR, 'entrypoint: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
