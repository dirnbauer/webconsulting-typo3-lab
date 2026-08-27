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

mkdir -p /run/typo3-secrets /var/www/html/config/system /var/www/html/public/fileadmin /var/www/html/var

if [ ! -s /run/typo3-secrets/encryption-key ]; then
    php -r 'echo bin2hex(random_bytes(48));' > /run/typo3-secrets/encryption-key
fi

chown -R www-data:www-data /run/typo3-secrets /var/www/html/config/system /var/www/html/public/fileadmin /var/www/html/var
chmod 0700 /run/typo3-secrets
chmod 0600 /run/typo3-secrets/encryption-key
chmod 0750 /var/www/html/config/system
find /var/www/html/config/system -type f -exec chmod 0640 {} +
chmod 0755 /var/www/html/public/fileadmin /var/www/html/var

exec docker-php-entrypoint "$@"
