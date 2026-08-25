#!/usr/bin/env bash

set -euo pipefail

# Repository setup

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root"

# Suite selection

matches_selector()
{
    local suite="$1"
    shift

    local selector

    if (( $# == 0 )); then
        return 0
    fi

    for selector in "$@"; do
        # shellcheck disable=SC2053
        if [[ "$suite" == $selector ]]; then
            matched_selectors+=("$selector")
            return 0
        fi
    done

    return 1
}

ran_shell_tests=false
ran_php_tests=false
list_only=false
rerun_failed_suites=false
print_failures_only=false

selectors=()
test_args=()
selected_shell_tests=()
selected_php_tests=()
matched_selectors=()
selected_suite_ids=()

for arg in "$@"; do
    case "$arg" in
        -q)
            test_args+=("$arg")
            ;;

        --list)
            list_only=true
            ;;

        --failed)
            rerun_failed_suites=true
            ;;

        --failures-only)
            print_failures_only=true
            ;;

        php)
            selectors+=("php:*")
            ;;

        shell)
            selectors+=("sh:*")
            ;;

        *)
            selectors+=("$arg")
            ;;
    esac
done

# Test results

test_results_dir="${TEST_RESULTS_DIR:-build/test-results}"
failed_suites_file="$test_results_dir/failed-suites"

mkdir -p "$test_results_dir"

statistics_dir="$test_results_dir/statistics"

mkdir -p "$test_results_dir"
rm -rf "$statistics_dir"
mkdir -p "$statistics_dir"

if $rerun_failed_suites; then
    if [[ ! -f "$failed_suites_file" ]]; then
        echo "❌ No previous failed-suite record exists." >&2
        exit 1
    fi

    while IFS= read -r suite; do
        if [[ -n "$suite" ]]; then
            selectors+=("$suite")
        fi
    done < "$failed_suites_file"

    if (( ${#selectors[@]} == 0 )); then
        echo "✅ No failed suites from the previous run."
        exit 0
    fi
fi

# Suite discovery

shopt -s nullglob

shell_tests=(tests/sh/*.test.sh)
for test in "${shell_tests[@]}"; do
    suite="${test#tests/sh/}"
    suite="${suite%.test.sh}"
    suite="sh:$suite"

    if matches_selector "$suite" "${selectors[@]}"; then
        selected_shell_tests+=("$test")
        selected_suite_ids+=("$suite")
    fi
done

php_tests=(tests/php/{,compiler/}*.test.php)
for test in "${php_tests[@]}"; do
    suite="${test#tests/php/}"
    suite="${suite%.test.php}"
    suite="php:$suite"

    if matches_selector "$suite" "${selectors[@]}"; then
        selected_php_tests+=("$test")
        selected_suite_ids+=("$suite")
    fi
done

unmatched_selectors=()

for selector in "${selectors[@]}"; do
    matched=false

    for matched_selector in "${matched_selectors[@]}"; do
        if [[ "$selector" == "$matched_selector" ]]; then
            matched=true
            break
        fi
    done

    if ! $matched; then
        unmatched_selectors+=("$selector")
    fi
done

if (( ${#unmatched_selectors[@]} > 0 )); then
    echo "❌ No test suites matched:" >&2

    for selector in "${unmatched_selectors[@]}"; do
        echo "   $selector" >&2
    done

    exit 1
fi

# List suites

if $list_only; then
    for suite in "${selected_suite_ids[@]}"; do
        echo "$suite"
    done

    exit 0
fi

# Shell tests

if (( ${#selected_shell_tests[@]} > 0 )); then
    ran_shell_tests=true
    echo
    echo "==> Shell tests"

    set +e

    if $print_failures_only; then
        tests/sh/run-all.sh \
            --failures-only \
            -- \
            "${selected_shell_tests[@]}"
    else
        tests/sh/run-all.sh \
            -- \
            "${selected_shell_tests[@]}"
    fi

    sh_rc=$?
    set -e
else
    sh_rc=0
fi

# PHP tests

if (( ${#selected_php_tests[@]} > 0 )); then
    ran_php_tests=true
    echo
    echo "==> PHP tests"

    set +e

    if $print_failures_only; then
        tests/php/run-all.sh \
            --failures-only \
            "${test_args[@]}" \
            -- \
            "${selected_php_tests[@]}"
    else
        tests/php/run-all.sh \
            "${test_args[@]}" \
            -- \
            "${selected_php_tests[@]}"
    fi

    php_rc=$?
    set -e
else
    php_rc=0
fi

# Overall summary

echo
echo "==> Overall summary"

if $ran_shell_tests; then
    if [[ $sh_rc -eq 0 ]]; then
        echo "✅ Shell tests"
    else
        echo "❌ Shell tests"
    fi
fi

if $ran_php_tests; then
    if [[ $php_rc -eq 0 ]]; then
        echo "✅ PHP tests"
    else
        echo "❌ PHP tests"
    fi
fi

if [[ $sh_rc -eq 0 && $php_rc -eq 0 ]]; then
    exit 0
else
    exit 1
fi