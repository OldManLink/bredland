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

template="templates/mikrotik/install-noc-routeros-staged.rsc.template"
rendered="${tmpdir}/install-noc-routeros-staged.rsc"
remote_file="install-noc-routeros-staged.rsc"

script_name="noc-routeros-staged"

router_user="${MIKROTIK_SSH_USER:?Missing MIKROTIK_SSH_USER}"
router_host="${MIKROTIK_SSH_HOST:?Missing MIKROTIK_SSH_HOST}"
router="${router_user}@${router_host}"

cleanup()
{
    rm -rf "$tmpdir"

    ssh "$router" \
        ":foreach id in=[/file find name=\"${remote_file}\"] do={ /file remove \$id }" \
        >/dev/null 2>&1 || true
}

trap cleanup EXIT

run_step \
    "Render MikroTik RouterOS staged-state installer" \
    scripts/render-template.sh \
    "$template" \
    "$rendered"

run_step \
    "Upload MikroTik RouterOS staged-state installer" \
    scp \
    "$rendered" \
    "${router}:${remote_file}"

run_step \
    "Import MikroTik RouterOS staged-state installer" \
    ssh \
    "$router" \
    "/import file-name=${remote_file}"

verify_routeros \
    "$router" \
    "RouterOS staged-state script found" \
    ":if ([:len [/system script find name=\"${script_name}\"]] > 0) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "RouterOS staged-state script has expected source" \
    ":local id [/system script find name=\"${script_name}\"]; \
     :local source [/system script get \$id source]; \
     :local expected \":local update [/system package update print as-value]\r\n:local status (\\\$update->\\\"status\\\")\r\n:return (\\\$status = \\\"Downloaded, please reboot router to upgrade it\\\")\"; \
     :if (\$source = \$expected) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "RouterOS staged-state script has expected policy" \
    ":local id [/system script find name=\"${script_name}\"]; :if ([/system script get \$id policy] = \"read;test\") do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "RouterOS staged-state script bypasses caller permissions" \
    ":local id [/system script find name=\"${script_name}\"]; :if ([/system script get \$id dont-require-permissions] = true) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

run_step \
    "Remove uploaded MikroTik RouterOS staged-state installer" \
    ssh \
    "$router" \
    "/file remove ${remote_file}"

echo
echo "✅ MikroTik RouterOS staged-state predicate deployed."
