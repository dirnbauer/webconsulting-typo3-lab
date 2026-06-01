# TYPO3 Site Set Memory Exhaustion Report

Generated: 2026-06-01 Europe/Vienna

## Summary

TYPO3 failed during site resolution with this fatal error:

```text
Fatal error: Allowed memory size of 1073741824 bytes exhausted
(tried to allocate 262144 bytes) in
/var/www/html/vendor/typo3/cms-core/Classes/Site/Set/SetRegistry.php
on line 80
```

The root cause was a recursive Site Set dependency path in the Desiderio
package:

```text
webconsulting/desiderio
  optionalDependencies -> webconsulting/desiderio-powermail
webconsulting/desiderio-powermail
  dependencies -> webconsulting/desiderio
```

This produced an optional dependency cycle. TYPO3's `SetRegistry` resolves
both hard dependencies and optional dependencies recursively when it determines
which sets are active for a site. Because this cycle had no visited-set guard in
the recursive lookup path, requests that loaded affected sites repeatedly
traversed the same set pair until PHP exhausted its memory limit.

## Impact

Affected requests returned a frontend or backend fatal error before TYPO3 could
finish resolving the site configuration. The fatal appeared in `SetRegistry` on
line 80 because the recursive dependency check repeatedly called
`getSet()`, not because line 80 allocated the full amount of memory by itself.

Observed repros:

- `curl https://webconsulting-typo3-lab.ddev.site/` returned a TYPO3 fatal
  body before the fix.
- `vendor/bin/typo3 site:list` failed under `memory_limit=1024M`.
- Running the CLI with no memory limit became CPU-bound and reached multiple
  GB of resident memory, showing that raising `memory_limit` only masked the
  recursion.

## Why TYPO3 Hit This Path

Site configuration in `config/sites/*/config.yaml` declares Site Set names in
the `dependencies` list. During site loading, TYPO3 creates `Site` objects and
merges set-provided settings, route enhancers, TypoScript, and TSconfig.

The relevant core flow is:

1. `SiteConfiguration::resolveAllExistingSites()` reads each site config.
2. `SiteSettingsFactory::createSettings()` composes settings for the site's
   configured sets.
3. `SetRegistry::getSets(...$siteSetNames)` returns the requested sets plus
   their transitive dependencies.
4. `SetRegistry::hasDependency()` recursively checks hard dependencies and
   optional dependencies.

The important behavior is that optional dependencies are still traversed when
the optional target set exists. In this installation,
`webconsulting/desiderio-powermail` existed, so the optional dependency from
the base Desiderio set was active.

## The Recursive Set Pair

Before the fix, the base Desiderio set had this shape:

```yaml
name: webconsulting/desiderio
dependencies: []
optionalDependencies:
  - webconsulting/desiderio-shadcnui-templates
  - webconsulting/desiderio-powermail
  - webconsulting/desiderio-solr
  - webconsulting/desiderio-news
  - webconsulting/desiderio-blog
```

The Powermail-specific set had this shape:

```yaml
name: webconsulting/desiderio-powermail
dependencies:
  - webconsulting/desiderio
optionalDependencies:
  - in2code/powermail-main
```

That creates a cycle:

```text
desiderio base -> optional powermail -> required desiderio base
```

This is invalid for the way TYPO3's set resolver currently walks dependency
graphs. Optional dependencies are best used for leaf integrations that do not
depend back on the set declaring them.

## Secondary Configuration Issue

Four site configs also referenced Site Set names that were not defined by the
installed packages:

```text
webconsulting/site-package-desiderio-dashboard
webconsulting/site-package-desiderio-editorial
webconsulting/site-package-desiderio-portfolio
webconsulting/site-package-desiderio-saas
```

Those missing sets did not cause the memory exhaustion. They did cause the
corresponding sites to resolve as invalid once the memory problem was removed.

The fix was to update those sites to depend directly on existing sets instead
of creating additional wrapper sets:

```yaml
dependencies:
  - webconsulting/site-package-search
  - webconsulting/desiderio-preset-dashboard
```

The preset name differs by site:

- `webconsulting/desiderio-preset-dashboard`
- `webconsulting/desiderio-preset-editorial`
- `webconsulting/desiderio-preset-portfolio`
- `webconsulting/desiderio-preset-saas`

The shared Shadcn UI template dependency now belongs to each
`webconsulting/desiderio-preset-*` set, so site configs do not need to repeat
`webconsulting/desiderio-shadcnui-templates`.

## Fix

The recursive set edge was removed from:

```text
vendor/webconsulting/desiderio/Configuration/Sets/Desiderio/config.yaml
```

The base Desiderio set no longer optionally includes
`webconsulting/desiderio-powermail`. Sites that need Powermail should declare
`webconsulting/desiderio-powermail` explicitly. That direction is valid because
the Powermail set can depend on the base set without the base set depending
back on Powermail.

The four invalid site configs were also changed to use existing set names
directly:

```text
config/sites/desiderio-dashboard/config.yaml
config/sites/desiderio-editorial/config.yaml
config/sites/desiderio-portfolio/config.yaml
config/sites/desiderio-saas/config.yaml
```

A follow-up simplification removed the local corporate wrapper set from
`packages/site_package` as well. Corporate demo sites now use direct
dependencies on `webconsulting/site-package-search` and
`webconsulting/desiderio-preset-corporate`; the corporate preset supplies the
Shadcn UI templates transitively.

## Verification

After flushing TYPO3 caches:

```bash
ddev exec vendor/bin/typo3 cache:flush
```

The following checks passed:

```bash
ddev exec 'php -d memory_limit=1024M vendor/bin/typo3 site:list'
ddev exec 'php -d memory_limit=1024M vendor/bin/typo3 site:show desiderio-dashboard'
```

Frontend smoke tests returned HTTP 200:

```text
/                                      200
/desiderio-websites/corporate/         200
/desiderio-websites/dashboard/         200
/desiderio-websites/editorial/         200
/desiderio-websites/portfolio/         200
/desiderio-websites/saas/              200
```

Static dependency checks also showed:

```text
set cycles: 0
missing site deps: 0
```

## Prevention

- Treat Site Set dependencies as a directed acyclic graph.
- Do not add optional dependencies from a base set to integration sets that
  depend back on the base set.
- Prefer explicit site-level dependencies for optional integrations such as
  Powermail, News, Blog, Solr, or form-specific rendering.
- Add a lightweight CI check that parses all `Configuration/Sets/*/config.yaml`
  files and fails on dependency cycles across both `dependencies` and
  `optionalDependencies`.
- Add a second check that verifies every `config/sites/*/config.yaml`
  dependency exists in installed Site Sets or Content Blocks virtual sets.
