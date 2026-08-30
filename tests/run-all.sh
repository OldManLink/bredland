#!/usr/bin/env bash
# Keep this image aligned with the PHP version supported by Oderland.
PHP_TEST_IMAGE="bredland/php55-test"

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root"

test_results_dir="${TEST_RESULTS_DIR:-build/test-results}"
failed_suites_file="$test_results_dir/failed-suites"

mkdir -p "$test_results_dir"
statistics_dir="$test_results_dir/statistics"

quietest=false
run_js=false
run_non_js=false
explicit_selector=false
rerun_failed_suites=false
list_only=false
print_failures_only=false

test_args=()
js_tests=()

for arg in "$@"; do
    case "$arg" in
        -qq)
            quietest=true
            test_args+=("-q")
            ;;

        js)
            explicit_selector=true
            run_js=true
            ;;

        js:*)
            explicit_selector=true
            run_js=true
            js_tests+=("tests/js/${arg#js:}.test.js")
            ;;

        --list)
            list_only=true
            test_args+=("$arg")
            ;;

        --failed)
            rerun_failed_suites=true
            ;;

        --failures-only)
            print_failures_only=true
            test_args+=("$arg")
            ;;

        -*)
            test_args+=("$arg")
            ;;

        *)
            explicit_selector=true
            run_non_js=true
            test_args+=("$arg")
            ;;
    esac
done

if $rerun_failed_suites; then
    if [[ ! -f "$failed_suites_file" ]]; then
        echo "❌ No previous failed-suite record exists." >&2
        exit 1
    fi

    while IFS= read -r suite; do
        if [[ -z "$suite" ]]; then
            continue
        fi

        case "$suite" in
            js:*)
                explicit_selector=true
                run_js=true
                js_tests+=("tests/js/${suite#js:}.test.js")
                ;;

            php:*|sh:*|py:*)
                explicit_selector=true
                run_non_js=true
                test_args+=("$suite")
                ;;
        esac
    done < "$failed_suites_file"

    if ! $run_js && ! $run_non_js; then
        echo "✅ No failed suites from the previous run."
        exit 0
    fi
fi

if ! $explicit_selector; then
    run_non_js=true
    run_js=true
fi

run_tests()
{
    non_js_rc=0
    js_rc=0
    if $run_non_js; then

        if ! docker info >/dev/null 2>&1; then
            echo "ERROR: Docker is required and must be running." >&2
            return 1
        fi

        if [[ "${CHECK_LOCAL_SECRETS:-0}" == "1" ]]; then
            echo "==> Verifying development environment"

            tests/sh/check-local-secrets.sh

            echo "✅ Local secrets match example config"
            echo
        fi

        if [[ "${SKIP_DOCKER_BUILD:-0}" != "1" ]]; then
            echo "==> Building Linux test environment ($PHP_TEST_IMAGE)"

            docker_build_output="$(mktemp)"
            docker_build_rc=0

            docker build \
                --platform linux/amd64 \
                -t "$PHP_TEST_IMAGE" \
                tests/docker/php55 \
                >"$docker_build_output" 2>&1 \
                || docker_build_rc=$?

            if (( docker_build_rc != 0 )); then
                cat "$docker_build_output"
                rm -f "$docker_build_output"
                return "$docker_build_rc"
            fi

            rm -f "$docker_build_output"
        fi

        echo "==> Starting Linux test environment ($PHP_TEST_IMAGE)"

        if (( ${#test_args[@]} > 0 )); then
            docker run --rm \
              --platform linux/amd64 \
              -v "$repo_root:/app" \
              -w /app \
              "$PHP_TEST_IMAGE" \
              bash tests/in-container.sh "${test_args[@]}" \
              || non_js_rc=$?
        else
            docker run --rm \
              --platform linux/amd64 \
              -v "$repo_root:/app" \
              -w /app \
              "$PHP_TEST_IMAGE" \
              bash tests/in-container.sh \
              || non_js_rc=$?
        fi
        
    fi

    if $run_js; then
        if $list_only; then
            if (( ${#js_tests[@]} > 0 )); then
                for test in "${js_tests[@]}"; do
                    suite="${test#tests/js/}"
                    suite="${suite%.test.js}"
                    echo "js:$suite"
                done
            else
                for test in tests/js/*.test.js; do
                    suite="${test#tests/js/}"
                    suite="${suite%.test.js}"
                    echo "js:$suite"
                done
            fi
        else
            echo
            echo "==> JavaScript tests"

            if (( ${#js_tests[@]} > 0 )); then
                if $print_failures_only; then
                    tests/js/run-all.sh \
                        --failures-only \
                        -- \
                        "${js_tests[@]}" \
                        || js_rc=$?
                else
                    tests/js/run-all.sh \
                        -- \
                        "${js_tests[@]}" \
                        || js_rc=$?
                fi
            else
                if $print_failures_only; then
                    tests/js/run-all.sh --failures-only \
                        || js_rc=$?
                else
                    tests/js/run-all.sh \
                        || js_rc=$?
                fi
            fi

            if (( js_rc == 0 )); then
                echo "✅ JavaScript tests"
            else
                echo "❌ JavaScript tests"
            fi
        fi
    fi

    if (( non_js_rc != 0 )); then
        return "$non_js_rc"
    fi

    if (( js_rc != 0 )); then
        return "$js_rc"
    fi

    return 0
}

: > "$failed_suites_file"
rm -rf "$statistics_dir"
mkdir -p "$statistics_dir"

start_time="$(date +%s)"

if $quietest; then
    output_file="$(mktemp)"
    trap 'rm -f "$output_file"' EXIT

    set +e
    (
        set -e
        run_tests
    ) >"$output_file" 2>&1
    rc=$?
    set -e

    if (( rc != 0 )); then
        cat "$output_file"
    fi
else
    set +e
    run_tests
    rc=$?
    set -e
fi

end_time="$(date +%s)"
elapsed=$((end_time - start_time))

if (( rc == 0 )); then
    echo "✅ All tests passed, elapsed time: ${elapsed}s"
else
    echo "❌ Test failures, elapsed time: ${elapsed}s"
    exit "$rc"
fi
