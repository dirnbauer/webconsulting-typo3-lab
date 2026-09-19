#!/usr/bin/env bash
# Link a development clone under packages/ into vendor/ for fast iteration.
#
#   Build/Scripts/lab-link.sh <composer-name>            symlink vendor/<vendor>/<pkg> -> packages/<clone>
#   Build/Scripts/lab-link.sh --restore <composer-name>  put the dist install back (composer reinstall)
#   Build/Scripts/lab-link.sh --status                   list linked packages (exit 1 if any)
#
# Composer keeps installing every own extension from its GitHub repository, so
# CI and Coolify builds never see a link. Restore before `composer update` or
# before committing composer.lock.
set -euo pipefail
cd "$(dirname "$0")/../.."

# Composer name and the packages/ directory holding its clone, one pair per
# line. An associative array would read better, but macOS still ships bash 3.2,
# where `declare -A` is a syntax error - and `set -u` then turned that into
# "webconsulting: unbound variable", so --status never ran on the host.
CLONES='
webconsulting/typo3-abilities abilities
webconsulting/agent-nexus agent_nexus
webconsulting/typo3-llms-txt llms_txt
webconsulting/skillflow skillflow
webconsulting/visual-editor-enhancements visual_editor_enhancements
webconsulting/desiderio desiderio
webconsulting/innesto innesto
webconsulting/astryx-typo3 astryx_typo3
webconsulting/records-list-types records_list_types
webconsulting/records-list-examples records_list_examples
webconsulting/webcon-easy-workspace webcon_easy_workspace
webconsulting/typo3-shadcn-ui shadcn_ui
'

clone_names() {
  printf '%s\n' "$CLONES" | awk 'NF { print $1 }'
}

clone_for() {
  printf '%s\n' "$CLONES" | awk -v want="$1" '$1 == want { print $2; exit }'
}

install_path() {
  php -r '
    $installed = json_decode(file_get_contents("vendor/composer/installed.json"), true);
    foreach ($installed["packages"] ?? [] as $package) {
      if ($package["name"] === $argv[1]) { echo "vendor/" . ltrim(str_replace("../", "", $package["install-path"]), "/"); exit(0); }
    }
    exit(1);
  ' "$1"
}

status() {
  local linked=0
  for name in $(clone_names); do
    path=$(install_path "$name" 2>/dev/null || true)
    if [ -n "$path" ] && [ -L "$path" ]; then
      printf 'linked   %-44s -> %s\n' "$name" "$(readlink "$path")"; linked=1
    fi
  done
  # Not `[ ... ] && echo`: under `set -e` that list fails as a whole when
  # something is linked, and the function exits before it can return.
  if [ "$linked" -eq 0 ]; then
    echo "no development clones linked"
  fi
  return "$linked"
}

case "${1:-}" in
  --status) status ;;
  --restore)
    name="${2:?composer name required}"
    # Composer removes the link itself. Deleting it first makes `composer
    # reinstall` report the package as not installed and abort, which is how a
    # restore used to fail from inside the web container.
    if ! composer reinstall "$name" --no-interaction; then
      echo "reinstall failed for $name — run 'composer install' to restore every missing vendor directory" >&2
      exit 1
    fi
    composer dump-autoload --no-interaction
    vendor/bin/typo3 cache:flush >/dev/null 2>&1 || true
    echo "restored $name from dist"
    ;;
  "") sed -n '2,9p' "$0"; exit 2 ;;
  *)
    name="$1"; clone=$(clone_for "$name")
    [ -n "$clone" ] || { echo "unknown package $name (add it to CLONES)"; exit 2; }
    [ -f "packages/$clone/composer.json" ] || { echo "packages/$clone is not a checkout"; exit 2; }
    php -r 'exit(json_decode(file_get_contents($argv[1]), true)["name"] === $argv[2] ? 0 : 1);' "packages/$clone/composer.json" "$name" \
      || { echo "packages/$clone/composer.json is not $name"; exit 2; }
    path=$(install_path "$name")
    rm -rf "$path"
    ln -s "../../packages/$clone" "$path"
    composer dump-autoload --no-interaction
    vendor/bin/typo3 cache:flush >/dev/null 2>&1 || true
    echo "linked $name -> packages/$clone (restore with: $0 --restore $name)"
    ;;
esac
