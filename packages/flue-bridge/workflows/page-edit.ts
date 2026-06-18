/**
 * `page-edit` WORKFLOW — apply ONE requested change to a page, staged in a draft
 * workspace for human review. The control plane runs the `page-editor` agent as the
 * sandboxed `_flue` backend user, so every write lands in a draft workspace, never
 * live. The requested change comes in as the per-run `instructions`.
 */
import {
  registerProvider,
  type FlueContext,
  type WorkflowRouteHandler,
} from '@flue/runtime';
import pageEditorAgent from '../agents/page-editor';
import { EDIT_RESULT, renderEdit } from '../lib/qa';

type PageEditPayload = {
  pageUid?: number;
  mcpToken?: string;
  mcpUrl?: string;
  instructions?: string;
  skills?: string[];
};

export const route: WorkflowRouteHandler = async (c, next) => {
  const key = c.req.header('X-Flue-Anthropic-Key');
  if (key) {
    registerProvider('anthropic', { apiKey: key });
  }
  return next();
};

export async function run({ init, payload }: FlueContext<PageEditPayload>) {
  const p = payload ?? {};
  const pageUid = p.pageUid ?? null;
  const change = (p.instructions ?? '').trim();
  const harness = await init(pageEditorAgent);
  const session = await harness.session();

  const response = await session.prompt(
    change
      ? `Apply this change to page uid ${pageUid}: ${change}\n\n` +
          `Read the page first, apply ONLY this change via WriteTable (it stages to a draft ` +
          `workspace), confirm with WorkspaceReview, and report exactly what changed.`
      : `No change was requested for page uid ${pageUid}. Make no edit; report that nothing was requested.`,
    { result: EDIT_RESULT },
  );

  const edit = response.data;

  return {
    report: renderEdit(pageUid ?? 0, edit),
    edit,
    usage: response.usage,
    pageUid,
  };
}
