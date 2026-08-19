#!/usr/bin/env bash

set -u

passed=0
failed=0

test_results_dir="$(mktemp -d)"
export TEST_RESULTS_DIR="$test_results_dir"
failure_fixture="tests/sh/test-runner-failure-fixture.test.sh"

cleanup()
{
    rm -f "$failure_fixture"
    rm -rf "$test_results_dir"
}

trap cleanup EXIT

assert_contains()
{
    local haystack="$1"
    local needle="$2"

    if [[ "$haystack" == *"$needle"* ]]; then
        ((passed++))
    else
        echo "❌ Expected output to contain: $needle"
        ((failed++))
    fi
}

assert_not_contains()
{
    local haystack="$1"
    local needle="$2"

    if [[ "$haystack" != *"$needle"* ]]; then
        ((passed++))
    else
        echo "❌ Expected output not to contain: $needle"
        ((failed++))
    fi
}

echo "Testing test runner ..."

# Command `--list` lists matching test suites

output="$(tests/in-container.sh --list 'php:card*')"
rc=$?

if (( rc != 0 )); then
    echo "❌ --list 'php:card*' exited with $rc"
    ((failed++))
else
    ((passed++))
fi

assert_contains "$output" "php:card"
assert_contains "$output" "php:card-head"
assert_not_contains "$output" "sh:rendered-noc"

# Unmatched selectors fail clearly

output="$(tests/in-container.sh --list 'php:definitely-does-not-exist' 2>&1)"
rc=$?

if (( rc == 1 )); then
    ((passed++))
else
    echo "❌ unmatched selector exited with $rc instead of 1"
    ((failed++))
fi

assert_contains "$output" "No test suites matched"
assert_contains "$output" "php:definitely-does-not-exist"

# Language aliases

output="$(tests/in-container.sh --list php)"
rc=$?

if (( rc != 0 )); then
    echo "❌ --list php exited with $rc"
    ((failed++))
else
    ((passed++))
fi

assert_contains "$output" "php:card"
assert_not_contains "$output" "sh:render-noc"

output="$(tests/in-container.sh --list shell)"
rc=$?

if (( rc != 0 )); then
    echo "❌ --list shell exited with $rc"
    ((failed++))
else
    ((passed++))
fi

assert_contains "$output" "sh:render-noc"
assert_not_contains "$output" "php:card"

# Run single selected test

output="$(tests/in-container.sh php:card-head 2>&1)"
rc=$?

if (( rc != 0 )); then
    echo "❌ php:card-head exited with $rc"
    ((failed++))
else
    ((passed++))
fi

assert_contains "$output" "==> card-head"
assert_contains "$output" "✅ card-head"
assert_not_contains "$output" "==> notification-badge"
assert_not_contains "$output" "==> Shell tests"

# PHP suite writes individual-test statistics

php_statistics_file="$test_results_dir/statistics/php/card-head.json"

if [[ -f "$php_statistics_file" ]]; then
    ((passed++))
else
    echo "❌ PHP suite did not write statistics: $php_statistics_file"
    ((failed++))
fi

if [[ -f "$php_statistics_file" ]]; then
    php_statistics="$(cat "$php_statistics_file")"

    assert_contains "$php_statistics" '"suite":"php:card-head"'
    assert_contains "$php_statistics" '"status":"passed"'
    assert_contains "$php_statistics" '"tests":{'
    assert_contains "$php_statistics" '"run":'
    assert_contains "$php_statistics" '"skipped":'
    assert_contains "$php_statistics" '"passed":'
    assert_contains "$php_statistics" '"failed":'
fi

# Shell suite writes suite-level statistics

output="$(tests/in-container.sh sh:deployment-helpers 2>&1)"
rc=$?

if (( rc != 0 )); then
    echo "❌ sh:deployment-helpers exited with $rc"
    ((failed++))
else
    ((passed++))
fi

shell_statistics_file="$test_results_dir/statistics/sh/deployment-helpers.json"

if [[ -f "$shell_statistics_file" ]]; then
    ((passed++))
else
    echo "❌ Shell suite did not write statistics: $shell_statistics_file"
    ((failed++))
fi

if [[ -f "$shell_statistics_file" ]]; then
    shell_statistics="$(cat "$shell_statistics_file")"

    assert_contains "$shell_statistics" '"suite":"sh:deployment-helpers"'
    assert_contains "$shell_statistics" '"status":"passed"'
    assert_not_contains "$shell_statistics" '"tests"'
fi

# PHP summary includes individual test totals

output="$(tests/in-container.sh php:test-suite-runner 2>&1)"
rc=$?

if (( rc != 0 )); then
    echo "❌ php:test-suite-runner exited with $rc"
    ((failed++))
else
    ((passed++))
fi

assert_contains \
    "$output" \
    "Suite summary: 1 test suites run, 0 skipped, 1 passed, 0 failed, 0 crashed"

assert_contains \
    "$output" \
    "Test summary: 1 tests run, 0 skipped, 1 passed, 0 failed"

# Nested runner inherits private test-results directory

printf '%s\n' \
    "sh:private-state-sentinel" \
    >"$test_results_dir/failed-suites"

output="$(tests/in-container.sh php:card-head 2>&1)"
rc=$?

if (( rc == 0 )); then
    ((passed++))
else
    echo "❌ isolated nested run exited with $rc"
    ((failed++))
fi

if [[ ! -s "$test_results_dir/failed-suites" ]]; then
    ((passed++))
else
    echo "❌ nested runner did not use the private test-results directory"
    ((failed++))
fi

# Failures only

output="$(tests/in-container.sh --failures-only php:card-head 2>&1)"
rc=$?

if (( rc != 0 )); then
    echo "❌ --failures-only php:card-head exited with $rc"
    ((failed++))
else
    ((passed++))
fi

assert_not_contains "$output" "==> card-head"
assert_not_contains "$output" "✅ card-head"
assert_contains "$output" "Suite summary:"
assert_contains "$output" "✅ PHP tests"

# List previously failed test

printf '%s\n' \
    "php:card-head" \
    >"$test_results_dir/failed-suites"

output="$(tests/in-container.sh --failed --list 2>&1)"
rc=$?

if (( rc != 0 )); then
    echo "❌ --failed --list exited with $rc"
    ((failed++))
else
    ((passed++))
fi

assert_contains "$output" "php:card-head"
assert_not_contains "$output" "php:notification-badge"
assert_not_contains "$output" "sh:rendered-noc"

if grep -qx 'php:card-head' \
    "$test_results_dir/failed-suites"; then
    ((passed++))
else
    echo "❌ --failed --list modified the failed-suite record"
    ((failed++))
fi

# Re-run previously failed test

printf '%s\n' \
    "php:card-head" \
    >"$test_results_dir/failed-suites"

output="$(tests/in-container.sh --failed 2>&1)"
rc=$?

if (( rc != 0 )); then
    echo "❌ --failed exited with $rc"
    ((failed++))
else
    ((passed++))
fi

assert_contains "$output" "==> card-head"
assert_contains "$output" "✅ card-head"

if [[ ! -s "$test_results_dir/failed-suites" ]]; then
    ((passed++))
else
    echo "❌ failed-suites was not cleared after successful rerun"
    ((failed++))
fi

# Deliberately failing test fixture

cat >"$failure_fixture" <<'EOF'
#!/usr/bin/env bash

echo "Deliberate test-runner fixture failure"
exit 1
EOF

chmod +x "$failure_fixture"

printf '%s\n' \
    "sh:test-runner-failure-fixture" \
    >"$test_results_dir/failed-suites"

output="$(tests/in-container.sh --failed 2>&1)"
rc=$?

rm -f "$failure_fixture"

if (( rc == 1 )); then
    ((passed++))
else
    echo "❌ failing --failed run exited with $rc instead of 1"
    ((failed++))
fi

assert_contains "$output" "==> test-runner-failure-fixture"
assert_contains "$output" "Deliberate test-runner fixture failure"
assert_contains "$output" "❌ test-runner-failure-fixture"

if grep -qx 'sh:test-runner-failure-fixture' \
    "$test_results_dir/failed-suites"; then
    ((passed++))
else
    echo "❌ failed suite was not recorded"
    ((failed++))
fi

# No previous failures to re-run

: >"$test_results_dir/failed-suites"

output="$(tests/in-container.sh --failed 2>&1)"
rc=$?

if (( rc == 0 )); then
    ((passed++))
else
    echo "❌ empty --failed run exited with $rc"
    ((failed++))
fi

assert_contains "$output" "No failed suites from the previous run"
assert_not_contains "$output" "==> Shell tests"
assert_not_contains "$output" "==> PHP tests"

# Composition test: selector + verbosity

output="$(tests/in-container.sh --failures-only -q php:card-head 2>&1)"
rc=$?

if (( rc != 0 )); then
    echo "❌ --failures-only -q php:card-head exited with $rc"
    ((failed++))
else
    ((passed++))
fi

assert_not_contains "$output" "==> card-head"
assert_contains "$output" "Suite summary:"
assert_contains "$output" "✅ PHP tests"
assert_not_contains "$output" "==> Shell tests"

# Tests summary

total=$((passed + failed))

if (( failed == 0 )); then
    echo "test-runner: $total tests run, $passed passed, $failed failed"
    exit 0
else
    echo "test-runner: $total tests run, $passed passed, $failed failed"
    exit 1
fi