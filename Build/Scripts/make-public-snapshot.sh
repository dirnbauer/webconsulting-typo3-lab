#!/usr/bin/env bash
# Build the sanitised database + Fileadmin pair that may be published.
#
#   Build/Scripts/make-public-snapshot.sh [--out DIR]   default: the published directory
#
# The full export from make-bootstrap-pair.sh is a private handover artifact:
# it carries Vault secrets, MCP access tokens, WorkOS identities, an LLM
# provider credential and backend password hashes. This script produces the
# variant that is safe to hand to anyone - every credential-bearing table keeps
# its structure and loses its rows, and backend login is replaced by a single
# demo account whose password is already public.
#
# Structure is kept deliberately: a dump missing those tables entirely would
# import into a broken install, because extension:setup expects them.
set -euo pipefail
cd "$(dirname "$0")/../.."
# What counts as sensitive, and what the published files are called, is shared
# with sync-coolify.sh publish-snapshot so the two cannot drift apart.
. Build/Scripts/lib/public-snapshot.sh

# public/fileadmin is a bind mount, not part of the Mutagen sync, so serving
# 350 MB from here costs the file watcher nothing - which it would not be if
# these sat anywhere else under public/.
#
# Filenames are chosen to clear TYPO3's shipped public/.htaccess, which denies
# .sh and .sql* from the document root: the dump ships as a .tar.gz (which
# `ddev import-db` reads natively) and the installer as .txt. That rule is
# worth keeping intact - it travels with the repository, while the basic auth
# that would otherwise have to justify an exemption does not.
out="public/fileadmin/$SNAPSHOT_DIR_NAME"
case "${1:-}" in
  --out) out="${2:?directory required}" ;;
  "") ;;
  *) sed -n '2,4p' "$0"; exit 2 ;;
esac

DEMO_USER="$SNAPSHOT_DEMO_USER"
DEMO_PASSWORD="$SNAPSHOT_DEMO_PASSWORD"

command -v ddev >/dev/null || { echo "ddev is not on PATH" >&2; exit 1; }
ddev describe >/dev/null 2>&1 || { echo "the DDEV project is not running - 'ddev start' first" >&2; exit 1; }

# Any table whose NAME matches one of these is emptied. Matching on the name
# rather than a fixed list means a credential table added by a future
# extension is stripped by default instead of silently shipping.
patterns="$SNAPSHOT_SENSITIVE_PATTERN"

# A plain string, not an array: macOS still ships bash 3.2, where `mapfile`
# does not exist. Table names cannot contain whitespace, so splitting is safe.
sensitive=$(ddev mysql -N -e "SHOW TABLES;" 2>/dev/null | grep -Ei "$patterns" | sort | tr '\n' ' ')
sensitive=${sensitive% }
[ -n "$sensitive" ] || { echo "no tables matched the sanitiser - refusing to publish" >&2; exit 1; }

mkdir -p "$out"
sql="$out/$SNAPSHOT_DB_MEMBER"  # gzipped into db-public.tar.gz below
revision="$(git rev-parse --short HEAD 2>/dev/null || echo unknown)"

echo "stripping $(echo "$sensitive" | wc -w | tr -d ' ') credential-bearing tables:"
for t in $sensitive; do echo "  $t"; done

# Tables with a soft-delete column ship their live rows only. A record removed
# in the backend stays in the database for the recycler, and must not reach the
# published dump that way.
soft=$(ddev mysql -N -e "SELECT DISTINCT table_name FROM information_schema.columns WHERE table_schema='db' AND column_name='deleted';" 2>/dev/null | grep -Eiv "$patterns" | sort | tr '\n' ' ')
soft=${soft% }
# The row-filtered table leaves the soft-delete batch and is dumped on its own.
filtered=""
case " $soft " in *" $SNAPSHOT_ROW_FILTER_TABLE "*) filtered="$SNAPSHOT_ROW_FILTER_TABLE"; soft=$(echo " $soft " | sed "s/ $filtered / /; s/^ //; s/ $//") ;; esac

ignore=""
for t in $sensitive $soft $filtered; do ignore="$ignore --ignore-table=db.$t"; done

# Everything else, with data.
ddev exec "mysqldump -u db -pdb --no-tablespaces --skip-comments db $ignore" > "$sql" 2>/dev/null
# The soft-delete tables, without their deleted rows.
ddev exec "mysqldump -u db -pdb --no-tablespaces --skip-comments --where='deleted=0' db $soft" >> "$sql" 2>/dev/null
# The row-filtered table, with its own condition.
if [ -n "$filtered" ]; then
  ddev exec "mysqldump -u db -pdb --no-tablespaces --skip-comments --where='$SNAPSHOT_ROW_FILTER_WHERE' db $filtered" >> "$sql" 2>/dev/null
fi
# The sensitive tables, structure only.
ddev exec "mysqldump -u db -pdb --no-tablespaces --skip-comments --no-data db $sensitive" >> "$sql" 2>/dev/null

# One demo administrator, with the password the documentation already prints.
# The password goes through a file, not an argument: `ddev exec` reassembles
# its command inside double quotes, so anything with a $ in it - $argv included
# - is expanded by the container's shell before PHP ever sees it.
printf '%s' "$DEMO_PASSWORD" | ddev exec "cat > /tmp/demo-pw"
hash=$(ddev exec php -r "echo password_hash(trim(file_get_contents('/tmp/demo-pw')), PASSWORD_ARGON2ID);" | tr -d '\r\n')
ddev exec rm -f /tmp/demo-pw
[ -n "$hash" ] || { echo "could not hash the demo password" >&2; exit 1; }
cat >> "$sql" <<EOF

-- Single demo administrator ($DEMO_USER / $DEMO_PASSWORD). Every other account,
-- and every credential table above, was stripped before publication.
INSERT INTO \`be_users\` (\`uid\`, \`pid\`, \`tstamp\`, \`crdate\`, \`username\`, \`password\`, \`admin\`, \`disable\`, \`deleted\`)
VALUES (1, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '$DEMO_USER', '$hash', 1, 0, 0);
EOF

ddev exec php Build/Scripts/lib/make-zip.php "$out/$SNAPSHOT_DB_ARCHIVE" "$sql" >/dev/null
rm -f "$sql"

# The installer is a tracked source file; public/fileadmin is ignored, so the
# copy that gets served has to be published here rather than edited in place.
# It is published as .txt so the stock .htaccess serves it; run it with
# `bash install.txt`.
cp Build/Scripts/install.sh "$out/$SNAPSHOT_INSTALLER"
chmod 0644 "$out/$SNAPSHOT_INSTALLER"
echo "archiving fileadmin"
# --exclude the download directory itself: it lives inside the tree being
# archived, so without this each run would pack the previous run's 350 MB
# archive into the new one.
# Private uploads stay out too. set -f keeps their wildcards from being
# expanded on the host; the single quotes keep the container's shell from
# expanding them, since `ddev exec` runs its command string through one.
private_excludes=""
set -f
for p in $SNAPSHOT_PRIVATE_PATHS; do private_excludes="$private_excludes --exclude '$p'"; done
set +f
ddev exec "php Build/Scripts/lib/make-zip.php '$out/$SNAPSHOT_FILES_ARCHIVE' --dir public/fileadmin --exclude '$SNAPSHOT_DIR_NAME'$private_excludes"

snapshot_readme "this DDEV project" "$revision" "$(date -u '+%Y-%m-%d %H:%M:%S UTC')" \
  > "$out/$SNAPSHOT_README"

printf '\ndone, revision %s:\n' "$revision"
for f in "$out/$SNAPSHOT_DB_ARCHIVE" "$out/$SNAPSHOT_FILES_ARCHIVE" "$out/$SNAPSHOT_INSTALLER"; do
  printf '  %-40s %s\n' "$f" "$(du -h "$f" | cut -f1)"
done
