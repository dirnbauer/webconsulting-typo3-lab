# Webconsulting TYPO3 Lab

A DDEV-powered TYPO3 14.3 feature lab for Desiderio, frontend editing,
search, content APIs, agent workflows, authentication, and extension demos.

[GitHub repository](https://github.com/dirnbauer/webconsulting-typo3-lab) ·
[Project documentation](docs/README.md) ·
[WorkOS frontend plugins](docs/workos-frontend-plugins.md)

## Quick start

Requirements: Docker, DDEV `>=1.25.2`, and Git.

```bash
git clone https://github.com/dirnbauer/webconsulting-typo3-lab.git
cd webconsulting-typo3-lab
cp config/system/settings.php.example config/system/settings.php

ddev start
ddev composer install
ddev import-db --file=dump.sql.gz

mkdir -p .tarballs
curl -L -o .tarballs/fileadmin.tar.gz \
  https://curt.at/downloads/typo3-lab/fileadmin-v1.2.0.tar.gz
ddev import-files --source=.tarballs/fileadmin.tar.gz

ddev typo3 extension:setup
ddev typo3 sitepackage:seed-workos-frontend
ddev typo3 cache:flush
```

Open `https://webconsulting-typo3-lab.ddev.site/typo3/`. The imported local
demo account is `admin` / `Demo123*`.

The database is versioned as `dump.sql.gz`; fileadmin is distributed separately
because of its size. See [DDEV bootstrap](docs/ddev-bootstrap.md) for reset and
export procedures.

## Runtime

| Component | Configuration |
|---|---|
| TYPO3 | `^14.3` |
| PHP | `8.3` |
| DDEV | TYPO3 project, Apache FPM, Mutagen |
| Database | MariaDB `10.11` |
| Search | TYPO3 Solr DDEV add-on |
| Local URL | `https://webconsulting-typo3-lab.ddev.site` |

Local secrets belong in the ignored `config/system/settings.php` or DDEV
environment variables. The committed `settings.php.example` contains only
environment lookups and non-secret defaults.

`nr-vault` secrets in a database dump are not portable by themselves. The
Vault master key is derived from the machine-local `TYPO3_ENCRYPTION_KEY`, which
must never be committed or copied with a public dump. A new machine should use
a newly generated TYPO3 encryption key and enter its own Vault values. See
[DDEV bootstrap](docs/ddev-bootstrap.md#vault-secrets-on-a-new-machine).

## Demo sites

| Site | Path | Root page |
|---|---|---:|
| Desiderio | `/` | `505` |
| Camino | `/camino/` | `99` |
| Blog | `/blog/` | `15` |
| Blog Bootstrap | `/14/` | `69` |
| TYPO3 Blog | `/typo3-blog/` | `390` |
| Desiderio corporate starter | `/desiderio-corporate-starter/` | `740` |
| MTUG Camp Munich 2026 | `/mtug-camp-munich-2026/` | `933` |

The canonical inventory, languages, Site Set dependencies, and troubleshooting
live in [Site configuration](docs/site-configuration.md).

## WorkOS frontend plugin lab

The Desiderio site includes one overview and three focused plugin pages:

| Surface | URL | CType |
|---|---|---|
| Overview | `/features/workos/frontend-plugins/` | navigation and explanatory content |
| Login and registration | `/features/workos/frontend-plugins/login/` | `workosauth_login` |
| Account center | `/features/workos/frontend-plugins/account-center/` | `workosauth_account` |
| Team administration | `/features/workos/frontend-plugins/team-administration/` | `workosauth_team` |

The lab keeps `webconsulting/workos-auth` as the behavior and security owner.
The local site package overrides only Fluid presentation and CSS. The result
uses Desiderio's semantic shadcn token system and responds to the active
`lagoon` preset without editing vendor files.

See [WorkOS frontend plugins](docs/workos-frontend-plugins.md) for WorkOS
dashboard redirects, environment variables, page UIDs, rendering architecture,
expected states, and verification.

## Local site package

`packages/site_package` is version `14.3.4` and provides six TYPO3 Site Sets:

| Site Set | Purpose |
|---|---|
| `webconsulting/site-package` | Base editor, Admin Panel, RTE, middleware, and MCP defaults |
| `webconsulting/site-package-search` | Solr defaults and numbered pagination |
| `webconsulting/site-package-blog` | Desiderio standalone Blog rendering |
| `webconsulting/site-package-blog-bootstrap` | Bootstrap Blog demo rendering |
| `webconsulting/site-package-camino` | Camino demo rendering |
| `webconsulting/site-package-workos` | Lab-only WorkOS Fluid overrides, plugin bridge, and CSS |

Desiderio corporate sites use the page templates supplied by Desiderio's
shadcn Site Set. Per-site style, icon, and preset values remain in
`config/sites/*/settings.yaml`.

## Dependency policy

Composer resolves the lab-owned `site_package` from its local path repository.
Other public integrations resolve from Packagist or their declared VCS
repositories, so a clean checkout no longer depends on sibling directories or
CI-time repository cloning. The private desktop connector is not a required lab
dependency and may be installed locally when its repository credentials are
available.

After dependency changes, run:

```bash
ddev composer update <package> --with-all-dependencies
ddev typo3 extension:setup
ddev typo3 cache:flush
Build/Scripts/runTests.sh -s ci
```

## Verification

```bash
ddev composer validate --strict --no-check-publish
ddev composer audit
Build/Scripts/runTests.sh -s ci
ddev typo3 lint:yaml config/sites packages/site_package/Configuration/Sets
ddev typo3 site:list
ddev typo3 sitepackage:seed-workos-frontend
ddev typo3 cache:flush
```

Frontend smoke test:

```bash
for url in / /camino/ /blog/ /14/ /typo3-blog/ \
  /desiderio-corporate-starter/ /mtug-camp-munich-2026/ \
  /features/workos/frontend-plugins/ \
  /features/workos/frontend-plugins/login/ \
  /features/workos/frontend-plugins/account-center/ \
  /features/workos/frontend-plugins/team-administration/; do
  ddev exec curl -k -s -o /dev/null -w "$url %{http_code}\\n" \
    "https://webconsulting-typo3-lab.ddev.site$url"
done
```

## Documentation

- [Project documentation index](docs/README.md)
- [WorkOS frontend plugins](docs/workos-frontend-plugins.md)
- [Site configuration](docs/site-configuration.md)
- [DDEV bootstrap and snapshots](docs/ddev-bootstrap.md)
- [Site package README](packages/site_package/README.md)
- [News API Studio](apps/news-api-studio/README.md)
- [Composer patches](patches/README.md)

## Credits

The lab started from Kanti's Visual Editor DDEV demo and builds on TYPO3,
Desiderio, Visual Editor, Solr, News, Blog, Powermail, WorkOS Auth, the
Netresearch integrations, and the other packages pinned in `composer.lock`.
