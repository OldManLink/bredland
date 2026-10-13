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
# shellcheck source=tests/sh/lib/testlib.sh
source "$(dirname "$0")/../sh/lib/testlib.sh"

integration_started=$SECONDS

load_bredland_secrets

bredland_host="${BREDLAND_SSH_HOST:?Missing BREDLAND_SSH_HOST}"

remote_run_dir=""
provision_backup_dir=""
provision_restore_pending=false
dns_cleanup_pending=false
renewal_pending=false
record_name=""
validation=""

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
            if execute_remote_command \
            "$bredland_host" \
            "sudo touch -m -r '$remote_run_dir/original-fullchain.timestamp' /etc/bredland/tls/fullchain.pem &&
             sudo touch -m -r '$remote_run_dir/original-privkey.timestamp' /etc/bredland/tls/privkey.pem" \
            >/dev/null 2>&1
            then
                echo "✅ Emergency TLS restoration completed"
            else
                echo "⚠️ Emergency TLS restoration completed, but original timestamps could not be restored" >&2
            fi
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

    if $renewal_pending &&
       [[ -n "$remote_run_dir" ]]
    then
        echo
        echo "→ Cancelling in-progress TLS renewal..."

        execute_remote_command \
            "$bredland_host" \
            "cancel-renewal '$run_id'" \
            >/dev/null 2>&1 || true

        execute_remote_command \
            "$bredland_host" \
            "wait-renewal '$run_id'" \
            >/dev/null 2>&1 || true

        echo "✅ In-progress TLS renewal stopped"
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

run_timed_step \
    "Creating independent TLS safety archive" \
    execute_remote_command \
        "$bredland_host" \
        'if [[ -e "$HOME/tls.tgz" ]]; then
             echo "TLS safety archive already exists: $HOME/tls.tgz" >&2
             exit 1
         fi

         sudo tar -czf "$HOME/tls.tgz" -C /etc/bredland tls
         sudo tar -tzf "$HOME/tls.tgz" >/dev/null'

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
echo "=== Test certificate issuance ==="

run_id="brd-038-live-$(date +%s)"
remote_run_dir="/tmp/$run_id"
provision_backup_dir="/etc/bredland/tls/backups/$run_id"
issued_expected_dir="$local_tmpdir/issued"
mkdir -p "$issued_expected_dir"

renewal_pending=true
run_timed_step \
    "Starting test-cert TLS renewal on Bredland" \
    execute_remote_command \
        "$bredland_host" \
        "CERTBOT_EMAIL='$CERTBOT_EMAIL' start-renewal --test-cert '$run_id'"

run_timed_step \
    "Preserving original certificate timestamps" \
    execute_remote_command \
    "$bredland_host" \
    "sudo touch -r /etc/bredland/tls/fullchain.pem '$remote_run_dir/original-fullchain.timestamp' &&
     sudo touch -r /etc/bredland/tls/privkey.pem '$remote_run_dir/original-privkey.timestamp'"

echo
echo "→ Reading Certbot DNS challenge request..."

request="$(
    execute_remote_command \
        "$bredland_host" \
        "cat '$remote_run_dir/request'"
)"

identifier="$(
    printf '%s\n' "$request" \
        | sed -n 's/^identifier=//p'
)"

record_name="$(
    printf '%s\n' "$request" \
        | sed -n 's/^record=//p'
)"

validation="$(
    printf '%s\n' "$request" \
        | sed -n 's/^validation=//p'
)"

if [[ "$identifier" != "nocster-73.arcanel.se" ]]; then
    echo "❌ Unexpected certificate identifier: $identifier" >&2
    exit 1
fi

if [[ "$record_name" != "_acme-challenge.nocster-73.arcanel.se" ]]; then
    echo "❌ Unexpected challenge record: $record_name" >&2
    exit 1
fi

if [[ -z "$validation" ]]; then
    echo "❌ Missing challenge validation" >&2
    exit 1
fi

echo "✅ Certbot DNS challenge request accepted"

dns_cleanup_pending=true
run_timed_step \
    "Publishing DNS challenge" \
    scripts/tls/publish-dns-challenge.sh \
        "$record_name" \
        "$validation"

run_timed_step \
    "Waiting for DNS challenge visibility" \
    scripts/tls/wait-for-dns-challenge.sh \
        "$record_name" \
        "$validation"

run_timed_step \
    "Continuing Certbot renewal" \
    execute_remote_command \
        "$bredland_host" \
        "continue-renewal '$run_id'"

run_timed_step \
    "Waiting for test certificate issuance" \
    execute_remote_command \
        "$bredland_host" \
        "wait-renewal '$run_id'"

renewal_pending=false

echo
echo "→ Capturing issued test certificate for served-cert verification..."

execute_remote_command \
    "$bredland_host" \
    "cat '$remote_run_dir/fullchain.pem'" \
    > "$issued_expected_dir/fullchain.pem"

echo "✅ Issued test certificate captured"

echo
echo "=== Issued certificate validation ==="

run_timed_step \
    "Validating issued test certificate" \
    scripts/tls/validate-issued-certificate.sh \
        "$run_id"

echo
echo "=== Issued certificate provisioning ==="

run_timed_step \
    "Provisioning test certificate" \
    scripts/tls/provision-issued-certificate.sh \
        "$run_id"

provision_restore_pending=true

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

run_timed_step \
    "Activating test certificate" \
    scripts/tls/activate-issued-certificate.sh

run_timed_step \
    "Verifying test certificate is served" \
    scripts/tls/verify-served-certificate.sh \
        "$issued_expected_dir"

echo
echo "=== DNS challenge cleanup ==="

run_timed_step \
    "Removing DNS challenge" \
    scripts/tls/remove-dns-challenge.sh \
        "$record_name" \
        "$validation"

dns_cleanup_pending=false

echo
echo "=== Restoring original production certificate ==="

run_timed_step \
    "Restoring original installed certificate pair" \
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

run_timed_step \
    "Restoring original certificate timestamps" \
    execute_remote_command \
    "$bredland_host" \
    "sudo touch -m -r '$remote_run_dir/original-fullchain.timestamp' /etc/bredland/tls/fullchain.pem &&
     sudo touch -m -r '$remote_run_dir/original-privkey.timestamp' /etc/bredland/tls/privkey.pem"

run_timed_step \
    "Reactivating original production certificate" \
    scripts/tls/activate-issued-certificate.sh

run_timed_step \
    "Verifying original production certificate is served" \
    scripts/tls/verify-served-certificate.sh \
        "$original_expected_dir"

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

integration_elapsed=$((SECONDS - integration_started))

echo
echo "=== Live integration test complete (${integration_elapsed}s total) ==="
