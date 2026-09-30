#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/../lib/bredland.sh"
# shellcheck source=scripts/lib/utils.sh
source "$(dirname "$0")/../lib/utils.sh"

load_bredland_secrets

oderland_user="${ODERLAND_SSH_USER:?Missing ODERLAND_SSH_USER}"
oderland_host="${ODERLAND_SSH_HOST:?Missing ODERLAND_SSH_HOST}"
remote="${oderland_user}@${oderland_host}"

record_name="$1"
validation="$2"

zone="arcanel.se"

zone_json="$(
    execute_remote_command \
        "$remote" \
        "uapi --output=json DNS parse_zone zone=$zone"
)"

if ! printf '%s\n' "$zone_json" | jq -e . >/dev/null 2>&1; then
    echo "Invalid DNS zone response" >&2
    exit 1
fi

parse_status="$(
    printf '%s\n' "$zone_json" \
        | jq -r '.result.status'
)"

if [[ "$parse_status" != "1" ]]; then
    error="$(
        printf '%s\n' "$zone_json" \
            | jq -r '.result.errors[]?'
    )"

    if [[ -n "$error" ]]; then
        printf '%s\n' "$error" >&2
    else
        echo "DNS zone read failed" >&2
    fi

    exit 1
fi

serial="$(
    printf '%s\n' "$zone_json" \
        | jq -r '
            .result.data[]
            | select(.type == "record" and .record_type == "SOA")
            | .data_b64[2]
            | @base64d
        '
)"

serial_count="$(
    printf '%s\n' "$serial" \
        | awk 'NF { count++ } END { print count + 0 }'
)"

if [[ "$serial_count" -ne 1 ]]; then
    echo "Expected exactly one SOA serial" >&2
    exit 1
fi

line_index="$(
    printf '%s\n' "$zone_json" \
        | jq -r \
            --arg record_name "${record_name%.arcanel.se}" \
            --arg validation "$validation" '
                .result.data[]
                | select(
                    .type == "record"
                    and .record_type == "TXT"
                    and .dname_raw == $record_name
                    and (.data_b64[0] | @base64d) == $validation
                )
                | .line_index
            '
)"

match_count="$(
    printf '%s\n' "$line_index" \
        | awk 'NF { count++ } END { print count + 0 }'
)"

if [[ "$match_count" -ne 1 ]]; then
    echo "Expected exactly one matching TXT record" >&2
    exit 1
fi

response="$(
    execute_remote_command \
        "$remote" \
        "uapi --output=json DNS mass_edit_zone zone=$zone serial=$serial remove=$line_index"
)"

if ! printf '%s\n' "$response" | jq -e . >/dev/null 2>&1; then
    echo "Invalid DNS zone edit response" >&2
    exit 1
fi

status="$(
    printf '%s\n' "$response" \
        | jq -r '.result.status'
)"

if [[ "$status" != "1" ]]; then
    error="$(
        printf '%s\n' "$response" \
            | jq -r '.result.errors[]?'
    )"

    if [[ -n "$error" ]]; then
        printf '%s\n' "$error" >&2
    else
        echo "DNS zone edit failed" >&2
    fi

    exit 1
fi
