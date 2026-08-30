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

template="templates/mikrotik/install-noc-rest-tls.rsc.template"

bredland_host="${BREDLAND_SSH_HOST:?Missing BREDLAND_SSH_HOST}"

router_user="${MIKROTIK_SSH_USER:?Missing MIKROTIK_SSH_USER}"
router_host="${MIKROTIK_SSH_HOST:?Missing MIKROTIK_SSH_HOST}"
router="${router_user}@${router_host}"

router_address="192.168.88.1"
bredland_address="192.168.88.5/32"

tls_dir="/etc/bredland/mikrotik-rest"

ca_cert="${tls_dir}/ca.pem"
ca_key="${tls_dir}/ca.key"
server_cert="${tls_dir}/server.pem"
server_key="${tls_dir}/server.key"
server_csr="${tls_dir}/server.csr"
server_ext="${tls_dir}/server.ext"

bredland_stage_dir="/tmp/bredland-mikrotik-rest-deploy"
bredland_stage_cert="${bredland_stage_dir}/server.pem"
bredland_stage_key="${bredland_stage_dir}/server.key"

tmpdir="$(mktemp -d)"
rendered="${tmpdir}/install-noc-rest-tls.rsc"
local_server_cert="${tmpdir}/server.pem"
local_server_key="${tmpdir}/server.key"

remote_installer="install-noc-rest-tls.rsc"
remote_server_cert="noc-rest-server.pem"
remote_server_key="noc-rest-server.key"

certificate_name="noc-rest-server"

cleanup() {
    rm -rf "$tmpdir"

    ssh "$bredland_host" \
        "rm -rf '$bredland_stage_dir'" \
        >/dev/null 2>&1 || true
}

trap cleanup EXIT

run_step \
    "Rendering MikroTik REST TLS installer" \
    scripts/render-template.sh \
    "$template" \
    "$rendered"

run_step \
    "Preparing MikroTik REST TLS directory on Bredland" \
    execute_remote_command \
    "$bredland_host" \
    "sudo install -d \
     -o root \
     -g bredland-trusted \
     -m 0750 \
     '$tls_dir'"

run_step \
    "Creating or preserving MikroTik REST CA on Bredland" \
    execute_remote_command \
    "$bredland_host" \
    "if sudo test -s '$ca_cert' && sudo test -s '$ca_key'; then
         exit 0
     fi

     sudo openssl req \
         -x509 \
         -newkey rsa:3072 \
         -sha256 \
         -days 3650 \
         -nodes \
         -subj '/CN=Bredland MikroTik REST CA' \
         -keyout '$ca_key' \
         -out '$ca_cert' &&

     sudo chmod 0600 '$ca_key' &&
     sudo chmod 0644 '$ca_cert'"

run_step \
    "Setting MikroTik REST CA permissions on Bredland" \
    execute_remote_command \
    "$bredland_host" \
    "sudo chown root:bredland-trusted '$ca_cert' &&
     sudo chmod 0640 '$ca_cert' &&
     sudo chown root:root '$ca_key' &&
     sudo chmod 0600 '$ca_key'"

run_step \
    "Generating MikroTik REST server certificate on Bredland" \
    execute_remote_command \
    "$bredland_host" \
    "sudo openssl req \
         -new \
         -newkey rsa:3072 \
         -nodes \
         -subj '/CN=$router_address' \
         -keyout '$server_key' \
         -out '$server_csr' &&

     printf '%s\n' \
         'subjectAltName=IP:$router_address' \
         'extendedKeyUsage=serverAuth' \
         'keyUsage=digitalSignature,keyEncipherment' \
         | sudo tee '$server_ext' >/dev/null &&

     sudo openssl x509 \
         -req \
         -in '$server_csr' \
         -CA '$ca_cert' \
         -CAkey '$ca_key' \
         -CAcreateserial \
         -days 825 \
         -sha256 \
         -extfile '$server_ext' \
         -out '$server_cert' &&

     sudo chmod 0600 '$server_key' &&
     sudo chmod 0644 '$server_cert'"

run_step \
    "Verifying MikroTik REST certificate on Bredland" \
    execute_remote_command \
    "$bredland_host" \
    "sudo openssl verify \
         -CAfile '$ca_cert' \
         '$server_cert'"

run_step \
    "Staging MikroTik REST certificate material on Bredland" \
    execute_remote_command \
    "$bredland_host" \
    "rm -rf '$bredland_stage_dir' &&
     mkdir -m 0700 '$bredland_stage_dir' &&

     sudo cp \
         '$server_cert' \
         '$bredland_stage_cert' &&

     sudo cp \
         '$server_key' \
         '$bredland_stage_key' &&

     sudo chown \$(id -un):\$(id -gn) \
         '$bredland_stage_cert' \
         '$bredland_stage_key' &&

     chmod 0600 \
         '$bredland_stage_cert' \
         '$bredland_stage_key'"

run_step \
    "Staging MikroTik REST server certificate locally" \
    scp \
    "${bredland_host}:${bredland_stage_cert}" \
    "$local_server_cert"

run_step \
    "Staging MikroTik REST server key locally" \
    scp \
    "${bredland_host}:${bredland_stage_key}" \
    "$local_server_key"

chmod 0600 "$local_server_key"

run_step \
    "Removing staged certificate material from Bredland" \
    execute_remote_command \
    "$bredland_host" \
    "rm -rf '$bredland_stage_dir'"

run_step \
    "Uploading MikroTik REST server certificate" \
    scp \
    "$local_server_cert" \
    "${router}:${remote_server_cert}"

run_step \
    "Uploading MikroTik REST server key" \
    scp \
    "$local_server_key" \
    "${router}:${remote_server_key}"

run_step \
    "Disabling existing MikroTik REST TLS service" \
    ssh \
    "$router" \
    "/ip service set www-ssl disabled=yes certificate=none"

run_step \
    "Removing previous managed MikroTik REST certificate" \
    ssh \
    "$router" \
    ":foreach id in=[/certificate find name=\"${certificate_name}\"] do={ /certificate remove \$id }"

run_step \
    "Importing MikroTik REST server certificate" \
    ssh \
    "$router" \
    "/certificate import file-name=${remote_server_cert} name=${certificate_name} trusted=yes"

run_step \
    "Importing MikroTik REST server key" \
    ssh \
    "$router" \
    "/certificate import file-name=${remote_server_key} name=${certificate_name}"

run_step \
    "Uploading MikroTik REST TLS installer" \
    scp \
    "$rendered" \
    "${router}:${remote_installer}"

run_step \
    "Importing MikroTik REST TLS installer" \
    ssh \
    "$router" \
    "/import file-name=${remote_installer}"

verify_routeros \
    "$router" \
    "REST TLS certificate found" \
    ":if ([:len [/certificate find name=\"${certificate_name}\"]] > 0) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "www-ssl enabled" \
    ":if ([/ip service get www-ssl disabled] = false) do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "www-ssl source restricted to Bredland" \
    ":if ([/ip service get www-ssl address] = \"${bredland_address}\") do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

verify_routeros \
    "$router" \
    "www-ssl uses managed certificate" \
    ":if ([/ip service get www-ssl certificate] = \"${certificate_name}\") do={ :put \"VERIFY_OK\" } else={ :put \"VERIFY_FAILED\" }"

run_step \
    "Verifying REST TLS from Bredland trusted service identity" \
    execute_remote_command \
    "$bredland_host" \
    "status=\$(sudo -u bredland-trusted curl \
         --silent \
         --show-error \
         --output /dev/null \
         --write-out '%{http_code}' \
         --cacert '$ca_cert' \
         'https://${router_address}/rest/system/resource') &&
     test \"\$status\" = '401'"

run_step \
    "Removing uploaded MikroTik REST files" \
    ssh \
    "$router" \
    "/file remove ${remote_server_cert}; \
     /file remove ${remote_server_key}; \
     /file remove ${remote_installer}"

echo
pass "MikroTik REST TLS deployed"
