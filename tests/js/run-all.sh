#!/usr/bin/env bash

set -euo pipefail

test_results_dir="${TEST_RESULTS_DIR:-build/test-results}"
failed_suites_file="$test_results_dir/failed-suites"

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

if (( ${#test_scripts[@]} == 0 )); then
    test_scripts=(tests/js/*.test.js)
fi

passed=0
failed=0
crashed=0

for test_script in "${test_scripts[@]}"; do
    name="$(basename "$test_script" .test.js)"

    suite="${test_script#tests/js/}"
    suite="${suite%.test.js}"
    suite="js:$suite"

    statistics_file="$test_results_dir/statistics/${suite/:/\/}.json"
    mkdir -p "$(dirname "$statistics_file")"
    rm -f "$statistics_file"

    output_file="$(mktemp)"

    set +e
    node --test "$test_script" >"$output_file" 2>&1
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
            ;;
    esac

    printf '{"suite":"%s","status":"%s"}\n' \
        "$suite" \
        "$status" \
        > "$statistics_file"

    rm -f "$output_file"
done

total=$((passed + failed + crashed))

echo "Suite summary: $total test suites run, $passed passed, $failed failed, $crashed crashed"

if (( failed || crashed )); then
    exit 1
fi