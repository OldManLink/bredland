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

template="templates/mikrotik/install-noc-check-routeros-updates.rsc.template"
rendered="${tmpdir}/install-noc-check-routeros-updates.rsc"
remote_file="install-noc-check-routeros-updates.rsc"

script_name="noc-check-routeros-updates"
scheduler_name="noc-check-routeros-updates"

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

echo "Capturing existing RouterOS update-check state..."

script_run_count_before="$(
    ssh "$router" \
        ":local id [/system script find name=\"${script_name}\"]; :if ([:len \$id] > 0) do={ :put [/system script get \$id run-count] }" \
        | tr -d '\r'
)"

script_last_started_before="$(
    ssh "$router" \
        ":local id [/system script find name=\"${script_name}\"]; :if ([:len \$id] > 0) do={ :put [/system script get \$id last-started] }" \
        | tr -d '\r'
)"

scheduler_run_count_before="$(
    ssh "$router" \
        ":local id [/system scheduler find name=\"${scheduler_name}\"]; :if ([:len \$id] > 0) do={ :put [/system scheduler get \$id run-count] }" \
        | tr -d '\r'
)"

scheduler_next_run_before="$(
    ssh "$router" \
        ":local id [/system scheduler find name=\"${scheduler_name}\"]; :if ([:len \$id] > 0) do={ :put [/system scheduler get \$id next-run] }" \
        | tr -d '\r'
)"

run_step \
    "Render MikroTik RouterOS update-check installer" \
    scripts/render-template.sh \
    "$template" \
    "$rendered"

run_step \
    "Upload MikroTik RouterOS update-check installer" \
    scp \
    "$rendered" \
    "${router}:${remote_file}"

run_step \
    "Import MikroTik RouterOS update-check installer" \
    ssh \
    "$router" \
    "/import file-name=${remote_file}"

verify_routeros \
    "$router" \
    "RouterOS update-check script found" \
    ":if ([:len [/system script find name=\"${script_name}\"]] > 0) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "RouterOS update-check script has expected source" \
    ":local id [/system script find name=\"${script_name}\"]; :local source [/system script get \$id source]; :if (\$source = \"/system package update check-for-updates\") do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "RouterOS update-check script has expected policy" \
    ":local id [/system script find name=\"${script_name}\"]; :if ([/system script get \$id policy] = \"ftp;reboot;read;write;policy;test;password;sniff;sensitive;romon\") do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "RouterOS update-check script requires caller permissions" \
    ":local id [/system script find name=\"${script_name}\"]; :if ([/system script get \$id dont-require-permissions] = false) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "RouterOS update-check scheduler found" \
    ":if ([:len [/system scheduler find name=\"${scheduler_name}\"]] > 0) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "RouterOS update-check scheduler runs daily at 04:15" \
    ":local id [/system scheduler find name=\"${scheduler_name}\"]; :if (([/system scheduler get \$id interval] = 1d) && ([/system scheduler get \$id start-time] = 04:15:00)) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "RouterOS update-check scheduler invokes expected script" \
    ":local id [/system scheduler find name=\"${scheduler_name}\"]; :if ([/system scheduler get \$id on-event] = \"${script_name}\") do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "RouterOS update-check scheduler has expected policy" \
    ":local id [/system scheduler find name=\"${scheduler_name}\"]; :if ([/system scheduler get \$id policy] = \"ftp;reboot;read;write;policy;test;password;sniff;sensitive;romon\") do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "RouterOS update-check scheduler has expected start date" \
    ":local id [/system scheduler find name=\"${scheduler_name}\"]; :if ([/system scheduler get \$id start-date] = \"2026-07-10\") do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

script_run_count_after="$(
    ssh "$router" \
        ":local id [/system script find name=\"${script_name}\"]; :put [/system script get \$id run-count]" \
        | tr -d '\r'
)"

script_last_started_after="$(
    ssh "$router" \
        ":local id [/system script find name=\"${script_name}\"]; :put [/system script get \$id last-started]" \
        | tr -d '\r'
)"

scheduler_run_count_after="$(
    ssh "$router" \
        ":local id [/system scheduler find name=\"${scheduler_name}\"]; :put [/system scheduler get \$id run-count]" \
        | tr -d '\r'
)"

scheduler_next_run_after="$(
    ssh "$router" \
        ":local id [/system scheduler find name=\"${scheduler_name}\"]; :put [/system scheduler get \$id next-run]" \
        | tr -d '\r'
)"

if [[ -n "$script_run_count_before" ]] \
    && [[ "$script_run_count_after" != "$script_run_count_before" ]]; then
    fail "RouterOS update-check script run-count was not preserved"
fi

if [[ -n "$script_last_started_before" ]] \
    && [[ "$script_last_started_after" != "$script_last_started_before" ]]; then
    fail "RouterOS update-check script last-started was not preserved"
fi

if [[ -n "$scheduler_run_count_before" ]] \
    && [[ "$scheduler_run_count_after" != "$scheduler_run_count_before" ]]; then
    fail "RouterOS update-check scheduler run-count was not preserved"
fi

if [[ -n "$scheduler_next_run_before" ]] \
    && [[ "$scheduler_next_run_after" != "$scheduler_next_run_before" ]]; then
    fail "RouterOS update-check scheduler next-run was not preserved"
fi

pass "Existing RouterOS update-check history preserved"

run_step \
    "Remove uploaded MikroTik RouterOS update-check installer" \
    ssh \
    "$router" \
    "/file remove ${remote_file}"

echo
echo "✅ MikroTik RouterOS update check deployed."
