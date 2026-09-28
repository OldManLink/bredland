#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"
# shellcheck source=scripts/lib/utils.sh
source "$(dirname "$0")/lib/utils.sh"
# shellcheck source=scripts/lib/deploy.sh
source "$(dirname "$0")/lib/deploy.sh"

load_bredland_secrets

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

bredland_host="${BREDLAND_SSH_HOST:?Missing BREDLAND_SSH_HOST}"

run_step \
    "Rendering One Shot Miner heartbeat script" \
    render_executable \
    templates/osm/osm-heartbeat.sh.template \
    "$tmpdir/osm-heartbeat"

run_step \
    "Uploading One Shot Miner collector" \
    execute_rsync \
    templates/osm/osm_collector.py \
    "${bredland_host}:/tmp/osm_collector.py"

run_step \
    "Rendering One Shot Miner heartbeat service" \
    scripts/render-template.sh \
    templates/osm/osm-heartbeat.service.template \
    "$tmpdir/osm-heartbeat.service"

run_step \
    "Rendering One Shot Miner heartbeat timer" \
    scripts/render-template.sh \
    templates/osm/osm-heartbeat.timer.template \
    "$tmpdir/osm-heartbeat.timer"

run_step \
    "Uploading One Shot Miner heartbeat script" \
    execute_rsync \
    "$tmpdir/osm-heartbeat" \
    "${bredland_host}:/tmp/osm-heartbeat"

run_step \
    "Uploading One Shot Miner heartbeat service" \
    execute_rsync \
    "$tmpdir/osm-heartbeat.service" \
    "${bredland_host}:/tmp/osm-heartbeat.service"

run_step \
    "Uploading One Shot Miner heartbeat timer" \
    execute_rsync \
    "$tmpdir/osm-heartbeat.timer" \
    "${bredland_host}:/tmp/osm-heartbeat.timer"

run_step \
    "Ensuring Bredland trusted service account" \
    ensure_bredland_trusted_user \
    "$bredland_host"

run_step \
    "Installing One Shot Miner heartbeat" \
    execute_remote_command \
    "$bredland_host" \
    "sudo install -m 755 /tmp/osm-heartbeat /usr/local/bin/osm-heartbeat &&
     sudo mkdir -p /usr/local/lib/bredland/osm &&
     sudo install -m 755 /tmp/osm_collector.py ${OSM_COLLECTOR_SCRIPT_FILE} &&
     sudo install -m 644 /tmp/osm-heartbeat.service /etc/systemd/system/osm-heartbeat.service &&
     sudo install -m 644 /tmp/osm-heartbeat.timer /etc/systemd/system/osm-heartbeat.timer &&
     sudo systemctl daemon-reload &&
     sudo systemctl restart osm-heartbeat.timer &&
     sudo systemctl enable --now osm-heartbeat.timer"

run_step \
    "Verifying One Shot Miner heartbeat timer" \
    execute_remote_command \
    "$bredland_host" \
    "systemctl is-active --quiet osm-heartbeat.timer &&
     systemctl is-enabled --quiet osm-heartbeat.timer"

echo
pass "One Shot Miner heartbeat deployed"
