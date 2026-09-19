#!/bin/sh
# Create or update the Downloads page from its definition, idempotently.
#
#   apply-downloads-page.sh <definition.json> <root-page-uid>
#
# Runs INSIDE the TYPO3 container with the project root as the working
# directory; both arguments are paths/ids valid there. Driven by
# Build/Scripts/publish-downloads-page.sh, which handles local vs Coolify.
#
# The page is a database record, so neither a deployment (code only) nor
# publish-snapshot (files only) carries it to the live site - this is what
# does. It matches on the slug under the given root rather than on a uid,
# because uids differ between the DDEV database and the live one.
#
# POSIX sh: the production image has no bash on the default path for this.
set -eu

DEF="$1"; ROOT="$2"
CLI="vendor/bin/typo3"
WORK="var/transient/downloads-page"

[ -f "$DEF" ] || { echo "no such definition: $DEF" >&2; exit 1; }

SLUG=$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["page"]["slug"];' "$DEF")
[ -n "$SLUG" ] || { echo "the definition has no page.slug" >&2; exit 1; }

find_page() {
    # The page tree is the read that works against pages in every context;
    # mcp:read-table refuses the table.
    "$CLI" mcp:get-page-tree --param startPage="$ROOT" --param depth=1 --plain 2>/dev/null \
        | grep -F "${SLUG}/" \
        | sed -n 's/^- \[\([0-9]\{1,\}\)\].*/\1/p' \
        | head -1
}

mkdir -p "$WORK"
php -r '
$d = json_decode(file_get_contents($argv[1]), true);
file_put_contents($argv[2], json_encode($d["page"]));
file_put_contents($argv[3], json_encode($d["content"]));
' "$DEF" "${WORK}/page.json" "${WORK}/content.json"

PAGE_UID=$(find_page)

if [ -n "$PAGE_UID" ]; then
    echo "page ${PAGE_UID} already at ${SLUG}; updating"
    "$CLI" mcp:write-table --action=update --table=pages --uid="$PAGE_UID" \
        --param data=@"${WORK}/page.json" --json >/dev/null
else
    echo "creating ${SLUG} under root ${ROOT}"
    "$CLI" mcp:write-table --action=create --table=pages --pid="$ROOT" \
        --param data=@"${WORK}/page.json" --json >/dev/null
    PAGE_UID=$(find_page)
    [ -n "$PAGE_UID" ] || { echo "the page was not created" >&2; exit 1; }
fi

# Reuse the first existing element rather than appending, so repeated runs do
# not stack duplicates on the page.
CE=$("$CLI" mcp:get-page --param uid="$PAGE_UID" --plain 2>/dev/null \
    | sed -n 's/^- \[\([0-9]\{1,\}\)\] .*(Type: .*/\1/p' | head -1)

if [ -n "$CE" ]; then
    echo "updating content element ${CE}"
    "$CLI" mcp:write-table --action=update --table=tt_content --uid="$CE" \
        --param data=@"${WORK}/content.json" --json >/dev/null
else
    echo "creating the content element"
    "$CLI" mcp:write-table --action=create --table=tt_content --pid="$PAGE_UID" \
        --param data=@"${WORK}/content.json" --json >/dev/null
fi

"$CLI" cache:flush >/dev/null 2>&1 || true
rm -f "${WORK}/page.json" "${WORK}/content.json"
rmdir "$WORK" 2>/dev/null || true
echo "done: page ${PAGE_UID} at ${SLUG}"
