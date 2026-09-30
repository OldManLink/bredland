read_dns_zone()
{
    local remote="$1"
    local zone="$2"

    execute_remote_command \
        "$remote" \
        "uapi --output=json DNS parse_zone zone=$zone"
}

require_valid_zone_response()
{
    local zone_json="$1"
    local parse_status
    local error

    if ! printf '%s\n' "$zone_json" | jq -e . >/dev/null 2>&1; then
        echo "Invalid DNS zone response" >&2
        return 1
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

        return 1
    fi
}

extract_zone_serial()
{
    local zone_json="$1"
    local serial
    local serial_count

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
        return 1
    fi

    printf '%s\n' "$serial"
}

require_valid_zone_edit_response()
{
    local response="$1"
    local status
    local error

    if ! printf '%s\n' "$response" | jq -e . >/dev/null 2>&1; then
        echo "Invalid DNS zone edit response" >&2
        return 1
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

        return 1
    fi
}

find_txt_line_indices()
{
    local zone_json="$1"
    local record_name="$2"
    local validation="${3-}"

    if [[ -n "$validation" ]]; then
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
    else
        printf '%s\n' "$zone_json" \
            | jq -r \
                --arg record_name "${record_name%.arcanel.se}" '
                    .result.data[]
                    | select(
                        .type == "record"
                        and .record_type == "TXT"
                        and .dname_raw == $record_name
                    )
                    | .line_index
                '
    fi
}

assert_line_count()
{
    local lines="$1"
    local expected_count="$2"
    local error_message="$3"
    local actual_count

    actual_count="$(
        printf '%s\n' "$lines" \
            | awk 'NF { count++ } END { print count + 0 }'
    )"

    if [[ "$actual_count" -ne "$expected_count" ]]; then
        echo "$error_message" >&2
        return 1
    fi
}
