/**
 * `skill-run` WORKFLOW — execute ONE skillflow skill against ONE record.
 *
 * The generic engine-bridge entry the `webconsulting/flue` extension's
 * FlueSkillRunner drives (skillflow Skills module / stage auto-run route
 * through it). Unlike the flow workflows (page-report & co.), the skill is
 * invoked EXPLICITLY via session.skill() with a validated result schema —
 * exactly one named procedure must run.
 *
 * Flue does not validate payloads at runtime, so this workflow parses
 * SKILL_RUN_PAYLOAD itself and returns a structured error instead of
 * throwing (the control plane treats a present `result` as terminal).
 */
import { registerProvider, type FlueContext, type WorkflowRouteHandler } from '@flue/runtime';
import * as v from 'valibot';
import skillRunnerAgent from '../agents/skill-runner';
import { SKILL_RUN_PAYLOAD } from '../lib/contracts';
import { executeSkill } from '../lib/run-skill';

export const route: WorkflowRouteHandler = async (c, next) => {
  const key = c.req.header('X-Flue-Anthropic-Key');
  if (key) {
    registerProvider('anthropic', { apiKey: key });
  }
  return next();
};

export async function run({ init, payload, log }: FlueContext<unknown>) {
  const parsed = v.safeParse(SKILL_RUN_PAYLOAD, payload ?? {});
  if (!parsed.success) {
    const message = parsed.issues.map((i) => i.message).join('; ');
    return {
      ok: false,
      error: { code: 'other', message: `Invalid skill-run payload: ${message}` },
      report: `# skill-run\n\n**Invalid payload:** ${message}`,
    };
  }
  const p = parsed.output;
  const target = `${p.table}:${p.uid}`;

  // init() connects the TYPO3 MCP server inside the agent initializer — an
  // unreachable server / bad PAT must surface structurally, not as a 500.
  let harness;
  try {
    harness = await init(skillRunnerAgent);
  } catch (e) {
    const message = e instanceof Error ? e.message : String(e);
    log.error('flue.progress', { phase: 'init_failed', target, message });
    return {
      ok: false,
      error: { code: 'mcp_error', message: `Agent init failed: ${message}` },
      report: `# Skill ${p.skill} — ${target}\n\n**Failed (mcp_error):** ${message}`,
      skill: p.skill,
      skillRunUid: p.skillRunUid || null,
      target: { table: p.table, uid: p.uid, workspace: p.workspace },
    };
  }

  // Preflight through the (local) sandbox fs: a missing export must not burn
  // a model call — surface a structured skill_not_found immediately.
  const skillFile = `.agents/skills/${p.skill}/SKILL.md`;
  if (!(await harness.fs.exists(skillFile))) {
    log.warn('flue.progress', { phase: 'skill_missing', skill: p.skill, target });
    return {
      ok: false,
      error: { code: 'skill_not_found', message: `Skill "${p.skill}" is not exported to ${skillFile}` },
      report: `# Skill ${p.skill} — ${target}\n\n**Failed (skill_not_found):** the skill is not exported to the sidecar.`,
      skill: p.skill,
      skillRunUid: p.skillRunUid || null,
      target: { table: p.table, uid: p.uid, workspace: p.workspace },
    };
  }

  const session = await harness.session();
  log.info('flue.progress', { phase: 'skill_start', skill: p.skill, target, index: 1, total: 1 });

  const outcome = await executeSkill(session, {
    skill: p.skill,
    table: p.table,
    uid: p.uid,
    workspace: p.workspace,
    instructions: p.instructions,
    resultMode: p.resultMode,
    model: p.model,
    thinkingLevel: p.thinkingLevel,
    extraArgs: p.args,
  });

  log.info('flue.progress', {
    phase: 'skill_end',
    skill: p.skill,
    target,
    ok: outcome.ok,
    durationMs: outcome.durationMs,
  });

  return {
    ok: outcome.ok,
    report: outcome.report,
    // `qa` / `edit` land where the control plane's extractMeta() already looks.
    qa: outcome.qa,
    edit: outcome.edit,
    error: outcome.error,
    usage: outcome.usage,
    skill: p.skill,
    resultMode: p.resultMode,
    skillRunUid: p.skillRunUid || null,
    runKey: p.runKey,
    target: { table: p.table, uid: p.uid, workspace: p.workspace },
  };
}
