#!/bin/sh
# Keeps one Netresearch AI Chat worker (`typo3 ai-chat:worker`, EXT:nr_mcp_agent)
# running: whenever it ends, a new one starts.
#
# The chat runs with processingStrategy=worker (packages/site_package/
# ext_localconf.php), because the extension's default, a PHP process per turn
# started from the web request, cannot start under the production image's
# mod_php (PHP_BINARY is empty there). Production has no cron, systemd or
# supervisor, so docker/coolify/entrypoint.sh starts this loop in the
# background; a DDEV post-start hook does the same locally.
#
# The worker ends by itself, as the extension intends, and this loop restarts
# it:
# - after every turn (--max-turns=1): each turn runs in a freshly booted
#   process, nothing of one turn leaks into the next, and a changed setting
#   applies from the next turn;
# - after an hour without turns (--time-limit), above 256 MB of memory
#   (--memory-limit). A booted, waiting worker uses about 35 MB (measured in
#   DDEV, 2026-11-20). PHP's hard limit of 2 GB (as php.ini in production)
#   only has to cover a boot on cold caches, which loads the large TCA schema:
#   1 GB was not enough right after a cache flush in the rehearsal;
# - when a turn runs longer than ten minutes (--turn-limit; needs pcntl, which
#   the image installs): that turn fails with a Retry button, never re-runs.
# A worker that fails right away (database not reachable yet, broken code) is
# restarted after 30 seconds instead of one, so a broken state cannot spin.
set -u

cd "${TYPO3_PROJECT_ROOT:-/var/www/html}" || exit 1

while :; do
    started=$(date +%s)
    php -d memory_limit=2G vendor/bin/typo3 ai-chat:worker \
        --max-turns=1 \
        --time-limit=3600 \
        --memory-limit=256 \
        --turn-limit=600 \
        --no-interaction
    status=$?
    if [ "$status" -ne 0 ] && [ $(( $(date +%s) - started )) -lt 30 ]; then
        echo "ai-chat-worker: worker exited with status ${status} after a short run; restarting in 30 s" >&2
        sleep 30
    else
        sleep 1
    fi
done
