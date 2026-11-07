#!/usr/bin/env bash
# Regular-runtime Visual Editor suite against the local DDEV lab
# (Tests/Integration/VisualEditor, see its README.md).
#
#   Build/Scripts/ve-runtime.sh install [<ref>]  put a VEE candidate into vendor/ (git archive of <ref> from
#                                                packages/visual_editor_enhancements; default feature/1.6-integration)
#   Build/Scripts/ve-runtime.sh restore          put the release from composer.lock back into vendor/
#   Build/Scripts/ve-runtime.sh fixture [<run>]  create the disposable fixture, sessions, copy the state out
#   Build/Scripts/ve-runtime.sh run [args...]    run the Playwright runtime spec (args go to playwright test)
#   Build/Scripts/ve-runtime.sh helper <mode>... run one fixture.php mode in the container
#   Build/Scripts/ve-runtime.sh cleanup          remove the fixture, users, sessions and state; print the proof
#
# The fixture state (var/ve-integration/fixture.json, 0600) is written inside the
# web container: var/ is not part of the Mutagen sync. `fixture` copies it and
# the session files to the same ignored host directory; `cleanup` deletes both
# copies. Generated passwords are never stored or printed - the suite signs in
# with fixated sessions of the disposable users.
#
# `install` refuses while the package is lab-linked (Build/Scripts/lab-link.sh):
# then the linked clone is what runs. Always `restore` afterwards.
set -euo pipefail
cd "$(dirname "$0")/../.."
SUITE="$PWD/Tests/Integration/VisualEditor"
PROJECT=${VEE_DDEV_PROJECT:-webconsulting-typo3-lab}
DESCRIBE=$(ddev describe -j "$PROJECT" 2>/dev/null)
ROOT=$(printf "%s" "$DESCRIBE" | jq -r '.raw.approot')
WEB=$(printf "%s" "$DESCRIBE" | jq -r '.raw.services.web.full_name // "ddev-\(.raw.name)-web"')
PKG=vendor/webconsulting/visual-editor-enhancements
CLONE="$ROOT/packages/visual_editor_enhancements"
STATE="$ROOT/var/ve-integration"
HELPER=/tmp/ve-runtime.php

in_root() { (cd "$ROOT" && "$@"); }
helper() { docker cp "$SUITE/fixture.php" "$WEB:$HELPER" >/dev/null && in_root ddev exec php "$HELPER" "$@"; }

refresh_vendor() {
  # Order matters: Mutagen first (or the package artifact is rebuilt against an
  # empty folder), then the CLI OPcache file cache, then autoload and caches.
  in_root ddev mutagen sync
  in_root ddev exec bash -c 'rm -rf /tmp/opcache/* && composer dump-autoload --no-interaction -q'
  in_root ddev exec bash -c 'grep -c autoload-include vendor/composer/autoload_static.php >/dev/null'
  in_root ddev exec bash -c 'grep -q "visual_editor_enhancements" vendor/typo3/PackageArtifact.php' \
    || { echo "PackageArtifact lost visual_editor_enhancements" >&2; exit 1; }
  in_root ddev exec vendor/bin/typo3 cache:flush
}

archive_into_vendor() {
  local ref=$1
  if [ -L "$ROOT/$PKG" ]; then
    echo "$PKG is lab-linked; the linked clone already runs. Restore the link first." >&2
    exit 1
  fi
  git -C "$CLONE" rev-parse --verify --quiet "$ref^{commit}" >/dev/null || { echo "unknown ref $ref" >&2; exit 1; }
  rm -rf "$ROOT/$PKG"
  mkdir -p "$ROOT/$PKG"
  git -C "$CLONE" archive "$ref" | tar -x -C "$ROOT/$PKG"
  echo "vendor: $ref ($(git -C "$CLONE" rev-parse --short "$ref^{commit}"))"
  refresh_vendor
}

health() {
  local code
  for url in / /typo3/login; do
    code=$(curl -ks -o /dev/null -w '%{http_code}' "https://webconsulting-typo3-lab.ddev.site$url")
    echo "health $url $code"
    [ "$code" = 200 ] || return 1
  done
}

case "${1:-}" in
  install)
    archive_into_vendor "${2:-feature/1.6-integration}"
    health
    ;;
  restore)
    version=$(jq -r '.packages[] | select(.name == "webconsulting/visual-editor-enhancements") | .version' "$ROOT/composer.lock")
    archive_into_vendor "$version"
    health
    ;;
  fixture)
    [ -e "$STATE/fixture.json" ] && { echo "host fixture state exists; run cleanup first" >&2; exit 1; }
    for mode in "fixture ${2:-$(date +%Y%m%d%H%M)}" extra visible localize ve_editor "session admin" "session editor" "session veEditor"; do
      # shellcheck disable=SC2086
      helper $mode
    done
    in_root ddev exec vendor/bin/typo3 cache:flush
    mkdir -p "$STATE" && chmod 700 "$STATE"
    for file in fixture.json state-admin.json state-editor.json state-veEditor.json; do
      (umask 077 && in_root ddev exec cat "var/ve-integration/$file" > "$STATE/$file")
    done
    echo "fixture state copied to $STATE"
    ;;
  helper)
    shift
    helper "$@"
    ;;
  run)
    shift
    [ -s "$STATE/fixture.json" ] || { echo "no fixture; run: $0 fixture" >&2; exit 1; }
    docker cp "$SUITE/fixture.php" "$WEB:$HELPER" >/dev/null
    work=$(mktemp -d /tmp/ve-runtime.XXXXXX)
    trap 'rm -rf "$work"' EXIT
    cp "$SUITE/runtime.spec.js" "$SUITE/playwright.config.mjs" "$work/"
    ln -s "$ROOT/node_modules" "$work/node_modules"
    VEE_RUNTIME_ROOT="$ROOT" VEE_FIXTURE_HELPER="$HELPER" VEE_FIXTURE_SOURCE="$SUITE/fixture.php" VEE_WEB_CONTAINER="$WEB" \
      VEE_DB_PORT=$(printf "%s" "$DESCRIBE" | jq -r '.raw.dbinfo.published_port') \
      VEE_AXE="$ROOT/node_modules/axe-core/axe.min.js" \
      "$ROOT/node_modules/.bin/playwright" test --config="$work/playwright.config.mjs" --output="$work/results" "$@" \
      || { status=$?; cp -R "$work/results" "$STATE/results-$(date +%H%M%S)" 2>/dev/null || true; exit $status; }
    ;;
  cleanup)
    helper cleanup
    in_root ddev exec bash -c 'rm -rf var/ve-integration /tmp/ve-runtime.php'
    rm -rf "$STATE"
    in_root ddev exec vendor/bin/typo3 cache:flush
    health
    ;;
  *)
    sed -n '2,20p' "$0" >&2
    exit 2
    ;;
esac
