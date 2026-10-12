#!/usr/bin/env bash
set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

fakebin="$tmpdir/bin"
systemctl_log="$tmpdir/systemctl-log"
stderr_file="$tmpdir/stderr"

mkdir -p "$fakebin"

export PATH="$fakebin:$PATH"
export SYSTEMCTL_LOG="$systemctl_log"

cat > "$fakebin/systemctl" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" >> "$SYSTEMCTL_LOG"

exit 0
EOF

chmod +x "$fakebin/systemctl"

echo
echo "→ restarts trusted discovery service"

if ! bash templates/bredland/activate-issued-certificate.sh.template \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected certificate activation to succeed"
    cat "$stderr_file"
    exit 1
fi

expected="restart trusted-discovery.service"
actual="$(sed -n '1p' "$systemctl_log")"

if [[ "$actual" != "$expected" ]]; then
    echo "❌ Expected trusted discovery service restart"
    echo
    echo "Expected:"
    echo "  $expected"
    echo
    echo "Actual:"
    echo "  $actual"
    exit 1
fi

echo "✅ restarts trusted discovery service"

echo
echo "→ verifies trusted discovery service is active"

: > "$systemctl_log"

if ! bash templates/bredland/activate-issued-certificate.sh.template \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected certificate activation to succeed"
    cat "$stderr_file"
    exit 1
fi

expected=$'restart trusted-discovery.service\nis-active --quiet trusted-discovery.service'
actual="$(cat "$systemctl_log")"

if [[ "$actual" != "$expected" ]]; then
    echo "❌ Expected trusted discovery service restart and active check"
    echo
    echo "Expected:"
    printf '  %s\n' "$expected"
    echo
    echo "Actual:"
    sed 's/^/  /' "$systemctl_log"
    exit 1
fi

echo "✅ verifies trusted discovery service is active"

echo
echo "→ reports inactive trusted discovery service"

cat > "$fakebin/systemctl" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" >> "$SYSTEMCTL_LOG"

if [[ "$1" == "is-active" ]]; then
    exit 1
fi

exit 0
EOF

chmod +x "$fakebin/systemctl"

: > "$systemctl_log"
: > "$stderr_file"

if bash templates/bredland/activate-issued-certificate.sh.template \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected certificate activation to fail"
    exit 1
fi

expected_error="Trusted discovery service failed to become active"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected inactive service error"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

echo "✅ reports inactive trusted discovery service"

echo
echo "→ reports trusted discovery restart failure"

cat > "$fakebin/systemctl" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" >> "$SYSTEMCTL_LOG"

if [[ "$1" == "restart" ]]; then
    echo "restart failed" >&2
    exit 1
fi

exit 0
EOF

chmod +x "$fakebin/systemctl"

: > "$systemctl_log"
: > "$stderr_file"

if bash templates/bredland/activate-issued-certificate.sh.template \
    >/dev/null \
    2>"$stderr_file"
then
    echo "❌ Expected certificate activation to fail"
    exit 1
fi

expected_error="Failed to restart trusted discovery service"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected restart failure error"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

echo "✅ reports trusted discovery restart failure"
