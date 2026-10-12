#!/usr/bin/env bash
set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

tls_dir="$tmpdir/tls"
run_dir="$tmpdir/brd-038-test-run"

fakebin="$tmpdir/bin"
chown_log="$tmpdir/chown-log"

mkdir -p "$fakebin"

cat > "$fakebin/chown" <<'EOF'
#!/usr/bin/env bash

printf '%s\n' "$*" >> "$CHOWN_LOG"
EOF

chmod +x "$fakebin/chown"

export PATH="$fakebin:$PATH"
export CHOWN_LOG="$chown_log"

reset_run_dir()
{
    rm -rf "$tls_dir" "$run_dir"
    mkdir -p "$tls_dir" "$run_dir"

    printf '%s\n' "old certificate" > "$tls_dir/fullchain.pem"
    printf '%s\n' "old private key" > "$tls_dir/privkey.pem"

    printf '%s\n' "new certificate" > "$run_dir/fullchain.pem"
    printf '%s\n' "new private key" > "$run_dir/privkey.pem"
}

echo
echo "→ backs up installed certificate pair"

reset_run_dir
cp "$tls_dir/fullchain.pem" "$tmpdir/original-fullchain.pem"
cp "$tls_dir/privkey.pem" "$tmpdir/original-privkey.pem"

BREDLAND_TLS_DIR="$tls_dir" \
    templates/bredland/provision-issued-certificate.sh.template \
        "$run_dir"

backup_dir="$tls_dir/backups/brd-038-test-run"

if ! cmp -s \
    "$tmpdir/original-fullchain.pem" \
    "$backup_dir/fullchain.pem"
then
    echo "❌ Expected installed certificate to be backed up"
    exit 1
fi

if ! cmp -s \
    "$tmpdir/original-privkey.pem" \
    "$backup_dir/privkey.pem"
then
    echo "❌ Expected installed private key to be backed up"
    exit 1
fi

echo "✅ backs up installed certificate pair"

echo
echo "→ installs issued certificate pair"

reset_run_dir
BREDLAND_TLS_DIR="$tls_dir" \
    templates/bredland/provision-issued-certificate.sh.template \
        "$run_dir"

if ! cmp -s \
    "$run_dir/fullchain.pem" \
    "$tls_dir/fullchain.pem"
then
    echo "❌ Expected issued certificate to be installed"
    exit 1
fi

if ! cmp -s \
    "$run_dir/privkey.pem" \
    "$tls_dir/privkey.pem"
then
    echo "❌ Expected issued private key to be installed"
    exit 1
fi

echo "✅ installs issued certificate pair"

echo
echo "→ installs certificate pair with mode 640"

reset_run_dir
BREDLAND_TLS_DIR="$tls_dir" \
    templates/bredland/provision-issued-certificate.sh.template \
        "$run_dir"

fullchain_mode="$(
    stat -c '%a' "$tls_dir/fullchain.pem"
)"

privkey_mode="$(
    stat -c '%a' "$tls_dir/privkey.pem"
)"

if [[ "$fullchain_mode" != "640" ]]; then
    echo "❌ Expected fullchain.pem mode 640"
    exit 1
fi

if [[ "$privkey_mode" != "640" ]]; then
    echo "❌ Expected privkey.pem mode 640"
    exit 1
fi

echo "✅ installs certificate pair with mode 640"

echo
echo "→ installs certificate pair with trusted TLS ownership"

reset_run_dir
: > "$chown_log"
test_owner="test-owner"
test_group="test-group"

BREDLAND_TLS_DIR="$tls_dir" \
BREDLAND_TLS_OWNER="$test_owner" \
BREDLAND_TLS_GROUP="$test_group" \
    templates/bredland/provision-issued-certificate.sh.template \
        "$run_dir"

expected_chown="$test_owner:$test_group $tls_dir/.fullchain.pem.new $tls_dir/.privkey.pem.new"

if ! grep -Fxq -- "$expected_chown" "$chown_log"; then
    echo "❌ Expected certificate pair ownership to be set"
    echo
    echo "Expected:"
    echo "  $expected_chown"
    echo
    echo "Actual:"
    sed 's/^/  /' "$chown_log"
    exit 1
fi

echo "✅ installs certificate pair with trusted TLS ownership"

echo
echo "→ creates backup directory with mode 750"

reset_run_dir

BREDLAND_TLS_DIR="$tls_dir" \
    templates/bredland/provision-issued-certificate.sh.template \
        "$run_dir"

backup_dir="$tls_dir/backups/brd-038-test-run"

backup_mode="$(
    stat -c '%a' "$backup_dir"
)"

if [[ "$backup_mode" != "750" ]]; then
    echo "❌ Expected backup directory mode 750"
    echo
    echo "Expected:"
    echo "  750"
    echo
    echo "Actual:"
    echo "  $backup_mode"
    exit 1
fi

echo "✅ creates backup directory with mode 750"

echo
echo "→ creates backup directory with trusted TLS ownership"

reset_run_dir
: > "$chown_log"

test_owner="test-owner"
test_group="test-group"

BREDLAND_TLS_DIR="$tls_dir" \
BREDLAND_TLS_OWNER="$test_owner" \
BREDLAND_TLS_GROUP="$test_group" \
    templates/bredland/provision-issued-certificate.sh.template \
        "$run_dir"

backup_dir="$tls_dir/backups/brd-038-test-run"

expected_chown="$test_owner:$test_group $backup_dir"

if ! grep -Fxq -- "$expected_chown" "$chown_log"; then
    echo "❌ Expected backup directory ownership to be set"
    echo
    echo "Expected:"
    echo "  $expected_chown"
    echo
    echo "Actual:"
    sed 's/^/  /' "$chown_log"
    exit 1
fi

echo "✅ creates backup directory with trusted TLS ownership"

echo
echo "→ leaves installed certificate pair untouched if staging fails"

reset_run_dir

cp "$tls_dir/fullchain.pem" "$tmpdir/original-fullchain.pem"
cp "$tls_dir/privkey.pem" "$tmpdir/original-privkey.pem"

fakebin="$tmpdir/fail-bin"
mkdir -p "$fakebin"

real_cp="$(command -v cp)"

cat > "$fakebin/cp" <<EOF
#!/usr/bin/env bash

if [[ "\$*" == *".privkey.pem.new"* ]]; then
    echo "simulated staging failure" >&2
    exit 1
fi

exec "$real_cp" "\$@"
EOF

chmod +x "$fakebin/cp"

if PATH="$fakebin:$PATH" \
    BREDLAND_TLS_DIR="$tls_dir" \
    templates/bredland/provision-issued-certificate.sh.template \
        "$run_dir" \
        >/dev/null \
        2>"$stderr_file"
then
    echo "❌ Expected provisioning to fail"
    exit 1
fi

if ! cmp -s \
    "$tmpdir/original-fullchain.pem" \
    "$tls_dir/fullchain.pem"
then
    echo "❌ Installed certificate changed after staging failure"
    exit 1
fi

if ! cmp -s \
    "$tmpdir/original-privkey.pem" \
    "$tls_dir/privkey.pem"
then
    echo "❌ Installed private key changed after staging failure"
    exit 1
fi

echo "✅ leaves installed certificate pair untouched if staging fails"

echo
echo "→ restores installed certificate pair if replacement fails"

reset_run_dir

cp "$tls_dir/fullchain.pem" "$tmpdir/original-fullchain.pem"
cp "$tls_dir/privkey.pem" "$tmpdir/original-privkey.pem"

fail_bin="$tmpdir/fail-mv-bin"
mkdir -p "$fail_bin"

real_mv="$(command -v mv)"

cat > "$fail_bin/mv" <<EOF
#!/usr/bin/env bash

if [[ "\$1" == "$tls_dir/.privkey.pem.new" ]]; then
    echo "simulated replacement failure" >&2
    exit 1
fi

exec "$real_mv" "\$@"
EOF

chmod +x "$fail_bin/mv"

if PATH="$fail_bin:$PATH" \
    BREDLAND_TLS_DIR="$tls_dir" \
    templates/bredland/provision-issued-certificate.sh.template \
        "$run_dir" \
        >/dev/null \
        2>"$tmpdir/stderr"
then
    echo "❌ Expected provisioning to fail"
    exit 1
fi

if ! cmp -s \
    "$tmpdir/original-fullchain.pem" \
    "$tls_dir/fullchain.pem"
then
    echo "❌ Installed certificate was not restored after replacement failure"
    exit 1
fi

if ! cmp -s \
    "$tmpdir/original-privkey.pem" \
    "$tls_dir/privkey.pem"
then
    echo "❌ Installed private key was not restored after replacement failure"
    exit 1
fi

backup_dir="$tls_dir/backups/brd-038-test-run"

if ! cmp -s \
    "$tmpdir/original-fullchain.pem" \
    "$backup_dir/fullchain.pem"
then
    echo "❌ Certificate backup was not preserved after replacement failure"
    exit 1
fi

if ! cmp -s \
    "$tmpdir/original-privkey.pem" \
    "$backup_dir/privkey.pem"
then
    echo "❌ Private key backup was not preserved after replacement failure"
    exit 1
fi

echo "✅ restores installed certificate pair if replacement fails"

echo
echo "→ restores installed certificate pair atomically"

reset_run_dir

cp "$tls_dir/fullchain.pem" "$tmpdir/original-fullchain.pem"
cp "$tls_dir/privkey.pem" "$tmpdir/original-privkey.pem"

backup_dir="$tls_dir/backups/brd-038-test-run"

fail_bin="$tmpdir/atomic-rollback-bin"
cp_log="$tmpdir/atomic-rollback-cp-log"

mkdir -p "$fail_bin"

real_cp="$(command -v cp)"
real_mv="$(command -v mv)"

cat > "$fail_bin/cp" <<EOF
#!/usr/bin/env bash

printf '%s\n' "\$*" >> "$cp_log"

exec "$real_cp" "\$@"
EOF

cat > "$fail_bin/mv" <<EOF
#!/usr/bin/env bash

if [[ "\$1" == "$tls_dir/.privkey.pem.new" ]]; then
    echo "simulated replacement failure" >&2
    exit 1
fi

exec "$real_mv" "\$@"
EOF

chmod +x \
    "$fail_bin/cp" \
    "$fail_bin/mv"

: > "$cp_log"

if PATH="$fail_bin:$PATH" \
    BREDLAND_TLS_DIR="$tls_dir" \
    templates/bredland/provision-issued-certificate.sh.template \
        "$run_dir" \
        >/dev/null \
        2>"$tmpdir/atomic-rollback-stderr"
then
    echo "❌ Expected provisioning to fail"
    exit 1
fi

expected_fullchain_rollback="$backup_dir/fullchain.pem $tls_dir/.fullchain.pem.rollback"
expected_privkey_rollback="$backup_dir/privkey.pem $tls_dir/.privkey.pem.rollback"

if ! grep -Fxq -- "$expected_fullchain_rollback" "$cp_log"; then
    echo "❌ Expected certificate rollback to use staging file"
    echo
    echo "Expected:"
    echo "  $expected_fullchain_rollback"
    echo
    echo "Actual:"
    sed 's/^/  /' "$cp_log"
    exit 1
fi

if ! grep -Fxq -- "$expected_privkey_rollback" "$cp_log"; then
    echo "❌ Expected private-key rollback to use staging file"
    echo
    echo "Expected:"
    echo "  $expected_privkey_rollback"
    echo
    echo "Actual:"
    sed 's/^/  /' "$cp_log"
    exit 1
fi

if ! cmp -s \
    "$tmpdir/original-fullchain.pem" \
    "$tls_dir/fullchain.pem"
then
    echo "❌ Installed certificate was not restored after replacement failure"
    exit 1
fi

if ! cmp -s \
    "$tmpdir/original-privkey.pem" \
    "$tls_dir/privkey.pem"
then
    echo "❌ Installed private key was not restored after replacement failure"
    exit 1
fi

echo "✅ restores installed certificate pair atomically"

echo
echo "→ preserves backup and reports rollback failure"

reset_run_dir

cp "$tls_dir/fullchain.pem" "$tmpdir/original-fullchain.pem"
cp "$tls_dir/privkey.pem" "$tmpdir/original-privkey.pem"

backup_dir="$tls_dir/backups/brd-038-test-run"

fail_bin="$tmpdir/double-failure-bin"
stderr_file="$tmpdir/double-failure-stderr"

mkdir -p "$fail_bin"

real_mv="$(command -v mv)"

cat > "$fail_bin/mv" <<EOF
#!/usr/bin/env bash

if [[ "\$1" == "$tls_dir/.privkey.pem.new" ]] ||
   [[ "\$1" == "$tls_dir/.privkey.pem.rollback" ]]
then
    echo "simulated mv failure" >&2
    exit 1
fi

exec "$real_mv" "\$@"
EOF

chmod +x "$fail_bin/mv"

if PATH="$fail_bin:$PATH" \
    BREDLAND_TLS_DIR="$tls_dir" \
    templates/bredland/provision-issued-certificate.sh.template \
        "$run_dir" \
        >/dev/null \
        2>"$stderr_file"
then
    echo "❌ Expected provisioning and rollback to fail"
    exit 1
fi

expected_error="Rollback failed; backup preserved at $backup_dir"

if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
    echo "❌ Expected explicit rollback-failure error"
    echo
    echo "Expected:"
    echo "  $expected_error"
    echo
    echo "Actual:"
    sed 's/^/  /' "$stderr_file"
    exit 1
fi

if ! cmp -s \
    "$tmpdir/original-fullchain.pem" \
    "$backup_dir/fullchain.pem"
then
    echo "❌ Certificate backup was not preserved after rollback failure"
    exit 1
fi

if ! cmp -s \
    "$tmpdir/original-privkey.pem" \
    "$backup_dir/privkey.pem"
then
    echo "❌ Private key backup was not preserved after rollback failure"
    exit 1
fi

echo "✅ preserves backup and reports rollback failure"

echo
echo "→ removes staging files after successful provisioning"

reset_run_dir

BREDLAND_TLS_DIR="$tls_dir" \
    templates/bredland/provision-issued-certificate.sh.template \
        "$run_dir"

for path in \
    "$tls_dir/.fullchain.pem.new" \
    "$tls_dir/.privkey.pem.new" \
    "$tls_dir/.fullchain.pem.rollback" \
    "$tls_dir/.privkey.pem.rollback"
do
    if [[ -e "$path" ]]; then
        echo "❌ Expected staging file to be removed: $path"
        exit 1
    fi
done

echo "✅ removes staging files after successful provisioning"

echo
echo "→ removes staging files after failed replacement and successful rollback"

reset_run_dir

fail_bin="$tmpdir/cleanup-after-failure-bin"
stderr_file="$tmpdir/cleanup-after-failure-stderr"

mkdir -p "$fail_bin"

real_mv="$(command -v mv)"

cat > "$fail_bin/mv" <<EOF
#!/usr/bin/env bash

if [[ "\$1" == "$tls_dir/.privkey.pem.new" ]]; then
    echo "simulated replacement failure" >&2
    exit 1
fi

exec "$real_mv" "\$@"
EOF

chmod +x "$fail_bin/mv"

if PATH="$fail_bin:$PATH" \
    BREDLAND_TLS_DIR="$tls_dir" \
    templates/bredland/provision-issued-certificate.sh.template \
        "$run_dir" \
        >/dev/null \
        2>"$stderr_file"
then
    echo "❌ Expected provisioning to fail"
    exit 1
fi

for path in \
    "$tls_dir/.fullchain.pem.new" \
    "$tls_dir/.privkey.pem.new" \
    "$tls_dir/.fullchain.pem.rollback" \
    "$tls_dir/.privkey.pem.rollback"
do
    if [[ -e "$path" ]]; then
        echo "❌ Expected staging file to be removed after rollback: $path"
        exit 1
    fi
done

echo "✅ removes staging files after failed replacement and successful rollback"

echo
echo "→ preserves rollback staging files when rollback fails"

reset_run_dir

backup_dir="$tls_dir/backups/brd-038-test-run"
fail_bin="$tmpdir/preserve-rollback-bin"
stderr_file="$tmpdir/preserve-rollback-stderr"

mkdir -p "$fail_bin"

real_mv="$(command -v mv)"

cat > "$fail_bin/mv" <<EOF
#!/usr/bin/env bash

if [[ "\$1" == "$tls_dir/.privkey.pem.new" ]] ||
   [[ "\$1" == "$tls_dir/.privkey.pem.rollback" ]]
then
    echo "simulated mv failure" >&2
    exit 1
fi

exec "$real_mv" "\$@"
EOF

chmod +x "$fail_bin/mv"

if PATH="$fail_bin:$PATH" \
    BREDLAND_TLS_DIR="$tls_dir" \
    templates/bredland/provision-issued-certificate.sh.template \
        "$run_dir" \
        >/dev/null \
        2>"$stderr_file"
then
    echo "❌ Expected provisioning and rollback to fail"
    exit 1
fi

rollback_privkey="$tls_dir/.privkey.pem.rollback"

if [[ ! -f "$rollback_privkey" ]]; then
    echo "❌ Expected failed rollback staging file to be preserved"
    exit 1
fi

if ! cmp -s \
    "$backup_dir/privkey.pem" \
    "$rollback_privkey"
then
    echo "❌ Expected preserved rollback private key to match backup"
    exit 1
fi

echo "✅ preserves rollback staging files when rollback fails"
