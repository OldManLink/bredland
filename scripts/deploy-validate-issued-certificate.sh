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
    "Rendering issued certificate validator" \
    render_executable \
    templates/bredland/validate-issued-certificate.sh.template \
    "$tmpdir/validate-issued-certificate"

run_step \
    "Uploading issued certificate validator" \
    execute_rsync \
    "$tmpdir/validate-issued-certificate" \
    "${bredland_host}:/tmp/validate-issued-certificate"

run_step \
    "Installing issued certificate validator" \
    execute_remote_command \
    "$bredland_host" \
    "sudo install -m 755 \
         /tmp/validate-issued-certificate \
         /usr/local/bin/validate-issued-certificate"

run_step \
    "Removing issued certificate validator staging file" \
    execute_remote_command \
    "$bredland_host" \
    "rm -f /tmp/validate-issued-certificate"

run_step \
    "Verifying issued certificate validator" \
    execute_remote_command \
    "$bredland_host" \
    "test -x /usr/local/bin/validate-issued-certificate"

echo
pass "Issued certificate validator deployed"
