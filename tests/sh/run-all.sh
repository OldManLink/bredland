#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$repo_root"

test_results_dir="${TEST_RESULTS_DIR:-build/test-results}"
failed_suites_file="$test_results_dir/failed-suites"

passed=0
skipped=0
failed=0
crashed=0

print_failures_only=false
test_scripts=()
reading_tests=false

for arg in "$@"; do
    if [[ "$arg" == "--" ]]; then
        reading_tests=true
        continue
    fi

    if $reading_tests; then
        test_scripts+=("$arg")
    else
        case "$arg" in
            --failures-only)
                print_failures_only=true
                ;;
        esac
    fi
done

for test_script in "${test_scripts[@]}"; do
    suite="${test_script#tests/sh/}"
    suite="${suite%.test.sh}"
    suite="sh:$suite"

    name="$(basename "$test_script" .test.sh)"
    output_file="$(mktemp)"

    set +e
    "$test_script" >"$output_file" 2>&1
    rc=$?
    set -e

    case "$rc" in
        0)
            if ! $print_failures_only; then
                echo "==> $name"
                cat "$output_file"
                echo "✅ $name"
                echo
            fi
            ((++passed))
            ;;

        77)
            if ! $print_failures_only; then
                echo "==> $name"
                cat "$output_file"
                echo "⚠️ $name"
                echo
            fi
            ((++skipped))
            ;;

        1)
            echo "==> $name"
            cat "$output_file"
            echo "❌ $name"
            echo
            echo "$suite" >> "$failed_suites_file"
            ((++failed))
            ;;

        *)
            echo "==> $name"
            cat "$output_file"
            echo "❌💥 $name (exit $rc)"
            echo
            echo "$suite" >> "$failed_suites_file"
            ((++crashed))
            ;;
    esac

    rm -f "$output_file"
done

total=$((passed + skipped + failed + crashed))
echo "Suite summary: $total test suites run, $skipped skipped, $passed passed, $failed failed, $crashed crashed"

if (( failed || crashed )); then
    exit 1
fi