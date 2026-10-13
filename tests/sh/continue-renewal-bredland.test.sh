#!/usr/bin/env bash
set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

run_root="$tmpdir/run"
run_id="brd-038-test-run"
run_dir="$run_root/$run_id"
stderr_file="$tmpdir/stderr"

mkdir -p "$run_dir"

export BREDLAND_TLS_RUN_ROOT="$run_root"

touch "$run_dir/challenge-ready"

echo
echo "→ continues renewal waiting at challenge"

if ! bash templates/bredland/continue-renewal.sh.template \
    "$run_id" \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected continue-renewal to succeed"
    cat "$stderr_file"
    exit 1
fi

if [[ ! -f "$run_dir/continue" ]]; then
    echo "❌ Expected continue marker"
    exit 1
fi

echo "✅ continues renewal waiting at challenge"

echo
echo "→ rejects renewal not waiting at challenge"

rm -f \
    "$run_dir/challenge-ready" \
    "$run_dir/continue"

: > "$stderr_file"

if bash templates/bredland/continue-renewal.sh.template \
    "$run_id" \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected continue-renewal to fail"
    exit 1
fi

expected_error="Renewal is not waiting for DNS challenge"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected challenge-wait error"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

if [[ -e "$run_dir/continue" ]]; then
    echo "❌ Expected no continue marker"
    exit 1
fi

echo "✅ rejects renewal not waiting at challenge"
