#!/usr/bin/env bash

#
# Compare the locally rendered dashboard with the production dashboard.
#
# This script runs inside the PHP 5.5 Docker test environment to ensure that
# the rendered dashboard matches the execution environment used in production.
#
set -euo pipefail

repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$repo_root"

tmpdir="$(mktemp -d)"
keep_tmpdir=0

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

centre()
{
    local width="$1"
    local text="$2"

    while [ "${#text}" -lt "$width" ]; do
        text=" $text"
        [ "${#text}" -lt "$width" ] && text="$text "
    done

    printf '%s' "$text"
}

compare_artifact()
{
    local title="$1"
    local production="$2"
    local local="$3"

    local diff_file="$tmpdir/${title}.diff"

    if diff -u "$production" "$local" > "$diff_file"; then
        return 0
    fi

    printf '%b' "${red}"
    printf '========================================================================\n'
    printf '========================= %s =============================\n' \
      "$(centre 16 "$title")"
    printf '========================================================================\n\n'

    tail -n +3 "$diff_file" | head -n -1

    printf '%b' "${reset}"
    return 1
}

cleanup()
{
    if (( keep_tmpdir )); then
        echo
        echo "Temporary files preserved in:"
        echo "    $tmpdir"
        echo "(but currently deleted when docker exits)"
    else
        rm -rf "$tmpdir"
    fi
}

trap cleanup EXIT
#
# Script proper starts here:
# Render and execute the dashboard.
#
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

set +e
php "$site_dir/index.php" > "$tmpdir/index.html" 2> "$tmpdir/index.err"
php_rc=$?
set -e

if (( php_rc != 0 )); then
    keep_tmpdir=1

    echo
    echo "========================================================================"
    echo "Dashboard execution failed"
    echo "========================================================================"

    if [[ -s "$tmpdir/index.err" ]]; then
        echo "--- stderr ---"
        cat "$tmpdir/index.err"
    fi

    if [[ -s "$tmpdir/index.html" ]]; then
        echo "--- stdout ---"
        cat "$tmpdir/index.html"
    fi

    if [[ ! -s "$tmpdir/index.err" && ! -s "$tmpdir/index.html" ]]; then
        echo "(no diagnostic output)"
    fi

    exit "$php_rc"
fi

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
# Compare with production
#
green='\033[0;32m'
red='\033[0;31m'
reset='\033[0m'

echo
printf '%b' "${green}"
printf '%s\n' \
    '========================================================================' \
    '==> Deployment gate: verifying rendered dashboard against production <==' \
    '========================================================================'
printf '%b' "${reset}"

#
# Fetch production UI files
#
 curl --fail --silent --show-error \
     https://noc.arcanel.se/ \
     > "$tmpdir/production.html"

 curl --fail --silent --show-error \
     https://noc.arcanel.se/static/style.css \
     > "$tmpdir/production.css"

 curl --fail --silent --show-error \
     https://noc.arcanel.se/static/dashboard.js \
     > "$tmpdir/production.js"
#
# Normalise html files
#
normalise_dashboard "$tmpdir/index.html" \
    > "$tmpdir/local.normalised.html"

normalise_dashboard "$tmpdir/production.html" \
    > "$tmpdir/production.normalised.html"

any_differences=false
#
# Compare html files
#
compare_artifact \
    "index.html" \
    "$tmpdir/production.normalised.html" \
    "$tmpdir/local.normalised.html" || any_differences=true
#
# Compare css files
#
compare_artifact \
    "style.css" \
    "$tmpdir/production.css" \
    templates/noc/static/style.css || any_differences=true
#
# Compare js files
#
compare_artifact \
    "dashboard.js" \
    "$tmpdir/production.js" \
    templates/noc/static/dashboard.js || any_differences=true

if ! $any_differences; then

    printf '%b' "${green}"
    printf '%s\n' \
        '========================================================================' \
        '=======================> 👍  GATE PASSED  👍 <==========================' \
        '========================================================================'
    printf '%b' "${reset}"
else
    printf '%b' "${red}"
    printf '%s\n' \
        '========================================================================' \
        '====================== ⚠️  CHECK THE CHANGES ABOVE  ⚠️  ==================' \
        '========================================================================'
    printf '%b' "${reset}"

    exit 1
fi
