#!/usr/bin/env bash
set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

fakebin="$tmpdir/bin"
ssh_log="$tmpdir/ssh-log"
stderr_file="$tmpdir/stderr"

mkdir -p "$fakebin"

export PATH="$fakebin:$PATH"
export SSH_LOG="$ssh_log"

cat > "$fakebin/ssh" <<'EOF'
#!/usr/bin/env bash

remote="$1"
command="$2"

printf '%s\n' "$remote" > "$SSH_LOG"
printf '%s\n' "$command" >> "$SSH_LOG"

if [[ "${SSH_FAIL:-false}" == true ]]; then
    echo "Certificate does not match private key" >&2
    exit 1
fi

exit 0
EOF

chmod +x "$fakebin/ssh"

secrets_file="$tmpdir/secrets.env"

cat > "$secrets_file" <<'EOF'
BREDLAND_SSH_HOST=bredland
EOF

export BREDLAND_SECRETS_FILE="$secrets_file"

echo
echo "→ validates issued certificate on Bredland"

export SSH_FAIL=false

if ! scripts/tls/validate-issued-certificate.sh \
    "brd-038-test-run" \
    > /dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected issued certificate validation to succeed"
    cat "$stderr_file"
    exit 1
fi

expected_remote="bredland"
expected_command="/usr/local/bin/validate-issued-certificate /tmp/brd-038-test-run"

actual_remote="$(sed -n '1p' "$ssh_log")"
actual_command="$(sed -n '2p' "$ssh_log")"

if [[ "$actual_remote" != "$expected_remote" ]]; then
    echo "❌ Expected correct Bredland host"
    echo
    echo "Expected:"
    echo "  $expected_remote"
    echo
    echo "Actual:"
    echo "  $actual_remote"
    exit 1
fi

if [[ "$actual_command" != "$expected_command" ]]; then
    echo "❌ Expected correct remote validator command"
    echo
    echo "Expected:"
    echo "  $expected_command"
    echo
    echo "Actual:"
    echo "  $actual_command"
    exit 1
fi

echo "✅ validates issued certificate on Bredland"

echo
echo "→ surfaces remote validation failure"

export SSH_FAIL=true

if scripts/tls/validate-issued-certificate.sh \
    "brd-038-test-run" \
    > /dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected issued certificate validation to fail"
    exit 1
fi

expected_error="Certificate does not match private key"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected remote validation error to be surfaced"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

echo "✅ surfaces remote validation failure"
