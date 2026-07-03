#!/usr/bin/env bash

set -euo pipefail

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"

load_bredland_secrets

if [[ "${SMOKE_TEST_DEPLOY:-0}" == "1" ]]; then
    enable_smoke_deploy
fi

if [[ $# -ne 2 ]]; then
    echo "Usage: $0 <template> <output>" >&2
    exit 1
fi

template="$1"
output="$2"

if [[ ! -f "$template" ]]; then
    echo "Template not found: $template" >&2
    exit 1
fi

rendered="$(cat "$template")"

while IFS= read -r placeholder; do
    var_name="${placeholder#__}"
    var_name="${var_name%__}"

    if [[ -z "${!var_name+x}" ]]; then
        echo "Missing environment variable: $var_name" >&2
        exit 1
    fi

    value="${!var_name}"
    rendered="${rendered//$placeholder/$value}"
done < <(grep -o '__[A-Z0-9_]\+__' "$template" | sort -u)

# Fail if any placeholders remain unresolved.
if grep -q '__[A-Z0-9_]\+__' <<<"$rendered"; then
    echo "ERROR: Unresolved placeholders remain after rendering." >&2
    exit 1
fi

mkdir -p "$(dirname "$output")"
printf '%s\n' "$rendered" > "$output"
