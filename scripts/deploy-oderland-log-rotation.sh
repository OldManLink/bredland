#!/usr/bin/env bash

set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"

load_bredland_secrets

command -v ssh >/dev/null
command -v scp >/dev/null

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

oderland_user="${ODERLAND_SSH_USER:?Missing ODERLAND_SSH_USER}"
oderland_host="${ODERLAND_SSH_HOST:?Missing ODERLAND_SSH_HOST}"
noc_bin_dir="${NOC_BIN_DIR:?Missing NOC_BIN_DIR}"

local_script="$tmpdir/compress-noc-logs.sh"
remote_script="$noc_bin_dir/compress-noc-logs.sh"

cron_begin="# BEGIN BREDLAND BRD-002"
cron_end="# END BREDLAND BRD-002"
cron_line="10 2 * * * $remote_script"

echo "Rendering NOC log compression script..."
scripts/render-template.sh \
  templates/oderland/compress-noc-logs.sh.template \
  "$local_script"

echo "Deploying to ${oderland_user}@${oderland_host}..."

echo "Uploading script..."
scp "$local_script" "${oderland_user}@${oderland_host}:${remote_script}"

echo -n "Making script executable... "
env -u LC_CTYPE -u LC_ALL -u LANG \
  ssh "${oderland_user}@${oderland_host}" \
  "chmod +x '$remote_script'"
echo "OK"

echo "Installing cron entry..."
env -u LC_CTYPE -u LC_ALL -u LANG \
  ssh "${oderland_user}@${oderland_host}" \
  "tmp=\$(mktemp); \
   crontab -l 2>/dev/null | sed '/^${cron_begin}$/,/^${cron_end}$/d' > \"\$tmp\"; \
   printf '%s\n%s\n%s\n' '${cron_begin}' '${cron_line}' '${cron_end}' >> \"\$tmp\"; \
   crontab \"\$tmp\"; \
   rm -f \"\$tmp\""

echo -n "Verifying script... "
env -u LC_CTYPE -u LC_ALL -u LANG \
  ssh "${oderland_user}@${oderland_host}" \
  "test -x '$remote_script'"
echo "OK"

echo -n "Verifying cron entry... "
env -u LC_CTYPE -u LC_ALL -u LANG \
  ssh "${oderland_user}@${oderland_host}" \
  "crontab -l | grep -q '^${cron_begin}$'"
echo "OK"

echo "Oderland NOC log rotation deployed."
