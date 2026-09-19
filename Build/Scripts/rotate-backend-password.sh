#!/usr/bin/env bash
# Rotate a TYPO3 backend password.
#
#   Build/Scripts/rotate-backend-password.sh [--live] [--user NAME] [--out FILE]
#
#   --live       rotate on the Coolify site instead of this DDEV project
#   --user NAME  backend user to rotate (default: admin)
#   --out FILE   where to write the new password (default: a 0600 file under
#                .tarballs/, which is git-ignored)
#
# The live backend has been running with the published demo password since the
# lab went up, behind nothing but HTTP basic auth, and the operations wiki
# flags it for rotation. This is that rotation, made repeatable.
#
# The new password is generated on the target and written to a file readable
# only by you. It is never printed, so it does not end up in a terminal
# transcript, a CI log, or an assistant's context - which is also why nothing
# here echoes it back for confirmation.
#
# Rotating the secret is the whole job: editing the wiki page alone leaves the
# old value live, and the old value stays in that page's revision history
# either way.
set -euo pipefail
# Two levels up: this lives in Build/Scripts. One level up lands in Build/,
# where the default .tarballs/ is NOT covered by .gitignore - the first live
# rotation wrote the new admin password there, one `git add -A` from GitHub.
cd "$(dirname "$0")/../.."

TARGET="local"
BE_USER="admin"
OUT=""

while [ $# -gt 0 ]; do
    case "$1" in
        --live) TARGET="live" ;;
        --user) BE_USER="${2:?user name required}"; shift ;;
        --out)  OUT="${2:?file path required}"; shift ;;
        *) sed -n '2,12p' "$0"; exit 2 ;;
    esac
    shift
done

[ -n "$OUT" ] || OUT=".tarballs/be-password-${TARGET}-${BE_USER}-$(date -u +%Y%m%dT%H%M%SZ).txt"
mkdir -p "$(dirname "$OUT")"

# Generated here and only ever written to the file.
#
# openssl rather than `tr -dc < /dev/urandom | head -c`: head closes the pipe
# once it has its 32 bytes, tr dies of SIGPIPE, and under `set -o pipefail`
# that aborts the script - after assigning the password but before using it.
generate() {
    openssl rand -base64 64 | tr -dc 'A-Za-z0-9' | cut -c1-32
}
NEW="$(generate)"
[ "${#NEW}" -eq 32 ] || { echo "could not generate a password" >&2; exit 1; }

# Argon2id through PHP's own hasher, which is what TYPO3 v14 verifies against.
hash_in() {  # $1: a command prefix that runs php in the target
    printf '%s' "$NEW" | $1 sh -c 'umask 077; cat > /tmp/.rotate-pw'
    $1 php -r "echo password_hash(trim(file_get_contents('/tmp/.rotate-pw')), PASSWORD_ARGON2ID);"
    $1 rm -f /tmp/.rotate-pw
}

case "$TARGET" in
    local)
        command -v ddev >/dev/null || { echo "ddev is not on PATH" >&2; exit 1; }
        HASH="$(hash_in "ddev exec" | tr -d '\r\n')"
        [ -n "$HASH" ] || { echo "could not hash the new password" >&2; exit 1; }
        # ROW_COUNT() in the same statement batch as the UPDATE: on its own
        # connection it always reports 0, which reads as "nothing matched".
        CHANGED="$(ddev mysql -N -e "UPDATE be_users SET password = '${HASH}', tstamp = UNIX_TIMESTAMP() WHERE username = '${BE_USER}' AND deleted = 0; SELECT ROW_COUNT();" 2>/dev/null | tail -1)"
        [ "${CHANGED:-0}" != "0" ] || { echo "no backend user named '${BE_USER}' was updated" >&2; exit 1; }
        ;;
    live)
        REMOTE_HOST="${TYPO3_LAB_REMOTE_HOST:-root@49.13.173.37}"
        SSH_OPTIONS=(-o BatchMode=yes -o ConnectTimeout=15)
        STACK_LABEL="at.webconsulting.stack=typo3-lab"
        find_container() {
            ssh "${SSH_OPTIONS[@]}" "$REMOTE_HOST" \
                "docker ps --filter label=${STACK_LABEL} --filter label=com.docker.compose.service=$1 --format '{{.Names}}'" \
                | head -1
        }
        web="$(find_container web)"
        db="$(find_container database)"
        [ -n "$web" ] && [ -n "$db" ] || { echo "could not find the live containers" >&2; exit 1; }

        printf '%s' "$NEW" \
            | ssh "${SSH_OPTIONS[@]}" "$REMOTE_HOST" "docker exec -i '${web}' sh -c 'umask 077; cat > /tmp/.rotate-pw'"
        HASH="$(ssh "${SSH_OPTIONS[@]}" "$REMOTE_HOST" \
            "docker exec '${web}' php -r \"echo password_hash(trim(file_get_contents('/tmp/.rotate-pw')), PASSWORD_ARGON2ID);\"" \
            | tr -d '\r\n')"
        ssh "${SSH_OPTIONS[@]}" "$REMOTE_HOST" "docker exec '${web}' rm -f /tmp/.rotate-pw"
        [ -n "$HASH" ] || { echo "could not hash the new password" >&2; exit 1; }

        # The statement goes in on stdin rather than through -e. Inlining it
        # meant quoting SQL through ssh, sh, docker exec and mariadb at once,
        # and the version that did was unreadable and wrong.
        CHANGED="$(printf "UPDATE be_users SET password = '%s', tstamp = UNIX_TIMESTAMP() WHERE username = '%s' AND deleted = 0; SELECT ROW_COUNT();\n" "$HASH" "$BE_USER" \
            | ssh "${SSH_OPTIONS[@]}" "$REMOTE_HOST" \
                "docker exec -i '${db}' sh -c 'MYSQL_PWD=\"\$MARIADB_PASSWORD\" exec mariadb -N -u\"\$MARIADB_USER\" \"\$MARIADB_DATABASE\"'" \
            | tail -1 | tr -d '\r')"
        [ "${CHANGED:-0}" != "0" ] || { echo "no backend user named '${BE_USER}' was updated" >&2; exit 1; }
        ;;
esac

umask 077
printf '%s\n' "$NEW" > "$OUT"
chmod 600 "$OUT"
unset NEW

cat <<EOF

Rotated ${BE_USER} on ${TARGET}. Rows changed: ${CHANGED}

The new password is in:
  ${OUT}

It was not printed. Read it once, sign in with it, then delete the file and
update the wiki entry. The old value remains in that page's revision history,
which is why rotating the secret - not editing the page - is what fixes this.
EOF
