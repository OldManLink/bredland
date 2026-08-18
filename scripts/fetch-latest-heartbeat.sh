#!/usr/bin/env bash

set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"
# shellcheck source=scripts/lib/utils.sh
source "$(dirname "$0")/lib/utils.sh"

usage()
{
    echo "Usage: $0 HOST" >&2
}

if [[ $# -ne 1 ]]; then
    usage
    exit 2
fi

host="$1"

if [[ ! "$host" =~ ^[a-zA-Z0-9._-]+$ ]]; then
    echo "Invalid host name: '$host'." >&2
    exit 2
fi

load_bredland_secrets

oderland_user="${ODERLAND_SSH_USER:?Missing ODERLAND_SSH_USER}"
oderland_host="${ODERLAND_SSH_HOST:?Missing ODERLAND_SSH_HOST}"

command -v jq >/dev/null
command -v ssh >/dev/null

date_utc="$(date -u +%Y-%m-%d)"
remote_file="${NOC_DATA_DIR:?Missing NOC_DATA_DIR}/${host}-${date_utc}.jsonl"

tmpfile="$(mktemp)"
trap 'rm -f "$tmpfile"' EXIT

execute_remote_command \
    "${oderland_user}@${oderland_host}" \
    "tail -n 1 '$remote_file'" |
    jq . > "$tmpfile"

actual_host="$(jq -er '.host' "$tmpfile")"

if [[ "$actual_host" != "$host" ]]; then
    echo "Expected host '$host', got '$actual_host'." >&2
    exit 1
fi

# Confirm that the timestamp exists and is parseable.
jq -er '.ts | fromdateiso8601' "$tmpfile" >/dev/null

cat "$tmpfile"
