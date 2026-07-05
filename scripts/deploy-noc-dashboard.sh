#!/usr/bin/env bash

set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"
# shellcheck source=scripts/lib/utils.sh
source "$(dirname "$0")/lib/utils.sh"

load_bredland_secrets

command -v ssh >/dev/null
command -v scp >/dev/null

tmpdir="$(mktemp -d)"
cleanup() {
    rm -rf "$tmpdir"
}
trap cleanup EXIT

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
icons_local="$tmpdir/icons"
icons_remote="$noc_root_dir/icons"

version_file="templates/noc/static/static.version"

current="$(cat "$version_file")"
next="$((current + 1))"
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

echo "Copying manifest.json"
cp templates/noc/manifest.json "$manifest_local"

echo "Copying icons"
mkdir -p "$icons_local"
cp templates/noc/icons/* "$icons_local/"

echo "Deploying NOC dashboard to ${ODERLAND_SSH_USER}@${ODERLAND_SSH_HOST}..."

echo "Uploading static files to $static_remote..."
execute_remote_command "mkdir -p '$static_remote'"
scp "$static_local"/* "${oderland_user}@${oderland_host}:${static_remote}/"

echo "Verifying static files..."
for static_file in "$static_local"/*; do
    static_name="$(basename "$static_file")"
    echo -n "  $static_name... "
    execute_remote_command "test -s '${static_remote}/${static_name}'"
    echo "OK"
done

echo "Uploading libraries to $libdir_remote..."
execute_remote_command "mkdir -p '$libdir_remote'"
scp "$libdir_local"/*.php "${oderland_user}@${oderland_host}:${libdir_remote}/"

echo "Verifying libraries..."
for lib_file in "$libdir_local"/*.php; do
    lib_name="$(basename "$lib_file")"
    echo -n "  $lib_name... "
    execute_remote_command "test -s '${libdir_remote}/${lib_name}'"
    echo "OK"
done


echo "Uploading manifest.json to $manifest_remote..."
scp "$manifest_local" "${oderland_user}@${oderland_host}:${manifest_remote}"

echo -n "Verifying manifest.json... "
execute_remote_command "test -s '${manifest_remote}'"
echo "OK"

echo "Uploading icons to $icons_remote"
execute_remote_command "mkdir -p '$icons_remote'"
scp "$icons_local"/* "${oderland_user}@${oderland_host}:${icons_remote}/"

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
