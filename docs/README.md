# Project Documentation

This directory contains project documentation that is not specific to one
local TYPO3 extension package.

## Available Documents

| Document | Purpose |
|---|---|
| [news-api-studio-spec.md](news-api-studio-spec.md) | Product and implementation specification for the News API Studio app. |
| [reports/typo3-v14-upgrade-20260516-213338.md](reports/typo3-v14-upgrade-20260516-213338.md) | Report for the TYPO3 14-only site package upgrade and verification pass. |

## Related Documentation

| Path | Purpose |
|---|---|
| [../README.md](../README.md) | Main project setup, DDEV notes, site package overview, and verification commands. |
| [../packages/site_package/README.md](../packages/site_package/README.md) | Local TYPO3 site package/provider extension documentation. |
| [../packages/site_package/Documentation/Index.rst](../packages/site_package/Documentation/Index.rst) | TYPO3 RST documentation for the TYPO3 14-only site package. |
| [../apps/news-api-studio/README.md](../apps/news-api-studio/README.md) | News API Studio setup, build commands, and app usage. |
| [../apps/news-api-studio/ARCHITECTURE.md](../apps/news-api-studio/ARCHITECTURE.md) | News API Studio technical architecture. |
| [../patches/README.md](../patches/README.md) | Composer patch workflow and current patch list. |

## Root README Coverage

The root README is the canonical high-level index for:

- The precise lab feature inventory.
- Configured DDEV services and local URLs.
- TYPO3 Site Set and demo site mappings.
- API, capability, MCP, workspace, and News API Studio features.
- Extension, Site Set dependency, and acknowledgement inventory.

## Documentation Maintenance

- Keep the root README focused on repo setup and operational commands.
- Keep package-specific behavior in the package README.
- Keep TYPO3 extension manual content in `packages/site_package/Documentation/`.
- Keep app-specific details in `apps/news-api-studio/`.
- Update this index when adding new Markdown documents under `docs/`.
- Keep generated or time-stamped reports under `docs/reports/` and link them
  from the table above instead of duplicating their content in the root README.
