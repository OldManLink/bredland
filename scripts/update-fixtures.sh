#!/usr/bin/env bash

set -euo pipefail

HEARTBEAT_TTL_SECONDS=300
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

oldest_heartbeat_epoch()
{
    local host
    local heartbeat_epoch
    local oldest=""

    for host in "${hosts[@]}"; do
        heartbeat_epoch="$(
            jq -er '.ts | fromdateiso8601' \
                "$heartbeat_dir/${host}.json"
        )"

        if [[ -z "$oldest" ]] || (( heartbeat_epoch < oldest )); then
            oldest="$heartbeat_epoch"
        fi
    done

    printf '%s\n' "$oldest"
}

fixtures_are_fresh_enough()
{
    local now
    local oldest
    local oldest_age
    local remaining_freshness

    fixtures_are_complete || return 1

    now="$(date -u +%s)"
    oldest="$(oldest_heartbeat_epoch)" || return 1

    oldest_age=$((now - oldest))

    # A future timestamp should not make the arithmetic misleading.
    if (( oldest_age < 0 )); then
        oldest_age=0
    fi

    remaining_freshness=$((HEARTBEAT_TTL_SECONDS - oldest_age))

    if (( remaining_freshness < REQUIRED_FRESHNESS_SECONDS )); then
        return 1
    fi

    echo "Existing fixtures are fresh enough."
    echo "  Last fetched: $(cat "$timestamp_file")"
    echo "  Oldest heartbeat age: ${oldest_age}s"
    echo "  Remaining freshness: ${remaining_freshness}s"

    return 0
}

if ! $force && fixtures_are_fresh_enough; then
    echo "✅ Fixture refresh not required."
    exit 0
fi

if $force; then
    echo "⚠️ Forced fixture refresh requested."
else
    echo "Fixtures are missing or too close to expiry."
fi

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

staged_heartbeats="$tmpdir/heartbeats"
staged_production="$tmpdir/production"

mkdir -p "$staged_heartbeats" "$staged_production"
mkdir -p "$staged_production/static"

date_utc="$(date -u +%Y-%m-%d)"
dashboard_url="${NOC_DASHBOARD_URL:?Missing NOC_DASHBOARD_URL}"
dashboard_url="${dashboard_url%/}"

for host in "${hosts[@]}"; do
    remote_file="${NOC_DATA_DIR:?Missing NOC_DATA_DIR}/${host}-${date_utc}.jsonl"
    staged_file="$staged_heartbeats/${host}.json"

    echo -n "Fetching latest ${host} heartbeat... "

    execute_remote_command "tail -n 1 '$remote_file'" |
        jq . > "$staged_file"

    actual_host="$(jq -er '.host' "$staged_file")"

    if [[ "$actual_host" != "$host" ]]; then
        echo
        echo "Expected host '$host', got '$actual_host'." >&2
        exit 1
    fi

    # Confirm that the timestamp exists and is parseable.
    jq -er '.ts | fromdateiso8601' "$staged_file" >/dev/null

    echo "OK"
done

echo -n "Fetching production index.html... "
curl --fail --silent --show-error \
    "$dashboard_url/" \
    > "$staged_production/index.html"
echo "OK"

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
date -u +"%Y-%m-%dT%H:%M:%SZ" > "$tmpdir/last-fetched.timestamp"
mv "$tmpdir/last-fetched.timestamp" "$timestamp_file"

echo
echo "✅ Production fixtures updated."
echo "   Snapshot timestamp: $(cat "$timestamp_file")"
