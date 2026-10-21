#!/bin/sh
set -eu
while IFS="$(printf '\t')" read -r destination expected source; do
  case "$destination" in /opt/solr/*.jar) ;; *) exit 1 ;; esac
  curl --fail --silent --show-error --location "$source" --output /tmp/solr-security.jar
  printf '%s  %s\n' "$expected" /tmp/solr-security.jar | sha256sum --check --strict -
  install -o solr -g solr -m 0644 /tmp/solr-security.jar "$destination"
done < /tmp/solr-library-patches.tsv
mv /opt/solr/modules/gcs-repository/lib/grpc-netty-shaded-1.65.1.jar /opt/solr/modules/gcs-repository/lib/grpc-netty-shaded-1.85.1.jar
rm /tmp/solr-security.jar /tmp/solr-library-patches.tsv
