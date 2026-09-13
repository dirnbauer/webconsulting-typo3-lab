#!/bin/sh
set -eu

if [ -z "${BASIC_AUTH_USER:-}" ] || [ -z "${BASIC_AUTH_PASSWORD:-}" ]; then
    echo "BASIC_AUTH_USER and BASIC_AUTH_PASSWORD are required" >&2
    exit 1
fi

umask 077
htpasswd -bcB /etc/apache2/typo3-lab.htpasswd "${BASIC_AUTH_USER}" "${BASIC_AUTH_PASSWORD}" >/dev/null
chown root:www-data /etc/apache2/typo3-lab.htpasswd
chmod 0640 /etc/apache2/typo3-lab.htpasswd

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

exec docker-php-entrypoint "$@"
