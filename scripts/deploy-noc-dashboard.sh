#!/usr/bin/env bash

set -euo pipefail

DEPLOYMENT_SAFETY_MARGIN_SECONDS=45

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"
# shellcheck source=scripts/lib/utils.sh
source "$(dirname "$0")/lib/utils.sh"

load_bredland_secrets

command -v jq >/dev/null
command -v ssh >/dev/null
command -v rsync >/dev/null
command -v open >/dev/null
command -v afplay >/dev/null

hosts=(
    bredland
    mikrotik
)

script_dir="$(cd "$(dirname "$0")" && pwd)"
fetch_latest_heartbeat="$script_dir/fetch-latest-heartbeat.sh"

if [[ ! -x "$fetch_latest_heartbeat" ]]; then
    echo "Error: heartbeat fetcher is not executable: $fetch_latest_heartbeat" >&2
    exit 1
fi

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

heartbeat_dir="$tmpdir/heartbeats"
staging_dir="$tmpdir/staging"

mkdir -p "$heartbeat_dir"

next_heartbeat_host=""
next_heartbeat_epoch=""
now_epoch="$(date -u +%s)"

for host in "${hosts[@]}"; do
    heartbeat_file="$heartbeat_dir/${host}.json"

    echo -n "Fetching latest ${host} heartbeat... "
    "$fetch_latest_heartbeat" "$host" > "$heartbeat_file"
    echo "OK"

    heartbeat_epoch="$(jq -er '.ts | fromdateiso8601' "$heartbeat_file")"
    heartbeat_ttl="$(jq -er '.ttl | select(type == "number" and floor == . and . > 0)' "$heartbeat_file")"
    expected_epoch=$((heartbeat_epoch + heartbeat_ttl))

    if [[ -z "$next_heartbeat_epoch" ]] || (( expected_epoch < next_heartbeat_epoch )); then
        next_heartbeat_epoch="$expected_epoch"
        next_heartbeat_host="$host"
    fi
done

seconds_until_next=$((next_heartbeat_epoch - now_epoch))
next_heartbeat_utc="$(date -u -r "$next_heartbeat_epoch" +%Y-%m-%dT%H:%M:%SZ)"

echo
echo "Next expected heartbeat: ${next_heartbeat_host} at ${next_heartbeat_utc}"
echo "Time remaining: ${seconds_until_next}s"
echo "Required safety margin: more than ${DEPLOYMENT_SAFETY_MARGIN_SECONDS}s"

if (( seconds_until_next <= DEPLOYMENT_SAFETY_MARGIN_SECONDS )); then
    echo "Deployment aborted: not enough time before the next expected heartbeat." >&2
    exit 1
fi

echo
echo "Running test suite..."
tests/run-all.sh -qq

echo
echo "Generating dashboard comparison preview..."
scripts/compare-dashboard.sh --preview

oderland_user="${ODERLAND_SSH_USER:?Missing ODERLAND_SSH_USER}"
oderland_host="${ODERLAND_SSH_HOST:?Missing ODERLAND_SSH_HOST}"
noc_root_dir="${NOC_ROOT_DIR:?Missing NOC_ROOT_DIR}"

endpoint_remote="${TELEMETRY_ENDPOINT_FILE:?Missing TELEMETRY_ENDPOINT_FILE}"
config_remote="${TELEMETRY_CONFIG_FILE:?Missing TELEMETRY_CONFIG_FILE}"
dashboard_remote="$noc_root_dir/index.php"
manifest_remote="$noc_root_dir/manifest.json"

lib_remote="$noc_root_dir/lib"
static_remote="$noc_root_dir/static"
icons_remote="$noc_root_dir/icons"
schemas_remote="$noc_root_dir/schemas"
clients_remote="$noc_root_dir/clients"

endpoint_local="$staging_dir/telemetry.php"
config_local="$staging_dir/telemetry.config.php"
dashboard_local="$staging_dir/index.php"
manifest_local="$staging_dir/manifest.json"

lib_local="$staging_dir/lib"
static_local="$staging_dir/static"
icons_local="$staging_dir/icons"
schemas_local="$staging_dir/schemas"
clients_local="$staging_dir/clients"

mkdir -p \
    "$lib_local" \
    "$static_local" \
    "$icons_local" \
    "$schemas_local" \
    "$clients_local"

version_file="templates/noc/static/static.version"
current="$(cat "$version_file")"
next="$((current + 1))"

# static.version is intentionally source-controlled project state.
# Deploy increments it so the rendered dashboard always references a
# fresh static asset version, and the repository records the last deployed
# asset version for the next manual coding session.
printf '%s\n' "$next" > "$version_file"

echo
echo "Rendering telemetry endpoint..."
scripts/render-template.sh \
    templates/noc/telemetry.endpoint.template.php \
    "$endpoint_local"

echo "Rendering telemetry private config..."
scripts/render-template.sh \
    templates/noc/telemetry.config.template.php \
    "$config_local"

echo "Rendering NOC dashboard..."
scripts/render-template.sh \
    templates/noc/index.template.php \
    "$dashboard_local"

echo "Copying libraries..."
rsync -a templates/noc/lib/ "$lib_local/"

echo "Copying static files..."
cp templates/noc/static/* "$static_local/"

echo "Copying icons..."
cp templates/noc/icons/* "$icons_local/"

echo "Copying heartbeat schemas..."
cp templates/noc/schemas/*.json "$schemas_local/"

echo "Copying client definitions..."
cp templates/noc/clients/*.json "$clients_local/"

echo "Copying manifest.json..."
cp templates/noc/manifest.json "$manifest_local"

echo
echo "Deploying to ${oderland_user}@${oderland_host}..."

execute_remote_command "mkdir -p \
    '$lib_remote' \
    '$static_remote' \
    '$icons_remote' \
    '$schemas_remote' \
    '$clients_remote' \
    '$(dirname "$endpoint_remote")' \
    '$(dirname "$config_remote")' \
    '$noc_root_dir'"

echo "Synchronising libraries to $lib_remote..."
execute_rsync \
    "$lib_local/" \
    "${oderland_user}@${oderland_host}:${lib_remote}/"

echo "Synchronising static files to $static_remote..."
execute_rsync \
    "$static_local/" \
    "${oderland_user}@${oderland_host}:${static_remote}/"

echo "Synchronising icons to $icons_remote..."
execute_rsync \
    "$icons_local/" \
    "${oderland_user}@${oderland_host}:${icons_remote}/"

echo "Synchronising schemas to $schemas_remote..."
execute_rsync \
    "$schemas_local/" \
    "${oderland_user}@${oderland_host}:${schemas_remote}/"

echo "Synchronising client definitions to $clients_remote..."
execute_rsync \
    "$clients_local/" \
    "${oderland_user}@${oderland_host}:${clients_remote}/"

echo "Uploading telemetry private config to $config_remote..."
execute_rsync \
    "$config_local" \
    "${oderland_user}@${oderland_host}:${config_remote}"

echo "Uploading telemetry endpoint to $endpoint_remote..."
execute_rsync \
    "$endpoint_local" \
    "${oderland_user}@${oderland_host}:${endpoint_remote}"

echo "Uploading manifest.json to $manifest_remote..."
execute_rsync \
    "$manifest_local" \
    "${oderland_user}@${oderland_host}:${manifest_remote}"

echo "Uploading dashboard to $dashboard_remote..."
execute_rsync \
    "$dashboard_local" \
    "${oderland_user}@${oderland_host}:${dashboard_remote}"

echo "Refreshing production fixtures..."
scripts/update-fixtures.sh --force

echo
echo "Opening dashboard..."
open "${NOC_DASHBOARD_URL:?Missing NOC_DASHBOARD_URL}"

echo
echo "KAWOOSH! 🚀"
afplay /System/Library/Sounds/Hero.aiff

exit 0
