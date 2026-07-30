#!/usr/bin/env bash

#
# Canonicalise rendered dashboard HTML before comparing it with production.
#
# hxnormalize removes insignificant formatting differences, after which we
# normalise a handful of remaining HTML constructs and mask dynamic content
# (health, timestamps, telemetry, etc.). This keeps the diff focused on
# genuine structural or rendering regressions.
#
normalise_dashboard()
{
    hxnormalize "$1" |
    sed -E \
        -e '/<script /{
                N
                s/<script([^>]*)>\n[[:space:]]*<\/script>/<script\1><\/script>/
            }' \
        -e 's/<title>[[:space:]]*/<title>/' \
        -e 's/(card )(green|yellow|red)/\1__HEALTH__/g' \
        -e 's/(led )(green|yellow|red)/\1__HEALTH__/g' \
        -e 's/^([[:space:]]*<p>Last heartbeat:).*/\1 __DYNAMIC__/' \
        -e 's/^([[:space:]]*<p>Uptime:).*/\1 __DYNAMIC__/' \
        -e 's/^([[:space:]]*<p>Free memory:).*/\1 __DYNAMIC__<\/p>/' |
    # Replace telemetry payloads with a placeholder so live values do not
    # affect the comparison.
    awk '
        function emit_telemetry_placeholder(line) {
            match(line, /^[[:space:]]*/)
            indentation = substr(line, 1, RLENGTH)

            print indentation \
                "<pre class=telemetry>" \
                "__DYNAMIC_TELEMETRY__" \
                "</pre>"
        }

        !inside_telemetry && /<pre class=telemetry/ {
            emit_telemetry_placeholder($0)

            if ($0 ~ /<\/pre[[:space:]]*>/) {
                next
            }

            inside_telemetry = 1
            closing_tag_started = ($0 ~ /<\/pre/)
            next
        }

        inside_telemetry {
            if (closing_tag_started) {
                if ($0 ~ /^[[:space:]]*>/) {
                    inside_telemetry = 0
                    closing_tag_started = 0
                }

                next
            }

            if ($0 ~ /<\/pre[[:space:]]*>/) {
                inside_telemetry = 0
                next
            }

            if ($0 ~ /<\/pre/) {
                closing_tag_started = 1
            }

            next
        }

        {
            print
        }
    '
}

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
green='\033[0;32m'
reset='\033[0m'

echo
printf "${green}========================================================================\n"
printf "==> Deployment gate: verifying rendered dashboard against production <==\n"
printf "========================================================================${reset}\n"

    curl --fail --silent --show-error \
        https://noc.arcanel.se/ \
        > "$tmpdir/production.html"

normalise_dashboard "$tmpdir/index.html" \
    > "$tmpdir/local.normalised.html"

normalise_dashboard "$tmpdir/production.html" \
    > "$tmpdir/production.normalised.html"

diff -u \
    "$tmpdir/production.normalised.html" \
    "$tmpdir/local.normalised.html"
fi

echo "OK"