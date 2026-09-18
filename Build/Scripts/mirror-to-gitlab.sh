#!/usr/bin/env bash
# Mirror every own / maintained repo to gitlab.webconsulting.at (push-to-create works).
# Existing `gitlab` remotes are kept; missing ones are created under the extensions/ group.
set -u
LOG="${MIRROR_LOG:-/tmp/mirror-to-gitlab.log}"
: > "$LOG"
REPOS=(
  ~/projects/astryx-typo3 ~/projects/desiderio ~/projects/innesto ~/projects/skillflow
  ~/projects/agentation ~/projects/typo3-ai-chat ~/projects/typo3-camino-vercel ~/projects/typo3-docx
  ~/projects/typo3-image-workbench ~/projects/typo3-mcp-server ~/projects/typo3-records-list-examples
  ~/projects/typo3-records-list-types ~/projects/typo3-skills ~/projects/typo3-webcon-easy-workspace
  ~/projects/typo3-x402-paywall ~/projects/workos ~/projects/powermail ~/projects/pw_teaser
  ~/projects/webconsulting-typo3-lab
  ~/projects/webconsulting-typo3-lab/packages/abilities ~/projects/webconsulting-typo3-lab/packages/agent_nexus
  ~/projects/webconsulting-typo3-lab/packages/llms_txt ~/projects/webconsulting-typo3-lab/packages/visual_editor_enhancements
)
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
  git -C "$d" push gitlab --all --force-with-lease >>"$LOG" 2>&1 || git -C "$d" push gitlab --all >>"$LOG" 2>&1 || echo "  BRANCH PUSH FAILED $name" | tee -a "$LOG"
  git -C "$d" push gitlab --tags >>"$LOG" 2>&1 || echo "  TAG PUSH FAILED $name" | tee -a "$LOG"
done
echo DONE | tee -a "$LOG"
