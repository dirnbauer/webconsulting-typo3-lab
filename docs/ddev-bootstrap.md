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

# Database (from dump.sql.gz in the git repository)
ddev import-db --file=dump.sql.gz

# Fileadmin (download from curt.at — required for images, PDFs, Desiderio assets)
mkdir -p .tarballs
curl -L -o .tarballs/fileadmin.tar.gz \
  https://curt.at/downloads/typo3-lab/fileadmin-v1.2.0.tar.gz
ddev import-files --source=.tarballs/fileadmin.tar.gz

ddev typo3 extension:setup
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

## Export (maintainers — refresh lab snapshot)

Run after site cleanup, content edits, or before a release that updates `dump.sql.gz`.

### Database

```bash
ddev export-db --file=dump.sql.gz
```

Writes a gzip-compressed SQL dump of the `db` database to the project root.

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

for url in / /blog/ /typo3-blog/ /mtug-camp-munich-2026/ /mtug-camp-munich-2026/ticket-anmeldung; do
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
ddev typo3 cache:flush
```

## Full Reset (database + files)

```bash
ddev import-db --file=dump.sql.gz
ddev import-files --source=.tarballs/fileadmin.tar.gz
ddev typo3 extension:setup
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
