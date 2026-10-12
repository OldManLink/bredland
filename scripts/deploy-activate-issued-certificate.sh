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
    "Rendering issued certificate activator" \
    render_executable \
    templates/bredland/activate-issued-certificate.sh.template \
    "$tmpdir/activate-issued-certificate"

run_step \
    "Uploading issued certificate activator" \
    execute_rsync \
    "$tmpdir/activate-issued-certificate" \
    "${bredland_host}:/tmp/activate-issued-certificate"

run_step \
    "Installing issued certificate activator" \
    execute_remote_command \
    "$bredland_host" \
    "sudo install -m 755 \
         /tmp/activate-issued-certificate \
         /usr/local/bin/activate-issued-certificate"

run_step \
    "Removing issued certificate activator staging file" \
    execute_remote_command \
    "$bredland_host" \
    "rm -f /tmp/activate-issued-certificate"

run_step \
    "Verifying issued certificate activator" \
    execute_remote_command \
    "$bredland_host" \
    "test -x /usr/local/bin/activate-issued-certificate"

echo
pass "Issued certificate activator deployed"
