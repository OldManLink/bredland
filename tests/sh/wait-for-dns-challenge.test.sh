#!/usr/bin/env bash
set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

dns1_count_file="$tmpdir/dns1-count"
dns2_count_file="$tmpdir/dns2-count"
public_count_file="$tmpdir/public-count"
sleep_count_file="$tmpdir/sleep-count"

export DNS1_COUNT_FILE="$dns1_count_file"
export DNS2_COUNT_FILE="$dns2_count_file"
export PUBLIC_COUNT_FILE="$public_count_file"
export SLEEP_COUNT_FILE="$sleep_count_file"

stderr_file="$tmpdir/stderr"
fakebin="$tmpdir/bin"
mkdir -p "$fakebin"
export PATH="$fakebin:$PATH"

cat > "$fakebin/dig" <<'EOF'
#!/usr/bin/env bash

case "$*" in
    "+short NS arcanel.se")
        printf '%s\n' \
            "dns1.oderland.com." \
            "dns2.oderland.com."
        ;;

    "@dns1.oderland.com. +short TXT _brd-038-spike.arcanel.se")
        count=0

        if [[ -e "$DNS1_COUNT_FILE" ]]; then
            count="$(cat "$DNS1_COUNT_FILE")"
        fi

        count=$((count + 1))
        printf '%s\n' "$count" > "$DNS1_COUNT_FILE"

        echo '"brd-038-test"'
        ;;

    "@dns2.oderland.com. +short TXT _brd-038-spike.arcanel.se")
        if [[ "${DNS2_NEVER_VISIBLE:-false}" == true ]]; then
            exit 0
        fi

        if [[ "${DNS2_DELAYED:-false}" == true ]]; then
            count=0

            if [[ -e "$DNS2_COUNT_FILE" ]]; then
                count="$(cat "$DNS2_COUNT_FILE")"
            fi

            count=$((count + 1))
            printf '%s\n' "$count" > "$DNS2_COUNT_FILE"

            if [[ "$count" -ge 2 ]]; then
                echo '"brd-038-test"'
            fi
        else
            echo '"brd-038-test"'
        fi
        ;;

    "+short TXT _brd-038-spike.arcanel.se")
        if [[ "${PUBLIC_NEVER_VISIBLE:-false}" == true ]]; then
            exit 0
        fi

        if [[ "${PUBLIC_DELAYED:-false}" == true ]]; then
            count=0

            if [[ -e "$PUBLIC_COUNT_FILE" ]]; then
                count="$(cat "$PUBLIC_COUNT_FILE")"
            fi

            count=$((count + 1))
            printf '%s\n' "$count" > "$PUBLIC_COUNT_FILE"

            if [[ "$count" -ge 2 ]]; then
                echo '"brd-038-test"'
            fi
        else
            echo '"brd-038-test"'
        fi
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

count=0

if [[ -e "$SLEEP_COUNT_FILE" ]]; then
    count="$(cat "$SLEEP_COUNT_FILE")"
fi

count=$((count + 1))
printf '%s\n' "$count" > "$SLEEP_COUNT_FILE"

if [[ "$count" -ge 3 ]]; then
    echo "Test safety fuse: wait loop did not terminate" >&2
    exit 99
fi
EOF

chmod +x "$fakebin/sleep"

echo
echo "→ succeeds when TXT is visible on all authoritative servers and publicly"

export DNS2_DELAYED=false
export PUBLIC_DELAYED=false
export DNS2_NEVER_VISIBLE=false
export PUBLIC_NEVER_VISIBLE=false

rm -f "$dns1_count_file" "$dns2_count_file" "$public_count_file"

if ! scripts/tls/wait-for-dns-challenge.sh \
    "_brd-038-spike.arcanel.se" \
    "brd-038-test" \
    2>"$stderr_file"
then
    echo "❌ Expected DNS challenge visibility check to succeed"
    cat "$stderr_file"
    exit 1
fi

echo "✅ succeeds when TXT is visible on all authoritative servers and publicly"

echo
echo "→ waits for authoritative TXT propagation"

export DNS2_DELAYED=true
export PUBLIC_DELAYED=false
export DNS2_NEVER_VISIBLE=false
export PUBLIC_NEVER_VISIBLE=false

rm -f "$dns1_count_file" "$dns2_count_file" "$public_count_file"

if ! scripts/tls/wait-for-dns-challenge.sh \
    "_brd-038-spike.arcanel.se" \
    "brd-038-test" \
    2>"$stderr_file"
then
    echo "❌ Expected DNS challenge visibility check to retry"
    cat "$stderr_file"
    exit 1
fi

if [[ ! -e "$dns2_count_file" ]]; then
    echo "❌ Expected delayed dns2 lookup to be exercised"
    exit 1
fi

actual_count="$(cat "$dns2_count_file")"

if [[ "$actual_count" -lt 2 ]]; then
    echo "❌ Expected authoritative DNS to be queried again"
    echo "Actual query count: $actual_count"
    exit 1
fi

echo "✅ waits for authoritative TXT propagation"

echo
echo "→ waits for public TXT propagation without rechecking authoritative DNS"

export DNS2_DELAYED=false
export PUBLIC_DELAYED=true
export DNS2_NEVER_VISIBLE=false
export PUBLIC_NEVER_VISIBLE=false

rm -f "$dns1_count_file" "$dns2_count_file" "$public_count_file"

if ! scripts/tls/wait-for-dns-challenge.sh \
    "_brd-038-spike.arcanel.se" \
    "brd-038-test" \
    2>"$stderr_file"
then
    echo "❌ Expected public DNS propagation wait to succeed"
    cat "$stderr_file"
    exit 1
fi

if [[ ! -e "$public_count_file" ]]; then
    echo "❌ Expected public DNS lookup to be exercised"
    exit 1
fi

public_count="$(cat "$public_count_file")"

if [[ "$public_count" -lt 2 ]]; then
    echo "❌ Expected public DNS to be queried again"
    echo "Actual query count: $public_count"
    exit 1
fi

dns1_count="$(cat "$dns1_count_file")"

if [[ "$dns1_count" -ne 1 ]]; then
    echo "❌ Expected authoritative DNS not to be rechecked"
    echo "Actual dns1 query count: $dns1_count"
    exit 1
fi

echo "✅ waits for public TXT propagation without rechecking authoritative DNS"

echo
echo "→ fails when authoritative TXT propagation times out"

export DNS2_DELAYED=false
export DNS2_NEVER_VISIBLE=true
export PUBLIC_DELAYED=false
export PUBLIC_NEVER_VISIBLE=false
export DNS_WAIT_MAX_ATTEMPTS=2
export DNS_WAIT_SLEEP_SECONDS=1

rm -f \
    "$dns1_count_file" \
    "$dns2_count_file" \
    "$public_count_file" \
    "$sleep_count_file"

if scripts/tls/wait-for-dns-challenge.sh \
    "_brd-038-spike.arcanel.se" \
    "brd-038-test" \
    > /dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected authoritative DNS propagation timeout"
    exit 1
fi

expected_error="Timed out waiting for authoritative DNS propagation"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected explicit authoritative DNS timeout"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

echo "✅ fails when authoritative TXT propagation times out"

echo
echo "→ fails when public TXT propagation times out"

export DNS2_DELAYED=false
export DNS2_NEVER_VISIBLE=false
export PUBLIC_DELAYED=false
export PUBLIC_NEVER_VISIBLE=true
export DNS_WAIT_MAX_ATTEMPTS=2
export DNS_WAIT_SLEEP_SECONDS=1

rm -f \
    "$dns1_count_file" \
    "$dns2_count_file" \
    "$public_count_file" \
    "$sleep_count_file"

if scripts/tls/wait-for-dns-challenge.sh \
    "_brd-038-spike.arcanel.se" \
    "brd-038-test" \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected public DNS propagation timeout"
    exit 1
fi

expected_error="Timed out waiting for public DNS propagation"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected explicit public DNS timeout"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

echo "✅ fails when public TXT propagation times out"
