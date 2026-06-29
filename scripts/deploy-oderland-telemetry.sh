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

endpoint_local="$tmpdir/telemetry.php"
config_local="$tmpdir/telemetry.config.php"

echo "Rendering Oderland Telemetry endpoint..."
scripts/render-template.sh templates/oderland/telemetry.endpoint.template.php "$endpoint_local"

echo "Rendering Oderland Telemetry private config..."
scripts/render-template.sh templates/oderland/telemetry.config.template.php "$config_local"

echo "Deploying to ${oderland_user}@${oderland_host}..."

echo "Uploading endpoint..."
scp "$endpoint_local" "${oderland_user}@${oderland_host}:${endpoint_remote}"

echo "Uploading private config..."
scp "$config_local" "${oderland_user}@${oderland_host}:${config_remote}"

echo -n "Verifying endpoint... "
env -u LC_CTYPE -u LC_ALL -u LANG ssh "${oderland_user}@${oderland_host}" "test -s '${endpoint_remote}'"
echo "OK"

echo -n "Verifying config... "
env -u LC_CTYPE -u LC_ALL -u LANG ssh "${oderland_user}@${oderland_host}" "test -s '${config_remote}'"
echo "OK"

echo "Oderland Telemetry endpoint deployed."