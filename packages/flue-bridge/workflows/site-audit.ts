/**
 * `site-audit` WORKFLOW — whole-subtree content-health audit.
 *
 * Reuses the read-only page-report agent (which now allows the `ContentAudit` MCP
 * tool) and the shared QA schema/renderer. The agent runs ContentAudit over a root
 * page's subtree, then summarizes the worst issues into a structured assessment so
 * the control plane stores a verdict + renders a findings table — same shape as
 * page-report, but scoped to a page tree instead of one page.
 */
import {
  registerProvider,
  type FlueContext,
  type WorkflowRouteHandler,
} from '@flue/runtime';
import pageReportAgent from '../agents/page-report';
import { QA_RESULT, renderReport } from '../lib/qa';

type SiteAuditPayload = {
  pageUid?: number;
  mcpToken?: string;
  mcpUrl?: string;
  skills?: string[];
};

export const route: WorkflowRouteHandler = async (c, next) => {
  const key = c.req.header('X-Flue-Anthropic-Key');
  if (key) {
    registerProvider('anthropic', { apiKey: key });
  }
  return next();
};

export async function run({ init, payload }: FlueContext<SiteAuditPayload>) {
  const p = payload ?? {};
  const root = p.pageUid ?? 1;
  const harness = await init(pageReportAgent);
  const session = await harness.session();

  const skillIds = Array.isArray(p.skills) ? p.skills.filter((s) => typeof s === 'string') : [];
  const skillLine = skillIds.length
    ? ` Apply these review skills where relevant: ${skillIds.join(', ')}.`
    : '';

  const response = await session.prompt(
    `Run the ContentAudit tool on rootPageId ${root} to scan the whole page subtree for ` +
      `content-quality and SEO issues. Then summarize the audit into a structured assessment: ` +
      `an overall verdict, a 0-100 health score, a one-paragraph summary, and the most important ` +
      `findings (each with a severity, a short title, the affected page/element, and a fix). ` +
      `Prioritize by severity and focus on pages with real problems.${skillLine}`,
    { result: QA_RESULT },
  );

  const qa = response.data;

  return {
    report: renderReport(`Site audit — from page ${root}`, qa, { skills: skillIds }),
    qa,
    usage: response.usage,
    appliedSkills: skillIds,
    pageUid: root,
  };
}
