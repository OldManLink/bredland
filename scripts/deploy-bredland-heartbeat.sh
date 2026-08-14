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
    "Rendering Bredland heartbeat script" \
    render_executable \
    templates/bredland/bredland-heartbeat.sh.template \
    "$tmpdir/bredland-heartbeat"

run_step \
    "Rendering Bredland heartbeat service" \
    scripts/render-template.sh \
    templates/bredland/bredland-heartbeat.service.template \
    "$tmpdir/bredland-heartbeat.service"

run_step \
    "Rendering Bredland heartbeat timer" \
    scripts/render-template.sh \
    templates/bredland/bredland-heartbeat.timer.template \
    "$tmpdir/bredland-heartbeat.timer"

run_step \
    "Uploading Bredland heartbeat script" \
    execute_rsync \
    "$tmpdir/bredland-heartbeat" \
    "${bredland_host}:/tmp/bredland-heartbeat"

run_step \
    "Uploading Bredland heartbeat service" \
    execute_rsync \
    "$tmpdir/bredland-heartbeat.service" \
    "${bredland_host}:/tmp/bredland-heartbeat.service"

run_step \
    "Uploading Bredland heartbeat timer" \
    execute_rsync \
    "$tmpdir/bredland-heartbeat.timer" \
    "${bredland_host}:/tmp/bredland-heartbeat.timer"

run_step \
    "Installing Bredland heartbeat" \
    execute_remote_command \
    "$bredland_host" \
    "sudo install -m 755 /tmp/bredland-heartbeat /usr/local/bin/bredland-heartbeat &&
     sudo install -m 644 /tmp/bredland-heartbeat.service /etc/systemd/system/bredland-heartbeat.service &&
     sudo install -m 644 /tmp/bredland-heartbeat.timer /etc/systemd/system/bredland-heartbeat.timer &&
     sudo systemctl daemon-reload &&
     sudo systemctl restart bredland-heartbeat.timer &&
     sudo systemctl enable --now bredland-heartbeat.timer"

run_step \
    "Verifying Bredland heartbeat timer" \
    execute_remote_command \
    "$bredland_host" \
    "systemctl is-active --quiet bredland-heartbeat.timer &&
     systemctl is-enabled --quiet bredland-heartbeat.timer"

echo
pass "Bredland heartbeat deployed"
