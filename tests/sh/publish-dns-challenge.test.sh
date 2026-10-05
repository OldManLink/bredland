#!/usr/bin/env bash
set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

fakebin="$tmpdir/bin"
uapi_log="$tmpdir/uapi-log"
stdout_file="$tmpdir/stdout"
stderr_file="$tmpdir/stderr"

mkdir -p "$fakebin"

export UAPI_LOG="$uapi_log"
export PATH="$fakebin:$PATH"

cat > "$fakebin/ssh" <<'EOF'
#!/usr/bin/env bash

remote="$1"
command="$2"

case "$command" in
    "uapi --output=json DNS parse_zone zone=arcanel.se")
            cat "tests/fixtures/dns/parse-zone.json"
        ;;

    "uapi --output=json DNS mass_edit_zone "*)
        printf '%s\n' "$command" > "$UAPI_LOG"
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
