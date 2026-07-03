#!/usr/bin/env bash

load_bredland_secrets() {
    BREDLAND_SECRETS_FILE="${BREDLAND_SECRETS_FILE:-/etc/bredland/secrets.env}"

    if [[ ! -r "$BREDLAND_SECRETS_FILE" ]]; then
        echo "Error: secrets file not found: $BREDLAND_SECRETS_FILE" >&2
        exit 1
    fi

    set -a
    # shellcheck disable=SC1090
    source "$BREDLAND_SECRETS_FILE"
    set +a
}

enable_smoke_deploy() {
    TELEMETRY_ENDPOINT_FILE="${TELEMETRY_ENDPOINT_FILE%.php}-smoke.php"
    TELEMETRY_CONFIG_FILE="${TELEMETRY_CONFIG_FILE%.php}-smoke.php"
    TELEMETRY_ENDPOINT="${TELEMETRY_ENDPOINT%.php}-smoke.php"

    SMOKE_TEST_HOST="${SMOKE_TEST_HOST:?Missing SMOKE_TEST_HOST}"
    SMOKE_TEST_TOKEN="${SMOKE_TEST_TOKEN:?Missing SMOKE_TEST_TOKEN}"
    # shellcheck disable=SC2089
    SMOKE_TEST_HOST_TOKEN_LINE="    '${SMOKE_TEST_HOST}' => '${SMOKE_TEST_TOKEN}',"

    export TELEMETRY_ENDPOINT_FILE
    export TELEMETRY_CONFIG_FILE
    export TELEMETRY_ENDPOINT
    # shellcheck disable=SC2090
    export SMOKE_TEST_HOST_TOKEN_LINE
}
