# Screenshot refresh — 12 October 2026

Published the completed Desiderio backend-preview changes as 4.24.0, deployed the lab and verified the new homepage/footer before capture. The separate Jev form localization fix was also deployed before the final form captures.

Refreshed 38 additional screenshots where the visible interface or page context had changed. Together with the five Easy Workspace images refreshed earlier, Desiderio 4.24.1 contains 43 current images. Unchanged screenshots were retained after comparison.

| Area | Additional images |
| --- | ---: |
| Desiderio frontend, page module and content wizard | 6 |
| Astryx | 2 |
| Blog | 3 |
| Visual Editor, including the image embedded in demo elements | 6 |
| DOCX Editor | 3 |
| Solr | 4 |
| Skillflow | 2 |
| Agent Nexus | 4 |
| Powermail | 2 |
| API documentation | 1 |
| llms.txt / agents.md | 3 |
| Agentation | 2 |

Images were captured from the rendered UI and saved as content-hashed WebP files. The homepage image was published into the blog before its list capture; the Visual Editor and Agentation images embedded in other demos were refreshed before the dependent captures. Agentation was captured in the recovered Development instance because its Production context gate intentionally disables the toolbar. Its feedback notes were left as unsent drafts.

Existing file references were updated through TYPO3 FAL and DataHandler within the Desiderio and v14 blog page trees. Record IDs, relationships, ordering and translations were preserved. Updated descriptions and the Agent Nexus screenshot caption have language maps. Hungarian pages retain their configured English fallback where no translated content record exists. Original files remain available for older references.

The source manifest is `EXT:desiderio/Build/Data/feature-screenshots.refresh-20261012.manifest.json`. Capture recipes and the scoped reference updater are included in the extension. Blog seed/payload references and the lab's translation dictionaries were updated too.

Verification includes image signatures and hashes, source-reference validation, PHP/JavaScript/JSON checks, the extension unit suite (865 tests, one environment-dependent skip), and live image/gallery checks in English, German, Chinese and Hungarian on desktop and mobile. The production refresh dry run reports zero remaining changes. Temporary capture accounts were disabled and deleted through DataHandler, and their sessions invalidated.

Detailed capture manifests, read-only/apply reports and browser evidence are retained locally in `var/screenshot-refresh-20261012/`. A production database backup and change reports are retained under `/var/backups/typo3-lab/screenshot-refresh-20261012/`.
