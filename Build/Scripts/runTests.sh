#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
SUITE="ci"
PHP_VERSION=""

while getopts "s:p:d:nx" option; do
    case "${option}" in
        s) SUITE="${OPTARG}" ;;
        p) PHP_VERSION="${OPTARG}" ;;
        d) ;;
        n) ;;
        x) ;;
        *) exit 2 ;;
    esac
done

cd "${ROOT_DIR}"

if [[ -n "${PHP_VERSION}" ]]; then
    CURRENT_PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
    if [[ "${CURRENT_PHP_VERSION}" != "${PHP_VERSION}" ]]; then
        echo "Requested PHP ${PHP_VERSION}, running PHP ${CURRENT_PHP_VERSION}." >&2
        exit 2
    fi
fi

case "${SUITE}" in
    composerValidate)
        composer validate --no-check-publish
        ;;
    phpstan)
        vendor/bin/phpstan analyse --configuration=Build/phpstan/phpstan.neon --memory-limit=512M --no-progress
        ;;
    phpLint|lint)
        find packages/site_package -name '*.php' -not -path '*/vendor/*' -print0 \
            | xargs -0 -n 1 php -l
        ;;
    yaml)
        YAML_FILE_COUNT=0
        while IFS= read -r -d '' YAML_FILE; do
            vendor/bin/typo3 lint:yaml --parse-tags "${YAML_FILE}" >/dev/null
            YAML_FILE_COUNT=$((YAML_FILE_COUNT + 1))
        done < <(find config packages -type f \( -name '*.yaml' -o -name '*.yml' \) \
            -not -path '*/vendor/*' -not -path '*/node_modules/*' -print0)
        echo "Validated ${YAML_FILE_COUNT} YAML files."
        ;;
    frontend)
        npm run build
        npm audit --audit-level=high
        ;;
    e2e)
        # Vite emits content-hashed filenames. Cached frontend pages may still
        # reference the previous manifest after a rebuild, so exercise the
        # freshly built assets rather than stale page-cache markup.
        vendor/bin/typo3 cache:flush
        npm run test:e2e
        ;;
    quality)
        "${BASH_SOURCE[0]}" -s composerValidate
        "${BASH_SOURCE[0]}" -s phpLint
        "${BASH_SOURCE[0]}" -s yaml
        "${BASH_SOURCE[0]}" -s phpstan
        "${BASH_SOURCE[0]}" -s frontend
        ;;
    ci)
        "${BASH_SOURCE[0]}" -s quality
        "${BASH_SOURCE[0]}" -s e2e
        ;;
    *)
        echo "Unknown suite: ${SUITE}" >&2
        exit 2
        ;;
esac
