#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=tests/sh/lib/testlib.sh
source "$(dirname "$0")/lib/testlib.sh"

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

run_root="$tmpdir/run-root"
run_id="brd-038-cancel-test"
run_dir="$run_root/$run_id"

mkdir -p "$run_dir"

echo
echo "→ creates cancel marker for existing run"

BREDLAND_TLS_RUN_ROOT="$run_root" \
    bash templates/bredland/cancel-renewal.sh.template \
        "$run_id"

if [[ ! -f "$run_dir/cancel" ]]; then
    echo "❌ Expected cancel marker to be created"
    exit 1
fi

echo "✅ creates cancel marker for existing run"

echo
echo "→ rejects missing run"

missing_run_id="brd-038-missing-run"

set +e

BREDLAND_TLS_RUN_ROOT="$run_root" \
    bash templates/bredland/cancel-renewal.sh.template \
        "$missing_run_id" \
        >/dev/null \
        2>"$tmpdir/stderr"

rc=$?

set -e

if [[ "$rc" -eq 0 ]]; then
    echo "❌ Expected missing run to be rejected"
    exit 1
fi

if [[ -e "$run_root/$missing_run_id" ]]; then
    echo "❌ Missing run directory was created"
    exit 1
fi

actual="$(cat "$tmpdir/stderr")"

if [[ "$actual" != "Renewal run does not exist: $missing_run_id" ]]; then
    echo "❌ Expected explicit missing-run error"
    echo
    echo "Actual:"
    echo "  $actual"
    exit 1
fi

echo "✅ rejects missing run"
