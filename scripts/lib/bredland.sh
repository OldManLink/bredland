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

ensure_bredland_trusted_user() {
    local bredland_host="$1"

    execute_remote_command \
        "$bredland_host" \
        "getent group bredland-trusted >/dev/null ||
             sudo groupadd --system bredland-trusted

         id -u bredland-trusted >/dev/null 2>&1 ||
             sudo useradd \
                 --system \
                 --gid bredland-trusted \
                 --no-create-home \
                 --shell /usr/sbin/nologin \
                 bredland-trusted"
}
