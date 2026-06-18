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
