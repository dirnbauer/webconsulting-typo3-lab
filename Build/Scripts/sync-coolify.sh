#!/usr/bin/env bash

set -euo pipefail

REMOTE_HOST="${TYPO3_LAB_REMOTE_HOST:-root@49.13.173.37}"
STACK_LABEL="at.webconsulting.stack=typo3-lab"
BACKUP_ROOT="${TYPO3_LAB_BACKUP_ROOT:-${PWD}/.tarballs/coolify-sync}"
SSH_OPTIONS=(-o BatchMode=yes -o ConnectTimeout=15)
SYNC_WORK_DIR=""

cleanup() {
    if [[ -n "${SYNC_WORK_DIR}" && -d "${SYNC_WORK_DIR}" ]]; then
        rm -rf -- "${SYNC_WORK_DIR}"
    fi
}

trap cleanup EXIT

usage() {
    cat <<'USAGE'
Usage: Build/Scripts/sync-coolify.sh <status|push|pull> [--confirm]

  status            Show the local DDEV and remote Coolify container state.
  push --confirm    Back up Coolify, then replace its database and fileadmin
                    with exports from this DDEV project.
  pull --confirm    Back up DDEV, then replace its database and fileadmin
                    with exports from Coolify.

Environment:
  TYPO3_LAB_REMOTE_HOST   SSH target (default: root@49.13.173.37)
  TYPO3_LAB_BACKUP_ROOT   Local backup directory
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

command="${1:-}"

case "${command}" in
    status)
        status
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
