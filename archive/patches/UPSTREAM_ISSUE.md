# Workspace Query Overlay Upstream Notes

The workspace query overlay patches address backend query paths that filter on
editable record fields before TYPO3 workspace overlay can run.

Without the patches, a value that exists only in a workspace draft can be missed
by record-list search, live search, suggest wizard queries, or slug uniqueness
checks because the default workspace restriction excludes regular workspace
versions.

The current local solution adds a controlled query helper and overlay
normalization for TYPO3 14.3+:

```php
$queryBuilder->addWorkspaceRestriction($workspaceId, $includeAllVersionedRecords);
```

The behavior is guarded by User or Page TSconfig:

```typoscript
options.workspaces.includeAllVersionedRecordsInQueries = 0
```

The patch bundle is maintained in
`webconsulting/typo3-workspace-overlay-patch`. Keep these root patch files in
sync with the installed package version before updating `patches.lock.json` or
tagging a release.
