#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"

load_bredland_secrets

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

bredland_host="${BREDLAND_SSH_HOST:?Missing BREDLAND_SSH_HOST}"

echo "Rendering Bredland heartbeat files..."
scripts/render-template.sh \
  templates/bredland/bredland-heartbeat.sh.template \
  "$tmpdir/bredland-heartbeat"

scripts/render-template.sh \
  templates/bredland/bredland-heartbeat.service.template \
  "$tmpdir/bredland-heartbeat.service"

scripts/render-template.sh \
  templates/bredland/bredland-heartbeat.timer.template \
  "$tmpdir/bredland-heartbeat.timer"

echo "Uploading to Bredland..."
scp "$tmpdir/bredland-heartbeat" "$bredland_host:/tmp/"
scp "$tmpdir/bredland-heartbeat.service" "$bredland_host:/tmp/"
scp "$tmpdir/bredland-heartbeat.timer" "$bredland_host:/tmp/"

echo "Installing on Bredland..."
ssh "$bredland_host" <<'EOF'
set -euo pipefail

sudo install -m 755 /tmp/bredland-heartbeat /usr/local/bin/bredland-heartbeat
sudo install -m 644 /tmp/bredland-heartbeat.service /etc/systemd/system/bredland-heartbeat.service
sudo install -m 644 /tmp/bredland-heartbeat.timer /etc/systemd/system/bredland-heartbeat.timer

sudo systemctl daemon-reload
sudo systemctl restart bredland-heartbeat.timer
sudo systemctl enable --now bredland-heartbeat.timer

systemctl status --no-pager bredland-heartbeat.timer
EOF

echo "Bredland heartbeat deployed."