#!/usr/bin/env bash
# Read-only quality baseline report over the own extension clones.
# Usage: Build/Scripts/extension-baseline-report.sh [clone-dir ...]
set -u
cd "$(dirname "$0")/../.."
DEFAULT=(
  packages/abilities packages/agent_nexus packages/llms_txt packages/skillflow packages/skillspector packages/site_package packages/visual_editor_enhancements
  ~/projects/desiderio ~/projects/innesto ~/projects/astryx-typo3 ~/projects/typo3-ai-chat ~/projects/typo3-records-list-types ~/projects/typo3-records-list-examples
  ~/projects/typo3-webcon-easy-workspace ~/projects/workos ~/projects/typo3-x402-paywall ~/projects/agentation ~/projects/typo3-camino-vercel
  ~/projects/typo3-docx ~/projects/typo3-image-workbench ~/projects/typo3-mcp-server ~/projects/pw_teaser ~/projects/powermail
)
DIRS=("${@:-${DEFAULT[@]}}")
printf '%-34s %-6s %-28s %-6s %-6s %-5s %-8s %-9s %s\n' repo phpstan ci-jobs README guides md-in-doc media chlog tag
for d in "${DIRS[@]}"; do
  d="${d/#\~/$HOME}"; [ -d "$d" ] || continue
  name=$(basename "$d")
  level=$(grep -hE '^\s*level:' "$d"/phpstan.neon "$d"/phpstan.neon.dist "$d"/Build/phpstan.neon "$d"/Build/phpstan/phpstan.neon 2>/dev/null | head -1 | tr -d ' ' | cut -d: -f2)
  jobs=$(ls "$d"/.github/workflows 2>/dev/null | tr '\n' ',' | sed 's/,$//')
  readme=$(wc -l < "$d/README.md" 2>/dev/null | tr -d ' ')
  guides=$([ -f "$d/Documentation/guides.xml" ] && echo yes || echo no)
  md=$(find "$d/Documentation" -name '*.md' 2>/dev/null | wc -l | tr -d ' ')
  media=$(git -C "$d" ls-files 2>/dev/null | grep -ciE '\.(map|mp4|webm|mov)$')
  chlog=$([ -f "$d/CHANGELOG.md" ] && echo yes || echo no)
  tag=$(git -C "$d" describe --tags --abbrev=0 2>/dev/null || echo -)
  printf '%-34s %-6s %-28s %-6s %-6s %-5s %-8s %-9s %s\n' "$name" "${level:--}" "${jobs:--}" "${readme:--}" "$guides" "$md" "$media" "$chlog" "$tag"
done
