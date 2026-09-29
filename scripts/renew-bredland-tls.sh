#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"
# shellcheck source=scripts/lib/utils.sh
source "$(dirname "$0")/lib/utils.sh"
# shellcheck source=scripts/lib/deploy.sh
source "$(dirname "$0")/lib/deploy.sh"

load_bredland_secrets

publish_dns_challenge="${TLS_PUBLISH_DNS_CHALLENGE:-scripts/tls/publish-dns-challenge.sh}"
wait_for_dns_challenge="${TLS_WAIT_FOR_DNS_CHALLENGE:-scripts/tls/wait-for-dns-challenge.sh}"
validate_issued_certificate="${TLS_VALIDATE_ISSUED_CERTIFICATE:-scripts/tls/validate-issued-certificate.sh}"
provision_issued_certificate="${TLS_PROVISION_ISSUED_CERTIFICATE:-scripts/tls/provision-issued-certificate.sh}"
activate_issued_certificate="${TLS_ACTIVATE_ISSUED_CERTIFICATE:-scripts/tls/activate-issued-certificate.sh}"
verify_served_certificate="${TLS_VERIFY_SERVED_CERTIFICATE:-scripts/tls/verify-served-certificate.sh}"
remove_dns_challenge="${TLS_REMOVE_DNS_CHALLENGE:-scripts/tls/remove-dns-challenge.sh}"

bredland_host="${BREDLAND_SSH_HOST:?Missing BREDLAND_SSH_HOST}"

run_id="$(printf '%04x%04x%04x' "$RANDOM" "$RANDOM" "$RANDOM")"

run_step \
    "Starting Bredland TLS renewal" \
    execute_remote_command \
    "$bredland_host" \
    "start-renewal $run_id"

request="$(
    execute_remote_command \
        "$bredland_host" \
        "cat /run/brd-038/$run_id/request"
)"

identifier="$(
    printf '%s\n' "$request" \
        | sed -n 's/^identifier=//p'
)"

if [[ "$identifier" != "nocster-73.arcanel.se" ]]; then
    echo "Unexpected certificate identifier: $identifier" >&2
    exit 1
fi

record="$(
    printf '%s\n' "$request" \
        | sed -n 's/^record=//p'
)"

if [[ "$record" != "_acme-challenge.nocster-73.arcanel.se" ]]; then
    echo "Unexpected challenge record: $record" >&2
    exit 1
fi

validation="$(
    printf '%s\n' "$request" \
        | sed -n 's/^validation=//p'
)"

if [[ -z "$validation" ]]; then
    echo "Missing challenge validation" >&2
    exit 1
fi

run_step \
    "Publishing DNS challenge" \
    "$publish_dns_challenge" \
    "$record" \
    "$validation"

run_step \
    "Waiting for DNS challenge visibility" \
    "$wait_for_dns_challenge" \
    "$record" \
    "$validation"

run_step \
    "Continuing Bredland TLS renewal" \
    execute_remote_command \
    "$bredland_host" \
    "continue-renewal $run_id"

run_step \
    "Waiting for Bredland TLS renewal" \
    execute_remote_command \
    "$bredland_host" \
    "wait-renewal $run_id"

run_step \
    "Validating issued certificate" \
    "$validate_issued_certificate" \
    "$run_id"

run_step \
    "Provisioning issued certificate" \
    "$provision_issued_certificate" \
    "$run_id"

run_step \
    "Activating issued certificate" \
    "$activate_issued_certificate" \
    "$run_id"

run_step \
    "Verifying served certificate" \
    "$verify_served_certificate" \
    "$run_id"

run_step \
    "Removing DNS challenge" \
    "$remove_dns_challenge" \
    "$record" \
    "$validation"
