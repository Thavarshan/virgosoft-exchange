#!/usr/bin/env bash
#
# Deploy one specific commit to Laravel Cloud and wait for the result.
#
# Required environment:
#   LARAVEL_CLOUD_TOKEN            organization API token
#   LARAVEL_CLOUD_ENVIRONMENT_ID   environment identifier
#   DEPLOY_SHA                     commit to deploy
# Optional:
#   LARAVEL_CLOUD_DEPLOY_HOOK      deploy hook URL (Settings > Deployments).
#                                  Pins the exact commit, so it is required to
#                                  redeploy an older commit. Without it the
#                                  Initiate-deployment API deploys the branch
#                                  head, so DEPLOY_SHA must still be that head.
#   DEPLOY_BRANCH                  branch the environment tracks (default main)
#   DEPLOY_REPOSITORY              owner/name, for the branch-head check
#   DEPLOY_TIMEOUT_SECONDS         default 1200
#   DEPLOY_POLL_SECONDS            default 10

set -euo pipefail

api='https://cloud.laravel.com/api'
timeout="${DEPLOY_TIMEOUT_SECONDS:-1200}"
interval="${DEPLOY_POLL_SECONDS:-10}"
branch="${DEPLOY_BRANCH:-main}"
hook="${LARAVEL_CLOUD_DEPLOY_HOOK:-}"

: "${LARAVEL_CLOUD_TOKEN:?is required}"
: "${LARAVEL_CLOUD_ENVIRONMENT_ID:?is required}"
: "${DEPLOY_SHA:?is required}"

# Keep secrets out of the log even when a step echoes a URL.
echo "::add-mask::${LARAVEL_CLOUD_TOKEN}"
[ -z "$hook" ] || echo "::add-mask::${hook}"

cloud_get() {
    curl --silent --show-error --fail-with-body         --header "Authorization: Bearer ${LARAVEL_CLOUD_TOKEN}"         --header 'Accept: application/json'         "${api}$1"
}

cloud_post() {
    curl --silent --show-error --fail-with-body --request POST         --header "Authorization: Bearer ${LARAVEL_CLOUD_TOKEN}"         --header 'Accept: application/json'         "${api}$1"
}

echo "Deploying ${DEPLOY_SHA} to Laravel Cloud"

started_after="$(date -u +%s)"
deployment_id=''

if [ -n "$hook" ]; then
    curl --silent --show-error --fail-with-body --request POST         "${hook}?commit_hash=${DEPLOY_SHA}" > /dev/null

    # The deploy hook's response shape is not documented, so find the
    # deployment by the commit it was started for. An older deployment of the
    # same commit must not be mistaken for this one, so only pending or
    # recently started deployments qualify.
    deadline=$((started_after + 180))

    while [ -z "$deployment_id" ]; do
        if listing="$(cloud_get "/environments/${LARAVEL_CLOUD_ENVIRONMENT_ID}/deployments")"; then
            deployment_id="$(
                jq -r --arg sha "$DEPLOY_SHA" --argjson after "$started_after" '
                    [.data[]?
                        | select(.attributes.commit_hash == $sha)
                        | select(
                            (.attributes.started_at // "") as $s
                            | $s == ""
                            or (try (($s | sub("\\.[0-9]+"; "") | fromdateiso8601) >= ($after - 60)) catch true)
                        )]
                    | sort_by(.attributes.started_at // "9999")
                    | last
                    | .id // empty' <<< "$listing"
            )"
        fi

        [ -n "$deployment_id" ] && break

        if [ "$(date -u +%s)" -ge "$deadline" ]; then
            echo "::error::Laravel Cloud did not start a deployment for ${DEPLOY_SHA} within 3 minutes."
            echo 'Check that Deploy hook is enabled and the commit is on the environment branch.'
            exit 1
        fi

        sleep "$interval"
    done
else
    # The API deploys whatever the branch head is. Only proceed when that is
    # the commit CI validated; otherwise a newer push owns the next deploy.
    if [ -n "${DEPLOY_REPOSITORY:-}" ]; then
        head_sha="$(git ls-remote "https://github.com/${DEPLOY_REPOSITORY}.git" "refs/heads/${branch}" | cut -f1)"

        if [ "$head_sha" != "$DEPLOY_SHA" ]; then
            echo "::notice::${branch} has moved to ${head_sha:-an unknown commit}; skipping ${DEPLOY_SHA}. The newer commit's own run deploys it."
            exit 0
        fi
    fi

    response="$(cloud_post "/environments/${LARAVEL_CLOUD_ENVIRONMENT_ID}/deployments")"
    deployment_id="$(jq -r '.data.id // empty' <<< "$response")"
    started_commit="$(jq -r '.data.attributes.commit_hash // empty' <<< "$response")"

    if [ -z "$deployment_id" ]; then
        echo '::error::Laravel Cloud did not return a deployment.'
        exit 1
    fi

    if [ -n "$started_commit" ] && [ "$started_commit" != "$DEPLOY_SHA" ]; then
        echo "::error::Laravel Cloud is deploying ${started_commit}, not the tested ${DEPLOY_SHA}."
        exit 1
    fi
fi

echo "Deployment ${deployment_id} started"

deadline=$(($(date -u +%s) + timeout))
last_status=''
api_failures=0

while true; do
    # One bad API response must not fail a deployment that is still running.
    if ! body="$(cloud_get "/deployments/${deployment_id}")"; then
        api_failures=$((api_failures + 1))

        if [ "$api_failures" -ge 6 ]; then
            echo "::error::Laravel Cloud API failed ${api_failures} times in a row."
            exit 1
        fi

        sleep "$interval"
        continue
    fi

    api_failures=0
    status="$(jq -r '.data.attributes.status' <<< "$body")"

    if [ "$status" != "$last_status" ]; then
        echo "Status: ${status}"
        last_status="$status"
    fi

    case "$status" in
        deployment.succeeded)
            echo "Deployed ${DEPLOY_SHA}"
            exit 0
            ;;
        build.failed | deployment.failed | failed | cancelled)
            echo "::error::Deployment ${deployment_id} ended as ${status}."
            jq -r '.data.attributes.failure_reason // "No failure reason reported."' <<< "$body"
            exit 1
            ;;
    esac

    if [ "$(date -u +%s)" -ge "$deadline" ]; then
        echo "::error::Deployment ${deployment_id} did not finish within ${timeout}s (last status: ${status})."
        exit 1
    fi

    sleep "$interval"
done
