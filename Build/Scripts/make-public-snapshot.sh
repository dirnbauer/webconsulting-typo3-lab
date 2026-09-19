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

# public/fileadmin is a bind mount, not part of the Mutagen sync, so serving
# 350 MB from here costs the file watcher nothing - which it would not be if
# these sat anywhere else under public/.
out="public/fileadmin/_downloads"
case "${1:-}" in
  --out) out="${2:?directory required}" ;;
  "") ;;
  *) sed -n '2,4p' "$0"; exit 2 ;;
esac

DEMO_USER="admin"
DEMO_PASSWORD="Demo123*"

command -v ddev >/dev/null || { echo "ddev is not on PATH" >&2; exit 1; }
ddev describe >/dev/null 2>&1 || { echo "the DDEV project is not running - 'ddev start' first" >&2; exit 1; }

# Any table whose NAME matches one of these is emptied. Matching on the name
# rather than a fixed list means a credential table added by a future
# extension is stripped by default instead of silently shipping.
patterns='vault|secret|token|credential|oauth|identity|payment_log|_provider$|^fe_users$|^be_users$|^be_sessions$|^fe_sessions$|^sys_log$|^sys_history$'

# A plain string, not an array: macOS still ships bash 3.2, where `mapfile`
# does not exist. Table names cannot contain whitespace, so splitting is safe.
sensitive=$(ddev mysql -N -e "SHOW TABLES;" 2>/dev/null | grep -Ei "$patterns" | sort | tr '\n' ' ')
sensitive=${sensitive% }
[ -n "$sensitive" ] || { echo "no tables matched the sanitiser - refusing to publish" >&2; exit 1; }

mkdir -p "$out"
sql="$out/db-public.sql"
revision="$(git rev-parse --short HEAD 2>/dev/null || echo unknown)"

echo "stripping $(echo "$sensitive" | wc -w | tr -d ' ') credential-bearing tables:"
for t in $sensitive; do echo "  $t"; done

ignore=""
for t in $sensitive; do ignore="$ignore --ignore-table=db.$t"; done

# Everything except the sensitive tables, with data.
ddev exec "mysqldump -u db -pdb --no-tablespaces --skip-comments db $ignore" > "$sql" 2>/dev/null
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

gzip -9 -f "$sql"

# The installer is a tracked source file; public/fileadmin is ignored, so the
# copy that gets served has to be published here rather than edited in place.
cp Build/Scripts/install.sh "$out/install.sh"
chmod 0644 "$out/install.sh"
echo "archiving fileadmin"
# --exclude the download directory itself: it lives inside the tree being
# archived, so without this each run would pack the previous run's 350 MB
# archive into the new one.
tar -czf "$out/fileadmin-public.tar.gz" --exclude='./_downloads' -C public/fileadmin .

cat > "$out/README.txt" <<EOF
Public snapshot of the Webconsulting TYPO3 Lab
Exported $(date -u '+%Y-%m-%d %H:%M:%S UTC') from git revision $revision

  db-public.sql.gz         database, sanitised
  fileadmin-public.tar.gz  matching Fileadmin contents

Sign in with $DEMO_USER / $DEMO_PASSWORD and change it.

Sanitised means: Vault secrets, API and MCP access tokens, OAuth rows, WorkOS
identities, the LLM provider credential, all backend and frontend accounts and
the log/history tables keep their structure but ship with no rows. Import both
together - the database references Fileadmin files by uid.
EOF

printf '\ndone, revision %s:\n' "$revision"
for f in "$out/db-public.sql.gz" "$out/fileadmin-public.tar.gz"; do
  printf '  %-40s %s\n' "$f" "$(du -h "$f" | cut -f1)"
done
