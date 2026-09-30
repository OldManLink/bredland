#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/../lib/bredland.sh"
# shellcheck source=scripts/lib/utils.sh
source "$(dirname "$0")/../lib/utils.sh"
# shellcheck source=scripts/tls/lib/dns.sh
source "$(dirname "$0")/../tls/lib/dns.sh"

load_bredland_secrets

oderland_user="${ODERLAND_SSH_USER:?Missing ODERLAND_SSH_USER}"
oderland_host="${ODERLAND_SSH_HOST:?Missing ODERLAND_SSH_HOST}"
remote="${oderland_user}@${oderland_host}"

record_name="$1"
validation="$2"

zone="arcanel.se"

zone_json="$(read_dns_zone "$remote" "$zone")"
require_valid_zone_response "$zone_json"
serial="$(extract_zone_serial "$zone_json")"

line_indices="$(
    find_txt_line_indices \
        "$zone_json" \
        "$record_name"
)"

assert_line_count \
    "$line_indices" \
    0 \
    "DNS challenge already exists"

dname="${record_name%.}."

response="$(
    execute_remote_command \
        "$remote" \
        "uapi --output=json DNS mass_edit_zone zone=$zone serial=$serial add='{\"dname\":\"$dname\",\"ttl\":14400,\"record_type\":\"TXT\",\"data\":[\"$validation\"]}'"
)"

require_valid_zone_edit_response "$response"
