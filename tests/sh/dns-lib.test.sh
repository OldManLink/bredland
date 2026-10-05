#!/usr/bin/env bash
set -euo pipefail

source scripts/tls/lib/dns.sh

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

fixture="tests/fixtures/dns/parse-zone.json"
stdout_file="$tmpdir/stdout"
stderr_file="$tmpdir/stderr"

echo
echo "→ rejects malformed DNS zone response"

if require_valid_zone_response \
    "this is not json" \
    2>"$stderr_file"
then
    echo "❌ Expected malformed DNS zone response to be rejected"
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

echo "✅ rejects malformed DNS zone response"

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

if require_valid_zone_response \
    "$(cat "$parse_failure")" \
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

if extract_zone_serial \
    "$(cat "$missing_serial")" \
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

if extract_zone_serial \
    "$(cat "$duplicate_soa")" \
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

echo "✅ rejects multiple SOA serials"

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

if require_valid_zone_edit_response \
    "$(cat "$stale_response")" \
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
echo "→ rejects malformed zone-edit response"

malformed_edit="$tmpdir/mass-edit-malformed.json"

cat > "$malformed_edit" <<'EOF'
this is not json
EOF

if require_valid_zone_edit_response \
    "$(cat "$malformed_edit")" \
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
