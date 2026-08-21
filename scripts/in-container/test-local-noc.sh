#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$repo_root"

build_dir="build/rendered-noc"
data_dir="$build_dir/data"

server_bind="0.0.0.0:8000"
server_url="http://127.0.0.1:8000"
server_log="$build_dir/php-server.log"

server_pid=""
jsonl_file=""

cleanup()
{
    if [[ -n "$server_pid" ]]; then
        kill "$server_pid" 2>/dev/null || true
        wait "$server_pid" 2>/dev/null || true
    fi
}

telemetry_line_count()
{
    local total=0
    local file
    local count

    while IFS= read -r file; do
        count="$(wc -l < "$file" | tr -d ' ')"
        total=$((total + count))
    done < <(
        find "$data_dir" \
            -maxdepth 1 \
            -type f \
            -name '*.jsonl' \
            | sort
    )

    printf '%s\n' "$total"
}

build_request_args()
{
    local items=(
        'host=bredland'
        'token=bredland.v1.test-token'
        'uptime=12347'
        'ttl=300'
        'fields=temperature,throttled,free_memory,total_memory,root_free,root_total'
        'temperature=49.1'
        'throttled=0x0'
        'free_memory=123440000'
        'total_memory=4294967296'
        'root_free=987640000'
        'root_total=1234567890'
    )

    local override
    local key
    local item_key
    local i
    local found

    for override in "$@"; do
        if [[ "$override" == !* ]]; then
            key="${override#!}"

            for i in "${!items[@]}"; do
                item_key="${items[$i]%%=*}"

                if [[ "$item_key" == "$key" ]]; then
                    unset 'items[i]'
                    break
                fi
            done

            continue
        fi

        key="${override%%=*}"
        found=false

        for i in "${!items[@]}"; do
            item_key="${items[$i]%%=*}"

            if [[ "$item_key" == "$key" ]]; then
                items[$i]="$override"
                found=true
                break
            fi
        done

        if ! $found; then
            items+=("$override")
        fi
    done

    request_args=()

    for i in "${!items[@]}"; do
        request_args+=(
            --data-urlencode "${items[$i]}"
        )
    done
}

check_request()
{
    local expected_status="$1"
    local expected_response="$2"
    local expected_line_delta="$3"
    shift 3

    local before_lines
    local after_lines
    local actual_line_delta
    local response_file
    local http_status
    local response

    before_lines="$(telemetry_line_count)"
    response_file="$(mktemp)"

    build_request_args "$@"

    if ! http_status="$(
        curl \
            --silent \
            --show-error \
            --output "$response_file" \
            --write-out '%{http_code}' \
            "${request_args[@]}" \
            "$server_url/telemetry.php"
    )"; then
        rm -f "$response_file"
        echo "❌ Telemetry request failed"
        exit 1
    fi

    response="$(cat "$response_file")"
    rm -f "$response_file"

    if [[ "$http_status" != "$expected_status" ]]; then
        echo "❌ Unexpected HTTP status"
        echo "Expected: $expected_status"
        echo "Actual:   $http_status"
        exit 1
    fi

    if [[ "$response" != "$expected_response" ]]; then
        echo "❌ Unexpected telemetry response"
        printf 'Expected: %s\n' "$expected_response"
        printf 'Actual:   %s\n' "$response"
        exit 1
    fi

    after_lines="$(telemetry_line_count)"
    actual_line_delta=$((after_lines - before_lines))

    if [[ "$actual_line_delta" != "$expected_line_delta" ]]; then
        echo "❌ Unexpected telemetry record count change"
        echo "Expected: $expected_line_delta"
        echo "Actual:   $actual_line_delta"
        exit 1
    fi
}

check_appended_line()
{
    local line_number="$1"
    local checks_name="$2"
    local -n checks="$checks_name"

    local record
    local filter=""
    local check

    record="$(sed -n "${line_number}p" "$jsonl_file")"

    if [[ -z "$record" ]]; then
        echo "❌ Expected telemetry record is missing"
        echo "Line: $line_number"
        exit 1
    fi

    if ! printf '%s\n' "$record" | jq -e . >/dev/null; then
        echo "❌ Telemetry record is not valid JSON"
        printf '%s\n' "$record"
        exit 1
    fi

    for check in "${checks[@]}"; do
        if [[ -n "$filter" ]]; then
            filter+=" and "
        fi

        filter+="($check)"
    done

    if ! printf '%s\n' "$record" |
        jq -e "$filter" >/dev/null; then

        echo "❌ Telemetry record contains unexpected values"
        printf '%s\n' "$record" | jq .
        exit 1
    fi
}

find_jsonl_file()
{
    local files

    mapfile -t files < <(
        find "$data_dir" \
            -maxdepth 1 \
            -type f \
            -name '*.jsonl' \
            | sort
    )

    if (( ${#files[@]} != 1 )); then
        echo "❌ Expected exactly one telemetry JSONL file"
        printf 'Found: %s\n' "${#files[@]}"
        printf '    %s\n' "${files[@]}"
        exit 1
    fi

    jsonl_file="${files[0]}"

    if [[ "$(basename "$jsonl_file")" != bredland-*.jsonl ]]; then
        echo "❌ Unexpected telemetry filename"
        echo "    $jsonl_file"
        exit 1
    fi
}

assert_same_jsonl_file()
{
    local files

    mapfile -t files < <(
        find "$data_dir" \
            -maxdepth 1 \
            -type f \
            -name '*.jsonl' \
            | sort
    )

    if (( ${#files[@]} != 1 )) ||
        [[ "${files[0]}" != "$jsonl_file" ]]; then

        echo "❌ Telemetry append created an unexpected JSONL file"
        exit 1
    fi
}

trap cleanup EXIT INT TERM

echo "Assembling local NOC..."
echo
tests/sh/render-noc.test.sh

echo
echo "Resetting frozen telemetry data..."

find "$data_dir" \
    -type f \
    -name '*.jsonl' \
    -delete

if find "$data_dir" \
    -type f \
    -name '*.jsonl' \
    | grep -q .; then

    echo "❌ Failed to reset local NOC telemetry data"
    exit 1
fi

echo "Starting local PHP server..."
echo

php -S "$server_bind" \
    -t "$build_dir" \
    >"$server_log" 2>&1 &

server_pid=$!

server_ready=false

for _ in 1 2 3 4 5; do
    if curl \
        --silent \
        --output /dev/null \
        "$server_url/telemetry.php"; then

        server_ready=true
        break
    fi

    sleep 1
done

if ! $server_ready; then
    echo "❌ Local PHP server failed to start"

    if [[ -s "$server_log" ]]; then
        echo "--- PHP server log ---"
        cat "$server_log"
    fi

    exit 1
fi

echo "Posting Bredland heartbeat..."

check_request \
    200 \
    "ok" \
    1 \
    'uptime=12345' \
    'temperature=47.2' \
    'free_memory=123456789' \
    'root_free=987654321'

find_jsonl_file

first_record_checks=(
    '.schema == 1'
    '.host == "bredland"'
    '.ttl == 300'
    '.uptime == 12345'
    '.temperature == 47.2'
    '.throttled == "0x0"'
    '.free_memory == 123456789'
    '.total_memory == 4294967296'
    '.root_free == 987654321'
    '.root_total == 1234567890'
    '(.ts | type == "string")'
    '(.remote_addr | type == "string")'
)

check_appended_line \
    1 \
    first_record_checks

first_record="$(sed -n '1p' "$jsonl_file")"

echo "✅ Local NOC accepted first Bredland heartbeat"

echo "Posting second Bredland heartbeat with extra field..."

check_request \
    200 \
    "ok" \
    1 \
    'uptime=12346' \
    'fields=temperature,throttled,ignore_this,free_memory,total_memory,root_free,root_total' \
    'temperature=48.3' \
    'ignore_this=new field' \
    'free_memory=123450000' \
    'root_free=987650000'

assert_same_jsonl_file

second_record_checks=(
    '.schema == 1'
    '.host == "bredland"'
    '.ttl == 300'
    '.uptime == 12346'
    '.temperature == 48.3'
    '.free_memory == 123450000'
    '.root_free == 987650000'
    '(has("ignore_this") | not)'
)

check_appended_line \
    2 \
    second_record_checks

first_record_after_append="$(sed -n '1p' "$jsonl_file")"

if [[ "$first_record_after_append" != "$first_record" ]]; then
    echo "❌ First telemetry record changed after append"
    exit 1
fi

echo "✅ Local NOC appended second Bredland heartbeat, ignoring the extra field"

echo "Rejecting heartbeat with wrong token..."

check_request \
    403 \
    "forbidden" \
    0 \
    'token=wrong-token'

echo "✅ Rejected heartbeat with wrong token"

echo "Rejecting heartbeat with missing uptime..."

check_request \
    400 \
    "missing parameter: uptime" \
    0 \
    '!uptime'

echo "✅ Rejected heartbeat with missing uptime"

echo "Rejecting heartbeat with invalid temperature..."

check_request \
    400 \
    "invalid value for field temperature: expected float" \
    0 \
    'temperature=not-a-number'

echo "✅ Rejected heartbeat with invalid temperature"

echo "Rejecting heartbeat from unknown host..."

check_request \
    403 \
    "forbidden" \
    0 \
    'host=no-such-host' \
    'token=does-not-matter'

echo "✅ Rejected heartbeat from unknown host"

echo "Rejecting heartbeat that spoofs reserved field ts..."

check_request \
    400 \
    "reserved field: ts" \
    0 \
    'uptime=12349' \
    'fields=ts,temperature,throttled,free_memory,total_memory,root_free,root_total' \
    'ts=2000-01-01T00:00:00Z'

echo "✅ Rejected heartbeat that spoofs reserved field ts"

if [[ "${LOCAL_NOC_PREVIEW:-0}" == "1" ]]; then
    echo
    echo "Posting short-lived heartbeat for preview..."

    check_request \
        200 \
        "ok" \
        1 \
        'host=mikrotik' \
        'token=mikrotik.v1.test-token' \
        'ttl=5' \
        'fields=version,update_channel,model,cpu_load,free_memory,total_memory,latest_version' \
        'version=7.23.1' \
        'update_channel=stable' \
        'model=RB4011iGS+' \
        'cpu_load=0' \
        'free_memory=879124480' \
        'total_memory=1073741824' \
        'latest_version=7.24'

    echo "✅ Local NOC ready for health transition preview"

    touch "$build_dir/.preview-ready"

    echo
    echo "✅ Local NOC integration checks passed"
    echo "🌐 Local NOC ready for preview"
    echo

    while true; do
        sleep 3600
    done
fi
