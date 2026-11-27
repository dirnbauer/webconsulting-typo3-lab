#!/bin/sh
# GitLab CI deploy of the images this pipeline built (.gitlab-ci.yml,
# deploy-production). Sets LAB_IMAGE_TAG in Coolify to the commit, which
# docker-compose.coolify.yml uses as the tag of the web and Solr images,
# starts the deployment, waits for it and for healthy containers, then checks
# the public URL. Needs curl and jq.
#
# COOLIFY_DEPLOY_TOKEN_B64 is base64 of the Coolify API token (protected and
# masked GitLab variable). Never add `set -x`: the token is in a header.
set -eu

base_url="${COOLIFY_URL:-https://coolify.webconsulting.at}"
app_uuid="${COOLIFY_APP_UUID:-kj8tirxaijsk4cmzevgaphvr}"
site_url="${SITE_URL:-https://typo3-lab.webconsulting.at/}"
tag="${CI_COMMIT_SHA:?CI_COMMIT_SHA is not set}"

token="$(printf '%s' "${COOLIFY_DEPLOY_TOKEN_B64:?COOLIFY_DEPLOY_TOKEN_B64 is not set}" | base64 -d)"
auth="Authorization: Bearer ${token}"

# Deployment logs can hold raw control characters that make the JSON invalid.
json() { tr -d '\000-\037' | jq -r "$1"; }

api() {
    method="$1"; path="$2"; shift 2
    curl -sS --fail-with-body --max-time 60 -X "${method}" -H "${auth}" \
        -H 'Accept: application/json' "$@" "${base_url}/api/v1${path}"
}

app="$(api GET "/applications/${app_uuid}")"
build_pack="$(printf '%s' "${app}" | json '.build_pack')"
repository="$(printf '%s' "${app}" | json '.git_repository')"
if [ "${build_pack}" != "dockercompose" ] || ! printf '%s' "${repository}" | grep -q 'extensions/webconsulting-typo3-lab'; then
    echo "Refusing to deploy: Coolify app ${app_uuid} is ${build_pack} from ${repository}, expected dockercompose from GitLab extensions/webconsulting-typo3-lab." >&2
    exit 1
fi

echo "Setting LAB_IMAGE_TAG=${tag}"
api PATCH "/applications/${app_uuid}/envs" -H 'Content-Type: application/json' \
    -d "{\"key\":\"LAB_IMAGE_TAG\",\"value\":\"${tag}\",\"is_preview\":false}" >/dev/null

deployment="$(api POST "/deploy?uuid=${app_uuid}&force=false" | json '.deployments[0].deployment_uuid // empty')"
if [ -z "${deployment}" ]; then
    echo "Coolify returned no deployment id." >&2
    exit 1
fi
echo "Deployment ${deployment} queued"

state=""
for _ in $(seq 1 90); do
    state="$(api GET "/deployments/${deployment}" | json '.status // empty' || true)"
    case "${state}" in
        finished) break ;;
        failed|cancelled*) echo "Deployment ${deployment} ended as ${state}; the previous containers keep serving." >&2; exit 1 ;;
    esac
    sleep 10
done
if [ "${state}" != "finished" ]; then
    echo "Deployment ${deployment} did not finish in 15 minutes (last state: ${state:-unknown})." >&2
    exit 1
fi
echo "Deployment finished; waiting for healthy containers"

# The web healthcheck has a 300 s start period (entrypoint migrations).
# Coolify refreshes the status periodically, so give it time to see the new
# containers before trusting "healthy".
sleep 30
status=""
for _ in $(seq 1 60); do
    status="$(api GET "/applications/${app_uuid}" | json '.status // empty' || true)"
    case "${status}" in
        running:healthy) break ;;
    esac
    sleep 10
done
if [ "${status}" != "running:healthy" ]; then
    echo "Application status is ${status:-unknown} 10 minutes after the deployment." >&2
    exit 1
fi

# 401 is the lab login, i.e. Apache and TYPO3 answer behind it.
code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 "${site_url}" || true)"
case "${code}" in
    200|401) echo "${site_url} answers ${code}. Live: ${tag}" ;;
    *) echo "${site_url} answers ${code:-nothing}." >&2; exit 1 ;;
esac
