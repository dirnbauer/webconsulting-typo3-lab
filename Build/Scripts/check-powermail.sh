#!/usr/bin/env bash
#
# ddev check-powermail: the one local proof, before a merge, of what GitHub CI
# cannot check. Powermail 14 and Powermail Conditions 14 come from in2code's
# Early Access Packagist; its token lives only in Coolify and in
# ~/.config/in2code/eap-packagist-token on this machine, so CI drops both
# packages (Build/Scripts/ci-drop-private-packages.php). This script checks,
# against the DDEV instance of the checkout it runs in:
#
#   1. patch declarations: every patch is locked with its sha256 and is a
#      well-formed diff (the static guard CI runs too)
#   2. composer validate
#   3. the installed in2code packages are exactly the ones composer.lock names
#   4. a fresh reinstall from in2code applies all five lab patches (Composer
#      aborts on a patch that does not apply), then dump-autoload
#   5. the installed code contains every patch (un-patched in a scratch copy)
#   6. the lab's unit tests and webcon_jev's unit tests (at the locked commit)
#      against this vendor/
#   7. the Powermail, Conditions and Jev journeys in Playwright
#   8. composer audit, including the two in2code packages
#   9. no file holds the token afterwards
#
# It prints one PASS/FAIL summary and exits 0 only when everything passed.
# A full pass is recorded (by content fingerprint) for the opt-in pre-push
# hook, see Build/Scripts/install-hooks.sh.
#
# The token is read from the file by the shell, kept in a subshell's variable
# and handed to `docker exec -e COMPOSER_AUTH` for the Composer steps only.
# It is never an argument of any process, never written to a file, and every
# other tool (PHPUnit, Playwright, PHP, git) runs without it.
#
# Usage: ddev check-powermail [--no-reinstall] [--no-e2e] [--full-token-scan]
#        Build/Scripts/check-powermail.sh […]   (same, from the project root)

# The step functions are called through run_step, which shellcheck cannot follow.
# shellcheck disable=SC2317
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage: ddev check-powermail [--no-reinstall] [--no-e2e] [--full-token-scan]

  --no-reinstall     skip the fresh reinstall from in2code (offline; proves less)
  --no-e2e           skip the Playwright journeys
  --full-token-scan  scan the whole project directory for the token (slow)

Only a run without --no-reinstall and --no-e2e counts for the pre-push hook.
Environment: IN2CODE_PACKAGIST_TOKEN_FILE (default ~/.config/in2code/eap-packagist-token),
PLAYWRIGHT_HTTP_CREDENTIALS_FILE (passed on to Playwright),
CHECK_POWERMAIL_LOCK (container path of a flock file; default var/lock/rehearsal.lock if it exists).
USAGE
}

# Never in CI: CI has no token by decision, and this rewrites vendor/.
if [[ "${GITHUB_ACTIONS:-}" == "true" || -n "${GITLAB_CI:-}" || ( -n "${CI:-}" && "${CI}" != "false" && "${CI}" != "0" ) ]]; then
  echo "check-powermail: refusing to run in CI." >&2
  echo "The in2code token exists only in Coolify and on the developer's machine, never in CI (owner decision 2026-11-21)." >&2
  echo "Run 'ddev check-powermail' locally before merging; CI covers the rest without Powermail (docs/coolify-deployment.md)." >&2
  exit 2
fi

reinstall=1
e2e=1
full_scan=0
for argument in "$@"; do
  case "${argument}" in
    --no-reinstall) reinstall=0 ;;
    --no-e2e) e2e=0 ;;
    --full-token-scan) full_scan=1 ;;
    -h|--help) usage; exit 0 ;;
    *) usage >&2; exit 2 ;;
  esac
done

# A caller's COMPOSER_AUTH must not leak into Playwright, PHP or git.
unset COMPOSER_AUTH

ROOT="${DDEV_APPROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
cd "${ROOT}"
# shellcheck source=Build/Scripts/lib/powermail-check-paths.sh
source "${ROOT}/Build/Scripts/lib/powermail-check-paths.sh"

token_file="${IN2CODE_PACKAGIST_TOKEN_FILE:-${HOME}/.config/in2code/eap-packagist-token}"
work_dir="var/check-powermail"
started="$(date +%s)"

fatal() {
  echo "check-powermail: $*" >&2
  echo "RESULT: FAIL (the check could not start)" >&2
  exit 1
}

# --- Preconditions -----------------------------------------------------------

command -v docker >/dev/null || fatal "docker is not available."
if [[ -n "${DDEV_SITENAME:-}" ]]; then
  site_name="${DDEV_SITENAME}"
  primary_url="${DDEV_PRIMARY_URL:-https://${DDEV_SITENAME}.ddev.site}"
else
  if ! command -v ddev >/dev/null || ! command -v jq >/dev/null; then
    fatal "run it as 'ddev check-powermail', or install ddev and jq."
  fi
  description="$(ddev describe -j)" || fatal "ddev describe failed in ${ROOT}."
  site_name="$(jq -r .raw.name <<<"${description}")"
  primary_url="$(jq -r .raw.primary_url <<<"${description}")"
fi
web="ddev-${site_name}-web"
[[ "$(docker inspect -f '{{.State.Running}}' "${web}" 2>/dev/null)" == "true" ]] \
  || fatal "the web container ${web} is not running (ddev start)."

[[ -s "${token_file}" ]] || fatal "no in2code token file at ${token_file} (docs/coolify-deployment.md)."
if [[ -n "$(find "${token_file}" -prune \( -perm -004 -o -perm -040 \) 2>/dev/null)" ]]; then
  fatal "${token_file} is readable by others: chmod 600 it."
fi
case "${token_file}" in
  "${ROOT}"/*) fatal "the token file must live outside the project (${token_file})." ;;
esac

uid_gid="$(id -u):$(id -g)"
in_web() {
  docker exec -u "${uid_gid}" -w /var/www/html "${web}" "$@"
}

# Composer with the token, and only Composer. The subshell keeps the variable
# away from everything after it; docker exec -e copies the value from this
# environment, so it is never on a command line.
composer_with_token() (
  local token
  token="$(tr -d '\r\n' < "${token_file}")"
  if ! [[ "${token}" =~ ^[A-Za-z0-9._~-]+$ ]]; then
    echo "The token in ${token_file} has unexpected characters." >&2
    return 1
  fi
  # A JSON document, the quotes are meant literally.
  # shellcheck disable=SC2089,SC2090
  COMPOSER_AUTH="{\"http-basic\":{\"in2code.repo.packagist.com\":{\"username\":\"token\",\"password\":\"${token}\"}}}"
  unset token
  # shellcheck disable=SC2090
  export COMPOSER_AUTH
  docker exec -e COMPOSER_AUTH -e COMPOSER_NO_INTERACTION=1 -e COMPOSER_DISCARD_CHANGES=true \
    -u "${uid_gid}" -w /var/www/html "${web}" ${lock_prefix[@]+"${lock_prefix[@]}"} composer "$@"
)

# Shared rehearsal instances serialise vendor and cache writes with flock.
lock_prefix=()
lock_file="${CHECK_POWERMAIL_LOCK:-var/lock/rehearsal.lock}"
if [[ -f "${ROOT}/${lock_file}" ]] || [[ -n "${CHECK_POWERMAIL_LOCK:-}" ]]; then
  lock_prefix=(flock "${lock_file}")
fi

rm -rf "${work_dir}"
mkdir -p "${work_dir}"

# --- Steps -------------------------------------------------------------------

results=()
failed=0
blocked=""   # set when a later step would only repeat an earlier failure

record() { # <PASS|FAIL|SKIP> <name> [detail]
  results+=("$(printf '%-4s  %s%s' "$1" "$2" "${3:+  ($3)}")")
  if [[ "$1" == "FAIL" ]]; then failed=1; fi
}

run_step() { # <name> <function>
  local name="$1" fn="$2" rc=0
  printf '\n==> %s\n' "${name}"
  if [[ -n "${blocked}" ]]; then
    record SKIP "${name}" "${blocked}"
    return 0
  fi
  "${fn}" || rc=$?
  if [[ ${rc} -eq 0 ]]; then record PASS "${name}"; else record FAIL "${name}"; fi
  return 0
}

step_declarations() {
  in_web php Build/Scripts/check-patch-declarations.php
}

step_validate() {
  in_web composer validate --strict --no-check-publish --no-interaction
}

step_installed() {
  if ! in_web php Build/Scripts/check-powermail.php installed; then
    blocked="the installed packages differ from composer.lock"
    return 1
  fi
}

vendor_digest() {
  in_web sh -c 'cd vendor/in2code && find . -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | cut -c1-16'
}

step_reinstall() {
  local before after
  before="$(vendor_digest)" || return 1
  if ! composer_with_token reinstall in2code/powermail in2code/powermail_cond --no-progress; then
    echo "Composer could not reinstall the in2code packages with their patches: a patch that no longer applies aborts here, as it aborts the Coolify build." >&2
    blocked="the reinstall failed"
    return 1
  fi
  # reinstall rewrites vendor/composer/autoload_files.php without TYPO3's
  # autoload-include.php (TYPO3 then looks for vendor/typo3/sysext/*/).
  in_web ${lock_prefix[@]+"${lock_prefix[@]}"} composer dump-autoload --no-interaction || return 1
  if ! in_web grep -q 'autoload-include.php' vendor/composer/autoload_files.php; then
    echo "vendor/composer/autoload_files.php lacks TYPO3's autoload-include.php after dump-autoload." >&2
    return 1
  fi
  in_web bash -c 'rm -rf /tmp/opcache/* 2>/dev/null' || true
  after="$(vendor_digest)" || return 1
  if [[ "${before}" != "${after}" ]]; then
    echo "The installed in2code code differed from a clean install with the patches; it is clean now."
  else
    echo "The installed code was already identical to a clean install with the patches."
  fi
  # Always flush: a request that arrived while the packages were briefly
  # absent can have cached a configuration without Powermail ("The default
  # controller for extension Powermail and plugin Pi1 can not be determined").
  in_web ${lock_prefix[@]+"${lock_prefix[@]}"} vendor/bin/typo3 cache:flush || return 1
}

step_patches() {
  in_web php Build/Scripts/check-powermail.php patches
}

step_audit() {
  composer_with_token audit --no-interaction
}

step_unit_lab() {
  # The lab's unit tests that touch Powermail (and this check's own logic).
  # The whole suite is the quality suite's job (runTests.sh -s unit, also in CI);
  # an unrelated test that is red there must not hide the Powermail result here.
  local file files=()
  while IFS= read -r file; do
    files+=("${file}")
  done < <(grep -rliE 'powermail' --include='*Test.php' packages/site_package/Tests/Unit Tests/Unit | LC_ALL=C sort)
  [[ ${#files[@]} -gt 0 ]] || { echo "No unit test mentions Powermail." >&2; return 1; }
  {
    echo '<?xml version="1.0" encoding="UTF-8"?>'
    echo '<phpunit bootstrap="../../vendor/autoload.php" cacheDirectory="phpunit-cache" failOnWarning="true" failOnRisky="true">'
    echo '    <testsuites>'
    echo '        <testsuite name="Lab unit tests that touch Powermail">'
    for file in "${files[@]}"; do
      echo "            <file>../../${file}</file>"
    done
    echo '        </testsuite>'
    echo '    </testsuites>'
    echo '</phpunit>'
  } > "${work_dir}/lab-phpunit.xml"
  printf '  %s\n' "${files[@]}"
  in_web vendor/bin/phpunit --configuration="${work_dir}/lab-phpunit.xml" --no-progress
}

step_unit_jev() {
  local source url reference clone index
  source="$(in_web php Build/Scripts/check-powermail.php jev-source)" || return 1
  url="${source% *}"
  reference="${source#* }"
  # A local clone that has the commit saves a download; otherwise a partial
  # bare clone of the public repository is kept in var/ for the next run.
  clone=""
  for candidate in "${WEBCON_JEV_CLONE:-}" "${ROOT}/packages/webcon_jev"; do
    if [[ -n "${candidate}" ]] && git -C "${candidate}" cat-file -e "${reference}^{commit}" 2>/dev/null; then
      clone="${candidate}"
      break
    fi
  done
  if [[ -z "${clone}" ]]; then
    clone="${ROOT}/var/check-powermail-cache/webcon-jev.git"
    if [[ ! -d "${clone}" ]]; then
      git clone --quiet --bare --filter=blob:none "${url}" "${clone}" || return 1
    fi
    git -C "${clone}" cat-file -e "${reference}^{commit}" 2>/dev/null \
      || git -C "${clone}" fetch --quiet origin "${reference}" || return 1
  fi
  # The dist archive leaves Tests/ out (export-ignore), so read the commit
  # through a temporary index: the clone's own index and files stay untouched.
  local git_dir
  git_dir="$(git -C "${clone}" rev-parse --absolute-git-dir)" || return 1
  index="${ROOT}/${work_dir}/jev.index"
  mkdir -p "${ROOT}/${work_dir}/jev"
  GIT_INDEX_FILE="${index}" git --git-dir="${git_dir}" --work-tree="${ROOT}/${work_dir}/jev" read-tree "${reference}" || return 1
  GIT_INDEX_FILE="${index}" git --git-dir="${git_dir}" --work-tree="${ROOT}/${work_dir}/jev" checkout-index --all --force || return 1
  rm -f "${index}"
  cat > "${work_dir}/jev-bootstrap.php" <<'PHP'
<?php
$loader = require dirname(__DIR__, 2) . '/vendor/autoload.php';
$loader->addPsr4('Webconsulting\\WebconJev\\Tests\\', __DIR__ . '/jev/Tests/');
PHP
  cat > "${work_dir}/jev-phpunit.xml" <<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="jev-bootstrap.php" cacheDirectory="phpunit-cache" failOnWarning="true" failOnRisky="true">
    <testsuites>
        <testsuite name="webcon_jev unit tests against the lab's Powermail">
            <directory>jev/Tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
XML
  echo "webcon_jev ${reference:0:10} (${clone})"
  in_web vendor/bin/phpunit --configuration="${work_dir}/jev-phpunit.xml" --no-progress
}

step_e2e() {
  local rc=0
  export PLAYWRIGHT_BROWSERS_PATH="${PLAYWRIGHT_BROWSERS_PATH:-var/playwright/browsers}"
  npx --no-install playwright install chromium >/dev/null || return 1
  echo "Against ${primary_url}"
  PLAYWRIGHT_BASE_URL="${primary_url}" npx --no-install playwright test \
    Tests/E2E/powermail.spec.js Tests/E2E/frontend.spec.js \
    --grep 'Powermail' --reporter=line --output="${work_dir}/playwright" || rc=$?
  if [[ ${rc} -eq 0 ]]; then
    rm -rf "${work_dir}/playwright"
  else
    echo "Traces and screenshots: ${work_dir}/playwright (delete them when done)." >&2
  fi
  return "${rc}"
}

step_token_scan() {
  local found_host found_container rc=0
  # Text files where a token could land: var/ (PHPStan's container, logs,
  # caches), Composer's state, configuration, and every working-tree file git
  # sees. Large binary trees (browsers, DB snapshots, node_modules) are skipped
  # unless --full-token-scan.
  if [[ ${full_scan} -eq 1 ]]; then
    found_host="$(tr -d '\r\n' < "${token_file}" | php Build/Scripts/check-powermail.php scan-token "${ROOT}")" || rc=$?
  else
    found_host="$(tr -d '\r\n' < "${token_file}" | php Build/Scripts/check-powermail.php scan-token --git-files --root="${ROOT}" \
      "${ROOT}/var" "${ROOT}/vendor/composer" "${ROOT}/vendor/in2code" "${ROOT}/config" "${ROOT}/.ddev" \
      "${ROOT}/auth.json" "${ROOT}/composer.lock" "${ROOT}/public/typo3temp" "${ROOT}/public/fileadmin/_temp_")" || rc=$?
  fi
  # Inside the container: temp files, Composer's home and repository cache.
  found_container="$(tr -d '\r\n' < "${token_file}" | docker exec -i -u "${uid_gid}" -w /var/www/html "${web}" \
    sh -c 'php Build/Scripts/check-powermail.php scan-token /tmp "${HOME}/.composer" "$(composer config --global cache-dir 2>/dev/null)/repo"')" || rc=$?
  if [[ -n "${found_host}${found_container}" ]]; then
    echo "THE TOKEN IS IN THESE FILES; delete them now and ask in2code for a new token if any was shared:" >&2
    [[ -n "${found_host}" ]] && printf '  %s\n' "${found_host}" >&2
    [[ -n "${found_container}" ]] && printf '  (container) %s\n' "${found_container}" >&2
    return 1
  fi
  if [[ ${rc} -ne 0 ]]; then
    echo "The token scan did not finish (exit ${rc})." >&2
    return 1
  fi
  echo "No file in the project, var/, Composer's state or the container's temp and Composer directories contains the token."
}

run_step "Patch declarations (static, same as CI)" step_declarations
run_step "composer validate" step_validate
run_step "Installed in2code packages match composer.lock" step_installed
if [[ ${reinstall} -eq 1 ]]; then
  run_step "Reinstall from in2code: all patches apply, dump-autoload" step_reinstall
else
  record SKIP "Reinstall from in2code" "--no-reinstall"
fi
run_step "Installed code contains every lab patch" step_patches
if [[ -z "${blocked}" && ${failed} -eq 1 ]]; then
  blocked="the install is not proven"
fi
run_step "Unit tests: lab and site_package tests that touch Powermail" step_unit_lab
run_step "Unit tests: webcon_jev at the locked commit" step_unit_jev
if [[ ${e2e} -eq 1 ]]; then
  run_step "E2E: Powermail, Conditions and Jev journeys" step_e2e
else
  record SKIP "E2E: Powermail, Conditions and Jev journeys" "--no-e2e"
fi
blocked=""
run_step "composer audit (with the in2code packages)" step_audit
run_step "No file contains the token" step_token_scan

rm -rf "${work_dir}/jev" "${work_dir}/phpunit-cache" "${work_dir}/jev-bootstrap.php" "${work_dir}/jev-phpunit.xml" "${work_dir}/lab-phpunit.xml"

# --- Summary -----------------------------------------------------------------

elapsed=$(( $(date +%s) - started ))
printf '\n==== Powermail check: %s (%s, %ds) ====\n' "${web}" "${primary_url}" "${elapsed}"
printf '%s\n' "${results[@]}"
if [[ ${failed} -eq 0 ]]; then
  if [[ ${reinstall} -eq 1 && ${e2e} -eq 1 ]]; then
    if stamp="$(powermail_check_stamp_file "${ROOT}")"; then
      {
        echo "fingerprint $(powermail_check_fingerprint_worktree "${ROOT}")"
        echo "date $(date -u +%Y-%m-%dT%H:%M:%SZ)"
        echo "instance ${primary_url}"
      } > "${stamp}"
      echo "Recorded for the pre-push hook: ${stamp}"
    fi
    echo "RESULT: PASS"
  else
    echo "RESULT: PASS (partial: not recorded for the pre-push hook)"
  fi
  exit 0
fi
echo "RESULT: FAIL"
exit 1
