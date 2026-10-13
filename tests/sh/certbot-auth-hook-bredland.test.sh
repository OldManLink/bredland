#!/usr/bin/env bash
set -euo pipefail

tmpdir="$(mktemp -d)"
hook_pid=""

cleanup()
{
    if [[ -n "$hook_pid" ]]; then
        kill "$hook_pid" 2>/dev/null || true
        wait "$hook_pid" 2>/dev/null || true
    fi

    rm -rf "$tmpdir"
}

trap cleanup EXIT

run_dir="$tmpdir/brd-038-test-run"
mkdir -p "$run_dir"

export CERTBOT_DOMAIN="nocster-73.arcanel.se"
export CERTBOT_VALIDATION="test-validation-value"

echo
echo "→ publishes challenge request and waits for continue"

bash templates/bredland/certbot-auth-hook.sh.template \
    "$run_dir" \
    >/dev/null \
    2>"$tmpdir/stderr" &

hook_pid=$!

ready=false

for attempt in 1 2 3 4 5 6 7 8 9 10; do
    if [[ -f "$run_dir/challenge-ready" ]]; then
        ready=true
        break
    fi

    sleep 0.1
done

if ! $ready; then
    echo "❌ Expected challenge-ready marker"
    cat "$tmpdir/stderr"
    exit 1
fi

expected_request="$(
    cat <<'EOF'
identifier=nocster-73.arcanel.se
record=_acme-challenge.nocster-73.arcanel.se
validation=test-validation-value
EOF
)"

actual_request="$(cat "$run_dir/request")"

if [[ "$actual_request" != "$expected_request" ]]; then
    echo "❌ Expected correct challenge request"
    echo
    echo "Expected:"
    printf '%s\n' "$expected_request"
    echo
    echo "Actual:"
    printf '%s\n' "$actual_request"
    exit 1
fi

if ! kill -0 "$hook_pid" 2>/dev/null; then
    echo "❌ Expected auth hook to wait for continue"
    exit 1
fi

touch "$run_dir/continue"

if ! wait "$hook_pid"; then
    echo "❌ Expected auth hook to continue successfully"
    cat "$tmpdir/stderr"
    exit 1
fi

hook_pid=""

echo "✅ publishes challenge request and waits for continue"

echo
echo "→ rejects missing CERTBOT_VALIDATION"

unset CERTBOT_VALIDATION

if bash templates/bredland/certbot-auth-hook.sh.template \
    "$run_dir" \
    >/dev/null \
    2>"$tmpdir/stderr"
then
    echo "❌ Expected missing validation to fail"
    exit 1
fi

expected_error="Missing CERTBOT_VALIDATION"

if ! grep -Fq -- "$expected_error" "$tmpdir/stderr"; then
    echo "❌ Expected missing validation error"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$tmpdir/stderr"
    exit 1
fi

echo "✅ rejects missing CERTBOT_VALIDATION"

echo
echo "→ exits nonzero when renewal is cancelled"

rm -f \
    "$run_dir/continue" \
    "$run_dir/cancel" \
    "$run_dir/request" \
    "$run_dir/challenge-ready"

stderr="$tmpdir/cancel-stderr"

set +e

CERTBOT_DOMAIN="nocster-73.arcanel.se" \
CERTBOT_VALIDATION="cancel-test-validation" \
    bash templates/bredland/certbot-auth-hook.sh.template \
        "$run_dir" \
        > /dev/null \
        2> "$stderr" &

hook_pid=$!

while [[ ! -f "$run_dir/challenge-ready" ]]; do
    sleep 0.1
done

touch "$run_dir/cancel"

wait "$hook_pid"
rc=$?

set -e

if [[ "$rc" -eq 0 ]]; then
    echo "❌ Expected cancelled auth hook to fail"
    exit 1
fi

echo "✅ exits nonzero when renewal is cancelled"
