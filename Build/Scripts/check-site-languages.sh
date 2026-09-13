#!/usr/bin/env bash

# Every language a site declares as enabled must actually answer.
#
# A site can list a language in config.yaml while its root page has no visible
# translation, and TYPO3 then answers 404 for the whole language branch. Nothing
# reports it: the configuration is valid, the extension suite is green, and the
# default language keeps working, so the branch can stay dark for months. The
# blog site's English and Chinese roots were hidden exactly that way.
#
# This walks the base URLs `site:list` prints and fails on the first that is not
# reachable. It needs the site running, so it belongs in the e2e suite rather
# than the static one.

set -euo pipefail
cd "$(dirname "$0")/../.."

URLS=$(vendor/bin/typo3 site:list 2>/dev/null \
    | grep -oE 'https?://[^ |]+' \
    | sort -u)

if [[ -z "${URLS}" ]]; then
    echo "ERROR: site:list returned no base URLs." >&2
    exit 1
fi

FAILED=0
COUNT=0
while IFS= read -r URL; do
    [[ -n "${URL}" ]] || continue
    COUNT=$((COUNT + 1))
    STATUS=$(curl -ksS -o /dev/null -w '%{http_code}' --max-time 30 "${URL}" || echo 000)
    if [[ "${STATUS}" != "200" ]]; then
        printf 'ERROR: %s answered %s\n' "${URL}" "${STATUS}" >&2
        FAILED=1
    fi
done <<< "${URLS}"

if [[ "${FAILED}" -ne 0 ]]; then
    cat >&2 <<'HINT'

A language that is enabled in config.yaml but answers 404 usually means the
site root has no visible translation for it. Check the root page's translated
records rather than the site configuration.
HINT
    exit 1
fi

printf 'All %d site base URLs answer 200.\n' "${COUNT}"
