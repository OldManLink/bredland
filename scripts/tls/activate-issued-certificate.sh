#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/../lib/bredland.sh"
# shellcheck source=scripts/lib/utils.sh
source "$(dirname "$0")/../lib/utils.sh"

load_bredland_secrets

bredland_host="${BREDLAND_SSH_HOST:?Missing BREDLAND_SSH_HOST}"

execute_remote_command \
    "$bredland_host" \
    "sudo /usr/local/bin/activate-issued-certificate"
