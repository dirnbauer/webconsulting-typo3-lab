/**
 * `skill-batch` WORKFLOW — execute N skillflow skills against ONE record.
 *
 * Sequential explicit session.skill() calls. Default `isolated` session mode
 * runs each skill in its own NAMED session (one harness, so skills are
 * discovered once): clean per-skill attribution, no cross-skill bias, and a
 * failing skill never pollutes the others' context. `shared` opts into one
 * session for all skills (cheaper: the record is read once).
 *
 * Per-skill outcomes are stamped structurally (lib/run-skill.ts) and rolled
 * up worst-verdict / mean-score; a failed skill surfaces as a blocker
 * finding instead of being silently dropped.
 */
import { registerProvider, type FlueContext, type WorkflowRouteHandler } from '@flue/runtime';
import * as v from 'valibot';
import skillRunnerAgent from '../agents/skill-runner';
import { SKILL_BATCH_PAYLOAD, rollupOutcomes, type SkillOutcome } from '../lib/contracts';
import { executeSkill } from '../lib/run-skill';
import { renderReport } from '../lib/qa';

export const route: WorkflowRouteHandler = async (c, next) => {
  const key = c.req.header('X-Flue-Anthropic-Key');
  if (key) {
    registerProvider('anthropic', { apiKey: key });
  }
  return next();
};

export async function run({ init, payload, log }: FlueContext<unknown>) {
  const parsed = v.safeParse(SKILL_BATCH_PAYLOAD, payload ?? {});
  if (!parsed.success) {
    const message = parsed.issues.map((i) => i.message).join('; ');
    return {
      ok: false,
      error: { code: 'other', message: `Invalid skill-batch payload: ${message}` },
      report: `# skill-batch\n\n**Invalid payload:** ${message}`,
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
      report: `# skill-batch — ${target}\n\n**Failed (mcp_error):** ${message}`,
      skillRunUid: p.skillRunUid || null,
      target: { table: p.table, uid: p.uid, workspace: p.workspace },
    };
  }
  const sharedSession = p.sessionMode === 'shared' ? await harness.session() : null;

  const outcomes: SkillOutcome[] = [];
  let index = 0;
  for (const skill of p.skills) {
    index++;
    log.info('flue.progress', { phase: 'skill_start', skill, target, index, total: p.skills.length });

    // Preflight each skill so one missing export becomes a structured failure
    // in the outcome list, not a burned model call.
    if (!(await harness.fs.exists(`.agents/skills/${skill}/SKILL.md`))) {
      outcomes.push({
        skill,
        resultMode: p.resultMode,
        ok: false,
        error: { code: 'skill_not_found', message: `Skill "${skill}" is not exported to the sidecar` },
        report: `# Skill ${skill} — ${target}\n\n**Failed (skill_not_found):** the skill is not exported to the sidecar.`,
        durationMs: 0,
      });
      continue;
    }

    const session = sharedSession ?? (await harness.session(`skill-${skill}`));
    const outcome = await executeSkill(session, {
      skill,
      table: p.table,
      uid: p.uid,
      workspace: p.workspace,
      instructions: p.instructions,
      resultMode: p.resultMode,
      model: p.model,
      thinkingLevel: p.thinkingLevel,
      extraArgs: p.args,
    });
    outcomes.push(outcome);
    log.info('flue.progress', { phase: 'skill_end', skill, target, ok: outcome.ok, durationMs: outcome.durationMs });
  }

  const rollup = rollupOutcomes(target, outcomes);
  const totalTokens = outcomes.reduce((sum, o) => {
    const usage = o.usage as { totalTokens?: number } | undefined;
    return sum + (typeof usage?.totalTokens === 'number' ? usage.totalTokens : 0);
  }, 0);

  const report = [
    renderReport(`Skill batch — ${target} (${outcomes.length} skills)`, rollup, { skills: p.skills }),
    ...outcomes.map((o) => `\n---\n\n${o.report}`),
  ].join('\n');

  return {
    ok: outcomes.every((o) => o.ok),
    report,
    qa: rollup,
    skills: outcomes,
    usage: { totalTokens },
    skillCount: outcomes.length,
    failedCount: outcomes.filter((o) => !o.ok).length,
    skillRunUid: p.skillRunUid || null,
    runKey: p.runKey,
    target: { table: p.table, uid: p.uid, workspace: p.workspace },
  };
}
