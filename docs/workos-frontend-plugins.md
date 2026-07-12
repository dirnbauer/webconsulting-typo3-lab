# WorkOS frontend plugins

The Desiderio lab exposes all three frontend plugins from
`webconsulting/workos-auth` and keeps the upstream authentication behavior
intact. Login and registration are two states of the same Login plugin; Account
and Team are separate plugins.

## Page tree

| Page | URL / role | Plugin CType |
|---|---|---|
| WorkOS Auth | `/features/workos/` | parent feature page |
| WorkOS frontend plugins | `/features/workos/frontend-plugins/` | overview |
| Login and registration | `/features/workos/frontend-plugins/login/` | `workosauth_login` |
| Account center | `/features/workos/frontend-plugins/account-center/` | `workosauth_account` |
| Team administration | `/features/workos/frontend-plugins/team-administration/` | `workosauth_team` |
| WorkOS frontend users | sysfolder below the root page | frontend users and their default group |

Recreate or refresh these records idempotently through TYPO3 DataHandler:

```bash
ddev typo3 sitepackage:seed-workos-frontend
```

The command updates existing matching records, creates missing records, orders
the page/content trees, and prints the resulting UIDs plus the two environment
variables needed for frontend-user provisioning. UIDs depend on the imported
database state; the command does not use raw SQL for writes.

## Extension configuration

The committed defaults in `config/system/settings.php.example` use:

| Setting | Value / source |
|---|---|
| `apiKey` | `TYPO3_WORKOS_API_KEY` environment variable |
| `clientId` | `TYPO3_WORKOS_CLIENT_ID` environment variable |
| `cookiePassword` | `TYPO3_WORKOS_COOKIE_PASSWORD` environment variable |
| `frontendStoragePid` | `TYPO3_WORKOS_FRONTEND_STORAGE_PID` environment variable (printed by the seeder) |
| `frontendDefaultGroupUids` | `TYPO3_WORKOS_FRONTEND_DEFAULT_GROUP_UIDS` environment variable (printed by the seeder) |
| `frontendSuccessRedirect` | `/features/workos/frontend-plugins/account-center` |
| `frontendCallbackPath` | `/workos-auth/frontend/callback` |
| `frontendLoginPath` | `/workos-auth/frontend/login` |
| `frontendLogoutPath` | `/workos-auth/frontend/logout` |

Put secrets in ignored local configuration. One DDEV option is
`.ddev/config.local.yaml`:

```yaml
web_environment:
  - TYPO3_WORKOS_API_KEY=sk_test_replace_me
  - TYPO3_WORKOS_CLIENT_ID=client_replace_me
  - TYPO3_WORKOS_COOKIE_PASSWORD=replace_with_a_long_random_secret
  - TYPO3_WORKOS_FRONTEND_STORAGE_PID=replace_with_the_seeded_storage_uid
  - TYPO3_WORKOS_FRONTEND_DEFAULT_GROUP_UIDS=replace_with_the_seeded_group_uid
```

Run the seeder first, copy its two numeric values into the local environment,
and restart DDEV after changing environment variables. Never commit these
values.

In the WorkOS dashboard, configure the local frontend callback as an allowed
redirect URI:

```text
https://webconsulting-typo3-lab.ddev.site/workos-auth/frontend/callback
```

WorkOS requires redirect URIs to be registered on the application and its
authorization request selects the matching URI. See the WorkOS
[application configuration](https://workos.com/docs/authkit/applications) and
[authorization URL reference](https://workos.com/docs/reference/authkit/authentication/get-authorization-url).

## Rendering architecture

`webconsulting/workos-auth` remains responsible for controllers, request
tokens, WorkOS API calls, provisioning, authorization, and routing. The lab
adds presentation in `webconsulting/site-package-workos`:

| Path | Responsibility |
|---|---|
| `Configuration/Sets/Workos/config.yaml` | Hidden lab Site Set and dependencies |
| `Configuration/Sets/Workos/setup.typoscript` | WorkOS template paths, direct CType-to-Extbase bridge, CSS include |
| `Resources/Private/FluidStyledContent/Templates/WorkosAuthPlugin.fluid.html` | Desiderio content-element wrapper |
| `Resources/Private/Extensions/WorkosAuth/Templates/` | Lab-owned Login, Account, and Team templates |
| `Resources/Private/Extensions/WorkosAuth/Partials/` | Social sign-in partial |
| `Resources/Public/Css/workos-shadcn.css` | Semantic component styling |

No vendor template is edited. The Site Set is enabled only on
`config/sites/desiderio/config.yaml`.

## shadcn style contract

The Desiderio site currently selects:

```yaml
desiderio.shadcn.style: radix-rhea
desiderio.shadcn.iconLibrary: lucide
desiderio.shadcn.preset: lagoon
```

The WorkOS stylesheet consumes semantic variables such as `--background`,
`--foreground`, `--card`, `--border`, `--primary`, `--muted`, and `--ring`.
It does not duplicate palette values, so WorkOS surfaces follow Desiderio's
light/dark and preset tokens. The implementation follows the CSS-variable model
used by shadcn's current CLI and preset workflow; see the official
[shadcn CLI documentation](https://ui.shadcn.com/docs/cli) and
[preset commands](https://ui.shadcn.com/docs/changelog/2026-04-preset-commands).

Inspect the currently installed Desiderio shadcn configuration with:

```bash
npx shadcn@latest info --json -c vendor/webconsulting/desiderio
```

## Expected states

- Login shows email/password, passwordless email code, supported social
  providers, and a sign-up link. Sign-up and verification remain within the
  same plugin.
- Account shows a clear sign-in requirement when logged out. Once linked, it
  provides profile, password, MFA, sessions, and organization membership tools.
- Team shows a clear sign-in requirement when logged out. Authorized members
  can manage invitations and open signed Admin Portal sessions.
- The layout supports keyboard focus, reduced motion, narrow viewports, and the
  active Desiderio token theme.

## Verification

```bash
ddev typo3 sitepackage:seed-workos-frontend
ddev typo3 cache:flush

for url in \
  /features/workos/frontend-plugins/ \
  /features/workos/frontend-plugins/login/ \
  /features/workos/frontend-plugins/account-center/ \
  /features/workos/frontend-plugins/team-administration/; do
  ddev exec curl -k -s -o /dev/null -w "$url %{http_code}\\n" \
    "https://webconsulting-typo3-lab.ddev.site$url"
done
```

Useful logged-out smoke expectations:

- Overview returns three subpage links.
- Login contains the `WorkOS Login` region and no horizontal overflow at 390px.
- Account says `Please sign in to manage your WorkOS account.`
- Team says `Please sign in to access the team workspace.`
- Browser console and page error collections are empty.
