# Standard Powermail forms — 12 October 2026

Target: <https://typo3-lab.webconsulting.at/desiderio-powermail-lab/>

All six standard forms completed real browser submissions using synthetic details (`Powermail Lab QA`, `powermail-qa@example.invalid`). Each reached its configured thank-you page, saved its answers, and produced a receiver notification and sender acknowledgement in the server's local mail sink. External mail delivery was not tested; the effective transport is `mbox`.

| Form | Tested input and validation | Successful result |
|---|---|---|
| 01 Contact | Empty required fields rejected; invalid email rejected; team size 4 rejected and 5 accepted; Support topic | Mail 29, thank-you page 792 |
| 02 Newsletter | Missing interests and consent rejected; all three interests and consent accepted | Mail 30, thank-you page 794 |
| 03 Callback | Missing contact details/reason rejected; Evening preference saved | Mail 31, thank-you page 798 |
| 04 Appointment | Missing contact details rejected; future date, Flexible and Video call; Previous/Next retained values | Mail 32, thank-you page 802 |
| 05 Support | Required customer details, description and consent; attachment uploaded after storage repair | Mail 34, thank-you page 806 |
| 06 Project | Four steps; required contact/radio/checkbox checks; Relaunch, TYPO3, all five features, €25,000–€50,000 and Flexible; optional deadline/newsletter left empty | Mail 33, thank-you page 1024 |

All six standard forms use the configured `office@webconsulting.at` route. The topic, priority and other selections are saved answers; these forms do not use Jev routing. The newsletter has no double-opt-in workflow configured and does not subscribe visitors to an external mailing service. Appointment and callback forms record requests without making bookings or calls.

## Repairs

- The support attachment path did not exist and PHP could not create it beneath the image-owned public directory. `docker/coolify/provision-powermail-uploads.sh` provisions a dedicated writable directory on the existing persistent runtime volume and retains the established `uploads/tx_powermail/` URL through a symlink. Startup calls the same helper. The helper preserves stored files, is idempotent, and refuses to replace unexpected existing paths. A 154-byte synthetic text attachment was saved successfully.
- A failed server validation returned the wizard without an explanation when the client error-summary setting was off. The Desiderio template patch renders server errors independently of that client preference. The captcha protection itself remains enabled.
- Chinese and Hungarian Powermail email/minimum validation labels fell back to English. The existing site-package language overrides now supply both labels.

## Frontend instructions and localization

Every standard form receives a short explanation and a table of options, example inputs and expected outcomes in English, German, Chinese and Hungarian. The project wizard also lists all its available choices. All 24 thank-you variants explain that the submission is a demo, removing the former two-working-day response promise. Updates use scoped TYPO3 DataHandler operations and preserve the existing forms and page IDs.

All 59 source fields, 11 fieldsets and six forms already had overlays in all four languages. The initial 24-page HTTP audit confirmed translated labels, options, buttons and required messages; it exposed the two validation-label gaps described above. Live rendered content and translated validation are checked again after deployment.

## Evidence and scope

Browser evidence: `var/powermail-check/browser-results.json`. Backend and local-mail verification: `backend-audit.json` and `synthetic-mbox-headers.json`. The 24 initial localized form-page results are in `http-rendered-before.jsonl`.

Six English browser submissions were completed. Every translated page is checked for rendering and content, but every permutation of ordinary dropdown choices is not submitted separately: these choices are stored values with the same receiver. Jev branching and routing have a separate exhaustive input/output matrix and report in `docs/jev-forms-check-2026-10-12.md`.
