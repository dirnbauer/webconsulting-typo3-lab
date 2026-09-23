# Content style guide

How every site in this lab writes: the showcase sites, the demo content in the
element library, the blogs and the camp. The numbers this guide mentions are
checked automatically. The facts, word lists and limits live in
`packages/site_package/Resources/Private/Data/Content/canon.yaml`, and two
commands read that file:

- `ddev exec vendor/bin/typo3 sitepackage:content:audit --site=<name|all>` checks what a site shows
- `ddev exec php packages/site_package/Build/Scripts/lint-copy.php <files>` checks seed files before they are seeded

## The short version

1. **Say one thing per sentence.** Put the point first.
2. **Use short, common words.** "Use", not "leverage". "Change", not "transform".
3. **Be specific.** Use a number, a name or an example instead of an adjective.
4. **Be calm.** No superlatives, no exclamation marks, no wordplay, no idioms,
   no rhetorical questions.
5. **Talk to the reader.** In English, use "you". In German, use "Sie"; the camp
   uses "ihr".
6. **Keep it true.** Take facts from `canon.yaml`. If a fact can't be checked,
   leave it out.

## Voice

Plain, specific and friendly without being chatty. Write like a senior
colleague explaining something at a desk: clear and concise, without marketing
fluff. Readers are editors, developers and decision-makers who scan first and
read second. Many of them read English or German as a second language, so
idioms and puns cost them time.

| Instead of | Write |
|---|---|
| Three teams, one unfair advantage | Built for agencies, in-house teams and freelancers |
| Where Desiderio earns its keep | What teams use Desiderio for |
| Running a TYPO3 site in 2026 shouldn't hurt this much | Common problems with TYPO3 sites today |
| Free forever. Faster with the creators. | Free to use. Paid plans add support. |
| Das Programm schreibt ihr selbst. | Ihr bestimmt das Programm. |

## Limits

Hard limits make the checks fail. Soft limits are reported so you can decide.

| What | Hard limit | Aim for |
|---|---|---|
| Page title | 50 characters | — |
| Headline (element or section) | 60 characters, 8 words | 45 characters |
| Eyebrow, badge, kicker | 24 characters, 3 words | — |
| Button, link label | 24 characters, 3 words (German: 28 characters, 4 words) | — |
| Any sentence (except legal pages) | 25 words | 15 on average (German: 13) |
| Lead, intro, subheadline | — | 2 sentences, 30 words |
| Card or list-item text | — | 25 words |
| FAQ answer | — | 60 words |
| Text block | — | 120 words |
| Meta description | — | 110–155 characters |
| Alt text | — | 125 characters |
| Readability per page (LIX) | — | English 40 or lower, German 45 or lower |

LIX is words per sentence plus the percentage of words longer than six letters.
It works the same way for English and German, which is why it is the measure
used here.

## Structure

- **Headlines state the point.** "Themes change without a rebuild" beats "Magic themes".
- **Use sentence case.** "Plans and pricing", not "Plans And Pricing".
- **A lead adds one fact the headline doesn't have.** It never repeats the headline.
- **Cards follow one pattern per group.** Parallel titles (all nouns or all
  verbs), the same length, and one idea each.
- **Buttons start with a verb** and say what happens: "See pricing", "Download
  the guide", "Book a call". Never "Click here", "Learn more" on its own, or
  "Submit".
- **Lists over paragraphs** when there are three or more parallel items.
- **Numbers as digits:** "€49 per month", "15 theme presets",
  "11–13 September 2026".

## English

- **British English:** colour, organise, licence (noun), centre, catalogue,
  grey. The full list is under `spelling_uk` in the canon.
- Date format: 11 September 2026. Currency: €49, €1,490.
- Serial comma only when a list would be unclear without it.
- Contractions are fine ("you'll", "don't"). They keep the tone plain.

## German

- **"Sie" on every site except the camp**, which uses "ihr" consistently. Never
  mix forms of address on one page.
- Use German words where common ones exist: "Inhaltselement", not
  "Content-Element". Established terms stay as they are: Backend, Frontend,
  Theme, Open Source.
- Keep sentences shorter than you would in English, and put the verb early.
- Date format: 11. September 2026. Currency: 49 €, 1.490 €.

## Glossary

One word for one thing. The canon rejects the alternatives.

| Use | Not |
|---|---|
| content element | block, widget, content block element |
| theme preset | skin, palette (for Desiderio presets) |
| editor | content manager, author (for backend users) |
| backend, frontend | back-end, front-end |
| TYPO3 v14 | TYPO3 14, TYPO3 CMS 14 |
| email | e-mail |
| open source (noun), open-source (adjective) | opensource |

## Calls to action per site

Pick from these. A new label needs a reason.

| Site | Labels |
|---|---|
| Desiderio, Astryx | Get started free · See pricing · Book a call · View on GitHub · Read the docs · See all elements |
| Corporate starter (Northstar) | Book a call · See our services · Download the brief · Contact us |
| Agent Nexus | Try the playground · Read the docs · Open the demo |
| Camp | Programm ansehen · Fotos ansehen · Newsletter abonnieren |
| Blogs | Read the post · See all posts / Beitrag lesen · Alle Beiträge |
| Camino | Compare the routes · See the packing list · Read the FAQ |

## What each site is for

| Site | Purpose | Reader |
|---|---|---|
| Desiderio (/) | Sells Desiderio, the shadcn/ui design system for TYPO3 v14 | Agencies, in-house teams, freelancers |
| Astryx (/astryx-typo3/) | Presents Astryx, Meta's design system rendered by TYPO3 | The same readers, comparing themes |
| Corporate starter | Shows a finished corporate site built with Desiderio (fictional firm Northstar Advisory Group) | Buyers judging the starter |
| Agent Nexus | Five agent protocols running live on TYPO3 | Developers |
| Camp | Recap of TYPO3 Camp München 2026 (11–13 September 2026) | Attendees and the community |
| Blog classico (/blog/) | German lifestyle blog demo: spring, Easter, everyday topics | Blog readers |
| TYPO3 v14 blog, The TYPO3 blog | English TYPO3 blog demos | TYPO3 users |
| Camino | Guide to the Camino de Santiago (theme-camino demo) | Walkers planning a trip |

## Demo content in the element library

`library.json` must read like a page an editor would keep after inserting the
element, so it never mentions Desiderio, TYPO3, shadcn or "content block". It
describes a believable, neutral business. `fixture.json` shows the element on
the showcase site and may describe Desiderio itself.

- Keep every key. Change string values only.
- Never blank a field: the seeder fills empty fields with generated filler.
- Keep collections at three items or more.
- People keep their names: each name belongs to a portrait.
- Library headlines must be unique across all elements.

## Images

- **One family:** natural-light editorial photography in calm, warm neutrals,
  with one accent colour matching the site's theme preset. Logos, badges and
  illustrations are flat vector.
- **No embedded text, no real brands, no real people.** Nothing photorealistic
  of real places, people or events. The camp recap uses the camp's own photos.
- **Alt text says what the image shows and why it's there**, in one sentence.
  Never "image of" or the file name.
- **New file, new name.** The FAL seeders never import a file name they
  already know, so a changed image needs a new, hashed name.
- **Screenshots come from the current site**, taken after its text was
  rewritten, never from an older state.

## Legal pages

Imprint, privacy and accessibility pages use the same operator details and
section names on every site. Rewriting them may only make them clearer; every
required statement stays. Labels: Imprint · Privacy · Accessibility, and in
German Impressum · Datenschutz · Barrierefreiheit.
