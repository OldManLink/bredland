#!/usr/bin/env bash

set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"
# shellcheck source=scripts/lib/utile.sh
source "$(dirname "$0")/lib/utils.sh"

load_bredland_secrets

command -v ssh >/dev/null
command -v scp >/dev/null

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

oderland_user="${ODERLAND_SSH_USER:?Missing ODERLAND_SSH_USER}"
oderland_host="${ODERLAND_SSH_HOST:?Missing ODERLAND_SSH_HOST}"
noc_bin_dir="${NOC_BIN_DIR:?Missing NOC_BIN_DIR}"
rotate_script_name="${NOC_ROTATE_SCRIPT_FILE:?Missing NOC_ROTATE_SCRIPT_FILE}"

local_script="$tmpdir/$rotate_script_name"
remote_script="$noc_bin_dir/$rotate_script_name"

cron_begin="# BEGIN BREDLAND BRD-002"
cron_end="# END BREDLAND BRD-002"
cron_line="10 2 * * * $remote_script"

echo "Rendering NOC daily log rotation script..."
scripts/render-template.sh \
  templates/noc/rotate-daily-logs.sh.template \
  "$local_script"

echo "Deploying to ${oderland_user}@${oderland_host}..."

echo "Uploading script..."
execute_remote_command "mkdir -p '$noc_bin_dir'"
scp "$local_script" "${oderland_user}@${oderland_host}:${remote_script}"

echo -n "Making script executable... "
execute_remote_command "chmod +x '$remote_script'"
echo "OK"

echo "Installing cron entry..."
execute_remote_command "tmp=\$(mktemp); \
   crontab -l 2>/dev/null | sed '/^${cron_begin}$/,/^${cron_end}$/d' > \"\$tmp\"; \
   printf '%s\n%s\n%s\n' '${cron_begin}' '${cron_line}' '${cron_end}' >> \"\$tmp\"; \
   crontab \"\$tmp\"; \
   rm -f \"\$tmp\""

echo -n "Verifying script... "
execute_remote_command "test -x '$remote_script'"
echo "OK"

echo -n "Verifying cron entry... "
execute_remote_command  "crontab -l | grep -Fx '${cron_begin}' >/dev/null &&
   crontab -l | grep -Fx '${cron_line}' >/dev/null &&
   crontab -l | grep -Fx '${cron_end}' >/dev/null"
echo "OK"

echo "Oderland NOC log rotation deployed."
