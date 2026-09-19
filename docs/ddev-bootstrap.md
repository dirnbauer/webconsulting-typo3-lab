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

## Published snapshot

`dump.sql.gz` and `.tarballs/fileadmin.tar.gz` stay private, and
`Build/Scripts/make-bootstrap-pair.sh` exports that pair in one run.

What may be handed out is a different artifact, built by
`Build/Scripts/make-public-snapshot.sh`:

```bash
Build/Scripts/make-public-snapshot.sh
```

It writes `db-public.tar.gz`, `fileadmin-public.tar.gz`, `snapshot-readme.txt`
and a copy of `Build/Scripts/install.sh` (published as `install.txt`) into
`public/fileadmin/_downloads/`, which the *Downloads* page (`/downloads/`)
links to. `public/fileadmin` is a bind mount rather than part of the Mutagen
sync, so serving 350 MB from there costs the file watcher nothing.

Those extensions are deliberate. TYPO3's shipped `public/.htaccess` denies
`.sh` and `.sql*` from the document root, and that file travels with the
repository while the basic auth that would justify an exemption does not — so
the artifacts are named to clear the stock rule instead of weakening it. `ddev
import-db` reads the tar archive natively, and the installer runs with `bash
install.txt`.

Every table whose name matches `vault|secret|token|credential|oauth|identity|
payment_log|_provider$`, both user tables and the log and history tables, keeps
its structure and ships with no rows; a single `admin` / `Demo123*` account is
inserted in their place. Matching on the name means a credential table added by
a future extension is stripped by default instead of silently shipping. Verify
before publishing:

```bash
tar -xzOf public/fileadmin/_downloads/db-public.tar.gz db-public.sql \
  | grep -c "INSERT INTO \`be_users\`"
```

One INSERT is expected: the demo administrator.

The deployed lab is behind HTTP basic auth (`<Location />` in
`docker/coolify/apache-vhost.conf`), so these downloads are reachable only with
those credentials, and the installer prompts for them. `public/.htaccess` is
untouched: an earlier version of this carried an exemption for the download
path, which worked but only stayed safe as long as that `<Location />` block
existed, and it is the `.htaccess` that ships with a clone, not the vhost.

Worth knowing if that ever gets revisited: a grant in the vhost cannot lift a
deny written in `.htaccess`. Authorization from `.htaccess` is merged last, and
a `<Location>` grant does not override it — verified by testing, where a
`<Location>` *deny* took effect immediately while a `<Location>` *grant* on the
same path stayed 403. The exemption can only live in `.htaccess`, or not exist.

`public/fileadmin` is git-ignored and a deployment ships code only, so neither
the artifacts nor the *Downloads* page record travel with a push. For the live
site there is a matching command that builds the snapshot from the **live**
database and fileadmin, on the server:

```bash
Build/Scripts/sync-coolify.sh publish-snapshot --confirm
```

It reads production and writes only inside `public/fileadmin/_downloads`, so it
adds the downloads without touching existing content — unlike `push`, which
replaces the production database wholesale. The sanitiser matches table names
using the same pattern as the local script, because both source
`Build/Scripts/lib/public-snapshot.sh`; the rule that decides what is safe to
publish is defined once, in that file.

Sanitisation is checked against the *local* database, whose demo content is
known to be synthetic. Before announcing the links, confirm the published dump
from the live site carries exactly one `INSERT` — the demo administrator — and
satisfy yourself that the remaining live content is meant to be public:

```bash
curl -u lab:PASSWORD -fsSL \
  https://typo3-lab.webconsulting.at/fileadmin/_downloads/db-public.tar.gz \
  | tar -xzO db-public.sql | grep -c "INSERT INTO .be_users."
```

The *Downloads* page itself is a database record and exists only in DDEV; the
live site needs it created there, or brought over by a deliberate data move.
