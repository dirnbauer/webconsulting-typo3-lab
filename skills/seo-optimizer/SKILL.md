---
name: seo-optimizer
description: Reviews a page and its content elements for on-page SEO quality - titles, meta description, heading structure, keyword focus and internal linking opportunities.
metadata:
  category: quality-management
---

# SEO Optimizer

Review the provided TYPO3 page content for on-page SEO.

## Checks

1. **Page title / seo_title**: present, 30-60 characters, contains the page's main topic, no keyword stuffing.
2. **Meta description**: present, 70-155 characters, actionable, contains the main topic naturally.
3. **Heading structure**: exactly one H1 idea per page, logical hierarchy in content element headers, headings describe the content beneath them.
4. **Content depth**: thin content (under ~150 words of body text) is flagged.
5. **Slug**: short, lowercase, hyphenated, reflects the title.
6. **Duplication**: repeated headlines or near-identical paragraphs across elements.

## Report format

- Start with a one-line verdict and a score from 1-10.
- List findings grouped by severity (critical / recommended / nice-to-have).
- For every critical finding, propose a concrete replacement text (e.g. a better meta description) based only on the content actually present.
