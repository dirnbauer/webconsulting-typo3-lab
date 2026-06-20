#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
SUITE="ci"

while getopts "s:p:d:nx" option; do
    case "${option}" in
        s) SUITE="${OPTARG}" ;;
        p) ;;
        d) ;;
        n) ;;
        x) ;;
        *) exit 2 ;;
    esac
done

cd "${ROOT_DIR}"

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
        vendor/bin/typo3 lint:yaml config/sites packages/site_package/Configuration/Sets
        ;;
    ci)
        "${BASH_SOURCE[0]}" -s composerValidate
        "${BASH_SOURCE[0]}" -s phpLint
        "${BASH_SOURCE[0]}" -s yaml
        "${BASH_SOURCE[0]}" -s phpstan
        ;;
    *)
        echo "Unknown suite: ${SUITE}" >&2
        exit 2
        ;;
esac
