# shellcheck shell=bash
#
# Shared by Build/Scripts/check-powermail.sh and the opt-in pre-push hook
# (Build/Hooks/pre-push): which files decide whether the Powermail check must
# run again, and the fingerprint of their content.
#
# A pass of `ddev check-powermail` is recorded with the fingerprint of these
# files in the working tree. The hook computes the same fingerprint for the
# commit being pushed; equal fingerprints mean the check passed for exactly
# that content, so the push goes through without running it again.

# Paths (relative to the repository root) whose change can break Powermail:
# the lock and the patches, Composer's view of them, the lab's Powermail
# templates, labels and scripts, and the E2E journeys.
POWERMAIL_CHECK_PATHS_REGEX='^(composer\.json|composer\.lock|patches\.lock\.json|patches/[^/]*\.patch|packages/site_package/Resources/Private/Extensions/Powermail/.*|packages/site_package/Resources/Private/Language/Overrides/[^/]*powermail[^/]*|packages/site_package/Resources/Public/JavaScript/desiderio-powermail-[^/]*|Tests/E2E/powermail\.spec\.js)$'

# Where a pass is recorded: in the git directory of this checkout (for a
# worktree its own .git/worktrees/<name>/), never in the working tree. The
# hook reads it in the checkout that pushes, so run the check in the checkout
# whose DDEV instance serves that code.
powermail_check_stamp_file() {
  local git_dir
  git_dir="$(git -C "$1" rev-parse --absolute-git-dir 2>/dev/null)" || return 1
  printf '%s/lab-check-powermail.pass\n' "${git_dir}"
}

# Fingerprint of the relevant files of a commit: <repository root> <commit>.
powermail_check_fingerprint_commit() {
  git -C "$1" ls-tree -r --full-tree "$2" \
    | POWERMAIL_CHECK_REGEX="${POWERMAIL_CHECK_PATHS_REGEX}" \
      awk -F '\t' '$2 ~ ENVIRON["POWERMAIL_CHECK_REGEX"] { split($1, meta, " "); print meta[3] " " $2 }' \
    | LC_ALL=C sort \
    | git -C "$1" hash-object --stdin
}

# Fingerprint of the same files as they are in the working tree.
powermail_check_fingerprint_worktree() {
  local root="$1" file
  git -C "${root}" ls-files -z \
    | while IFS= read -r -d '' file; do
        if [[ "${file}" =~ ${POWERMAIL_CHECK_PATHS_REGEX} ]] && [[ -f "${root}/${file}" ]]; then
          printf '%s %s\n' "$(git -C "${root}" hash-object -- "${file}")" "${file}"
        fi
      done \
    | LC_ALL=C sort \
    | git -C "${root}" hash-object --stdin
}
