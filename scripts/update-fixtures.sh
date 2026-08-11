#!/usr/bin/env bash

set -euo pipefail

REQUIRED_FRESHNESS_SECONDS=30

# shellcheck source=scripts/lib/bredland.sh
source "$(dirname "$0")/lib/bredland.sh"
# shellcheck source=scripts/lib/utils.sh
source "$(dirname "$0")/lib/utils.sh"

load_bredland_secrets

command -v curl >/dev/null
command -v jq >/dev/null
command -v ssh >/dev/null

force=false

case "${1:-}" in
    "")
        ;;
    --force)
        force=true
        ;;
    *)
        echo "Usage: $0 [--force]" >&2
        exit 2
        ;;
esac

fixture_root="tests/fixtures"
heartbeat_dir="$fixture_root/heartbeats"
production_dir="$fixture_root/production"
timestamp_file="$fixture_root/last-fetched.timestamp"

hosts=(
    mikrotik
    bredland
)

fixtures_are_complete()
{
    local host

    [[ -s "$timestamp_file" ]] || return 1
    [[ -s "$production_dir/index.html" ]] || return 1
    [[ -s "$production_dir/style.css" ]] || return 1
    [[ -s "$production_dir/dashboard.js" ]] || return 1

    for host in "${hosts[@]}"; do
        [[ -s "$heartbeat_dir/${host}.json" ]] || return 1
    done
}

production_now_epoch()
{
    execute_remote_command 'date -u +%s'
}

fixtures_are_fresh_enough()
{
    local now_epoch
    local host
    local heartbeat_file
    local heartbeat_epoch
    local heartbeat_ttl
    local heartbeat_age
    local remaining_freshness
    local least_remaining=""

    fixtures_are_complete || return 1

    now_epoch="$(production_now_epoch)"

    for host in "${hosts[@]}"; do
        heartbeat_file="$heartbeat_dir/${host}.json"

        heartbeat_epoch="$(
            jq -er \
                '.ts | fromdateiso8601' \
                "$heartbeat_file"
        )"

        heartbeat_ttl="$(
            jq -er \
                '.ttl |
                 select(
                     type == "number" and
                     floor == . and
                     . > 0
                 )' \
                "$heartbeat_file"
        )"

        heartbeat_age=$((now_epoch - heartbeat_epoch))

        # A future heartbeat should not make the arithmetic misleading.
        if (( heartbeat_age < 0 )); then
            heartbeat_age=0
        fi

        remaining_freshness=$((heartbeat_ttl - heartbeat_age))

        if [[ -z "$least_remaining" ]] ||
           (( remaining_freshness < least_remaining )); then
            least_remaining="$remaining_freshness"
        fi

        if (( remaining_freshness >= REQUIRED_FRESHNESS_SECONDS )); then
            echo \
                "✅ ${host}: ${remaining_freshness}s freshness remaining"
        else
            echo \
                "⚠️ ${host}: only ${remaining_freshness}s freshness remaining"
        fi
    done

    if (( least_remaining < REQUIRED_FRESHNESS_SECONDS )); then
        return 1
    fi

    echo
    echo "Existing fixtures are fresh enough."
    echo "  Last fetched: $(cat "$timestamp_file")"
    echo "  Least remaining freshness: ${least_remaining}s"

    return 0
}

if ! $force && fixtures_are_fresh_enough; then
    echo
    echo "✅ Fixture refresh not required."
    exit 0
fi

if $force; then
    echo "⚠️ Forced fixture refresh requested."
else
    echo
    echo "Fixtures are missing or too close to expiry."
fi

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

staged_heartbeats="$tmpdir/heartbeats"
staged_production="$tmpdir/production"
staged_timestamp="$tmpdir/last-fetched.timestamp"

mkdir -p "$staged_heartbeats" "$staged_production"
mkdir -p "$staged_production/static"

fetch_latest_heartbeat="$(dirname "$0")/fetch-latest-heartbeat.sh"
dashboard_url="${NOC_DASHBOARD_URL:?Missing NOC_DASHBOARD_URL}"
dashboard_url="${dashboard_url%/}"

for host in "${hosts[@]}"; do
    staged_file="$staged_heartbeats/${host}.json"

    echo -n "Fetching latest ${host} heartbeat... "
    "$fetch_latest_heartbeat" "$host" > "$staged_file"

    # Confirm that the heartbeat has a valid positive integer TTL.
    jq -er \
        '.ttl |
         select(
             type == "number" and
             floor == . and
             . > 0
         )' \
        "$staged_file" \
        >/dev/null

    echo "OK"
done

index_headers="$tmpdir/index.headers"

echo -n "Fetching production index.html... "
curl --fail --silent --show-error \
    --dump-header "$index_headers" \
    "$dashboard_url/" \
    > "$staged_production/index.html"
echo "OK"

snapshot_timestamp="$(
    awk '
        tolower($0) ~ /^x-noc-now:/ {
            sub(/\r$/, "")
            sub(/^[^:]*:[[:space:]]*/, "")
            value = $0
        }

        END {
            print value
        }
    ' "$index_headers"
)"

if [[ -z "$snapshot_timestamp" ]]; then
    echo "Error: production response did not contain X-Noc-Now" >&2
    exit 1
fi

echo -n "Fetching production style.css... "
curl --fail --silent --show-error \
    "$dashboard_url/static/style.css" \
    > "$staged_production/static/style.css"
echo "OK"

echo -n "Fetching production dashboard.js... "
curl --fail --silent --show-error \
    "$dashboard_url/static/dashboard.js" \
    > "$staged_production/static/dashboard.js"
echo "OK"

printf '%s\n' "$snapshot_timestamp" > "$staged_timestamp"

mkdir -p "$heartbeat_dir" "$production_dir" "$production_dir/static"

for host in "${hosts[@]}"; do
    mv \
        "$staged_heartbeats/${host}.json" \
        "$heartbeat_dir/${host}.json"
done

mv "$staged_production/index.html" "$production_dir/index.html"
mv "$staged_production/static/style.css" "$production_dir/static/style.css"
mv "$staged_production/static/dashboard.js" "$production_dir/static/dashboard.js"

# Write this last: it acts as the commit marker for the complete fixture set.
mv "$staged_timestamp" "$timestamp_file"

echo
echo "✅ Production fixtures updated."
echo "   Snapshot timestamp: $(cat "$timestamp_file")"
