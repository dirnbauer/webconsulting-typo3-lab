/**
 * `page-editor` — a careful, minimal WRITE agent.
 *
 * It makes ONE requested edit to ONE page. Writes go through the TYPO3 MCP server,
 * which — because the control plane runs this agent as the sandboxed `_flue`
 * backend user (strict-sandbox TSconfig) — stages every change in a DRAFT workspace,
 * never live. A human reviews and publishes in the Workspaces module. The agent
 * cannot publish, roll back, bulk-write, or delete: its allowlist is read tools +
 * single-record `WriteTable` + `WorkspaceReview` (to confirm the staged diff).
 *
 * SAFETY: live-content protection comes from the sandboxed backend user (draft-only
 * writes), NOT from this allowlist alone. Never run this agent as an unsandboxed user.
 */
import { createAgent, connectMcpServer, type AgentRouteHandler } from '@flue/runtime';
import { local } from '@flue/runtime/node';

/** Read + single-record write + workspace review. NO publish / rollback / bulk / delete. */
const ALLOWED = ['GetPage', 'ReadTable', 'RenderRecord', 'GetPageTree', 'WriteTable', 'WorkspaceReview'];

type PageEditorPayload = {
  pageUid?: number;
  mcpToken?: string;
  mcpUrl?: string;
  instructions?: string;
  skills?: string[];
};

export const route: AgentRouteHandler = async (_c, next) => next();

export default createAgent<PageEditorPayload>(async (ctx) => {
  const payload = ctx.payload ?? {};
  const mcpUrl = payload.mcpUrl ?? ctx.env?.TYPO3_MCP_URL ?? 'http://web/mcp';

  const typo3 = await connectMcpServer('typo3', {
    url: mcpUrl,
    transport: 'streamable-http',
    headers: payload.mcpToken ? { Authorization: `Bearer ${payload.mcpToken}` } : undefined,
  });
  const tools = typo3.tools.filter((t) =>
    ALLOWED.some((name) => (t?.name ?? '').endsWith(`__${name}`)),
  );

  return {
    model: ctx.env?.FLUE_MODEL ?? 'anthropic/claude-sonnet-4-6',
    instructions: [
      'You make exactly ONE requested edit to ONE TYPO3 page — nothing more.',
      'First read the page to understand the current content. Then apply ONLY the requested',
      'change with WriteTable. Every write is automatically staged in a DRAFT workspace for a',
      'human to review and publish — you never touch live content and you must not try to.',
      'After writing, call WorkspaceReview to confirm what was staged. Report the workspace',
      'and a precise before/after of every field you changed. Do not invent changes, and do',
      'not touch anything the request did not ask for. If the request is unclear or unsafe,',
      'make no change and say why.',
    ].join(' '),
    tools,
    sandbox: local(),
  };
});
