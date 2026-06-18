/**
 * `tree-auditor` — coordinator agent for a whole-subtree content audit.
 *
 * It enumerates a page subtree (GetPageTree) and delegates each page to a
 * `page_auditor` subagent that runs ONE focused, read-only editorial QA in its
 * own child session. The subagent profile is built per-run inside this
 * initializer because it needs the per-request MCP tools (read-only, token from
 * the dispatch payload). Subagents share the parent's sandbox, so the `local()`
 * sandbox below also gives a delegated auditor skill discovery if a flow uses it.
 *
 * Strictly read-only: both the coordinator and the subagent get only the
 * read-tool allowlist.
 */
import {
  createAgent,
  connectMcpServer,
  defineAgentProfile,
  type AgentRouteHandler,
} from '@flue/runtime';
import { local } from '@flue/runtime/node';

const READ_ONLY = ['GetPage', 'ReadTable', 'RenderRecord', 'GetPageTree', 'Search', 'ContentAudit'];

type TreeAuditorPayload = {
  pageUid?: number;
  mcpToken?: string;
  mcpUrl?: string;
  skills?: string[];
};

export const route: AgentRouteHandler = async (_c, next) => next();

export default createAgent<TreeAuditorPayload>(async (ctx) => {
  const payload = ctx.payload ?? {};
  const mcpUrl = payload.mcpUrl ?? ctx.env?.TYPO3_MCP_URL ?? 'http://web/mcp';

  const typo3 = await connectMcpServer('typo3', {
    url: mcpUrl,
    transport: 'streamable-http',
    headers: payload.mcpToken ? { Authorization: `Bearer ${payload.mcpToken}` } : undefined,
  });
  const tools = typo3.tools.filter((t) =>
    READ_ONLY.some((name) => (t?.name ?? '').endsWith(`__${name}`)),
  );

  const model = ctx.env?.FLUE_MODEL ?? 'anthropic/claude-sonnet-4-6';

  // The per-page specialist. Profile-owned tools — it gets the same read-only set.
  const pageAuditor = defineAgentProfile({
    name: 'page_auditor',
    description: 'Audits exactly ONE TYPO3 page for editorial quality. Give it a single page uid.',
    instructions:
      'You audit exactly ONE TYPO3 page. Read it via the read-only TYPO3 tools, then return a ' +
      'structured QA assessment for that page only: a verdict, a 0-100 score, a one-paragraph ' +
      'summary, and concrete findings (severity, title, affected element, fix). Never write.',
    model,
    tools,
  });

  return {
    model,
    instructions:
      'You coordinate a whole-subtree content audit. Use GetPageTree to enumerate the pages, ' +
      'then delegate each page to the page_auditor subagent. Stay read-only.',
    tools,
    subagents: [pageAuditor],
    sandbox: local(),
  };
});
