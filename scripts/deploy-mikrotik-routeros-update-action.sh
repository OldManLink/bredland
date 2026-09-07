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

template="templates/mikrotik/install-noc-install-routeros-update.rsc.template"
rendered="${tmpdir}/install-noc-install-routeros-update.rsc"
remote_file="install-noc-install-routeros-update.rsc"

script_name="noc-install-routeros-update"

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
    "Render MikroTik RouterOS update-action installer" \
    scripts/render-template.sh \
    "$template" \
    "$rendered"

run_step \
    "Upload MikroTik RouterOS update-action installer" \
    scp \
    "$rendered" \
    "${router}:${remote_file}"

run_step \
    "Import MikroTik RouterOS update-action installer" \
    ssh \
    "$router" \
    "/import file-name=${remote_file}"

verify_routeros \
    "$router" \
    "RouterOS update-action script found" \
    ":if ([:len [/system script find name=\"${script_name}\"]] > 0) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "RouterOS update-action script has expected source" \
    ":local id [/system script find name=\"${script_name}\"]; \
     :local source [/system script get \$id source]; \
     :local expected \":local installed [/system package update get installed-version]\n:local latest [/system package update get latest-version]\n:local status [/system package update get status]\n\n:if ((\\\$installed = \\\"\\\") || (\\\$latest = \\\"\\\") || (\\\$installed = \\\$latest) || (\\\$status != \\\"New version is available\\\")) do={\n    :log warning \\\"[NOC-ROUTEROS-UPDATE] update no longer available\\\"\n} else={\n    :log warning \\\"[NOC-ROUTEROS-UPDATE] SAFETY CATCH: would install RouterOS update\\\"\n\n    :if (false) do={\n        :log warning \\\"[NOC-ROUTEROS-UPDATE] THIS SHOULD NEVER HAPPEN\\\"\n    }\n}\"; \
     :if (\$source = \$expected) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "RouterOS update-action script has expected policy" \
    ":local id [/system script find name=\"${script_name}\"]; :if ([/system script get \$id policy] = \"reboot;read;write\") do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "RouterOS update-action script bypasses caller permissions" \
    ":local id [/system script find name=\"${script_name}\"]; :if ([/system script get \$id dont-require-permissions] = true) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

run_step \
    "Remove uploaded MikroTik RouterOS update-action installer" \
    ssh \
    "$router" \
    "/file remove ${remote_file}"

echo
echo "✅ MikroTik RouterOS update action deployed with safety catch."
