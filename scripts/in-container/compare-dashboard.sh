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

build_dir="build/render-index"
compare_dir="build/compare-dashboard"
fixture_dir="tests/fixtures/production"

rm -rf "$compare_dir"
mkdir -p "$compare_dir"

#
# Canonicalise dashboard HTML before presenting structural differences.
#
normalise_dashboard()
{
    hxnormalize -c "snapshot" "$1"
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

    local diff_cmd=(diff -u)
    local diff_output

    if (( ${COLUMNS:-80} >= 180 )); then
        diff_cmd=(diff -y)
    fi

    if diff_output="$("${diff_cmd[@]}" "$production" "$local")"; then
        return 0
    fi

    if [[ "${diff_cmd[1]}" == "-u" ]]; then
        diff_output="$(printf '%s\n' "$diff_output" | tail -n +3)"
    fi

    printf '%b' "${red}"
    printf '========================================================================\n'
    printf '========================= %s =============================\n' \
        "$(centre 16 "$title")"
    printf '========================================================================\n'

    printf '%s\n' "$diff_output"

    printf '%b' "${reset}"

    if [[ "${diff_cmd[1]}" == "-u" ]]; then
        echo "Tip: maximize or widen your terminal and rerun for a side-by-side comparison."
    fi

    return 1
}

#
# Script proper starts here:
# Build and verify the deterministic dashboard.
#
set +e

RENDER_INDEX_NEXT_STEP="Comparison skipped because dashboard rendering failed." \
    tests/sh/render-index.test.sh

render_rc=$?
set -e

if (( render_rc > 1 )); then
    exit "$render_rc"
fi
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
# Normalise html files
#
normalise_dashboard "$build_dir/index.html" \
    > "$compare_dir/local.normalised.html"

normalise_dashboard "$fixture_dir/index.html" \
    > "$compare_dir/production.normalised.html"

any_differences=false
#
# Compare html files
#
compare_artifact \
    "index.html" \
    "$compare_dir/production.normalised.html" \
    "$compare_dir/local.normalised.html" || any_differences=true
#
# Compare css files
#
compare_artifact \
    "style.css" \
    "$fixture_dir/static/style.css" \
    "$build_dir/static/style.css" || any_differences=true
#
# Compare js files
#
compare_artifact \
    "dashboard.js" \
    "$fixture_dir/static/dashboard.js" \
    "$build_dir/static/dashboard.js" || any_differences=true

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
