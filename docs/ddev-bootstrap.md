# DDEV Bootstrap — Database and Fileadmin

Reproducible first-time setup for the Webconsulting TYPO3 Lab using DDEV.

The lab needs **two artifacts** besides Composer dependencies:

| Artifact | Source | Local path | Purpose |
|---|---|---|---|
| Database dump | Git repository (project root) | `dump.sql.gz` | Full MariaDB state after cleanup |
| Fileadmin archive | [curt.at](https://curt.at/downloads/typo3-lab/fileadmin-v1.2.0.tar.gz) | `.tarballs/fileadmin.tar.gz` | `public/fileadmin/` assets (FAL, uploads, demo media) |

### Hosted fileadmin archive (curt.at)

| | |
|---|---|
| **Public URL** | https://curt.at/downloads/typo3-lab/fileadmin-v1.2.0.tar.gz |
| **Server path** | `public_html/sites/curt.at/public/downloads/typo3-lab/fileadmin-v1.2.0.tar.gz` |
| **Size** | ~122 MB |
| **Save as** | `.tarballs/fileadmin.tar.gz` before `ddev import-files` |

Download and verify:

```bash
mkdir -p .tarballs
curl -L -o .tarballs/fileadmin.tar.gz \
  https://curt.at/downloads/typo3-lab/fileadmin-v1.2.0.tar.gz
curl -sI https://curt.at/downloads/typo3-lab/fileadmin-v1.2.0.tar.gz | head -1
# Expected: HTTP/2 200 (redirects to www.curt.at)
```

## First-Time Import (new machine)

```bash
git clone https://github.com/dirnbauer/webconsulting-typo3-lab.git
cd webconsulting-typo3-lab

cp config/system/settings.php.example config/system/settings.php
# Optional secrets: .ddev/config.local.yaml (see root README)

ddev start
ddev composer install
ddev npm ci

# Database (from dump.sql.gz in the git repository)
ddev import-db --file=dump.sql.gz

# Fileadmin (download from curt.at — required for images, PDFs, Desiderio assets)
mkdir -p .tarballs
curl -L -o .tarballs/fileadmin.tar.gz \
  https://curt.at/downloads/typo3-lab/fileadmin-v1.2.0.tar.gz
ddev import-files --source=.tarballs/fileadmin.tar.gz

ddev typo3 extension:setup
ddev vite build
ddev typo3 sitepackage:seed-workos-frontend
ddev typo3 cache:flush
ddev typo3 site:list
```

Open the backend:

```bash
ddev launch /typo3/
```

Demo credentials (from root README): `admin` / `Demo123*`

Without the fileadmin archive, pages render but FAL references break (images,
downloads, Powermail uploads). Regenerate locally with the export command below
if you maintain the lab.

WorkOS credentials and provisioning IDs are optional for the rest of the lab.
To exercise authentication, configure the five `TYPO3_WORKOS_*` environment
variables described in
[workos-frontend-plugins.md](workos-frontend-plugins.md), then restart DDEV.

## Vault secrets on a new machine

The database snapshot contains nr-vault ciphertext, encrypted per-secret keys,
and non-secret identifiers. It does **not** contain the Vault master key.
nr-vault derives that key from TYPO3's `encryptionKey`; this lab reads it from
the machine-local `TYPO3_ENCRYPTION_KEY` environment variable in
`config/system/settings.php`. The local settings file and DDEV
`config.local.yaml` are ignored by Git.

Generate a different key for every independently administered installation:

```bash
openssl rand -hex 48
```

Add the generated value only to the new machine's ignored
`.ddev/config.local.yaml`, then restart DDEV:

```yaml
web_environment:
  - TYPO3_ENCRYPTION_KEY=PASTE_THE_NEW_96_CHARACTER_VALUE_HERE
```

```bash
ddev restart
```

The imported Vault values are intentionally unreadable with this new key.
Enter replacement API keys in **Admin Tools → Vault**, then select the new
Vault identifier in the corresponding nr-llm provider or other integration.
Do not copy `config/system/settings.php`, `.ddev/config.local.yaml`, or
`TYPO3_ENCRYPTION_KEY` from the source machine.

If the installation is intentionally being migrated with its secrets, transfer
the original encryption key separately through an approved secret channel.
Possession of both the database dump and that key makes the Vault values
decryptable.

## Export (maintainers — refresh lab snapshot)

Run after site cleanup, content edits, or before a release that updates `dump.sql.gz`.

### Database

```bash
ddev export-db --file=dump.sql.gz
```

Writes a gzip-compressed SQL dump of the `db` database to the project root.
The dump contains only encrypted nr-vault payloads. Never distribute the
source machine's `TYPO3_ENCRYPTION_KEY` with it.

### Fileadmin

DDEV has `import-files` but no `export-files` command. Archive `public/fileadmin`
manually:

```bash
mkdir -p .tarballs
tar -czf .tarballs/fileadmin.tar.gz -C public fileadmin
```

The archive contains the **contents** of `fileadmin/` (not a nested `fileadmin`
folder). `ddev import-files` extracts into TYPO3's default upload directory
(`public/fileadmin` for project type `typo3`).

### Post-export verification

```bash
ddev typo3 lint:yaml config/sites
ddev typo3 site:list
ddev typo3 cache:flush

for url in / /blog/ /typo3-blog/ /mtug-camp-munich-2026/ \
  /mtug-camp-munich-2026/ticket-anmeldung \
  /features/workos/frontend-plugins/ \
  /features/workos/frontend-plugins/login/ \
  /features/workos/frontend-plugins/account-center/ \
  /features/workos/frontend-plugins/team-administration/; do
  ddev exec curl -k -s -o /dev/null -w "$url %{http_code}\n" \
    "https://webconsulting-typo3-lab.ddev.site$url"
done
```

Commit `dump.sql.gz` when the database snapshot should ship with the repo. Upload
a refreshed fileadmin archive to curt.at:

```bash
scp -P 222 .tarballs/fileadmin.tar.gz \
  curtaa@www.curt.at:public_html/sites/curt.at/public/downloads/typo3-lab/fileadmin-v1.2.0.tar.gz
```

| | |
|---|---|
| **Server path** | `public_html/sites/curt.at/public/downloads/typo3-lab/fileadmin-v1.2.0.tar.gz` |
| **Public URL** | https://curt.at/downloads/typo3-lab/fileadmin-v1.2.0.tar.gz |

## Database-Only Refresh

To reset the database without touching fileadmin:

```bash
ddev import-db --file=dump.sql.gz
ddev typo3 extension:setup
ddev typo3 sitepackage:seed-workos-frontend
ddev typo3 cache:flush
```

## Full Reset (database + files)

```bash
ddev import-db --file=dump.sql.gz
ddev import-files --source=.tarballs/fileadmin.tar.gz
ddev typo3 extension:setup
ddev typo3 sitepackage:seed-workos-frontend
ddev typo3 cache:flush
```

`import-files` **replaces** the target upload directory.

## TYPO3 Cleanup Before Export

Before exporting a clean snapshot, purge soft-deleted records and orphans:

```bash
ddev typo3 cleanup:deletedrecords -n
ddev typo3 cleanup:orphanrecords -n
ddev typo3 cache:flush
```

`cleanup:deletedrecords` permanently removes all rows with `deleted=1`.
`cleanup:orphanrecords` removes records that lost their page-tree connection.

Then export database and fileadmin as above.

## Related Documentation

- [../README.md](../README.md) — DDEV prerequisites, secrets, local URLs
- [site-configuration.md](site-configuration.md) — active site configs and troubleshooting
- [mcp-clients.md](mcp-clients.md) — local Codex, Claude Code, and Cursor setup
- [workos-frontend-plugins.md](workos-frontend-plugins.md) — WorkOS configuration and plugin demo
