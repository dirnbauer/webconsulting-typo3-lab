# Local MCP clients

The lab exposes the TYPO3 MCP server over stdio:

```bash
ddev exec -p webconsulting-typo3-lab -- \
  php vendor/bin/typo3 mcp:server
```

Stdio starts one child process per client and does not bind a port. It is the
preferred local transport because it cannot collide with another DDEV project
and does not need OAuth or a bearer token. The HTTP `/mcp` route remains useful
for authenticated remote integrations and the Flue container, but it is not
needed by local desktop clients.

The project uses `hn/typo3-mcp-server` from the pinned commit in
`composer.lock` and `logiscape/mcp-sdk-php` 2.x, aligned with MCP specification
revision 2026-07-28.

## Shared project configuration

The committed `.mcp.json` contains one project-scoped server:

```json
{
  "mcpServers": {
    "typo3-local": {
      "type": "stdio",
      "command": "ddev",
      "args": [
        "exec",
        "-p",
        "webconsulting-typo3-lab",
        "--",
        "php",
        "vendor/bin/typo3",
        "mcp:server"
      ]
    }
  }
}
```

The explicit DDEV project name makes the command independent of the client's
working directory.

## Codex

Codex stores MCP servers in its user configuration. Add the same stdio command
once if `typo3-local` is not already present:

```bash
codex mcp add typo3-local -- \
  ddev exec -p webconsulting-typo3-lab -- \
  php vendor/bin/typo3 mcp:server
codex mcp get typo3-local
```

Start a new Codex task after changing MCP configuration, then call a read-only
TYPO3 tool such as the system-information or site-list operation.

## Claude Code

Claude reads the project `.mcp.json`, but requires project approval before it
connects. From the repository root:

```bash
claude auth status
claude mcp get typo3-local
```

If authentication expired:

```bash
claude auth login
```

Restart Claude Code, approve the project-scoped `typo3-local` server, and run
`claude mcp get typo3-local` again. A healthy result says `Connected`.

If a previous rejection is cached:

```bash
claude mcp reset-project-choices
```

Then reopen the project and approve the server. Do not add secrets to
`.mcp.json`; stdio needs none.

## Cursor

Cursor uses `.cursor/mcp.json`, which mirrors the shared definition locally.
The directory is ignored so developers may keep unrelated Cursor preferences
without committing them. To add the server through the CLI instead:

```bash
cursor --add-mcp \
  '{"name":"typo3-local","command":"ddev","args":["exec","-p","webconsulting-typo3-lab","--","php","vendor/bin/typo3","mcp:server"]}' \
  --mcp-workspace
```

Reload the Cursor window after changing MCP configuration and confirm the
server is green in Settings → MCP.

## Health checks

Before diagnosing a client, verify the server itself:

```bash
ddev describe
ddev exec vendor/bin/typo3 mcp:tool:list
ddev exec vendor/bin/typo3 mcp:server --help
```

Common failures:

| Symptom | Resolution |
|---|---|
| DDEV project not found | Start the lab once with `ddev start`; keep the configured project name unchanged. |
| Server exits immediately | Run `ddev composer install`, then `ddev typo3 extension:setup`. |
| Claude shows pending approval | Approve the project server or reset project choices. |
| Claude reports an expired session | Run `claude auth login`, then restart Claude Code. |
| Cursor/Codex sees an old tool list | Restart the client after Composer or MCP configuration changes. |
| A write is denied | Inspect MCP capabilities, workspace and local safety policy; do not weaken policy merely to make a test pass. |

For any final release check, make a real read-only call from all three clients;
configuration presence alone does not prove that the subprocess can start.
