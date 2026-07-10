#!/usr/bin/env bash

set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"
# shellcheck source=scripts/lib/utils.sh
source "$(dirname "$0")/lib/utils.sh"

load_bredland_secrets

export SMOKE_TEST_HOST_TOKEN_LINE=
if [[ "${SMOKE_TEST_DEPLOY:-0}" == "1" ]]; then
    enable_smoke_deploy
fi

command -v ssh >/dev/null
command -v scp >/dev/null
command -v rsync >/dev/null

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

oderland_user="${ODERLAND_SSH_USER:?Missing ODERLAND_SSH_USER}"
oderland_host="${ODERLAND_SSH_HOST:?Missing ODERLAND_SSH_HOST}"

endpoint_remote="${TELEMETRY_ENDPOINT_FILE:?Missing TELEMETRY_ENDPOINT_FILE}"
endpoint_dir="$(dirname "$endpoint_remote")"
endpoint_local="$tmpdir/telemetry.php"
config_local="$tmpdir/telemetry.config.php"
config_remote="${TELEMETRY_CONFIG_FILE:?Missing TELEMETRY_CONFIG_FILE}"
libdir_local="$tmpdir/lib"
libdir_remote="$endpoint_dir/lib"
schemas_local="$tmpdir/schemas"
schemas_remote="$endpoint_dir/schemas"

echo "Rendering Oderland Telemetry endpoint..."
scripts/render-template.sh templates/noc/telemetry.endpoint.template.php "$endpoint_local"

echo "Rendering Oderland Telemetry private config..."
scripts/render-template.sh templates/noc/telemetry.config.template.php "$config_local"

echo "Copying endpoint libraries"
mkdir -p "$libdir_local"
cp templates/noc/lib/*.php "$libdir_local/"

echo "Copying heartbeat schemas"
mkdir -p "$schemas_local"
cp templates/noc/schemas/*.json "$schemas_local/"

echo "Deploying to ${oderland_user}@${oderland_host}..."

echo "Synchronising libraries to $libdir_remote..."
execute_remote_command "mkdir -p '$libdir_remote'"
execute_rsync "$libdir_local/" "${oderland_user}@${oderland_host}:${libdir_remote}/"

echo "Verifying libraries..."
for lib_file in "$libdir_local"/*.php; do
    lib_name="$(basename "$lib_file")"
    echo -n "  $lib_name... "
    execute_remote_command "test -s '${libdir_remote}/${lib_name}'"
    echo "OK"
done

echo "Synchronising schemas to $schemas_remote..."
execute_remote_command "mkdir -p '$schemas_remote'"
execute_rsync "$schemas_local/" "${oderland_user}@${oderland_host}:${schemas_remote}/"

echo "Verifying schemas..."
for schema_file in "$schemas_local"/*.json; do
    schema_name="$(basename "$schema_file")"
    echo -n "  $schema_name... "
    execute_remote_command "test -s '${schemas_remote}/${schema_name}'"
    echo "OK"
done

echo "Uploading private config to $config_remote..."
scp "$config_local" "${oderland_user}@${oderland_host}:${config_remote}"

echo -n "Verifying config... "
execute_remote_command "test -s '${config_remote}'"
echo "OK"

echo "Uploading endpoint to $endpoint_remote..."
scp "$endpoint_local" "${oderland_user}@${oderland_host}:${endpoint_remote}"

echo -n "Verifying endpoint... "
execute_remote_command "test -s '${endpoint_remote}'"
echo "OK"

echo "Oderland Telemetry endpoint deployed."