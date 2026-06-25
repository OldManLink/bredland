#!/usr/bin/env bash

set -euo pipefail

if [[ $# -ne 2 ]]; then
    echo "Usage: $0 <subsystem> <version>" >&2
    echo "Example: $0 mikrotik v1" >&2
    exit 1
fi

subsystem="$1"
version="$2"

if [[ ! "$subsystem" =~ ^[a-z][a-z0-9-]*$ ]]; then
    echo "Invalid subsystem: $subsystem" >&2
    exit 1
fi

if [[ ! "$version" =~ ^v[0-9]+$ ]]; then
    echo "Invalid version: $version" >&2
    exit 1
fi

token="$(openssl rand -base64 48 | tr -d '\n' | tr '+/' '-_')"

printf '%s.%s.%s\n' "$subsystem" "$version" "$token"
