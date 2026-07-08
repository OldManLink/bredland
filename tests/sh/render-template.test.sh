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

    if diff -q "$template" "$output" >/dev/null; then
        echo "Rendered output did not differ from template: $template" >&2
        exit 1
    fi
}

# Test MikroTik install-noc-heartbeat.rsc.template
echo -n "Testing mikrotik/install-noc-heartbeat.rsc.template ... "
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
echo "OK"

# Test Oderland telemetry.endpoint.template.php
echo -n "Testing noc/telemetry.endpoint.template.php ... "
cat > "$tmpdir/telemetry.endpoint.env" <<'EOF'
# PHP magic constants
DIR=DIR
# Oderland stuff
TELEMETRY_CONFIG_FILE=/private/telemetry.config.php
EOF
run_render templates/noc/telemetry.endpoint.template.php \
"$tmpdir/telemetry.php" \
"$tmpdir/telemetry.endpoint.env"
echo "OK"

# Test Oderland telemetry.config.template.php
echo -n "testing noc/telemetry.config.template.php ... "
cat > "$tmpdir/telemetry.config.env" <<'EOF'
# Shared stuff (from MikroTik to Oderland)
MIKROTIK_NOC_HOST=mikrotik-test
MIKROTIK_NOC_TOKEN=mikrotik.v1.test-token
BREDLAND_NOC_HOST=bredland-test
BREDLAND_NOC_TOKEN=bredland.v1.test-token
# Oderland stuff
NOC_DATA_DIR=/private/data/
# Remove placeholder from config file
SMOKE_TEST_HOST_TOKEN_LINE=
EOF
run_render templates/noc/telemetry.config.template.php \
"$tmpdir/telemetry.config.php" \
"$tmpdir/telemetry.config.env"
echo "OK"

# Test Oderland rotate-daily-logs.template
echo -n "Testing noc/rotate-daily-logs.sh.template ... "
cat > "$tmpdir/rotate-daily-logs.env" <<'EOF'
# Oderland stuff
NOC_DATA_DIR=/private/data/
EOF
run_render templates/noc/rotate-daily-logs.sh.template \
"$tmpdir/rotate-daily-logs.sh" \
"$tmpdir/rotate-daily-logs.env"
echo "OK"

# Test Oderland consolidate-monthly-logs.template
echo -n "Testing noc/consolidate-monthly-logs.sh.template ... "
cat > "$tmpdir/consolidate-monthly-logs.env" <<'EOF'
# Oderland stuff
NOC_DATA_DIR=/private/data/
EOF
run_render templates/noc/consolidate-monthly-logs.sh.template \
"$tmpdir/consolidate-monthly-logs.sh" \
"$tmpdir/consolidate-monthly-logs.env"
echo "OK"

# Test NOC index.template.php
echo -n "Testing noc/index.template.php ... "
cat > "$tmpdir/noc-index.env" <<'EOF'
# Oderland stuff
TELEMETRY_CONFIG_FILE=/private/telemetry.config.php
STATIC_VERSION=42
EOF
run_render templates/noc/index.template.php \
"$tmpdir/index.php" \
"$tmpdir/noc-index.env"
grep -q 'private/telemetry.config.php' "$tmpdir/index.php"
grep -q '<!DOCTYPE html>' "$tmpdir/index.php"
echo "OK"

# Test bredland-heartbeat.service.template
echo -n "Testing bredland/bredland-heartbeat.service.template ... "
cat > "$tmpdir/bredland-heartbeat.service.env" <<'EOF'
# Bredland stuff
BREDLAND_HEARTBEAT_SCRIPT_FILE=/private/bredland-heartbeat
EOF
run_render templates/bredland/bredland-heartbeat.service.template \
"$tmpdir/bredland-heartbeat.service" \
"$tmpdir/bredland-heartbeat.service.env"
echo "OK"

# Test bredland-heartbeat.sh.template
echo -n "Testing bredland/bredland-heartbeat.sh.template ... "
cat > "$tmpdir/bredland-heartbeat.sh.env" <<'EOF'
# Bredland stuff
BREDLAND_NOC_HOST=bredland-test
BREDLAND_NOC_TOKEN=bredland.v1.test-token
TELEMETRY_ENDPOINT=https://example.invalid/telemetry
EOF
run_render templates/bredland/bredland-heartbeat.sh.template \
"$tmpdir/bredland-heartbeat.sh" \
"$tmpdir/bredland-heartbeat.sh.env"
echo "OK"

# Test bredland-heartbeat.timer.template
echo -n "Testing bredland/bredland-heartbeat.timer.template ... "
cat > "$tmpdir/bredland-heartbeat.timer.env" <<'EOF'
# Bredland stuff
BREDLAND_HEARTBEAT_SCHEDULE=5_and_10_past
EOF
run_render templates/bredland/bredland-heartbeat.timer.template \
"$tmpdir/bredland-heartbeat.timer" \
"$tmpdir/bredland-heartbeat.timer.env"
echo "OK"
