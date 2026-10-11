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
