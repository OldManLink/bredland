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