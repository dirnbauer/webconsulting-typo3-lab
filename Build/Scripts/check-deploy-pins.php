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

// Compose passes this fallback as a build argument, overriding the Dockerfile.
// A stale fallback silently prevents an updated runtime digest from being used.
$webDockerfile = (string)file_get_contents($root . '/Dockerfile.coolify');
$compose = (string)file_get_contents($root . '/docker-compose.coolify.yml');
if (preg_match('/^ARG PHP_BASE_IMAGE=(\S+)$/m', $webDockerfile, $phpDefault) !== 1
    || preg_match('/PHP_BASE_IMAGE:\s*\$\{PHP_BASE_IMAGE:-(\S+)\}/', $compose, $phpFallback) !== 1) {
    $problems[] = 'PHP_BASE_IMAGE defaults must be declared in the web Dockerfile and Compose.';
} elseif ($phpDefault[1] !== $phpFallback[1]) {
    $problems[] = 'PHP_BASE_IMAGE defaults differ between Dockerfile.coolify and docker-compose.coolify.yml.';
}

$gitlabCiPath = $root . '/.gitlab-ci.yml';
if (!is_file($gitlabCiPath)) {
    $problems[] = '.gitlab-ci.yml is missing.';
} else {
    $gitlabCi = (string)file_get_contents($gitlabCiPath);
    if (isset($phpDefault[1])) {
        if (preg_match('/^\s*PHP_BASE_IMAGE:\s*"?([^"\s]+)"?\s*$/m', $gitlabCi, $gitlabPhp) !== 1) {
            $problems[] = '.gitlab-ci.yml must declare PHP_BASE_IMAGE.';
        } elseif ($gitlabPhp[1] !== $phpDefault[1]) {
            $problems[] = 'PHP_BASE_IMAGE differs between Dockerfile.coolify and .gitlab-ci.yml.';
        }
    }
    if (preg_match('/^FROM\s+(node:\S+)\s+AS\s+node-runtime$/m', $webDockerfile, $nodeRuntime) !== 1
        || preg_match('/^\s*NODE_BASE_IMAGE:\s*"?([^"\s]+)"?\s*$/m', $gitlabCi, $gitlabNodeImage) !== 1) {
        $problems[] = 'NODE_BASE_IMAGE must be declared in Dockerfile.coolify and .gitlab-ci.yml.';
    } elseif ($nodeRuntime[1] !== $gitlabNodeImage[1]) {
        $problems[] = 'NODE_BASE_IMAGE differs between Dockerfile.coolify and .gitlab-ci.yml.';
    }
    if (preg_match('/^ARG COMPOSER_VERSION=(\S+)$/m', $webDockerfile, $composerVer) !== 1
        || preg_match('/^\s*COMPOSER_VERSION:\s*"?([^"\s]+)"?\s*$/m', $gitlabCi, $gitlabComposerVer) !== 1
        || $composerVer[1] !== $gitlabComposerVer[1]) {
        $problems[] = 'COMPOSER_VERSION must match between Dockerfile.coolify and .gitlab-ci.yml.';
    }
    if (preg_match('/^ARG NPM_VERSION=(\S+)$/m', $webDockerfile, $npmVer) !== 1
        || preg_match('/^\s*NPM_VERSION:\s*"?([^"\s]+)"?\s*$/m', $gitlabCi, $gitlabNpmVer) !== 1
        || $npmVer[1] !== $gitlabNpmVer[1]) {
        $problems[] = 'NPM_VERSION must match between Dockerfile.coolify and .gitlab-ci.yml.';
    }
}

$registryComposePath = $root . '/docker-compose.coolify.registry.yml';
if (!is_file($registryComposePath)) {
    $problems[] = 'docker-compose.coolify.registry.yml is missing.';
} else {
    $registryCompose = (string)file_get_contents($registryComposePath);
    if (preg_match('/^\s+build:\s*$/m', $registryCompose) === 1) {
        $problems[] = 'docker-compose.coolify.registry.yml must pull prebuilt images instead of declaring build: blocks.';
    }
    if (substr_count($registryCompose, 'pull_policy: always') < 2
        || substr_count($registryCompose, '${SOURCE_COMMIT:-latest}') < 2) {
        $problems[] = 'docker-compose.coolify.registry.yml must declare pull_policy: always and ${SOURCE_COMMIT:-latest} on both web and typo3-solr.';
    }
}

$lock = json_decode((string)file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR);
$lockedReference = null;
$lockedVersion = null;
$recordsListVersions = [];
foreach ([...$lock['packages'] ?? [], ...$lock['packages-dev'] ?? []] as $package) {
    if (in_array($package['name'] ?? null, ['webconsulting/records-list-types', 'webconsulting/records-list-examples'], true)) {
        $recordsListVersions[$package['name']] = ltrim((string)($package['version'] ?? ''), 'v');
    }
    if (($package['name'] ?? null) === 'apache-solr-for-typo3/solr') {
        $lockedReference = $package['source']['reference'] ?? null;
        $lockedVersion = $package['version'] ?? null;
    }
}

if (($recordsListVersions['webconsulting/records-list-types'] ?? '') === ''
    || ($recordsListVersions['webconsulting/records-list-examples'] ?? '') === '') {
    $problems[] = 'composer.lock must include both Records List packages.';
} elseif ($recordsListVersions['webconsulting/records-list-types'] !== $recordsListVersions['webconsulting/records-list-examples']) {
    $problems[] = sprintf(
        'Records List Types %s and Examples %s must have the same release number.',
        $recordsListVersions['webconsulting/records-list-types'],
        $recordsListVersions['webconsulting/records-list-examples'],
    );
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

// Every locale a site declares has to exist in the production image, or TYPO3
// falls back and logs "Locale ... not found" on each request that formats a
// date. The lab logged that 1318 times: the image generated four locales while
// the sites asked for five, and one site had a hreflang ("en-US") where a POSIX
// locale belongs.
$requested = [];
foreach (glob($root . '/config/sites/*/config.yaml') ?: [] as $siteConfig) {
    if (preg_match_all('/^\s*locale:\s*(\S+)\s*$/m', (string)file_get_contents($siteConfig), $found) < 1) {
        continue;
    }
    foreach ($found[1] as $locale) {
        $requested[trim($locale, "'\"")][] = basename(dirname($siteConfig));
    }
}

$dockerfile = (string)file_get_contents($root . '/Dockerfile.coolify');
preg_match_all('/([a-z]{2}_[A-Z]{2}\.UTF-8) UTF-8/', $dockerfile, $generated);
$available = array_unique($generated[1]);

foreach ($requested as $locale => $sites) {
    $where = implode(', ', array_unique($sites));
    if (preg_match('/^[a-z]{2}_[A-Z]{2}\.UTF-8$/', $locale) !== 1) {
        $problems[] = sprintf(
            '%s declares locale "%s", which is not a POSIX locale. Site "locale" needs the '
            . 'de_AT.UTF-8 form; the en-US form belongs in "hreflang".',
            $where,
            $locale,
        );
        continue;
    }
    if (!in_array($locale, $available, true)) {
        $problems[] = sprintf(
            'Locale "%s" (%s) is not generated by Dockerfile.coolify. Add it to the locale.gen list.',
            $locale,
            $where,
        );
    }
}

if ($problems !== []) {
    foreach ($problems as $problem) {
        fwrite(STDERR, 'ERROR: ' . $problem . PHP_EOL);
    }
    exit(1);
}

printf(
    "Deployment pins agree with composer.lock (EXT:solr %s, Records List pair %s).\n",
    $lockedVersion ?? '?',
    $recordsListVersions['webconsulting/records-list-types'],
);
