#!/usr/bin/env bash
# Manual live integration test for BRD-038 DNS challenge flow.
#
# Exercises real Oderland DNS:
#   publish challenge
#   verify all authoritative nameservers
#   verify public recursive visibility
#   remove challenge
#
# Not run by the automated test suite.
# Requires live network access and production DNS credentials.

set -euo pipefail
# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/../../scripts/lib/bredland.sh"
# shellcheck source=scripts/lib/utils.sh
source "$(dirname "$0")/../../scripts/lib/utils.sh"

load_bredland_secrets

bredland_host="${BREDLAND_SSH_HOST:?Missing BREDLAND_SSH_HOST}"

remote_run_dir=""
provision_backup_dir=""
provision_restore_pending=false

cleanup_live_test()
{
    if $provision_restore_pending &&
       [[ -n "$provision_backup_dir" ]]
    then
        echo
        echo "→ Emergency restoration of installed TLS certificate..."

        execute_remote_command \
            "$bredland_host" \
            "sudo cp '$provision_backup_dir/fullchain.pem' /etc/bredland/tls/.fullchain.pem.restore &&
             sudo cp '$provision_backup_dir/privkey.pem' /etc/bredland/tls/.privkey.pem.restore &&
             sudo chown root:bredland-trusted \
                 /etc/bredland/tls/.fullchain.pem.restore \
                 /etc/bredland/tls/.privkey.pem.restore &&
             sudo chmod 640 \
                 /etc/bredland/tls/.fullchain.pem.restore \
                 /etc/bredland/tls/.privkey.pem.restore &&
             sudo mv /etc/bredland/tls/.fullchain.pem.restore /etc/bredland/tls/fullchain.pem &&
             sudo mv /etc/bredland/tls/.privkey.pem.restore /etc/bredland/tls/privkey.pem" \
            >/dev/null 2>&1 || true
    fi

    if [[ -n "$remote_run_dir" ]]; then
        execute_remote_command \
            "$bredland_host" \
            "rm -rf '$remote_run_dir'" \
            >/dev/null 2>&1 || true
    fi
}

trap cleanup_live_test EXIT

echo
echo "=== BRD-038 live DNS challenge integration test ==="
record_name="_brd-038-live-$(date +%s).arcanel.se"
validation="brd-038-live-test"
echo "Record: $record_name"
echo "Value:  $validation"
echo

echo "→ Publishing DNS challenge..."
publish_started=$SECONDS

scripts/tls/publish-dns-challenge.sh \
    "$record_name" \
    "$validation"

publish_elapsed=$((SECONDS - publish_started))

echo "✅ DNS challenge published (${publish_elapsed}s)"
echo

echo "→ Discovering authoritative nameservers..."

authoritative_servers=()

while IFS= read -r server; do
    authoritative_servers+=("$server")
done < <(
    dig +short NS arcanel.se
)

printf '  %s\n' "${authoritative_servers[@]}"
echo

overall_started=$SECONDS

for server in "${authoritative_servers[@]}"; do
    echo "→ Waiting for $server..."
    server_started=$SECONDS

    while true; do
        result="$(
            dig "@$server" +short TXT "$record_name"
        )"

        if [[ "$result" == "\"$validation\"" ]]; then
            break
        fi

        sleep 1
    done

    server_elapsed=$((SECONDS - server_started))
    overall_elapsed=$((SECONDS - overall_started))

    echo "✅ $server sees challenge after ${server_elapsed}s (${overall_elapsed}s since authoritative wait began)"
done

echo
echo "→ Waiting for public recursive DNS..."
public_started=$SECONDS

while true; do
    result="$(
        dig +short TXT "$record_name"
    )"

    if [[ "$result" == "\"$validation\"" ]]; then
        break
    fi

    sleep 1
done

public_elapsed=$((SECONDS - public_started))
overall_elapsed=$((SECONDS - overall_started))

echo "✅ Public DNS sees challenge after ${public_elapsed}s (${overall_elapsed}s total propagation)"
echo

echo "→ Removing DNS challenge..."
remove_started=$SECONDS

scripts/tls/remove-dns-challenge.sh \
    "$record_name" \
    "$validation"

remove_elapsed=$((SECONDS - remove_started))

echo "✅ DNS challenge removed (${remove_elapsed}s)"
echo

echo
echo "=== BRD-038 issued certificate validation integration test ==="

run_id="brd-038-validator-live-$(date +%s)"
remote_run_dir="/tmp/$run_id"

echo
echo "→ Creating Bredland test run directory..."

execute_remote_command \
    "$bredland_host" \
    "mkdir -p '$remote_run_dir'"

echo "✅ Bredland test run directory created"

echo
echo "→ Uploading certificate fixtures..."

execute_rsync \
    tests/fixtures/tls/fullchain.pem \
    "${bredland_host}:${remote_run_dir}/fullchain.pem"

execute_rsync \
    tests/fixtures/tls/privkey.pem \
    "${bredland_host}:${remote_run_dir}/privkey.pem"

echo "✅ Certificate fixtures uploaded"

echo
echo "→ Validating issued certificate through Mac wrapper..."

scripts/tls/validate-issued-certificate.sh \
    "$run_id"

echo "✅ Issued certificate accepted through Mac → Bredland path"

echo
echo "→ Removing Bredland test run directory..."

execute_remote_command \
    "$bredland_host" \
    "rm -rf '$remote_run_dir'"

remote_run_dir=""

echo "✅ Bredland test run directory removed"

echo
echo "=== BRD-038 issued certificate provisioning integration test ==="

run_id="brd-038-provision-live-$(date +%s)"
remote_run_dir="/tmp/$run_id"
provision_backup_dir="/etc/bredland/tls/backups/$run_id"

echo
echo "→ Recording installed TLS certificate hashes..."

original_fullchain_hash="$(
    execute_remote_command \
        "$bredland_host" \
        "sudo sha256sum /etc/bredland/tls/fullchain.pem | awk '{print \$1}'"
)"

original_privkey_hash="$(
    execute_remote_command \
        "$bredland_host" \
        "sudo sha256sum /etc/bredland/tls/privkey.pem | awk '{print \$1}'"
)"

echo "✅ Installed TLS certificate hashes recorded"

echo
echo "→ Creating Bredland provisioning run directory..."

execute_remote_command \
    "$bredland_host" \
    "mkdir -p '$remote_run_dir'"

echo "✅ Bredland provisioning run directory created"

echo
echo "→ Uploading synthetic certificate pair..."

execute_rsync \
    tests/fixtures/tls/fullchain.pem \
    "${bredland_host}:${remote_run_dir}/fullchain.pem"

execute_rsync \
    tests/fixtures/tls/privkey.pem \
    "${bredland_host}:${remote_run_dir}/privkey.pem"

echo "✅ Synthetic certificate pair uploaded"

echo
echo "→ Provisioning synthetic certificate through Mac wrapper..."

scripts/tls/provision-issued-certificate.sh \
    "$run_id"

provision_restore_pending=true

echo "✅ Synthetic certificate provisioned"

echo
echo "→ Verifying installed certificate pair changed..."

provisioned_fullchain_hash="$(
    execute_remote_command \
        "$bredland_host" \
        "sudo sha256sum /etc/bredland/tls/fullchain.pem | awk '{print \$1}'"
)"

provisioned_privkey_hash="$(
    execute_remote_command \
        "$bredland_host" \
        "sudo sha256sum /etc/bredland/tls/privkey.pem | awk '{print \$1}'"
)"

if [[ "$provisioned_fullchain_hash" == "$original_fullchain_hash" ]]; then
    echo "❌ Expected installed certificate to change"
    exit 1
fi

if [[ "$provisioned_privkey_hash" == "$original_privkey_hash" ]]; then
    echo "❌ Expected installed private key to change"
    exit 1
fi

echo "✅ Installed certificate pair changed"

echo
echo "→ Verifying rollback backup exists..."

execute_remote_command \
    "$bredland_host" \
    "sudo test -f '$provision_backup_dir/fullchain.pem' &&
     sudo test -f '$provision_backup_dir/privkey.pem'"

echo "✅ Rollback backup exists"

echo
echo "→ Restoring original installed certificate pair..."

execute_remote_command \
    "$bredland_host" \
    "sudo cp '$provision_backup_dir/fullchain.pem' /etc/bredland/tls/.fullchain.pem.restore &&
     sudo cp '$provision_backup_dir/privkey.pem' /etc/bredland/tls/.privkey.pem.restore &&
     sudo chown root:bredland-trusted \
         /etc/bredland/tls/.fullchain.pem.restore \
         /etc/bredland/tls/.privkey.pem.restore &&
     sudo chmod 640 \
         /etc/bredland/tls/.fullchain.pem.restore \
         /etc/bredland/tls/.privkey.pem.restore &&
     sudo mv /etc/bredland/tls/.fullchain.pem.restore /etc/bredland/tls/fullchain.pem &&
     sudo mv /etc/bredland/tls/.privkey.pem.restore /etc/bredland/tls/privkey.pem"

provision_restore_pending=false

echo "✅ Original certificate pair restored"

echo
echo "→ Verifying restored certificate hashes..."

restored_fullchain_hash="$(
    execute_remote_command \
        "$bredland_host" \
        "sudo sha256sum /etc/bredland/tls/fullchain.pem | awk '{print \$1}'"
)"

restored_privkey_hash="$(
    execute_remote_command \
        "$bredland_host" \
        "sudo sha256sum /etc/bredland/tls/privkey.pem | awk '{print \$1}'"
)"

if [[ "$restored_fullchain_hash" != "$original_fullchain_hash" ]]; then
    echo "❌ Restored certificate does not match original"
    exit 1
fi

if [[ "$restored_privkey_hash" != "$original_privkey_hash" ]]; then
    echo "❌ Restored private key does not match original"
    exit 1
fi

echo "✅ Restored certificate pair matches original"

echo
echo "→ Removing provisioning test artifacts..."

execute_remote_command \
    "$bredland_host" \
    "rm -rf '$remote_run_dir' &&
     sudo rm -rf '$provision_backup_dir'"

remote_run_dir=""
provision_backup_dir=""

echo "✅ Provisioning test artifacts removed"

echo "=== Live integration test complete ==="

