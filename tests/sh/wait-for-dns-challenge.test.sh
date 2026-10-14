#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=tests/sh/lib/testlib.sh
source "$(dirname "$0")/lib/testlib.sh"
passed=0
failed=0

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

fakebin="$tmpdir/bin"
dig_log="$tmpdir/dig-log"
sleep_log="$tmpdir/sleep-log"
stderr_file="$tmpdir/stderr"
stdout_file="$tmpdir/stdout"
dns1_count_file="$tmpdir/dns1-count"
dns2_count_file="$tmpdir/dns2-count"
public_count_file="$tmpdir/public-count"
now_file="$tmpdir/now"

mkdir -p "$fakebin"

export PATH="$fakebin:$PATH"
export DIG_LOG="$dig_log"
export SLEEP_LOG="$sleep_log"
export DNS1_COUNT_FILE="$dns1_count_file"
export DNS2_COUNT_FILE="$dns2_count_file"
export PUBLIC_COUNT_FILE="$public_count_file"
export NOW_FILE="$now_file"

cat > "$fakebin/dig" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

printf '%s\n' "$*" >> "$DIG_LOG"

read_count()
{
    local file="${1:?Missing count file}"

    if [[ -e "$file" ]]; then
        cat "$file"
    else
        printf '%s\n' 0
    fi
}

increment_count()
{
    local file="${1:?Missing count file}"
    local count

    count="$(read_count "$file")"
    count=$((count + 1))
    printf '%s\n' "$count" > "$file"

    printf '%s\n' "$count"
}

case "$*" in
    "+short NS arcanel.se")
        if [[ "${NS_FAIL:-false}" == true ]]; then
            echo "simulated NS lookup failure" >&2
            exit 1
        fi

        if [[ "${NS_EMPTY:-false}" != true ]]; then
            printf '%s\n' \
                "dns1.oderland.com." \
                "dns2.oderland.com."
        fi
        ;;

    "@dns1.oderland.com. +short TXT _brd-038-spike.arcanel.se")
        increment_count "$DNS1_COUNT_FILE" >/dev/null
        printf '%s\n' "${DNS1_RESPONSE:-\"brd-038-test\"}"
        ;;

    "@dns2.oderland.com. +short TXT _brd-038-spike.arcanel.se")
        if [[ "${DNS2_FAIL:-false}" == true ]]; then
            echo "simulated dns2 lookup failure" >&2
            exit 1
        fi

        count="$(increment_count "$DNS2_COUNT_FILE")"

        if [[ "${DNS2_READY_AFTER:-1}" -le "$count" ]]; then
            printf '%s\n' '"brd-038-test"'
        else
            printf '%s\n' "${DNS2_RESPONSE_BEFORE_READY:-}"
        fi
        ;;

    "+short TXT _brd-038-spike.arcanel.se")
        increment_count "$PUBLIC_COUNT_FILE" >/dev/null
        printf '%s\n' "${PUBLIC_RESPONSE:-\"brd-038-test\"}"
        ;;

    "TXT _brd-038-spike.arcanel.se")
        count="$(increment_count "$PUBLIC_COUNT_FILE")"

        if [[ "${PUBLIC_FAIL:-false}" == true ]]; then
            echo "simulated public DNS lookup failure" >&2
            exit 1
        fi

        if [[ "${PUBLIC_NXDOMAIN:-false}" == true ]] &&
           [[ "${PUBLIC_NXDOMAIN_AFTER_FIRST:-true}" == true || "$count" -eq 1 ]]; then
            printf '%s\n' \
                ';; ->>HEADER<<- opcode: QUERY, status: NXDOMAIN, id: 12345' \
                ';; AUTHORITY SECTION:'

            printf 'arcanel.se. %s IN SOA dns1.oderland.com. sales.oderland.se. 2026101401 86400 7200 3600000 %s\n' \
                "$PUBLIC_SOA_TTL" \
                "$PUBLIC_SOA_MINIMUM"
            exit 0
        fi

        if [[ "${PUBLIC_READY_AFTER:-1}" -le "$count" ]]; then
            response='"brd-038-test"'
        else
            response="${PUBLIC_RESPONSE_BEFORE_READY:-\"old-challenge-token\"}"
        fi

        ttl="${PUBLIC_TTL:-30}"

        if [[ "$count" -ge 2 && -n "${PUBLIC_TTL_AFTER_FIRST:-}" ]]; then
            ttl="$PUBLIC_TTL_AFTER_FIRST"
        fi

        printf '%s\t%s\tIN\tTXT\t%s\n' \
            "_brd-038-spike.arcanel.se." \
            "$ttl" \
            "$response"
        ;;

    *)
        echo "Unexpected dig invocation: $*" >&2
        exit 1
        ;;
esac
EOF
chmod +x "$fakebin/dig"

cat > "$fakebin/sleep" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

seconds="${1:?Missing sleep duration}"

printf '%s\n' "$seconds" >> "$SLEEP_LOG"

now="$(cat "$NOW_FILE")"
printf '%s\n' "$((now + seconds))" > "$NOW_FILE"
EOF
chmod +x "$fakebin/sleep"

cat > "$fakebin/date" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

case "$*" in
    "+%s")
        cat "$NOW_FILE"
        ;;

    "-r "*)
        epoch="${2:?Missing epoch}"
        format="${3:?Missing format}"

        case "$format" in
            "+%H:%M")
                hour=$(((epoch / 3600) % 24))
                minute=$(((epoch / 60) % 60))

                printf '%02d:%02d\n' "$hour" "$minute"
                ;;

            "+%Y-%m-%d")
                printf '%s\n' "$((epoch / 86400))"
                ;;

            *)
                echo "Unexpected date format: $format" >&2
                exit 1
                ;;
        esac
        ;;

    *)
        echo "Unexpected date invocation: $*" >&2
        exit 1
        ;;
esac
EOF
chmod +x "$fakebin/date"

run_waiter()
{
    scripts/tls/wait-for-dns-challenge.sh \
        "_brd-038-spike.arcanel.se" \
        "brd-038-test" \
        >"$stdout_file" \
        2>"$stderr_file"
}

reset_dns_scenario()
{
    export NS_FAIL=false
    export NS_EMPTY=false

    export DNS1_RESPONSE='"brd-038-test"'
    export DNS2_FAIL=false
    export DNS2_READY_AFTER=1
    export DNS2_RESPONSE_BEFORE_READY=""
    export PUBLIC_READY_AFTER=1
    export PUBLIC_RESPONSE_BEFORE_READY='"old-challenge-token"'
    export PUBLIC_TTL=30
    export DNS_WAIT_MAX_ATTEMPTS=1
    export DNS_WAIT_TIMEOUT_SECONDS=60
    export PUBLIC_RESPONSE='"brd-038-test"'
    export PUBLIC_TTL_AFTER_FIRST=""
    export PUBLIC_FAIL=false
    export PUBLIC_NXDOMAIN=false
    export PUBLIC_SOA_TTL=7200
    export PUBLIC_SOA_MINIMUM=86400
    export PUBLIC_NXDOMAIN_AFTER_FIRST=true
    export TZ=UTC

    rm -f \
        "$dns1_count_file" \
        "$dns2_count_file" \
        "$public_count_file"

    printf '%s\n' 1000 > "$now_file"
    : > "$dig_log"
    : > "$sleep_log"
    : > "$stdout_file"
    : > "$stderr_file"
}

#
# End setup, begin test suite.
#

echo
echo "→ succeeds when expected TXT is visible everywhere"
reset_dns_scenario

if ! run_waiter; then
    echo "❌ Expected DNS challenge visibility check to succeed"
    cat "$stderr_file"
    exit 1
fi

expected_calls="$(
    cat <<'EOF'
+short NS arcanel.se
@dns1.oderland.com. +short TXT _brd-038-spike.arcanel.se
@dns2.oderland.com. +short TXT _brd-038-spike.arcanel.se
TXT _brd-038-spike.arcanel.se
EOF
)"

actual_calls="$(cat "$dig_log")"

if [[ "$actual_calls" != "$expected_calls" ]]; then
    echo "❌ Expected authoritative DNS to complete before public DNS"
    echo
    echo "Expected:"
    printf '%s\n' "$expected_calls" | sed 's/^/  /'
    echo
    echo "Actual:"
    printf '%s\n' "$actual_calls" | sed 's/^/  /'
    exit 1
fi

echo "✅ succeeds when expected TXT is visible everywhere"

echo
echo "→ retries only authoritative servers still waiting for TXT"
reset_dns_scenario

export DNS_WAIT_MAX_ATTEMPTS=2

export DNS1_RESPONSE='"brd-038-test"'
export DNS2_READY_AFTER=2
export DNS2_RESPONSE_BEFORE_READY=""
export PUBLIC_RESPONSE='"brd-038-test"'

if ! run_waiter; then
    echo "❌ Expected DNS challenge visibility check to succeed"
    cat "$stderr_file"
    exit 1
fi

dns1_count="$(cat "$dns1_count_file")"
dns2_count="$(cat "$dns2_count_file")"

if [[ "$dns1_count" -ne 1 ]]; then
    echo "❌ Expected already-ready authoritative server to be queried once"
    echo "Actual dns1 query count: $dns1_count"
    exit 1
fi

if [[ "$dns2_count" -ne 2 ]]; then
    echo "❌ Expected pending authoritative server to be retried"
    echo "Actual dns2 query count: $dns2_count"
    exit 1
fi

actual="$(cat "$stdout_file")"
actual_sleep="$(cat "$sleep_log")"
failed_before=$failed

assert_contains "$actual_sleep" "1"
assert_contains "$actual" "dns1.oderland.com. ✓ expected TXT visible"
assert_contains "$actual" "dns2.oderland.com. … waiting for TXT"
assert_contains "$actual" "dns2.oderland.com. ✓ expected TXT visible"

if (( $failed == $failed_before )); then
    echo "✅ retries only authoritative servers still waiting for TXT"
fi

echo
echo "→ fails explicitly when authoritative DNS lookup fails"
reset_dns_scenario

export DNS1_RESPONSE='"brd-038-test"'
export DNS2_FAIL=true
export DNS2_READY_AFTER=1
export DNS2_RESPONSE_BEFORE_READY=""
export PUBLIC_RESPONSE='"brd-038-test"'

if run_waiter; then
    echo "❌ Expected authoritative DNS lookup failure"
    exit 1
fi
export DNS2_FAIL=false

actual_stdout="$(cat "$stdout_file")"
actual_stderr="$(cat "$stderr_file")"

failed_before=$failed

assert_contains "$actual_stderr" "Authoritative DNS lookup failed: dns2.oderland.com."
assert_not_contains "$actual_stdout" "dns2.oderland.com. … waiting for TXT"

if (( failed == failed_before )); then
    echo "✅ fails explicitly when authoritative DNS lookup fails"
fi

echo
echo "→ fails when no authoritative DNS servers are discovered"
reset_dns_scenario

export NS_EMPTY=true

if run_waiter; then
    echo "❌ Expected missing authoritative DNS servers to fail"
    exit 1
fi
export NS_EMPTY=false

actual="$(cat "$stderr_file")"
failed_before=$failed

assert_contains "$actual" "No authoritative DNS servers discovered for arcanel.se"

if (( failed == failed_before )); then
    echo "✅ fails when no authoritative DNS servers are discovered"
fi

echo
echo "→ fails explicitly when authoritative DNS discovery fails"
reset_dns_scenario

export NS_FAIL=true
export NS_EMPTY=false

if run_waiter; then
    echo "❌ Expected authoritative DNS discovery failure"
    exit 1
fi
export NS_FAIL=false

actual="$(cat "$stderr_file")"
failed_before=$failed

assert_contains "$actual" "Authoritative DNS discovery failed for arcanel.se"

if (( failed == failed_before )); then
    echo "✅ fails explicitly when authoritative DNS discovery fails"
fi

echo
echo "→ times out when authoritative TXT never becomes visible"
reset_dns_scenario

export DNS1_RESPONSE='"brd-038-test"'
export DNS2_FAIL=false
export DNS2_READY_AFTER=999
export DNS2_RESPONSE_BEFORE_READY=""
export PUBLIC_RESPONSE='"brd-038-test"'
export DNS_WAIT_MAX_ATTEMPTS=2

if run_waiter; then
    echo "❌ Expected authoritative DNS propagation timeout"
    exit 1
fi

actual="$(cat "$stderr_file")"
failed_before=$failed

assert_contains "$actual" "Timed out waiting for authoritative DNS propagation"

if (( failed == failed_before )); then
    echo "✅ times out when authoritative TXT never becomes visible"
fi

echo
echo "→ succeeds when public DNS already has expected TXT"
reset_dns_scenario

if ! run_waiter; then
    echo "❌ Expected public DNS visibility check to succeed"
    cat "$stderr_file"
    exit 1
fi

actual="$(cat "$dig_log")"
failed_before=$failed

assert_contains "$actual" "TXT _brd-038-spike.arcanel.se"

if (( failed == failed_before )); then
    echo "✅ succeeds when public DNS already has expected TXT"
fi

echo
echo "→ reports stale public TXT value"
reset_dns_scenario

export PUBLIC_READY_AFTER=999
export PUBLIC_RESPONSE_BEFORE_READY='"old-challenge-token"'

if run_waiter; then
    echo "❌ Expected stale public TXT value to prevent success"
    exit 1
fi

actual="$(cat "$stderr_file")"
failed_before=$failed

assert_contains "$actual" 'Public DNS returned stale TXT: "old-challenge-token"'

if (( failed == failed_before )); then
    echo "✅ reports stale public TXT value"
fi

echo
echo "→ reports stale public TXT value with remaining TTL"
reset_dns_scenario

export PUBLIC_READY_AFTER=999
export PUBLIC_RESPONSE_BEFORE_READY='"old-challenge-token"'
export PUBLIC_TTL=30

if run_waiter; then
    echo "❌ Expected stale public TXT value to prevent success"
    exit 1
fi

actual="$(cat "$stderr_file")"
failed_before=$failed

assert_contains "$actual" 'Public DNS returned stale TXT: "old-challenge-token"'
assert_contains "$actual" "Remaining TTL: 30s"

if (( failed == failed_before )); then
    echo "✅ reports stale public TXT value with remaining TTL"
fi

echo
echo "→ sleeps for stale public TXT TTL before retrying"
reset_dns_scenario

export PUBLIC_READY_AFTER=2
export PUBLIC_TTL=30
export DNS_WAIT_MAX_ATTEMPTS=2

if ! run_waiter; then
    echo "❌ Expected public DNS to succeed after stale cache expires"
    cat "$stderr_file"
    exit 1
fi

actual_sleep="$(cat "$sleep_log")"
failed_before=$failed

assert_contains "$actual_sleep" "30"

public_count="$(cat "$public_count_file")"

if [[ "$public_count" -ne 2 ]]; then
    echo "❌ Expected public DNS to be queried again after TTL wait"
    echo "Actual public query count: $public_count"
    exit 1
fi

if (( failed == failed_before )); then
    echo "✅ sleeps for stale public TXT TTL before retrying"
fi

echo
echo "→ aborts early when stale public TXT outlives wait budget"
reset_dns_scenario

export PUBLIC_READY_AFTER=2
export PUBLIC_TTL=120
export DNS_WAIT_MAX_ATTEMPTS=2
export DNS_WAIT_TIMEOUT_SECONDS=60

if run_waiter; then
    echo "❌ Expected stale public DNS cache to outlive wait budget"

    exit 1
fi

actual="$(cat "$stderr_file")"
failed_before=$failed

assert_contains "$actual" "Public DNS cache TTL exceeds remaining wait time"
assert_contains "$actual" "Remaining TTL: 120s"
assert_contains "$actual" "Remaining wait time: 60s"
assert_contains "$actual" "Please try again after 00:18 today"

if [[ -s "$sleep_log" ]]; then
    echo "❌ Expected no sleep when stale cache outlives wait budget"
    echo
    echo "Actual sleeps:"
    sed 's/^/  /' "$sleep_log"
    exit 1
fi

if (( failed == failed_before )); then
    echo "✅ aborts early when stale public TXT outlives wait budget"
fi

echo
echo "→ reports tomorrow when retry time crosses midnight"

reset_dns_scenario

printf '%s\n' 86390 > "$now_file"

export PUBLIC_READY_AFTER=2
export PUBLIC_TTL=30
export DNS_WAIT_MAX_ATTEMPTS=2
export DNS_WAIT_TIMEOUT_SECONDS=10

if run_waiter; then
    echo "❌ Expected stale public DNS cache to outlive wait budget"
    exit 1
fi

actual="$(cat "$stderr_file")"
failed_before=$failed

assert_contains "$actual" "Please try again after 00:00 tomorrow"

if (( failed == failed_before )); then
    echo "✅ reports tomorrow when retry time crosses midnight"
fi

echo
echo "→ uses remaining public wait time after earlier sleep"

reset_dns_scenario

export PUBLIC_READY_AFTER=3
export PUBLIC_TTL=30
export PUBLIC_TTL_AFTER_FIRST=40
export DNS_WAIT_MAX_ATTEMPTS=3
export DNS_WAIT_TIMEOUT_SECONDS=60

if run_waiter; then
    echo "❌ Expected second stale cache to outlive remaining wait budget"
    exit 1
fi

actual="$(cat "$stderr_file")"
failed_before=$failed

assert_contains "$actual" "Public DNS cache TTL exceeds remaining wait time"
assert_contains "$actual" "Remaining TTL: 40s"
assert_contains "$actual" "Remaining wait time: 30s"

actual_sleep="$(cat "$sleep_log")"

if [[ "$actual_sleep" != "30" ]]; then
    echo "❌ Expected only the first 30s cache wait"
    echo
    echo "Actual sleeps:"
    sed 's/^/  /' "$sleep_log"
    exit 1
fi

if (( failed == failed_before )); then
    echo "✅ uses remaining public wait time after earlier sleep"
fi

echo
echo "→ authoritative retries do not consume public wait budget"

reset_dns_scenario

export DNS2_READY_AFTER=3
export PUBLIC_READY_AFTER=2
export PUBLIC_TTL=60
export DNS_WAIT_MAX_ATTEMPTS=3
export DNS_WAIT_TIMEOUT_SECONDS=60

if ! run_waiter; then
    echo "❌ Expected full public wait budget after authoritative retries"
    cat "$stderr_file"
    exit 1
fi

actual_sleep="$(cat "$sleep_log")"
failed_before=$failed

assert_contains "$actual_sleep" "1"
assert_contains "$actual_sleep" "60"

if (( failed == failed_before )); then
    echo "✅ authoritative retries do not consume public wait budget"
fi

echo
echo "→ fails explicitly when public DNS lookup fails"

reset_dns_scenario

export PUBLIC_FAIL=true

if run_waiter; then
    echo "❌ Expected public DNS lookup failure"
    exit 1
fi

actual="$(cat "$stderr_file")"
failed_before=$failed

assert_contains \
    "$actual" \
    "Public DNS lookup failed for _brd-038-spike.arcanel.se"

if (( failed == failed_before )); then
    echo "✅ fails explicitly when public DNS lookup fails"
fi

echo
echo "→ reports public NXDOMAIN explicitly"

reset_dns_scenario

export PUBLIC_NXDOMAIN=true

if run_waiter; then
    echo "❌ Expected public DNS NXDOMAIN"
    exit 1
fi

actual="$(cat "$stderr_file")"
failed_before=$failed

assert_contains "$actual" "Public DNS returned NXDOMAIN"
assert_contains "$actual" "Negative cache TTL: 7200s"
assert_contains "$actual" "Negative cache TTL exceeds remaining wait time"
assert_contains "$actual" "Remaining wait time: 60s"
assert_contains "$actual" "Please try again after 02:16 today"

if [[ -s "$sleep_log" ]]; then
    echo "❌ Expected no sleep when negative cache outlives wait budget"
    echo
    echo "Actual sleeps:"
    sed 's/^/  /' "$sleep_log"
    exit 1
fi

if (( failed == failed_before )); then
    echo "✅ reports public NXDOMAIN explicitly"
fi

echo
echo "→ uses SOA minimum when it is lower than SOA TTL"

reset_dns_scenario

export PUBLIC_NXDOMAIN=true
export PUBLIC_SOA_TTL=7200
export PUBLIC_SOA_MINIMUM=600

if run_waiter; then
    echo "❌ Expected public DNS NXDOMAIN"
    exit 1
fi

actual="$(cat "$stderr_file")"
failed_before=$failed

assert_contains "$actual" "Negative cache TTL: 600s"

if (( failed == failed_before )); then
    echo "✅ uses SOA minimum when it is lower than SOA TTL"
fi

echo
echo "→ sleeps for negative cache TTL before retrying"

reset_dns_scenario

export PUBLIC_NXDOMAIN=true
export PUBLIC_NXDOMAIN_AFTER_FIRST=false
export PUBLIC_SOA_TTL=30
export PUBLIC_SOA_MINIMUM=30
export DNS_WAIT_MAX_ATTEMPTS=2
export DNS_WAIT_TIMEOUT_SECONDS=60

if ! run_waiter; then
    echo "❌ Expected public DNS to succeed after negative cache expires"
    cat "$stderr_file"
    exit 1
fi

actual_sleep="$(cat "$sleep_log")"
failed_before=$failed

assert_contains "$actual_sleep" "30"

public_count="$(cat "$public_count_file")"

if [[ "$public_count" -ne 2 ]]; then
    echo "❌ Expected public DNS to be queried twice"
    echo "Actual public query count: $public_count"
    exit 1
fi

if (( failed == failed_before )); then
    echo "✅ sleeps for negative cache TTL before retrying"
fi

echo
echo "→ reports tomorrow for negative cache crossing midnight"

reset_dns_scenario

printf '%s\n' 86390 > "$now_file"

export PUBLIC_NXDOMAIN=true
export PUBLIC_SOA_TTL=30
export PUBLIC_SOA_MINIMUM=30
export DNS_WAIT_TIMEOUT_SECONDS=10

if run_waiter; then
    echo "❌ Expected negative cache to outlive wait budget"
    exit 1
fi

actual="$(cat "$stderr_file")"
failed_before=$failed

assert_contains "$actual" "Please try again after 00:00 tomorrow"

if (( failed == failed_before )); then
    echo "✅ reports tomorrow for negative cache crossing midnight"
fi
