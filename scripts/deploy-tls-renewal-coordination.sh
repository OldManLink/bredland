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

scripts=(
    start-renewal
    certbot-renewal-worker
    certbot-auth-hook
    continue-renewal
    wait-renewal
    cancel-renewal
)

for script in "${scripts[@]}"; do
    run_step \
        "Rendering $script" \
        render_executable \
        "templates/bredland/$script.sh.template" \
        "$tmpdir/$script"

    run_step \
        "Uploading $script" \
        execute_rsync \
        "$tmpdir/$script" \
        "${bredland_host}:/tmp/$script"

    run_step \
        "Installing $script" \
        execute_remote_command \
        "$bredland_host" \
        "sudo install -m 755 \
             /tmp/$script \
             /usr/local/bin/$script"

    run_step \
        "Removing $script staging file" \
        execute_remote_command \
        "$bredland_host" \
        "rm -f /tmp/$script"

    run_step \
        "Verifying $script" \
        execute_remote_command \
        "$bredland_host" \
        "test -x /usr/local/bin/$script"
done

echo
pass "TLS renewal coordination deployed"
