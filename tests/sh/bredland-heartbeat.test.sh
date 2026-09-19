#!/usr/bin/env bash
set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

fakebin="$tmpdir/bin"
curl_args="$tmpdir/curl-args"
secrets_file="$tmpdir/secrets.env"
template="templates/bredland/bredland-heartbeat.sh.template"
output="$tmpdir/bredland-heartbeat"

mkdir -p "$fakebin"

assert_curl_argument()
{
    local expected="$1"

    if grep -Fxq "$expected" "$curl_args"; then
        return
    fi

    echo "Expected curl argument:"
    echo "  $expected"
    echo
    echo "Actual curl arguments:"
    sed 's/^/  /' "$curl_args"
    exit 1
}

cat > "$fakebin/vcgencmd" <<'EOF'
#!/usr/bin/env bash

case "$1" in
    measure_temp)
        echo "temp=42.0'C"
        ;;
    get_throttled)
        echo "throttled=0x0"
        ;;
    *)
        exit 1
        ;;
esac
EOF
chmod +x "$fakebin/vcgencmd"

cat > "$fakebin/openssl" <<'EOF'
#!/usr/bin/env bash
echo "notAfter=Nov 23 17:52:58 2026 GMT"
EOF
chmod +x "$fakebin/openssl"

cat > "$fakebin/date" <<'EOF'
#!/usr/bin/env bash

if [ "$1" = "+%s" ]; then
    echo "1789570846"
    exit 0
fi

if [ "$1" = "-d" ]; then
    echo "1795456378"
    exit 0
fi

exit 1
EOF
chmod +x "$fakebin/date"

cat > "$fakebin/curl" <<'EOF'
#!/usr/bin/env bash
printf '%s\n' "$@" > "$BREDLAND_HEARTBEAT_CURL_ARGS"
EOF
chmod +x "$fakebin/curl"

cat > "$secrets_file" <<'EOF'
BREDLAND_NOC_HOST=bredland-test
BREDLAND_NOC_TOKEN=test-token
TELEMETRY_ENDPOINT=https://example.invalid/telemetry
EOF

BREDLAND_SECRETS_FILE="$secrets_file" \
    scripts/render-template.sh \
    "$template" \
    "$output"

chmod +x "$output"

export PATH="$fakebin:$PATH"
export BREDLAND_HEARTBEAT_CURL_ARGS="$curl_args"

"$output"
assert_curl_argument "tls_cert_remaining=5885532"
echo "✅ Bredland heartbeat posts tls_cert_remaining"

cat > "$fakebin/openssl" <<'EOF'
#!/usr/bin/env bash
exit 1
EOF

"$output"
assert_curl_argument "tls_cert_remaining=0"
echo "✅ Bredland heartbeat falls back to tls_cert_remaining=0"

cat > "$fakebin/openssl" <<'EOF'
#!/usr/bin/env bash
echo "notAfter=Nov 23 17:52:58 2026 GMT"
EOF

cat > "$fakebin/date" <<'EOF'
#!/usr/bin/env bash

if [ "$1" = "+%s" ]; then
    echo "1795456478"
    exit 0
fi

if [ "$1" = "-d" ]; then
    echo "1795456378"
    exit 0
fi

exit 1
EOF

"$output"
assert_curl_argument "tls_cert_remaining=-100"
echo "✅ Bredland heartbeat preserves negative TLS certificate remaining time"
