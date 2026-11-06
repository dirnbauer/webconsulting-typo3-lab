# Visual Editor 1.5.0 release record — 2026-11-05

Parent follow-up to `2026-11-05-visual-editor-sol-integration-handoff.md` and the binding
`2026-11-05-visual-editor-review-decisions.md`. The provisional Astra findings 1–4 were
addressed by the Sol integration commit with regression tests, the relation factory override
was removed, strict live-byte preservation and the first-version backend fallback were kept,
and the parent re-verified the evidence before release: unit 75/140, JS 52, functional
50/260 (MariaDB, only the two known vendor deprecations), limits audit 184 groups / 0
violations, and the showcase page 200 in all four lab languages.

## Releases

- visual_editor_enhancements `v1.5.0` = `1895016` (release commit on top of `dce1e28`),
  pushed to GitHub and GitLab, main + tag.
- desiderio `v4.31.0` = `52d6eb26` (on top of `4091ffdc`), pushed to GitHub and GitLab,
  main + tag.
- Lab `8dbd4b8` on GitHub and GitLab main: lock bump to v4.31.0 / v1.5.0 only
  (2 lock operations, nothing else moved).

## Local runtime

The two dev links were taken off `.lab-links`; the release tags were git-archived into
`vendor/webconsulting/` per the single-package-install recipe (mutagen sync, opcache clear,
dump-autoload, cache:flush). The container's installed metadata still shows the older dev-link
versions (a pre-existing state; a full container install would try the gitlab-SSH
webcon-templates source the container cannot fetch), but the v1.5.0/v4.31.0 classes load and
the site is healthy; CI and Coolify install cleanly from the lock.

## Deploy

Quality run 42124990984 green (Node + TYPO3 14 quality, deploy configured); Coolify rebuilt
and the web container is healthy on image `8dbd4b8…`; live site answers 401 (healthy).

## Production data run

Runner `var/prod-ve-1.5.0/` (run.sh, verify.php, health.sh, router.php), run on the VPS:

- Backup `20261105T230018Z-pre-ve-1.5.0/database.sql.gz` (734 MB, 132 s), run.log archived
  next to it; the runner directory on the VPS was removed afterwards.
- `desiderio:styleguide:seed --pages=content-types/visual-editor-inline --allow-production`:
  page created as uid **8158** (1 new, 23 elements, 10 s). The first attempt of the runner
  aborted after the backup on a bash-only `<<<` in `db_one` (host sh is dash); fixed to a
  POSIX pipe and resumed with `BACKUP=0 FROM=1`.
- Targeted translate `--site=desiderio --languages=de,zh,hu --pages=8158`: 1 new page + 23
  records per language, 137/134/135 rows and 255/254/255 fields, **0 missing** (168 s);
  second dry run with `--fail-on-missing` clean (8 s); cache flushed.
- `verify.php` VERIFY-OK: page once per language with the designed titles, nav_hide/no_search
  set, below `/content-types`, 23 visible elements per language, no duplicate or free-mode
  translations, the five audited collection CTypes present.
- `health.sh` HEALTH-OK: the page renders 200 in en/de/zh/hu with translated markers, home
  and the backend login fine. Total duration **243 s** (target: under 30 min).

## Retained limits (unchanged, by decision)

First workspace auto-versioning and unproven cascades keep explicit backend fallbacks;
translated existing source entries mostly demonstrate refusal messaging. The 19 pre-existing
Innesto `keywords` lint errors remain, unrelated to this release.
