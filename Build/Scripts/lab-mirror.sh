#!/usr/bin/env bash
# Mirror lab-repo edits between the lab worktree (WT) and the DDEV project checkout (MAIN).
#
# Usage:
#   Build/Scripts/lab-mirror.sh          WT -> MAIN (forward): the paths in FORWARD_PATHS
#   Build/Scripts/lab-mirror.sh --back   MAIN -> WT: generated film artefacts in BACK_PATHS
#
# Why this exists:
#   - Lab commits are made in the worktree WT, never in MAIN.
#   - The DDEV container mounts MAIN, so a page, seed or film run only sees what is in MAIN.
#   - Several sessions share MAIN and leave uncommitted edits there, so the copy is scoped to a
#     few paths, never deletes (no --delete) and never overwrites a foreign edit.
#
# Forward mode copies tracked and untracked-but-not-ignored files of WT (per WT's .gitignore),
# minus node_modules/, .DS_Store and docs/plans/ (plans stay in the worktree). Before copying it
# looks at every entry of `git -C MAIN status` in those paths:
#   - MAIN's file equals WT's file, or equals what this script copied last time: fine.
#   - WT has no such file, or WT's file equals MAIN's HEAD version (WT did not change it): the
#     MAIN edit is left alone and the file is not copied.
#   - anything else is a foreign edit that the copy would overwrite: the script lists the files
#     and exits 2 without copying anything.
# Hashes of copied files are recorded in WT's git dir (lab-mirror.sha1), never committed.
# After copying it runs `ddev mutagen sync` (a failure only warns) and prints the changed files.
#
# Back mode first runs `ddev mutagen sync` (container writes reach the host), then copies the
# non-ignored files of BACK_PATHS from MAIN into WT (no --delete) and prints the changed files.
# It overwrites WT copies of those artefacts; edit film fixtures in MAIN or re-render instead.
#
# Exit codes: 0 done, 1 usage or environment error, 2 refused because of foreign edits in MAIN.
# Compatible with macOS bash 3.2 and /usr/bin/rsync (openrsync).
set -euo pipefail

WT=/Users/dirnbauer/.t3/worktrees/webconsulting-typo3-lab/t3-0ef0c98b
MAIN=/Users/dirnbauer/projects/webconsulting-typo3-lab

FORWARD_PATHS=(
    packages/site_package/
    config/sites/desiderio/
    Build/Scripts/lib/downloads-page.json
    docs/
    CHANGELOG.md
    Tests/E2E/
)
BACK_PATHS=(
    packages/site_package/Resources/Private/Fixtures/Films/
    packages/site_package/Build/Film/data/
    packages/site_package/Build/Film/audio/
)

die() { echo "lab-mirror: $*" >&2; exit 1; }

# True for paths that are never mirrored in either direction.
is_excluded() {
    case "$1" in
        node_modules/* | */node_modules/* | .DS_Store | */.DS_Store | docs/plans/*) return 0 ;;
    esac
    return 1
}

# list_files <repo> <paths...>: existing, non-ignored, non-excluded files, one per line.
list_files() {
    local repo=$1 path
    shift
    git -C "$repo" ls-files -z --cached --others --exclude-standard -- "$@" |
        while IFS= read -r -d '' path; do
            is_excluded "$path" && continue
            [ -f "$repo/$path" ] || [ -L "$repo/$path" ] || continue
            printf '%s\n' "$path"
        done | sort -u
}

sha_of() { shasum -a 1 "$1" | cut -d' ' -f1; }

# Runs rsync for a file list and prints the transferred files ("+ path" new, "~ path" changed).
copy_list() {
    local from=$1 to=$2 list=$3 line
    [ -s "$list" ] || return 0
    rsync -rlpc --itemize-changes --files-from="$list" "$from/" "$to/" |
        while IFS= read -r line; do
            case "$line" in
                '>f+++'*) printf '+ %s\n' "${line#* }" ;;
                '>f'*) printf '~ %s\n' "${line#* }" ;;
            esac
        done
}

mutagen_sync() {
    if ! (cd "$MAIN" && ddev mutagen sync >/dev/null 2>&1); then
        echo "lab-mirror: warning: 'ddev mutagen sync' failed in $MAIN (is the project running?)" >&2
    fi
}

for repo in "$WT" "$MAIN"; do
    [ -d "$repo" ] || die "missing checkout: $repo"
    git -C "$repo" rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "not a git checkout: $repo"
done
[ "$(cd "$WT" && pwd -P)" != "$(cd "$MAIN" && pwd -P)" ] || die "WT and MAIN are the same directory"

MODE=forward
case "${1:-}" in
    '') ;;
    --back) MODE=back ;;
    -h | --help) sed -n '2,30p' "$0"; exit 0 ;;
    *) die "unknown argument: $1 (usage: lab-mirror.sh [--back])" ;;
esac

TMP=$(mktemp -d "${TMPDIR:-/tmp}/lab-mirror.XXXXXX")
trap 'rm -rf "$TMP"' EXIT

if [ "$MODE" = back ]; then
    mutagen_sync
    list_files "$MAIN" "${BACK_PATHS[@]}" > "$TMP/files"
    copy_list "$MAIN" "$WT" "$TMP/files" > "$TMP/changed"
    echo "lab-mirror --back: $(wc -l < "$TMP/changed" | tr -d ' ') file(s) changed in $WT"
    cat "$TMP/changed"
    exit 0
fi

MANIFEST="$(git -C "$WT" rev-parse --absolute-git-dir)/lab-mirror.sha1"
touch "$MANIFEST"

recorded_sha() { awk -F'\t' -v p="$1" '$2 == p { s = $1 } END { print s }' "$MANIFEST"; }

: > "$TMP/skip"
: > "$TMP/conflicts"
: > "$TMP/kept"
git -C "$MAIN" status --porcelain=v1 -z --untracked-files=all -- "${FORWARD_PATHS[@]}" > "$TMP/status"
while IFS= read -r -d '' entry; do
    state=${entry:0:2}
    path=${entry:3}
    # Renames and copies carry the source path as an extra NUL-separated field.
    case "$state" in R* | C*) IFS= read -r -d '' _ || true ;; esac
    is_excluded "$path" && continue
    wt_file="$WT/$path"
    main_file="$MAIN/$path"
    if [ ! -f "$wt_file" ] && [ ! -L "$wt_file" ]; then
        printf '%s %s\n' "$state" "$path" >> "$TMP/kept"
        continue
    fi
    if [ -f "$main_file" ]; then
        cmp -s "$main_file" "$wt_file" && continue
        [ "$(sha_of "$main_file")" = "$(recorded_sha "$path")" ] && continue
    fi
    if git -C "$MAIN" cat-file -e "HEAD:$path" 2>/dev/null &&
        git -C "$MAIN" show "HEAD:$path" | cmp -s - "$wt_file"; then
        printf '%s\n' "$path" >> "$TMP/skip"
        printf '%s %s\n' "$state" "$path" >> "$TMP/kept"
        continue
    fi
    printf '%s %s\n' "$state" "$path" >> "$TMP/conflicts"
done < "$TMP/status"

if [ -s "$TMP/conflicts" ]; then
    {
        echo "lab-mirror: refused. MAIN has foreign edits that differ from WT in these files:"
        sed 's/^/  /' "$TMP/conflicts"
        echo "Nothing was copied. Settle them first (generated film artefacts: run --back)."
    } >&2
    exit 2
fi

list_files "$WT" "${FORWARD_PATHS[@]}" > "$TMP/all"
if [ -s "$TMP/skip" ]; then
    grep -vxF -f "$TMP/skip" "$TMP/all" > "$TMP/files" || true
else
    cp "$TMP/all" "$TMP/files"
fi
copy_list "$WT" "$MAIN" "$TMP/files" > "$TMP/changed"

if [ -s "$TMP/changed" ]; then
    while IFS= read -r line; do
        path=${line#? }
        printf '%s\t%s\n' "$(sha_of "$MAIN/$path")" "$path"
    done < "$TMP/changed" >> "$MANIFEST"
    awk -F'\t' '{ last[$2] = $0 } END { for (p in last) print last[p] }' "$MANIFEST" | sort -t"$(printf '\t')" -k2 > "$TMP/manifest"
    cp "$TMP/manifest" "$MANIFEST"
    mutagen_sync
fi

if [ -s "$TMP/kept" ]; then
    echo "lab-mirror: left alone (MAIN edits that WT does not change):"
    sed 's/^/  /' "$TMP/kept"
fi
echo "lab-mirror: $(wc -l < "$TMP/changed" | tr -d ' ') file(s) changed in $MAIN"
cat "$TMP/changed"
