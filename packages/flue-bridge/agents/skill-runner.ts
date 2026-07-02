/**
 * `skill-runner` — the generic agent behind the skillflow engine bridge.
 *
 * Executes exactly ONE review/edit procedure (an Agent Skill exported from
 * skillflow) against ONE TYPO3 record. Everything variable arrives in the
 * dispatch payload: MCP endpoint + PAT, the effective tool allowlist, the
 * model, and the result mode. The workflows (`skill-run`, `skill-batch`)
 * invoke the skill explicitly via session.skill().
 */
import { createAgent } from '@flue/runtime';
import { local } from '@flue/runtime/node';
import { connectTypo3, resolveTools } from '../lib/typo3-mcp';
import type { SkillBatchPayload, SkillRunPayload } from '../lib/contracts';

type Payload = Partial<SkillRunPayload & SkillBatchPayload>;

export default createAgent<Payload>(async (ctx) => {
  const payload = ctx.payload ?? {};
  const resultMode = payload.resultMode ?? 'qa';

  const typo3 = await connectTypo3(payload, ctx.env);
  const tools = resolveTools(typo3, payload.mcpTools ?? [], resultMode);

  const writeClause = resultMode === 'edit'
    ? 'You may stage AT MOST the changes the skill demands via WriteTable (drafts only, the server enforces a draft workspace); confirm staged changes with WorkspaceReview. Never publish, roll back, bulk-write or delete.'
    : 'You are strictly READ-ONLY: never call any tool that writes, publishes, deletes, or moves content.';

  const instructions = [
    'You execute exactly one editorial procedure against ONE TYPO3 record.',
    'The procedure arrives as an Agent Skill; follow it precisely and base every statement on record data you actually read through the TYPO3 tools.',
    writeClause,
    payload.uid ? `Target record: ${payload.table ?? 'pages'}:${payload.uid} (workspace ${payload.workspace ?? 0}).` : '',
  ]
    .filter(Boolean)
    .join(' ');

  return {
    model: payload.model ?? ctx.env?.FLUE_MODEL ?? 'anthropic/claude-sonnet-4-6',
    instructions,
    tools,
    // The local sandbox (cwd = /app) is what makes Flue discover the
    // control-plane-exported skills under /app/.agents/skills/.
    sandbox: local(),
  };
});
