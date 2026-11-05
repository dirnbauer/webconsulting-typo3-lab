# Selling free software: the Desiderio sales model on the site, the target-group pages, a lighter home page

Implementation plan, written 2026-11-05 by the planning thread (Fable 5.1, effort xhigh). Execution: Opus 5.5 workers at
effort high, in parallel where section 12 allows it. This file is the single source of truth for the work; every worker
reads sections 1, 2, 3 and 12 in full and the sections its work package names. The previous round's plan
(`docs/plans/2026-11-04-software-ai-features-plan.md`, sections 1.2, 1.3, 9–12) describes how the site is generated,
the traps and the release recipe; this plan repeats only what changed.

Implementation started 2026-11-05 after the user's go (section 13 records the decisions). Progress is logged in 11.1.

## 0. What the user asked for, in one table

| # | Request (2026-11-05) | Where it lands |
|---|---|---|
| 1 | "There is one big thing missing: we want to sell Desiderio packages." Think hard about how to make money with open source; explain the strategy on the site, not pushy: "we are glad about support, features are good, use it, we take care of everything, but that costs" | 2 (strategy), 3 (decisions), 5.1 (the model page), 5.2 (home), 5.4 (pricing) |
| 2 | Update `/target-groups/` and its subpages in particular | 5.3, WP3 |
| 3 | Prominent on the home page | 5.2, WP2 |
| 4 | The home page has too much content: split it, perhaps own pages (the usability talk among them); find out where content fits better | 3 (D5–D7), 4, 5.2, 5.5, 5.6, WP2 |
| 5 | Translate everything (de, zh, hu) | 9, WP7 |
| 6 | Use Corey Haines' marketing skills | 7, WP6 |
| 7 | Plan with Fable 5.1 xhigh, implement with Opus 5.5 high | 12 |
| 8 | Standing rule: finish with commit, push (GitHub + GitLab), merge, deploy, health check, no further asking | 11, WP9 |

## 1. Ground truth (verified 2026-11-05 morning)

### 1.1 Repositories and checkouts

| What | Path | State |
|---|---|---|
| Lab worktree (lab commits happen here) | `/Users/dirnbauer/.t3/worktrees/webconsulting-typo3-lab/t3-0ef0c98b` (`WT`) | branch `t3/software-category-ops-ledger-medikamente` = `main` = `e133d5f`, clean |
| Lab main checkout = the DDEV project | `/Users/dirnbauer/projects/webconsulting-typo3-lab` (`MAIN`) | `main` at `e133d5f`, clean; DDEV runs natively on ARM64 again (PHP 8.5.12, CLI bootstrap 0.33 s) |
| Desiderio clone | `MAIN/packages/desiderio` (`DES`) | branch `feature/software-ai-strategy` = `main` = `v4.28.0` = `b0b53c3b` = origin/main = gitlab/main; 4 untracked webp files of other sessions in `Resources/Public/Styleguide/Backend/` (leave alone, never `git add -A`) |
| Installed Desiderio | `MAIN/vendor/webconsulting/desiderio` | **dist zip 4.28.0, not linked** (the release restored it from dist). WP0 links the clone again with `MAIN/Build/Scripts/lab-link.sh webconsulting/desiderio` |
| Production | `typo3-lab.webconsulting.at` (Coolify, basic auth) | lab commit `12da81e`, desiderio 4.28.0, data run `var/prod-desiderio-4.28` ended `ALL-DONE` + `VERIFY-OK` at 00:24 |
| Mirror script | `WT/Build/Scripts/lab-mirror.sh` | WT → MAIN for `packages/site_package/`, `config/sites/desiderio/`, `Build/Scripts/lib/downloads-page.json`, `docs/`, `CHANGELOG.md`, `Tests/E2E/`; `--back` for film artefacts |

### 1.2 The sales model today (decided by the owner on 2026-09-27, memory `desiderio-sales-plan-2026-09`)

- **Free model:** every release of Desiderio and the suite is free and public the day it ships (GPL-2.0). No paid early access, no private Composer repository, no discount codes. The owner forbids promising paid early access anywhere.
- **Plans = yearly support plans,** priced by live sites (staging and local free), single source `DES/Classes/Data/Showcase/ShowcasePricing.php`, mirrored in `canon.yaml` `facts.desiderio.pricing/services` and in `packages/astryx_typo3/Classes/Data/AstryxSiteDefinitions.php` (lines 431–441): Community €0 · Studio €590/yr (€59/mo, 5 live sites, 1 named developer, 2-business-day answers) · Agency €1,990/yr (€199/mo, 25 sites, +10 for €490, next-business-day answers, 3 developers, onboarding call, queue priority) · Partner €4,900/yr (unlimited, 6 developers, critical fixes in 2 business days, workshop, roadmap call, passed-on enquiries, five places).
- **Owner's rate (2026-11-05): €130/h.** Every fixed price and Care must earn at least that.
- **Services, fixed price:** Kickstart €1,490 · Brand Theme €2,490 · element S €890 / M €1,690 · upgrade audit €690 (credited) · editor training €790 · support hours €650/5 h · Launch Pack €5,970 · founding offer (first 20 agencies keep their price, product council, until 31 March 2027).
- **Promises:** maintenance promise (every TYPO3 v14 patch within 10 business days, confirmed security issues fixed within 5, miss one and the plan runs a month longer), 30 days money back, your code and sites stay yours, your clients stay yours.
- **Retired and forbidden by canon:** €49/€149 plans, DESIDERIO20, "Creator Care", "managed hosting" wording, "installation service", 4-hour answers, unlimited client projects (`canon.yaml` `forbidden`, lines 145–149).
- **Ordering:** `/pricing/order` (hidden page): no shop, a Friendly-Captcha form (name, work email, company, message), "We send the invoice within one business day". Open owner decisions from September: where the shop lives, who answers support, invoices first, lawyer check of terms and CRA.
- **Seller identity gap:** the Desiderio site's imprint is the fictional Example Studio GmbH ("This imprint is example content"), by owner rule no real contact data on Desiderio pages (`canon.yaml` line 142 forbids Mattersburg, `@webconsulting.at`, FN 401904 k … on the desiderio site). The pricing and order pages say "we" without naming a legal seller. `https://www.webconsulting.at/impressum` and `/agb` exist (200). → Q4.
- **GitHub Sponsors:** `dirnbauer` has no sponsors listing (`hasSponsorsListing: false`), `DES/.github/FUNDING.yml` does not exist. → Q2.

### 1.3 The pages today

- **Home (505): 26 visible elements**, in order: hero · product film · six problems · theme in three steps · 62 typed components · speed stats · 244 elements in 10 groups · eight forms · gallery (five elements) · three kinds of teams · five reasons · integrations · strategy checklist "Five points for all our software" · strategy film + transcript · "Not only TYPO3" inline CTA · "Everything in this lab" (4 cards) · lab overview film + transcript · usability talk (5:05, portrait) + transcript · "More sites on this TYPO3 installation" (8 cards) · four steps · pricing three-tier (Studio/Agency/Partner) · testimonials (example) · CTA banner. Four films on one page. `StyleguideShowcasePages::homeContent()` (lines 121–420).
- **/target-groups** (1094, nav "For your team"): sales hero + three `textmedia` pitches (agency, in-house, freelancer, each naming the recommended plan) + CTA banner. Three subpages 983/984/985: `headersection → textmedia → benefitcards (4) → pricingsimple (one recommended plan with a business case) → testimonial (example) → ctabanner`. `ShowcaseAudiencePages.php` (326 lines).
- **/pricing** (2230): sales hero · `pricingfourtier` · `trustbadges` promises · comparison table · services · Launch Pack · founding offer · FAQ. `/pricing/order` hidden. `ShowcasePricingPages.php` (192 lines).
- **/resources** (2232, nav "News and demos"): hero · "Check Desiderio before you build on it" · "Where to go next" cards (News, Powermail Lab, Download, Documentation, Example stories, Strategy) · FAQ. `ShowcaseHubPages::resourcesPage()`. Children: Powermail Lab (712), News (992).
- **/typo3-v14-strategy** (1121): already carries the strategy film, the five points and the full plan; the home page repeats the film.
- **Menu:** Product · Website tools · AI and agents · Software · For your team · Pricing and plans · News and demos (7 first-level items; `MenuNamesTest` caps level-1 nav titles at 20 characters, lower levels at 42, abstracts ≤ 160). Header CTA removed in 4.28 (`desiderio.header.ctaSlug: ''`).
- **Films on home:** `typo3-v14-strategy/film.json` placements `[{slug:/typo3-v14-strategy},{slug:/, after:{header:'Five points for all our software'}}]`; `lab-overview/film.json` `[{slug:/, after:{header:'Everything in this lab'}}]`; `usability-slogans/long/film.json` `[{slug:/, after:{film:'lab-overview'}}]`; the product film is a seeded block (`ShowcaseBlocks::productFilm()`), not a films-seeder film.
- **E2E that pins the home:** `WT/Tests/E2E/usability-slogans.spec.js` expects the long cut on `/` (USABILITY_TALK_PATH); `var/prod-desiderio-4.28/verify.php` asserts "home has at least 4 film blocks" and "no /usability-slogans page". Both flip in this round.
- **Translations:** memories `WT/packages/site_package/Resources/Private/Data/Translations/desiderio/{de,zh,hu}.json` (1.8–1.9 MB each, append only), `sitepackage:content:translate --site=desiderio [--pages=…] [--dry-run --missing=-]`. A styleguide reseed drops every element translation of the site, so after the reseed the full translate runs (14:56–19:23 under qemu on 2026-11-04; expect well under half of that natively).
- **Content blocks available for the new sections** (244, `DES/ContentBlocks/ContentElements/`): `feature-numbered`, `feature-timeline`, `feature-cards`, `compare` (two columns with points and a badge), `comparison-table`, `pricing-simple`, `pricing-enterprise`, `pricing-two-tier`, `special-offer`, `stats`, `stats-counter`, `trust-badges`, `founder-quote`, `brand-story`, `mission-statement`, `company-values`, `callout`, `content-highlight`, `faq`, `cta-split`, `cta-inline`, `cta-banner`, `textmedia`, `feature-video`.

### 1.4 Market context (web, 2026-11-05; for the strategy, not for the copy)

- **TYPO3 Suite** (TYPO3 GmbH, announced 2 November 2026): a commercial repository with early access to selected TYPO3 features, sold only through official TYPO3 Partners, first feature a REST API on 9 November 2026; TYPO3 CMS stays free; TYPO3 GmbH takes CRA manufacturer responsibility. Prices only on suite.typo3.com.
- **in2code Early Access Program** (TYPO3 14): sponsors €389 net (customers €349), freelancers €189 (€179), non-profits free, +€15 for Packagist access; extensions become free at most 9 months after the LTS release; benefits: early access, prioritised issues and pull requests, sponsor naming. Justification on the page: "support us financially in the development of our open source extensions".
- **Open Source Pledge:** member companies pay more than $2,000 per developer per year directly to maintainers; $9.2 million raised in total, $4 million in the past year. One-sentence rationale: "Society depends on Open Source. We contribute cash to prevent maintainer burnout and supply chain risk."
- **TYPO3 care plans by agencies:** Forge12 lists care plans from €159 a month; NITSAN and others sell maintenance retainers; ELTS for v12.4 is paid until 30 April 2029 (free support ended 30 April 2026).
- **Pattern from the OSS pricing analyses:** a product-style pricing page with tiers converts far better than a GitHub Sponsors page alone (React Flow: "very little money" until it "rolled out a product-like website with several pricing tiers"); Sidekiq, Cap, Plausible, Grafana sell hosting, support or an open core next to a free project. Babel sells a $24k/yr Open Collective plan with 2 hours of support a month.
- **Corey Haines' marketingskills** (MIT, 53 skills, `https://raw.githubusercontent.com/coreyhaines31/marketingskills/main/skills/<name>/SKILL.md`): read this round `pricing` (value metric, good-better-best, pricing page practices, "price between the next best alternative and perceived value", avoid the $9 trap), `offers` (value equation: dream outcome × likelihood ÷ time × effort; six components: deliverable, bonus, guarantee, scarcity, name, price; banned vocabulary; no fake scarcity, no discounting to acquire), `product-marketing` (the shared context document), `site-architecture` (4–7 nav items, 3-click rule, breadcrumbs mirror URLs), `community-marketing` (value flows to members first; recognise members who help). `copywriting`, `cro`, `copy-editing`, `schema`, `ai-seo` were read last round and still apply (section 7).

## 2. The strategy: how a small agency earns with free TYPO3 software

### 2.1 The one-sentence position

**"Free software, paid care."** Everything we build is free with every release. We earn when someone wants our time: answers, work by a date, or running their site. Not when someone wants our code.

### 2.2 Why the software stays free (and why this is the money-making choice, not the charitable one)

1. **TYPO3 is GPL and so is everything built on it.** A paid-code model needs a private fork, licence keys and a second codebase to maintain. One public codebase for every customer is cheaper for us and safer for them.
2. **Adoption is the funnel.** Every agency that builds client sites on Desiderio for free is a buyer of care the day a client site goes live and a patch release lands. Paid plans are priced by live sites because that is when the value (and the risk) appears.
3. **Trust is the product of a small vendor.** A buyer can read every line before paying, can leave at any time and keeps working sites (GPL). The maintenance promise with a refund clause turns "a small agency might stop" into a contract term.
4. **The market just moved towards paid early access** (TYPO3 Suite, in2code EAP). We say the opposite, calmly and once: a release that waits for payment is a security fix that waits. That is a position buyers remember, and it costs nothing.
5. **CRA:** selling plans likely makes webconsulting a manufacturer (reporting duties since 11 Sep 2026, full duties 11 Dec 2027). Public code and a public changelog are the cheapest way to meet them. Not for the copy; for the owner.

### 2.3 The four ways a visitor can relate to us (the model the site explains)

The user's own words, turned into the page structure: *"we are glad about support · features are good · use it · we take care of everything, but that costs"*.

| Way | What the visitor does | What it costs | What exists today | What this round adds |
|---|---|---|---|---|
| **Use it** | Installs Desiderio, builds and sells client sites | €0, every release, commercial use | Community plan, GitHub, demo download | Nothing new; said more clearly |
| **Support it** | Stars, reports bugs, writes about it, recommends it, or pays a small yearly amount for recognition | €0, or **Supporter €190 a year** (Q2) | Nothing | Supporter (name/logo on the model page, thank-you in the release notes), optional GitHub Sponsors (owner action) |
| **Ask for features** | Opens an issue; good ideas go on the public roadmap; needs it by a date → sponsors it | €0, or **Sponsored feature from €1,290** (Q2): we build it, release it free for everyone, name the sponsor | Custom element S/M (private, under the agency's name) | Sponsored feature as a public alternative to a private custom element |
| **Let us take care** | Wants answers, named developers and the maintenance promise; or wants us to run the site | **Studio €590 · Agency €1,990 · Partner €4,900** a year; **Care €390 a month per live site** (Q1) | The three plans, services, Launch Pack | **Care** (hosting on the customer's own Coolify instance via Mini-Mi, updates and security fixes applied by T3 Ops within the maintenance promise, monitoring and daily backups, Agency-level answer times) |

The value-equation diagnosis (offers skill): dream outcome (client sites in days) and likelihood (244 audited elements, 900+ tests, live demo) score high; **time delay and effort are the weak levers** for a small team that does not want to run TYPO3 itself. Care lowers effort to zero ("we take care of everything"); it is the offer the user described and the one with the largest recurring value. It is priced per live site like the plans, so the metric stays one.

### 2.4 What we never do (and say so once, on the model page)

No paid early access, no private repository, no licence keys, no feature gating, no discount codes, no countdown timers, no "limited time" without a real limit, no calls to clients of agencies. Bug and security fixes are free for everyone, always.

### 2.5 The calculation (owner's rate €130/h, stated 2026-11-05; never on the site)

All figures net. Hours are the planner's estimates for the owner to confirm; the September 2026 plan used €110/h and is superseded on this point. House price pattern: every price ends in 90 (€590, €1,990, €4,900, €1,490, €2,490, €890, €1,690, €690, €790).

**Care, first live site, per month**

| Item | Quantity | Rate | Amount |
|---|---:|---:|---:|
| Attention: review T3 Ops update evidence, approve releases, answer questions | 1.00 h | €130 | €130.00 |
| Incident reserve: outages, hotfixes, restores (averaged over the year) | 0.75 h | €130 | €97.50 |
| Monitoring review and monthly note to the customer | 0.25 h | €130 | €32.50 |
| Infrastructure: VPS for the customer's own Coolify, backup storage, TLS, DNS | — | — | €40.00 |
| **Subtotal** | **2.00 h** | | **€300.00** |
| Tooling share (T3 Ops, Mini-Mi, monitoring stack) and risk, 15 % | | | €45.00 |
| **Cost floor** | | | **€345.00** |
| **Price (house pattern, 13 % buffer): €390 a month** · yearly €3,900 (two months free) | | | **€390.00** |

Effective rate on time at €390: (390 − 40 infrastructure) ÷ 2 h = €175/h; the buffer covers 2.7 h a month at €130 before the site costs money. At the computed €340 the effective rate is €150/h.

**Care, every further live site of the same customer, per month**

| Item | Quantity | Rate | Amount |
|---|---:|---:|---:|
| Attention and incident reserve (shared Coolify, shared monitoring) | 1.25 h | €130 | €162.50 |
| Infrastructure share | — | — | €20.00 |
| Tooling and risk, 15 % | | | €27.40 |
| **Cost floor** | | | **€209.90** |
| **Price: €290 a month** | | | **€290.00** |

**Sponsored feature (public release, sponsor named)**

| Item | Hours |
|---|---:|
| Scope and acceptance criteria with the sponsor | 1.0 |
| Build: Content Block, Fluid, CSS, backend preview | 4.0 |
| Tests: unit and visual check in two presets | 1.5 |
| Labels and translations (en, de; zh and hu memories) | 1.0 |
| Documentation, changelog, release notes with the sponsor's name | 1.0 |
| Review, release, publication | 1.5 |
| **Total** | **10.0 h × €130 = €1,300 → "from €1,290"** |

A custom element S for the sponsor's own use stays €890 (≈ 6 h, no public release, no translations beyond en/de).

**Supporter, per year: €190.** Not hour-based. Our cost: logo and name on the model page 0.25 h, yearly thank-you in the release notes 0.25 h, invoice 0.25 h = 0.75 h = €97.50; the rest is the contribution. The price matches what a freelancer pays in2code for a year of early access (€189), without buying anything but recognition.

**The existing fixed services at €130/h (hours assumed by the planner)**

| Service | Price | Hours assumed | Effective rate | Verdict |
|---|---:|---:|---:|---|
| Kickstart workshop | €1,490 | 3 h workshop + 3 h preparation + 2 h first-project review + 2 h of 30-day questions = 10 h | €149 | keep |
| Brand Theme | €2,490 | 8 h preset + 3 h contrast checks + 4 h two review rounds + 2 h handover = 17 h | €146 | keep |
| Custom element S | €890 | 6 h | €148 | keep |
| Custom element M | €1,690 | 12 h | €141 | keep |
| Upgrade audit | €690 | 5 h (credited when the upgrade is booked) | €138 | keep |
| Editor training | €790 | 3 h training + 2 h preparation + 1 h recording and handout = 6 h | €132 | keep, no slack |
| Support hours | €650 / 5 h | 5 h | €130 | **on the floor → raise to €690 / 5 h (€138)** (Q2b) |
| Launch Pack | €5,970 | Agency year €1,990 + Kickstart €1,490 + Brand Theme €2,490 + training €790 = €6,760 list | — | keep: the training is the included bonus (offers skill: a real bonus, not an invented "value") |

**The support plans: hours a plan can carry at €130/h**

| Plan | Price a year | Hours covered | Expected use a year | Note |
|---|---:|---:|---:|---|
| Studio | €590 | 4.5 h | 2–4 h | 5 live sites, answers within 2 business days |
| Agency | €1,990 | 15.3 h | 8–14 h | 25 sites, 1.5 h onboarding call, queue priority |
| Partner | €4,900 | 37.7 h | 20–35 h | unlimited sites, half-day workshop 4 h, four roadmap calls 4 h, critical fixes |

The maintenance promise itself (every v14 patch within 10 business days) is product work shared by all customers; the plan pool funds it. No plan price changes this round.

**Illustrative year-1 mix (goal from September: €50–80k)**

| Line | Count | Price | Revenue | Paid hours |
|---|---:|---:|---:|---:|
| Studio | 10 | €590 | €5,900 | 30 |
| Agency | 6 | €1,990 | €11,940 | 66 |
| Partner | 2 | €4,900 | €9,800 | 56 |
| Care, yearly | 6 sites | €3,900 | €23,400 | 144 |
| Supporter | 15 | €190 | €2,850 | 11 |
| Sponsored features | 3 | ≈ €1,500 | €4,500 | 34 |
| Services: 2 Brand Themes, 2 Kickstarts, 4 trainings | | | €13,120 | 78 |
| **Total** | | | **€71,510** | **≈ 420 h ≈ 1 day a week** |

Care is the largest line and the only one that compounds monthly; at the computed €340 the total is €68,510. The founding offer (20 places, until 31 March 2027) stays the only urgency.

### 2.6 How to say it without pushing (the tone rule for every worker)

- One calm sentence per page about the model, never a popup, never a banner, never a second CTA competing with the page's own: "Desiderio is free with every release. Paid plans add our time." The full explanation lives on one page (5.1) that every other page links to with one link.
- The order of every pitch: the reader's problem → what Desiderio does → what is free → what costs, with the number → the next step. Price is a fact, stated once, never a lever ("only", "just", "cheap" are banned; `docs/content/style-guide.md`).
- Recognition before money: "Support it" lists stars, reports and recommendations before the Supporter price.
- Honesty as a feature: the model page says why it is free, where the money goes and what happens if we stop. The FAQ answers "What is the catch?" literally.

## 3. Decisions (with reasons)

- **D1 One model page under Pricing:** `/pricing/how-we-earn`, title "Free software, paid care", nav title "How we earn". It is a trust page for buyers, so it sits where buyers look (Pricing flyout), gets a hero photo, a film (D9) and a FAQ, and every other page links to it once. The nav keeps seven items (site-architecture: 4–7).
- **D2 Prices change only in `ShowcasePricing`** (this round: Care, Supporter, Sponsored feature, and support hours €690 if Q2b is yes) (new methods `care()`, `supporter()`, `sponsoredFeature()`, `ways()` for the four-ways cards, `isAlwaysFree()`/`costsMoney()` lists for the compare block) and are mirrored in `canon.yaml` and the Astryx block. Home, pricing, model page and the four audience pages read from there. No number is typed twice.
- **D3 Care is not a fifth plan column.** The four-tier block stays Community/Studio/Agency/Partner (support plans, yearly, by live sites). Care is a different thing (we run the site) and gets its own block on the pricing page and the model page (`desiderio_pricingenterprise` or `desiderio_pricingsimple`: price, included items, "Ask for Care" → order page). Astryx keeps its four tiers; its text gains one sentence about Care only if Q1 is approved.
- **D4 Supporter and Sponsored feature live on the model page and in an "Other ways to support" block on the pricing page,** not in the plan table. GitHub Sponsors appears only after the owner enables it (Q2); until then the site names stars, issues and the Supporter.
- **D5 The home page sells Desiderio; the lab moves to one page.** Home drops from 26 to 15 elements (5.2). The lab content (overview film, "Everything in this lab", "More sites on this installation") moves to the Resources hub, which becomes **"Lab and resources"** (`/resources`, nav title "Lab and resources", 17 characters) with the lab film after its hero. The home keeps the four-card "Everything in this lab" block as the link into the lab (the film goes). No new first-level item, no redirect (the slug stays).
- **D6 The usability talk gets its own page** `/usability-slogans` (child of Resources, nav title "Usability slogans (talk)", 24 characters): header section, the long cut as a portrait film block right after it (films seeder, `variant: portrait`), transcript, the ten slogans as `desiderio_featurenumbered` with two columns (closes the 10-in-3 orphan noted last round), one "Why this matters for editors" `textmedia` linking to the in-house page, CTA. The home loses the talk; the lab page and the in-house page link to it. (The user, 2026-11-05: "perhaps its own page, find out where it fits better".)
- **D7 Strategy on home = the five-point checklist and one inline CTA.** The strategy film and its transcript leave the home page (they stay on `/typo3-v14-strategy`, which already carries them); `typo3-v14-strategy/film.json` loses its home placement. The checklist keeps the user's "important points of Strategy 14" on the home page; the CTA text carries the "Not only TYPO3" line, so the separate `ctainline` goes.
- **D8 Home blocks that move elsewhere:** "Built from 62 typed components" and "Fast to run, edit and launch" (speed stats) move to the tech facts page if that page lacks them (the worker checks `ShowcaseHubPages`/`technicalFeaturesPage()`), "Templates for the extensions you use" moves to the Product hub, "Five reasons to build on Desiderio" is dropped (its points are in the problems block and the model block). Nothing is lost that is not elsewhere.
- **D9 One new film, 50–60 s, for the model page** ("Free software, paid care"), on the existing WAAPI pipeline with the fal voice, scene outline in 6.2. Every hub-like page of the site has a film near the top; this page explains the one thing the user wants understood. Default yes; the user can strike it (Q5).
- **D10 Target-group pages keep their outline and gain two sections each:** "Your path with Desiderio" (`desiderio_featurenumbered`, four steps: use it free → the plan when the first live site runs → services for the brand → Care when you want us to run it, with the audience's numbers) and "What you pay, what you get" (`desiderio_compare`: always free vs costs money, audience-specific right column). The hub `/target-groups` gets the four-ways block after its hero and a sharper pitch per audience (5.3). Copy follows the copywriting checklist (problem first, one promise, proof next to claims, one primary CTA).
- **D11 Seller identity on sales pages** (Q4, recommended default): the model page, the pricing FAQ and the order page name "webconsulting GmbH, Austria" as the party that invoices, with one link to `https://www.webconsulting.at/impressum` and one to `/agb`. The Desiderio imprint stays the demo imprint (owner rule: demo content carries no real contact data); the canon rule of line 142 is narrowed to the demo content by adding an allowed-phrase exception for the two external links on the three sales pages (`sitepackage:content:audit` must stay green).
- **D12 Translations:** every new English string gets de (Sie), zh and hu memory entries before the seed chain; after the reseed the full `content:translate` runs once (the reseed drops element translations). No page is published in English only.
- **D13 Release:** desiderio **4.29.0**; lab lock bump; prod data run `var/prod-desiderio-4.29` (same structure as 4.28, new `verify.php`); deploy via the Quality workflow; health = live 401 + the in-container URL checks + `VERIFY-OK`.

## 4. Information architecture after the change

```
Home (/)                                   15 elements (was 26)
├── Product (/product)                     + "Templates for the extensions you use" (moved from home)
│   └── … (unchanged)                      tech facts page: + components cards, + speed stats (moved from home, if absent)
├── Website tools (/features)              unchanged
├── AI and agents (/ai)                    unchanged
├── Software (/software)                   unchanged
├── For your team (/target-groups)         reworked (5.3)
│   ├── /target-groups/for-agencies        + path + compare
│   ├── /target-groups/for-inhouse-teams   + path + compare + link to the talk
│   └── /target-groups/for-freelancers     + path + compare
├── Pricing and plans (/pricing)           + Care block, + other ways to support, + model link, FAQ (5.4)
│   ├── How we earn (/pricing/how-we-earn) NEW: the model page (5.1), film (6.2)
│   └── Order (/pricing/order, hidden)     + seller sentence (D11)
├── Lab and resources (/resources)         renamed from "News and demos"; + lab film, + Everything in this lab, + More sites, + talk card (5.5)
│   ├── Usability slogans (/usability-slogans)  NEW: the talk page (5.6)
│   ├── News (/news)                        unchanged
│   └── Powermail Lab                       unchanged
└── (hidden) Imprint · Privacy · Accessibility · 404 · Downloads   unchanged
```

Slugs removed: none. Redirects: none. New pages: 2 (`/pricing/how-we-earn`, `/usability-slogans`). Renamed nav title: 1 (`/resources`). `MenuNamesTest` limits hold (20 / 42 / 160).

## 5. Page content (section by section)

Copy below is the first draft for the workers; WP6 polishes it with the skills and `lint-copy.php`. Every number comes from `ShowcasePricing` or `canon.yaml`. British English, sentence case, headlines ≤ 60 characters, sentences ≤ 25 words, no superlatives, no rhetorical questions in body copy (the FAQ's questions are the visitor's).

### 5.1 /pricing/how-we-earn — "Free software, paid care" (new, `ShowcasePricingPages::howWeEarnPage()`)

Page: title "Free software, paid care", navTitle "How we earn", seoTitle "How we earn with free TYPO3 software", abstract "Desiderio is free with every release. This page says why, what is always free, what costs money and where the money goes.", parentSlug `pricing`.

1. `salesHero`: badge "Open source" · header "Free software, paid care" · lead "Desiderio and every tool in this lab are free, with every release. You pay only when you want our time: answers, work by a date, or running your site." · primary "See the plans" → `{{page:pricing}}` · secondary "Download the demo" → `{{page:downloads}}` · photo `ShowcaseHeroPhotos::for('how-we-earn')` (new, 5.7).
2. *(film block, placed by the films seeder after the hero, D9)*
3. `desiderio_featurecards` (columns 4), eyebrow "The model", header "Four ways to use Desiderio", items from `ShowcasePricing::ways()`:
   - **Use it** — "Install it, build client sites, sell your work. Every release and every fix is free under GPL-2.0. No registration, no licence key." link → `ShowcaseBlocks::REPO_URL`
   - **Support it** — "Star the repository, report what breaks, write about it, recommend it. Or become a Supporter for €190 a year and be named on this page." link → `#supporter` (anchor on this page; the worker checks the card block supports a plain link, else `{{page:pricing/how-we-earn}}`)
   - **Ask for features** — "Open an issue; good ideas go on the public roadmap. Need it by a date? Sponsor it: we build it, release it for everyone and name you. From €1,290." link → `{{page:pricing}}`
   - **Let us take care** — "Plans from €590 a year add answers and the maintenance promise. Care runs your site: hosting, updates, monitoring, €390 a month per live site." link → `{{page:pricing}}`
4. `desiderio_textmedia` (media left, the hero photo of the pricing page or a new still), header "Why the software is free", content: "<p><strong>TYPO3 is GPL, and so is everything built on it.</strong> A paid version would need a private copy, licence keys and a second codebase. One public codebase for every customer is cheaper for us and safer for you.</p><p>Free use is also how we find our customers. The day a client site goes live and a TYPO3 patch lands, the people who built it want answers and the maintenance promise. That is what the plans sell.</p><p>Some vendors sell early access to their extensions. We release everything the day it ships, because a release that waits for payment is a security fix that waits.</p>"
5. `desiderio_compare`: eyebrow "In one look", header "What is always free, and what costs money" · left title "Always free" with points from `ShowcasePricing::alwaysFree()`: every release, the day it ships · every bug and security fix · the demo site and its download · documentation and GitHub issues · commercial use on unlimited sites · left button "Install for free" → repo · right badge "Our time", right title "What costs money", points from `ShowcasePricing::costsMoney()`: guaranteed answer times and named developers (plans) · the maintenance promise in the terms (plans) · workshops, Brand Themes and custom elements (fixed prices) · a feature by a date, released for everyone (sponsored feature) · running your site: hosting, updates, monitoring (Care) · right button "See the plans" → pricing.
6. `desiderio_stats` (columns 4), header "Where the money goes", items from canon: "244 elements kept current with every TYPO3 release" · "10 business days: every TYPO3 v14 patch supported" · "5 business days: confirmed security issues fixed" · "900+ automated tests on every change" (worker confirms the tests count in `canon.yaml` `facts.desiderio`; if absent, use "PHPStan level 8 on every change").
7. `desiderio_featuretimeline`, header "A typical path", four steps: "Day 1: install and build, free" → "First live client site: Studio, €590 a year" → "Your brand or a new element: a fixed-price service" → "You want one partner for everything: Care, €390 a month per site".
8. `desiderio_pricingenterprise` (or `pricingsimple`), id anchor `care`, eyebrow "We take care", header "Care: we run your site", description "For teams that want one partner for hosting, updates and support. Your site runs on its own Coolify instance in the EU; T3 Ops applies TYPO3 and extension updates within the maintenance promise and checks security four times a day.", features: own Coolify instance (Mini-Mi), EU hosting · updates and security fixes applied, with screenshots as evidence · monitoring and daily backups · answers by the next business day · monthly, cancel to the end of a year · price "€390 a month per live site" · button "Ask for Care" → `ShowcasePricing::ORDER_LINK`. *(All facts from `ShowcasePricing::care()`; T3 Ops facts from canon `t3ops_security_checks`, `t3ops_update_runs`.)*
9. `desiderio_featurecards` (columns 2), id anchor `supporter`, eyebrow "Thank you", header "Two more ways to support the work": **Supporter, €190 a year** — "No support included, on purpose. Your name or logo on this page and a thank-you in the release notes. For companies that earn with Desiderio and want to say so." · **Sponsored feature, from €1,290** — "You name the feature and the date, we build it and release it free for everyone, with tests, documentation and translations, and your name in the release notes. A custom element for your own use stays €890." Buttons → order page.
10. `desiderio_trustbadges` promises (`ShowcasePricing::promises()`), header "What every paid plan promises", subheadline "In the terms, not only on this page."
11. `desiderio_faq`, header "Honest questions", items (`ShowcasePricing::modelFaq()`): "What is the catch?" — "There is none we know of. The software is free because that is cheaper and safer for everyone, and we earn when you want our time." · "Do I have to pay if I earn money with Desiderio?" — "No. GPL-2.0 allows commercial use on unlimited sites. Pay when you want answers, work or Care." · "What if you stop maintaining it?" — "You keep every version and your sites keep working. Plan holders have the maintenance promise in their terms, and a missed patch extends the plan." · "Why no paid early access?" — "A release that waits for payment is a security fix that waits. Everyone gets every release the same day." · "Can I pay without needing support?" — "Yes: Supporter, €190 a year, or sponsor a feature." · "Who sends the invoice?" — (D11/Q4) "webconsulting GmbH, Austria. The legal notice and terms are on webconsulting.at." with the two external links.
12. `desiderio_ctabanner` (primary): header "Use it today. Call us when you want help." · description "Install Desiderio for free, or tell us the plan and the number of live sites. A maintainer answers within one business day." · CTA "Order or book a demo" → order page.

### 5.2 Home (`StyleguideShowcasePages::homeContent()`) — 15 elements

| # | Block | Change |
|---|---|---|
| 1 | `salesHero` | lead → "244 finished content elements and 15 theme presets, with backend previews and inline editing. Free with every release; paid plans add our time." CTAs unchanged (Download the demo · See pricing) |
| 2 | product film | unchanged |
| 3 | six problems (`featurecards`) | unchanged |
| 4 | theme in three steps | unchanged |
| 5 | **NEW four-ways block** (`featurecards`, columns 4) | eyebrow "Open source", header "Free software, paid care", subheadline "Everything is free with every release. You pay only for our time: answers, work by a date, or running your site.", items `ShowcasePricing::ways()` (same four cards as 5.1 step 3, descriptions trimmed to ≤ 25 words), followed by `ctainline` header "How we earn with free software", description "Why it is free, what always stays free and where the money goes.", CTA "Read how we earn" → `{{page:pricing/how-we-earn}}` *(counts as one section; if the card block has its own button field, use it and drop the inline CTA)* |
| 6 | 244 elements in 10 groups | unchanged |
| 7 | eight forms | unchanged |
| 8 | gallery, five elements | unchanged |
| 9 | three kinds of teams (`usecasegrid`) | each item ends with its path: "Free to start, Agency at €1,990 a year, Care per site when you want it." etc. (≤ 25 words) |
| 10 | pricing three-tier | subheadline → "Desiderio is free under GPL-2.0, every release. Plans add guaranteed answer times and the maintenance promise; Care runs your site for €390 a month." (if Q1 yes) |
| 11 | strategy checklist "Five points for all our software" | unchanged items; a `ctainline` follows with header "Not only TYPO3" and CTA "Watch the film and read the strategy" → strategy page *(the existing inline block, retitled; the film and transcript leave the home)* |
| 12 | "Everything in this lab" (`categorycards`, 4) | kept as the link into the lab; subheadline → "…Films, more sites and Kurt's usability talk are on the lab page." with the CTA/link → `{{page:resources}}` (the block has no CTA field → the subheadline sentence links, or a `ctainline` "See the lab" follows; the worker picks what the block supports) |
| 13 | four steps | unchanged |
| 14 | testimonials (example) | unchanged |
| 15 | CTA banner | unchanged |

Removed from home: 62 components (→ tech facts page), speed stats (→ tech facts page), five reasons (dropped), integrations (→ Product hub), strategy film + transcript, lab film + transcript, usability talk + transcript, "More sites" (→ lab page). Films on home after the change: the product film only.

### 5.3 /target-groups and the three audience pages (`ShowcaseAudiencePages`)

**Hub** (1094): hero header → "Built for the teams that ship TYPO3" (keep), lead → "Agencies deliver fixed-price projects faster, in-house teams publish without tickets, freelancers get a full design system. All of it free; care when you want it." Then the four-ways block (as on home, D10) → the three `textmedia` pitches, each rewritten problem-first: first sentence = the reader's cost today (template hours; developer tickets; unpaid design phases), second = what changes, third = what is free and what the recommended step costs → CTA banner "Not sure which plan fits?" unchanged.

**Each subpage** keeps `headersection → textmedia → benefitcards → pricingsimple → testimonial → ctabanner` and inserts after the benefit cards:

- `desiderio_featurenumbered` (columns 2 or 4), header "Your path with Desiderio", four steps per audience:
  - Agencies: 1 "Pitch with the free demo" (seed, three presets live) · 2 "Second client signs: Agency, €1,990 a year" · 3 "Brand Theme €2,490 and elements from €890, under your name" · 4 "Clients who want one partner: Care, €390 a month per site, billed to you"
  - In-house: 1 "Developers build the site once, free" · 2 "First campaign: Studio, €590 a year, and an editor training, €790" · 3 "Your brand as a preset: Brand Theme, €2,490" · 4 "No one in-house for TYPO3 operations: Care, €390 a month"
  - Freelancers: 1 "Install, seed, show the client 244 real elements" · 2 "First live client site: Studio, €590 a year" · 3 "Say thanks when it pays off: Supporter, €190 a year" · 4 "A client wants it run for them: Care, €390 a month per site"
- `desiderio_compare`, header "What you pay, what you get": left "Always free" (shared list) · right the audience's paid items with prices.

The business-case `pricingsimple` blocks stay (they are good: €249 per project, one campaign page a year, seven billable hours) and get one closing line linking the model page: "How we earn with free software: the full explanation." The testimonials keep "(example)".

### 5.4 /pricing (`ShowcasePricingPages::pricingPage()`)

- Hero lead → "Every element and every release is free and open source. Plans add guaranteed answer times and the maintenance promise; Care runs your site for you." Secondary CTA → "How we earn" → model page (replaces "Download the demo", which the footer and home already carry).
- After the comparison table: **Care block** (same data as 5.1 step 8; one source).
- After the services grid: **"Other ways to support"** (`featurecards`, columns 3): Supporter €190/yr · Sponsored feature from €1,290 · "Stars, issues and articles" (free; link to the repo). GitHub Sponsors card only when Q2 enables it.
- FAQ: add "Why is Desiderio free?" (one sentence + link to the model page) and "Who sends the invoice?" (D11).
- Founding offer, Launch Pack, promises: unchanged.

### 5.5 /resources → "Lab and resources" (`ShowcaseHubPages::resourcesPage()`)

Title "Lab and resources", navTitle "Lab and resources", abstract updated. Order: `salesHero` (badge "The lab", header "The webconsulting TYPO3 lab", lead "One TYPO3 v14 installation runs Desiderio, Astryx, our software and ten template sites. Everything here is live, free and seeded by command.", CTAs "See everything" → `#everything` or `{{page:features}}`, "Download the demo") → *(lab overview film, placed by the films seeder after the hero)* → "Everything in this lab" `categorycards` (moved from home, with computed counts) → "More sites on this TYPO3 installation" `categorycards` (moved from home) → "Where to go next" cards (existing six + **"Kurt's usability talk"** card → `{{page:usability-slogans}}`) → "Check Desiderio before you build on it" (existing) → FAQ (existing). Films: `lab-overview/film.json` placements → `[{slug: /resources}]`.

### 5.6 /usability-slogans — the talk page (new; restore `ShowcaseTalkPages` from `git show b0b53c3b^:Classes/Data/Showcase/ShowcaseTalkPages.php` and rework)

Parent `resources`. Title "Ten usability slogans", navTitle "Usability slogans (talk)", seoTitle "Ten usability slogans, explained by Kurt Dirnbauer", abstract "Kurt Dirnbauer's five-minute talk on ten usability slogans after Jakob Nielsen, with transcript, and what they mean for editors." Content: `headersection` (eyebrow "From the founder", header "Ten usability slogans, explained by Kurt Dirnbauer", lead "Usability means real people can use a product and reach their goals without friction. Five minutes on ten slogans every product team should know.", center) → *(the long cut, portrait, placed by the films seeder after the header section; `usability-slogans/long/film.json` placements → `[{slug: /usability-slogans}]`, variant portrait, autoplay false)* → `desiderio_featurenumbered` (columns 2), header "The ten slogans", the ten items (from the film's description list, one sentence each) → `desiderio_textmedia`, header "What this means for editors", content: slogans 4, 8 and 9 mapped to backend previews, plain element names, inline editing; source line "Slogans after Jakob Nielsen, Usability Engineering, 1993, ch. 1.3." with the nngroup link and the blog link (German) → `desiderio_ctabanner` "See how in-house teams publish without tickets" → `{{page:target-groups/for-inhouse-teams}}`.

### 5.7 Images

- New hero photo `hero-how-we-earn-<hash8>.webp` (`ShowcaseHeroPhotos['how-we-earn']`): realistic, two people at a desk looking at a laptop with a plain spreadsheet-like page, daylight, no logos; `ddev exec vendor/bin/typo3 sitepackage:llm:generate-image` (gpt-image-2.5, 1536×1024) cropped to 1440×900 WebP like the others (memory `desiderio-demo-assets`).
- The talk page uses the film's poster; the lab page reuses the `resources` hero photo. No other new images.

## 6. Films

### 6.1 Placement changes (`WT/packages/site_package/Resources/Private/Fixtures/Films/*/film.json`)

| Film | Before | After |
|---|---|---|
| `typo3-v14-strategy` | strategy page + home after "Five points for all our software" | strategy page only |
| `lab-overview` | home after "Everything in this lab" | `/resources` (after the hero) |
| `usability-slogans` (long) | home after the lab-overview film | `/usability-slogans` (after the header section), `variant: portrait`, `autoplay: false` |
| `how-we-earn` (new, D9) | — | `/pricing/how-we-earn` (after the hero) |

`sitepackage:films:seed --dry-run` after the reseed must list exactly these moves; a second real run reports 0 changes.

### 6.2 New film "Free software, paid care" (id `how-we-earn`, 50–60 s, 1920×1080, fal voice George, music bed late)

Scenes (the `film.html` builders): `hook` "Everything here is free. Here is how we pay the rent." → `grid` four tiles Use it · Support it · Ask for features · Let us take care (one line each) → `points` "Always free": every release, every fix, the demo, commercial use → `shot` pricing page with the plans, caption "Plans add our time: €590, €1,990, €4,900 a year" → `shot` T3 Ops dashboard (existing `frontend-feature-t3ops-overview-*.webp`) caption "Care: we run your site, €390 a month per site" → `points` "What we never do": paid early access, licence keys, countdown timers → `end` "Use it today. Call us when you want help." Narration ≈ 115 words at 2.2 words/s; the `say` lines are the first draft for WP6's check; no number outside `ShowcasePricing`. Fixture folder `Films/how-we-earn/` (MP4 < 6 MB, poster, VTT, `film.json` with transcript, `placements`, `video.description`). Spec `films/how-we-earn.mjs`, registry entry in `films/index.mjs`.

## 7. Copy and the marketing skills (WP6)

- Read first: `MAIN/var/research/positioning.md` (last round's `.agents/product-marketing.md`-style context; git-ignored). Extend it with sections 2.1–2.4 (business model, the four ways, objections: "small vendor", "free means abandoned", "why not early access", anti-persona: buyers who want a private build) before writing.
- Then apply, in this order, the checklists of `pricing` (pricing page: tiers above the fold, the recommended plan marked, FAQ answers objections, one metric), `offers` (every paid item names deliverable, guarantee, real limit; banned vocabulary), `copywriting` (headline = outcome, lead = one new fact, proof beside each claim, one primary CTA per page), `cro` (one path per page, no competing CTAs), `copy-editing` (cut, then cut again). Where a skill and the house style disagree, `docs/content/style-guide.md` wins (calm, no superlatives, no rhetorical questions in body copy, British English).
- `lint-copy.php --site=desiderio --php='<Class>::<method>'` for `ShowcasePricing::ways`, `::care`, `::modelFaq`, `ShowcasePricingPages::pages`, `ShowcaseAudiencePages::pages`, `ShowcaseHubPages::resourcesPage`, `ShowcaseTalkPages::pages`, `StyleguideShowcasePages::homeContent`; `sitepackage:content:audit --site=desiderio` after the seed: no new hard errors.
- Canon updates in `WT/packages/site_package/Resources/Private/Data/Content/canon.yaml`: `facts.desiderio.pricing` (+ Care), `facts.desiderio.services` (+ Supporter, Sponsored feature; replace "no hosting" with "Care is the only hosting-related offer and is never called managed hosting"), `facts.desiderio.model` (the four ways, one sentence each, source `ShowcasePricing::ways()`), the `forbidden` rule of line 142 gets the exception for the two webconsulting.at links on the three sales pages (D11), and a new forbidden pattern for the retired home phrasing "Paid plans add support" is **not** added (the phrase stays valid on the pricing hero).

## 8. Code, tests and data to touch

| Repo | File | Change |
|---|---|---|
| DES | `Classes/Data/Showcase/ShowcasePricing.php` | `ways()`, `care()`, `supporter()`, `sponsoredFeature()`, `alwaysFree()`, `costsMoney()`, `modelFaq()`, `SELLER` constant (D11); docblock types |
| DES | `Classes/Data/Showcase/ShowcasePricingPages.php` | `howWeEarnPage()` in `pages()`; pricing page additions (5.4); order page seller sentence |
| DES | `Classes/Data/Showcase/ShowcaseAudiencePages.php` | 5.3 |
| DES | `Classes/Data/Showcase/ShowcaseHubPages.php` | `resourcesPage()` → lab and resources (5.5); `productPage()` + integrations block; tech facts page + components/stats if absent (`StyleguideShowcasePages::technicalFeaturesPage()`) |
| DES | `Classes/Data/Showcase/ShowcaseTalkPages.php` (restored) | 5.6; hooked into `StyleguideShowcasePages::subpages()` after `ShowcaseHubPages::resourcesPage()` (parent resources) |
| DES | `Classes/Data/StyleguideShowcasePages.php` | `homeContent()` (5.2), `subpages()` hook |
| DES | `Classes/Data/Showcase/ShowcaseHeroPhotos.php` + `Resources/Public/Styleguide/Heroes/hero-how-we-earn-*.webp` | 5.7 |
| DES | `Tests/Unit/…` | `ShowcasePricingTest` (every price string matches `^€[0-9,]+( a (month|year)( per live site)?)?$`-style; `ways()` has four items with links; `care()`/`supporter()` present), `ShowcasePricingPagesTest` (model page outline pinned: hero → featurecards → textmedia → compare → stats → featuretimeline → pricing block → featurecards → trustbadges → faq → ctabanner), `ShowcaseTalkPagesTest` (outline), `MenuNamesTest` green, `ShowcaseCardLinksTest` (no plain `/paths`), the existing home-related tests (`StyleguideFeatureVideoTest` if it counts home films) |
| DES | `CHANGELOG.md`, `ext_emconf.php`, `composer.json` | 4.29.0 |
| WT | `Fixtures/Films/{typo3-v14-strategy,lab-overview,usability-slogans/long}/film.json` | 6.1 |
| WT | `Build/Film/films/how-we-earn.mjs`, `films/index.mjs`, `Fixtures/Films/how-we-earn/*` | 6.2 |
| WT | `Tests/E2E/usability-slogans.spec.js` | default path `/usability-slogans/`; home asserts **no** talk block; `frontend.spec.js`: home film count 1, `/pricing/how-we-earn/` and `/resources/` 200 with one h1 |
| WT | translation memories de/zh/hu | append (section 9) |
| WT | `canon.yaml` | section 7 |
| WT | `docs/content/seeding.md` | the three moved films and the new one |
| WT | `composer.json`/`composer.lock` | desiderio 4.29.0 |
| WT | `CHANGELOG.md` | "Install desiderio 4.29.0: …" |
| MAIN | `var/prod-desiderio-4.29/{run.sh,verify.php}` (git-ignored) | section 11 |
| astryx_typo3 (MAIN/packages) | `AstryxSiteDefinitions.php` | only if Q1 yes: one sentence about Care in the tiers intro; separate release of astryx if touched (**default: leave Astryx untouched this round**, note it as a follow-up) |

## 9. Translations (WP7)

1. After the English seed is final in the lab: `ddev exec vendor/bin/typo3 sitepackage:content:translate --site=desiderio --dry-run --missing=- > /tmp/missing.json` (in the container; `ddev exec cat`). Expect roughly 250–400 strings per language (model page, home changes, four audience pages, pricing additions, lab page, talk page, the new film's header/caption/transcript).
2. Translate (Opus, high) into German (Sie), Chinese and Hungarian in the memory's style: short, plain, product and plan names untranslated ("Care", "Supporter" stay English as names; the explanation is translated), prices with the same digits, "GPL-2.0" untouched. Keys = the stored, RTE-transformed text from the dry run, never from the PHP source.
3. Append at the end of each file's `strings` object by text edit; `python3 -c 'import json;json.load(open(...))'` per file; `ddev mutagen sync`.
4. Full run `sitepackage:content:translate --site=desiderio` (the reseed drops element translations); then `--dry-run --missing=- --fail-on-missing` must pass.
5. Spot-check `/de/pricing/how-we-earn/`, `/zh/target-groups/for-agencies/`, `/hu/resources/`, `/de/usability-slogans/` render translated, the German film transcript accordions included.

## 10. Verification gates

| Gate | Check | Owner |
|---|---|---|
| Desiderio unit + static | `cd DES && Build/Scripts/runTests.sh -s unit`, `-s phpstan`, `composer cgl:check`, `-s validate`, `-s assets` | WP1–WP4 |
| Copy | `lint-copy.php` per class (section 7); `sitepackage:content:audit --site=desiderio` no new hard errors; every price string on the site appears in `ShowcasePricing` (grep `€` in the rendered pages vs the class) | WP6 |
| Seed | `desiderio:styleguide:seed --dry-run` → real → second real run 0 new pages; `webcon-jev:examples:seed`; `webcon-templates:overview:seed`; `sitepackage:films:seed` (dry run lists exactly the 6.1 moves + the new film; second run 0); `sitepackage:apply-downloads-page … 505` | WP7 |
| Pages | `/`, `/pricing/`, `/pricing/how-we-earn/`, `/target-groups/` + 3, `/resources/`, `/usability-slogans/`, `/typo3-v14-strategy/` answer 200 in en/de/zh/hu, one `<h1>` each; home has 15 elements and 1 film block; the lab page has the lab film after its hero; the talk page plays the long cut (portrait, poster, captions, no autoplay); the strategy page still has its film; menu shows "Lab and resources" and "How we earn" in the Pricing flyout | WP8 |
| Visual | Playwright full-page shots (`reducedMotion: 'reduce'`, scroll + decode first) of home, model page, pricing, the four target-group pages, lab page, talk page at 1440 and 390, light and dark: no empty grid slots, no orphan cards, no overflow | WP8 |
| Accessibility | `@axe-core/playwright` on the nine pages: no new serious/critical findings | WP8 |
| Lab tests | `Build/Scripts/runTests.sh -s quality` in MAIN; `npx playwright test` (updated specs) | WP8 |
| Film | 6.2 stills review, `--check` on the narration, every number in the film matches `ShowcasePricing` | WP5 |
| Solr | `sitepackage:solr:reindex --index`; "how we earn" and "usability slogans" found on `/search` | WP8 |
| Translations | `--dry-run --missing=- --fail-on-missing` passes | WP7 |
| Release | desiderio CI green except the pre-existing "Theme contrast" job; lab Quality green; Coolify finished; live 401; prod run `ALL-DONE` + `VERIFY-OK` | WP9 |

## 11. Release, deploy, production data (WP9)

1. **Desiderio 4.29.0** from DES branch `feature/open-source-model` (WP0 creates it from `main` = v4.28.0): `ext_emconf.php` + `composer.json` version, CHANGELOG `[4.29.0]`, `composer cgl:fix` on touched files, explicit-path commits, `git merge --ff-only` into `main`, tag `v4.29.0`, push `main` + tag to `origin` and `gitlab`, CI green (the red "Theme contrast" job is pre-existing, section 5 follow-ups of the last plan).
2. **Lab lock bump** in WT (`/opt/homebrew/opt/php@8.5/bin/php $(which composer) update webconsulting/desiderio --no-install --no-scripts`); in MAIN `Build/Scripts/lab-link.sh --restore webconsulting/desiderio` then host-side `composer install` with PHP 8.5 (the powermail GitLab mirrors need the host SSH key; memory `desiderio-4-28-software-ai-round`), OPcache clear, dump-autoload, `extension:setup`, `cache:flush`, page gate again.
3. **Lab commits** in WT, explicit paths (`packages/site_package/**` incl. the film fixtures and memories, `Tests/E2E/*.spec.js`, `docs/content/seeding.md`, `docs/plans/**`, `CHANGELOG.md`, `composer.json`, `composer.lock`), message style "Install desiderio 4.29.0: …".
4. **Push**: `git fetch origin`; `git log --oneline origin/main..HEAD` as its own command (own commits only); `git push origin <sha>:main`; fast-forward the T3 branch; `git push gitlab <sha>:main`.
5. **Deploy**: the Quality workflow deploys through Coolify; check `df -h /` and `free -m` on the host first (SSH works today); wait for `finished`; live answers 401.
6. **Production data**: `MAIN/var/prod-desiderio-4.29/run.sh` from the 4.28 one (guards on desiderio 4.29.0, the new film folder, the memory entries; backup; the seed chain; `content:translate`; downloads page; flush; warm libraries; Solr; `verify.php`: 2 new pages in 4 languages, home film blocks = 1, lab film on `/resources`, talk on `/usability-slogans`, `how-we-earn` film on its page, "Lab and resources" nav title, equal page counts per language, no `€49`/`€149`, every `€` string in the rendered pages is in `ShowcasePricing`). Run `sh var/prod-run.sh var/prod-desiderio-4.29` during a push freeze; `--follow` until `ALL-DONE`; rerun `FROM=<step> BACKUP=0` if cut.
7. **Health**: live 401; in-container `curl` of the nine URLs = 200 (through the web container with the production host header); Coolify containers healthy; the plan's release log (11.1) written and committed `[skip ci]`.

### 11.1 Release log

- 06:50 WP0 (orchestrator): DES branch `feature/open-source-model` from v4.28.0; desiderio linked into the lab again; home, login, pricing, target groups 200.
- 07:0x WP1a (Opus 5.5 high): DES `d9bfe170` price source (Care €390/month per live site, €3,900/yr, further sites €290; Supporter €190/yr; sponsored feature from €1,290; support hours €690; `ways()`, `alwaysFree()`, `costsMoney()`, `modelFaq()`, `modelSentence()`, SELLER constants), `5ec4de58` test, `2e287ab5` copy limits; WT `1bb8cbc` canon (pricing, services, model, seller facts). 1,047 unit tests, PHPStan, cgl, lint-copy green.
- 07:04 WP5: WT `e2c16b3`: film `how-we-earn` (58.4 s, 2.85 MB, aurora background, George), placements moved (strategy → strategy page only, lab-overview → /resources, talk → /usability-slogans), E2E specs (8 passed, 12 skipped until the pages exist), `docs/content/seeding.md`. Orchestrator checked stills and the chapter counter of the final MP4 (02/05 … 05/05).
- WP1b+4: DES `82538f14` hero photo `hero-how-we-earn-6cc03feb.webp` (gpt-image-2.5), `8e27a5fc` how-we-earn page (11 blocks), pricing page (Care block, other ways, FAQ "Why is Desiderio free?" and "Who sends the invoice?"), order page names webconsulting GmbH; `ShowcasePricingPagesTest`.
- WP2: DES `52d5c73b` home 15 sections / 17 elements (one film), tech facts page gets the components and speed blocks, product hub gets the integrations block, `/resources` → "Lab and resources" with the lab cards (shared helper `labOverviewCards()`), more sites and the talk card, `/usability-slogans` restored under Resources; `StyleguideHomeContentTest`, `ShowcaseTalkPagesTest`.
- WP3: DES `7f23816f` hub model block + problem-first pitches, three audience pages with "Your path with Desiderio" and the pay-and-get section, every price from the price source; `ShowcaseAudiencePagesTest`. Finding: the `compare` block reads its left column as "not included" → WP6.
- WP6: DES `d93ea323` free/paid as `pricingtwotier` (model page + audience pages), `af79ec4c` `feature-numbered` two columns for 4 or 10 items (template + CSS, no schema change), `3848e684` icon `message-circle`, "Support it" → GitHub, sponsored text 24 words, `ShowcasePricing::service()`, `d35b4b5f` typed prices on the tech facts and pricing pages from the price source, `ab65ccdb` `ctainline` on /target-groups, `54358e61` copy pass + stale functional-test comment. 1,075 unit tests, PHPStan (host PHP 8.5), cgl green.
- Orchestrator: `MAIN/var/prod-desiderio-4.29/{run.sh,verify.php,workos-nav.php}` written (guards on 4.29.0, Care, the new film, the moved talk placement, the memory entry; verify: new pages, lab nav title, one film on home, films on their pages, Care blocks, no compare block, no retired prices, equal counts per language).
- WP7 started: reseed chain + translations (see below).
- WP7 (done, WT `e5960ee`): mirror 0 files (MAIN already had WP5's work); styleguide seed 97 pages, 2 new (8341 `/pricing/how-we-earn` under 2230, 8342 `/usability-slogans` under 2232), 0 moved, 821 elements, second run 0 new; jev 5 forms / 10 pages; templates overview 0 changes; films seed 120 created (none on 505; strategy 1121 only, lab-overview 2232, talk 8342 portrait, how-we-earn 8341), dry run after 0; Innesto 22 unchanged; Downloads page updated. 215 missing strings per language translated and appended (de `1.990 €`, zh `€1,990`, hu `1990 €`); full translate 07:52–08:31 (39 min natively; 2026-11-04 took 4.5 h under qemu), `--fail-on-missing` 0; Solr 130 documents on site 505, 0 failures. Audit: 69 errors / 563 warnings site-wide, of which new: the how-we-earn film headline (9 words) → WP8-B; moved with the lab/talk films: a 31-word alt sentence, US spellings in the talk transcript → WP8-B. Found: the de/zh/hu audience subpages carry old localized slugs (`/target-groups/for-agencies-integrators` …) → WP8-B fixes them in the lab and in the prod run.
- WP8-A (QA of the English pages, parallel to the translate run): all 13 pages 200 with one h1; menus right; films on their pages (home: product film only; talk portrait with poster, controls, captions, no autoplay); 94 media URLs and 69 links 200 incl. the two webconsulting.at links; no placeholders; every € amount from the price source; axe clean on 60 scans (no serious/critical, new or old); E2E 20 passed; `verify.php` fixed (page lookup limited to the desiderio root 505, per-language count by `l10n_parent`), one real failure left until the translations land. **Findings for WP8-B (content, after WP7):** (1) home theme step 3 and `ShowcaseSuccessStories.php:153` use `frontend-pricing-midnight-dark-87c4160e.png`, which shows the retired "Pro €49 / Agency €149" → recapture or swap; (2) "Recommended plan" ribbon on the "Our time" tier of the model page → `featured => false` there (keep on the audience pages); (3) the talk film block repeats the header section's eyebrow/header and lists the ten slogans again → own eyebrow/header/description in `usability-slogans/long/film.json`; (4) pricing services grid 7-in-4 (pre-existing) → add the sponsored feature as the eighth service card and shrink "Other ways to support" to two cards; (5) "Free to use. Paid plans add support." (home pricing block, pricing hero) → "…add our time"; (6) home "Everything in this lab" needs a link to `/resources` (inline CTA "See the lab"); (7) Supporter copy promises a logo "on our How we earn page" that has no supporters section → "in the release notes and, from the first Supporter on, on this page"; (8) lab hero "See everything" → `/features` is odd → secondary CTA to the strategy page; (9) model page title → "How we earn" (sr-only h1 no longer repeats the hero); (10) "What this means for editors" has no media → use the Visual Editor screenshot. Minor, left: testimonial quote mark overlap (existing style), product film without a captions track (burned-in captions, existing).
- WP8-B (done): DES `2df33fb7` (dark pricing screenshot `frontend-feature-pricing-dark-dcf6dbfd.webp` with the current plans; the old PNG stays for six element fixtures → follow-up), `de157449` (model page title "How we earn", no ribbon on its "Our time" tier, eight service cards incl. the sponsored feature, two-card "Other ways to support", pricing hero "add our time", Supporter copy without the logo promise), `b18c6f2c` (home: "add our time", "See the lab" inline CTA, sample quotes removed → 17 elements = 14 sections + 3 inline CTAs; lab hero CTAs download + strategy; talk page editors block with the Visual Editor shot); WT `4dbc412` (talk film block "The talk / Watch the five-minute talk", "How we earn, in 58 seconds", lab alt split, British spellings in the talk transcript/VTT, E2E spec, style-guide example), `4a4c7c9` (19 strings × 3 languages). Localized slugs of the audience pages fixed in the lab (9 rows, old URLs 307 via auto redirects 961–969), `fix-localized-slugs.php` added to the prod run after translate, `verify.php` +4 checks. Second chain: 0 new pages, films 120 created then 0, translate 09:17–10:01 (44 min), 0 missing, Solr 130 docs; 40/40 URL checks in four languages, home 17 elements / one video, E2E 20/20, `VERIFY-OK`, audit 0 errors on the nine pages (64 elsewhere, none from this round). `SiteTranslator` already gives new localized pages the parent's slug.
- WP9 started (Opus 5.5 high): release 4.29.0, lab lock, push, deploy, prod data run, health.
- WP9 done 2026-11-05: desiderio `877ddd28` tagged `v4.29.0` (origin + gitlab; CI green except the old "Theme contrast" job); lab `e0d995a` on GitHub and GitLab; Quality run 42003720144 green, Coolify image `e0d995a`; prod backup `/var/backups/typo3-lab/20261105T094350Z-pre-desiderio-4.29`; data run 10:43–13:43 local (translate ≈ 2 h 20 min on prod), 97 pages, 821 elements, 120 films, 10 localized slugs fixed, Solr 130 documents; `VERIFY-OK` + `ALL-DONE`; 28 URLs (7 sales pages × 4 languages) answer 200.
- WP10 started (Opus 5.5 high): the five follow-ups (retired-price PNG in six fixtures, 64 audit errors, `/adress` → `/address`, Care on Astryx, `FUNDING.yml` Sponsor button), release 4.29.1.
- WP10 (released): desiderio `v4.29.1` = `8d67a05b` (six fixtures on `frontend-feature-pricing-dark-dcf6dbfd.webp`, the case study on the dark contact form; PNG deleted, capture entry dropped, `RetiredScreenshotTest`; audit copy; `.github/FUNDING.yml` → `/pricing/how-we-earn`, `github:` prepared as a comment, `FundingFileTest`), astryx-typo3 `v2.6.2` = `c67305f9` (Care, support hours, sponsored feature, Supporter, model sentence, seller FAQ; prices from `ShowcasePricing`, requires desiderio `^4.29`; `AstryxPricingTest`; FUNDING.yml), webcon-jev `v0.3.2` = `0d220f0`, webcon-templates `v1.0.4` = `fe4b9c1`; lab: audit counts inline code as one word and ends a sentence after a closing quote (2 false-positive kinds), 22 film alts/descriptions split, `desiderio/address-slug.payload.json` (folder 569 is a sysfolder: no frontend URL, no redirect; page 555 on pid 15 left as is), memories +44 de / +48 zh / +48 hu. Local: reseed 505 + Astryx, translate 0 missing, audit 0 errors on 505 (64 before; 558 warnings), E2E 71 passed / 3 skipped, axe clean on the changed elements (pre-existing: `stats-bar` dl structure on trust-social-proof), `verify.php` VERIFY-OK. Desiderio 1,080 unit tests, astryx 53, jev 137, site_package 182; PHPStan level 8 clean (desiderio, astryx). Prod runner `MAIN/var/prod-desiderio-4.29.1/`.

## 12. Work packages (Opus 5.5, effort high)

Rules for every worker: read sections 1–3 and 12 first, then your package's sections; edit only the files your package owns; never `git add -A`, never stash; commit in WT (lab) or DES (desiderio) with explicit paths and a one-line message in the repo's style; report what was verified and what was not; never print secrets; never two seeders or translate runs at once; `ddev mutagen sync` before container reads of host edits; `ddev` runs from MAIN (`cd MAIN && ddev …`), never from WT; the lab-mirror script moves WT edits into MAIN. All prices come from `ShowcasePricing`; nobody types a number twice. Tone: section 2.6.

| WP | Scope | Owns | Depends on | Effort |
|---|---|---|---|---|
| **WP0 Foundations** | Resolve Q1–Q5 into section 3 (the orchestrator does this before starting WP1); DES: `git fetch`, `git checkout -b feature/open-source-model main` (main = v4.28.0), `MAIN/Build/Scripts/lab-link.sh webconsulting/desiderio`, `ddev composer dump-autoload`, OPcache clear, `cache:flush`, `/` and `/typo3/login` 200, `lab-link.sh --status`; `MAIN/var/research/positioning.md` + section 2 (business model, objections, anti-persona); canon facts for the new prices (section 7) in WT | DES branch; WT `canon.yaml`; `var/research/positioning.md` | — | 1 h |
| **WP1 Pricing data + model page** | `ShowcasePricing` additions (D2), `ShowcasePricingPages::howWeEarnPage()` (5.1), hero photo (5.7), `SELLER` + order page sentence (D11), tests (`ShowcasePricingTest`, `ShowcasePricingPagesTest`), lint-copy | DES: `ShowcasePricing.php`, `ShowcasePricingPages.php` (model page + order page only), `ShowcaseHeroPhotos.php`, `Heroes/hero-how-we-earn-*.webp`, tests | WP0 | 0.5 day |
| **WP2 Home split, lab page, talk page** | 5.2, 5.5, 5.6, D8 moves (`productPage()` integrations, tech facts components/stats), `ShowcaseTalkPages` restored and reworked, `subpages()` hook, `MenuNamesTest`, `ShowcaseTalkPagesTest`, lint-copy | DES: `StyleguideShowcasePages.php`, `ShowcaseHubPages.php`, `ShowcaseTalkPages.php`, tests | WP1's commit for `ShowcasePricing::ways()` (WP2 waits for it, ≈ 2 h into WP1; until then it uses a stub call it replaces) | 0.5 day |
| **WP3 Target groups** | 5.3 on the hub and the three pages; lint-copy; tests green | DES: `ShowcaseAudiencePages.php` | WP1's `ShowcasePricing` commit | 0.5 day |
| **WP4 Pricing page additions** | 5.4 (Care block, other ways, FAQ, hero lead/CTA); tests | DES: `ShowcasePricingPages::pricingPage()` only (WP1 owns the rest of the file: WP4 starts after WP1 commits) | WP1 | 2 h |
| **WP5 Films** | 6.1 placement edits; 6.2 new film (spec, audio, render, fixture, `film.json`); E2E spec updates (section 8); `docs/content/seeding.md`; dry-run proof after WP7's reseed (not before: the pages do not exist yet) | WT: the three `film.json`, `films/how-we-earn.mjs`, `films/index.mjs`, `Fixtures/Films/how-we-earn/*`, `Tests/E2E/*.spec.js`, `docs/content/seeding.md` | WP0 (film can render against the lab's current pricing page shot and the T3 Ops shot; the hero shot of the model page is replaced after WP7 if used) | 0.5 day |
| **WP6 Copy pass** | Section 7 over every touched class and the film narration; one wording for the model sentence everywhere; canon final | DES definition classes (edits only), WT `canon.yaml`, film `say` lines (before WP5's audio where possible) | WP1–WP4 drafts | 3 h |
| **WP7 Seed + translate** | lab-mirror WT → MAIN; seed chain (steps 1, 2, 3, 4, downloads page, films, 14 of `docs/content/seeding.md`); section 9; `--fail-on-missing` passes; `content:audit` | WT memories (append only) | WP1–WP6 | 0.5 day + translate runtime |
| **WP8 QA** | Section 10 gates; findings go back to the owners; the home element count and film counts; verify.php dry-tested against the lab | WT `Tests/E2E/` fixes; findings | WP7 | 3 h |
| **WP9 Release + production** | Section 11; memory and plan log | DES main/tag; WT lock + commits; `MAIN/var/prod-desiderio-4.29/` | WP8 | 3 h + prod run |

Parallel schedule: WP0 → WP1 (first 2 h: `ShowcasePricing` committed) → {WP1 rest, WP2, WP3, WP4, WP5} in parallel → WP6 → WP7 → WP8 → WP9. Wall clock ≈ 1.5 working days with four workers; effort ≈ 4 worker-days. Machine limits as before: one film render and one Playwright run at a time; never two seeders.

## 13. Open questions (the owner's decisions; defaults the workers use if the owner says "recommended")

**Decided 2026-11-05 (the user: "starte mit umsetzung. mit opus 5.5 high."): every question takes its recommended default — Q1 (a) Care €390/month per live site, €3,900 a year, further sites €290; Q2 (a) Supporter €190 a year and Sponsored feature from €1,290, GitHub Sponsors later; Q2b yes, support hours €690 / 5 h; Q3 (a) the home split with the lab page, the talk page and the new film; Q4 (a) webconsulting GmbH named as the seller with links to webconsulting.at/impressum and /agb. Workers treat these as settled.**

| Q | Question | Recommended default | Alternatives |
|---|---|---|---|
| **Q1** | Add **Care** (we run your site: own Coolify instance via Mini-Mi, T3 Ops updates within the maintenance promise, security checks four times a day, monitoring, daily backups, next-business-day answers) at **€390 a month per live site** (€3,900 a year)? | Yes, at €390/month per site (€3,900 a year, two months free); further sites of the same customer €290/month. The calculation is in 2.5: cost floor €345 (2 h at €130 + €40 infrastructure + 15 % tooling and risk); €390 follows the house pattern and leaves a 13 % buffer. Forge12 care plans start at €159/month without hosting; agency retainers run €200–600. It is the only offer that compounds monthly and the one the user described ("we take care of everything, but that costs"). | (b) €340/month (the computed floor plus 15 %, effective €150/h); (c) Care "on request" without a price (weaker: a price is the fact buyers scan for); (d) no Care this round |
| **Q2** | Add **Supporter €190 a year** (recognition only: name or logo on the model page, thank-you in release notes) and **Sponsored feature from €1,290** (≈ 10 h at €130/h: build, tests, docs, translations, public release, sponsor named)? And do you enable **GitHub Sponsors** on `github.com/sponsors/dirnbauer` (owner action; the site adds the link and `FUNDING.yml` only once it exists)? | Supporter yes, Sponsored feature yes, GitHub Sponsors: the site is written so it works without it; enable it when you have ten minutes (Stripe/bank details), then one card and `FUNDING.yml` are added in a patch release | Supporter at €290; no Supporter (keep "support" as stars and reports only); no sponsored features |
| **Q2b** | Raise **support hours** from €650 to **€690 / 5 h**? They are the only service sitting exactly on the €130/h floor (2.5). | Yes, €690 (€138/h); every other service stays | Keep €650 |
| **Q3** | Home split as in D5–D8: the lab (overview film, "Everything in this lab" cards stay on home as the link, "More sites") moves to `/resources` renamed **"Lab and resources"**; the usability talk gets its own page `/usability-slogans`; the strategy film leaves the home (the five points stay as a checklist) | Yes | (b) keep the strategy film on home (home 17 elements); (c) a new first-level "Lab" item (8 nav items: against the 4–7 rule) |
| **Q4** | **Seller on the sales pages:** name "webconsulting GmbH, Austria" as the party that invoices on the model page FAQ, the pricing FAQ and the order page, with links to webconsulting.at/impressum and /agb; the Desiderio demo imprint stays fictional | Yes (a buyer must know whom they pay; without it the plans are not a real offer) | (b) say nothing (as today); (c) move ordering to webconsulting.at (separate project) |
| **Q5** | The 50–60 s film for the model page (D9, 6.2) | Yes | No film this round |

## 14. Web facts used in this plan

- news.typo3.com, "The TYPO3 Suite Is Here" (Daniel Fau, 2 Nov 2026): commercial repository, early access to selected features, via official TYPO3 Partners only, first feature REST API on 9 Nov 2026, TYPO3 CMS stays free, CRA manufacturer responsibility; prices on suite.typo3.com.
- in2code.de, Early Access Program page (fetched 5 Nov 2026): €389/€349 sponsor, €189/€179 freelancer, non-profits free, +€15 Packagist until 31.03.2027, free at most 9 months after the LTS; the page is internally inconsistent (EAP13 wording).
- opensourcepledge.com (fetched 5 Nov 2026): > $2,000 per developer per year to maintainers; $9,200,672 raised, $4M+ in the past year.
- forge12.com (search result, 5 Nov 2026): care plans from €159 a month. typo3.com ELTS v12.4 until 30 April 2029.
- Corey Haines, marketingskills (GitHub, MIT): `pricing` v2.1.4, `offers` v1.0.3, `product-marketing` v2.2.0, `site-architecture` v2.1.0, `community-marketing` v2.0.2 (fetched 5 Nov 2026).
- OSS pricing analyses (search results, 5 Nov 2026): React Flow's move from a sponsors page to a tiered pricing site; Babel's $24k/yr Open Collective plan with 2 h support a month; Plausible, Sidekiq, Cap, Grafana models.
