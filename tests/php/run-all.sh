#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$repo_root"

shopt -s nullglob
test_scripts=(tests/php/*.test.php)

if (( ${#test_scripts[@]} == 0 )); then
    echo "==> no PHP tests yet"
    exit 0
fi

passed=0
skipped=0
failed=0
crashed=0

for test in "${test_scripts[@]}"; do
    name="$(basename "$test" .test.php)"    # or .test.php
    echo "==> $name"

    set +e
    php "$test"
    rc=$?
    set -e

    case "$rc" in
        0)  echo "✅ $name"; ((++passed)) ;;
        77) echo "⚠️ $name"; ((++skipped)) ;;
        1)  echo "❌ $name"; ((++failed)) ;;
        *)  echo "💥 $name (exit $rc)"; ((++crashed)) ;;
    esac

    echo
done

total=$((passed + skipped + failed + crashed))
echo "Suite summary: $total tests run, $skipped skipped, $passed passed, $failed failed, $crashed crashed"

if (( failed || crashed )); then
    exit 1
fi