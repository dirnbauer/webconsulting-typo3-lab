#!/usr/bin/env bash
# DDEV-only protocol integration test. Never creates a permanent trusted test client.
set -euo pipefail
cd "$(dirname "$0")/../.."
[[ -f .ddev/config.yaml ]] || { echo 'DDEV checkout required' >&2; exit 2; }
NAME="$(python3 -c 'import re; print(re.search(r"^name: (.+)$",open(".ddev/config.yaml").read(),re.M)[1])')"
export POPPY_TEST_ORIGIN="https://${NAME}.ddev.site"
TEST_DIR="$(mktemp -d "$PWD/var/poppy-test.XXXXXX")"
export POPPY_TEST_DIR="$TEST_DIR"
export POPPY_TEST_PUBLIC="$PWD/public/fileadmin/user_upload/$(basename "$TEST_DIR")"
export POPPY_TEST_CLIENT="$POPPY_TEST_ORIGIN/fileadmin/user_upload/$(basename "$TEST_DIR")/agent.json"
mkdir -p "$POPPY_TEST_PUBLIC"
cp config/sites/desiderio/config.yaml "$TEST_DIR/config.yaml"
cleanup() {
  cp "$TEST_DIR/config.yaml" config/sites/desiderio/config.yaml
  rm -rf "$POPPY_TEST_PUBLIC" "$TEST_DIR"
  ddev exec vendor/bin/typo3 cache:flush >/dev/null
}
trap cleanup EXIT
python3 - <<'PY'
import os
p='config/sites/desiderio/config.yaml';s=open(p).read()
assert '  allowedClients: []' in s, 'Test requires the lab default empty allowlist; will not replace configured clients.'
s=s.replace('  allowedClients: []','  allowedClients:\n    - '+os.environ['POPPY_TEST_CLIENT'])
open(p,'w').write(s)
PY
# Trust the local CA, never disable TLS verification.
export NODE_EXTRA_CA_CERTS="$(mkcert -CAROOT)/rootCA.pem"
node Build/Scripts/test-poppy.mjs prepare
ddev exec vendor/bin/typo3 cache:flush
node Build/Scripts/test-poppy.mjs test
