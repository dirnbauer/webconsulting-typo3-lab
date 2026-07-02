/**
 * Shared single-skill executor used by the `skill-run` and `skill-batch`
 * workflows: invokes ONE workspace-discovered skill explicitly via
 * session.skill() with the result schema matching the requested mode, and
 * stamps the outcome (attribution, timing, error mapping) structurally.
 */
import { ResultUnavailableError, SkillNotRegisteredError } from '@flue/runtime';
import { QA_RESULT, EDIT_RESULT, renderReport, renderEdit, type QaResult, type EditResult } from './qa';
import type { ResultMode, SkillOutcome, SkillOutcomeError } from './contracts';

/** Minimal structural view of a Flue session (avoids depending on non-exported types). */
type SkillCapableSession = {
  skill: (
    name: string,
    options?: {
      args?: Record<string, unknown>;
      result?: unknown;
      model?: string;
      thinkingLevel?: string;
    },
  ) => Promise<{ data?: unknown; text?: string; usage?: unknown }>;
};

export type ExecuteSkillOptions = {
  skill: string;
  table: string;
  uid: number;
  workspace: number;
  instructions: string;
  resultMode: ResultMode;
  model?: string;
  thinkingLevel?: string;
  extraArgs?: Record<string, unknown>;
};

function mapError(e: unknown): SkillOutcomeError {
  const message = e instanceof Error ? e.message : String(e);
  if (e instanceof SkillNotRegisteredError) {
    return { code: 'skill_not_found', message };
  }
  if (e instanceof ResultUnavailableError) {
    return { code: 'result_unavailable', message };
  }
  if ((e instanceof Error && e.name === 'AbortError') || /abort/i.test(message)) {
    return { code: 'aborted', message };
  }
  if (/mcp/i.test(message)) {
    return { code: 'mcp_error', message };
  }
  return { code: 'other', message };
}

export async function executeSkill(session: SkillCapableSession, opts: ExecuteSkillOptions): Promise<SkillOutcome> {
  const startedAt = Date.now();
  const target = `${opts.table}:${opts.uid}`;
  try {
    const response = await session.skill(opts.skill, {
      args: {
        table: opts.table,
        uid: opts.uid,
        workspace: opts.workspace,
        instructions: opts.instructions,
        ...opts.extraArgs,
      },
      ...(opts.resultMode === 'qa' ? { result: QA_RESULT } : {}),
      ...(opts.resultMode === 'edit' ? { result: EDIT_RESULT } : {}),
      ...(opts.model ? { model: opts.model } : {}),
      ...(opts.thinkingLevel ? { thinkingLevel: opts.thinkingLevel } : {}),
    });

    const durationMs = Date.now() - startedAt;
    if (opts.resultMode === 'qa') {
      const qa = response.data as QaResult;
      return {
        skill: opts.skill,
        resultMode: opts.resultMode,
        ok: true,
        qa,
        report: renderReport(`Skill ${opts.skill} — ${target}`, qa, { skills: [opts.skill] }),
        usage: response.usage,
        durationMs,
      };
    }
    if (opts.resultMode === 'edit') {
      const edit = response.data as EditResult;
      return {
        skill: opts.skill,
        resultMode: opts.resultMode,
        ok: true,
        edit,
        report: renderEdit(opts.uid, edit),
        usage: response.usage,
        durationMs,
      };
    }
    const text = response.text ?? JSON.stringify(response.data ?? null);
    return {
      skill: opts.skill,
      resultMode: opts.resultMode,
      ok: true,
      text,
      report: `# Skill ${opts.skill} — ${target}\n\n${text}`,
      usage: response.usage,
      durationMs,
    };
  } catch (e) {
    const error = mapError(e);
    return {
      skill: opts.skill,
      resultMode: opts.resultMode,
      ok: false,
      error,
      report: `# Skill ${opts.skill} — ${target}\n\n**Failed (${error.code}):** ${error.message}`,
      durationMs: Date.now() - startedAt,
    };
  }
}
