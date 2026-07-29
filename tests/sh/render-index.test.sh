#!/usr/bin/env bash

set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

echo -n "Testing rendered NOC index ... "

cat > "$tmpdir/noc-index.env" <<EOF
TELEMETRY_CONFIG_FILE="$tmpdir/telemetry.config.php"
EOF

mkdir -p "$tmpdir/data"
for file in tests/fixtures/heartbeats/*.json; do
    php -r '
        $json = file_get_contents($argv[1]);
        $data = json_decode($json, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            fwrite(STDERR, "Invalid JSON: " . json_last_error_msg() . PHP_EOL);
            exit(1);
        }

        echo json_encode($data), PHP_EOL;
    ' "$file" \
        > "$tmpdir/data/$(basename "${file%.json}")-2026-07-29.jsonl"
done

cat > "$tmpdir/telemetry.config.php" <<EOF
<?php

\$DATA_DIR = '$tmpdir/data';
EOF

BREDLAND_SECRETS_FILE="$tmpdir/noc-index.env" \
    scripts/render-template.sh \
    templates/noc/index.template.php \
    "$tmpdir/index.php"

[[ -s "$tmpdir/index.php" ]]

if grep -q '__[A-Z0-9_]\+__' "$tmpdir/index.php"; then
    echo "Unresolved placeholders remain in rendered index.php" >&2
    exit 1
fi

grep -q '<!DOCTYPE html>' "$tmpdir/index.php"

#
# Test that the rendered index.php actually executes.
#

site_dir="$tmpdir/site"

mkdir -p "$site_dir"

cp "$tmpdir/index.php" "$site_dir/index.php"
cp "$tmpdir/telemetry.config.php" "$site_dir/telemetry.config.php"

cp -R templates/noc/lib "$site_dir/"
cp -R templates/noc/clients "$site_dir/"
cp -R templates/noc/static "$site_dir/"

php "$site_dir/index.php" > "$tmpdir/index.html" 2> "$tmpdir/index.err"

if [ -s "$tmpdir/index.err" ]; then
    echo "Unexpected output on stderr:"
    cat "$tmpdir/index.err"
    exit 1
fi

[[ -s "$tmpdir/index.html" ]]

grep -q '<!DOCTYPE html>' "$tmpdir/index.html"
grep -q '<html' "$tmpdir/index.html"
grep -q '<body>' "$tmpdir/index.html"
grep -q '</body>' "$tmpdir/index.html"
grep -q '</html>' "$tmpdir/index.html"
grep -q '<div class="dashboard">' "$tmpdir/index.html"
grep -q 'cards-row' "$tmpdir/index.html"
! grep -q '>Uptime: unavailable<' "$tmpdir/index.html"
! grep -q '>Free memory: unavailable<' "$tmpdir/index.html"

#
# Verify against production
#

if [ "${COMPARE_WITH_PRODUCTION:-0}" = "1" ]; then

    curl --fail --silent --show-error \
        https://noc.arcanel.se/ \
        > "$tmpdir/production.html"

    php tests/normalise-dashboard-html.php \
        "$tmpdir/index.html" \
        > "$tmpdir/local.normalised.html"

    php tests/normalise-dashboard-html.php \
        "$tmpdir/production.html" \
        > "$tmpdir/production.normalised.html"

    diff -u \
        "$tmpdir/production.normalised.html" \
        "$tmpdir/local.normalised.html"
fi

echo "OK"