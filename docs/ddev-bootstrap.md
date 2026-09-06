# DDEV bootstrap and backups

A fresh checkout needs a matching database dump and Fileadmin archive from the
lab maintainer. Neither artifact is committed: `dump.sql.gz` and `.tarballs/`
are ignored. Keep the two exports together so TYPO3 file references match the
physical files.

| Artifact | Local path |
|---|---|
| Database export | `dump.sql.gz` |
| Matching Fileadmin export | `.tarballs/fileadmin.tar.gz` |

The [historical v1.2.0 Fileadmin archive](https://curt.at/downloads/typo3-lab/fileadmin-v1.2.0.tar.gz)
belongs to that old demo snapshot. It is not a replacement for the matching
archive of the current database.

## First-time setup

After cloning the repository and obtaining both artifacts:

```bash
cp config/system/settings.php.example config/system/settings.php
ddev start
ddev composer install
ddev npm ci
ddev import-db --file=dump.sql.gz
ddev import-files --source=.tarballs/fileadmin.tar.gz
ddev typo3 extension:setup
ddev vite build
ddev typo3 cache:flush
ddev exec Build/Scripts/runTests.sh -s ci -p 8.4
```

Open `https://webconsulting-typo3-lab.ddev.site/typo3/`. The supplied local demo
account is `admin` / `Demo123*`. PHP 8.4, Node.js 24 and the DDEV services are
configured in `.ddev/config.yaml`.

Run npm inside DDEV. Host-installed native dependencies can make the Linux
build fail with missing Rolldown bindings. `ddev npm ci` reinstalls exactly the
locked dependencies for the container platform.

## Credentials and encrypted data

`config/system/settings.php` and `.ddev/config.local.yaml` remain local. A
clone used with new credentials must receive its own TYPO3 encryption key:

```bash
openssl rand -hex 48
```

Set that new value as `TYPO3_ENCRYPTION_KEY` in the ignored local DDEV
configuration and restart DDEV. Imported Vault entries cannot be decrypted
with the new key; enter replacement provider credentials through the Vault
backend. WorkOS configuration is described in
[workos-frontend-plugins.md](workos-frontend-plugins.md).

For an intentional migration that must preserve existing credentials, transfer
the original encryption key separately through an approved secret channel.
Never distribute it with the database dump. The dump also contains account and
application data, so it is not a public release asset.

## Export a matching pair

```bash
mkdir -p .tarballs
ddev export-db --file=dump.sql.gz
tar -czf .tarballs/fileadmin.tar.gz -C public/fileadmin .
```

The archive root contains Fileadmin's contents, without another `fileadmin/`
directory. Store the pair privately with its Git revision. Publish or deploy
only through a separately authorized release procedure; see
[coolify-deployment.md](coolify-deployment.md) for the current deployment setup.

## Restore existing local data

Create a database snapshot and preserve current files before replacing them:

```bash
ddev snapshot --name before-import
mkdir -p .tarballs
tar -czf .tarballs/fileadmin-before-import.tar.gz -C public/fileadmin .
ddev import-db --file=dump.sql.gz
ddev import-files --source=.tarballs/fileadmin.tar.gz
ddev typo3 extension:setup
ddev typo3 cache:flush
```

`import-files` replaces the upload directory. For a database-only refresh,
omit that step. To undo a database import:

```bash
ddev snapshot restore before-import
```

After a restore, check `ddev typo3 site:list`, `ddev solrctl list`, and the
quality suite. Demo seed commands are opt-in maintenance tools documented in
the [site-package README](../packages/site_package/README.md); an ordinary
restore does not require reseeding or purging records.
