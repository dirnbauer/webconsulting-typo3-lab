#!/usr/bin/env bash
# OPcache's file cache needs its directory to exist before PHP starts; PHP will
# populate it but never creates it. It lives under /tmp so it stays inside the
# container and out of the Mutagen sync, and it is world-writable because
# php-fpm runs as www-data while CLI runs as the mapped host user.
#
# See opcache.file_cache in .ddev/php/performance.ini for why this is here.
mkdir -p /tmp/opcache
chmod 1777 /tmp/opcache
