#!/usr/bin/env bash
# Shared definition of what a publishable snapshot is.
#
# Sourced by Build/Scripts/make-public-snapshot.sh (local DDEV) and by the
# publish-snapshot action of Build/Scripts/sync-coolify.sh (Coolify). The two
# run against different databases through different transports, and the one
# thing that must never differ between them is which tables get emptied - so
# that list lives here and nowhere else.

# Any table whose NAME matches this is published with structure but no rows.
# Matching on the name rather than a fixed list means a credential table added
# by a future extension is stripped by default instead of silently shipping.
#
# The second half covers what people wrote rather than what authenticates
# them: form submissions, blog comments, chat and agent conversations, run and
# audit logs, notes, and the demo shop's orders and leads. Caches and the
# orphaned indexed_search tables go too: they hold rendered copies of pages,
# including pages deleted since, and they rebuild themselves.
SNAPSHOT_SENSITIVE_PATTERN='vault|secret|token|credential|oauth|identity|payment_log|_provider$|^fe_users$|^be_users$|^be_sessions$|^fe_sessions$|^sys_log$|^sys_history$|^sys_note$|^tx_powermail_domain_model_(mail|answer)$|^tx_blog_domain_model_comment$|^tx_[a-z0-9_]+_(conversation|message|run|audit|audit_log)$|^tx_webconmcpchatbridge_|^tx_agentnexus_(ucp_order|ucp_order_log|a2ui_inquiry|a2a_request|agui_lead)$|^cache_|^index_(config|debug|fulltext|grlist|phash|rel|section|stat_word|words)$'

# Fileadmin paths the published files archive never contains: uploads made
# through the AI chat, and personal documents (invoices, tax reports, a CV)
# that were uploaded while testing. A plain name is a top-level folder; the
# rest are make-zip.php patterns over the relative path. Space-separated, and
# expanded with globbing switched off by both callers.
SNAPSHOT_PRIVATE_PATHS='ai-chat *Lebenslauf* *gas_20*.pdf *rechnung* *Steuerbericht* *invoice*'

# One table whose live rows are filtered further. Page 15 holds the "Bad Shop
# Watch" candidates another project collected in tt_address: scraped shop
# imprints with names, addresses and e-mail addresses. They are not demo
# content and never go into the published dump. No quotes in the condition:
# it passes through ssh, sh -c and ddev exec.
SNAPSHOT_ROW_FILTER_TABLE='tt_address'
SNAPSHOT_ROW_FILTER_WHERE='deleted=0 AND pid<>15'

# The one account the published database keeps. The password is in the
# documentation already; it is a demo login, not a secret.
SNAPSHOT_DEMO_USER="admin"
SNAPSHOT_DEMO_PASSWORD="Demo123*"

# Published filenames, picked to survive two separate Apache rules.
#
# TYPO3's shipped public/.htaccess denies .sh and .sql* from the document root,
# so the dump cannot ship as .sql.gz and the installer cannot be .sh. Renaming
# around that keeps the rule intact for every deployment of this repository,
# including ones without the basic auth that guards this lab.
#
# Zip rather than tar.gz because Apache serves any .gz with
# "Content-Encoding: gzip", which HTTP clients transparently decode: a browser
# saved a file named db-public.tar.gz that was really a plain tar, and
# `ddev import-db` rejects that. .zip carries no Content-Encoding, arrives
# byte-for-byte, and both import-db and import-files read it.
SNAPSHOT_DB_ARCHIVE="db-public.zip"
SNAPSHOT_DB_MEMBER="db-public.sql"
SNAPSHOT_FILES_ARCHIVE="fileadmin-public.zip"
SNAPSHOT_INSTALLER="install.txt"
SNAPSHOT_README="snapshot-readme.txt"
SNAPSHOT_DIR_NAME="_downloads"

snapshot_readme() {
    # $1 origin label, $2 revision, $3 timestamp
    cat <<EOF
Public snapshot of the Webconsulting TYPO3 Lab
Exported $3 from $1, git revision $2

  ${SNAPSHOT_DB_ARCHIVE}             database, sanitised (ddev import-db reads it directly)
  ${SNAPSHOT_FILES_ARCHIVE}      matching Fileadmin contents
  ${SNAPSHOT_INSTALLER}              scripted setup: bash ${SNAPSHOT_INSTALLER}

Sign in with ${SNAPSHOT_DEMO_USER} / ${SNAPSHOT_DEMO_PASSWORD} and change it.

Sanitised means: Vault secrets, API and MCP access tokens, OAuth rows, WorkOS
identities, the LLM provider credential, all backend and frontend accounts, the
log/history tables, form submissions, comments, chat and agent conversations
and run logs keep their structure but ship with no rows. Fileadmin ships
without AI chat uploads and personal documents. Import both archives together -
the database references Fileadmin files by uid.
EOF
}
