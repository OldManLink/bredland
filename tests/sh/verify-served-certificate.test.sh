#!/usr/bin/env bash
set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

fakebin="$tmpdir/bin"
openssl_log="$tmpdir/openssl-log"
stderr_file="$tmpdir/stderr"
run_dir="$tmpdir/brd-038-test-run"

mkdir -p "$fakebin" "$run_dir"

export PATH="$fakebin:$PATH"
export OPENSSL_LOG="$openssl_log"

cp tests/fixtures/tls/fullchain.pem \
    "$run_dir/fullchain.pem"

cat > "$fakebin/openssl" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" >> "$OPENSSL_LOG"

exit 0
EOF

chmod +x "$fakebin/openssl"

echo
echo "→ connects to trusted discovery TLS endpoint"

if ! scripts/tls/verify-served-certificate.sh \
    "$run_dir" \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected served certificate verification to succeed"
    cat "$stderr_file"
    exit 1
fi

expected="s_client -connect nocster-73.arcanel.se:8081 -servername nocster-73.arcanel.se"

actual="$(sed -n '1p' "$openssl_log")"

if [[ "$actual" != "$expected" ]]; then
    echo "❌ Expected trusted discovery TLS connection"
    echo
    echo "Expected:"
    echo "  $expected"
    echo
    echo "Actual:"
    echo "  $actual"
    exit 1
fi

echo "✅ connects to trusted discovery TLS endpoint"

echo
echo "→ verifies served certificate matches issued certificate"

served_cert="$tmpdir/served-cert.pem"

cp tests/fixtures/tls/leaf-cert.pem \
    "$served_cert"

cat > "$fakebin/openssl" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" >> "$OPENSSL_LOG"

if [[ "$1" == "s_client" ]]; then
    cat "$SERVED_CERT"
    exit 0
fi

if [[ "$1" == "x509" ]]; then
    cat >/dev/null
    echo "SHA256 Fingerprint=test-fingerprint"
    exit 0
fi

exit 1
EOF

chmod +x "$fakebin/openssl"

export SERVED_CERT="$served_cert"

: > "$openssl_log"
: > "$stderr_file"

if ! scripts/tls/verify-served-certificate.sh \
    "$run_dir" \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected matching served certificate to be accepted"
    cat "$stderr_file"
    exit 1
fi

expected_x509_calls=2
actual_x509_calls="$(
    grep -c '^x509 ' "$openssl_log"
)"

if [[ "$actual_x509_calls" -ne "$expected_x509_calls" ]]; then
    echo "❌ Expected served and issued certificates to be fingerprinted"
    echo
    echo "Expected x509 calls:"
    echo "  $expected_x509_calls"
    echo
    echo "Actual:"
    sed 's/^/  /' "$openssl_log"
    exit 1
fi

echo "✅ verifies served certificate matches issued certificate"

echo
echo "→ rejects mismatched served certificate"

cat > "$fakebin/openssl" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" >> "$OPENSSL_LOG"

if [[ "$1" == "s_client" ]]; then
    cat "$SERVED_CERT"
    exit 0
fi

if [[ "$1" == "x509" ]]; then
    if [[ "$*" == *"-in "* ]]; then
        echo "SHA256 Fingerprint=issued"
    else
        cat >/dev/null
        echo "SHA256 Fingerprint=served"
    fi
    exit 0
fi

exit 1
EOF

chmod +x "$fakebin/openssl"

: > "$openssl_log"
: > "$stderr_file"

if scripts/tls/verify-served-certificate.sh \
    "$run_dir" \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected mismatched served certificate to be rejected"
    exit 1
fi

expected_error="Served certificate does not match issued certificate"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected certificate mismatch error"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

echo "✅ rejects mismatched served certificate"

echo
echo "→ reports TLS connection failure"

cat > "$fakebin/openssl" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" >> "$OPENSSL_LOG"

if [[ "$1" == "s_client" ]]; then
    echo "connect: Connection refused" >&2
    exit 1
fi

exit 1
EOF

chmod +x "$fakebin/openssl"

: > "$openssl_log"
: > "$stderr_file"

if scripts/tls/verify-served-certificate.sh \
    "$run_dir" \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected TLS connection failure"
    exit 1
fi

expected_error="Failed to connect to trusted discovery TLS endpoint"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected TLS connection failure error"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

echo "✅ reports TLS connection failure"

echo
echo "→ retries while TLS endpoint is starting"

attempt_file="$tmpdir/attempts"
echo 0 > "$attempt_file"

export ATTEMPT_FILE="$attempt_file"

cat > "$fakebin/openssl" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" >> "$OPENSSL_LOG"

if [[ "$1" == "s_client" ]]; then
    attempt="$(cat "$ATTEMPT_FILE")"
    attempt=$((attempt + 1))
    echo "$attempt" > "$ATTEMPT_FILE"

    if [[ "$attempt" -lt 3 ]]; then
        exit 1
    fi

    cat "$SERVED_CERT"
    exit 0
fi

if [[ "$1" == "x509" ]]; then
    cat >/dev/null
    echo "SHA256 Fingerprint=test-fingerprint"
    exit 0
fi

exit 1
EOF

chmod +x "$fakebin/openssl"

: > "$openssl_log"
: > "$stderr_file"

if ! scripts/tls/verify-served-certificate.sh \
    "$run_dir" \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected TLS verification to tolerate startup delay"
    cat "$stderr_file"
    exit 1
fi

actual_attempts="$(cat "$attempt_file")"

if [[ "$actual_attempts" -ne 3 ]]; then
    echo "❌ Expected three TLS connection attempts"
    echo "Expected: 3"
    echo "Actual:   $actual_attempts"
    exit 1
fi

echo "✅ retries while TLS endpoint is starting"
