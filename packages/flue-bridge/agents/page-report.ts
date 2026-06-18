/**
 * `page-report` — a READ-ONLY TYPO3 page reporter.
 *
 * Reads the target page through typo3-mcp-server's MCP tools and produces an
 * editorial report guided by the per-run instructions (and, in a full build,
 * the skillflow skills mounted under ./skills). The MCP bearer token and the
 * page context arrive in the dispatch payload, injected by the
 * webconsulting/flue TYPO3 control plane (page id + {uid}-resolved context).
 *
 * Authored against @flue/runtime 1.0.0-beta.1 — verify the exact tool-name
 * shape (mcp__typo3__<Tool>) once the sidecar runs the first probe.
 */
import { createAgent, connectMcpServer, type AgentRouteHandler } from '@flue/runtime';
import { local } from '@flue/runtime/node';

/** Allow only read tools — defends against DDEV's live-workspace default. */
const READ_ONLY = ['GetPage', 'ReadTable', 'RenderRecord', 'GetPageTree', 'Search', 'ContentAudit'];

type PageReportPayload = {
  pageUid?: number;
  mcpToken?: string;
  mcpUrl?: string;
  instructions?: string;
};

// Default route: keep the agent reachable over HTTP (tighten/authenticate as needed).
export const route: AgentRouteHandler = async (_c, next) => next();

export default createAgent<PageReportPayload>(async (ctx) => {
  const payload = ctx.payload ?? {};
  const mcpUrl = payload.mcpUrl ?? ctx.env?.TYPO3_MCP_URL ?? 'http://web/mcp';

  const typo3 = await connectMcpServer('typo3', {
    url: mcpUrl,
    transport: 'streamable-http',
    headers: payload.mcpToken ? { Authorization: `Bearer ${payload.mcpToken}` } : undefined,
  });

  // MCP-adapted names look like `mcp__typo3__GetPage`; keep only the read tools.
  const tools = typo3.tools.filter((t) =>
    READ_ONLY.some((name) => (t?.name ?? '').endsWith(`__${name}`)),
  );

  const instructions = [
    'You produce a concise editorial report for ONE TYPO3 page.',
    'You are strictly READ-ONLY: never call any tool that writes, publishes, deletes, or moves content.',
    payload.pageUid ? `Target page uid: ${payload.pageUid}.` : '',
    payload.instructions ? `\n\nPer-run instructions:\n${payload.instructions}` : '',
  ]
    .filter(Boolean)
    .join(' ');

  return {
    model: ctx.env?.FLUE_MODEL ?? 'anthropic/claude-sonnet-4-6',
    instructions,
    tools,
    // The local sandbox (cwd = process.cwd() = /app) is what makes Flue discover the
    // control-plane-exported skills under /app/.agents/skills/. The default virtual
    // sandbox has an isolated FS and never sees them. Tools stay the read-only MCP set;
    // skills are auto-discovered by name and invoked via session.skill(<id>).
    sandbox: local(),
  };
});
