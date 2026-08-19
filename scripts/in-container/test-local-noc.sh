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

cleanup()
{
    if [[ -n "$server_pid" ]]; then
        kill "$server_pid" 2>/dev/null || true
        wait "$server_pid" 2>/dev/null || true
    fi
}

trap cleanup EXIT INT TERM

echo "Assembling local NOC..."
tests/sh/render-noc.test.sh

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

response="$(
    curl \
        --silent \
        --show-error \
        --data-urlencode 'host=bredland' \
        --data-urlencode 'token=bredland.v1.test-token' \
        --data-urlencode 'uptime=12345' \
        --data-urlencode 'ttl=300' \
        --data-urlencode \
            'fields=temperature,throttled,free_memory,total_memory,root_free,root_total' \
        --data-urlencode 'temperature=47.2' \
        --data-urlencode 'throttled=0x0' \
        --data-urlencode 'free_memory=123456789' \
        --data-urlencode 'total_memory=4294967296' \
        --data-urlencode 'root_free=987654321' \
        --data-urlencode 'root_total=1234567890' \
        "$server_url/telemetry.php"
)"

if [[ "$response" != "ok" ]]; then
    echo "❌ Unexpected telemetry endpoint response:"
    printf '%s\n' "$response"
    exit 1
fi

mapfile -t jsonl_files < <(
    find "$data_dir" \
        -maxdepth 1 \
        -type f \
        -name '*.jsonl' \
        | sort
)

if (( ${#jsonl_files[@]} != 1 )); then
    echo "❌ Expected exactly one telemetry JSONL file"
    printf 'Found: %s\n' "${#jsonl_files[@]}"
    printf '    %s\n' "${jsonl_files[@]}"
    exit 1
fi

jsonl_file="${jsonl_files[0]}"

if [[ "$(basename "$jsonl_file")" != bredland-*.jsonl ]]; then
    echo "❌ Unexpected telemetry filename:"
    echo "    $jsonl_file"
    exit 1
fi

line_count="$(wc -l < "$jsonl_file" | tr -d ' ')"

if [[ "$line_count" != "1" ]]; then
    echo "❌ Expected exactly one telemetry record"
    echo "Found: $line_count"
    exit 1
fi

record="$(cat "$jsonl_file")"

if ! printf '%s\n' "$record" | jq -e . >/dev/null; then
    echo "❌ Telemetry record is not valid JSON:"
    printf '%s\n' "$record"
    exit 1
fi

printf '%s\n' "$record" |
    jq -e '
        .schema == 1 and
        .host == "bredland" and
        .ttl == 300 and
        .uptime == 12345 and
        .temperature == 47.2 and
        .throttled == "0x0" and
        .free_memory == 123456789 and
        .total_memory == 4294967296 and
        .root_free == 987654321 and
        .root_total == 1234567890 and
        (.ts | type == "string") and
        (.remote_addr | type == "string")
    ' >/dev/null || {
        echo "❌ Telemetry record contains unexpected values:"
        printf '%s\n' "$record" | jq .
        exit 1
    }

echo "✅ Local NOC accepted first Bredland heartbeat"

echo "Posting second Bredland heartbeat..."

response="$(
    curl \
        --silent \
        --show-error \
        --data-urlencode 'host=bredland' \
        --data-urlencode 'token=bredland.v1.test-token' \
        --data-urlencode 'uptime=12346' \
        --data-urlencode 'ttl=300' \
        --data-urlencode \
            'fields=temperature,throttled,free_memory,total_memory,root_free,root_total' \
        --data-urlencode 'temperature=48.3' \
        --data-urlencode 'throttled=0x0' \
        --data-urlencode 'free_memory=123450000' \
        --data-urlencode 'total_memory=4294967296' \
        --data-urlencode 'root_free=987650000' \
        --data-urlencode 'root_total=1234567890' \
        "$server_url/telemetry.php"
)"

if [[ "$response" != "ok" ]]; then
    echo "❌ Unexpected telemetry endpoint response:"
    printf '%s\n' "$response"
    exit 1
fi

mapfile -t jsonl_files < <(
    find "$data_dir" \
        -maxdepth 1 \
        -type f \
        -name '*.jsonl' \
        | sort
)

if (( ${#jsonl_files[@]} != 1 )); then
    echo "❌ Expected exactly one telemetry JSONL file after second heartbeat"
    printf 'Found: %s\n' "${#jsonl_files[@]}"
    exit 1
fi

line_count="$(wc -l < "$jsonl_file" | tr -d ' ')"

if [[ "$line_count" != "2" ]]; then
    echo "❌ Expected exactly two telemetry records"
    echo "Found: $line_count"
    exit 1
fi

second_record="$(sed -n '2p' "$jsonl_file")"

printf '%s\n' "$second_record" |
    jq -e '
        .schema == 1 and
        .host == "bredland" and
        .ttl == 300 and
        .uptime == 12346 and
        .temperature == 48.3 and
        .free_memory == 123450000 and
        .root_free == 987650000
    ' >/dev/null || {
        echo "❌ Second telemetry record contains unexpected values:"
        printf '%s\n' "$second_record" | jq .
        exit 1
    }

first_record_after_append="$(sed -n '1p' "$jsonl_file")"

if [[ "$first_record_after_append" != "$record" ]]; then
    echo "❌ First telemetry record changed after append"
    exit 1
fi

echo "✅ Local NOC appended second Bredland heartbeat"

before_lines="$(wc -l < "$jsonl_file" | tr -d ' ')"

echo "Rejecting heartbeat with wrong token..."

response_file="$(mktemp)"

http_status="$(
    curl \
        --silent \
        --show-error \
        --output "$response_file" \
        --write-out '%{http_code}' \
        --data-urlencode 'host=bredland' \
        --data-urlencode 'token=wrong-token' \
        --data-urlencode 'uptime=12347' \
        --data-urlencode 'ttl=300' \
        --data-urlencode \
            'fields=temperature,throttled,free_memory,total_memory,root_free,root_total' \
        --data-urlencode 'temperature=49.1' \
        --data-urlencode 'throttled=0x0' \
        --data-urlencode 'free_memory=123440000' \
        --data-urlencode 'total_memory=4294967296' \
        --data-urlencode 'root_free=987640000' \
        --data-urlencode 'root_total=1234567890' \
        "$server_url/telemetry.php"
)"

response="$(cat "$response_file")"
rm -f "$response_file"

if [[ "$http_status" != "403" ]]; then
    echo "❌ Expected HTTP 403 for invalid token, got $http_status"
    exit 1
fi

if [[ "$response" != "forbidden" ]]; then
    echo "❌ Unexpected response for invalid token:"
    printf '%s\n' "$response"
    exit 1
fi

after_lines="$(wc -l < "$jsonl_file" | tr -d ' ')"

if [[ "$after_lines" != "$before_lines" ]]; then
    echo "❌ Invalid heartbeat modified telemetry data"
    exit 1
fi

echo "✅ Rejected heartbeat with wrong token"

echo "Rejecting heartbeat with missing uptime..."

before_lines="$(wc -l < "$jsonl_file" | tr -d ' ')"
response_file="$(mktemp)"

http_status="$(
    curl \
        --silent \
        --show-error \
        --output "$response_file" \
        --write-out '%{http_code}' \
        --data-urlencode 'host=bredland' \
        --data-urlencode 'token=bredland.v1.test-token' \
        --data-urlencode 'ttl=300' \
        --data-urlencode \
            'fields=temperature,throttled,free_memory,total_memory,root_free,root_total' \
        --data-urlencode 'temperature=49.1' \
        --data-urlencode 'throttled=0x0' \
        --data-urlencode 'free_memory=123440000' \
        --data-urlencode 'total_memory=4294967296' \
        --data-urlencode 'root_free=987640000' \
        --data-urlencode 'root_total=1234567890' \
        "$server_url/telemetry.php"
)"

response="$(cat "$response_file")"
rm -f "$response_file"

if [[ "$http_status" != "400" ]]; then
    echo "❌ Expected HTTP 400 for missing uptime, got $http_status"
    exit 1
fi

if [[ "$response" != "missing parameter: uptime" ]]; then
    echo "❌ Unexpected response for missing uptime:"
    printf '%s\n' "$response"
    exit 1
fi

after_lines="$(wc -l < "$jsonl_file" | tr -d ' ')"

if [[ "$after_lines" != "$before_lines" ]]; then
    echo "❌ Heartbeat with missing uptime modified telemetry data"
    exit 1
fi

echo "✅ Rejected heartbeat with missing uptime"

echo "Rejecting heartbeat with invalid temperature..."

before_lines="$(wc -l < "$jsonl_file" | tr -d ' ')"
response_file="$(mktemp)"

http_status="$(
    curl \
        --silent \
        --show-error \
        --output "$response_file" \
        --write-out '%{http_code}' \
        --data-urlencode 'host=bredland' \
        --data-urlencode 'token=bredland.v1.test-token' \
        --data-urlencode 'uptime=12347' \
        --data-urlencode 'ttl=300' \
        --data-urlencode \
            'fields=temperature,throttled,free_memory,total_memory,root_free,root_total' \
        --data-urlencode 'temperature=not-a-number' \
        --data-urlencode 'throttled=0x0' \
        --data-urlencode 'free_memory=123440000' \
        --data-urlencode 'total_memory=4294967296' \
        --data-urlencode 'root_free=987640000' \
        --data-urlencode 'root_total=1234567890' \
        "$server_url/telemetry.php"
)"

response="$(cat "$response_file")"
rm -f "$response_file"

if [[ "$http_status" != "400" ]]; then
    echo "❌ Expected HTTP 400 for invalid temperature, got $http_status"
    exit 1
fi

if [[ -z "$response" ]]; then
    echo "❌ Expected an error response for invalid temperature"
    exit 1
fi

if [[ "$response" != "invalid value for field temperature: expected float" ]]; then
    echo "❌ Unexpected response for invalid temperature:"
    printf '%s\n' "$response"
    exit 1
fi

after_lines="$(wc -l < "$jsonl_file" | tr -d ' ')"

if [[ "$after_lines" != "$before_lines" ]]; then
    echo "❌ Invalid temperature heartbeat modified telemetry data"
    exit 1
fi

echo "✅ Rejected heartbeat with invalid temperature"

echo "Rejecting heartbeat from unknown host..."

before_lines="$(wc -l < "$jsonl_file" | tr -d ' ')"
response_file="$(mktemp)"

http_status="$(
    curl \
        --silent \
        --show-error \
        --output "$response_file" \
        --write-out '%{http_code}' \
        --data-urlencode 'host=no-such-host' \
        --data-urlencode 'token=does-not-matter' \
        --data-urlencode 'uptime=12348' \
        --data-urlencode 'ttl=300' \
        --data-urlencode \
            'fields=temperature,throttled,free_memory,total_memory,root_free,root_total' \
        --data-urlencode 'temperature=49.1' \
        --data-urlencode 'throttled=0x0' \
        --data-urlencode 'free_memory=123430000' \
        --data-urlencode 'total_memory=4294967296' \
        --data-urlencode 'root_free=987630000' \
        --data-urlencode 'root_total=1234567890' \
        "$server_url/telemetry.php"
)"

response="$(cat "$response_file")"
rm -f "$response_file"

if [[ "$http_status" != "403" ]]; then
    echo "❌ Expected HTTP 403 for unknown host, got $http_status"
    exit 1
fi

if [[ "$response" != "forbidden" ]]; then
    echo "❌ Unexpected response for unknown host:"
    printf '%s\n' "$response"
    exit 1
fi

after_lines="$(wc -l < "$jsonl_file" | tr -d ' ')"

if [[ "$after_lines" != "$before_lines" ]]; then
    echo "❌ Unknown-host heartbeat modified telemetry data"
    exit 1
fi

echo "✅ Rejected heartbeat from unknown host"

echo "Rejecting heartbeat that spoofs reserved field ts..."

before_lines="$(wc -l < "$jsonl_file" | tr -d ' ')"
response_file="$(mktemp)"

http_status="$(
    curl \
        --silent \
        --show-error \
        --output "$response_file" \
        --write-out '%{http_code}' \
        --data-urlencode 'host=bredland' \
        --data-urlencode 'token=bredland.v1.test-token' \
        --data-urlencode 'uptime=12349' \
        --data-urlencode 'ttl=300' \
        --data-urlencode \
            'fields=ts,temperature,throttled,free_memory,total_memory,root_free,root_total' \
        --data-urlencode 'ts=2000-01-01T00:00:00Z' \
        --data-urlencode 'temperature=49.1' \
        --data-urlencode 'throttled=0x0' \
        --data-urlencode 'free_memory=123420000' \
        --data-urlencode 'total_memory=4294967296' \
        --data-urlencode 'root_free=987620000' \
        --data-urlencode 'root_total=1234567890' \
        "$server_url/telemetry.php"
)"

response="$(cat "$response_file")"
rm -f "$response_file"

if [[ "$http_status" != "400" ]]; then
    echo "❌ Expected HTTP 400 for reserved field ts, got $http_status"
    exit 1
fi

if [[ "$response" != "reserved field: ts" ]]; then
    echo "❌ Unexpected response for reserved field ts:"
    printf '%s\n' "$response"
    exit 1
fi

after_lines="$(wc -l < "$jsonl_file" | tr -d ' ')"

if [[ "$after_lines" != "$before_lines" ]]; then
    echo "❌ Reserved-field heartbeat modified telemetry data"
    exit 1
fi

echo "✅ Rejected heartbeat that spoofs reserved field ts"

if [[ "${LOCAL_NOC_PREVIEW:-0}" == "1" ]]; then
    touch "$build_dir/.preview-ready"

    echo
    echo "✅ Local NOC integration checks passed"
    echo "🌐 Local NOC ready for preview"
    echo

    while true; do
        sleep 3600
    done
fi
