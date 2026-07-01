#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$repo_root"

example_file="config/bredland.example.env"
secrets_file="/etc/bredland/secrets.env"

if [[ "${CHECK_LOCAL_SECRETS:-0}" != "1" ]]; then
    echo "Skipping local secrets test (CHECK_LOCAL_SECRETS not set)"
    exit 0
fi

if [[ ! -f "$secrets_file" ]]; then
    echo "Missing local secrets file: $secrets_file" >&2
    exit 1
fi

set -a
# shellcheck disable=SC1090
source "$secrets_file"
set +a

missing=0

while IFS= read -r name; do
    if [[ -z "${!name+x}" ]]; then
        echo "Missing in $secrets_file: $name" >&2
        missing=1
    fi
done < <(
    sed -nE 's/^[[:space:]]*([A-Z][A-Z0-9_]*)=.*/\1/p' "$example_file" | sort -u
)

if (( missing )); then
    exit 1
fi

echo "local secrets match example env"