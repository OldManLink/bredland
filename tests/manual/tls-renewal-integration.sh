#!/usr/bin/env bash
# Manual live integration test for BRD-038 TLS renewal flow.
#
# Exercises real Oderland DNS and Bredland TLS:
#   create independent TLS safety archive
#   publish DNS challenge
#   verify all authoritative nameservers
#   verify public recursive visibility
#   validate issued certificate
#   provision issued certificate
#   activate issued certificate
#   verify served certificate
#   remove DNS challenge
#   restore original certificate pair
#   reactivate original certificate
#   verify original certificate is served again
#   remove test artifacts
#   remove safety archive only after complete success
#
# Not run by the automated test suite.
# Requires live network access and production credentials.

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
dns_cleanup_pending=false

local_tmpdir="$(mktemp -d)"
original_expected_dir="$local_tmpdir/original"

mkdir -p "$original_expected_dir"

cleanup_live_test()
{
    if $provision_restore_pending &&
       [[ -n "$provision_backup_dir" ]]
    then
        echo
        echo "→ Emergency restoration of installed TLS certificate..."

        if execute_remote_command \
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
             sudo mv /etc/bredland/tls/.privkey.pem.restore /etc/bredland/tls/privkey.pem &&
             sudo /usr/local/bin/activate-issued-certificate" \
            >/dev/null 2>&1
        then
            echo "✅ Emergency TLS restoration completed"
        else
            echo "❌ Emergency TLS restoration failed; \$HOME/tls.tgz has been preserved on Bredland" >&2
        fi
    fi

    if $dns_cleanup_pending; then
        echo
        echo "→ Emergency removal of DNS challenge..."

        scripts/tls/remove-dns-challenge.sh \
            "$record_name" \
            "$validation" \
            >/dev/null 2>&1 || true
    fi

    if [[ -n "$remote_run_dir" ]]; then
        execute_remote_command \
            "$bredland_host" \
            "rm -rf '$remote_run_dir'" \
            >/dev/null 2>&1 || true
    fi

    rm -rf "$local_tmpdir"
}

trap cleanup_live_test EXIT

echo
echo "=== BRD-038 live TLS renewal integration test ==="

echo
echo "→ Creating independent TLS safety archive..."

execute_remote_command \
    "$bredland_host" \
    'test ! -e "$HOME/tls.tgz" &&
     sudo tar -czf "$HOME/tls.tgz" -C /etc/bredland tls &&
     sudo tar -tzf "$HOME/tls.tgz" >/dev/null'

echo "✅ TLS safety archive created and readable"

echo
echo "→ Recording installed TLS certificate state..."

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

execute_remote_command \
    "$bredland_host" \
    "sudo cat /etc/bredland/tls/fullchain.pem" \
    > "$original_expected_dir/fullchain.pem"

echo "✅ Installed TLS certificate state recorded"

echo
echo "=== DNS challenge ==="

record_name="_brd-038-live-$(date +%s).arcanel.se"
validation="brd-038-live-test"

echo "Record: $record_name"
echo "Value:  $validation"
echo

echo "→ Publishing DNS challenge..."
publish_started=$SECONDS

dns_cleanup_pending=true

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
echo "=== Issued certificate validation ==="

run_id="brd-038-live-$(date +%s)"
remote_run_dir="/tmp/$run_id"
provision_backup_dir="/etc/bredland/tls/backups/$run_id"

echo
echo "→ Creating Bredland renewal run directory..."

execute_remote_command \
    "$bredland_host" \
    "mkdir -p '$remote_run_dir'"

echo "✅ Bredland renewal run directory created"

echo
echo "→ Uploading synthetic issued certificate pair..."

execute_rsync \
    tests/fixtures/tls/fullchain.pem \
    "${bredland_host}:${remote_run_dir}/fullchain.pem"

execute_rsync \
    tests/fixtures/tls/privkey.pem \
    "${bredland_host}:${remote_run_dir}/privkey.pem"

echo "✅ Synthetic issued certificate pair uploaded"

echo
echo "→ Validating issued certificate through Mac wrapper..."

scripts/tls/validate-issued-certificate.sh \
    "$run_id"

echo "✅ Issued certificate accepted through Mac → Bredland path"

echo
echo "=== Issued certificate provisioning ==="

echo
echo "→ Provisioning synthetic issued certificate..."

scripts/tls/provision-issued-certificate.sh \
    "$run_id"

provision_restore_pending=true

echo "✅ Synthetic issued certificate provisioned"

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
echo "=== Issued certificate activation ==="

echo
echo "→ Activating synthetic issued certificate..."

scripts/tls/activate-issued-certificate.sh

echo "✅ Synthetic issued certificate activated"

echo
echo "→ Verifying synthetic certificate is served..."

scripts/tls/verify-served-certificate.sh \
    tests/fixtures/tls

echo "✅ Synthetic issued certificate is being served"

echo
echo "=== DNS challenge cleanup ==="

echo
echo "→ Removing DNS challenge..."
remove_started=$SECONDS

scripts/tls/remove-dns-challenge.sh \
    "$record_name" \
    "$validation"

dns_cleanup_pending=false

remove_elapsed=$((SECONDS - remove_started))

echo "✅ DNS challenge removed (${remove_elapsed}s)"

echo
echo "=== Restoring original production certificate ==="

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

echo "✅ Original certificate pair restored on disk"

echo
echo "→ Reactivating original production certificate..."

scripts/tls/activate-issued-certificate.sh

echo "✅ Original production certificate reactivated"

echo
echo "→ Verifying original production certificate is served..."

scripts/tls/verify-served-certificate.sh \
    "$original_expected_dir"

echo "✅ Original production certificate is being served"

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

provision_restore_pending=false

echo
echo "=== Integration test cleanup ==="

echo
echo "→ Removing renewal test artifacts..."

execute_remote_command \
    "$bredland_host" \
    "rm -rf '$remote_run_dir' &&
     sudo rm -rf '$provision_backup_dir'"

remote_run_dir=""
provision_backup_dir=""

echo "✅ Renewal test artifacts removed"

echo
echo "→ Removing TLS safety archive..."

execute_remote_command \
    "$bredland_host" \
    'sudo rm -f "$HOME/tls.tgz" &&
     test ! -e "$HOME/tls.tgz"'

echo "✅ TLS safety archive removed"

echo
echo "=== Live integration test complete ==="
