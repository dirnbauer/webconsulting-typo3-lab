<?php

declare(strict_types=1);

/**
 * The production Solr image serves the configsets that EXT:solr creates its
 * cores from, so the extension version in composer.lock and the source commit
 * baked into Dockerfile.solr.coolify have to be the same release. When they
 * drift, nothing fails locally and nothing fails at build time — the mismatch
 * only surfaces on the deployed site, as core creation rejecting a field type
 * one side does not know. It went unnoticed once already: the image still
 * built 14.0.0-RC1 while the lock had moved to 14.0.1.
 *
 * This compares the two and prints what to change. It needs no network and no
 * running site, so it belongs in the ordinary quality run.
 */

$root = dirname(__DIR__, 2);
$lockPath = $root . '/composer.lock';
$dockerfilePath = $root . '/Dockerfile.solr.coolify';

$problems = [];

$lock = json_decode((string)file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR);
$lockedReference = null;
$lockedVersion = null;
foreach ([...$lock['packages'] ?? [], ...$lock['packages-dev'] ?? []] as $package) {
    if (($package['name'] ?? null) === 'apache-solr-for-typo3/solr') {
        $lockedReference = $package['source']['reference'] ?? null;
        $lockedVersion = $package['version'] ?? null;
        break;
    }
}

if (!is_string($lockedReference) || $lockedReference === '') {
    $problems[] = 'composer.lock has no source reference for apache-solr-for-typo3/solr.';
} else {
    $dockerfile = (string)file_get_contents($dockerfilePath);
    if (preg_match('/^ARG EXT_SOLR_REF=([0-9a-f]{40})$/m', $dockerfile, $matches) !== 1) {
        $problems[] = 'Dockerfile.solr.coolify has no ARG EXT_SOLR_REF with a full commit hash.';
    } elseif ($matches[1] !== $lockedReference) {
        $problems[] = sprintf(
            "Dockerfile.solr.coolify builds EXT:solr from %s but composer.lock resolves %s to %s.\n"
            . "      Update ARG EXT_SOLR_REF and ARG EXT_SOLR_SHA256 together:\n"
            . "        curl -sL https://codeload.github.com/TYPO3-Solr/ext-solr/tar.gz/%s | shasum -a 256",
            $matches[1],
            $lockedVersion ?? 'the locked version',
            $lockedReference,
            $lockedReference,
        );
    }
}

if ($problems !== []) {
    foreach ($problems as $problem) {
        fwrite(STDERR, 'ERROR: ' . $problem . PHP_EOL);
    }
    exit(1);
}

printf("Deployment pins agree with composer.lock (EXT:solr %s).\n", $lockedVersion ?? '?');
