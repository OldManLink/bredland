#!/usr/bin/env bash

set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"
# shellcheck source=scripts/lib/utils.sh
source "$(dirname "$0")/lib/utils.sh"

load_bredland_secrets

export SMOKE_TEST_DEPLOY=1
export SMOKE_TEST_HOST="${SMOKE_TEST_HOST:-smoke-test}"

if [[ -z "${SMOKE_TEST_TOKEN:-}" ]]; then
    SMOKE_TEST_TOKEN="$(scripts/create-token.sh smoke v1)"
    export SMOKE_TEST_TOKEN
fi

enable_smoke_deploy

echo "Deploying smoke-test telemetry endpoint..."
./scripts/deploy-oderland-telemetry.sh

echo
echo "Running smoke test..."
oderland_user="${ODERLAND_SSH_USER:?Missing ODERLAND_SSH_USER}"
oderland_host="${ODERLAND_SSH_HOST:?Missing ODERLAND_SSH_HOST}"
data_dir="${NOC_DATA_DIR:?Missing NOC_DATA_DIR}"

smoke_date="$(date -u +%F)"
smoke_file="${data_dir%/}/${SMOKE_TEST_HOST}-${smoke_date}.jsonl"
smoke_local="$(mktemp)"

# Start clean so verification is deterministic.
execute_remote_command "rm -f '$smoke_file'"
smoke_host="${SMOKE_TEST_HOST:?Missing SMOKE_TEST_HOST}"
smoke_token="${SMOKE_TEST_TOKEN:?Missing SMOKE_TEST_TOKEN}"
endpoint="${TELEMETRY_ENDPOINT:?Missing TELEMETRY_ENDPOINT}"

response="$(
    curl --fail --silent --show-error \
        -X POST "$endpoint" \
        --data-urlencode "host=$smoke_host" \
        --data-urlencode "token=$smoke_token" \
        --data-urlencode "uptime=0d00:00:42" \
        --data-urlencode "fields=temperature,throttled,smoked" \
        --data-urlencode "temperature=42.0" \
        --data-urlencode "throttled=0x0" \
        --data-urlencode "smoked=salmon"
)"

if [[ "$response" != "ok" ]]; then
    echo "Smoke test failed: expected 'ok', got:" >&2
    echo "$response" >&2
    exit 1
fi

echo "Verifying smoke-test JSONL..."

scp "${oderland_user}@${oderland_host}:${smoke_file}" "$smoke_local"

expected_substrings=(
    '"host":"smoke-test"'
    '"uptime":"0d00:00:42"'
    '"temperature":"42.0"'
    '"throttled":"0x0"'
    '"smoked":"salmon"'
)

for expected in "${expected_substrings[@]}"; do
    if ! grep -q "$expected" "$smoke_local"; then
        echo "Smoke JSONL verification failed: missing $expected" >&2
        cat "$smoke_local" >&2
        exit 1
    fi
done

echo "Cleaning up smoke-test artefacts..."

execute_remote_command "rm -f '$smoke_file' '$TELEMETRY_ENDPOINT_FILE' '$TELEMETRY_CONFIG_FILE'"

rm -f "$smoke_local"

echo "Smoke-test artefacts cleaned up."
echo "Smoke test passed."