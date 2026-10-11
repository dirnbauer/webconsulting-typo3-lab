# Personal Agent Protocol in TYPO3 Lab

The lab implements the **Poppy draft 0.1 signed-out knowledge profile**, based
on the [official specification](https://personalagentprotocol.org/docs/spec)
and [Sierra announcement](https://sierra.ai/blog/poppy) of 9 October 2026.
The independent extension is `webconsulting/typo3-poppy`, in
[our GitLab](https://gitlab.webconsulting.at/extensions/typo3-poppy).

The repository is private. GitHub CI uses the read-only project deploy key in
`GITLAB_EXTENSIONS_READ_KEY`. Coolify uses the same key as a build-only Docker
secret, with **Use Docker Build Secrets** enabled. The Dockerfile mounts it only
during Composer installation; the private key is absent from image layers.
The key has `is_runtime=false` and is absent from service `environment`
declarations. Coolify substitutes saved values even for explicit empty entries,
so an empty Compose override would expose the key at runtime. Its generated
runtime `.env` respects `is_runtime=false`. The build context excludes `.env`.
Both Dockerfiles use syntax 1.10 because Coolify injects secret environment
mounts into their build steps; this syntax supports those mounts.
Coolify trims the key's final newline. The Composer step restores it in a
temporary memory mount, so OpenSSH can read the key and no key file enters an
image layer.
The trusted GitLab host key is pinned in `Build/SSH/known_hosts`. Local builds
can instead forward an SSH agent with `docker build --ssh default`.

## Try it online

Open `/poppy/demo` after the lab login. Select Poppy or Pi Durable, click
**Ask TYPO3 Lab**, then try **Renew session** and **Test replay protection**.
The request trace shows actual HTTP outcomes without credentials. This is a
protocol playground; Cloudflare model inference belongs to the separate Pi
Durable example and requires a Cloudflare account.

The lab enables `poppy.playground: true`. This registers only its own demo
caller and exposes public metadata/JWKS; assertions remain behind the lab login
and require an HttpOnly visitor cookie, a CSRF token and the canonical Origin.
Its signing key persists in `var/poppy/identity.json`, outside the web root.
HTTP Basic Auth exempts the exact protocol paths because DPoP uses the same
Authorization header. The Apache rule checks the original request line so the
exception survives TYPO3's internal rewrite to `index.php`. Pages, the
playground and its assertion route retain Basic.
Only the knowledge endpoint accepts a simple `topic` query on public protocol
routes. Other query strings retain Basic Auth so TYPO3's earlier eID handler
cannot bypass the lab login through a protocol URL.

## Native content

| Page | Native slug |
| --- | --- |
| Feature, protocol explanation and example answer | `/agent-nexus/poppy/` |
| Pi Durable personal agent example | `/agent-nexus/poppy/pi-durable/` |
| Installation, editorial workflow, errors and testing | `/agent-nexus/poppy/help/` |

Poppy is the sixth protocol in the Agent Nexus field guide. Its native page contains purpose, practical uses, the request flow, endpoint explanations, scope and comparisons with the other five protocols, followed by a sourced summary of Sierra's draft announcement: the draft at a glance, the five building blocks (each marked with what the lab implements), the standards it builds on, Sierra's three examples, next steps and a numbered source list. Those sections paraphrase the sources and link them inline; keep them in the lab's own wording when the draft changes. Pi Durable and Help are child pages. Features, Resources, Technical features, the Agent Nexus product page, overview, shared playground and docs link to this structure. The repeatable
source is `packages/site_package/Resources/Private/Data/Content/poppy/poppy.payload.json`.
The Poppy SVG diagram is imported into FAL with alternative text and a caption.
The Pi Durable flow uses the existing `desiderio_featurenumbered` Content Block
with four native `feature_numbered_items` records: Pi Durable, Poppy, TYPO3 and
Answer + source. Each title, rich-text explanation and link is editable in TYPO3.
The former Pi Durable diagram reference is removed through DataHandler; its
source file remains available in history. The collection uses a two-column
desktop layout and a single column on mobile.

```sh
ddev exec vendor/bin/typo3 extension:setup --extension=poppy
ddev exec vendor/bin/typo3 sitepackage:seed-poppy
ddev exec vendor/bin/typo3 cache:flush
```

The seeder resolves English parent pages by slug within the configured site
root, so production and local page IDs may differ. Existing Poppy pages are moved into the Agent Nexus site with DataHandler; their content and FAL references retain their IDs. Former `/poppy/`, `/features/poppy/`, its Pi Durable child and `/resources/poppy-help/` paths redirect permanently to the canonical pages. The domain-level `/poppy/demo` and protocol endpoints remain stable. It then uses the existing content payload command and applies
DataHandler move commands to establish the intended reading order. TYPO3
manages sort values itself; direct sorting-field values in an import are not
an ordering contract. Reapply this lab-owned seeder after the general
Desiderio seeder. It preserves unrelated content and updates its own records.
For production, deploy the code first, then run
`Build/Scripts/sync-coolify.sh publish-poppy --confirm`. This backs up the
production database and Fileadmin before calling the seeder with
`--allow-production`; it does not replace the production database.

The main Poppy page uses existing Desiderio content sidebars, numbered features,
definition lists, content grids, use-case grids, a highlighted callout and resource
lists. Its 36 repeatable items are native records linked through the payload's
`_collection` declarations; element templates, fields and layouts remain unchanged.
The diagram keeps its existing FAL reference. Content distinguishes agent identity
from customer-account identity, session assertions from authenticated knowledge
requests, and Account Token revocation from guest Session Tokens.

The knowledge API reads the current published `text` record headed
**What TYPO3 Lab says** on the selected page. Editing that record in the backend
changes the next API answer. Update the payload as well when changing seeded
copy, so reseeding preserves the edit. Keep this record's `CType=text`, heading and
`bodytext` storage: the API selects that exact source, while the surrounding sections
use Desiderio. The Agent Nexus field guide and API sources are English.

## Protocol configuration

Only the root Desiderio site enables `poppy`. Its configuration defines two
allowlisted topics with an explicit `site: agent-nexus` publication boundary, and starts with no external trusted personal-agent clients. The optional playground registers its own caller. Register
an actual caller by adding its public HTTPS `/agent.json` URL to
`poppy.allowedClients`. The extension checks its metadata and public JWKS;
private keys stay with the caller.

The domain-wide discovery document is `/.well-known/poppy.json`. Its issuer is
`https://host/poppy`, with metadata at
`/.well-known/oauth-authorization-server/poppy` following RFC 8414. This avoids
the existing host-level MCP OAuth metadata. Poppy tokens do not authenticate
MCP calls or TYPO3 users.

The guest-session API is described by `/poppy/openapi.json`. Sessions carry no
account scopes and remain signed out. A copied Session Token does not work
without the bound DPoP private key. Publication, access, ancestor and paid-page
checks protect selected content. Other channels and sign-in flows are not
advertised. See the extension README for the complete contract and limits.

## Pi Durable example

The user's [linked post](https://x.com/irvinebroque/status/2108673648999723482)
points to [Brendan Irvine-Broque's demo](https://github.com/irvinebroque/poppy-demo).
Our example adapts the personal-agent/company split to native TYPO3 content:

1. A Pi Durable tool discovers TYPO3 Lab and verifies the issuer.
2. Its private WebCrypto transport opens or renews a verified guest session.
3. It requests the `poppy` or `pi-durable` topic through the OpenAPI channel.
4. The tool returns published text and a source URL to the model.

`packages/poppy/Examples/pi-durable` has pinned dependencies, a Cloudflare
Agents SDK `PiHarness` integration, durable private key/token storage, public
client metadata and JWKS, transport tests and setup instructions. Its Worker
requires authenticated, user-scoped application routing before exposing agent
submissions. Cloudflare deployment and live model inference are separate from
local protocol verification and have not been run for this change.

## Verification

```sh
ddev exec packages/poppy/Build/Scripts/runTests.sh -s ci -p 8.4
Build/Scripts/test-poppy.sh
ddev exec npx playwright test Tests/E2E/poppy.spec.js
cd packages/poppy/Examples/pi-durable
npm ci
npm test
npm run check
npm run build
```

The DDEV integration script temporarily registers a generated identity, keeps
private keys outside Fileadmin, tests authenticated discovery, both answers,
renewal after private-state restoration, proof/assertion replay, key binding,
scope escalation, cross-user session renewal and anonymous access. It also
hides and edits a native content element through DataHandler, verifies the API
behaviour and restores the original text. Its exit trap restores the site
allowlist and removes test identity files.

Browser checks cover all three pages on desktop/mobile, one h1, loaded images,
no horizontal overflow and no browser exceptions. Captures freeze decorative
animations so the full page shows every explanatory section.

Local verification covers 103 lab unit tests, 41 extension tests, seven Pi
transport tests, 17 authenticated integration checks and 12 browser checks,
including the interactive playground. PHPStan covers the seeder and playground.
Composer/YAML validation, Pi TypeScript checks, the Wrangler dry build and
Dockerfile build checks are also run. The complete local browser suite passes
32 of 36 checks: the four failures concern the existing Records List and blog
pages using screen-reader-only headings where the tests expect visible
`data-slot=typography` headings. The Poppy checks all pass.

The deployment gate found three existing SVG sanitizer advisories. TYPO3 was
patched from 14.3.7 to 14.3.8, which permits `enshrined/svg-sanitize` 1.0.0; the
updated lockfile has no Composer security advisories. The root Node lockfile
also updates five vulnerable transitive packages within their existing version
ranges; its audit is clean.

A database snapshot was taken before content creation:
`before-poppy-20261010`. No production database or Cloudflare deployment was
changed by these local content and protocol tests.

## Agent Nexus integration verification (11 October 2026)

- Poppy 0.2.1: 45 PHP unit tests, including cross-site publication boundaries and canonical source URLs.
- Lab: 103 unit tests, PHPStan level 8, YAML/lint, Composer audit, frontend build and deployment pins.
- 17 authenticated protocol integration checks read the moved native content and verify publication changes, renewal and replay rejection.
- 18 Poppy browser cases cover desktop/mobile content, six overview cards, cross-links, redirects, guest sessions and the deployed Basic Auth boundary (the two deployment-only cases skip on DDEV).
- Reapplying the native content seeder reports no content changes or duplicate records.
