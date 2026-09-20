#!/usr/bin/env bash

set -euo pipefail

REMOTE_HOST="${TYPO3_LAB_REMOTE_HOST:-root@49.13.173.37}"
STACK_LABEL="at.webconsulting.stack=typo3-lab"
BACKUP_ROOT="${TYPO3_LAB_BACKUP_ROOT:-${PWD}/.tarballs/coolify-sync}"
SSH_OPTIONS=(-o BatchMode=yes -o ConnectTimeout=15)
SYNC_WORK_DIR=""

# What counts as sensitive, and what the published files are called, is shared
# with make-public-snapshot.sh so the local and remote snapshots cannot drift.
. "$(dirname "$0")/lib/public-snapshot.sh"

cleanup() {
    if [[ -n "${SYNC_WORK_DIR}" && -d "${SYNC_WORK_DIR}" ]]; then
        rm -rf -- "${SYNC_WORK_DIR}"
    fi
}

trap cleanup EXIT

usage() {
    cat <<'USAGE'
Usage: Build/Scripts/sync-coolify.sh <status|deploy|publish-snapshot|publish-downloads-page|push|pull> [--confirm|--force]

  status            Show the local DDEV and remote Coolify container state.
  deploy            Ask Coolify to rebuild and redeploy the application from
                    the current main branch, then wait for the new containers.
                    Code only: it does not touch the database or fileadmin.
                    Refuses unless the Quality run for origin/main is green;
                    'deploy --force' skips that check.
  publish-snapshot --confirm
                    Build the sanitised download snapshot from the LIVE
                    database and fileadmin and place it in the site's
                    fileadmin/_downloads. Reads production and writes only
                    inside that directory; it changes no existing content.
  publish-downloads-page --confirm
                    Create or update the /downloads/ page on the live site from
                    Build/Scripts/lib/downloads-page.json. The page is a
                    database record, so neither deploy nor publish-snapshot
                    carries it over. Matched by slug, so re-running updates
                    rather than duplicating.
  push --confirm    Back up Coolify, then replace its database and fileadmin
                    with exports from this DDEV project.
  pull --confirm    Back up DDEV, then replace its database and fileadmin
                    with exports from Coolify.

Coolify does not deploy when this repository is pushed: there is no webhook
and no deploy key, so the running image stays on whichever commit was last
deployed by hand. "deploy" is that hand step, made repeatable.

Environment:
  TYPO3_LAB_REMOTE_HOST     SSH target (default: root@49.13.173.37)
  TYPO3_LAB_BACKUP_ROOT     Local backup directory
  COOLIFY_URL               Coolify base URL (default: https://coolify.webconsulting.at)
  COOLIFY_APP_UUID          Application UUID (default: kj8tirxaijsk4cmzevgaphvr)
  COOLIFY_TOKEN_FILE        File holding the API token (default: ~/.config/coolify-token)
  COOLIFY_TOKEN             The API token itself, if you prefer not to use a file
USAGE
}

require_confirmation() {
    if [[ "${2:-}" != "--confirm" ]]; then
        echo "Refusing to replace data without --confirm." >&2
        usage >&2
        exit 2
    fi
}

remote_container() {
    local service="$1"
    local containers

    containers="$(ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        docker ps \
        --filter "label=${STACK_LABEL}" \
        --filter "label=com.docker.compose.service=${service}" \
        --format '{{.Names}}')"

    if [[ "$(printf '%s\n' "${containers}" | sed '/^$/d' | wc -l | tr -d ' ')" != "1" ]]; then
        echo "Expected exactly one running ${service} container; found: ${containers:-none}" >&2
        exit 1
    fi

    if [[ ! "${containers}" =~ ^[a-zA-Z0-9_.-]+$ ]]; then
        echo "Refusing unexpected container name: ${containers}" >&2
        exit 1
    fi

    printf '%s' "${containers}"
}

remote_backup() {
    local timestamp="$1"
    local database_container="$2"
    local web_container="$3"
    local remote_backup_dir="/var/backups/typo3-lab/${timestamp}"

    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "install -d -m 0700 '${remote_backup_dir}'"

    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "docker exec '${database_container}' sh -c 'MYSQL_PWD=\"\$MARIADB_PASSWORD\" exec mariadb-dump --single-transaction --quick --skip-lock-tables -u\"\$MARIADB_USER\" \"\$MARIADB_DATABASE\"' | gzip -1 > '${remote_backup_dir}/database.sql.gz'"

    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "docker exec '${web_container}' tar -czf - -C /var/www/html/public fileadmin > '${remote_backup_dir}/fileadmin.tar.gz'"

    echo "Remote backup: ${REMOTE_HOST}:${remote_backup_dir}"
}

status() {
    echo "Local DDEV:"
    ddev describe
    echo
    echo "Remote Coolify containers:"
    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "docker ps --filter 'label=${STACK_LABEL}' --format 'table {{.Names}}\t{{.Status}}\t{{.Image}}'"
}

push_to_coolify() {
    local timestamp
    local remote_stage
    local database_container
    local web_container

    timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
    SYNC_WORK_DIR="$(mktemp -d)"
    remote_stage="/var/tmp/typo3-lab-sync-${timestamp}"

    ddev start
    database_container="$(remote_container database)"
    web_container="$(remote_container web)"

    echo "Exporting DDEV database..."
    ddev export-db --file="${SYNC_WORK_DIR}/database.sql.gz"
    echo "Archiving DDEV fileadmin..."
    COPYFILE_DISABLE=1 tar --no-xattrs -czf "${SYNC_WORK_DIR}/fileadmin.tar.gz" -C public fileadmin
    ddev exec php -r 'echo (require "/var/www/html/config/system/settings.php")["SYS"]["encryptionKey"];' \
        > "${SYNC_WORK_DIR}/encryption-key"
    chmod 0600 "${SYNC_WORK_DIR}/encryption-key"

    remote_backup "${timestamp}" "${database_container}" "${web_container}"

    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" "install -d -m 0700 '${remote_stage}'"
    scp "${SSH_OPTIONS[@]}" \
        "${SYNC_WORK_DIR}/database.sql.gz" \
        "${SYNC_WORK_DIR}/fileadmin.tar.gz" \
        "${REMOTE_HOST}:${remote_stage}/"

    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "docker exec -i '${web_container}' sh -c 'umask 077; cat > /run/typo3-secrets/encryption-key'" \
        < "${SYNC_WORK_DIR}/encryption-key"

    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "docker exec '${database_container}' sh -c 'MYSQL_PWD=\"\$MARIADB_ROOT_PASSWORD\" mariadb -uroot -e \"DROP DATABASE IF EXISTS \\\`\$MARIADB_DATABASE\\\`; CREATE DATABASE \\\`\$MARIADB_DATABASE\\\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON \\\`\$MARIADB_DATABASE\\\`.* TO \\\"\$MARIADB_USER\\\"@\\\"%\\\";\"'"

    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "gzip -dc '${remote_stage}/database.sql.gz' | docker exec -i '${database_container}' sh -c 'MYSQL_PWD=\"\$MARIADB_PASSWORD\" exec mariadb -u\"\$MARIADB_USER\" \"\$MARIADB_DATABASE\"'"

    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "docker exec '${web_container}' sh -c 'test -d /var/www/html/public/fileadmin && find /var/www/html/public/fileadmin -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +'"
    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "gzip -dc '${remote_stage}/fileadmin.tar.gz' | docker exec -i '${web_container}' tar -xf - -C /var/www/html/public"
    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "docker exec '${web_container}' chown -R www-data:www-data /var/www/html/public/fileadmin /var/www/html/var"

    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "docker exec -u www-data '${web_container}' vendor/bin/typo3 extension:setup"
    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "docker exec -u www-data '${web_container}' vendor/bin/typo3 cache:flush"
    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "docker exec -u www-data '${web_container}' vendor/bin/typo3 cache:warmup"

    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" "rm -rf '${remote_stage}'"
    echo "Push completed. Coolify now contains the DDEV database and fileadmin."
}

pull_from_coolify() {
    local timestamp
    local local_backup_dir
    local database_container
    local web_container

    timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
    SYNC_WORK_DIR="$(mktemp -d)"
    local_backup_dir="${BACKUP_ROOT}/${timestamp}"

    ddev start
    database_container="$(remote_container database)"
    web_container="$(remote_container web)"

    mkdir -p "${local_backup_dir}"
    ddev snapshot --name="pre-coolify-pull-${timestamp}"
    COPYFILE_DISABLE=1 tar --no-xattrs -czf "${local_backup_dir}/fileadmin.tar.gz" -C public fileadmin

    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "docker exec '${database_container}' sh -c 'MYSQL_PWD=\"\$MARIADB_PASSWORD\" exec mariadb-dump --single-transaction --quick --skip-lock-tables -u\"\$MARIADB_USER\" \"\$MARIADB_DATABASE\"' | gzip -1" \
        > "${SYNC_WORK_DIR}/database.sql.gz"
    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "docker exec '${web_container}' tar -czf - -C /var/www/html/public fileadmin" \
        > "${SYNC_WORK_DIR}/fileadmin.tar.gz"

    ddev import-db --file="${SYNC_WORK_DIR}/database.sql.gz"
    find public/fileadmin -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +
    tar -xzf "${SYNC_WORK_DIR}/fileadmin.tar.gz" -C public
    ddev exec vendor/bin/typo3 cache:flush

    echo "Pull completed. Local file backup: ${local_backup_dir}/fileadmin.tar.gz"
    echo "Local database backup: DDEV snapshot pre-coolify-pull-${timestamp}"
}

coolify_token() {
    if [[ -n "${COOLIFY_TOKEN:-}" ]]; then
        printf '%s' "${COOLIFY_TOKEN}"
        return 0
    fi

    local token_file="${COOLIFY_TOKEN_FILE:-${HOME}/.config/coolify-token}"
    if [[ ! -r "${token_file}" ]]; then
        cat >&2 <<HINT
No Coolify API token found.

Create one at ${COOLIFY_URL:-https://coolify.webconsulting.at}/security/api-tokens
with a permission that allows deployments, then store it readable only by you:

    install -m 600 /dev/null "${token_file}"
    printf '%s' '<token>' > "${token_file}"

Or export COOLIFY_TOKEN for a single run.
HINT
        return 1
    fi

    # A token pasted into a file usually arrives with a trailing newline.
    tr -d '\r\n' < "${token_file}"
}

deploy() {
    local base_url="${COOLIFY_URL:-https://coolify.webconsulting.at}"
    local app_uuid="${COOLIFY_APP_UUID:-kj8tirxaijsk4cmzevgaphvr}"
    local token status body

    token="$(coolify_token)" || exit 2

    # Coolify builds whatever origin/main is, so that is the commit to judge.
    # The CI deploy job used to be this gate; Coolify's IP allowlist refuses
    # GitHub's runners, so every deployment comes through here instead, and on
    # 2026-09-19 a commit with a red run went live because nothing checked.
    if [[ "${1:-}" != "--force" ]]; then
        require_green_ci
    fi

    local before
    before="$(remote_image_tag || true)"
    echo "Currently deployed: ${before:-unknown}"

    # POST, not GET: Coolify moved this endpoint and answers 405 on a GET.
    body="$(curl -sS -w $'\n%{http_code}' \
        --max-time 60 \
        -X POST \
        -H "Authorization: Bearer ${token}" \
        "${base_url}/api/v1/deploy?uuid=${app_uuid}")" || {
        echo "Could not reach ${base_url}." >&2
        exit 1
    }
    status="${body##*$'\n'}"
    body="${body%$'\n'*}"

    if [[ "${status}" == "401" ]]; then
        echo "Coolify rejected the token. Create a new one and try again." >&2
        exit 1
    fi
    if [[ "${status}" == "403" ]]; then
        # The token is valid but was created without "deploy". Coolify's
        # permissions are granular (read, read:sensitive, write,
        # write:sensitive, deploy, root) and default to the read ones, so a
        # token made for this ends up unable to do it.
        echo "Coolify refused the call: ${body}" >&2
        if [[ "${body}" == *"not allowed to access the API"* ]]; then
            # Coolify's IP allowlist, not the token: this machine's address is
            # not on Settings > Configuration > Allowed IPs.
            echo "This machine's IP is not on Coolify's API allowlist; the token is not the problem." >&2
        else
            echo "Create the token with the \"deploy\" permission at ${base_url}/security/api-tokens." >&2
        fi
        exit 1
    fi
    if [[ "${status}" != "200" && "${status}" != "201" ]]; then
        echo "Coolify answered ${status}: ${body}" >&2
        exit 1
    fi

    echo "${body}"

    local deployment_uuid
    deployment_uuid="$(printf '%s' "${body}" | sed -n 's/.*"deployment_uuid":"\([^"]*\)".*/\1/p')"
    if [[ -z "${deployment_uuid}" ]]; then
        echo "Deployment queued. Watch it at ${base_url}, or re-run 'status' in a few minutes."
        return 0
    fi

    # A queued deployment is not a deployed site: on 2026-09-20 one failed
    # because the host rebooted mid-build, and the old image kept serving.
    echo "Waiting for Coolify to finish ${deployment_uuid} ..."
    local state="" waited=0
    while (( waited < 1800 )); do
        state="$(curl -sS --max-time 30 -H "Authorization: Bearer ${token}" \
            "${base_url}/api/v1/deployments/${deployment_uuid}" \
            | sed -n 's/.*"status":"\([^"]*\)".*/\1/p' | head -1)" || state=""
        case "${state}" in
            finished) break ;;
            failed|cancelled-by-user|cancelled)
                echo "Coolify reports the deployment as ${state}; the previous image keeps serving." >&2
                exit 1
                ;;
        esac
        sleep 20
        waited=$(( waited + 20 ))
    done
    if [[ "${state}" != "finished" ]]; then
        echo "Gave up after ${waited}s with the deployment still '${state:-unknown}'." >&2
        exit 1
    fi

    # 401 is the healthy answer: the lab sits behind basic auth. The entrypoint
    # runs migrations before Apache starts, so the edge answers 503 for a while.
    local edge="" tries=0
    while (( tries < 30 )); do
        edge="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "https://typo3-lab.webconsulting.at/" || true)"
        [[ "${edge}" == "401" || "${edge}" == "200" ]] && break
        sleep 20
        tries=$(( tries + 1 ))
    done
    echo "Deployed: $(remote_image_tag || echo unknown) — edge answers ${edge}."
    [[ "${edge}" == "401" || "${edge}" == "200" ]] || exit 1
}

require_green_ci() {
    if ! command -v gh >/dev/null 2>&1; then
        echo "gh is not installed, so the Quality run cannot be checked. Use 'deploy --force' to deploy anyway." >&2
        exit 1
    fi
    local repo="${GITHUB_REPOSITORY:-dirnbauer/webconsulting-typo3-lab}" sha verdict
    sha="$(git ls-remote origin refs/heads/main | cut -f1)"
    verdict="$(gh run list -R "${repo}" --workflow Quality --branch main --limit 20 \
        --json headSha,status,conclusion \
        --jq "[.[] | select(.headSha == \"${sha}\")][0] | \"\(.status) \(.conclusion)\"" 2>/dev/null || true)"
    case "${verdict}" in
        "completed success")
            echo "Quality is green for ${sha:0:7}."
            ;;
        ""|"null null")
            echo "No Quality run found for ${sha:0:7} on origin/main. Push first, or use 'deploy --force'." >&2
            exit 1
            ;;
        *)
            echo "Quality for ${sha:0:7} is '${verdict}', not green. Wait for it, fix it, or use 'deploy --force'." >&2
            exit 1
            ;;
    esac
}

remote_image_tag() {
    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "docker ps --filter 'name=^web-' --format '{{.Image}}' | head -1" 2>/dev/null
}

publish_snapshot() {
    local timestamp stage database_container web_container revision

    timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
    stage="/var/tmp/typo3-lab-snapshot-${timestamp}"
    database_container="$(remote_container database)"
    web_container="$(remote_container web)"
    # remote_image_tag returns "<compose-project>_web:<sha>". Slicing the front
    # of that yields the project id, not the commit - the first published
    # snapshot was stamped "kj8tirx", which is the Coolify project.
    revision="$(remote_image_tag || true)"
    revision="${revision##*:}"
    revision="${revision:0:7}"
    [ -n "${revision}" ] || revision="unknown"

    SYNC_WORK_DIR="$(mktemp -d)"
    snapshot_readme "the live site" "${revision}" \
        "$(date -u '+%Y-%m-%d %H:%M:%S UTC')" > "${SYNC_WORK_DIR}/${SNAPSHOT_README}"
    cp "$(dirname "$0")/install.sh" "${SYNC_WORK_DIR}/${SNAPSHOT_INSTALLER}"
    cp "$(dirname "$0")/lib/remote-publish.sh" "${SYNC_WORK_DIR}/remote-publish.sh"
    cp "$(dirname "$0")/lib/make-zip.php" "${SYNC_WORK_DIR}/make-zip.php"

    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" "install -d -m 0700 '${stage}'"
    scp "${SSH_OPTIONS[@]}" \
        "${SYNC_WORK_DIR}/remote-publish.sh" \
        "${SYNC_WORK_DIR}/make-zip.php" \
        "${SYNC_WORK_DIR}/${SNAPSHOT_INSTALLER}" \
        "${SYNC_WORK_DIR}/${SNAPSHOT_README}" \
        "${REMOTE_HOST}:${stage}/"

    # Arguments rather than an inlined command: the remote script does the work
    # with ordinary local quoting instead of four levels of shell escaping.
    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "sh '${stage}/remote-publish.sh' \
            '${database_container}' '${web_container}' '${stage}' \
            '${revision}' '${timestamp}' \
            '${SNAPSHOT_SENSITIVE_PATTERN}' \
            '${SNAPSHOT_DEMO_USER}' '${SNAPSHOT_DEMO_PASSWORD}' \
            '${SNAPSHOT_DB_ARCHIVE}' '${SNAPSHOT_DB_MEMBER}' \
            '${SNAPSHOT_FILES_ARCHIVE}' '${SNAPSHOT_INSTALLER}' \
            '${SNAPSHOT_README}' '${SNAPSHOT_DIR_NAME}'"

    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" "rm -rf '${stage}'"
    echo
    echo "Verify before announcing the links - exactly one INSERT is expected,"
    echo "the demo administrator:"
    echo "  curl -u lab:PASSWORD -fsSLO https://typo3-lab.webconsulting.at/fileadmin/${SNAPSHOT_DIR_NAME}/${SNAPSHOT_DB_ARCHIVE}"
    echo "  unzip -p ${SNAPSHOT_DB_ARCHIVE} ${SNAPSHOT_DB_MEMBER} | grep -c 'INSERT INTO .be_users.'"
}

publish_downloads_page() {
    local stage web_container root_page
    local definition="$(dirname "$0")/lib/downloads-page.json"
    local applier="$(dirname "$0")/lib/apply-downloads-page.sh"

    root_page="${DOWNLOADS_ROOT_PAGE:-505}"
    [ -f "${definition}" ] || { echo "missing ${definition}" >&2; exit 1; }

    stage="/var/tmp/typo3-lab-page-$(date -u +%Y%m%dT%H%M%SZ)"
    web_container="$(remote_container web)"

    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" "install -d -m 0700 '${stage}'"
    scp "${SSH_OPTIONS[@]}" "${definition}" "${applier}" "${REMOTE_HOST}:${stage}/"

    # Into the container rather than run over ssh: the applier expects the
    # TYPO3 root as its working directory, and runs the site package's
    # sitepackage:apply-downloads-page command - which only exists in images
    # built from a commit that contains it, so deploy before running this.
    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" \
        "docker cp '${stage}/downloads-page.json' '${web_container}:/var/www/html/var/transient-downloads-page.json' \
         && docker cp '${stage}/apply-downloads-page.sh' '${web_container}:/var/www/html/var/apply-downloads-page.sh' \
         && docker exec -u www-data -w /var/www/html '${web_container}' sh var/apply-downloads-page.sh var/transient-downloads-page.json ${root_page} \
         ; rc=\$?; docker exec '${web_container}' rm -f /var/www/html/var/transient-downloads-page.json /var/www/html/var/apply-downloads-page.sh; exit \$rc"

    ssh "${SSH_OPTIONS[@]}" "${REMOTE_HOST}" "rm -rf '${stage}'"
    echo
    echo "The page links to files published by 'publish-snapshot'; run that too if you have not."
}

command="${1:-}"

case "${command}" in
    status)
        status
        ;;
    deploy)
        deploy "${2:-}"
        ;;
    publish-snapshot)
        require_confirmation "$@"
        publish_snapshot
        ;;
    publish-downloads-page)
        require_confirmation "$@"
        publish_downloads_page
        ;;
    push)
        require_confirmation "$@"
        push_to_coolify
        ;;
    pull)
        require_confirmation "$@"
        pull_from_coolify
        ;;
    *)
        usage >&2
        exit 2
        ;;
esac
