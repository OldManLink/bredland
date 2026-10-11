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

cleanup_remote_run_dir()
{
    if [[ -n "$remote_run_dir" ]]; then
        execute_remote_command \
            "$bredland_host" \
            "rm -rf '$remote_run_dir'" \
            >/dev/null 2>&1 || true
    fi
}

trap cleanup_remote_run_dir EXIT

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

echo "=== Live integration test complete ==="

