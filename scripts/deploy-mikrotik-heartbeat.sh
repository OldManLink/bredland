#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"
# shellcheck source=scripts/lib/deploy.sh
source "$(dirname "$0")/lib/deploy.sh"
# shellcheck source=scripts/lib/mikrotik.sh
source "$(dirname "$0")/lib/mikrotik.sh"

load_bredland_secrets

tmpdir="$(mktemp -d)"
cleanup() {
    rm -rf "$tmpdir"

    ssh "$router" \
        ":foreach id in=[/file find name=\"${remote_file}\"] do={ /file remove \$id }" \
        >/dev/null 2>&1 || true
}

template="templates/mikrotik/install-noc-heartbeat.rsc.template"
rendered="${tmpdir}/install-noc-heartbeat.rsc"
remote_file="install-noc-heartbeat.rsc"

script_name="telemetry-heartbeat"
scheduler_name="telemetry-heartbeat-5m"

router_user="${MIKROTIK_SSH_USER:?Missing MIKROTIK_SSH_USER}"
router_host="${MIKROTIK_SSH_HOST:?Missing MIKROTIK_SSH_HOST}"
router="${router_user}@${router_host}"

trap cleanup EXIT

echo "Rendering MikroTik heartbeat installer..."
if scripts/render-template.sh "$template" "$rendered"; then
    pass "Installer rendered"
else
    fail "Rendering installer"
fi

echo "Uploading to MikroTik..."
if scp "$rendered" "${router}:${remote_file}"; then
    pass "Installer uploaded"
else
    fail "Uploading installer"
fi

echo "Importing on MikroTik..."
if ssh "$router" "/import file-name=${remote_file}"; then
    pass "Installer imported"
else
    fail "Importing installer"
fi

verify_routeros \
    "$router" \
    "Heartbeat script found" \
    ":if ([:len [/system script find name=\"${script_name}\"]] > 0) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "Heartbeat scheduler found" \
    ":if ([:len [/system scheduler find name=\"${scheduler_name}\"]] > 0) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

echo "Cleaning up uploaded installer..."
if ssh "$router" "/file remove ${remote_file}"; then
    pass "Uploaded installer removed"
else
    fail "Removing uploaded installer"
fi

echo
echo "✅ MikroTik heartbeat deployed."