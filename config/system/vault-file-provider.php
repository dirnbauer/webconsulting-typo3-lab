<?php

declare(strict_types=1);

/**
 * Configure only an explicitly enabled, pre-provisioned native Vault file provider.
 * This runs at bootstrap as well as container start: nr-vault's file provider can
 * otherwise fall back to its auto-key path when a configured file disappears.
 *
 * The fourth argument exists for synthetic fixtures; production callers use
 * only the fixed read-only custody volume declared in Compose.
 *
 * @return Closure(array<string, mixed>, string, string, string=): array<string, mixed>
 */
return static function (#[SensitiveParameter] array $configuration, string $file, string $webRoot, string $custodyDirectory = '/run/typo3-vault-secrets'): array {
    if ($file === '') {
        return $configuration;
    }

    if ($file[0] !== '/' || preg_match('/[\x00-\x20\x7f]/', $file)) {
        throw new RuntimeException('Vault master key file must be a canonical absolute local path.');
    }

    clearstatcache(true, $file);
    $resolved = realpath($file);
    $public = realpath($webRoot);
    if ($resolved === false || !is_file($resolved)) {
        throw new RuntimeException('Configured Vault master key file is missing or is not a regular file.');
    }
    if ($public === false) {
        throw new RuntimeException('Cannot resolve the web root for Vault master key validation.');
    }
    if ($resolved === $public || str_starts_with($resolved, rtrim($public, '/') . '/')) {
        throw new RuntimeException('Vault master key file must be outside the web root.');
    }
    if ($resolved !== $file || is_link($file)) {
        throw new RuntimeException('Vault master key file must not use symlinks or path aliases.');
    }
    if (!is_readable($resolved)) {
        throw new RuntimeException('Configured Vault master key file is not readable by the runtime user.');
    }
    $permissions = fileperms($resolved);
    if ($permissions === false || ($permissions & 07777) !== 0400) {
        throw new RuntimeException('Vault master key file must have permissions 0400.');
    }
    if ($resolved !== rtrim($custodyDirectory, '/') . '/master-key') {
        throw new RuntimeException('Vault master key file must use the dedicated read-only custody volume.');
    }

    // Bound the read and accept only canonical base64 for exactly 32 bytes.
    // The native provider also accepts raw bytes; this deployment deliberately
    // requires its portable base64 format, with at most one final LF or CRLF.
    $encoded = file_get_contents($resolved, false, null, 0, 47);
    $matches = [];
    $formatValid = $encoded !== false && preg_match('~\A([A-Za-z0-9+/]{43}=)(?:\r?\n)?\z~', $encoded, $matches) === 1;
    $base64 = $matches[1] ?? '';
    $decoded = base64_decode($base64, true);
    if (!$formatValid || $decoded === false
        || strlen($decoded) !== 32 || base64_encode($decoded) !== $base64) {
        throw new RuntimeException('Vault master key file must contain canonical base64 encoding of exactly 32 bytes.');
    }

    return array_replace_recursive($configuration, [
        'EXTENSIONS' => [
            'nr_vault' => [
                'masterKeyProvider' => 'file',
                'masterKeySource' => $resolved,
            ],
        ],
    ]);
};
