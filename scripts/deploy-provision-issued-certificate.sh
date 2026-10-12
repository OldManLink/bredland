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
    "Rendering issued certificate provisioner" \
    render_executable \
    templates/bredland/provision-issued-certificate.sh.template \
    "$tmpdir/provision-issued-certificate"

run_step \
    "Uploading issued certificate provisioner" \
    execute_rsync \
    "$tmpdir/provision-issued-certificate" \
    "${bredland_host}:/tmp/provision-issued-certificate"

run_step \
    "Installing issued certificate provisioner" \
    execute_remote_command \
    "$bredland_host" \
    "sudo install -m 755 \
         /tmp/provision-issued-certificate \
         /usr/local/bin/provision-issued-certificate"

run_step \
    "Removing issued certificate provisioner staging file" \
    execute_remote_command \
    "$bredland_host" \
    "rm -f /tmp/provision-issued-certificate"

run_step \
    "Verifying issued certificate provisioner" \
    execute_remote_command \
    "$bredland_host" \
    "test -x /usr/local/bin/provision-issued-certificate"

echo
pass "Issued certificate provisioner deployed"
