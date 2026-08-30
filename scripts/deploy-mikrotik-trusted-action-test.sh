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

template="templates/mikrotik/install-noc-trusted-action-test.rsc.template"
rendered="${tmpdir}/install-noc-trusted-action-test.rsc"
remote_file="install-noc-trusted-action-test.rsc"

script_name="noc-trusted-action-test"
log_message="[NOC-TRUSTED-TEST] trusted action test invoked"

router_user="${MIKROTIK_SSH_USER:?Missing MIKROTIK_SSH_USER}"
router_host="${MIKROTIK_SSH_HOST:?Missing MIKROTIK_SSH_HOST}"
router="${router_user}@${router_host}"

trap cleanup EXIT

echo "Rendering MikroTik trusted action test installer..."
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
    "Trusted action test script found" \
    ":if ([:len [/system script find name=\"${script_name}\"]] > 0) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "Trusted action test script bypasses caller permissions" \
    ":local id [/system script find name=\"${script_name}\"]; :if ([/system script get \$id dont-require-permissions] = true) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "Trusted action test script has expected log marker" \
    ":local id [/system script find name=\"${script_name}\"]; :local source [/system script get \$id source]; :if ([:find \$source \"NOC-TRUSTED-TEST\"] != nil) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

echo "Executing trusted action test script..."
before_count="$(
    ssh "$router" \
        ":put [:len [/log find where message=\"${log_message}\"]]" \
        | tr -d '\r'
)"

ssh "$router" \
    "/system script run [find name=\"${script_name}\"]"

after_count="$(
    ssh "$router" \
        ":put [:len [/log find where message=\"${log_message}\"]]" \
        | tr -d '\r'
)"

if [[ "$before_count" =~ ^[0-9]+$ ]] \
    && [[ "$after_count" =~ ^[0-9]+$ ]] \
    && (( after_count == before_count + 1 )); then
    pass "Trusted action test produced expected log entry"
else
    echo "Matching log entries before: $before_count" >&2
    echo "Matching log entries after:  $after_count" >&2
    fail "Verifying trusted action test log entry"
fi

echo "Cleaning up uploaded installer..."
if ssh "$router" "/file remove ${remote_file}"; then
    pass "Uploaded installer removed"
else
    fail "Removing uploaded installer"
fi

echo
echo "✅ MikroTik trusted action test deployed."
