/**
 * `tree-audit` WORKFLOW — supervised whole-subtree editorial audit via subagent fan-out.
 *
 * The coordinator (`tree-auditor`) enumerates the subtree (GetPageTree), then this
 * workflow delegates each page to the `page_auditor` subagent (`session.task`,
 * each in a fresh child session) and rolls up the per-page verdicts into one
 * structured result. Strictly read-only. Capped at MAX_PAGES so a single run can't
 * fan out unbounded — long runs are meant for the CLI/Scheduler, not the browser.
 */
import {
  registerProvider,
  type FlueContext,
  type WorkflowRouteHandler,
} from '@flue/runtime';
import treeAuditor from '../agents/tree-auditor';
import { QA_RESULT, PAGE_LIST, rollupAudits, renderTreeAudit, type PageAudit } from '../lib/qa';

type TreeAuditPayload = {
  pageUid?: number;
  mcpToken?: string;
  mcpUrl?: string;
  skills?: string[];
};

/** Fan-out cap (cost/time guard). */
const MAX_PAGES = 8;

export const route: WorkflowRouteHandler = async (c, next) => {
  const key = c.req.header('X-Flue-Anthropic-Key');
  if (key) {
    registerProvider('anthropic', { apiKey: key });
  }
  return next();
};

export async function run({ init, payload }: FlueContext<TreeAuditPayload>) {
  const p = payload ?? {};
  const root = p.pageUid ?? 1;
  const harness = await init(treeAuditor);
  const session = await harness.session();

  // 1. Enumerate the subtree (coordinator uses GetPageTree).
  const list = await session.prompt(
    `Use GetPageTree to list every page in the subtree rooted at page ${root} (include ${root} itself). ` +
      `Return each page's uid and title.`,
    { result: PAGE_LIST },
  );
  const total = list.data.pages.length;
  const pages = list.data.pages.slice(0, MAX_PAGES);

  // 2. Fan out: delegate each page to the page_auditor subagent (a fresh child session each).
  const audits: PageAudit[] = [];
  const usages: unknown[] = [list.usage];
  for (const pg of pages) {
    const res = await session.task(
      `Audit page uid ${pg.uid} ("${pg.title}") and return its QA assessment.`,
      { agent: 'page_auditor', result: QA_RESULT },
    );
    audits.push({ uid: pg.uid, title: pg.title, ...res.data });
    usages.push(res.usage);
  }

  // 3. Roll up into one verdict + report.
  const qa = rollupAudits(root, audits);
  const usage = {
    totalTokens: usages.reduce((s: number, u) => s + (Number((u as { totalTokens?: number })?.totalTokens) || 0), 0),
    cost: {
      total: usages.reduce((s: number, u) => s + (Number((u as { cost?: { total?: number } })?.cost?.total) || 0), 0),
    },
  };

  const truncated = total > MAX_PAGES;
  const report = truncated
    ? `> ⚠️ Subtree has ${total} pages; audited the first ${MAX_PAGES} (fan-out cap).\n\n${renderTreeAudit(root, audits)}`
    : renderTreeAudit(root, audits);

  return {
    report,
    qa,
    usage,
    pageCount: audits.length,
    totalPages: total,
    pageUid: root,
  };
}
