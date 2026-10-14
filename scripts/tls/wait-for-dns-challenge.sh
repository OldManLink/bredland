#!/usr/bin/env bash
set -euo pipefail

record_name="${1:?Missing record name}"
validation="${2:?Missing validation}"
max_attempts="${DNS_WAIT_MAX_ATTEMPTS:-60}"
wait_timeout_seconds="${DNS_WAIT_TIMEOUT_SECONDS:-60}"

print_retry_time()
{
    local now="${1:?Missing current time}"
    local ttl="${2:?Missing TTL}"

    local retry_at
    local retry_time
    local current_day
    local retry_day
    local tomorrow_day

    retry_at=$((now + ttl))
    retry_time="$(date -r "$retry_at" "+%H:%M")"

    current_day="$(date -r "$now" "+%Y-%m-%d")"
    retry_day="$(date -r "$retry_at" "+%Y-%m-%d")"
    tomorrow_day="$(date -r "$((now + 86400))" "+%Y-%m-%d")"

    if [[ "$retry_day" == "$current_day" ]]; then
        echo "Please try again after $retry_time today" >&2
    elif [[ "$retry_day" == "$tomorrow_day" ]]; then
        echo "Please try again after $retry_time tomorrow" >&2
    else
        echo "Please try again after $retry_time" >&2
    fi
}

authoritative_servers=()

if ! authoritative_output="$(
    dig +short NS arcanel.se
)"; then
    echo "Authoritative DNS discovery failed for arcanel.se" >&2
    exit 1
fi

while IFS= read -r server; do
    if [[ -n "$server" ]]; then
        authoritative_servers+=("$server")
    fi
done <<< "$authoritative_output"

if [[ "${#authoritative_servers[@]}" -eq 0 ]]; then
    echo "No authoritative DNS servers discovered for arcanel.se" >&2
    exit 1
fi

pending_servers=("${authoritative_servers[@]}")

attempt=0
while [[ "${#pending_servers[@]}" -gt 0 ]]; do
    still_pending=()

    for server in "${pending_servers[@]}"; do
        if ! result="$(
            dig "@$server" +short TXT "$record_name"
        )"; then
            echo "Authoritative DNS lookup failed: $server" >&2
            exit 1
        fi

        if [[ "$result" == "\"$validation\"" ]]; then
            echo "$server ✓ expected TXT visible"
        else
            echo "$server … waiting for TXT"
            still_pending+=("$server")
        fi
    done

    pending_servers=("${still_pending[@]}")

    if [[ "${#pending_servers[@]}" -gt 0 ]]; then
        attempt=$((attempt + 1))

        if [[ "$attempt" -ge "$max_attempts" ]]; then
            echo "Timed out waiting for authoritative DNS propagation" >&2
            exit 1
        fi

        sleep 1
    fi
done

public_started_at="$(date +%s)"
attempt=0

while true; do
    if ! result="$(
        dig TXT "$record_name"
    )"; then
        echo "Public DNS lookup failed for $record_name" >&2
        exit 1
    fi

    if [[ "$result" == *"status: NXDOMAIN"* ]]; then
        soa_ttl="$(
            printf '%s\n' "$result" \
                | awk '$4 == "SOA" { print $2; exit }'
        )"

        soa_minimum="$(
            printf '%s\n' "$result" \
                | awk '$4 == "SOA" { print $11; exit }'
        )"

        if [[ "$soa_ttl" -lt "$soa_minimum" ]]; then
            negative_ttl="$soa_ttl"
        else
            negative_ttl="$soa_minimum"
        fi

        echo "Public DNS returned NXDOMAIN" >&2
        echo "Negative cache TTL: ${negative_ttl}s" >&2

        now="$(date +%s)"
        elapsed=$((now - public_started_at))
        remaining_wait=$((wait_timeout_seconds - elapsed))

        if [[ "$negative_ttl" -gt "$remaining_wait" ]]; then
            echo "Negative cache TTL exceeds remaining wait time" >&2
            echo "Remaining wait time: ${remaining_wait}s" >&2
            print_retry_time "$now" "$negative_ttl"
            exit 1
        fi

        sleep "$negative_ttl"
        continue
    fi

    public_ttl="$(
        printf '%s\n' "$result" \
            | awk '$4 == "TXT" { print $2; exit }'
    )"

    public_txt="$(
        printf '%s\n' "$result" \
            | awk '
                $4 == "TXT" {
                    $1 = ""
                    $2 = ""
                    $3 = ""
                    $4 = ""
                    sub(/^[[:space:]]+/, "")
                    print
                    exit
                }
            '
    )"

    if [[ "$public_txt" == "\"$validation\"" ]]; then
        exit 0
    fi

    if [[ -n "$public_txt" ]]; then
        echo "Public DNS returned stale TXT: $public_txt" >&2
        echo "Remaining TTL: ${public_ttl}s" >&2

        now="$(date +%s)"
        elapsed=$((now - public_started_at))
        remaining_wait=$((wait_timeout_seconds - elapsed))

        if [[ "$public_ttl" -gt "$remaining_wait" ]]; then
            echo "Public DNS cache TTL exceeds remaining wait time" >&2
            echo "Remaining wait time: ${remaining_wait}s" >&2
            print_retry_time "$now" "$public_ttl"
            exit 1
        fi
    fi

    attempt=$((attempt + 1))

    if [[ "$attempt" -ge "$max_attempts" ]]; then
        exit 1
    fi

    sleep "$public_ttl"
done
