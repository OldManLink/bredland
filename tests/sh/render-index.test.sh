#!/usr/bin/env bash

set -euo pipefail

build_dir="build/render-index"
data_dir="$build_dir/data"
static_dir="$build_dir/static"

rm -rf "$build_dir"
mkdir -p "$data_dir" "$static_dir"

echo -n "Testing rendered NOC index ... "

cat > "$build_dir/noc-index.env" <<EOF
TELEMETRY_CONFIG_FILE="$build_dir/telemetry.config.php"
EOF

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

cat > "$build_dir/telemetry.config.php" <<EOF
<?php

\$DATA_DIR = '$data_dir';
EOF

cat > "$build_dir/noc-index.env" <<EOF
TELEMETRY_CONFIG_FILE="$build_dir/telemetry.config.php"
EOF

BREDLAND_SECRETS_FILE="$build_dir/noc-index.env" \
    scripts/render-template.sh \
    templates/noc/index.template.php \
    "$build_dir/index.php"

[[ -s "$build_dir/index.php" ]]

if grep -q '__[A-Z0-9_]\+__' "$build_dir/index.php"; then
    echo "Unresolved placeholders remain in rendered index.php" >&2
    exit 2
fi

#
# Test that the rendered index.php actually executes.
#

cp -R templates/noc/clients "$build_dir/"
cp -R templates/noc/icons "$build_dir/"
cp -R templates/noc/lib "$build_dir/"
cp -R templates/noc/schemas "$build_dir/"
cp -R templates/noc/static/. "$static_dir/"
cp -R templates/noc/manifest.json "$build_dir/"

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

    if [[ -n "${RENDER_INDEX_NEXT_STEP:-}" ]]; then
        echo "$RENDER_INDEX_NEXT_STEP"
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