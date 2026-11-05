# Parent decisions after the interrupted Astra review

The review task ended with provider status `failed`: its final content was flagged for possible cybersecurity risk.
Before that termination it wrote `2026-11-05-visual-editor-astra-workspace-review.md` and isolated diagnostic artifacts.
Those existing findings are retained as provisional engineering evidence. Do not call the delegate successfully
completed, restart it with disguised wording, or count its diagnostic passing probes as acceptance tests.

The existing Sol integration task continues with defensive fixes to the reported defects in our own code.
This is implementation feedback for that task, not a replacement review or a new exploration task.

## Required before integration acceptance

- Address reported findings 1–4: validate supported nested command data before mutation; enforce actual ancestor
  record/relation permissions; preserve ordinary content-element operations where tt_content can also be an inline
  child; and compile child overrides correctly or refuse the unsupported configuration with a backend fallback.
- Add focused regression tests that assert rejection with unchanged records and preservation of legitimate operations.
  Existing diagnostics assert observed defects and cannot be copied unchanged as acceptance tests.
- Remove the scoped RelationHandler factory override if the reported stock-Core regression results are confirmed by
  the package's maintained tests. Keep compiled draft identities. Use supported Core APIs; no global XCLASS, vendor
  patches, SQL restoration or manual version-row construction.
- Keep the original first-version live-byte preservation contract for this candidate. Initial workspace creation
  remains an explicitly documented backend fallback. The review establishes metadata normalization without a change
  in visible order in its fixtures; it does not establish harmlessness for all consumers, timestamps or relation graphs.
  Do not expand initial versioning support by silently relaxing the contract. Existing-draft workspace operations still
  need their real runtime tests, including MariaDB.
- Correct obsolete/deleted/foreign-workspace translation handling so irrelevant rows do not permanently block operations.
  Keep genuinely unverified cascades refused. Explain these limits accurately: the translated showcase's existing entries
  may require the backend form, while newly created untranslated leaves demonstrate direct create/delete.
- A successful local build is not a release gate by itself. Complete real browser flows with the regular installed
  package candidates and permitted/restricted users. Parent will assess corrected implementation evidence before release.

No publication, tag or production change is authorized by this note beyond the existing coordinated release plan.
