#!/usr/bin/env bash
set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

fakebin="$tmpdir/bin"
run_root="$tmpdir/run"
worker_log="$tmpdir/worker-log"
stderr_file="$tmpdir/stderr"

mkdir -p "$fakebin" "$run_root"

export PATH="$fakebin:$PATH"
export WORKER_LOG="$worker_log"
export BREDLAND_TLS_RUN_ROOT="$run_root"

cat > "$fakebin/certbot-renewal-worker" <<'EOF'
#!/usr/bin/env bash

run_dir="$1"

printf '%s\n' "$run_dir" > "$WORKER_LOG"

touch "$run_dir/challenge-ready"

exit 0
EOF

chmod +x "$fakebin/certbot-renewal-worker"

echo
echo "→ starts renewal worker and waits for challenge readiness"

if ! bash templates/bredland/start-renewal.sh.template \
    "brd-038-test-run" \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected start-renewal to succeed"
    cat "$stderr_file"
    exit 1
fi

expected_run_dir="$run_root/brd-038-test-run"

if [[ ! -d "$expected_run_dir" ]]; then
    echo "❌ Expected run directory to be created"
    exit 1
fi

actual_worker_run_dir="$(cat "$worker_log")"

if [[ "$actual_worker_run_dir" != "$expected_run_dir" ]]; then
    echo "❌ Expected worker to receive correct run directory"
    echo
    echo "Expected:"
    echo "  $expected_run_dir"
    echo
    echo "Actual:"
    echo "  $actual_worker_run_dir"
    exit 1
fi

if [[ ! -f "$expected_run_dir/challenge-ready" ]]; then
    echo "❌ Expected challenge-ready marker before start-renewal returned"
    exit 1
fi

echo "✅ starts renewal worker and waits for challenge readiness"

echo
echo "→ passes test-cert mode to renewal worker"

cat > "$fakebin/certbot-renewal-worker" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" > "$WORKER_LOG"

run_dir="${@: -1}"

touch "$run_dir/challenge-ready"

exit 0
EOF

chmod +x "$fakebin/certbot-renewal-worker"

rm -rf "$run_root/brd-038-test-run"
: > "$worker_log"
: > "$stderr_file"

if ! bash templates/bredland/start-renewal.sh.template \
    --test-cert \
    "brd-038-test-run" \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected test-cert start-renewal to succeed"
    cat "$stderr_file"
    exit 1
fi

expected="--test-cert $run_root/brd-038-test-run"
actual="$(cat "$worker_log")"

if [[ "$actual" != "$expected" ]]; then
    echo "❌ Expected test-cert flag to be passed to worker"
    echo
    echo "Expected:"
    echo "  $expected"
    echo
    echo "Actual:"
    echo "  $actual"
    exit 1
fi

echo "✅ passes test-cert mode to renewal worker"

echo
echo "→ fails if renewal worker fails before challenge readiness"

cat > "$fakebin/certbot-renewal-worker" <<'EOF'
#!/usr/bin/env bash

run_dir="${@: -1}"

printf '%s\n' "simulated worker failure" \
    > "$run_dir/error"

printf '%s\n' "failed" \
    > "$run_dir/status"

exit 1
EOF

chmod +x "$fakebin/certbot-renewal-worker"

rm -rf "$run_root/brd-038-test-run"
: > "$stderr_file"

if bash templates/bredland/start-renewal.sh.template \
    "brd-038-test-run" \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected start-renewal to fail"
    exit 1
fi

expected_error="simulated worker failure"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected worker failure to be surfaced"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

echo "✅ fails if renewal worker fails before challenge readiness"

echo
echo "→ fails if worker exits before challenge readiness"

cat > "$fakebin/certbot-renewal-worker" <<'EOF'
#!/usr/bin/env bash
exit 42
EOF

chmod +x "$fakebin/certbot-renewal-worker"

rm -rf "$run_root/brd-038-test-run"
: > "$stderr_file"

set +e

BREDLAND_TLS_RUN_ROOT="$run_root" \
    bash templates/bredland/start-renewal.sh.template \
        "brd-038-test-run" \
        >/dev/null \
        2>"$tmpdir/stderr"

rc=$?

set -e

if [[ "$rc" -eq 0 ]]; then
    echo "❌ Expected start-renewal to fail"
    exit 1
fi

actual="$(cat "$tmpdir/stderr")"

if [[ "$actual" != "Renewal worker exited before challenge readiness" ]]; then
    echo "❌ Expected explicit worker-exit error"
    echo
    echo "Actual:"
    echo "  $actual"
    exit 1
fi

echo "✅ fails if worker exits before challenge readiness"
