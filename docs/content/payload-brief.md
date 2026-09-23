# Payload brief: sites that live only in the database

Read `docs/content/rewrite-brief.md` first: voice, limits, facts and checks
are the same. This file adds how to edit a **content payload**, the JSON file
that `sitepackage:content:apply` writes into TYPO3. Examples of finished
payloads: `packages/site_package/Resources/Private/Data/Content/camp/home.payload.json`
(German recap) and `…/blog-classico/post-22.payload.json` (English post
rewritten in German).

## The file

```json
{ "version": 1, "site": "blog-classico", "language": 0, "languageCode": "de",
  "records": [
    { "table": "tt_content", "uid": 58,
      "expect": { "CType": "textmedia", "header": "old text" },
      "set":    { "header": "new text" },
      "_context": { "page": 22, "pageTitle": "…", "roles": { "header": "headline" } } } ] }
```

- **`set`** is the only thing you rewrite. Keep its keys; change the values.
- **`expect`** is what the row holds now. Never edit it: apply refuses a row
  whose current values differ, which protects edits made after the export.
- **`_context`** tells you the page and what each field is for (`roles`:
  headline, lead, card, body, button, eyebrow, meta, alt, page_title). Apply
  ignores it.
- Rich text stays HTML with the tags it had. Internal links stay `t3://…`.

## Actions

Add `"action"` to a record when it should not just be updated:

| Action | Use | Example |
|---|---|---|
| `"delete"` | Remove a record (soft delete, restorable). Test leftovers, copied press images, elements a rewrite no longer needs. | `{"table":"tt_content","uid":1386,"expect":{…},"action":"delete"}` |
| `"hide"` | Keep the record but take it off the page. | |
| `"create"` | A new record, e.g. one more text element a new post needs. Give `key` (`NEW_…`), `pid` and `match` (fields that find it again, so a second run updates instead of duplicating). | `{"action":"create","table":"tt_content","key":"NEW_post88_intro","pid":88,"match":{"pid":88,"header":"…"},"set":{"CType":"text","colPos":0,"header":"…","bodytext":"<p>…</p>"}}` |

To reuse a record for different content (a replacement post), update it in
place: new title, text and slug. Delete what the new text doesn't need.

## Pages

- `title`: page title, 50 characters or fewer. `nav_title` only if it was set.
- `description`: meta description, 110–155 characters, one or two sentences.
- `abstract`, `subtitle`: the teaser on blog lists, one sentence.
- `seo_title`, `og_title`, `twitter_title`: the same as the title unless there
  is a reason; `og_description`, `twitter_description`: the same as `description`.
- **Slugs.** Set a new `slug` in `set` only where the brief for your batch says
  so. TYPO3 creates the redirect from the old address itself — don't add
  redirect records. Slugs are lower-case, hyphenated, ASCII (ä → ae, ö → oe,
  ü → ue, ß → ss), start with `/` and keep the same parent prefix as the old
  slug (a post at `/data/…` stays under `/data/`, one at `/…` stays there).

## Images

- File references (`sys_file_reference`) carry `alternative`: one plain
  sentence saying what the image shows, in the page's language. Some are empty
  now; fill them.
- References to press or agency images — file names or alt texts naming ORF,
  APA, Getty, Adobe Stock, AFP, Reuters, dpa — get `"action":"delete"`. New
  images are added in a later step; a post without an image for a while is
  fine.

## Other records you may need

The export covers pages, content elements, their collection items, file
references and news. Other tables (for example `sys_category` titles of blog
categories) can be added by hand: look up the row read-only with
`ddev mysql -e "SELECT uid,title FROM sys_category WHERE pid=16 AND deleted=0"`
and add `{"table":"sys_category","uid":N,"expect":{"title":"old"},"set":{"title":"new"}}`.

## Checking

```bash
cd /Users/dirnbauer/projects/webconsulting-typo3-lab
ddev mutagen sync
ddev exec "php packages/site_package/Build/Scripts/lint-copy.php --site=<site> <your payload files>"
ddev exec vendor/bin/typo3 sitepackage:content:apply EXT:site_package/Resources/Private/Data/Content/<site>/<file>.payload.json --dry-run
```

The dry run must report `0 skipped` and no warnings about unknown fields. Do
**not** run apply without `--dry-run`: the orchestrator applies all payloads
in order after the seeders.
