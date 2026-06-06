# Lab Export Archives

This directory holds large bootstrap artifacts that are not committed to git.

| File | Created by | Imported with |
|---|---|---|
| `fileadmin.tar.gz` | `tar -czf .tarballs/fileadmin.tar.gz -C public fileadmin` | `ddev import-files --source=.tarballs/fileadmin.tar.gz` |

The database dump lives at the project root: `dump.sql.gz`
(`ddev export-db --file=dump.sql.gz` / `ddev import-db --file=dump.sql.gz`).

See [docs/ddev-bootstrap.md](../docs/ddev-bootstrap.md) for the full export/import
workflow.
