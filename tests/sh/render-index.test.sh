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
    fixture_date="$(jq -r '.ts[0:10]' "$fixture_file")"

    jq -c . "$fixture_file" \
        > "$data_dir/${host}-${fixture_date}.jsonl"
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
    exit 1
fi

#
# Test that the rendered index.php actually executes.
#

cp -R templates/noc/lib "$build_dir/"
cp -R templates/noc/clients "$build_dir/"
cp -R templates/noc/static/. "$static_dir/"

fixture_now="$(cat tests/fixtures/last-fetched.timestamp)"

set +e
NOC_NOW="$fixture_now" \
    php "$build_dir/index.php" \
    > "$build_dir/index.html" \
    2> "$build_dir/index.err"
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
    echo "Please run scripts/compare-dashboard.sh for full diagnostics."
    exit "$php_rc"
fi

if [[ -s "$build_dir/index.err" ]]; then
    echo "Unexpected output on stderr:"
    cat "$build_dir/index.err"
    exit 1
fi

[[ -s "$build_dir/index.html" ]]

if ! cmp -s \
    tests/fixtures/production/index.html \
    "$build_dir/index.html"; then

    echo
    echo "Rendered dashboard differs from the production fixture."
    echo "Run scripts/compare-dashboard.sh to inspect the changes."
    exit 1
fi

echo "OK"