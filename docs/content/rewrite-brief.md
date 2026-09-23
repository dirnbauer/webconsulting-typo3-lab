# Rewrite brief for writers and agents

You are rewriting text in seed sources of the TYPO3 lab at
`/Users/dirnbauer/projects/webconsulting-typo3-lab`. The goal: shorter, plainer,
consistent copy, in British English (German files: formal "Sie"). Read these
first, in this order:

1. `docs/content/style-guide.md` — voice, limits, glossary, calls to action.
2. `packages/site_package/Resources/Private/Data/Content/canon.yaml` — the only
   source for facts, numbers and prices, plus banned words and US→UK spellings.
3. The approved pilot, as before/after examples of the voice:
   `git -C packages/desiderio show 66ef0784` and `git -C packages/astryx_typo3 show 5957e49`.

## What you may change

- **Only string values of copy fields.** Headlines, leads, descriptions, card
  texts, quotes, button labels, alt texts, captions.
- **Never** change: JSON keys, `_type`, the order of items, the number of items
  in a collection, links and URLs (`#`, `https://…`, `t3://…`, `{{page:…}}`,
  `__HUB__`), file paths (`file`, `source`), select/variant/icon/size/columns/
  tone/width/align values, numbers used as settings, person names.
- **Never blank a field.** An empty value is filled with generated filler by
  the seeder.
- **Rich text** (values with HTML): keep the same kinds of tags that were there
  (`<p>`, `<strong>`, `<ul><li>`, `<code>`…). Don't add classes or styles.
- **No git, composer or seeding commands.** The orchestrator commits and seeds.
  Edit only the files on your list.

## Limits (the linter fails on these)

| Field | Hard limit |
|---|---|
| Headline / title | 60 characters, 8 words (aim for 45 characters) |
| Eyebrow, badge | 24 characters, 3 words |
| Button, link label | 24 characters, 3 words (German: 28 characters, 4 words) |
| Any sentence | 25 words |

Aim: leads up to 2 sentences and 30 words; card texts up to 25 words; FAQ
answers up to 60 words. Contractions count as one word ("it's").

## How to write

- One idea per sentence. Put the point first. Active voice.
- Short, common words. No wordplay, idioms, rhetorical questions, superlatives
  or exclamation marks. Sentence case for headings.
- Be specific: a number, a name or an example instead of an adjective. Keep
  the specific details a text already has (times, amounts, place names) and
  cut the padding around them.
- British spelling: colour, organise, catalogue, licence (noun), centre, grey.
- Facts only from the canon. If a text states a number the canon doesn't
  cover and you can't verify it, keep the original wording for that claim.
- No real company as a customer, partner, investor or reviewer. Real
  integrations (TYPO3, Solr, Brevo, Stripe, GitHub, News, Powermail …) are fine.

## Element files

Each element folder has `config.yaml` (field definitions — read it to see
which fields are text), `fixture.json` and `library.json`.

- **`fixture.json`** shows the element on the Desiderio/Astryx showcase site
  and may describe the product itself.
  - Any "N elements" / "N content elements" in a Desiderio fixture must say
    244 (a test enforces it). Never write a group count like "25 pricing
    elements" there. Keep existing "244" mentions where they read naturally.
  - Testimonials or reviews that quote people praising the product: keep the
    names, add " (example)" to the job title field (as in the pilot).
  - Image objects: rewrite `alternative` as one plain sentence saying what the
    image shows. Set `description` to "Photo: Unsplash." for Unsplash files and
    "Screenshot of a TYPO3 v14 site with Desiderio." for screenshots. **Leave
    image objects whose `file` is under `Logos/` untouched** — logos are
    replaced in the image phase.
- **`library.json`** is demo content an editor keeps after inserting the
  element: a believable, neutral business.
  - It must never mention Desiderio, TYPO3, shadcn, "Content Block" or
    `composer require`.
  - Headlines must be unique across all elements; make them specific to the
    scenario, not generic ("Support that scales with you" is the kind to avoid).
  - People keep their names (each name belongs to a portrait).
- **`library.de.json`** is not part of this pass. Don't edit it.

## Checking your work

Run these before you report back. Fix every ERROR in your own files.

```bash
cd /Users/dirnbauer/projects/webconsulting-typo3-lab
ddev mutagen sync
# lint only your files; --against=HEAD also checks that no key changed and nothing was blanked
ddev exec "php packages/site_package/Build/Scripts/lint-copy.php --site=<site> --against=HEAD <your files>"
```

Site keys: `desiderio`, `astryx`, `corporate-starter`, `agent-nexus`, `camp`,
`blog-classico`, `v14-blog`, `typo3-blog`, `camino`. For innesto use `desiderio`.

Desiderio element tests (run on the host; other agents edit other groups at the
same time, so a failure naming a file outside your list is not yours — note it
in your report and move on):

```bash
cd /Users/dirnbauer/projects/webconsulting-typo3-lab/packages/desiderio
/opt/homebrew/opt/php@8.5/bin/php -d memory_limit=-1 vendor/bin/phpunit -c Build/phpunit/UnitTests.xml --filter 'LibraryFixtureTest|ContentElementAuditTest|ContentElementCatalogConsistencyTest|ContentBlockStructureTest'
```

Astryx: `cd packages/astryx_typo3 && /opt/homebrew/opt/php@8.5/bin/php -d memory_limit=-1 vendor/bin/phpunit -c Build/phpunit/UnitTests.xml`

## Report

End with a short report: files changed, anything you could not fix, any
claim you kept because you couldn't verify it (quote it), and any headline you
think may collide with another element.
