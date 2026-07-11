#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"

load_bredland_secrets

template="templates/mikrotik/install-noc-heartbeat.rsc.template"
rendered="/tmp/install-noc-heartbeat.rsc"
remote_file="install-noc-heartbeat.rsc"

script_name="telemetry-heartbeat"
scheduler_name="telemetry-heartbeat-5m"

router_user="${MIKROTIK_SSH_USER:?Missing MIKROTIK_SSH_USER}"
router_host="${MIKROTIK_SSH_HOST:?Missing MIKROTIK_SSH_HOST}"
router="${router_user}@${router_host}"

pass() {
    echo "✅ $1"
}

fail() {
    echo "❌ $1" >&2
    exit 1
}

verify_routeros() {
    local description="$1"
    local command="$2"
    local output

    if ! output="$(ssh "$router" "$command" 2>&1)"; then
        echo "$output" >&2
        fail "$description"
    fi

    if grep -q 'VERIFY_OK' <<<"$output"; then
        pass "$description"
    else
        echo "$output" >&2
        fail "$description"
    fi
}

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
    "Heartbeat script found" \
    ":if ([:len [/system script find name=\"${script_name}\"]] > 0) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
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