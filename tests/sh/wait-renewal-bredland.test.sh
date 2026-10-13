#!/usr/bin/env bash
set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

run_root="$tmpdir/run"
run_id="brd-038-test-run"
run_dir="$run_root/$run_id"
stderr_file="$tmpdir/stderr"
wait_pid=""

mkdir -p "$run_dir"

export BREDLAND_TLS_RUN_ROOT="$run_root"

cleanup()
{
    if [[ -n "$wait_pid" ]]; then
        kill "$wait_pid" 2>/dev/null || true
        wait "$wait_pid" 2>/dev/null || true
    fi

    rm -rf "$tmpdir"
}

trap cleanup EXIT

echo
echo "→ waits for successful renewal completion"

(
    sleep 0.2

    printf '%s\n' "issued-fullchain" \
        > "$run_dir/fullchain.pem"

    printf '%s\n' "issued-privkey" \
        > "$run_dir/privkey.pem"

    printf '%s\n' "succeeded" \
        > "$run_dir/status"
) &

state_writer_pid=$!

if ! bash templates/bredland/wait-renewal.sh.template \
    "$run_id" \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected wait-renewal to succeed"
    cat "$stderr_file"
    wait "$state_writer_pid" 2>/dev/null || true
    exit 1
fi

wait "$state_writer_pid"

echo "✅ waits for successful renewal completion"

echo
echo "→ surfaces failed renewal"

rm -f \
    "$run_dir/status" \
    "$run_dir/error" \
    "$run_dir/fullchain.pem" \
    "$run_dir/privkey.pem"

printf '%s\n' "simulated renewal failure" \
    > "$run_dir/error"

printf '%s\n' "failed" \
    > "$run_dir/status"

: > "$stderr_file"

if bash templates/bredland/wait-renewal.sh.template \
    "$run_id" \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected wait-renewal to fail"
    exit 1
fi

expected_error="simulated renewal failure"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected renewal failure to be surfaced"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

echo "✅ surfaces failed renewal"

echo
echo "→ rejects successful renewal with incomplete artifacts"

rm -f \
    "$run_dir/status" \
    "$run_dir/error" \
    "$run_dir/fullchain.pem" \
    "$run_dir/privkey.pem"

printf '%s\n' "issued-fullchain" \
    > "$run_dir/fullchain.pem"

printf '%s\n' "succeeded" \
    > "$run_dir/status"

: > "$stderr_file"

if bash templates/bredland/wait-renewal.sh.template \
    "$run_id" \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected incomplete successful renewal to fail"
    exit 1
fi

expected_error="Renewal succeeded without privkey.pem"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected missing private-key error"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

echo "✅ rejects successful renewal with incomplete artifacts"
