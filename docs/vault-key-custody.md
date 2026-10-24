# Optional Vault master-key file custody — preparation only

`TYPO3_VAULT_MASTER_KEY_FILE` is empty by default. Deploying this code with the
default does not change the configured Vault provider, the TYPO3 signing key,
existing secrets, audit rows, or stored anchors. This preparation is unpublished
until the operator approves the cutover. It does not repair historical audit
hash mismatches or establish which historical key signed them.

The only supported nonempty value is `/run/typo3-vault-secrets/master-key`.
It selects nr-vault's native `file` provider and sets
`EXTENSIONS.nr_vault.masterKeySource` to that exact path. The file must already
exist, be a canonical absolute local path with no symlinks, lie outside `public/`,
have mode `0400`, and be readable by Apache's `www-data` identity. Its content
must be canonical base64 encoding of exactly 32 bytes; a final newline is allowed.
No key is generated, copied, printed, chmodded, or repaired by this feature.

The entrypoint validates as `www-data` before startup writes or migrations.
Opt-in also requires the existing `/run/typo3-secrets/encryption-key` signing-key
file; it refuses to generate a replacement if that custody is missing. The empty
flag retains the existing first-start initialization behavior.
`additional.coolify.php` repeats the validation at every production bootstrap;
this prevents nr-vault's native missing-file fallback to its auto-key location.
Missing, unreadable or invalid explicit files stop startup/bootstrap. There is
no fallback to the TYPO3 provider. An empty flag keeps existing behavior.

## Passive preparation deployment

Compose always attaches the dedicated named volume `typo3_vault_keys` at
`/run/typo3-vault-secrets:ro`. With the flag empty, that mount is inert and may
remain empty. The existing writable `typo3_secrets` signing-key volume is
unchanged. The application entrypoint never creates files in or chowns the new
read-only mount. Preparing or deploying this opt-out code is not permission to
enable the file provider or rotate the signing key.

## Two separate, operator-approved phases

1. **Preserve the current Vault master; change custody only.** Before any key
   operation, retain recoverable backups of the existing key custody and
   database, and record the read-only integrity baseline and existing historical
   mismatches. Privately export the **exact current 32-byte Vault master key**
   from its active provider. This is not the text of `SYS.encryptionKey`: the
   default TYPO3 provider derives the master from that string. Encode those same
   32 bytes in base64 and provision `master-key` once through a separate temporary
   **read-write administrative mount** of that same named volume, after passive
   preparation is deployed. Set ownership to the container runtime UID for
   `www-data`, mode `0400`, with a runtime-traversable containing directory.
   Remove the temporary administrative mount afterward; the application sees
   only `/run/typo3-vault-secrets/master-key` through its read-only volume.
   Do not bake it into an
   image, Git, an environment value, or an application/files backup. Keep it out
   of `/run/typo3-secrets`, which the existing entrypoint recursively chowns.
   **Only after user approval**, set `TYPO3_VAULT_MASTER_KEY_FILE` to the supported
   path. Restart through the normal reviewed deployment process
   and verify that the native file provider returns the same master bytes, the
   expected credentials remain usable, and audit/anchor verification matches
   the recorded baseline. Do not rotate the Vault master or re-sign audit rows.
   Keep the file and its containing mount immutable to the application: the
   bootstrap check cannot protect against an administrator replacing a mount
   between validation and the native provider's later read.
2. **Separate the new runtime signing key after custody is proven.** Merely selecting `file`
   while retaining the old TYPO3 signing key is not cryptographic independence:
   the master is still derivable from that pre-cutover string. After explicit user
   approval, rotate **only TYPO3's `SYS.encryptionKey`** in its existing custody,
   retain the unchanged external Vault master, and verify again. This invalidates
   existing TYPO3 signed links, cached form hashes and similar signatures.
   It also invalidates trust in persisted DOCX round-trip manifests from older
   exports, changes Solr site hashes/document IDs/API-key derivation (requiring
   a coordinated complete reindex), and changes x402 pseudonym/correlation
   values. Existing MCP OAuth handshake cookies must restart their short-lived
   flow. These effects need a deliberate window and explicit user choice; the
   rotation must not be bundled into enabling this flag.
   The previous signing string **still derives the unchanged Vault master**:
   rotation cannot revoke old backups or anybody who retained that string.
   This phase removes derivability from the **new active** signing key only.
   Revoking the previous derivation requires rotating the Vault master itself,
   a different operation outside this preparation and immutable-history scope.

**Stopping after phase 1 is a valid storage-only choice.** The current master is
held through a dedicated read-only file, and the signing key, old DOCX manifests,
Solr identifiers and x402 correlation stay unchanged. This does not revoke the
pre-cutover signing string's ability to derive the same master. Choosing phase 2
separates the new runtime signing key and accepts the DOCX/search/correlation and
signature effects above, but still does not revoke old holders or backups.
Rotating the Vault master to achieve that revocation would also require audit
HMAC migration/rewrites, which are outside the authorized immutable-history plan.

## Operator command templates — do not run without the approvals above

Run on the deployment host after the passive preparation image is serving.
Use an already privately exported and verified base64 file containing the exact
current Vault master; these commands do not derive or export a real key. Never
enable shell tracing or print the source file. Adjust only the operator source
path; the runtime destination is fixed.

```sh
set -eu
set +x
umask 077
vault_source=/root/secure-operator-input/current-vault-master.base64
lab_web=$(docker ps --filter label=at.webconsulting.stack=typo3-lab --filter label=com.docker.compose.service=web --format '{{.Names}}')
test -n "$lab_web"
test "$(printf '%s\n' "$lab_web" | wc -l | tr -d '[:space:]')" = 1
lab_image=$(docker inspect --format '{{.Image}}' "$lab_web")
vault_volume=$(docker inspect --format '{{range .Mounts}}{{if eq .Destination "/run/typo3-vault-secrets"}}{{.Name}}{{end}}{{end}}' "$lab_web")
test -n "$vault_volume"
test "$(docker inspect --format '{{range .Mounts}}{{if eq .Destination "/run/typo3-vault-secrets"}}{{.RW}}{{end}}{{end}}' "$lab_web")" = false

# Phase 1 provisioning only: refuses to overwrite a key already provisioned.
# No network, no application/database volumes, no full entrypoint.
docker run --rm --network none --entrypoint /bin/sh \
  --mount "type=volume,src=$vault_volume,dst=/run/typo3-vault-secrets" \
  --mount "type=bind,src=$vault_source,dst=/operator/current-master.base64,readonly" \
  -e TYPO3_VAULT_MASTER_KEY_FILE=/run/typo3-vault-secrets/master-key \
  "$lab_image" -eu -c '
    test ! -e /run/typo3-vault-secrets/master-key
    test ! -L /run/typo3-vault-secrets/master-key
    install -o www-data -g www-data -m 0400 /operator/current-master.base64 /run/typo3-vault-secrets/master-key
    chown root:www-data /run/typo3-vault-secrets
    chmod 0750 /run/typo3-vault-secrets
    su -s /bin/sh -c "exec php /var/www/html/docker/coolify/validate-vault-master-key.php" www-data
  '
```

After user approval, set the Coolify application variable
`TYPO3_VAULT_MASTER_KEY_FILE=/run/typo3-vault-secrets/master-key`, then use the
existing deployment flow (`Build/Scripts/sync-coolify.sh deploy`). Re-resolve
`lab_web` after deployment. Run the isolated validator and the existing private
key-aware verifier; confirm exact master-byte equality and unchanged native
audit/secret/anchor verification before the second approval.

```sh
lab_web=$(docker ps --filter label=at.webconsulting.stack=typo3-lab --filter label=com.docker.compose.service=web --format '{{.Names}}')
test -n "$lab_web"
test "$(printf '%s\n' "$lab_web" | wc -l | tr -d '[:space:]')" = 1
docker exec --user www-data "$lab_web" php /var/www/html/docker/coolify/validate-vault-master-key.php
```

Only after separate approval for the full DOCX/Solr/x402/signature impact, retain a
protected backup of the pre-cutover signing file and use this atomic signing-key
replacement. It neither changes the external Vault file nor invokes Vault
rotation/migration. It deliberately requires the explicit file-provider flag.

```sh
docker exec --user www-data "$lab_web" php -r '
    $file = getenv("TYPO3_VAULT_MASTER_KEY_FILE");
    if ($file !== "/run/typo3-vault-secrets/master-key") {
        throw new RuntimeException("Explicit Vault file custody must already be active.");
    }
    $configure = require "/var/www/html/config/system/vault-file-provider.php";
    $configure([], $file, "/var/www/html/public");
    $signingFile = "/run/typo3-secrets/encryption-key";
    if (!is_file($signingFile)) {
        throw new RuntimeException("Existing signing key custody is missing.");
    }
    umask(0077);
    $next = tempnam(dirname($signingFile), ".signing-key-");
    if ($next === false) {
        throw new RuntimeException("Cannot prepare the signing key replacement.");
    }
    if (file_put_contents($next, bin2hex(random_bytes(48))) !== 96
        || !chmod($next, 0600) || !rename($next, $signingFile)) {
        unlink($next);
        throw new RuntimeException("Signing key replacement failed.");
    }
  '
```

Redeploy/restart the reviewed web service through the normal deployment flow,
coordinate the complete Solr reindex before accepting search as healthy, then
repeat read-only master-byte, secret, native audit, archived historical audit
and anchor checks. Existing historical failures remain visible; do not re-sign
rows, reset anchors or delete records to make this check green.

## Rollback and evidence

Before phase 2, unset the flag and redeploy the previous/default configuration
while retaining the original TYPO3 signing key; the derived Vault master must
match the external file. After phase 2, keep the external Vault file configured
during application rollback. The rollback image must retain this feature or
explicitly carry native file-provider/source configuration; an older image that
does not know this flag would ignore it and select the wrong derived master.
Never unset the flag with a changed signing key:
that would select a different derived master and orphan existing ciphertext and
audit HMACs. A full custody rollback needs the backed-up original signing key
and an explicit, reviewed restoration of both settings; it can invalidate newly
issued TYPO3 signatures too. Retain the external file and recovery backups.

All preparation tests use fixed synthetic key bytes in disposable fixtures.
They exercise the native file provider, opt-out, a missing/disappearing file,
malformed encodings, unsafe modes/paths, web-root exposure and the isolated
entrypoint validator. They never execute the full entrypoint or read real keys.
