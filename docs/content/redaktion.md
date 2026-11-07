# Redaktion software page

The source page `/software/redaktion` is owned by the lab's site package. It uses normal Desiderio elements, collections and FAL references. English source, German, Chinese and Hungarian translations are stored in the existing memories.

```sh
vendor/bin/typo3 sitepackage:seed-redaktion --dry-run
vendor/bin/typo3 sitepackage:seed-redaktion
vendor/bin/typo3 sitepackage:films:seed --film=redaktion
vendor/bin/typo3 sitepackage:content:translate --site=desiderio --pages=<redaktion>,<software> --languages=de,zh,hu --fail-on-missing
```

Resolve both page IDs within the Desiderio root on the target installation. They differ between local and production databases. `seed-redaktion` resolves its parent itself and orders the owned content through DataHandler; repeated execution reuses the records. Collections are linked after real child UIDs exist.

The film uses real screenshots of the packaged macOS application and synthetic demo articles. Its seven-line narration, timing, selectable English WebVTT and MP4 use the existing film pipeline. Burned-in subtitles are disabled. The screenshot hashes change when a capture changes; generated fileadmin copies are ignored.

The app and connector source live in the **private** GitLab repository `webcon/desktop-news-typo3`. No private source is copied into this public repository. The connector is a development dependency for the original local DDEV target. Production's `--no-dev` installation does not expose this beta editing API. The production product page and film do not require it.

The current beta requires TYPO3 14.3, abilities 1.3 and an active administrator account. Record-level editor scopes are not implemented, so ordinary editor accounts are refused. Existing JWT/auth and sanitizer corrections remain enabled. This is a development evaluation, not a claim of a production deployment of the editing service.

The native app is Apple Silicon, unsigned and not notarized. The page says this and offers a demonstration; it does not promise a public binary or a public source repository. The locally built installer is delivered separately.

Local verification uses the private app's unit/browser/native tests, plus real API requests against the DDEV installation. The API test creates only a hidden fixture folder, hidden articles and a synthetic non-admin account, then removes them through DataHandler. It confirms actual CRUD, token renewal, refusal of a forged admin claim, and explicit review approval for deletion. It never publishes an existing workspace.
