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
# Remove placeholder from config file
SMOKE_TEST_HOST_TOKEN_LINE=
EOF

rendered_config="$build_dir/telemetry.config.php"
BREDLAND_SECRETS_FILE="$secrets_file" ./scripts/render-template.sh \
    templates/noc/telemetry.config.template.php \
    "$rendered_config"
export TEST_CONFIG="$rendered_config"
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