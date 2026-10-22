# Runtime security packages — 2026-10-22

These are webconsulting builds for amd64, compiled against the stated Debian
release. They are not official Debian packages. Their versions carry a
`+webcon.20261022.4` suffix. No official Coolify image or bundled tool is modified.

`SHA256SUMS` pins the payloads used by the runtime Dockerfile. Package receipts,
source references and patches are installed under
`/usr/share/doc/webcon-security-backports/PACKAGE/`.

The libxml2, GLib, SQLite and OpenSSH changes are upstream/vendor security
backports. libaom is Debian's maintained 3.12.1-1+deb13u1 source, rebuilt for
bookworm, including the 2026 Debian security fixes. Its public API and every
runtime ELF consumer were checked, and the upstream resize regressions pass.

Replace these local packages with reviewed vendor fixes when they become
available; do not silently remove this install step during a base-image update.
Source patches and rebuild notes live in the T3 Ops repository under
`ops/security/backport-sources/`. These payloads contain no runtime credentials.
