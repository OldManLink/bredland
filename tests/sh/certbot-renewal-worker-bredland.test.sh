#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=tests/sh/lib/testlib.sh
source "$(dirname "$0")/lib/testlib.sh"

passed=0
failed=0

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

fakebin="$tmpdir/bin"
run_dir="$tmpdir/brd-038-test-run"
certbot_log="$tmpdir/certbot-log"
certbot_live_dir="$tmpdir/certbot-live"

mkdir -p \
    "$fakebin" \
    "$run_dir" \
    "$certbot_live_dir"

export RUN_DIR="$run_dir"
export PATH="$fakebin:$PATH"
export CERTBOT_LOG="$certbot_log"
export CERTBOT_LIVE_DIR="$certbot_live_dir"
export CERTBOT_EMAIL="test@example.com"

sudo_log="$tmpdir/sudo-log"
export SUDO_LOG="$sudo_log"

cat > "$fakebin/sudo" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" >> "$SUDO_LOG"
"$@"
EOF

chmod +x "$fakebin/sudo"

write_fake_certbot()
{
    cat > "$fakebin/certbot"

    chmod +x "$fakebin/certbot"
}

write_issued_certificate_pair()
{
    local live_dir="${1:?Missing live directory}"

    mkdir -p "$live_dir"

    printf '%s\n' "issued-fullchain" \
        > "$live_dir/fullchain.pem"

    printf '%s\n' "issued-privkey" \
        > "$live_dir/privkey.pem"
}

write_issued_certificate_pair "$certbot_live_dir"

write_fake_certbot <<'EOF'
#!/usr/bin/env bash

cat "$RUN_DIR/status" > "$CERTBOT_LOG"
exit 0
EOF

echo
echo "→ marks renewal running before invoking certbot"

if ! bash templates/bredland/certbot-renewal-worker.sh.template \
    "$run_dir" \
    >/dev/null \
    2>"$tmpdir/stderr"
then
    echo "❌ Expected renewal worker to succeed"
    cat "$tmpdir/stderr"
    exit 1
fi

expected_status="running"
actual_status="$(cat "$certbot_log")"

if [[ "$actual_status" != "$expected_status" ]]; then
    echo "❌ Expected running status"
    echo
    echo "Expected:"
    echo "  $expected_status"
    echo
    echo "Actual:"
    echo "  $actual_status"
    exit 1
fi

echo "✅ marks renewal running before invoking certbot"

echo
echo "→ marks renewal succeeded after certbot completes"

write_fake_certbot <<'EOF'
#!/usr/bin/env bash

exit 0
EOF

rm -f "$run_dir/status"
: > "$tmpdir/stderr"

if ! bash templates/bredland/certbot-renewal-worker.sh.template \
    "$run_dir" \
    >/dev/null \
    2>"$tmpdir/stderr"
then
    echo "❌ Expected renewal worker to succeed"
    cat "$tmpdir/stderr"
    exit 1
fi

expected_status="succeeded"
actual_status="$(cat "$run_dir/status")"

if [[ "$actual_status" != "$expected_status" ]]; then
    echo "❌ Expected succeeded status"
    echo
    echo "Expected:"
    echo "  $expected_status"
    echo
    echo "Actual:"
    echo "  $actual_status"
    exit 1
fi

echo "✅ marks renewal succeeded after certbot completes"

echo
echo "→ marks renewal failed when certbot fails"

write_fake_certbot <<'EOF'
#!/usr/bin/env bash

echo "simulated certbot failure" >&2
exit 23
EOF

rm -f "$run_dir/status" "$run_dir/error"
: > "$tmpdir/stderr"

if bash templates/bredland/certbot-renewal-worker.sh.template \
    "$run_dir" \
    >/dev/null \
    2>"$tmpdir/stderr"
then
    echo "❌ Expected renewal worker to fail"
    exit 1
fi

expected_status="failed"
actual_status="$(cat "$run_dir/status")"

if [[ "$actual_status" != "$expected_status" ]]; then
    echo "❌ Expected failed status"
    echo
    echo "Expected:"
    echo "  $expected_status"
    echo
    echo "Actual:"
    echo "  $actual_status"
    exit 1
fi

expected_error="simulated certbot failure"

if ! grep -Fxq -- "$expected_error" "$run_dir/error"; then
    echo "❌ Expected certbot failure to be recorded"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$run_dir/error"
    exit 1
fi

echo "✅ marks renewal failed when certbot fails"

echo
echo "→ invokes production certbot with renewal arguments"

write_fake_certbot <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" > "$CERTBOT_LOG"
exit 0
EOF

rm -f "$run_dir/status" "$run_dir/error"
: > "$certbot_log"
: > "$tmpdir/stderr"
: > "$sudo_log"

if ! bash templates/bredland/certbot-renewal-worker.sh.template \
    "$run_dir" \
    >/dev/null \
    2>"$tmpdir/stderr"
then
    echo "❌ Expected renewal worker to succeed"
    cat "$tmpdir/stderr"
    exit 1
fi

if ! grep -Fq "certbot certonly" "$sudo_log"; then
    echo "❌ Expected production Certbot invocation through sudo"
    echo
    echo "Actual sudo call:"
    sed 's/^/  /' "$sudo_log"
    exit 1
fi

actual="$(cat "$certbot_log")"
failed_before=$failed

assert_contains "$actual" "-d nocster-73.arcanel.se"
assert_contains "$actual" "--manual-auth-hook"
assert_contains "$actual" "certbot-auth-hook"
assert_contains "$actual" "--non-interactive"
assert_contains "$actual" "--agree-tos"
assert_contains "$actual" "--no-eff-email"
assert_contains "$actual" "--email test@example.com"

if (( failed == failed_before )); then
    echo "✅ invokes production certbot with renewal arguments"
fi

echo
echo "→ invokes certbot test-cert mode with isolated state"

test_cert_config_dir="$run_dir/certbot-config"
test_cert_live_dir="$test_cert_config_dir/live/nocster-73.arcanel.se"

write_issued_certificate_pair "$test_cert_live_dir"

write_fake_certbot <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" > "$CERTBOT_LOG"
exit 0
EOF

rm -f "$run_dir/status" "$run_dir/error"
: > "$certbot_log"
: > "$tmpdir/stderr"
: > "$sudo_log"

if ! env -u CERTBOT_TEST_CERT_CONFIG_DIR \
    bash templates/bredland/certbot-renewal-worker.sh.template \
        --test-cert \
        "$run_dir" \
        >/dev/null \
        2>"$tmpdir/stderr"
then
    echo "❌ Expected test-cert renewal worker to succeed"
    cat "$tmpdir/stderr"
    exit 1
fi

if [[ -s "$sudo_log" ]]; then
    echo "❌ Expected test-cert renewal to avoid sudo"
    echo
    echo "Actual sudo calls:"
    sed 's/^/  /' "$sudo_log"
    exit 1
fi

actual="$(cat "$certbot_log")"
failed_before=$failed

assert_contains "$actual" "--test-cert"
assert_contains "$actual" "--config-dir $test_cert_config_dir"
assert_contains "$actual" "--work-dir $run_dir/certbot-work"
assert_contains "$actual" "--logs-dir $run_dir/certbot-logs"
assert_contains "$actual" "--cert-name nocster-73.arcanel.se"
assert_contains "$actual" "--non-interactive"
assert_contains "$actual" "--agree-tos"
assert_contains "$actual" "--no-eff-email"
assert_contains "$actual" "--email test@example.com"

if (( failed == failed_before )); then
    echo "✅ invokes certbot test-cert mode with isolated state"
fi

echo
echo "→ copies issued certificate artifacts into run directory"

write_fake_certbot <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "issued-fullchain" \
    > "$CERTBOT_LIVE_DIR/fullchain.pem"

printf '%s\n' "issued-privkey" \
    > "$CERTBOT_LIVE_DIR/privkey.pem"

exit 0
EOF

rm -f \
    "$run_dir/status" \
    "$run_dir/error" \
    "$run_dir/fullchain.pem" \
    "$run_dir/privkey.pem"

: > "$tmpdir/stderr"

if ! bash templates/bredland/certbot-renewal-worker.sh.template \
    "$run_dir" \
    >/dev/null \
    2>"$tmpdir/stderr"
then
    echo "❌ Expected renewal worker to succeed"
    cat "$tmpdir/stderr"
    exit 1
fi

if ! grep -Fq \
    "cp $certbot_live_dir/fullchain.pem $run_dir/fullchain.pem" \
    "$sudo_log"
then
    echo "❌ Expected production fullchain copy through sudo"
    echo
    echo "Actual sudo calls:"
    sed 's/^/  /' "$sudo_log"
    exit 1
fi

expected_owner="$(id -un):$(id -gn)"

if ! grep -Fq \
    "chown $expected_owner $run_dir/fullchain.pem $run_dir/privkey.pem" \
    "$sudo_log"
then
    echo "❌ Expected production certificate handoff ownership through sudo"
    echo
    echo "Actual sudo calls:"
    sed 's/^/  /' "$sudo_log"
    exit 1
fi

fullchain_mode="$(stat -c '%a' "$run_dir/fullchain.pem")"
privkey_mode="$(stat -c '%a' "$run_dir/privkey.pem")"

if [[ "$fullchain_mode" != "600" || "$privkey_mode" != "600" ]]; then
    echo "❌ Expected production certificate handoff files to be mode 600"
    echo
    echo "Actual modes:"
    echo "  fullchain.pem: $fullchain_mode"
    echo "  privkey.pem:   $privkey_mode"
    exit 1
fi

if [[ "$(cat "$run_dir/fullchain.pem")" != "issued-fullchain" ]]; then
    echo "❌ Expected issued fullchain in run directory"
    exit 1
fi

if [[ "$(cat "$run_dir/privkey.pem")" != "issued-privkey" ]]; then
    echo "❌ Expected issued private key in run directory"
    exit 1
fi

echo "✅ copies issued certificate artifacts into run directory"

echo
echo "→ does not succeed if issued certificate artifacts are incomplete"

rm -f \
    "$run_dir/status" \
    "$run_dir/error" \
    "$run_dir/fullchain.pem" \
    "$run_dir/privkey.pem"

printf '%s\n' "issued-fullchain" \
    > "$certbot_live_dir/fullchain.pem"

rm -f "$certbot_live_dir/privkey.pem"

write_fake_certbot <<'EOF'
#!/usr/bin/env bash

exit 0
EOF

: > "$tmpdir/stderr"

if bash templates/bredland/certbot-renewal-worker.sh.template \
    "$run_dir" \
    >/dev/null \
    2>"$tmpdir/stderr"
then
    echo "❌ Expected incomplete issued artifacts to fail"
    exit 1
fi

expected_status="failed"
actual_status="$(cat "$run_dir/status")"

if [[ "$actual_status" != "$expected_status" ]]; then
    echo "❌ Expected failed status"
    echo
    echo "Expected:"
    echo "  $expected_status"
    echo
    echo "Actual:"
    echo "  $actual_status"
    exit 1
fi

echo "✅ does not succeed if issued certificate artifacts are incomplete"

echo
echo "→ removes partial issued artifacts after handoff failure"

rm -f \
    "$run_dir/status" \
    "$run_dir/error" \
    "$run_dir/fullchain.pem" \
    "$run_dir/privkey.pem"

printf '%s\n' "issued-fullchain" \
    > "$certbot_live_dir/fullchain.pem"

rm -f "$certbot_live_dir/privkey.pem"

write_fake_certbot <<'EOF'
#!/usr/bin/env bash

exit 0
EOF

: > "$tmpdir/stderr"

if bash templates/bredland/certbot-renewal-worker.sh.template \
    "$run_dir" \
    >/dev/null \
    2>"$tmpdir/stderr"
then
    echo "❌ Expected incomplete artifact handoff to fail"
    exit 1
fi

if [[ -e "$run_dir/fullchain.pem" ]]; then
    echo "❌ Expected partial fullchain artifact to be removed"
    exit 1
fi

if [[ -e "$run_dir/privkey.pem" ]]; then
    echo "❌ Expected partial private-key artifact to be removed"
    exit 1
fi

echo "✅ removes partial issued artifacts after handoff failure"

echo
echo "→ reads issued certificate from test-cert config directory"

test_cert_config_dir="$tmpdir/test-cert-config"
test_cert_live_dir="$test_cert_config_dir/live/nocster-73.arcanel.se"

rm -rf "$test_cert_config_dir"

rm -f \
    "$run_dir/fullchain.pem" \
    "$run_dir/privkey.pem" \
    "$run_dir/status" \
    "$run_dir/error"

write_fake_certbot <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

mkdir -p "$EXPECTED_TEST_CERT_LIVE_DIR"

printf '%s\n' "test-cert-fullchain" \
    > "$EXPECTED_TEST_CERT_LIVE_DIR/fullchain.pem"

printf '%s\n' "test-cert-privkey" \
    > "$EXPECTED_TEST_CERT_LIVE_DIR/privkey.pem"

exit 0
EOF

export EXPECTED_TEST_CERT_LIVE_DIR="$test_cert_live_dir"

if ! env -u CERTBOT_LIVE_DIR \
    CERTBOT_EMAIL="test@example.com" \
    CERTBOT_TEST_CERT_CONFIG_DIR="$test_cert_config_dir" \
    bash templates/bredland/certbot-renewal-worker.sh.template \
        --test-cert \
        "$run_dir" \
        >/dev/null \
        2>"$tmpdir/stderr"
then
    echo "❌ Expected test-cert renewal worker to succeed"
    cat "$tmpdir/stderr"
    exit 1
fi

actual="$(cat "$run_dir/fullchain.pem")"

if [[ "$actual" != "test-cert-fullchain" ]]; then
    echo "❌ Expected test-cert fullchain to be copied into run directory"
    echo
    echo "Actual:"
    echo "  $actual"
    exit 1
fi

actual="$(cat "$run_dir/privkey.pem")"

if [[ "$actual" != "test-cert-privkey" ]]; then
    echo "❌ Expected test-cert private key to be copied into run directory"
    echo
    echo "Actual:"
    echo "  $actual"
    exit 1
fi

echo "✅ reads issued certificate from test-cert config directory"
