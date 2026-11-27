#!/bin/sh
set -eu

# Turns of the AI chat that were running when the previous container went down
# were last touched before this moment (see the release step at the end).
container_started=$(date +%s)

# Opt-in custody validation must fail before startup writes or migrations.
# Run as Apache's user so a root-readable but runtime-unreadable file fails.
if [ -n "${TYPO3_VAULT_MASTER_KEY_FILE:-}" ]; then
    # Custody opt-in must never implicitly rotate TYPO3's signing key either.
    # Its separate, approved rotation happens only after the Vault file is proven.
    if [ ! -s /run/typo3-secrets/encryption-key ]; then
        echo "entrypoint: existing TYPO3 signing key file is required before enabling Vault file custody" >&2
        exit 1
    fi
    su -s /bin/sh -c 'exec php /var/www/html/docker/coolify/validate-vault-master-key.php' www-data
fi

if [ -z "${BASIC_AUTH_USER:-}" ] || [ -z "${BASIC_AUTH_PASSWORD:-}" ]; then
    echo "BASIC_AUTH_USER and BASIC_AUTH_PASSWORD are required" >&2
    exit 1
fi

umask 077
htpasswd -bcB /etc/apache2/typo3-lab.htpasswd "${BASIC_AUTH_USER}" "${BASIC_AUTH_PASSWORD}" >/dev/null
chown root:www-data /etc/apache2/typo3-lab.htpasswd
chmod 0640 /etc/apache2/typo3-lab.htpasswd

# Addresses that skip the lab login (apache-vhost.conf includes this file in
# its RequireAny). TYPO3_LAB_ALLOWED_IPS holds IPs or CIDR ranges, separated by
# spaces or commas; it lives in Coolify, not in this public repository. Only
# address characters are accepted, so the value can never inject directives.
allowed_ips_conf=/etc/apache2/typo3-lab-allowed-ips.conf
: > "$allowed_ips_conf"
set -f
for ip in $(printf '%s' "${TYPO3_LAB_ALLOWED_IPS:-}" | tr ',' ' '); do
    case "$ip" in
        *[!0-9A-Fa-f:./]*)
            echo "entrypoint: ignoring invalid TYPO3_LAB_ALLOWED_IPS entry" >&2
            ;;
        *)
            echo "Require ip $ip" >> "$allowed_ips_conf"
            ;;
    esac
done
set +f
chmod 0644 "$allowed_ips_conf"

mkdir -p \
    /run/typo3-secrets \
    /var/www/html/config/system \
    /var/www/html/public/fileadmin \
    /var/www/html/public/typo3temp/assets/_processed_ \
    /var/www/html/public/typo3temp/assets/images \
    /var/www/html/var

if [ ! -s /run/typo3-secrets/encryption-key ]; then
    php -r 'echo bin2hex(random_bytes(48));' > /run/typo3-secrets/encryption-key
fi

chown -R www-data:www-data \
    /run/typo3-secrets \
    /var/www/html/config/system \
    /var/www/html/public/fileadmin \
    /var/www/html/public/typo3temp \
    /var/www/html/var
chmod 0700 /run/typo3-secrets
chmod 0600 /run/typo3-secrets/encryption-key
chmod 0750 /var/www/html/config/system
find /var/www/html/config/system -type f -exec chmod 0640 {} +
chmod 0755 \
    /var/www/html/public/fileadmin \
    /var/www/html/public/typo3temp \
    /var/www/html/public/typo3temp/assets \
    /var/www/html/public/typo3temp/assets/_processed_ \
    /var/www/html/public/typo3temp/assets/images \
    /var/www/html/var

/usr/local/bin/typo3-provision-powermail-uploads

# The compiled dependency-injection container and class caches live in the
# persistent var/ volume, so a new image inherits whatever the previous one
# compiled. When a vendor library that produced them changes, the new code
# refuses to load them and every request dies before the error handler can even
# render — which is exactly what a symfony/var-exporter upgrade did here. The
# compiled code is cheap to rebuild and must never outlive the image it was
# built from.
rm -rf /var/www/html/var/cache/code
mkdir -p /var/www/html/var/cache/code
chown www-data:www-data /var/www/html/var/cache/code

# The CLI's OPcache file cache (docker/coolify/php-cli.ini) for the same
# reason: compiled code never outlives the start it was compiled in. It is
# emptied here, after the root-run PHP above and before the first www-data CLI
# step, so every compiled file in it belongs to www-data. PHP runs no CLI
# script at all while the directory is missing or not writable, so it is
# recreated, not just emptied.
rm -rf /tmp/opcache
install -d -o www-data -g www-data -m 0700 /tmp/opcache

# TYPO3's FileWriter appends forever and nothing in the container rotates it:
# var/log grew to 239 MB, a single file holding months of repeated warnings that
# made the log useless to read and slow to search. Roll anything oversized at
# start, keeping one previous generation, which bounds growth without a cron
# daemon. Nothing is serving yet, so no writer holds these open.
for log in /var/www/html/var/log/*.log; do
    [ -f "$log" ] || continue
    size=$(wc -c < "$log")
    if [ "$size" -gt "${TYPO3_LOG_MAX_BYTES:-52428800}" ]; then
        echo "entrypoint: rotating $(basename "$log") ($((size / 1048576)) MB)"
        mv -f "$log" "$log.1"
        : > "$log"
        chown www-data:www-data "$log"
    fi
done

# New code usually expects new tables. Nothing else applies them, so without
# this a deployment serves 500s until someone runs it by hand. extension:setup
# only adds and changes; dropping columns stays a deliberate, separate step so
# an upgrade wizard can read the old data first.
if [ "${TYPO3_RUN_SETUP:-1}" = "1" ]; then
    echo "entrypoint: applying extension setup and database migrations"
    if su -s /bin/sh -c 'cd /var/www/html && php -d memory_limit=1536M vendor/bin/typo3 extension:setup --no-interaction' www-data; then
        echo "entrypoint: extension setup finished"
    else
        # Serving with a clear log beats refusing to boot: a failed migration
        # is visible in the logs and in the site, a boot loop hides both.
        echo "entrypoint: WARNING extension setup failed; the database may be behind the code" >&2
    fi
fi

# Data migrations that must follow a package swap. extension:setup creates the
# new tables but never copies data, and nobody runs the Upgrade module on this
# host. A wizard that already ran is marked done and does nothing, so listing
# it here costs one quick check per start. Keep the list to wizards that only
# copy data; anything destructive stays a manual step. Never call upgrade:run
# without a wizard name: that would also run the ones below that must not run.
#
# nr-llm (the AI stack's base): providers that predate the trust zone get one
# stamped (without it a provider falls back to the strictest zone, and runs
# lose their diagnostics, code and configuration tools), the never-applied 30 s
# API timeout becomes 120 s, and well-known embedding models get their vector
# size. nr-mcp-agent 0.39 (the Netresearch AI Chat): a stored English
# "waiting for approval" sentence is cleared; the messages of the chats from
# its earlier install move from the conversation rows into their own table,
# then each conversation gets its last-message time. All of them only fill or
# correct data and do nothing once done. Production had the first two done
# before this list existed (2026-11-21).
#
# Left out on purpose: nrLlm_dataClassEnforcementObserveForExisting writes
# extension configuration into settings.php, which every image rebuilds from
# settings.php.example, so it would be marked done while its value is lost;
# nrMcpAgent_dashboardWidgetSizes and nrMcpAgent_recentChatsWidgetSize
# rearrange users' dashboards, which is a decision, not a data fix.
run_wizard() {
    echo "entrypoint: upgrade wizard $1"
    su -s /bin/sh -c "cd /var/www/html && php -d memory_limit=1536M vendor/bin/typo3 upgrade:run $1 --no-interaction" www-data
}
if [ "${TYPO3_RUN_WIZARDS:-1}" = "1" ]; then
    for wizard in nrLlm_stampProviderTrustZone nrLlm_providerApiTimeout120 nrLlm_embeddingModelDimensions nrMcpAgent_clearStoredApprovalNotice; do
        run_wizard "${wizard}" || echo "entrypoint: WARNING upgrade wizard ${wizard} failed" >&2
    done
    # The last-message time is filled from the moved messages. Run on a
    # transcript table that is still empty, it would find nothing to do and be
    # marked done for good, so it only runs after the move succeeded.
    if run_wizard nrMcpAgent_migrateMessagesToTable; then
        run_wizard nrMcpAgent_lastMessageTime \
            || echo "entrypoint: WARNING upgrade wizard nrMcpAgent_lastMessageTime failed" >&2
    else
        echo "entrypoint: WARNING upgrade wizard nrMcpAgent_migrateMessagesToTable failed; nrMcpAgent_lastMessageTime waits for the next start" >&2
    fi

    # nr-llm's skill digest wizard (ADR-214) disables every skill whose stored
    # frontmatter does not verify; it must never run here (the skills are kept
    # by skillflow). It is marked done instead, as netresearch/typo3-demo does,
    # so a later run without a wizard name cannot pick it up. A mark that
    # exists already is left alone.
    echo "entrypoint: marking nrllmAdr214SkillVersionDigest as done (it never runs here)"
    su -s /bin/sh -c "cd /var/www/html && php -d memory_limit=1536M vendor/bin/typo3 sitepackage:upgrade:mark-done 'Netresearch\\NrLlm\\Updates\\SkillVersionDigestUpdateWizard' --no-interaction" www-data \
        || echo "entrypoint: WARNING could not mark nrllmAdr214SkillVersionDigest as done" >&2
fi

# Core labels come from language packs that TYPO3 downloads into var/labels;
# nothing in the image carries them, so a fresh volume has a half-English
# backend (only extensions ship their own German). var/ is a persistent volume,
# so this mostly refreshes packs that are already there. A failed download
# (no network at start) keeps whatever packs the volume already holds.
if [ "${TYPO3_LANGUAGE_UPDATE:-1}" = "1" ]; then
    echo "entrypoint: updating the core language packs (${TYPO3_LANGUAGE_PACKS:-de})"
    su -s /bin/sh -c "cd /var/www/html && php -d memory_limit=1536M vendor/bin/typo3 language:update ${TYPO3_LANGUAGE_PACKS:-de} --no-interaction" www-data \
        || echo "entrypoint: WARNING language pack update failed; core labels may stay English" >&2
fi

# Rendered pages are cached in the database, which is a persistent volume, so a
# new image keeps serving the previous one's HTML: a template fix deploys
# successfully and changes nothing visible. Only the "pages" group is dropped —
# the system caches stay warm, so this does not send every worker off to rebuild
# TCA and the DI container at once, which is what makes a full flush expensive
# on a small host.
if [ "${TYPO3_FLUSH_PAGE_CACHE:-1}" = "1" ]; then
    echo "entrypoint: flushing the page cache so new templates are rendered"
    su -s /bin/sh -c 'cd /var/www/html && php -d memory_limit=1536M vendor/bin/typo3 cache:flush --group pages' www-data \
        || echo "entrypoint: WARNING page cache flush failed; pages may render from the previous release" >&2
fi

# The Netresearch AI Chat (EXT:nr_mcp_agent) finds the nr-llm task it runs on
# by uid (llmTaskUid). The uid belongs to this database, and settings.php is
# rebuilt from settings.php.example by every image, so the start writes it again
# from the task's identifier ("backend-ai-chat"). It only reads the database and
# writes that one setting; the records themselves come from the production data
# run (sitepackage:seed-ai-stack, docs/content/seeding.md). Before that ran once,
# the chat says it is not configured, and nothing else is affected.
if [ "${TYPO3_CHAT_SETTINGS:-1}" = "1" ]; then
    echo "entrypoint: pointing the AI chat at its nr-llm task"
    su -s /bin/sh -c 'cd /var/www/html && php -d memory_limit=1536M vendor/bin/typo3 sitepackage:seed-ai-stack --settings-only --no-interaction' www-data \
        || echo "entrypoint: WARNING the AI chat task setting could not be written; the chat may say it is not configured" >&2
fi

# A chat turn that dies leaves its conversation claimed, and only this command
# releases it (after five minutes). There is no cron daemon in this container,
# so a scheduler task would never fire; at start nothing can be mid-turn, which
# makes this the one moment the release is certainly safe. The same run applies
# the retention windows: conversations idle for autoArchiveDays (30) are
# archived, archived ones are deleted after 90 days.
if [ "${TYPO3_CHAT_CLEANUP:-1}" = "1" ]; then
    echo "entrypoint: releasing stuck chat conversations and applying retention"
    su -s /bin/sh -c 'cd /var/www/html && php -d memory_limit=1536M vendor/bin/typo3 ai-chat:cleanup --delete-after-days=90 --no-interaction' www-data \
        || echo "entrypoint: WARNING chat cleanup failed; stuck conversations stay claimed until the next start" >&2
fi

# The release of turns a container stop or an OOM kill interrupted (see
# sitepackage:ai-chat:release-interrupted): only rows last touched before this
# container started, and only running ones (locked, tool_loop); queued turns
# stay for the workers. It runs whatever TYPO3_CHAT_CLEANUP says, because it
# archives and deletes nothing.
if [ "${TYPO3_CHAT_RELEASE:-1}" = "1" ]; then
    echo "entrypoint: releasing AI chat turns interrupted by the last stop"
    su -s /bin/sh -c "cd /var/www/html && php -d memory_limit=1536M vendor/bin/typo3 sitepackage:ai-chat:release-interrupted --before=${container_started} --no-interaction" www-data \
        || echo "entrypoint: WARNING interrupted chat turns could not be released; they stay claimed" >&2
fi

# Agent Nexus logs every protocol request and keeps the objects the demos create
# (tasks, checkouts, mandates). Its scheduler task would never fire here (no
# cron), so the retention settings are applied at start instead.
if [ "${TYPO3_AGENTNEXUS_CLEANUP:-1}" = "1" ]; then
    echo "entrypoint: applying the Agent Nexus retention settings"
    su -s /bin/sh -c 'cd /var/www/html && php -d memory_limit=1536M vendor/bin/typo3 agentnexus:cleanup --no-interaction' www-data \
        || echo "entrypoint: WARNING Agent Nexus cleanup failed; old traffic stays until the next start" >&2
fi

# Chat workers (processingStrategy=worker, packages/site_package/
# ext_localconf.php). Each loop keeps one `ai-chat:worker` alive and restarts it
# whenever it ends (docker/coolify/ai-chat-worker.sh); one worker runs one turn
# at a time, so TYPO3_CHAT_WORKERS is the number of chats that can answer at the
# same moment. The loops run as www-data and are started last, after every
# schema change; their output goes to the container log. They stop with the
# container. 0 switches them off: the chat then queues every turn until a
# worker exists, because the per-request fallback cannot start under mod_php.
chat_workers="${TYPO3_CHAT_WORKERS:-2}"
case "$chat_workers" in
    ''|*[!0-9]*) echo "entrypoint: WARNING TYPO3_CHAT_WORKERS is not a number; starting none" >&2; chat_workers=0 ;;
esac
worker=0
while [ "$worker" -lt "$chat_workers" ]; do
    worker=$((worker + 1))
    echo "entrypoint: starting AI chat worker loop ${worker} of ${chat_workers}"
    su -s /bin/sh -c 'exec sh /var/www/html/docker/coolify/ai-chat-worker.sh' www-data </dev/null &
done

exec docker-php-entrypoint "$@"
