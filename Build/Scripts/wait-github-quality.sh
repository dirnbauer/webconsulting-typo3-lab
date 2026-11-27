#!/bin/sh
# Waits until GitHub's Quality workflow has passed for this commit
# (deploy-production in .gitlab-ci.yml). GitLab mirrors main and develop to the
# public GitHub repository, where .github/workflows/quality.yml runs the TYPO3
# and Node suites on every push to main. The deploy starts only when both jobs
# have succeeded for exactly this commit; a failed, cancelled or missing run
# stops it. Unauthenticated API calls are limited to 60 an hour, so it polls
# once a minute. Needs curl and jq.
set -eu

repo="${GITHUB_QUALITY_REPOSITORY:-dirnbauer/webconsulting-typo3-lab}"
sha="${CI_COMMIT_SHA:?CI_COMMIT_SHA is not set}"
timeout="${QUALITY_TIMEOUT_SECONDS:-2400}"
deadline=$(( $(date +%s) + timeout ))

echo "Waiting for GitHub Quality on ${repo}@${sha}"
while :; do
    runs="$(curl -sS --max-time 30 -H 'Accept: application/vnd.github+json' \
        "https://api.github.com/repos/${repo}/commits/${sha}/check-runs?per_page=100" || true)"
    # Newest run per job name; a re-run replaces an earlier failure.
    state="$(printf '%s' "${runs}" | jq -r '
        [ (.check_runs // [])[] | select(.name == "TYPO3 14 quality" or .name == "Node quality") ]
        | group_by(.name) | map(max_by(.started_at)) as $latest
        | if ($latest | length) < 2 then "waiting"
          elif any($latest[]; .status != "completed") then "waiting"
          elif all($latest[]; .conclusion == "success") then "success"
          else "failed: " + ([ $latest[] | .name + "=" + (.conclusion // "none") ] | join(", "))
          end' 2>/dev/null || echo waiting)"
    case "${state}" in
        success) echo "GitHub Quality passed for ${sha}."; exit 0 ;;
        failed*) echo "GitHub Quality ${state}; not deploying. https://github.com/${repo}/commit/${sha}/checks" >&2; exit 1 ;;
    esac
    if [ "$(date +%s)" -ge "${deadline}" ]; then
        echo "GitHub Quality did not finish within ${timeout} s for ${sha}; not deploying. Check the push mirror and GitHub Actions." >&2
        exit 1
    fi
    sleep 60
done
