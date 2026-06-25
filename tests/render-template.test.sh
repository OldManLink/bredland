#!/usr/bin/env bash

set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

cat > "$tmpdir/secrets.env" <<'EOF'
MIKROTIK_NOC_ENDPOINT=https://example.invalid/mikrotik
MIKROTIK_NOC_TOKEN=mikrotik.v1.test-token
MIKROTIK_NOC_HOST=mikrotik-test
MIKROTIK_NOC_INTERVAL=5m
MIKROTIK_NOC_SCRIPT_NAME=noc-heartbeat
MIKROTIK_NOC_SCHEDULER_NAME=noc-heartbeat
MIKROTIK_CONFIG_FILE=/private/mikrotik.config.php
MIKROTIK_DATA_DIR=/private/data/
EOF

run_render() {
    local template="$1"
    local output="$2"

    BREDLAND_SECRETS_FILE="$tmpdir/secrets.env" \
        scripts/render-template.sh "$template" "$output"

    [[ -s "$output" ]]

    if grep -q '__[A-Z0-9_]\+__' "$output"; then
        echo "Unresolved placeholders remain in $output" >&2
        exit 1
    fi

    if grep -q '""' "$output"; then
        echo "Suspicious doubled quotes in $output" >&2
        exit 1
    fi

    if diff -q "$template" "$output" >/dev/null; then
        echo "Rendered output did not differ from template: $template" >&2
        exit 1
    fi
}

run_render mikrotik/install-noc-heartbeat.rsc.template "$tmpdir/noc-heartbeat.rsc"
run_render oderland/mikrotik.endpoint.template.php "$tmpdir/mikrotik.php"
run_render oderland/mikrotik.config.template.php "$tmpdir/mikrotik.config.php"

echo "render-template tests passed"