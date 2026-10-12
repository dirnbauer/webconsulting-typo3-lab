# Jev form check — 12 October 2026

Target: <https://typo3-lab.webconsulting.at/desiderio-powermail-lab/>

## Result

All five Jev examples completed synthetic browser submissions. Their conditional branches also matched the configured rules. Production uses `MAIL/transport = mbox`: messages and acknowledgements are retained on the server, not delivered externally. The recipient displayed by the demo is the selected route, not evidence of external delivery.

| Example | Verified browser behavior | Saved test mail | Selected recipient |
|---|---|---:|---|
| 07 Contact routing | Required-field validation; invoice enquiry; successful confirmation | 22 | accounting@webconsulting.at, 100% |
| 08 Support triage | Small bug → reproduction; severe outage → reproduction + escalation; ordinary question → both hidden; successful submission | 23 | engineering@webconsulting.at, 100% |
| 09 Quality gate | Empty/noise → send hidden and hint visible; genuine enquiry → send visible; reverse transition and successful submission | 26 | office@webconsulting.at, 98% |
| 10 Project enquiry | Small bakery → timing only; large confidential tender → tender/procurement/NDA; back/next and successful submission | 24 | bids@webconsulting.at, 100% |
| 11 Job application | Senior remote developer → portfolio/leadership/remote; student → portfolio/study; project manager → leadership; successful submission | 25 | pm-hiring@webconsulting.at, 100% |

Synthetic identity: `Jev Lab QA`, `jev-qa@example.invalid`. No real applicant or customer data was used.

## Translation repair and explanatory content

The Jev demos had English and German forms, but no Chinese or Hungarian form overlays after an earlier reseed. The scoped DataHandler localization created 110 translated form/fieldset/field records (55 per language), retaining marker names, option values and canonical form IDs. The page translator created 20 content overlays and five missing Hungarian page overlays. Repeat preflights were idempotent.

All 20 form pages (EN/DE/ZH/HU × five demos) returned HTTP 200 with translated headings, labels, placeholders, options, step buttons, consent and required-message attributes. Twelve condition requests using DE/ZH/HU text passed expected support and quality-gate branches. Chinese support was also checked in the browser: its reproduction field and escalation note appeared in Chinese.

The five explanatory texts and the overview are maintained in `packages/site_package/Resources/Private/Data/Content/jev/`. They describe what to enter, what changes, thresholds and uncertainty, the mail sink, and the synthetic-data boundary. They replace unsupported one-second timing claims and explain both quality-gate predicates. No full Jev reseed was run.

## Evidence and scope

- Browser tests above used the live site outside workspace preview.
- Ten English condition endpoint cases passed (HTTP 200, two evaluation loops).
- Jev source unit suite: 137 tests, 438 assertions passed.
- Jev isolated SQLite functional suite: 91 tests, 814 assertions passed; two deprecation notices.
- Versions: TYPO3 14.3.8; Jev v0.3.0 (`f7829d90`); Powermail 14.0.3.7 (`db4a3a04`); Conditions `5157fdca`.
- Local evidence: `var/jev-check/jev-condition-matrix-results.jsonl`, `jev-localized-http-verification.jsonl`, `jev-current-submissions.json`, `localized-verification.md`.

AI outcomes may vary. This check verifies the stated examples, not every possible natural-language input. Translated HTTP checks did not submit all 15 translated forms. External SMTP delivery was deliberately not tested. The quality gate is only a frontend demonstration, not server-side spam enforcement. The application demo does not make hiring decisions.

A browser automation caveat: programmatic fill alone did not consistently fire a native change event in the connected Chrome session. Pressing a real key and leaving the field produced the expected requests; this was not a reproduced Jev defect.

## Required-selection translation fix

Desiderio's required checkbox/radio errors could fall back to the browser language even when the page selected another language. The Composer patch checks native `validity.valueMissing` and uses the existing translated required message. Two failing regressions were reproduced before the fix; all eight focused message-selection tests pass afterward. Composer validation and patch preimage/checksum verification also pass.

The site was independently redeployed during this check from `16780edb` to `e77b6664`; content readback was repeated against the new container. The repair uses scoped DataHandler writes, preserving all page/form IDs and other workspace changes.

## Complete option matrix and testing tables

The second pass replaced the five Jev explanations in all four languages with concrete testing tables: option, exact example input, expected visible fields and recipient. A second table lists every ordinary input, select value and checkbox requirement. The quality-gate rows explicitly distinguish blocked frontend submission from the separately evaluated routing result. The unavailable-service row is a simulated automated fixture, not text that a visitor can enter to force a fallback. Low routing confidence is described separately because it does not necessarily hide conditional fields.

The current live runtime (`web-kj8tirxaijsk4cmzevgaphvr-20261012T001058`) was checked again for `MAIL/transport = mbox` before running the matrix. No matrix case submitted a Powermail mail record or sent email: the runner evaluated the persisted localized Jev decision, then POSTed the same synthetic fields to the real localized `condition.json` endpoint. Normal Jev logs/cache and service usage are expected side effects.

- **108/108 natural-language scenarios passed:** 27 exact examples in each of EN/DE/ZH/HU. They cover all five contact departments; all three support queues and four severity levels; four quality/detail levels and spam; all three project sizes, all three owners, confidentiality on/off and all four fit levels; all four job roles, four experience levels and remote on/off.
- **64/64 manual-select scenarios passed:** each of the four support products, four project timing options, four job start options and four education options, in all four languages. These use the appropriate successful base example and assert that its expected route and conditional fields remain correct.
- **140 score-range assertions passed** against the saved live responses, checking the meaningful rubric levels described by the tables. Scores remain probabilistic; these are sample-specific results.
- **5/5 deterministic service-failure routing fixtures passed:** default office, support, office, sales and jobs respectively. No service outage or live configuration change was induced.
- Reusable source: `packages/site_package/Build/Scripts/check-jev-demo-options.php`, reading `Resources/Private/Data/Content/jev/test-matrix.json`. It refuses a non-mbox runtime, obtains Basic Auth only from the existing environment, and exits nonzero on mismatched outcomes. The retained runner passed syntax validation, a live 16-case EN manual smoke run, and all five fallback fixtures.

Run in the existing configured TYPO3 runtime:

```sh
php packages/site_package/Build/Scripts/check-jev-demo-options.php > /tmp/jev-results.jsonl
```

Optional filters are `--language=en|de|zh|hu` and `--group=examples|manual|fallback`. The matrix stores the installed lab record IDs and exact language-specific URLs deliberately, so a future reseed requires an explicit fixture update.

The guarded content payload updated **20 existing text records**, with zero creations, deletions or skips. It preserved all page, form and content identities. Translation memories retain all five new English source texts mapped to DE/ZH/HU, and scoped additions are kept beside the payload. Cache flush followed the DataHandler apply.

Evidence under `var/jev-check/`: `2026-10-12-exhaustive-matrix-results.jsonl`, `2026-10-12-translated-matrix-results.jsonl`, `2026-10-12-manual-options-results.jsonl`, `2026-10-12-durable-runner-smoke.jsonl`, `2026-10-12-durable-fallback-results.jsonl`, `2026-10-12-testing-tables-dry-run.txt`, `2026-10-12-testing-tables-apply.txt`, and `2026-10-12-testing-tables-frontend.jsonl`.

Post-apply frontend verification passed **20/20 pages** (HTTP 200): each rendered the expected two tables with three column headers and every translated table cell. The content wrappers retain canonical source IDs for localized overlays. This is HTTP/DOM verification of all table content and semantics, not an additional visual browser pass.
