#!/usr/bin/env bash
#
# Installs the lab's opt-in git hooks (Build/Hooks/) into this clone. Nothing
# installs them silently: run this once per clone if you want them.
#
#   Build/Scripts/install-hooks.sh            install (a symlink, so updates of
#                                             Build/Hooks/ apply at once)
#   Build/Scripts/install-hooks.sh --remove   remove what this script installed
#   Build/Scripts/install-hooks.sh --status   show what is installed
#
# Hooks:
#   pre-push  stops a push that changes composer.lock, patches/ or the lab's
#             Powermail files while `ddev check-powermail` has not passed for
#             that content (git config lab.powermailCheck block|remind|off).
#
# The hooks live in the clone's common git directory, so they apply to every
# worktree of this clone. An existing hook that is not ours is never replaced.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
hooks_dir="$(git -C "${ROOT}" rev-parse --path-format=absolute --git-common-dir)/hooks"
if custom="$(git -C "${ROOT}" config --get core.hooksPath)"; then
  echo "core.hooksPath is set to ${custom}; install the hooks there by hand (ln -s ${ROOT}/Build/Hooks/pre-push ${custom}/pre-push)." >&2
  exit 1
fi
action="${1:-install}"

is_ours() {
  [[ -L "$1" ]] && [[ "$(readlink "$1")" == */Build/Hooks/"$(basename "$1")" ]]
}

hooks=(pre-push)
for hook in "${hooks[@]}"; do
  target="${hooks_dir}/${hook}"
  source_file="${ROOT}/Build/Hooks/${hook}"
  case "${action}" in
    install)
      if [[ -e "${target}" || -L "${target}" ]] && ! is_ours "${target}"; then
        echo "${target} exists and is not the lab's hook; leaving it alone." >&2
        exit 1
      fi
      mkdir -p "${hooks_dir}"
      ln -sfn "${source_file}" "${target}"
      echo "Installed ${hook}: ${target} -> ${source_file}"
      ;;
    --remove|remove)
      if is_ours "${target}"; then
        rm -f "${target}"
        echo "Removed ${hook}."
      else
        echo "${hook}: not installed by this script, nothing removed."
      fi
      ;;
    --status|status)
      if is_ours "${target}"; then
        echo "${hook}: installed (-> $(readlink "${target}")), mode $(git -C "${ROOT}" config --get lab.powermailCheck || echo block)"
      else
        echo "${hook}: not installed"
      fi
      ;;
    *)
      echo "Usage: Build/Scripts/install-hooks.sh [--remove|--status]" >&2
      exit 2
      ;;
  esac
done
