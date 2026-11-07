# TYPO3 Lab statistics

All configured demo sites include `webconsulting/lab-statistics`. The tag runs
only on `https://typo3-lab.webconsulting.at`, using site **11** of
`https://statistics.webconsulting.at`. Webconsulting's main website uses site 10;
the two reports stay separate. The existing Basic Auth protection is retained.

TYPO3 omits the tag for authenticated frontend and backend users. The JavaScript
also excludes frames, preview parameters, searches, authentication routes and
extension/form parameters. Do Not Track and Global Privacy Control stop loading
Matomo entirely. No consent banner or new browser-storage entry is created.

Before recording a page view, the tag disables cookies and browser feature
detection. It sends only the page's origin/path, uses that path as the title and
reduces the referrer to its origin. Query strings and fragments are discarded.
No link/form tracking or user ID is configured. The tracker script uses
`referrerpolicy="no-referrer"`; page views use POST.

The Matomo site additionally enforces cookieless tracking, masks two IP bytes,
uses the masked address for enrichment, respects DNT, strips referrer paths,
randomizes the browser configuration identifier, and deletes raw logs after
90 days. Aggregate reports are retained. Counts are privacy-reduced page-view
statistics; they are not reliable person or cross-session counts.

These are technical privacy settings. The site's privacy information and legal
basis must describe the actual analytics use; disabling cookies alone is not a
blanket legal exemption.

Validate the tag with `node --test Tests/JavaScript/LabStatistics.test.mjs`.
