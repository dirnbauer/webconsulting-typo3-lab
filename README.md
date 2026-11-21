# Webconsulting TYPO3 Lab

A DDEV-powered TYPO3 14.3 integration lab for server-rendered design systems,
content editing, search, forms, APIs and governed agent workflows.

[Documentation](docs/README.md) ·
[Site inventory](docs/site-configuration.md) ·
[MCP clients](docs/mcp-clients.md) ·
[Poppy + Pi Durable](docs/poppy.md) ·
[GitHub](https://github.com/dirnbauer/webconsulting-typo3-lab)

## Runtime

| Component | Version or policy |
|---|---|
| TYPO3 | `14.3.12` (minimum `^14.3.12`) |
| PHP | `8.5` minimum |
| DDEV | `>=1.25.3`, Apache FPM, Mutagen |
| Database | MariaDB `10.11` |
| Node.js | `24` inside DDEV |
| npm | `12.2.1`, pinned for DDEV, CI and production builds |
| Frontend | Vite `8`, official TYPO3 plugin and asset collector |
| Search | EXT:solr `14.0.2` against Apache Solr `10.0.0` through `ddev/ddev-typo3-solr` |
| AI stack (Netresearch) | nr-llm `0.51`, nr-vault `1.10`, AI Chat (nr-mcp-agent `0.39`), Cowriter `3.28`, landing pages, browser AI; see [AI Chat](docs/ai-chat.md) |
| Forms | Powermail `14.2` and Powermail Conditions `14.0` from in2code's Early Access Packagist (token needed, see below) |
| Browser regression | Playwright + axe-core, desktop and mobile Chromium |

The Composer platform is PHP 8.5.12. Run Composer, npm and Vite inside DDEV so
native Node packages are installed for Linux rather than the host platform.

## Quick start

Requirements: Docker or OrbStack, DDEV `>=1.25.3`, Git and the two demo-data
artifacts described in [DDEV bootstrap](docs/ddev-bootstrap.md). Powermail 14
comes from in2code's Early Access Packagist: `composer install` needs the in2code
token. It lives only in Coolify (build-only variable) and in
`~/.config/in2code/eap-packagist-token` on the developer's machine, never in a
file of this repository, an `auth.json` or GitHub; Composer gets it through
`COMPOSER_AUTH` for the one command (see
[Coolify deployment](docs/coolify-deployment.md)).

```bash
git clone https://github.com/dirnbauer/webconsulting-typo3-lab.git
cd webconsulting-typo3-lab
cp config/system/settings.php.example config/system/settings.php

ddev start
export COMPOSER_AUTH="{\"http-basic\":{\"in2code.repo.packagist.com\":{\"username\":\"token\",\"password\":\"$(tr -d '\r\n' < ~/.config/in2code/eap-packagist-token)\"}}}"
# docker exec -e copies the value from this shell: never on a command line.
docker exec -e COMPOSER_AUTH -u "$(id -u):$(id -g)" -w /var/www/html "ddev-$(ddev describe -j | jq -r .raw.name)-web" composer install
unset COMPOSER_AUTH
ddev npm ci
ddev import-db --file=dump.sql.gz

# Use the matching Fileadmin archive supplied with the database dump.
ddev import-files --source=.tarballs/fileadmin.tar.gz

ddev typo3 extension:setup
ddev typo3 install:password:set --no-interaction   # prints this install's Install Tool password once
ddev vite build
ddev typo3 cache:flush
ddev typo3 sitepackage:configure-ai-examples   # nr-llm models (needs an OpenAI key in nr-vault)
ddev typo3 sitepackage:seed-ai-stack           # AI Chat, Cowriter tasks, chat tools
```

Open `https://webconsulting-typo3-lab.ddev.site/typo3/`. The imported demo
account (`admin` / `Demo123*`) is for this local DDEV installation only.

Never commit `config/system/settings.php`, `.ddev/config.local.yaml`, Vault
keys, API tokens or generated DDEV compose files.

## GitLab mirror

Every repository we maintain is mirrored to `gitlab.webconsulting.at` (group
`extensions/`, push-to-create). Release work pushes `main` and tags to both
remotes; `Build/Scripts/mirror-to-gitlab.sh` re-pushes every clone in one go.

## Design-system ownership

The two frontend systems share TYPO3 infrastructure but own separate template
and asset trees:

- [Desiderio](https://github.com/dirnbauer/desiderio) provides its shadcn/ui-
  inspired Fluid 5 components, ClassicContent renderers, page shell and
  Content Blocks.
- [Astryx for TYPO3](https://github.com/dirnbauer/astryx-typo3) provides its own
  Astryx page shell, Fluid components, templates, CSS and Content Blocks.
  It does not import React or StyleX into the browser.
- [Innesto](https://github.com/dirnbauer/innesto) extends the Desiderio element
  library under the `Webconsulting` PHP namespace.

Sites select one frontend tree through Site Set dependencies. Provider filters
such as `elementLibrary.hosts: 'desiderio,innesto,core'` prevent the two
catalogues from being mixed in an editor's element picker.

`typo3/cms-fluid-styled-content` is installed for the standalone Blog and
Camino demos. Desiderio renders classic content through its own
`Resources/Private/ClassicContent` tree. The Blog embedded in the main
Desiderio site uses `blog/integration`; the separate Blog sites use the
standalone integration.

## Frontend assets

The project uses only the official Simon Praetorius toolchain:

- `vite-plugin-typo3` discovers each extension's
  `Configuration/ViteEntrypoints.json`;
- `praetorius/vite-asset-collector` resolves the generated manifest;
- `s2b/ddev-vite-sidecar` exposes the optional development server.

There is no custom manifest reader, asset ViewHelper or server detector. The
compiled manifest is the reliable default, so pages keep their CSS when no
development process is running.

`vite.config.js` keeps `resolve.preserveSymlinks: true`: manifest keys stay
`vendor/webconsulting/<package>/…` whether `vendor/` holds a symlink
(site_package, a clone linked by `Build/Scripts/lab-link.sh`) or a real copy
(the production image). vite-asset-collector 2 no longer looks up the symlink
target, so a manifest built without it fails with "Invalid asset file".

```bash
ddev npm ci
ddev vite build
```

For HMR, add the explicit URL to the ignored `.ddev/config.local.yaml`, restart
DDEV, and start Vite in a second terminal:

```yaml
web_environment:
  - TYPO3_VITE_DEV_SERVER=https://vite-webconsulting-typo3-lab.ddev.site
```

```bash
ddev restart
ddev vite dev
```

Removing the variable returns the site to manifest mode.

## Sites

| Site | Local path | Root page |
|---|---|---:|
| Desiderio | `/` | `505` |
| Astryx for TYPO3 | `/astryx-typo3/` | `1290` |
| Camino | `/camino/` | `99` |
| Blog | `/blog/` | `15` |
| Blog demo | `/14/` | `69` |
| TYPO3 Blog | `/typo3-blog/` | `390` |
| Desiderio corporate starter | `/desiderio-corporate-starter/` | `740` |
| MTUG Camp Munich 2026 | `/mtug-camp-munich-2026/` | `933` |

The complete language and Site Set inventory lives in
[Site configuration](docs/site-configuration.md).

## Content and video policy

The shipped database, Fileadmin and default element-library seeds contain no
video content elements and no video files. Video-capable components and
generation commands remain available only as explicit, opt-in tooling. Do not
commit generated captures, audio, transcripts or browser recordings.

## MCP and agent clients

The local MCP server uses stdio and therefore needs no dedicated network port.
The committed `.mcp.json` starts it through the named DDEV project, so commands
also work when a client was opened from another directory. Codex, Claude Code
and Cursor setup and health checks are documented in
[MCP clients](docs/mcp-clients.md).

## Verification

The canonical test entrypoint supports suites through `-s` and validates a
requested PHP minor through `-p`:

```bash
ddev exec Build/Scripts/runTests.sh -s ci -p 8.5
```

Individual checks:

```bash
ddev composer validate --strict --no-check-publish
ddev composer audit
ddev exec Build/Scripts/runTests.sh -s phpstan -p 8.5
ddev exec Build/Scripts/runTests.sh -s unit -p 8.5
ddev exec Build/Scripts/runTests.sh -s frontend
ddev exec Build/Scripts/runTests.sh -s deployPins
ddev exec Build/Scripts/runTests.sh -s siteLanguages
ddev exec Build/Scripts/runTests.sh -s e2e
ddev typo3 lint:yaml config/sites packages/site_package/Configuration/Sets
ddev typo3 site:list
ddev solrctl list
```

The quality suite checks the local site package at PHPStan level 8 on
PHP 8.5 and runs its PHPUnit tests. It also validates Composer and YAML,
builds the frontend, and audits Composer/npm dependencies.

### Powermail: the local check before each merge

GitHub Actions runs the same quality suite without Powermail and Powermail
Conditions: GitHub has no copy of the in2code token (owner decision), so
`Build/Scripts/ci-drop-private-packages.php` removes the two packages, their
repository and their patches from the runner's throw-away checkout first (it
refuses to run anywhere else without `--force`). Before the drop, CI runs the
one check that needs no in2code code, `Build/Scripts/check-patch-declarations.php`:
every patch `composer.json` declares exists, is locked in `patches.lock.json`
with its sha256, no patch file is undeclared, and each is a well-formed diff.

Everything else about Powermail is one local command, against the DDEV
instance of the checkout it runs in. Run it before every merge, and always when
`composer.json`, `composer.lock`, `patches/` or the lab's Powermail templates,
labels or E2E journeys changed:

```bash
ddev check-powermail
```

| Step | What it proves |
|---|---|
| Patch declarations | the static guard above, as in CI |
| `composer validate` | composer.json and composer.lock agree |
| Installed = lock | the installed version and dist reference of both in2code packages equal composer.lock; the lock's URLs carry no credentials |
| Reinstall | `composer reinstall` from in2code's repository applies all five patches to the pristine archive (cweagans/composer-patches 2 aborts with "No available patcher was able to apply patch", exactly as in the Coolify build), then `dump-autoload` (reinstall drops TYPO3's `autoload-include.php` otherwise), opcache clear and `cache:flush` |
| Patches applied | a scratch copy of each installed package is un-patched in reverse order without fuzz, so the running code contains every patch |
| Unit tests | the lab's unit tests that touch Powermail, and webcon_jev's unit tests at the commit composer.lock pins, against this `vendor/` |
| E2E | `Tests/E2E/powermail.spec.js` (Powermail validation, Conditions with Jev routing) and the Powermail page of `frontend.spec.js`, desktop and mobile, against the instance |
| `composer audit` | including the two in2code packages |
| No token left | no file in `var/` (PHPStan's container, logs), Composer's state, `config/`, `.ddev/`, the git working tree, or the container's `/tmp` and Composer directories contains the token |

It prints one PASS/FAIL table and exits non-zero on failure (about four
minutes). It refuses to run in CI. The token is read from
`~/.config/in2code/eap-packagist-token` (or `IN2CODE_PACKAGIST_TOKEN_FILE`) and
reaches only the Composer steps, as `docker exec -e COMPOSER_AUTH`; it is never
an argument and never written. `--no-e2e`, `--no-reinstall` (offline, proves
less) and `--full-token-scan` exist; only a full run counts for the hook below.
The reinstall briefly removes Powermail from `vendor/`, so do not run it while
someone tests forms on the same instance. On a shared rehearsal instance it
takes `var/lock/rehearsal.lock` for its vendor and cache writes.

Not covered: webcon_jev's functional tests (they need the TYPO3 testing
framework, which the lab does not install) and a real form submission with mail
delivery.

**Opt-in reminder.** `Build/Scripts/install-hooks.sh` installs a git pre-push
hook (`Build/Hooks/pre-push`; `--status`, `--remove`). It stops a push whose
commits change one of the files above while the last full pass of
`ddev check-powermail` in this checkout was for other content (it compares a
fingerprint of those files). `git config lab.powermailCheck remind` only warns,
`off` silences it, `LAB_SKIP_POWERMAIL_CHECK=1 git push` skips it once. Nothing
installs it for you.

The full local quality suite (`runTests.sh -s quality`) also reaches the in2code
repository in its `composer audit` step; give it the token the way
[Coolify deployment](docs/coolify-deployment.md) describes for any Composer
command. `runTests.sh` hands the variable only to its Composer steps and removes
it for every other tool: PHPStan stores the process environment in its compiled
container under `var/phpstan`, which once put the token into a file there.

Two checks cover mistakes that stay invisible until they reach production.
`deployPins` compares the EXT:solr commit baked into the production Solr image
with the one composer.lock resolves, because the image serves the configsets
the extension creates its cores from and a mismatch only shows on the deployed
site. `siteLanguages` requests every base URL `site:list` prints, because a
language can be enabled in `config.yaml` while its site root has no visible
translation, which answers 404 for that whole branch without anything else
noticing.

The Playwright suite checks Records List, Powermail, Blog and Astryx at desktop
and mobile widths. It rejects missing or 4xx/5xx stylesheets, accidental Vite
development URLs, missing/multiple H1 elements, horizontal overflow, video
markup, console failures and serious or critical WCAG violations.

## Maintenance

After dependency changes:

```bash
ddev composer update <package> --with-all-dependencies
ddev npm install
ddev typo3 extension:setup
ddev vite build
ddev typo3 cache:flush
ddev exec Build/Scripts/runTests.sh -s ci -p 8.5
```

Do not use `git clean -fdX` in this project: ignored paths include local DDEV
configuration and database snapshots. Inspect cleanup candidates first and
remove only reproducible caches or generated outputs.

## Development clones

Own extensions are installed from their GitHub repositories. To iterate on one without a
release, keep its clone under `packages/` (the DDEV web container only sees paths inside the
project) and link it into `vendor/`:

```bash
ddev exec Build/Scripts/lab-link.sh webconsulting/typo3-abilities
ddev exec Build/Scripts/lab-link.sh --restore webconsulting/typo3-abilities
ddev exec Build/Scripts/lab-link.sh --status
```

Restore every link before `composer update` or before committing `composer.lock`; the quality
runner refuses to run while a clone is linked.

Several sessions share this checkout, and any `composer install` or `composer update` that
touches a linked package used to put its release back in `vendor/`, silently. A link is now
recorded in `.lab-links` (git-ignored, one Composer name per line), and Composer's
post-install/post-update hook runs `lab-link.sh --relink`, which puts back every recorded link
a run replaced. Only `--restore` takes a package off that list; `--status` flags a recorded
package that is not linked. CI and the Docker build have no `.lab-links`, so the hook does
nothing there. To refresh the lock without touching `vendor/` at all, use
`ddev composer update <package> --no-install`. `Build/Scripts/extension-baseline-report.sh`
prints the PHPStan level, CI, README and docs state of every own extension clone.

## Documentation

- [Documentation index](docs/README.md)
- [DDEV bootstrap and backups](docs/ddev-bootstrap.md)
- [Site configuration](docs/site-configuration.md)
- [MCP clients](docs/mcp-clients.md)
- [WorkOS frontend plugins](docs/workos-frontend-plugins.md)
- [Site-package internals](packages/site_package/README.md)

## Credits and licences

Thank you to the TYPO3 community; Simon Praetorius and the Vite integration
contributors; the DDEV and TYPO3-Solr teams; the Content Blocks, Visual Editor,
News, Blog and Powermail maintainers (in2code); Netresearch, whose AI stack
(nr-llm, nr-vault, the AI Chat, Cowriter) runs this lab's AI features; and every extension author
represented in `composer.lock`.

Special thanks go to the Astryx team, Meta Open Source, the Facebook design-
systems community and all Astryx contributors. The pinned upstream Astryx
release is MIT-licensed, copyright 2026 Meta Platforms, Inc.; the exact licence
text and pinned source commit are retained in
[Astryx for TYPO3's third-party notice](https://github.com/dirnbauer/astryx-typo3/blob/main/THIRD_PARTY_NOTICES.md).

## Personal Agent Protocol (Poppy)

`webconsulting/typo3-poppy` adds the draft 0.1 signed-out knowledge profile:
HTTPS discovery, registered agent identities, DPoP guest sessions and a
read-only API backed by published TYPO3 text. The extension has its own
[GitLab repository](https://gitlab.webconsulting.at/extensions/typo3-poppy).

After installing dependencies and running extension setup:

```bash
ddev exec vendor/bin/typo3 sitepackage:seed-poppy
ddev exec vendor/bin/typo3 cache:flush
```

Poppy is the sixth protocol in the Agent Nexus field guide. The native pages
are `/agent-nexus/poppy/`, `/agent-nexus/poppy/pi-durable/` and
`/agent-nexus/poppy/help/`; former content URLs redirect to them.
The overview, menu, shared playground and docs link this structure.
The pages include practical uses, a Poppy diagram and editable Pi Durable steps, an editorial
example answer, supported capabilities and help. The Pi Durable example
provides a pinned Cloudflare PiHarness integration; its protocol transport is
tested against the actual TYPO3 instance. See [setup and testing](docs/poppy.md).
