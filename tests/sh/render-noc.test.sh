#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$repo_root"

build_dir="$repo_root/build/rendered-noc"
data_dir="$build_dir/data"
static_dir="$build_dir/static"

rm -rf "$build_dir"
mkdir -p "$data_dir" "$static_dir"

echo -n "Testing rendered NOC index ... "

cat > "$build_dir/telemetry.config.php" <<EOF
<?php

\$HOST_TOKENS = array(
    'mikrotik' => 'mikrotik.v1.test-token',
    'bredland' => 'bredland.v1.test-token'
);

\$DATA_DIR = '$data_dir';
EOF

render_env="$build_dir/noc.env"

cat > "$render_env" <<EOF
TELEMETRY_CONFIG_FILE="$build_dir/telemetry.config.php"
EOF

BREDLAND_SECRETS_FILE="$render_env" \
    scripts/render-template.sh \
    templates/noc/index.template.php \
    "$build_dir/index.php"

[[ -s "$build_dir/index.php" ]]

if grep -q '__[A-Z0-9_]\+__' "$build_dir/index.php"; then
    echo "Unresolved placeholders remain in rendered index.php" >&2
    exit 2
fi

BREDLAND_SECRETS_FILE="$render_env" \
    scripts/render-template.sh \
    templates/noc/telemetry.endpoint.template.php \
    "$build_dir/telemetry.php"

[[ -s "$build_dir/telemetry.php" ]]

if grep -q '__[A-Z0-9_]\+__' "$build_dir/telemetry.php"; then
    echo "Unresolved placeholders remain in rendered telemetry.php" >&2
    exit 2
fi


cp -R templates/noc/clients "$build_dir/"
cp -R templates/noc/icons "$build_dir/"
cp -R templates/noc/lib "$build_dir/"
cp -R templates/noc/schemas "$build_dir/"
cp -R templates/noc/static/. "$static_dir/"
cp -R templates/noc/manifest.json "$build_dir/"

#
# Test that the rendered telemetry.php actually executes.
#

set +e
REQUEST_METHOD=GET \
    php \
        -d display_errors=0 \
        -d log_errors=1 \
        -d error_log="$build_dir/telemetry.err" \
        "$build_dir/telemetry.php" \
        > "$build_dir/telemetry.out"
php_rc=$?
set -e

if (( php_rc != 0 )); then
    echo
    echo "Rendered telemetry endpoint execution failed."

    if [[ -s "$build_dir/telemetry.err" ]]; then
        echo "--- stderr ---"
        cat "$build_dir/telemetry.err"
    fi

    if [[ -s "$build_dir/telemetry.out" ]]; then
        echo "--- stdout ---"
        cat "$build_dir/telemetry.out"
    fi

    exit "$php_rc"
fi

if [[ -s "$build_dir/telemetry.err" ]]; then
    echo
    echo "Unexpected telemetry endpoint PHP diagnostic output:"
    cat "$build_dir/telemetry.err"
    exit 2
fi

if [[ "$(cat "$build_dir/telemetry.out")" != "method not allowed" ]]; then
    echo
    echo "Unexpected telemetry endpoint response:"
    cat "$build_dir/telemetry.out"
    exit 2
fi

#
# Verify that GET requests don't write anything to the data directory
#
if find "$data_dir" -type f | grep -q .; then
    echo
    echo "Telemetry endpoint wrote data for a rejected GET request."
    exit 2
fi

#
# Test that the rendered index.php actually executes.
#
for fixture_file in tests/fixtures/heartbeats/*.json; do
    host="$(basename "$fixture_file" .json)"

    if ! compact_fixture="$(jq -c . "$fixture_file")"; then
        echo
        echo "Heartbeat fixture contains invalid JSON:"
        echo "    $fixture_file"
        echo "Run scripts/update-fixtures.sh to refresh the fixture set."
        exit 2
    fi

    fixture_date="$(
        printf '%s\n' "$compact_fixture" |
            jq -r '.ts[0:10]'
    )"

    jsonl_file="$data_dir/${host}-${fixture_date}.jsonl"
    printf '%s\n' "$compact_fixture" > "$jsonl_file"
done

timestamp_file="tests/fixtures/last-fetched.timestamp"

if [[ ! -s "$timestamp_file" ]]; then
    echo
    echo "Fixture timestamp is missing or empty:"
    echo "    $timestamp_file"
    echo "Run scripts/update-fixtures.sh to refresh the fixture set."
    exit 2
fi

fixture_now="$(cat "$timestamp_file")"

if ! date -d "$fixture_now" +%s >/dev/null 2>&1; then
    echo
    echo "Fixture timestamp is invalid:"
    echo "    $fixture_now"
    echo "Run scripts/update-fixtures.sh to refresh the fixture set."
    exit 2
fi

set +e
NOC_NOW="$fixture_now" \
    php \
        -d display_errors=0 \
        -d log_errors=1 \
        -d error_log="$build_dir/index.err" \
        "$build_dir/index.php" \
        > "$build_dir/index.html"
php_rc=$?
set -e

if (( php_rc != 0 )); then
    echo
    echo "Rendered dashboard execution failed."

    if [[ -s "$build_dir/index.err" ]]; then
        echo "--- stderr ---"
        cat "$build_dir/index.err"
    fi

    if [[ -s "$build_dir/index.html" ]]; then
        echo "--- stdout ---"
        cat "$build_dir/index.html"
    fi

    echo

    if [[ -n "${RENDER_NOC_NEXT_STEP:-}" ]]; then
        echo "$RENDER_NOC_NEXT_STEP"
    else
        echo "Please run scripts/compare-dashboard.sh for full diagnostics."
    fi
    exit "$php_rc"
fi

if [[ -s "$build_dir/index.err" ]]; then
    echo
    echo "Unexpected PHP diagnostic output:"
    cat "$build_dir/index.err"
    exit 2
fi

if [[ ! -s "$build_dir/index.html" ]]; then
    echo
    echo "Rendered dashboard produced no HTML output."
    echo "Please run scripts/compare-dashboard.sh for full diagnostics."
    exit 2
fi

production_index="tests/fixtures/production/index.html"

if [[ ! -s "$production_index" ]]; then
    echo
    echo "Production dashboard fixture is missing or empty:"
    echo "    $production_index"
    echo "Run scripts/update-fixtures.sh to refresh the fixture set."
    exit 2
fi

if [[ ! -s "$static_dir/style.css" ]]; then
    echo
    echo "Rendered dashboard build is missing:"
    echo "    $static_dir/style.css"
    exit 2
fi

if [[ ! -s "$static_dir/dashboard.js" ]]; then
    echo
    echo "Rendered dashboard build is missing:"
    echo "    $static_dir/dashboard.js"
    exit 2
fi

if ! cmp -s \
    "$production_index" \
    "$build_dir/index.html"; then

    echo
    echo "Rendered dashboard differs from the production fixture."
    echo "Run scripts/compare-dashboard.sh to inspect the changes."
    exit 1
fi

echo "OK"