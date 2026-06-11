---
name: content-qa
description: Editorial quality gate for workspace review - completeness, consistency, spelling/grammar, placeholder detection and factual red flags before publishing.
metadata:
  category: quality-management
---

# Content QA Gate

Act as the editorial quality gate before content is approved for publishing.

## Checks

1. **Placeholders & leftovers**: lorem ipsum, "TODO", "xxx", draft markers, broken markdown/HTML fragments.
2. **Completeness**: empty headers, elements with a header but no body (or vice versa), images implied by text but missing.
3. **Spelling & grammar**: list concrete errors with corrections (keep the content's language).
4. **Consistency**: dates, names, product terms and numbers used consistently across the page.
5. **Claims**: flag absolute or legally risky claims (e.g. "guaranteed", "best in the world", pricing promises) for human verification - do not decide their truth.

## Report format

- Verdict line: READY / NEEDS WORK / BLOCKER, with one sentence why.
- Numbered findings with severity, the affected element/field, and a concrete fix.
- End with a short checklist the editor can tick off.
