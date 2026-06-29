#!/usr/bin/env bash

set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

run_render() {
    local template="$1"
    local output="$2"
    local secrets_file="$3"

    BREDLAND_SECRETS_FILE="$secrets_file" scripts/render-template.sh "$template" "$output"

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

# Test MikroTik install-noc-heartbeat.rsc.template
cat > "$tmpdir/install-noc-heartbeat.env" <<'EOF'
# MikroTik stuff
MIKROTIK_HEARTBEAT_SCRIPT_NAME=noc-heartbeat
MIKROTIK_HEARTBEAT_SCHEDULER_NAME=noc-heartbeat
MIKROTIK_HEARTBEAT_INTERVAL=5m
# Shared stuff (from MikroTik to Oderland)
MIKROTIK_NOC_HOST=mikrotik-test
MIKROTIK_NOC_TOKEN=mikrotik.v1.test-token
# Oderland stuff
TELEMETRY_ENDPOINT=https://example.invalid/telemetry
EOF
run_render templates/mikrotik/install-noc-heartbeat.rsc.template \
"$tmpdir/noc-heartbeat.rsc" \
"$tmpdir/install-noc-heartbeat.env"

# Test Oderland telemetry.endpoint.template.php
cat > "$tmpdir/telemetry.endpoint.env" <<'EOF'
# Oderland stuff
TELEMETRY_CONFIG_FILE=/private/telemetry.config.php
EOF
run_render templates/oderland/telemetry.endpoint.template.php \
"$tmpdir/telemetry.php" \
"$tmpdir/telemetry.endpoint.env"

# Test Oderland telemetry.config.template.php
cat > "$tmpdir/telemetry.config.env" <<'EOF'
# Shared stuff (from MikroTik to Oderland)
MIKROTIK_NOC_TOKEN=mikrotik.v1.test-token
# Oderland stuff
NOC_DATA_DIR=/private/data/
EOF
run_render templates/oderland/telemetry.config.template.php \
"$tmpdir/telemetry.config.php" \
"$tmpdir/telemetry.config.env"

# Test Oderland rotate-logs.template
cat > "$tmpdir/rotate-logs.env" <<'EOF'
# Oderland stuff
NOC_DATA_DIR=/private/data/
EOF
run_render templates/oderland/rotate-logs.sh.template \
"$tmpdir/rotate-logs.sh" \
"$tmpdir/rotate-logs.env"

# Test bredland-heartbeat.service.template
cat > "$tmpdir/bredland-heartbeat.service.env" <<'EOF'
# Bredland stuff
BREDLAND_HEARTBEAT_SCRIPT_FILE=/private/bredland-heartbeat
EOF
run_render templates/bredland/bredland-heartbeat.service.template \
"$tmpdir/bredland-heartbeat.service" \
"$tmpdir/bredland-heartbeat.service.env"

# Test bredland-heartbeat.sh.template
cat > "$tmpdir/bredland-heartbeat.sh.env" <<'EOF'
# Bredland stuff
BREDLAND_NOC_HOST=bredland-test
BREDLAND_NOC_TOKEN=bredland.v1.test-token
TELEMETRY_ENDPOINT=https://example.invalid/telemetry
EOF
run_render templates/bredland/bredland-heartbeat.sh.template \
"$tmpdir/bredland-heartbeat.sh" \
"$tmpdir/bredland-heartbeat.sh.env"

# Test bredland-heartbeat.timer.template
cat > "$tmpdir/bredland-heartbeat.timer.env" <<'EOF'
# Bredland stuff
BREDLAND_HEARTBEAT_INTERVAL=5min
EOF
run_render templates/bredland/bredland-heartbeat.timer.template \
"$tmpdir/bredland-heartbeat.timer" \
"$tmpdir/bredland-heartbeat.timer.env"

echo "render-template tests passed"