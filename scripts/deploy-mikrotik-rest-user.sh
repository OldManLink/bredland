#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"
# shellcheck source=scripts/lib/utils.sh
source "$(dirname "$0")/lib/utils.sh"
# shellcheck source=scripts/lib/deploy.sh
source "$(dirname "$0")/lib/deploy.sh"
# shellcheck source=scripts/lib/mikrotik.sh
source "$(dirname "$0")/lib/mikrotik.sh"

load_bredland_secrets

template="templates/mikrotik/install-noc-rest-user.rsc.template"

bredland_host="${BREDLAND_SSH_HOST:?Missing BREDLAND_SSH_HOST}"

router_user="${MIKROTIK_SSH_USER:?Missing MIKROTIK_SSH_USER}"
router_host="${MIKROTIK_SSH_HOST:?Missing MIKROTIK_SSH_HOST}"
router="${router_user}@${router_host}"

router_address="192.168.88.1"
bredland_address="192.168.88.5/32"

rest_group="noc-rest"
rest_user="noc-rest-bredland"
test_script="noc-trusted-action-test"
test_log_message="[NOC-TRUSTED-TEST] trusted action test invoked"

tls_dir="/etc/bredland/mikrotik-rest"
ca_cert="${tls_dir}/ca.pem"
credentials_file="${tls_dir}/credentials.env"

bredland_stage_dir="/tmp/bredland-mikrotik-rest-user-deploy"
bredland_stage_credentials="${bredland_stage_dir}/credentials.env"

tmpdir="$(mktemp -d)"
local_credentials="${tmpdir}/credentials.env"
rendered="${tmpdir}/install-noc-rest-user.rsc"

remote_installer="install-noc-rest-user.rsc"

cleanup() {
    rm -rf "$tmpdir"

    ssh "$bredland_host" \
        "rm -rf '$bredland_stage_dir'" \
        >/dev/null 2>&1 || true

    ssh "$router" \
        ":foreach id in=[/file find name=\"${remote_installer}\"] do={ /file remove \$id }" \
        >/dev/null 2>&1 || true
}

trap cleanup EXIT

run_step \
    "Creating or preserving MikroTik REST credentials on Bredland" \
    execute_remote_command \
    "$bredland_host" \
    "if sudo test -s '$credentials_file'; then
         exit 0
     fi

     password=\$(openssl rand -hex 32) &&

     printf '%s\n' \
         'MIKROTIK_REST_USER=$rest_user' \
         \"MIKROTIK_REST_PASSWORD=\$password\" \
         | sudo tee '$credentials_file' >/dev/null &&

     sudo chown root:bredland-trusted '$credentials_file' &&
     sudo chmod 0640 '$credentials_file'"

run_step \
    "Verifying MikroTik REST credential permissions on Bredland" \
    execute_remote_command \
    "$bredland_host" \
    "test \"\$(sudo stat -c '%U:%G:%a' '$credentials_file')\" \
         = 'root:bredland-trusted:640'"

run_step \
    "Staging MikroTik REST credentials on Bredland" \
    execute_remote_command \
    "$bredland_host" \
    "rm -rf '$bredland_stage_dir' &&
     mkdir -m 0700 '$bredland_stage_dir' &&

     sudo cp \
         '$credentials_file' \
         '$bredland_stage_credentials' &&

     sudo chown \$(id -un):\$(id -gn) \
         '$bredland_stage_credentials' &&

     chmod 0600 '$bredland_stage_credentials'"

run_step \
    "Staging MikroTik REST credentials locally" \
    scp \
    "${bredland_host}:${bredland_stage_credentials}" \
    "$local_credentials"

chmod 0600 "$local_credentials"

run_step \
    "Removing staged MikroTik REST credentials from Bredland" \
    execute_remote_command \
    "$bredland_host" \
    "rm -rf '$bredland_stage_dir'"

set -a
# shellcheck disable=SC1090
source "$local_credentials"
set +a

: "${MIKROTIK_REST_USER:?Missing MIKROTIK_REST_USER}"
: "${MIKROTIK_REST_PASSWORD:?Missing MIKROTIK_REST_PASSWORD}"

if [[ "$MIKROTIK_REST_USER" != "$rest_user" ]]; then
    fail "Unexpected MikroTik REST username"
fi

run_step \
    "Rendering MikroTik REST user installer" \
    scripts/render-template.sh \
    "$template" \
    "$rendered"

run_step \
    "Uploading MikroTik REST user installer" \
    scp \
    "$rendered" \
    "${router}:${remote_installer}"

run_step \
    "Importing MikroTik REST user installer" \
    ssh \
    "$router" \
    "/import file-name=${remote_installer}"

verify_routeros \
    "$router" \
    "Restricted REST group found" \
    ":if ([:len [/user group find name=\"${rest_group}\"]] > 0) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "Restricted REST user found" \
    ":if ([:len [/user find name=\"${rest_user}\"]] > 0) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "Restricted REST user belongs to managed group" \
    ":local id [/user find name=\"${rest_user}\"]; :if ([/user get \$id group] = \"${rest_group}\") do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "Restricted REST user source restricted to Bredland" \
    ":local id [/user find name=\"${rest_user}\"]; :if ([/user get \$id address] = \"${bredland_address}\") do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "Restricted REST group has expected policy" \
    ":local id [/user group find name=\"${rest_group}\"]; \
     :local policies (\";\" . [:tostr [/user group get \$id policy]] . \";\"); \
     :if (([:find \$policies \";read;\"] != nil) && \
          ([:find \$policies \";api;\"] != nil) && \
          ([:find \$policies \";rest-api;\"] != nil) && \
          ([:find \$policies \";!write;\"] != nil) && \
          ([:find \$policies \";!policy;\"] != nil) && \
          ([:find \$policies \";!sensitive;\"] != nil)) do={ \
         :put \"VERIFY_OK\" \
     } else={ \
         :put (\"POLICIES=\" . \$policies); \
         :put \"VERIFY_FAILED\" \
     }"

run_step \
    "Verifying authenticated REST read from Bredland" \
    execute_remote_command \
    "$bredland_host" \
    "status=\$(sudo -u bredland-trusted sh -c '
         . \"$credentials_file\"
         curl \
             --silent \
             --show-error \
             --output /dev/null \
             --write-out \"%{http_code}\" \
             --cacert \"$ca_cert\" \
             --user \"\$MIKROTIK_REST_USER:\$MIKROTIK_REST_PASSWORD\" \
             \"https://${router_address}/rest/system/resource\"
     ') &&
     test \"\$status\" = '200'"

before_count="$(
    ssh "$router" \
        ":put [:len [/log find where message=\"${test_log_message}\"]]" \
        | tr -d '\r'
)"

run_step \
    "Invoking trusted action test through restricted REST identity" \
    execute_remote_command \
    "$bredland_host" \
    "status=\$(sudo -u bredland-trusted sh -c '
         . \"$credentials_file\"
         curl \
             --silent \
             --show-error \
             --output /dev/null \
             --write-out \"%{http_code}\" \
             --request POST \
             --header \"Content-Type: application/json\" \
             --data \"{\\\"number\\\":\\\"${test_script}\\\"}\" \
             --cacert \"$ca_cert\" \
             --user \"\$MIKROTIK_REST_USER:\$MIKROTIK_REST_PASSWORD\" \
             \"https://${router_address}/rest/system/script/run\"
     ') &&
     test \"\$status\" = '200'"

after_count="$(
    ssh "$router" \
        ":put [:len [/log find where message=\"${test_log_message}\"]]" \
        | tr -d '\r'
)"

if [[ "$before_count" =~ ^[0-9]+$ ]] \
    && [[ "$after_count" =~ ^[0-9]+$ ]] \
    && (( after_count == before_count + 1 )); then
    pass "Restricted REST identity executed trusted action test"
else
    echo "Matching log entries before: $before_count" >&2
    echo "Matching log entries after:  $after_count" >&2
    fail "Verifying trusted action test execution"
fi

echo "→ Verifying restricted REST identity cannot perform generic writes"

write_status="$(
    ssh "$bredland_host" \
        "sudo -u bredland-trusted sh -c '
             . \"$credentials_file\"
             curl \
                 --silent \
                 --show-error \
                 --output /dev/null \
                 --write-out \"%{http_code}\" \
                 --request PATCH \
                 --header \"Content-Type: application/json\" \
                 --data \"{\\\"comment\\\":\\\"NOC REST write test\\\"}\" \
                 --cacert \"$ca_cert\" \
                 --user \"\$MIKROTIK_REST_USER:\$MIKROTIK_REST_PASSWORD\" \
                 \"https://${router_address}/rest/user\"
         '" \
        | tr -d '\r'
)"

if [[ "$write_status" =~ ^2 ]]; then
    echo "Restricted REST write unexpectedly returned HTTP ${write_status}" >&2
    fail "Restricted REST identity cannot perform generic writes"
else
    pass "Restricted REST identity cannot perform generic writes"
fi

run_step \
    "Removing uploaded MikroTik REST user installer" \
    ssh \
    "$router" \
    "/file remove ${remote_installer}"

echo
pass "MikroTik restricted REST identity deployed"
