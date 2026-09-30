#!/usr/bin/env bash
set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

fakebin="$tmpdir/bin"
curl_args="$tmpdir/curl-args"
secrets_file="$tmpdir/secrets.env"
template="templates/osm/osm-heartbeat.sh.template"
output="$tmpdir/osm-heartbeat"

export OSM_PROBE_CURL_ARGS="$tmpdir/osm-probe-curl-args"

mkdir -p "$fakebin"

test_label()
{
    echo
    echo "→ $1"
}

fail()
{
    echo "❌ $1"
    exit 1
}

pass()
{
    echo "✅ $1"
}

assert_curl_argument()
{
    local expected="$1"

    if grep -Fxq -- "$expected" "$curl_args"; then
        return
    fi

    echo "Expected curl argument:"
    echo "  $expected"
    echo
    echo "Actual curl arguments:"
    sed 's/^/  /' "$curl_args"
    exit 1
}

assert_probe_curl_argument()
{
    local expected="$1"

    if grep -Fxq -- "$expected" "$OSM_PROBE_CURL_ARGS"; then
        return
    fi

    echo "Expected probe curl argument:"
    echo "  $expected"
    echo
    echo "Actual probe curl arguments:"
    sed 's/^/  /' "$OSM_PROBE_CURL_ARGS"
    exit 1
}

assert_no_telemetry_post()
{
    local reason="$1"

    if [[ ! -e "$OSM_HEARTBEAT_CURL_ARGS" ]]; then
        return
    fi

    echo "Telemetry POST was attempted $reason"
    echo
    echo "Captured curl arguments:"
    sed 's/^/  /' "$OSM_HEARTBEAT_CURL_ARGS"
    exit 1
}

run_expected_collection_failure()
{
    local label="$1"
    local variable="$2"
    local value="$3"
    local stderr_file="$tmpdir/failure-stderr"

    test_label "$label"

    rm -f \
        "$OSM_HEARTBEAT_CURL_ARGS" \
        "$stderr_file"

    export "$variable=$value"

    if "$output" 2>"$stderr_file"; then
        unset "$variable"
        fail "Expected OSM heartbeat to fail"
    fi

    unset "$variable"

    assert_no_telemetry_post "after collection failure"

    if grep -Fq "unbound variable" "$stderr_file"; then
        echo "Collector failure was not propagated cleanly."
        echo
        echo "Actual stderr:"
        sed 's/^/  /' "$stderr_file"
        exit 1
    fi

    pass "$label"
}

cat > "$fakebin/curl" <<'EOF'
#!/usr/bin/env bash

last_arg="${!#}"

case "$last_arg" in
    "$OSM_BASE_URL/api/system/info")
        case "${OSM_SYSTEM_INFO_CURL_FAIL:-}" in
            unreachable)
                exit 7
                ;;
            timeout)
                exit 28
                ;;
        esac

        cat "$OSM_SYSTEM_INFO_FIXTURE"
        ;;

    "$OSM_BASE_URL/probe")
        printf '%s\n' "$@" > "$OSM_PROBE_CURL_ARGS"

        if [[ "${OSM_PROBE_MALFORMED:-}" == "1" ]]; then
            printf '{broken-json\n'
            exit 0
        fi

        cat "$OSM_PROBE_FIXTURE"
        ;;

    "$TELEMETRY_ENDPOINT")
        printf '%s\n' "$@" > "$OSM_HEARTBEAT_CURL_ARGS"
        ;;

    *)
        echo "Unexpected curl target: $last_arg" >&2
        exit 1
        ;;
esac
EOF

chmod +x "$fakebin/curl"

cat > "$secrets_file" <<'EOF'
OSM_BASE_URL=http://osm.invalid
OSM_NOC_HOST=osm-test
OSM_NOC_TOKEN=test-token
OSM_COLLECTOR_SCRIPT_FILE=/app/templates/osm/osm_collector.py
TELEMETRY_ENDPOINT=https://example.invalid/telemetry
EOF

BREDLAND_SECRETS_FILE="$secrets_file" \
    scripts/render-template.sh \
    "$template" \
    "$output"

chmod +x "$output"

export PATH="$fakebin:$PATH"
export OSM_BASE_URL="http://osm.invalid"
export TELEMETRY_ENDPOINT="https://example.invalid/telemetry"
export OSM_SYSTEM_INFO_FIXTURE="$PWD/tests/fixtures/osm/system-info.json"
export OSM_PROBE_FIXTURE="$PWD/tests/fixtures/osm/probe.json"
export OSM_HEARTBEAT_CURL_ARGS="$curl_args"

test_label "posts telemetry from OSM API responses"

rm -f "$OSM_HEARTBEAT_CURL_ARGS"
"$output"

assert_curl_argument "host=osm-test"
assert_curl_argument "token=test-token"
assert_curl_argument "ttl=600"
assert_curl_argument "uptime=727990"
assert_curl_argument "hash_rate=1040183"
assert_curl_argument "version=v2.0.03"
assert_curl_argument "rssi=-67"
assert_curl_argument "free_heap=54608"
assert_curl_argument "best_difficulty_ever=5306"
assert_curl_argument "block_hits=0"
assert_curl_argument "shares_accepted=151"
assert_curl_argument "shares_rejected=0"

assert_probe_curl_argument "--max-time"
assert_probe_curl_argument "5"

pass "posts telemetry from OSM API responses"

run_expected_collection_failure \
    "does not post telemetry when OSM is unreachable" \
    OSM_SYSTEM_INFO_CURL_FAIL \
    unreachable

run_expected_collection_failure \
    "does not post telemetry after OSM collection timeout" \
    OSM_SYSTEM_INFO_CURL_FAIL \
    timeout

run_expected_collection_failure \
    "fails cleanly on malformed OSM response" \
    OSM_PROBE_MALFORMED \
    1
