#!/usr/bin/env bash
set -euo pipefail

run_dir="${1:?Missing run directory}"

served_certificate=""
connected=false

for attempt in 1 2 3 4 5; do
    if served_certificate="$(
        openssl s_client \
            -connect nocster-73.arcanel.se:8081 \
            -servername nocster-73.arcanel.se \
            </dev/null \
            2>/dev/null
    )"; then
        connected=true
        break
    fi

    if [[ "$attempt" -lt 5 ]]; then
        sleep 1
    fi
done

if ! $connected; then
    echo "Failed to connect to trusted discovery TLS endpoint" >&2
    exit 1
fi

served_fingerprint="$(
    printf '%s\n' "$served_certificate" |
        openssl x509 \
            -noout \
            -fingerprint \
            -sha256
)"

issued_fingerprint="$(
    openssl x509 \
        -in "$run_dir/fullchain.pem" \
        -noout \
        -fingerprint \
        -sha256
)"

if [[ "$served_fingerprint" != "$issued_fingerprint" ]]; then
    echo "Served certificate does not match issued certificate" >&2
    exit 1
fi
