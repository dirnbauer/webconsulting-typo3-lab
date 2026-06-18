/**
 * `page-report` WORKFLOW — the HTTP entry the TYPO3 control plane drives.
 *
 * A workflow (not the direct `/agents/page-report/:id` chat route) because only a
 * workflow's `init(agent)` passes the structured dispatch payload into the agent's
 * `ctx.payload`. The exported `route` is Flue's admission boundary where the
 * per-request Anthropic key (from nr-vault) is injected.
 *
 * Asks for a structured QA assessment (valibot schema → validated `response.data`)
 * and renders it into a deterministic findings table via the shared renderer.
 */
import {
  registerProvider,
  type FlueContext,
  type WorkflowRouteHandler,
} from '@flue/runtime';
import pageReportAgent from '../agents/page-report';
import { QA_RESULT, renderReport } from '../lib/qa';

type PageReportPayload = {
  pageUid?: number;
  mcpToken?: string;
  mcpUrl?: string;
  instructions?: string;
  model?: string;
  /** skillflow skill identifiers the control plane materialized into .agents/skills/. */
  skills?: string[];
};

export const route: WorkflowRouteHandler = async (c, next) => {
  const key = c.req.header('X-Flue-Anthropic-Key');
  if (key) {
    registerProvider('anthropic', { apiKey: key });
  }
  return next();
};

export async function run({ init, payload }: FlueContext<PageReportPayload>) {
  const p = payload ?? {};
  const harness = await init(pageReportAgent);
  const session = await harness.session();

  // The control plane materializes the flow's selected skillflow skills into
  // <cwd>/.agents/skills/<id>/SKILL.md, where Flue auto-discovers them as
  // "Available Skills". We instruct the agent to apply them — no explicit
  // session.skill() (that re-runs an already-activated skill).
  const skillIds = Array.isArray(p.skills) ? p.skills.filter((s) => typeof s === 'string') : [];
  const target = p.pageUid ? `page uid ${p.pageUid}` : 'the target page';
  const skillLine = skillIds.length
    ? ` Apply these review skills where relevant: ${skillIds.join(', ')}.`
    : '';

  const response = await session.prompt(
    `Read ${target} via the read-only TYPO3 tools and assess its editorial quality. ` +
      `Return your verdict, a 0-100 quality score, a one-paragraph summary, and a list of ` +
      `concrete findings (each with a severity, a short title, the affected record/element, ` +
      `and a fix).${skillLine}`,
    { result: QA_RESULT },
  );

  const qa = response.data;

  return {
    report: renderReport(`Editorial QA — page ${p.pageUid ?? '(unspecified)'}`, qa, { skills: skillIds }),
    qa,
    usage: response.usage,
    appliedSkills: skillIds,
    pageUid: p.pageUid ?? null,
  };
}
