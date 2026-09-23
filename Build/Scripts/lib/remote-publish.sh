#!/bin/sh
# Runs on the Coolify host, shipped there by Build/Scripts/sync-coolify.sh.
#
# Builds the sanitised snapshot from the *live* database and fileadmin and
# places it in the web container's download directory. It reads production and
# writes only inside fileadmin/<dir>, so it adds downloads without touching any
# existing content.
#
# POSIX sh: the host runs it directly, not through bash.
set -eu

DB="$1"; WEB="$2"; STAGE="$3"; REV="$4"; TS="$5"
PATTERN="$6"; DEMO_USER="$7"; DEMO_PASSWORD="$8"; DB_ARCHIVE="$9"
shift 9
DB_MEMBER="$1"; FILES_ARCHIVE="$2"; INSTALLER="$3"; README="$4"; DIR_NAME="$5"
PRIVATE_PATHS="${6:-}"; ROW_FILTERS="${7:-}"

DEST="/var/www/html/public/fileadmin/${DIR_NAME}"

DBNAME=$(docker exec "$DB" sh -c 'printf %s "$MARIADB_DATABASE"')
[ -n "$DBNAME" ] || { echo "could not read the database name" >&2; exit 1; }

SENSITIVE=$(echo 'SHOW TABLES;' \
  | docker exec -i "$DB" sh -c 'MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb -u"$MARIADB_USER" -N -B "$MARIADB_DATABASE"' \
  | grep -Ei "$PATTERN" | sort | tr '\n' ' ')
SENSITIVE=${SENSITIVE% }
[ -n "$SENSITIVE" ] || { echo "no tables matched the sanitiser - refusing to publish" >&2; exit 1; }

echo "stripping $(echo "$SENSITIVE" | wc -w | tr -d ' ') credential-bearing tables:"
for t in $SENSITIVE; do echo "  $t"; done

# Tables with a soft-delete column ship their live rows only: a record removed
# in the backend stays in the database for the recycler, and must not reach the
# published dump that way.
SOFT=$(echo "SELECT DISTINCT table_name FROM information_schema.columns WHERE table_schema=DATABASE() AND column_name='deleted';" \
  | docker exec -i "$DB" sh -c 'MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb -u"$MARIADB_USER" -N -B "$MARIADB_DATABASE"' \
  | grep -Eiv "$PATTERN" | sort | tr '\n' ' ')
SOFT=${SOFT% }
# Row-filtered tables leave both batches and are dumped on their own.
FILTERED=""
IFS=';'
for filter in $ROW_FILTERS; do
  table=${filter%%|*}
  FILTERED="$FILTERED $table"
  SOFT=$(echo " $SOFT " | sed "s/ $table / /; s/^ //; s/ $//")
done
unset IFS

IGNORE=""
for t in $SENSITIVE $SOFT $FILTERED; do IGNORE="$IGNORE --ignore-table=${DBNAME}.${t}"; done

DUMP=mariadb-dump
docker exec "$DB" sh -c 'command -v mariadb-dump >/dev/null 2>&1' || DUMP=mysqldump

# Everything else with data, then the soft-delete tables without their deleted
# rows, then the sensitive tables as structure only. Structure is kept
# deliberately: a dump missing them would import into a broken install,
# because extension:setup expects the tables to exist.
docker exec "$DB" sh -c \
  "MYSQL_PWD=\"\$MARIADB_PASSWORD\" $DUMP -u\"\$MARIADB_USER\" --no-tablespaces --skip-comments \"\$MARIADB_DATABASE\" $IGNORE" \
  > "${STAGE}/${DB_MEMBER}"
docker exec "$DB" sh -c \
  "MYSQL_PWD=\"\$MARIADB_PASSWORD\" $DUMP -u\"\$MARIADB_USER\" --no-tablespaces --skip-comments --where='deleted=0' \"\$MARIADB_DATABASE\" $SOFT" \
  >> "${STAGE}/${DB_MEMBER}"
# Each row-filtered table with its own condition; a condition may read
# another table, hence no table locks.
IFS=';'
for filter in $ROW_FILTERS; do
  table=${filter%%|*}
  docker exec "$DB" sh -c \
    "MYSQL_PWD=\"\$MARIADB_PASSWORD\" $DUMP -u\"\$MARIADB_USER\" --no-tablespaces --skip-comments --single-transaction --skip-lock-tables --where='${filter#*|}' \"\$MARIADB_DATABASE\" $table" \
    >> "${STAGE}/${DB_MEMBER}"
done
unset IFS
docker exec "$DB" sh -c \
  "MYSQL_PWD=\"\$MARIADB_PASSWORD\" $DUMP -u\"\$MARIADB_USER\" --no-tablespaces --skip-comments --no-data \"\$MARIADB_DATABASE\" $SENSITIVE" \
  >> "${STAGE}/${DB_MEMBER}"

# One demo administrator. The password goes through a file rather than an
# argument so it stays out of `ps`, and the PHP contains no $ so no shell
# expands anything before PHP sees it.
printf '%s' "$DEMO_PASSWORD" | docker exec -i "$WEB" sh -c 'umask 077; cat > /tmp/demo-pw'
HASH=$(docker exec "$WEB" php -r "echo password_hash(trim(file_get_contents('/tmp/demo-pw')), PASSWORD_ARGON2ID);")
docker exec "$WEB" rm -f /tmp/demo-pw
[ -n "$HASH" ] || { echo "could not hash the demo password" >&2; exit 1; }

cat >> "${STAGE}/${DB_MEMBER}" <<EOF

-- Single demo administrator (${DEMO_USER} / ${DEMO_PASSWORD}). Every other
-- account, and every credential table above, was stripped before publication.
INSERT INTO \`be_users\` (\`uid\`, \`pid\`, \`tstamp\`, \`crdate\`, \`username\`, \`password\`, \`admin\`, \`disable\`, \`deleted\`)
VALUES (1, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '${DEMO_USER}', '${HASH}', 1, 0, 0);
EOF

# Zip inside the web container: the production image is php:8.4-apache, which
# ships the PHP zip extension and unzip but no zip binary.
docker cp "${STAGE}/make-zip.php" "${WEB}:/tmp/make-zip.php"
docker cp "${STAGE}/${DB_MEMBER}" "${WEB}:/tmp/${DB_MEMBER}"
docker exec "$WEB" php /tmp/make-zip.php "/tmp/${DB_ARCHIVE}" "/tmp/${DB_MEMBER}" >/dev/null
docker cp "${WEB}:/tmp/${DB_ARCHIVE}" "${STAGE}/${DB_ARCHIVE}"
docker exec "$WEB" rm -f "/tmp/${DB_MEMBER}" "/tmp/${DB_ARCHIVE}"
rm -f "${STAGE}/${DB_MEMBER}"

echo "archiving fileadmin"
docker exec "$WEB" sh -c "mkdir -p '${DEST}'"
# --exclude the download directory: it lives inside the tree being archived,
# so without this each run packs the previous run's archive into the new one.
# The private paths (AI chat uploads, personal documents) are excluded as well;
# set -f keeps their wildcards away from this host's filesystem, and each one
# is its own argument to docker exec, so no shell sees it again.
set -f
set -- --dir /var/www/html/public/fileadmin --exclude "${DIR_NAME}"
for p in $PRIVATE_PATHS; do set -- "$@" --exclude "$p"; done
set +f
docker exec "$WEB" php /tmp/make-zip.php "/tmp/${FILES_ARCHIVE}" "$@" >/dev/null
docker cp "${WEB}:/tmp/${FILES_ARCHIVE}" "${STAGE}/${FILES_ARCHIVE}"
docker exec "$WEB" rm -f "/tmp/${FILES_ARCHIVE}" /tmp/make-zip.php

for f in "$DB_ARCHIVE" "$FILES_ARCHIVE" "$INSTALLER" "$README"; do
    docker cp "${STAGE}/${f}" "${WEB}:${DEST}/${f}"
done
docker exec "$WEB" sh -c "chown -R www-data:www-data '${DEST}' && chmod 0644 ${DEST}/*"

echo
echo "published to ${DEST} (revision ${REV}, ${TS}):"
docker exec "$WEB" sh -c "ls -lh '${DEST}'"
