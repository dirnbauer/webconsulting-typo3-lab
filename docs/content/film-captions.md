# Captions on website films

The native video player owns the narration captions. Website MP4 files must not
also contain a burned-in copy. This keeps captions selectable and preserves the
reader's font, size and language preferences.

`Build/Film/film.html` and the feature-film factory default to no burned captions.
Standalone exports can opt in explicitly, but registered website films must not.
The JavaScript regression check covers all registered film specifications.

To replace only a picture without changing speech, timing, copy or translations:

```sh
node packages/site_package/Build/Film/render-films.mjs --film=solr --check-picture
node packages/site_package/Build/Film/render-films.mjs --film=solr --refresh-picture
```

The refresh refuses changed durations, transcript wording, cue timing or missing
assets. It copies the original AAC stream, retains every existing VTT file and
translated transcript, and writes new MP4/poster filenames with a content hash.
Already clean films are skipped. Keep a copy of the original fixture before a
batch refresh for stream and metadata comparison.

After deploying the files, update existing TYPO3 media references:

```sh
vendor/bin/typo3 sitepackage:films:seed --film=solr --pictures-only --dry-run
vendor/bin/typo3 sitepackage:films:seed --film=solr --pictures-only
```

Use the existing `--allow-production` switch for a production update after its
backup. `--pictures-only` updates the file ID in the source and translated file
references through DataHandler. It preserves their UIDs, translated metadata,
video settings, caption tracks, page copy and transcripts. Unpublished films
are skipped. A second dry run must report zero changes.

Verify full MP4 decoding, identical audio hashes and caption bytes, unchanged
frame counts/durations, and playback with captions enabled and disabled.
