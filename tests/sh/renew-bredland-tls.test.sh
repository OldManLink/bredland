#!/usr/bin/env bash
set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

fakebin="$tmpdir/bin"
secrets_file="$tmpdir/secrets.env"
stdout_file="$tmpdir/stdout"
stderr_file="$tmpdir/stderr"
ssh_log="$tmpdir/ssh-log"
ssh_request_file="$tmpdir/ssh-request"

mkdir -p "$fakebin"

cat > "$fakebin/ssh" <<'EOF'
#!/usr/bin/env bash
exit 42
EOF

chmod +x "$fakebin/ssh"

cat > "$secrets_file" <<'EOF'
BREDLAND_SSH_HOST=bredland-test
EOF

export PATH="$fakebin:$PATH"
export SSH_REQUEST_FILE="$ssh_request_file"
export SSH_LOG="$ssh_log"
export TLS_PUBLISH_DNS_CHALLENGE="$fakebin/publish-dns-challenge"
export TLS_WAIT_FOR_DNS_CHALLENGE="$fakebin/wait-for-dns-challenge"
export TLS_VALIDATE_ISSUED_CERTIFICATE="$fakebin/validate-issued-certificate"
export TLS_PROVISION_ISSUED_CERTIFICATE="$fakebin/provision-issued-certificate"
export TLS_ACTIVATE_ISSUED_CERTIFICATE="$fakebin/activate-issued-certificate"
export TLS_VERIFY_SERVED_CERTIFICATE="$fakebin/verify-served-certificate"
export TLS_REMOVE_DNS_CHALLENGE="$fakebin/remove-dns-challenge"

install_noop_tls_helpers()
{
    local helper

    for helper in \
        publish-dns-challenge \
        wait-for-dns-challenge \
        validate-issued-certificate \
        provision-issued-certificate \
        activate-issued-certificate \
        verify-served-certificate \
        remove-dns-challenge
    do
        cat > "$fakebin/$helper" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF
        chmod +x "$fakebin/$helper"
    done
}

install_ssh_fake()
{
    cat > "$fakebin/ssh" <<'EOF'
#!/usr/bin/env bash

if [[ -n "${SSH_LOG:-}" ]]; then
    printf '%s\n' "$*" >> "$SSH_LOG"
fi

case "${2:-}" in
    start-renewal\ *)
        exit 0
        ;;

    cat\ /run/brd-038/*/request)
        cat "$SSH_REQUEST_FILE"
        exit 0
        ;;

    continue-renewal\ *)
        exit 0
        ;;

    wait-renewal\ *)
        exit 0
        ;;

    *)
        echo "Unexpected ssh command: ${2:-}" >&2
        exit 1
        ;;
esac
EOF

    chmod +x "$fakebin/ssh"
}

prepare_happy_path()
{
    ssh_log="$tmpdir/ssh-log"
    rm -f "$ssh_log"
    export SSH_LOG="$ssh_log"

    cat > "$ssh_request_file" <<'EOF'
identifier=nocster-73.arcanel.se
record=_acme-challenge.nocster-73.arcanel.se
validation=test-challenge-token
EOF

    install_ssh_fake
    install_noop_tls_helpers
}

run_renewal()
{
    BREDLAND_SECRETS_FILE="$secrets_file" \
        scripts/renew-bredland-tls.sh \
        >"$stdout_file" \
        2>"$stderr_file"
}

echo
echo "→ stops when Bredland renewal start fails"

if run_renewal; then
    echo "❌ Expected TLS renewal to fail"
    exit 1
fi

if ! grep -Fq "→ Starting Bredland TLS renewal" "$stdout_file"; then
    echo "❌ Expected Bredland renewal start step"
    exit 1
fi

if ! grep -Fq "❌ Starting Bredland TLS renewal" "$stderr_file"; then
    echo "❌ Expected Bredland renewal start failure"
    exit 1
fi

echo "✅ stops when Bredland renewal start fails"

echo
echo "→ reads request for the started renewal run"

prepare_happy_path

if ! run_renewal; then
    echo "❌ Expected TLS renewal start to succeed"
    cat "$stderr_file"
    exit 1
fi

start_command="$(sed -n '1p' "$ssh_log")"

run_id="${start_command##*start-renewal }"

if [[ -z "$run_id" || "$run_id" == "$start_command" ]]; then
    echo "❌ Expected start-renewal to receive a run-id"
    exit 1
fi

expected_request_command="bredland-test cat /run/brd-038/$run_id/request"

if ! grep -Fxq "$expected_request_command" "$ssh_log"; then
    echo "❌ Expected request to be read for run-id: $run_id"
    echo
    echo "Actual SSH calls:"
    sed 's/^/  /' "$ssh_log"
    exit 1
fi

echo "✅ reads request for the started renewal run"

echo
echo "→ rejects renewal request for unexpected hostname"

prepare_happy_path

cat > "$ssh_request_file" <<'EOF'
identifier=wrong.example
record=_acme-challenge.wrong.example
validation=test-challenge-token
EOF
install_ssh_fake

if run_renewal; then
    echo "❌ Expected unexpected hostname to be rejected"
    exit 1
fi

if ! grep -Fq "Unexpected certificate identifier: wrong.example" "$stderr_file"; then
    echo "❌ Expected clear unexpected-hostname error"
    echo
    echo "Actual stderr:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

echo "✅ rejects renewal request for unexpected hostname"

echo
echo "→ rejects unexpected challenge record"

prepare_happy_path

cat > "$ssh_request_file" <<'EOF'
identifier=nocster-73.arcanel.se
record=_acme-challenge.wrong.example
validation=test-challenge-token
EOF
install_ssh_fake

if run_renewal; then
    echo "❌ Expected unexpected challenge record to be rejected"
    exit 1
fi

if ! grep -Fq \
    "Unexpected challenge record: _acme-challenge.wrong.example" \
    "$stderr_file"
then
    echo "❌ Expected clear unexpected-challenge-record error"
    echo
    echo "Actual stderr:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

echo "✅ rejects unexpected challenge record"

echo
echo "→ rejects missing challenge validation"

prepare_happy_path

cat > "$ssh_request_file" <<'EOF'
identifier=nocster-73.arcanel.se
record=_acme-challenge.nocster-73.arcanel.se
EOF
install_ssh_fake

if run_renewal; then
    echo "❌ Expected missing challenge validation to be rejected"
    exit 1
fi

if ! grep -Fq \
    "Missing challenge validation" \
    "$stderr_file"
then
    echo "❌ Expected clear missing-validation error"
    echo
    echo "Actual stderr:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

echo "✅ rejects missing challenge validation"

echo
echo "→ publishes DNS challenge from renewal request"

dns_log="$tmpdir/dns-log"
rm -f "$dns_log"
export DNS_LOG="$dns_log"

prepare_happy_path

cat > "$fakebin/publish-dns-challenge" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" > "$DNS_LOG"
EOF

chmod +x "$fakebin/publish-dns-challenge"

if ! run_renewal; then
    echo "❌ Expected valid renewal request to reach DNS publication"
    cat "$stderr_file"
    exit 1
fi

if [[ ! -e "$dns_log" ]]; then
    echo "❌ Expected DNS challenge publication"
    exit 1
fi

expected_dns_call="_acme-challenge.nocster-73.arcanel.se test-challenge-token"

if ! grep -Fxq "$expected_dns_call" "$dns_log"; then
    echo "❌ Expected DNS challenge publication"
    echo
    echo "Expected:"
    echo "  $expected_dns_call"
    echo
    echo "Actual:"
    sed 's/^/  /' "$dns_log"
    exit 1
fi

echo "✅ publishes DNS challenge from renewal request"

echo
echo "→ waits for published DNS challenge"

dns_wait_log="$tmpdir/dns-wait-log"
rm -f "$dns_wait_log"

export DNS_WAIT_LOG="$dns_wait_log"

prepare_happy_path

cat > "$fakebin/wait-for-dns-challenge" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" > "$DNS_WAIT_LOG"
EOF

chmod +x "$fakebin/wait-for-dns-challenge"

if ! run_renewal; then
    echo "❌ Expected DNS challenge wait to succeed"
    cat "$stderr_file"
    exit 1
fi

if [[ ! -e "$dns_wait_log" ]]; then
    echo "❌ Expected DNS challenge visibility check"
    exit 1
fi

expected_dns_wait="_acme-challenge.nocster-73.arcanel.se test-challenge-token"

if ! grep -Fxq "$expected_dns_wait" "$dns_wait_log"; then
    echo "❌ Expected exact DNS challenge visibility check"
    echo
    echo "Expected:"
    echo "  $expected_dns_wait"
    echo
    echo "Actual:"
    sed 's/^/  /' "$dns_wait_log"
    exit 1
fi

echo "✅ waits for published DNS challenge"

echo
echo "→ continues the started renewal run after DNS is visible"

prepare_happy_path

if ! run_renewal; then
    echo "❌ Expected renewal to continue after DNS became visible"
    cat "$stderr_file"
    exit 1
fi

start_command="$(grep ' start-renewal ' "$ssh_log")"
run_id="${start_command##*start-renewal }"

expected_continue="bredland-test continue-renewal $run_id"

if ! grep -Fxq "$expected_continue" "$ssh_log"; then
    echo "❌ Expected started renewal run to be continued"
    echo
    echo "Expected:"
    echo "  $expected_continue"
    echo
    echo "Actual SSH calls:"
    sed 's/^/  /' "$ssh_log"
    exit 1
fi

echo "✅ continues the started renewal run after DNS is visible"

echo
echo "→ waits for the continued renewal run to finish"

prepare_happy_path

if ! run_renewal; then
    echo "❌ Expected renewal completion wait to succeed"
    cat "$stderr_file"
    exit 1
fi

start_command="$(grep ' start-renewal ' "$ssh_log")"
run_id="${start_command##*start-renewal }"

expected_wait="bredland-test wait-renewal $run_id"

if ! grep -Fxq "$expected_wait" "$ssh_log"; then
    echo "❌ Expected continued renewal run to be awaited"
    echo
    echo "Expected:"
    echo "  $expected_wait"
    echo
    echo "Actual SSH calls:"
    sed 's/^/  /' "$ssh_log"
    exit 1
fi

echo "✅ waits for the continued renewal run to finish"

echo
echo "→ validates issued certificate after renewal finishes"

validate_log="$tmpdir/validate-log"
rm -f "$validate_log"

export VALIDATE_LOG="$validate_log"

prepare_happy_path

cat > "$fakebin/validate-issued-certificate" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" > "$VALIDATE_LOG"
EOF

chmod +x "$fakebin/validate-issued-certificate"

if ! run_renewal; then
    echo "❌ Expected issued-certificate validation to succeed"
    cat "$stderr_file"
    exit 1
fi

if [[ ! -e "$validate_log" ]]; then
    echo "❌ Expected issued certificate validation"
    exit 1
fi

start_command="$(grep ' start-renewal ' "$SSH_LOG")"
run_id="${start_command##*start-renewal }"

if ! grep -Fxq "$run_id" "$validate_log"; then
    echo "❌ Expected issued certificate validation for run-id: $run_id"
    echo
    echo "Actual validation call:"
    sed 's/^/  /' "$validate_log"
    exit 1
fi

echo "✅ validates issued certificate after renewal finishes"

echo
echo "→ provisions issued certificate after validation"

provision_log="$tmpdir/provision-log"
rm -f "$provision_log"

export PROVISION_LOG="$provision_log"

prepare_happy_path

cat > "$fakebin/provision-issued-certificate" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" > "$PROVISION_LOG"
EOF

chmod +x "$fakebin/provision-issued-certificate"

if ! run_renewal; then
    echo "❌ Expected issued-certificate provisioning to succeed"
    cat "$stderr_file"
    exit 1
fi

if [[ ! -e "$provision_log" ]]; then
    echo "❌ Expected issued certificate provisioning"
    exit 1
fi

start_command="$(grep ' start-renewal ' "$SSH_LOG")"
run_id="${start_command##*start-renewal }"

if ! grep -Fxq "$run_id" "$provision_log"; then
    echo "❌ Expected issued certificate provisioning for run-id: $run_id"
    echo
    echo "Actual provisioning call:"
    sed 's/^/  /' "$provision_log"
    exit 1
fi

echo "✅ provisions issued certificate after validation"

echo
echo "→ activates issued certificate after provisioning"

activate_log="$tmpdir/activate-log"
rm -f "$activate_log"
export ACTIVATE_LOG="$activate_log"

prepare_happy_path

cat > "$fakebin/activate-issued-certificate" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" > "$ACTIVATE_LOG"
EOF

chmod +x "$fakebin/activate-issued-certificate"

if ! run_renewal; then
    echo "❌ Expected issued-certificate activation to succeed"
    cat "$stderr_file"
    exit 1
fi

if [[ ! -e "$activate_log" ]]; then
    echo "❌ Expected issued certificate activation"
    exit 1
fi

start_command="$(grep ' start-renewal ' "$SSH_LOG")"
run_id="${start_command##*start-renewal }"

if ! grep -Fxq "$run_id" "$activate_log"; then
    echo "❌ Expected issued certificate activation for run-id: $run_id"
    echo
    echo "Actual activation call:"
    sed 's/^/  /' "$activate_log"
    exit 1
fi

echo "✅ activates issued certificate after provisioning"

echo
echo "→ verifies served certificate after activation"

verify_log="$tmpdir/verify-log"
rm -f "$verify_log"
export VERIFY_LOG="$verify_log"

prepare_happy_path

cat > "$fakebin/verify-served-certificate" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" > "$VERIFY_LOG"
EOF

chmod +x "$fakebin/verify-served-certificate"

if ! run_renewal; then
    echo "❌ Expected served-certificate verification to succeed"
    cat "$stderr_file"
    exit 1
fi

if [[ ! -e "$verify_log" ]]; then
    echo "❌ Expected served certificate verification"
    exit 1
fi

start_command="$(grep ' start-renewal ' "$SSH_LOG")"
run_id="${start_command##*start-renewal }"

if ! grep -Fxq "$run_id" "$verify_log"; then
    echo "❌ Expected served certificate verification for run-id: $run_id"
    echo
    echo "Actual verification call:"
    sed 's/^/  /' "$verify_log"
    exit 1
fi

echo "✅ verifies served certificate after activation"

echo
echo "→ removes DNS challenge after served certificate verification"

remove_dns_log="$tmpdir/remove-dns-log"
rm -f "$remove_dns_log"
export REMOVE_DNS_LOG="$remove_dns_log"

prepare_happy_path

cat > "$fakebin/remove-dns-challenge" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" > "$REMOVE_DNS_LOG"
EOF

chmod +x "$fakebin/remove-dns-challenge"

if ! run_renewal; then
    echo "❌ Expected DNS challenge cleanup to succeed"
    cat "$stderr_file"
    exit 1
fi

if [[ ! -e "$remove_dns_log" ]]; then
    echo "❌ Expected DNS challenge cleanup"
    exit 1
fi

expected_remove="_acme-challenge.nocster-73.arcanel.se test-challenge-token"

if ! grep -Fxq "$expected_remove" "$remove_dns_log"; then
    echo "❌ Expected exact DNS challenge cleanup"
    echo
    echo "Expected:"
    echo "  $expected_remove"
    echo
    echo "Actual:"
    sed 's/^/  /' "$remove_dns_log"
    exit 1
fi

echo "✅ removes DNS challenge after served certificate verification"
