#!/usr/bin/env bash
set -euo pipefail

source scripts/lib/deploy.sh

output="$(pass "Everything worked")"

if [[ "$output" != "✅ Everything worked" ]]; then
    echo "Unexpected pass output: $output" >&2
    exit 1
fi

set +e
output="$(fail "Something broke" 2>&1)"
rc=$?
set -e

if (( rc == 0 )); then
    echo "fail should return non-zero" >&2
    exit 1
fi

if [[ "$output" != "❌ Something broke" ]]; then
    echo "Unexpected fail output: $output" >&2
    exit 1
fi

output="$(run_step "Successful step" true)"

if [[ "$output" != $'→ Successful step\n✅ Successful step' ]]; then
    echo "Unexpected successful run_step output:" >&2
    echo "$output" >&2
    exit 1
fi

set +e
output="$(run_step "Failed step" false 2>&1)"
rc=$?
set -e

if (( rc == 0 )); then
    echo "Failed run_step should return non-zero" >&2
    exit 1
fi

if [[ "$output" != $'→ Failed step\n❌ Failed step' ]]; then
    echo "Unexpected failed run_step output:" >&2
    echo "$output" >&2
    exit 1
fi

echo "✅ Deployment helpers behave correctly"
