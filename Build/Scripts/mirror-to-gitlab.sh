#!/usr/bin/env bash
# Mirror every own / maintained repo to gitlab.webconsulting.at (push-to-create works).
# Existing `gitlab` remotes are kept; missing ones are created under the extensions/ group.
#
# GitHub (`origin`) is the source of truth: the script mirrors origin's branches and tags, not
# whatever a local clone happens to hold. Pushing local refs (`push --all --tags`) used to bring
# back branches and tags that had been deleted on purpose — easy-workspace's withdrawn v14.0.x
# tags came back that way from an old clone.
set -u
LOG="${MIRROR_LOG:-/tmp/mirror-to-gitlab.log}"
: > "$LOG"
LAB=~/projects/webconsulting-typo3-lab
REPOS=(
  # Repos that are not installed in the lab
  ~/projects/typo3-ai-chat ~/projects/typo3-camino-vercel ~/projects/typo3-skills ~/projects/pw_teaser
  # The lab itself
  "$LAB"
  # Every extension the lab installs: the clone under packages/ is the canonical one
  # (Build/Scripts/lab-link.sh links them into vendor/).
  "$LAB"/packages/abilities
  "$LAB"/packages/agent_nexus
  "$LAB"/packages/agentation
  "$LAB"/packages/ai_assistant
  "$LAB"/packages/astryx_typo3
  "$LAB"/packages/desiderio
  "$LAB"/packages/docx_editor
  "$LAB"/packages/friendlycaptcha
  "$LAB"/packages/image_workbench
  "$LAB"/packages/innesto
  "$LAB"/packages/llms_txt
  "$LAB"/packages/mcp_server
  "$LAB"/packages/powermail
  "$LAB"/packages/powermail_cond
  "$LAB"/packages/records_list_examples
  "$LAB"/packages/records_list_types
  "$LAB"/packages/skillflow
  "$LAB"/packages/skillspector
  "$LAB"/packages/solr_numbered_pagination
  "$LAB"/packages/visual_editor_enhancements
  "$LAB"/packages/webcon_easy_workspace
  "$LAB"/packages/webcon_jev
  "$LAB"/packages/workos_auth
  "$LAB"/packages/x402_paywall
)

# Private and archived repositories are mirrored from bare clones instead:
#   for r in frontend_editing frontend_editing_autodiscover jobs typo3-capability-manifest \
#            typo3-deepfake-detection typo3-rust-datahandler typo3-tiptap webconsulting-skills; do
#     git clone --mirror git@github.com:dirnbauer/$r.git /tmp/$r.git
#     git -C /tmp/$r.git push --mirror git@gitlab.webconsulting.at:extensions/$r.git
#   done
for d in "${REPOS[@]}"; do
  d="${d/#\~/$HOME}"
  [ -d "$d/.git" ] || { echo "SKIP $d (no git)" | tee -a "$LOG"; continue; }
  # repo name = GitHub repo name from origin
  origin=$(git -C "$d" remote get-url origin 2>/dev/null)
  name=$(basename "${origin%.git}")
  if ! git -C "$d" remote get-url gitlab >/dev/null 2>&1; then
    git -C "$d" remote add gitlab "git@gitlab.webconsulting.at:extensions/${name}.git"
  fi
  url=$(git -C "$d" remote get-url gitlab)
  echo "== $name -> $url" | tee -a "$LOG"
  if ! git -C "$d" fetch origin --prune --tags --force >>"$LOG" 2>&1; then
    echo "  FETCH FAILED $name" | tee -a "$LOG"; continue
  fi
  # GitLab rejects pushes whose LFS objects it does not have yet (powermail).
  if [ -n "$(git -C "$d" lfs ls-files 2>/dev/null | head -1)" ]; then
    git -C "$d" lfs push --all gitlab >>"$LOG" 2>&1 || echo "  LFS PUSH FAILED $name" | tee -a "$LOG"
  fi
  # Plain word lists, not arrays: macOS ships bash 3.2, where mapfile does not exist and an
  # empty array trips `set -u`. Ref names never contain spaces.
  refspecs=$(git -C "$d" for-each-ref --format='%(refname:lstrip=3)' refs/remotes/origin \
    | grep -vx HEAD | sed 's#.*#+refs/remotes/origin/&:refs/heads/&#')
  if [ -n "$refspecs" ]; then
    # shellcheck disable=SC2086
    git -C "$d" push gitlab $refspecs >>"$LOG" 2>&1 || echo "  BRANCH PUSH FAILED $name" | tee -a "$LOG"
  fi
  # Only push the tags GitHub also has.
  tags=$(git -C "$d" ls-remote --tags --refs origin | awk '{print $2}')
  if [ -n "$tags" ]; then
    # shellcheck disable=SC2086
    git -C "$d" push gitlab $tags >>"$LOG" 2>&1 || echo "  TAG PUSH FAILED $name (old TER-style tags are rejected by a pre-receive hook, which is harmless)" | tee -a "$LOG"
  fi
done
echo DONE | tee -a "$LOG"
