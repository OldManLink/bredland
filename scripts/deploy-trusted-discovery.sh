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

run_step \
    "Rendering trusted discovery service" \
    scripts/render-template.sh \
    templates/bredland/trusted_discovery.template.py \
    "$tmpdir/trusted_discovery.py"

run_step \
    "Rendering trusted discovery systemd unit" \
    scripts/render-template.sh \
    templates/bredland/trusted-discovery.service.template \
    "$tmpdir/trusted-discovery.service"

run_step \
    "Uploading trusted discovery service" \
    execute_rsync \
    "$tmpdir/trusted_discovery.py" \
    "${bredland_host}:/tmp/trusted_discovery.py"

run_step \
    "Uploading trusted JavaScript" \
    execute_rsync \
    templates/bredland/static/trusted.js \
    "${bredland_host}:/tmp/trusted.js"

run_step \
    "Uploading trusted stylesheet" \
    execute_rsync \
    templates/bredland/static/trusted.css \
    "${bredland_host}:/tmp/trusted.css"

run_step \
    "Uploading trusted discovery systemd unit" \
    execute_rsync \
    "$tmpdir/trusted-discovery.service" \
    "${bredland_host}:/tmp/trusted-discovery.service"

run_step \
    "Ensuring trusted discovery service account" \
    execute_remote_command \
    "$bredland_host" \
    "getent group bredland-trusted >/dev/null ||
         sudo groupadd --system bredland-trusted

     id -u bredland-trusted >/dev/null 2>&1 ||
         sudo useradd \
             --system \
             --gid bredland-trusted \
             --no-create-home \
             --shell /usr/sbin/nologin \
             bredland-trusted"

run_step \
    "Granting trusted discovery TLS access" \
    execute_remote_command \
    "$bredland_host" \
    "sudo chown root:bredland-trusted /etc/bredland/tls &&
     sudo chmod 750 /etc/bredland/tls &&
     sudo chown root:bredland-trusted /etc/bredland/tls/fullchain.pem &&
     sudo chmod 640 /etc/bredland/tls/fullchain.pem &&
     sudo chown root:bredland-trusted /etc/bredland/tls/privkey.pem &&
     sudo chmod 640 /etc/bredland/tls/privkey.pem"

run_step \
    "Verifying trusted discovery TLS access" \
    execute_remote_command \
    "$bredland_host" \
    "sudo -u bredland-trusted test -r /etc/bredland/tls/fullchain.pem &&
     sudo -u bredland-trusted test -r /etc/bredland/tls/privkey.pem"

run_step \
    "Installing trusted discovery service" \
    execute_remote_command \
    "$bredland_host" \
    "sudo install -d -m 755 /usr/local/lib/bredland/static &&
     sudo install -m 644 /tmp/trusted_discovery.py /usr/local/lib/bredland/trusted_discovery.py &&
     sudo install -m 644 /tmp/trusted.js /usr/local/lib/bredland/static/trusted.js &&
     sudo install -m 644 /tmp/trusted.css /usr/local/lib/bredland/static/trusted.css &&
     sudo install -m 644 /tmp/trusted-discovery.service /etc/systemd/system/trusted-discovery.service &&
     sudo systemctl daemon-reload &&
     sudo systemctl enable --now trusted-discovery.service &&
     sudo systemctl restart trusted-discovery.service"

run_step \
    "Verifying trusted discovery service" \
    execute_remote_command \
    "$bredland_host" \
    "systemctl is-active --quiet trusted-discovery.service &&
     systemctl is-enabled --quiet trusted-discovery.service"

run_step \
    "Verifying trusted discovery HTTPS endpoint" \
    execute_remote_command \
    "$bredland_host" \
    "probe_headers=\$(mktemp) &&
     script_headers=\$(mktemp) &&
     stylesheet_headers=\$(mktemp) &&
     trap 'rm -f \"\$probe_headers\" \"\$script_headers\" \"\$stylesheet_headers\"' EXIT &&

     curl --fail --silent --show-error \
        --dump-header \"\$probe_headers\" \
        --output /dev/null \
        '${BREDLAND_TRUSTED_BASE_URL}/probe' &&

     grep -qi '^content-type: application/json' \"\$probe_headers\" &&

     echo &&

curl --fail --silent --show-error \
        --dump-header \"\$script_headers\" \
        '${BREDLAND_TRUSTED_BASE_URL}${BREDLAND_TRUSTED_SCRIPT_PATH}' \
        | head -3 &&

     grep -qi '^content-type: application/javascript' \"\$script_headers\" &&

     echo &&
     echo &&

     curl --fail --silent --show-error \
        --dump-header \"\$stylesheet_headers\" \
        '${BREDLAND_TRUSTED_BASE_URL}${BREDLAND_TRUSTED_STYLESHEET_PATH}' \
        | head -3 &&

     echo &&

     grep -qi '^content-type: text/css' \"\$stylesheet_headers\" &&

     echo"

echo
pass "Trusted discovery service deployed"
