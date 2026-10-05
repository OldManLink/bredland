#!/usr/bin/env bash
set -euo pipefail

record_name="_brd-038-live-$(date +%s).arcanel.se"
validation="brd-038-live-test"

echo
echo "=== BRD-038 live DNS challenge integration test ==="
echo "Record: $record_name"
echo "Value:  $validation"
echo

echo "→ Publishing DNS challenge..."
publish_started=$SECONDS

scripts/tls/publish-dns-challenge.sh \
    "$record_name" \
    "$validation"

publish_elapsed=$((SECONDS - publish_started))

echo "✅ DNS challenge published (${publish_elapsed}s)"
echo

echo "→ Discovering authoritative nameservers..."

authoritative_servers=()

while IFS= read -r server; do
    authoritative_servers+=("$server")
done < <(
    dig +short NS arcanel.se
)

printf '  %s\n' "${authoritative_servers[@]}"
echo

overall_started=$SECONDS

for server in "${authoritative_servers[@]}"; do
    echo "→ Waiting for $server..."
    server_started=$SECONDS

    while true; do
        result="$(
            dig "@$server" +short TXT "$record_name"
        )"

        if [[ "$result" == "\"$validation\"" ]]; then
            break
        fi

        sleep 1
    done

    server_elapsed=$((SECONDS - server_started))
    overall_elapsed=$((SECONDS - overall_started))

    echo "✅ $server sees challenge after ${server_elapsed}s (${overall_elapsed}s since authoritative wait began)"
done

echo
echo "→ Waiting for public recursive DNS..."
public_started=$SECONDS

while true; do
    result="$(
        dig +short TXT "$record_name"
    )"

    if [[ "$result" == "\"$validation\"" ]]; then
        break
    fi

    sleep 1
done

public_elapsed=$((SECONDS - public_started))
overall_elapsed=$((SECONDS - overall_started))

echo "✅ Public DNS sees challenge after ${public_elapsed}s (${overall_elapsed}s total propagation)"
echo

echo "→ Removing DNS challenge..."
remove_started=$SECONDS

scripts/tls/remove-dns-challenge.sh \
    "$record_name" \
    "$validation"

remove_elapsed=$((SECONDS - remove_started))

echo "✅ DNS challenge removed (${remove_elapsed}s)"
echo

echo "=== Live integration test complete ==="

