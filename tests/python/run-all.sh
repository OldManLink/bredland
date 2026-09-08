#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$repo_root"

test_results_dir="${TEST_RESULTS_DIR:-build/test-results}"
failed_suites_file="$test_results_dir/failed-suites"

test_scripts=()
reading_tests=false
print_failures_only=false

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

if (( ${#test_scripts[@]} == 0 )); then
    echo "==> no Python tests yet"
    exit 0
fi

passed=0
failed=0
crashed=0

for test in "${test_scripts[@]}"; do
    name="$(basename "$test" .test.py)"

    suite="${test#tests/python/}"
    suite="${suite%.test.py}"
    suite="py:$suite"

    statistics_file="$test_results_dir/statistics/${suite/:/\/}.json"
    mkdir -p "$(dirname "$statistics_file")"
    rm -f "$statistics_file"

    output_file="$(mktemp)"

    set +e
    TEST_SUITE_ID="$suite" \
    TEST_STATISTICS_FILE="$statistics_file" \
    python3 "$test" >"$output_file" 2>&1
    rc=$?
    set -e

    case "$rc" in
        0)
            status="passed"

            if ! $print_failures_only; then
                echo "==> $name"
                cat "$output_file"
                echo "✅ $name"
                echo
            fi

            ((++passed))
            ;;

        1)
            status="failed"
            echo "==> $name"
            cat "$output_file"
            echo "❌ $name"
            echo
            echo "$suite" >> "$failed_suites_file"
            ((++failed))
            ;;

        *)
            status="crashed"
            echo "==> $name"
            cat "$output_file"
            echo "❌💥 $name (exit $rc)"
            echo
            echo "$suite" >> "$failed_suites_file"
            ((++crashed))

            printf '{"suite":"%s","status":"crashed"}\n' \
                "$suite" \
                >"$statistics_file"
            ;;
    esac

    rm -f "$output_file"
done

total=$((passed + failed + crashed))

echo "Suite summary: $total test suites run, $passed passed, $failed failed, $crashed crashed"
statistics_dir="$test_results_dir/statistics/py"
python3 tests/python/lib/summarize_statistics.py "$statistics_dir"

if (( failed || crashed )); then
    exit 1
fi
