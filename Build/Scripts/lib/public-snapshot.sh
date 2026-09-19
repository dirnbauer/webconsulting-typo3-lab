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
SNAPSHOT_SENSITIVE_PATTERN='vault|secret|token|credential|oauth|identity|payment_log|_provider$|^fe_users$|^be_users$|^be_sessions$|^fe_sessions$|^sys_log$|^sys_history$'

# The one account the published database keeps. The password is in the
# documentation already; it is a demo login, not a secret.
SNAPSHOT_DEMO_USER="admin"
SNAPSHOT_DEMO_PASSWORD="Demo123*"

# Published filenames. These clear TYPO3's shipped public/.htaccess, which
# denies .sh and .sql* from the document root: the dump ships as a .tar.gz
# (read natively by `ddev import-db`) and the installer as .txt. Renaming
# around that rule keeps it intact for every deployment of this repository,
# including ones without the basic auth that guards this lab.
SNAPSHOT_DB_ARCHIVE="db-public.tar.gz"
SNAPSHOT_DB_MEMBER="db-public.sql"
SNAPSHOT_FILES_ARCHIVE="fileadmin-public.tar.gz"
SNAPSHOT_INSTALLER="install.txt"
SNAPSHOT_README="snapshot-readme.txt"
SNAPSHOT_DIR_NAME="_downloads"

snapshot_readme() {
    # $1 origin label, $2 revision, $3 timestamp
    cat <<EOF
Public snapshot of the Webconsulting TYPO3 Lab
Exported $3 from $1, git revision $2

  ${SNAPSHOT_DB_ARCHIVE}         database, sanitised (ddev import-db reads it directly)
  ${SNAPSHOT_FILES_ARCHIVE}  matching Fileadmin contents
  ${SNAPSHOT_INSTALLER}              scripted setup: bash ${SNAPSHOT_INSTALLER}

Sign in with ${SNAPSHOT_DEMO_USER} / ${SNAPSHOT_DEMO_PASSWORD} and change it.

Sanitised means: Vault secrets, API and MCP access tokens, OAuth rows, WorkOS
identities, the LLM provider credential, all backend and frontend accounts and
the log/history tables keep their structure but ship with no rows. Import both
archives together - the database references Fileadmin files by uid.
EOF
}
