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
rotate_script_daily_name="${NOC_ROTATE_SCRIPT_DAILY_FILE:?Missing NOC_ROTATE_SCRIPT_DAILY_FILE}"
consolidate_script_monthly_name="${NOC_CONSOLIDATE_SCRIPT_MONTHLY_FILE:?Missing NOC_CONSOLIDATE_SCRIPT_MONTHLY_FILE}"

local_script_daily="$tmpdir/$rotate_script_daily_name"
local_script_monthly="$tmpdir/$consolidate_script_monthly_name"
remote_script_daily="$noc_bin_dir/$rotate_script_daily_name"
remote_script_monthly="$noc_bin_dir/$consolidate_script_monthly_name"

cron_begin="# BEGIN BREDLAND NOC"
cron_end="# END BREDLAND NOC"
cron_line_daily="10 3 * * * $remote_script_daily"
cron_line_monthly="30 3 1 * * $remote_script_monthly"

echo "Rendering NOC daily log rotation script ..."
scripts/render-template.sh \
  templates/noc/rotate-daily-logs.sh.template \
  "$local_script_daily"

echo "Rendering NOC monthly log consolidation script ..."
scripts/render-template.sh \
  templates/noc/consolidate-monthly-logs.sh.template \
  "$local_script_monthly"

echo "Deploying to ${oderland_user}@${oderland_host}..."

echo "Uploading script ..."
execute_remote_command "mkdir -p '$noc_bin_dir'"
scp "$local_script_daily" "${oderland_user}@${oderland_host}:${remote_script_daily}"
scp "$local_script_monthly" "${oderland_user}@${oderland_host}:${remote_script_monthly}"

echo -n "Making scripts executable ... "
execute_remote_command "chmod +x '$remote_script_daily'"
execute_remote_command "chmod +x '$remote_script_monthly'"
echo "OK"

# Remove any existing managed cron block, trim trailing blank lines,
# then append exactly one blank line followed by the managed block.
echo "Installing cron entry ..."
execute_remote_command "tmp=\$(mktemp); \
   crontab -l 2>/dev/null \
     | sed '/^${cron_begin}$/,/^${cron_end}$/d' \
     | sed -e :a -e '/^[[:space:]]*$/{\$d;N;ba' -e '}' > \"\$tmp\"; \
   printf '\n%s\n%s\n%s\n%s\n' '${cron_begin}' '${cron_line_daily}' '${cron_line_monthly}' '${cron_end}' >> \"\$tmp\"; \
   crontab \"\$tmp\"; \
   rm -f \"\$tmp\""

echo -n "Verifying scripts ... "
execute_remote_command "test -x '$remote_script_daily'"
execute_remote_command "test -x '$remote_script_monthly'"
echo "OK"

echo -n "Verifying cron entry... "
execute_remote_command  "crontab -l | grep -Fx '${cron_begin}' >/dev/null &&
   crontab -l | grep -Fx '${cron_line_daily}' >/dev/null &&
   crontab -l | grep -Fx '${cron_line_monthly}' >/dev/null &&
   crontab -l | grep -Fx '${cron_end}' >/dev/null"
echo "OK"

echo "Oderland NOC log rotation deployed."
