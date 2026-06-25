#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"

load_bredland_secrets

template="mikrotik/install-noc-heartbeat.rsc.template"
rendered="/tmp/install-noc-heartbeat.rsc"
remote_file="install-noc-heartbeat.rsc"

router_user="${MIKROTIK_SSH_USER:?Missing MIKROTIK_SSH_USER}"
router_host="${MIKROTIK_SSH_HOST?Missing MIKROTIK_SSH_HOST}"

echo "Rendering MikroTik heartbeat installer..."
scripts/render-template.sh "$template" "$rendered"

echo "Uploading to MikroTik..."
scp "$rendered" "${router_user}@${router_host}:${remote_file}"

echo "Importing on MikroTik..."
ssh "${router_user}@${router_host}" "/import file-name=${remote_file}"

echo -n "Verifying script... "
ssh "${router_user}@${router_host}" \
  ':if ([:len [/system script find name="noc-heartbeat"]] = 0) do={ :error "script not found" }'
echo "OK"

echo -n "Verifying scheduler... "
ssh "${router_user}@${router_host}" \
  ':if ([:len [/system scheduler find name="noc-heartbeat"]] = 0) do={ :error "scheduler not found" }'
echo "OK"

echo "Cleaning up uploaded installer..."
ssh "${router_user}@${router_host}" "/file remove ${remote_file}"

echo "MikroTik heartbeat deployed."
