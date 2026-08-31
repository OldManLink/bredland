#!/usr/bin/env bash

set -euo pipefail

build_dir="tests/php/build"

cleanup() {
    rm -rf "$build_dir"
}

trap cleanup EXIT

rm -rf "$build_dir"
mkdir -p "$build_dir"

secrets_file="$build_dir/telemetry.config.env"
cat > "$secrets_file" <<'EOF'
# Shared stuff (from MikroTik to Oderland)
MIKROTIK_NOC_HOST=mikrotik-test
MIKROTIK_NOC_TOKEN=mikrotik.v1.test-token
BREDLAND_NOC_HOST=bredland-test
BREDLAND_NOC_TOKEN=bredland.v1.test-token
# Oderland stuff
NOC_DATA_DIR=/private/data/
EOF

rendered_config="$build_dir/telemetry.config.php"
BREDLAND_SECRETS_FILE="$secrets_file" ./scripts/render-template.sh \
    templates/noc/telemetry.config.template.php \
    "$rendered_config"
export TEST_CONFIG="$rendered_config"
repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$repo_root"

test_results_dir="${TEST_RESULTS_DIR:-build/test-results}"
failed_suites_file="$test_results_dir/failed-suites"

test_args=()
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

            *)
                test_args+=("$arg")
                ;;
        esac
    fi
done

if (( ${#test_scripts[@]} == 0 )); then
    echo "==> no PHP tests yet"
    exit 0
fi

passed=0
skipped=0
failed=0
crashed=0

for test in "${test_scripts[@]}"; do
    name="$(basename "$test" .test.php)"

    suite="${test#tests/php/}"
    suite="${suite%.test.php}"
    suite="php:$suite"

    statistics_file="$test_results_dir/statistics/${suite/:/\/}.json"
    mkdir -p "$(dirname "$statistics_file")"
    rm -f "$statistics_file"

    output_file="$(mktemp)"

    set +e
    TEST_SUITE_ID="$suite" \
    TEST_STATISTICS_FILE="$statistics_file" \
    php "$test" "${test_args[@]}" >"$output_file" 2>&1
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

statistics_dir="$test_results_dir/statistics/php"
php tests/php/lib/summarize-statistics.php "$statistics_dir"

if (( failed || crashed )); then
    exit 1
fi