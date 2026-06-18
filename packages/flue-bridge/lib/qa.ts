/**
 * Shared QA result schema + renderer for Flue flows (page-report, site-audit, …).
 *
 * A valibot schema is passed as `session.prompt(..., { result: QA_RESULT })` so the
 * model must return validated structured data (Flue retries until it conforms). The
 * control plane then stores the structured `qa` and renders `report` for the module.
 */
import * as v from 'valibot';

export const QA_RESULT = v.object({
  verdict: v.picklist(['READY', 'NEEDS_WORK', 'BLOCKER']),
  score: v.pipe(v.number(), v.minValue(0), v.maxValue(100)),
  summary: v.string(),
  findings: v.array(
    v.object({
      severity: v.picklist(['blocker', 'high', 'medium', 'low', 'info']),
      title: v.string(),
      element: v.string(),
      fix: v.string(),
    }),
  ),
});

export type QaResult = v.InferOutput<typeof QA_RESULT>;

const VERDICT_ICON: Record<QaResult['verdict'], string> = {
  READY: '✅',
  NEEDS_WORK: '🟠',
  BLOCKER: '🔴',
};
const SEVERITY_ICON: Record<QaResult['findings'][number]['severity'], string> = {
  blocker: '🔴',
  high: '🟠',
  medium: '🟡',
  low: '🔵',
  info: '⚪',
};

/** Escape table-breaking characters in a cell. */
const cell = (s: string): string => s.replace(/\|/g, '\\|').replace(/\n+/g, ' ').trim();

/** Render a QA result into a deterministic markdown report (verdict + findings table). */
export function renderReport(
  title: string,
  qa: QaResult,
  opts: { skills?: string[] } = {},
): string {
  const lines: string[] = [];
  lines.push(`# ${title}`);
  lines.push('');
  lines.push(`**Verdict:** ${VERDICT_ICON[qa.verdict]} ${qa.verdict}  ·  **Score:** ${qa.score}/100`);
  lines.push('');
  lines.push(qa.summary);
  lines.push('');
  if (qa.findings.length > 0) {
    lines.push('| Severity | Finding | Element | Fix |');
    lines.push('|---|---|---|---|');
    for (const f of qa.findings) {
      lines.push(`| ${SEVERITY_ICON[f.severity]} ${f.severity} | ${cell(f.title)} | \`${cell(f.element)}\` | ${cell(f.fix)} |`);
    }
  } else {
    lines.push('_No issues found._');
  }
  const skills = opts.skills ?? [];
  if (skills.length > 0) {
    lines.push('');
    lines.push(`<sub>Skills applied: ${skills.join(', ')}</sub>`);
  }
  return lines.join('\n');
}

/** Page list returned when a coordinator enumerates a subtree before fan-out. */
export const PAGE_LIST = v.object({
  pages: v.array(v.object({ uid: v.number(), title: v.string() })),
});

export type PageAudit = QaResult & { uid: number; title: string };

const VERDICT_RANK: Record<QaResult['verdict'], number> = { READY: 0, NEEDS_WORK: 1, BLOCKER: 2 };

/** Roll up per-page audits into one QA_RESULT: worst verdict, mean score, one finding per problem page. */
export function rollupAudits(rootUid: number, audits: PageAudit[]): QaResult {
  if (audits.length === 0) {
    return { verdict: 'READY', score: 100, summary: `No pages audited under page ${rootUid}.`, findings: [] };
  }
  const verdict = audits.reduce<QaResult['verdict']>(
    (worst, a) => (VERDICT_RANK[a.verdict] > VERDICT_RANK[worst] ? a.verdict : worst),
    'READY',
  );
  const score = Math.round(audits.reduce((s, a) => s + a.score, 0) / audits.length);
  const needWork = audits.filter((a) => a.verdict !== 'READY').length;
  const summary =
    `${audits.length} page(s) audited under page ${rootUid}: ${needWork} need work. ` +
    `Worst verdict ${verdict}; mean score ${score}/100.`;
  const findings = audits
    .filter((a) => a.findings.length > 0)
    .map((a) => ({
      severity: (a.verdict === 'BLOCKER' ? 'blocker' : a.verdict === 'NEEDS_WORK' ? 'high' : 'info') as QaResult['findings'][number]['severity'],
      title: `${a.title} — ${a.verdict} (${a.findings.length} issue${a.findings.length === 1 ? '' : 's'})`,
      element: `page ${a.uid}`,
      fix: a.summary,
    }));
  return { verdict, score, summary, findings };
}

/** Render a subtree audit: a roll-up header + per-page summary table + per-page detail. */
export function renderTreeAudit(rootUid: number, audits: PageAudit[]): string {
  const rollup = rollupAudits(rootUid, audits);
  const lines: string[] = [];
  lines.push(`# Subtree audit — from page ${rootUid}`);
  lines.push('');
  lines.push(`**Verdict:** ${VERDICT_ICON[rollup.verdict]} ${rollup.verdict}  ·  **Mean score:** ${rollup.score}/100  ·  **Pages:** ${audits.length}`);
  lines.push('');
  lines.push(rollup.summary);
  lines.push('');
  lines.push('| Page | Title | Verdict | Score | Findings |');
  lines.push('|---|---|---|---|---|');
  for (const a of audits) {
    lines.push(`| ${a.uid} | ${cell(a.title)} | ${VERDICT_ICON[a.verdict]} ${a.verdict} | ${a.score} | ${a.findings.length} |`);
  }
  for (const a of audits) {
    lines.push('');
    lines.push('---');
    lines.push('');
    lines.push(renderReport(`Page ${a.uid} — ${a.title}`, a));
  }
  return lines.join('\n');
}
