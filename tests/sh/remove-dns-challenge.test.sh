#!/usr/bin/env bash
set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

fakebin="$tmpdir/bin"
uapi_log="$tmpdir/uapi-log"
fixture="tests/fixtures/dns/parse-zone.json"
stdout_file="$tmpdir/stdout"
stderr_file="$tmpdir/stderr"

mkdir -p "$fakebin"

export UAPI_LOG="$uapi_log"
export PARSE_ZONE_FIXTURE="$fixture"
export PATH="$fakebin:$PATH"

cat > "$fakebin/ssh" <<'EOF'
#!/usr/bin/env bash

remote="$1"
command="$2"

case "$command" in
    "uapi --output=json DNS parse_zone zone=arcanel.se")
            cat "$PARSE_ZONE_FIXTURE"
        ;;

    "uapi --output=json DNS mass_edit_zone "*)
        printf '%s\n' "$command" > "$UAPI_LOG"
        cat <<'JSON'
{
  "result": {
    "data": {
      "new_serial": "2026093003"
    },
    "errors": null,
    "status": 1
  }
}
JSON
        ;;

    *)
        echo "Unexpected remote command: $command" >&2
        exit 1
        ;;
esac
EOF

chmod +x "$fakebin/ssh"

secrets_file="$tmpdir/secrets.env"

cat > "$secrets_file" <<'EOF'
ODERLAND_SSH_USER=arcanel
ODERLAND_SSH_HOST=bandol
EOF

export BREDLAND_SECRETS_FILE="$secrets_file"

echo
echo "→ removes exact matching TXT record"

if ! scripts/tls/remove-dns-challenge.sh \
    "_brd-038-spike.arcanel.se" \
    "brd-038-test" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected DNS challenge removal to succeed"
    cat "$stderr_file"
    exit 1
fi

expected_call="uapi --output=json DNS mass_edit_zone zone=arcanel.se serial=2026093002 remove=469"

if [[ ! -e "$uapi_log" ]]; then
    echo "❌ Expected DNS zone edit"
    exit 1
fi

if ! grep -Fxq -- "$expected_call" "$uapi_log"; then
    echo "❌ Expected exact DNS zone edit"
    echo
    echo "Expected:"
    echo "  $expected_call"
    echo
    echo "Actual:"
    sed 's/^/  /' "$uapi_log"
    exit 1
fi

echo "✅ removes exact matching TXT record"

echo
echo "→ rejects missing TXT record"

missing_fixture="$tmpdir/parse-zone-missing.json"

jq '
    .result.data |= map(
        select(.record_type != "TXT")
    )
' "$fixture" > "$missing_fixture"

export PARSE_ZONE_FIXTURE="$missing_fixture"
rm -f "$uapi_log"

if scripts/tls/remove-dns-challenge.sh \
    "_brd-038-spike.arcanel.se" \
    "brd-038-test" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected missing DNS challenge to be rejected"
    exit 1
fi

if [[ -e "$uapi_log" ]]; then
    echo "❌ Expected no DNS zone edit"
    echo
    echo "Actual:"
    sed 's/^/  /' "$uapi_log"
    exit 1
fi

expected_error="Expected exactly one matching TXT record"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected explicit missing-record error"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

echo "✅ rejects missing TXT record"

echo
echo "→ rejects duplicate matching TXT records"

duplicate_fixture="$tmpdir/parse-zone-duplicate.json"

jq '
    .result.data += [
        (.result.data[]
        | select(.record_type == "TXT"))
    ]
' "$fixture" > "$duplicate_fixture"

export PARSE_ZONE_FIXTURE="$duplicate_fixture"
rm -f "$uapi_log"

if scripts/tls/remove-dns-challenge.sh \
    "_brd-038-spike.arcanel.se" \
    "brd-038-test" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected duplicate DNS challenge to be rejected"
    exit 1
fi

expected_error="Expected exactly one matching TXT record"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected explicit duplicate-record error"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

if [[ -e "$uapi_log" ]]; then
    echo "❌ Expected no DNS zone edit"
    echo
    echo "Actual:"
    sed 's/^/  /' "$uapi_log"
    exit 1
fi

echo "✅ rejects duplicate matching TXT records"
