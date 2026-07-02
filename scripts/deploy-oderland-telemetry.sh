#!/usr/bin/env bash

set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"

load_bredland_secrets

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

oderland_user="${ODERLAND_SSH_USER:?Missing ODERLAND_SSH_USER}"
oderland_host="${ODERLAND_SSH_HOST:?Missing ODERLAND_SSH_HOST}"

endpoint_remote="${TELEMETRY_ENDPOINT_FILE:?Missing TELEMETRY_ENDPOINT_FILE}"
config_remote="${TELEMETRY_CONFIG_FILE:?Missing TELEMETRY_CONFIG_FILE}"
libdir_remote="${NOC_LIB_DIR:?Missing NOC_LIB_DIR}"

endpoint_local="$tmpdir/telemetry.php"
config_local="$tmpdir/telemetry.config.php"
libdir_local="$tmpdir/lib"

echo "Rendering Oderland Telemetry endpoint..."
scripts/render-template.sh templates/oderland/telemetry.endpoint.template.php "$endpoint_local"

echo "Rendering Oderland Telemetry private config..."
scripts/render-template.sh templates/oderland/telemetry.config.template.php "$config_local"

echo "Copying endpoint libraries"
mkdir -p "$libdir_local"
cp templates/oderland/lib/*.php "$libdir_local/"

echo "Deploying to ${oderland_user}@${oderland_host}..."

echo "Uploading endpoint to $endpoint_remote..."
scp "$endpoint_local" "${oderland_user}@${oderland_host}:${endpoint_remote}"

echo "Uploading private config to $config_remote..."
scp "$config_local" "${oderland_user}@${oderland_host}:${config_remote}"

echo "Uploading libraries to $libdir_remote..."
env -u LC_CTYPE -u LC_ALL -u LANG ssh "${oderland_user}@${oderland_host}" "mkdir -p '$libdir_remote'"
scp "$libdir_local"/*.php "${oderland_user}@${oderland_host}:${libdir_remote}/"

echo -n "Verifying endpoint... "
env -u LC_CTYPE -u LC_ALL -u LANG ssh "${oderland_user}@${oderland_host}" "test -s '${endpoint_remote}'"
echo "OK"

echo -n "Verifying config... "
env -u LC_CTYPE -u LC_ALL -u LANG ssh "${oderland_user}@${oderland_host}" "test -s '${config_remote}'"
echo "OK"

echo "Verifying libraries..."
for lib_file in "$libdir_local"/*.php; do
    lib_name="$(basename "$lib_file")"
    echo -n "  $lib_name... "
    env -u LC_CTYPE -u LC_ALL -u LANG ssh "${oderland_user}@${oderland_host}" \
        "test -s '${libdir_remote}/${lib_name}'"
    echo "OK"
done

echo "Oderland Telemetry endpoint deployed."