#!/usr/bin/env bash
# Export the matching database + Fileadmin pair a fresh checkout needs.
#
#   Build/Scripts/make-bootstrap-pair.sh             write dump.sql.gz and .tarballs/fileadmin.tar.gz
#   Build/Scripts/make-bootstrap-pair.sh --out DIR   write the pair into DIR instead
#
# The two exports only work as a pair: TYPO3 stores file references in the
# database by uid, so a dump taken at one moment and an archive taken at
# another give a site with broken images. Both are written in one run and
# stamped with the Git revision they came from, which is what docs/
# ddev-bootstrap.md means by "store the pair privately with its Git revision".
#
# This replaces doing it by hand. It is NOT a release step: the dump carries
# backend accounts, frontend users and application data, so the pair is handed
# over privately and never published. See docs/ddev-bootstrap.md.
set -euo pipefail
cd "$(dirname "$0")/../.."

out="."
case "${1:-}" in
  --out) out="${2:?directory required}" ;;
  "") ;;
  *) sed -n '2,6p' "$0"; exit 2 ;;
esac

command -v ddev >/dev/null || { echo "ddev is not on PATH" >&2; exit 1; }
[ -d public/fileadmin ] || { echo "public/fileadmin is missing - is this the lab checkout?" >&2; exit 1; }
if ! ddev describe >/dev/null 2>&1; then
  echo "the DDEV project is not running - start it with 'ddev start' first" >&2
  exit 1
fi

mkdir -p "$out" "$out/.tarballs"
db="$out/dump.sql.gz"
files="$out/.tarballs/fileadmin.tar.gz"
revision="$(git rev-parse --short HEAD 2>/dev/null || echo unknown)"

echo "exporting database  -> $db"
ddev export-db --file="$db"

echo "exporting fileadmin -> $files"
# Archive the *contents* of fileadmin: `ddev import-files` unpacks into the
# upload directory, so a nested fileadmin/ would land at fileadmin/fileadmin.
tar -czf "$files" -C public/fileadmin .

cat > "$out/.tarballs/PAIR.txt" <<EOF
Exported $(date -u '+%Y-%m-%d %H:%M:%S UTC') from git revision $revision

  ddev import-db --file=dump.sql.gz
  ddev import-files --source=.tarballs/fileadmin.tar.gz

Import both or neither - a mismatched pair renders with broken file
references. Contains account and application data: hand over privately,
never publish. See docs/ddev-bootstrap.md.
EOF

printf '\ndone, from revision %s:\n' "$revision"
printf '  %-36s %s\n' "$db" "$(du -h "$db" | cut -f1)"
printf '  %-36s %s\n' "$files" "$(du -h "$files" | cut -f1)"
printf '  %-36s %s\n' "$out/.tarballs/PAIR.txt" "notes"
