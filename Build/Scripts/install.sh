#!/usr/bin/env bash
# Set up the Webconsulting TYPO3 Lab from the published snapshot.
#
#   ./install.sh [target-directory]        # default: webconsulting-typo3-lab
#
# Clones the repository, fetches the sanitised database and Fileadmin pair,
# and runs the documented DDEV bootstrap. Needs Docker or OrbStack, DDEV
# >= 1.25.3 and Git.
#
# The lab sits behind HTTP basic auth, so the downloads need the same
# credentials you used to reach this script. Either export them:
#
#   export LAB_USER=... LAB_PASSWORD=...
#
# or let the script prompt. Nothing is written to disk or the shell history.
set -euo pipefail

BASE="https://typo3-lab.webconsulting.at/fileadmin/_downloads"
REPO="https://github.com/dirnbauer/webconsulting-typo3-lab.git"
target="${1:-webconsulting-typo3-lab}"

for tool in git ddev curl; do
  command -v "$tool" >/dev/null || { echo "$tool is required but not installed" >&2; exit 1; }
done

if [ -e "$target" ]; then
  echo "$target already exists - pass a different directory" >&2
  exit 1
fi

echo "==> cloning into $target"
git clone --depth 1 "$REPO" "$target"
cd "$target"

cp config/system/settings.php.example config/system/settings.php

echo "==> downloading the snapshot pair"
if [ -z "${LAB_USER:-}" ]; then
  printf 'lab username: '
  read -r LAB_USER
fi
if [ -z "${LAB_PASSWORD:-}" ]; then
  printf 'lab password: '
  stty -echo 2>/dev/null || true
  read -r LAB_PASSWORD
  stty echo 2>/dev/null || true
  printf '\n'
fi

# Credentials go through a file descriptor, never the command line, so they do
# not show up in `ps` for every other user on the machine.
fetch() {
  curl -fL --progress-bar --netrc-file <(printf 'machine %s login %s password %s\n' \
    "$(printf '%s' "$BASE" | awk -F/ '{print $3}')" "$LAB_USER" "$LAB_PASSWORD") \
    -o "$1" "$2"
}

fetch db-public.sql.gz "$BASE/db-public.sql.gz"
mkdir -p .tarballs
fetch .tarballs/fileadmin-public.tar.gz "$BASE/fileadmin-public.tar.gz"

echo "==> starting DDEV"
ddev start
ddev composer install
ddev npm ci

echo "==> importing"
ddev import-db --file=db-public.sql.gz
ddev import-files --source=.tarballs/fileadmin-public.tar.gz

ddev typo3 extension:setup
ddev vite build
ddev typo3 cache:flush

cat <<'EOF'

Done.

  Frontend  https://webconsulting-typo3-lab.ddev.site
  Backend   https://webconsulting-typo3-lab.ddev.site/typo3/
  Sign in   admin / Demo123*   <- change this

The published database is sanitised: Vault secrets, API and MCP tokens, OAuth
rows, WorkOS identities and every other account were stripped before export,
so integrations that need credentials stay switched off until you supply your
own through the Vault backend module.
EOF
