#!/usr/bin/env bash

set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"

echo "Deploying smoke-test telemetry endpoint..."
SMOKE_TEST_DEPLOY=1 ./scripts/deploy-oderland-telemetry.sh

echo
echo "Running smoke test..."

SMOKE_TEST_DEPLOY=1 load_bredland_secrets

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

echo "Smoke test passed."