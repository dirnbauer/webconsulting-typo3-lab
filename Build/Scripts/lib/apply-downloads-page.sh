#!/bin/sh
# Create or update the Downloads page from its definition, idempotently.
#
#   apply-downloads-page.sh <definition.json> <root-page-uid>
#
# Runs INSIDE the TYPO3 container with the project root as the working
# directory; both arguments are paths/ids valid there. Driven by the
# publish-downloads-page action of Build/Scripts/sync-coolify.sh.
#
# The page is a database record, so neither a deployment (code only) nor
# publish-snapshot (files only) carries it to the live site - this is what
# does.
#
# The work is done by the sitepackage:apply-downloads-page command, which
# writes through DataHandler in the live workspace. This used to drive
# mcp:write-table, and on the live site that stages every write in a draft
# workspace: the page landed there, the live page tree never saw it, and the
# only way on was publishing a workspace that also held other people's pending
# drafts. The command also discards the draft that attempt left behind.
#
# POSIX sh: the production image has no bash on the default path for this.
set -eu

DEF="$1"; ROOT="$2"

[ -f "$DEF" ] || { echo "no such definition: $DEF" >&2; exit 1; }

# t3:// references carry uids - t3://file?uid=1111 is one row in one database.
# The first definition was exported from DDEV after the RTE had rewritten its
# plain links into references to DDEV's sys_file rows; on the live site those
# uids do not exist, so three of the four download links rendered as nothing.
# Plain paths are resolved by whichever database saves them.
if grep -q 't3://' "$DEF"; then
    echo "the definition contains t3:// references; their uids are specific to the database they came from - use plain paths" >&2
    exit 1
fi

vendor/bin/typo3 sitepackage:apply-downloads-page "$DEF" "$ROOT"
vendor/bin/typo3 cache:flush >/dev/null 2>&1 || true
