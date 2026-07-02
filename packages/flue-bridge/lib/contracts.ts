/**
 * Payload/result contracts for the generic skillflow engine-bridge workflows
 * (`skill-run`, `skill-batch`). Flue does NOT validate workflow payloads at
 * runtime (FlueContext typing is compile-time only), so the workflows parse
 * these schemas themselves and fail fast with a structured error.
 *
 * Per-skill attribution is stamped STRUCTURALLY here (each session.skill call
 * is wrapped and its outcome recorded in TypeScript) — never model-asserted.
 */
import * as v from 'valibot';
import { QA_RESULT, EDIT_RESULT, type QaResult } from './qa';

export const RESULT_MODE = v.picklist(['qa', 'edit', 'freeform']);
export type ResultMode = v.InferOutput<typeof RESULT_MODE>;

export const THINKING_LEVEL = v.picklist(['off', 'minimal', 'low', 'medium', 'high', 'xhigh']);

/** Agent Skills name shape — must equal the skill directory name under .agents/skills/. */
const SKILL_NAME = v.pipe(v.string(), v.regex(/^[a-z0-9][a-z0-9-]{0,63}$/));

const PAYLOAD_BASE = {
  table: v.optional(v.string(), 'pages'),
  uid: v.number(),
  workspace: v.optional(v.number(), 0),
  runKey: v.optional(v.string(), ''),
  /** tx_skillflow_run uid — echoed back so the control plane can cross-link. */
  skillRunUid: v.optional(v.number(), 0),
  instructions: v.optional(v.string(), ''),
  mcpUrl: v.optional(v.string()),
  mcpToken: v.optional(v.string()),
  model: v.optional(v.string()),
  thinkingLevel: v.optional(THINKING_LEVEL),
  /** Effective MCP tool allowlist, computed by the PHP control plane. */
  mcpTools: v.optional(v.array(v.string()), []),
  resultMode: v.optional(RESULT_MODE, 'qa'),
  /** Extra skill args merged into the session.skill() invocation input. */
  args: v.optional(v.record(v.string(), v.unknown()), {}),
};

export const SKILL_RUN_PAYLOAD = v.object({
  skill: SKILL_NAME,
  ...PAYLOAD_BASE,
});
export type SkillRunPayload = v.InferOutput<typeof SKILL_RUN_PAYLOAD>;

export const SKILL_BATCH_PAYLOAD = v.object({
  skills: v.pipe(v.array(SKILL_NAME), v.minLength(1), v.maxLength(12)),
  /** isolated (default): one named session per skill — clean attribution and
   *  failure isolation. shared: one session for all skills — cheaper, but
   *  earlier skills' findings leak into later ones. */
  sessionMode: v.optional(v.picklist(['isolated', 'shared']), 'isolated'),
  ...PAYLOAD_BASE,
});
export type SkillBatchPayload = v.InferOutput<typeof SKILL_BATCH_PAYLOAD>;

export type SkillOutcomeError = {
  code: 'skill_not_found' | 'result_unavailable' | 'mcp_error' | 'aborted' | 'other';
  message: string;
};

/** ONE skill's outcome — the attribution unit the control plane persists. */
export type SkillOutcome = {
  skill: string;
  resultMode: ResultMode;
  ok: boolean;
  qa?: v.InferOutput<typeof QA_RESULT>;
  edit?: v.InferOutput<typeof EDIT_RESULT>;
  text?: string;
  error?: SkillOutcomeError;
  /** Rendered markdown fragment for this skill. */
  report: string;
  usage?: unknown;
  durationMs: number;
};

const VERDICT_RANK: Record<QaResult['verdict'], number> = { READY: 0, NEEDS_WORK: 1, BLOCKER: 2 };

/** Roll up per-skill outcomes: worst verdict / mean score over qa-mode results;
 *  failed skills surface as blocker findings so they are never silently dropped. */
export function rollupOutcomes(target: string, outcomes: SkillOutcome[]): QaResult {
  const qaResults = outcomes.filter((o) => o.ok && o.qa).map((o) => ({ skill: o.skill, qa: o.qa as QaResult }));
  const failed = outcomes.filter((o) => !o.ok);

  const verdict = qaResults.reduce<QaResult['verdict']>(
    (worst, r) => (VERDICT_RANK[r.qa.verdict] > VERDICT_RANK[worst] ? r.qa.verdict : worst),
    failed.length > 0 ? 'NEEDS_WORK' : 'READY',
  );
  const score = qaResults.length > 0
    ? Math.round(qaResults.reduce((s, r) => s + r.qa.score, 0) / qaResults.length)
    : (failed.length > 0 ? 0 : 100);

  const findings: QaResult['findings'] = [];
  for (const r of qaResults) {
    for (const f of r.qa.findings) {
      findings.push({ ...f, title: `[${r.skill}] ${f.title}` });
    }
  }
  for (const o of failed) {
    findings.push({
      severity: 'blocker',
      title: `[${o.skill}] skill did not complete`,
      element: target,
      fix: o.error?.message ?? 'unknown error',
    });
  }

  return {
    verdict,
    score,
    summary:
      `${outcomes.length} skill(s) ran against ${target}: ` +
      `${qaResults.length} returned QA results, ${failed.length} failed. ` +
      `Worst verdict ${verdict}; mean score ${score}/100.`,
    findings,
  };
}

/** Flatten per-skill findings with their source skill attached (control-plane convenience). */
export function flattenFindings(outcomes: SkillOutcome[]): Array<QaResult['findings'][number] & { skill: string }> {
  const flat: Array<QaResult['findings'][number] & { skill: string }> = [];
  for (const o of outcomes) {
    for (const f of o.qa?.findings ?? []) {
      flat.push({ ...f, skill: o.skill });
    }
  }
  return flat;
}
