#!/usr/bin/env bash

set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"
# shellcheck source=scripts/lib/utils.sh
source "$(dirname "$0")/lib/utils.sh"

load_bredland_secrets

command -v ssh >/dev/null
command -v scp >/dev/null
command -v rsync >/dev/null

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

oderland_user="${ODERLAND_SSH_USER:?Missing ODERLAND_SSH_USER}"
oderland_host="${ODERLAND_SSH_HOST:?Missing ODERLAND_SSH_HOST}"
noc_root_dir="${NOC_ROOT_DIR:?Missing NOC_ROOT_DIR}"

dashboard_local="$tmpdir/index.php"
dashboard_remote="$noc_root_dir/index.php"
manifest_local="$tmpdir/manifest.json"
manifest_remote="$noc_root_dir/manifest.json"
static_local="$tmpdir/static"
static_remote="$noc_root_dir/static"
libdir_local="$tmpdir/lib"
libdir_remote="$noc_root_dir/lib"
schemas_local="$tmpdir/schemas"
schemas_remote="$noc_root_dir/schemas"
icons_local="$tmpdir/icons"
icons_remote="$noc_root_dir/icons"
clients_local="$tmpdir/clients"
clients_remote="$noc_root_dir/clients"

version_file="templates/noc/static/static.version"

current="$(cat "$version_file")"
next="$((current + 1))"
# static.version is intentionally source-controlled project state.
# Deploy increments it so the rendered dashboard always references a
# fresh static asset version, and the repository records the last deployed
# asset version for the next manual coding session.
printf '%s\n' "$next" > "$version_file"

export STATIC_VERSION="$next"

echo "Rendering NOC dashboard..."
scripts/render-template.sh templates/noc/index.template.php "$dashboard_local"

echo "Copying static files"
mkdir -p "$static_local"
cp templates/noc/static/* "$static_local/"

echo "Copying endpoint libraries"
mkdir -p "$libdir_local"
cp templates/noc/lib/*.php "$libdir_local/"

echo "Copying heartbeat schemas"
mkdir -p "$schemas_local"
cp templates/noc/schemas/*.json "$schemas_local/"

echo "Copying client definitions"
mkdir -p "$clients_local"
cp templates/noc/clients/*.json "$clients_local/"

echo "Copying manifest.json"
cp templates/noc/manifest.json "$manifest_local"

echo "Copying icons"
mkdir -p "$icons_local"
cp templates/noc/icons/* "$icons_local/"

echo "Deploying NOC dashboard to ${ODERLAND_SSH_USER}@${ODERLAND_SSH_HOST}..."

echo "Synchronising static files to $static_remote..."
execute_remote_command "mkdir -p '$static_remote'"
execute_rsync "$static_local/" "${oderland_user}@${oderland_host}:${static_remote}/"

echo "Verifying static files..."
for static_file in "$static_local"/*; do
    static_name="$(basename "$static_file")"
    echo -n "  $static_name... "
    execute_remote_command "test -s '${static_remote}/${static_name}'"
    echo "OK"
done

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

echo "Synchronising client definitions to $clients_remote..."
execute_remote_command "mkdir -p '$clients_remote'"
execute_rsync "$clients_local/" "${oderland_user}@${oderland_host}:${clients_remote}/"

echo "Verifying client definitions..."
for client_file in "$clients_local"/*.json; do
    client_name="$(basename "$client_file")"
    echo -n "  $client_name... "
    execute_remote_command "test -s '${clients_remote}/${client_name}'"
    echo "OK"
done

echo "Uploading manifest.json to $manifest_remote..."
scp "$manifest_local" "${oderland_user}@${oderland_host}:${manifest_remote}"

echo -n "Verifying manifest.json... "
execute_remote_command "test -s '${manifest_remote}'"
echo "OK"

echo "Uploading icons to $icons_remote"
execute_remote_command "mkdir -p '$icons_remote'"
execute_rsync "$icons_local/" "${oderland_user}@${oderland_host}:${icons_remote}/"

echo "Verifying icons..."
for icon_file in "$icons_local"/*; do
    icon_name="$(basename "$icon_file")"
    echo -n "  $icon_name "
    execute_remote_command "test -s '${icons_remote}/${icon_name}'"
    echo "OK"
done

echo "Uploading dashboard to $dashboard_remote..."
scp "$dashboard_local" "${oderland_user}@${oderland_host}:${dashboard_remote}"

echo -n "Verifying dashboard... "
execute_remote_command "test -s '${dashboard_remote}'"
echo "OK"

echo "Opening dashboard..."
open "${NOC_DASHBOARD_URL:?Missing NOC_DASHBOARD_URL}"

echo "NOC dashboard deployed."
