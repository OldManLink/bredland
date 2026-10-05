#!/usr/bin/env bash
set -euo pipefail

record_name="${1:?Missing record name}"
validation="${2:?Missing validation}"
max_attempts="${DNS_WAIT_MAX_ATTEMPTS:-60}"
sleep_seconds="${DNS_WAIT_SLEEP_SECONDS:-1}"

authoritative_servers=()

while IFS= read -r server; do
    authoritative_servers+=("$server")
done < <(
    dig +short NS arcanel.se
)

attempt=0

while true; do
    authoritative_visible=true

    for server in "${authoritative_servers[@]}"; do
        result="$(
            dig "@$server" +short TXT "$record_name"
        )"

        if [[ "$result" != "\"$validation\"" ]]; then
            authoritative_visible=false
            break
        fi
    done

    if $authoritative_visible; then
        break
    fi

    attempt=$((attempt + 1))

    if [[ "$attempt" -ge "$max_attempts" ]]; then
        echo "Timed out waiting for authoritative DNS propagation" >&2
        exit 1
    fi

    sleep "$sleep_seconds"
done

attempt=0

while true; do
    result="$(
        dig +short TXT "$record_name"
    )"

    if [[ "$result" == "\"$validation\"" ]]; then
        exit 0
    fi

    attempt=$((attempt + 1))

    if [[ "$attempt" -ge "$max_attempts" ]]; then
        echo "Timed out waiting for public DNS propagation" >&2
        exit 1
    fi

    sleep "$sleep_seconds"
done
