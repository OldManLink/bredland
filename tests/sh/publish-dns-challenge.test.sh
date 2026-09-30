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
        if [[ -n "${PARSE_ZONE_RESPONSE:-}" ]]; then
            cat "$PARSE_ZONE_RESPONSE"
        else
            cat "$PARSE_ZONE_FIXTURE"
        fi
        ;;

    "uapi --output=json DNS mass_edit_zone "*)
        printf '%s\n' "$command" > "$UAPI_LOG"

        if [[ -n "${MASS_EDIT_RESPONSE:-}" ]]; then
            cat "$MASS_EDIT_RESPONSE"
        else
            cat <<'JSON'
{
  "result": {
    "data": {
      "new_serial": "2026093002"
    },
    "errors": null,
    "status": 1
  }
}
JSON
        fi
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
echo "→ publishes TXT record"

if ! scripts/tls/publish-dns-challenge.sh \
    "_brd-038-spike-add.arcanel.se" \
    "brd-038-test" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected DNS challenge publication to succeed"
    cat "$stderr_file"
    exit 1
fi

expected_call='uapi --output=json DNS mass_edit_zone zone=arcanel.se serial=2026093002 add='\''{"dname":"_brd-038-spike-add.arcanel.se.","ttl":14400,"record_type":"TXT","data":["brd-038-test"]}'\'''

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

echo "✅ publishes TXT record"

echo
echo "→ rejects existing TXT record"

export PARSE_ZONE_RESPONSE="$fixture"
unset MASS_EDIT_RESPONSE
rm -f "$uapi_log"

if scripts/tls/publish-dns-challenge.sh \
    "_brd-038-spike.arcanel.se" \
    "brd-038-test" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected existing DNS challenge to be rejected"
    exit 1
fi

expected_error="DNS challenge already exists"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected explicit existing-record error"
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

echo "✅ rejects existing TXT record"

echo
echo "→ rejects stale zone serial"

stale_response="$tmpdir/stale-response.json"

cat > "$stale_response" <<'JSON'
{
  "result": {
    "data": {
      "detail": null,
      "type": "Stale"
    },
    "errors": [
      "The given serial number (2026091800) does not match the DNS zone’s serial number (2026093000). Refresh your view of the DNS zone, then resubmit."
    ],
    "status": 0
  }
}
JSON

export PARSE_ZONE_RESPONSE="$fixture"
export MASS_EDIT_RESPONSE="$stale_response"
rm -f "$uapi_log"

if scripts/tls/publish-dns-challenge.sh \
    "_brd-038-spike-add.arcanel.se" \
    "brd-038-test" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected stale zone serial to be rejected"
    exit 1
fi
expected_error="The given serial number (2026091800) does not match the DNS zone’s serial number (2026093000). Refresh your view of the DNS zone, then resubmit."

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected stale-serial error to be surfaced"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

echo "✅ rejects stale zone serial"

echo
echo "→ rejects parse-zone API failure"

parse_failure="$tmpdir/parse-zone-failure.json"

cat > "$parse_failure" <<'JSON'
{
  "result": {
    "data": null,
    "errors": [
      "Unable to parse DNS zone"
    ],
    "status": 0
  }
}
JSON

export PARSE_ZONE_RESPONSE="$parse_failure"
unset MASS_EDIT_RESPONSE
rm -f "$uapi_log"

if scripts/tls/publish-dns-challenge.sh \
    "_brd-038-spike-add.arcanel.se" \
    "brd-038-test" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected parse-zone failure to be rejected"
    exit 1
fi

expected_error="Unable to parse DNS zone"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected parse-zone error to be surfaced"
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
    exit 1
fi

echo "✅ rejects parse-zone API failure"

echo
echo "→ rejects missing SOA serial"

missing_serial="$tmpdir/parse-zone-missing-serial.json"

jq '
    .result.data |= map(
        if .record_type == "SOA"
        then .data_b64[2] = ""
        else .
        end
    )
' "$fixture" > "$missing_serial"

export PARSE_ZONE_RESPONSE="$missing_serial"
unset MASS_EDIT_RESPONSE
rm -f "$uapi_log"

if scripts/tls/publish-dns-challenge.sh \
    "_brd-038-spike-add.arcanel.se" \
    "brd-038-test" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected missing SOA serial to be rejected"
    exit 1
fi

expected_error="Expected exactly one SOA serial"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected explicit missing-serial error"
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
    exit 1
fi

echo "✅ rejects missing SOA serial"

echo
echo "→ rejects multiple SOA serials"

duplicate_soa="$tmpdir/parse-zone-duplicate-soa.json"

jq '
    .result.data += [
        (.result.data[]
        | select(.record_type == "SOA"))
    ]
' "$fixture" > "$duplicate_soa"

export PARSE_ZONE_RESPONSE="$duplicate_soa"
unset MASS_EDIT_RESPONSE
rm -f "$uapi_log"

if scripts/tls/publish-dns-challenge.sh \
    "_brd-038-spike-add.arcanel.se" \
    "brd-038-test" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected multiple SOA serials to be rejected"
    exit 1
fi

expected_error="Expected exactly one SOA serial"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected explicit multiple-serial error"
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
    exit 1
fi

echo "✅ rejects multiple SOA serials"

echo
echo "→ rejects malformed parse-zone response"

malformed_parse="$tmpdir/parse-zone-malformed.json"

cat > "$malformed_parse" <<'EOF'
this is not json
EOF

export PARSE_ZONE_RESPONSE="$malformed_parse"
unset MASS_EDIT_RESPONSE
rm -f "$uapi_log"

if scripts/tls/publish-dns-challenge.sh \
    "_brd-038-spike-add.arcanel.se" \
    "brd-038-test" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected malformed parse-zone response to be rejected"
    exit 1
fi

expected_error="Invalid DNS zone response"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected explicit malformed-response error"
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
    exit 1
fi

echo "✅ rejects malformed parse-zone response"

echo
echo "→ rejects malformed zone-edit response"

malformed_edit="$tmpdir/mass-edit-malformed.json"

cat > "$malformed_edit" <<'EOF'
this is not json
EOF

export PARSE_ZONE_RESPONSE="$fixture"
export MASS_EDIT_RESPONSE="$malformed_edit"
rm -f "$uapi_log"

if scripts/tls/publish-dns-challenge.sh \
    "_brd-038-spike-add.arcanel.se" \
    "brd-038-test" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected malformed zone-edit response to be rejected"
    exit 1
fi

expected_error="Invalid DNS zone edit response"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected explicit malformed zone-edit error"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

echo "✅ rejects malformed zone-edit response"
