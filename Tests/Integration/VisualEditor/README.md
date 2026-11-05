# Regular-runtime Visual Editor integration

Opt-in, serial, local DDEV checks against real Core, FormEngine, MariaDB,
backend sessions, modal dialogs and rendered templates. No component routes are
mocked. Use only disposable fixtures. `VEE_RUNTIME_ROOT` is the MAIN runtime;
the lab worktree owns these test sources. The regular package candidates must
already be linked with `Build/Scripts/lab-link.sh`; never inject an autoloader.

The PHP helper runs from MAIN after being copied into its web container as
`/tmp/ve-runtime.php`. Its modes are `inspect`, `fixture`, `extra`, `visible`,
`settings`, `version`, `cleanup`. Run `fixture`, `extra`, `visible` once, then
flush the TYPO3 cache. It creates a page below showcase UID 8430, page-scoped
editor group, two disposable users and a workspace using Core DataHandler.
Generated credentials stay in `var/ve-integration/fixture.json` (0600). Copy
that file privately from the container to MAIN's same ignored directory.
`settings <admin|editor> <tabs|sections|disabled> <workspace-id>` touches those
fixtures only. `version` provisions complete drafts through stock Core before
the workspace test's live snapshot; metadata changes during this fixture setup
are not an acceptance pass for first-version frontend creation.

Authenticate the disposable admin through the classic backend login using the
fixture's generated password, then save Playwright storage state privately.
Never substitute an existing user's password or alter their preferences.

Set `VEE_RUNTIME_ROOT`, `VEE_AUTH_STATE`, optionally `VEE_RUNTIME_FIXTURE` and
`PLAYWRIGHT_BROWSERS_PATH`. Run with MAIN's Playwright dependency and the config
in this directory. If the worktree has no node_modules, copy this directory to
an ignored temporary directory and link MAIN's node_modules there. The final
8-case run uses the same source as the committed suite.

```sh
VEE_RUNTIME_ROOT=/absolute/MAIN VEE_AUTH_STATE=/private/disposable-auth.json \
  /absolute/MAIN/node_modules/.bin/playwright test \
  --config=/temporary/test-copy/playwright.config.mjs
```

The suite changes only fixture records, restores fixture preferences and never
retries mutation requests automatically. The library compatibility case tests
the real library persistence protocol; it does not automate catalog tile drag
and drop. The text persistence cases check normal child saves through the real
endpoint and the real Core FormEngine, retaining hidden=1. Hidden entries
intentionally have no anonymous frontend editable.

After tests, run `cleanup` through the helper. It discards the fixture's drafts,
soft-deletes the page, group/workspace/users and disables both users. Check the
reported remaining page-owned records; remove the private credential and auth
files, invalidate only those users' sessions and flush the TYPO3 cache. Keep
showcase pages 8430–8433 and unrelated content intact. The helper refuses to
reuse an existing fixture state file.
