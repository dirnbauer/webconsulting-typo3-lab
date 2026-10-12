#!/bin/sh
set -eu

# Powermail stores relative uploads/tx_powermail paths in submission records.
# Keep those paths stable, with the files on the persistent runtime volume.
project_root="${1:-/var/www/html}"
upload_storage="${project_root}/var/powermail-uploads"
upload_link="${project_root}/public/uploads/tx_powermail"

install -d -o www-data -g www-data -m 0750 "${upload_storage}"
install -d -m 0755 "${project_root}/public/uploads"

if [ -L "${upload_link}" ]; then
    if [ "$(readlink "${upload_link}")" != "${upload_storage}" ]; then
        echo "Powermail upload path points to unexpected storage: ${upload_link}" >&2
        exit 1
    fi
elif [ -e "${upload_link}" ]; then
    echo "Refusing to replace existing Powermail uploads: ${upload_link}" >&2
    exit 1
else
    ln -s "${upload_storage}" "${upload_link}"
fi
