/**
 * Shared TYPO3 MCP connection + tool-allowlist policy.
 *
 * The Flue runtime does NOT enforce a skill's `allowed-tools` frontmatter —
 * this registry is the sidecar's enforcement point. The PHP control plane
 * computes an allowlist into the payload; here it is intersected with the
 * known registries, and WRITE tools are honored ONLY in `edit` result mode.
 */
import { connectMcpServer } from '@flue/runtime';
import type { ResultMode } from './contracts';

export const READ_TOOLS = ['GetPage', 'ReadTable', 'RenderRecord', 'GetPageTree', 'Search', 'ContentAudit'];
export const WRITE_TOOLS = ['WriteTable', 'WorkspaceReview'];

export type Typo3Connection = Awaited<ReturnType<typeof connectMcpServer>>;

export async function connectTypo3(
  payload: { mcpUrl?: string; mcpToken?: string },
  env: Record<string, string | undefined> | undefined,
): Promise<Typo3Connection> {
  const mcpUrl = payload.mcpUrl ?? env?.TYPO3_MCP_URL ?? 'http://web/mcp';
  return connectMcpServer('typo3', {
    url: mcpUrl,
    transport: 'streamable-http',
    headers: payload.mcpToken ? { Authorization: `Bearer ${payload.mcpToken}` } : undefined,
  });
}

/**
 * Resolve the effective tool set: requested ∩ (READ + WRITE-if-edit-mode),
 * falling back to the full read-only set when nothing valid was requested.
 * Unknown names are dropped silently — the control plane already validated.
 */
export function resolveTools(
  connection: Typo3Connection,
  requested: string[],
  resultMode: ResultMode,
): Typo3Connection['tools'] {
  const allowedNames = new Set(READ_TOOLS);
  if (resultMode === 'edit') {
    for (const name of WRITE_TOOLS) {
      allowedNames.add(name);
    }
  }
  const effective = requested.filter((name) => allowedNames.has(name));
  const active = effective.length > 0 ? effective : READ_TOOLS;

  // MCP-adapted names look like `mcp__typo3__GetPage`.
  return connection.tools.filter((t) => active.some((name) => (t?.name ?? '').endsWith(`__${name}`)));
}
