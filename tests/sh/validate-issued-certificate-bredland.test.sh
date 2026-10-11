#!/usr/bin/env bash
set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

run_id="brd-038-test-run"
run_dir="$tmpdir/$run_id"
stdout_file="$tmpdir/stdout"
stderr_file="$tmpdir/stderr"

reset_run_dir()
{
    rm -rf "$run_dir"
    mkdir -p "$run_dir"
}

assert_stderr()
{
    local expected_error="${1?Missing expected error}"
    local description="${2:?Missing description}"

    if [[ -z "$expected_error" ]]; then
        if [[ -s "$stderr_file" ]]; then
            echo "❌ $description"
            echo
            echo "Expected:"
            echo "  <empty>"
            echo
            echo "Actual:"
            sed 's/^/  /' "$stderr_file"
            exit 1
        fi

        return
    fi

    if ! grep -Fxq -- "$expected_error" "$stderr_file"; then
        echo "❌ $description"
        echo
        echo "Expected:"
        echo "  $expected_error"
        echo
        echo "Actual:"
        sed 's/^/  /' "$stderr_file"
        exit 1
    fi
}

echo
echo "→ rejects missing issued certificate"

reset_run_dir
if templates/bredland/validate-issued-certificate.sh.template \
    "$run_dir" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected missing issued certificate to be rejected"
    exit 1
fi

assert_stderr \
    "Missing certificate file: fullchain.pem" \
    "Expected explicit missing-certificate error"

echo "✅ rejects missing issued certificate"

echo
echo "→ rejects missing private key"

reset_run_dir
touch "$run_dir/fullchain.pem"

if templates/bredland/validate-issued-certificate.sh.template \
    "$run_dir" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected missing private key to be rejected"
    exit 1
fi

assert_stderr \
    "Missing certificate file: privkey.pem" \
    "Expected explicit missing-private-key error"

echo "✅ rejects missing private key"

echo
echo "→ rejects malformed certificate"

reset_run_dir
cat > "$run_dir/fullchain.pem" <<'EOF'
this is not a certificate
EOF

touch "$run_dir/privkey.pem"

if templates/bredland/validate-issued-certificate.sh.template \
    "$run_dir" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected malformed certificate to be rejected"
    exit 1
fi

assert_stderr \
    "Invalid certificate: fullchain.pem" \
    "Expected explicit malformed-certificate error"

echo "✅ rejects malformed certificate"

echo
echo "→ rejects malformed private key"

reset_run_dir
cp tests/fixtures/tls/fullchain.pem "$run_dir/fullchain.pem"
cat > "$run_dir/privkey.pem" <<'EOF'
this is not a private key
EOF

if templates/bredland/validate-issued-certificate.sh.template \
    "$run_dir" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected malformed private key to be rejected"
    exit 1
fi

assert_stderr \
    "Invalid private key: privkey.pem" \
    "Expected explicit malformed-private-key error"

echo "✅ rejects malformed private key"

echo
echo "→ rejects certificate and private key that do not match"

reset_run_dir
cp tests/fixtures/tls/fullchain.pem "$run_dir/fullchain.pem"

openssl genpkey \
    -algorithm RSA \
    -pkeyopt rsa_keygen_bits:2048 \
    -out "$run_dir/privkey.pem" \
    >/dev/null 2>&1

if templates/bredland/validate-issued-certificate.sh.template \
    "$run_dir" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected unmatched private key to be rejected"
    exit 1
fi

assert_stderr \
    "Certificate does not match private key" \
    "Expected explicit unmatched-private-key error"

echo "✅ rejects certificate and private key that do not match"

echo
echo "→ rejects certificate for the wrong hostname"

openssl req \
    -x509 \
    -newkey rsa:2048 \
    -nodes \
    -sha256 \
    -days 1 \
    -subj '/CN=wrong.example' \
    -addext 'subjectAltName=DNS:wrong.example' \
    -keyout "$run_dir/privkey.pem" \
    -out "$run_dir/fullchain.pem" \
    >/dev/null 2>&1

if templates/bredland/validate-issued-certificate.sh.template \
    "$run_dir" \
    >"$stdout_file" \
    2>"$stderr_file"
then
    echo "❌ Expected certificate for the wrong host to be rejected"
    exit 1
fi

assert_stderr \
    "Certificate does not cover nocster-73.arcanel.se" \
    "Expected explicit wrong host error"

echo "✅ rejects certificate for the wrong hostname"

echo
echo "→ rejects not yet valid certificate"

reset_run_dir
cp tests/fixtures/tls/fullchain.pem "$run_dir/fullchain.pem"
cp tests/fixtures/tls/privkey.pem "$run_dir/privkey.pem"

if TLS_VALIDATION_NOW=1577836800 \
    templates/bredland/validate-issued-certificate.sh.template \
        "$run_dir" \
        >"$stdout_file" \
        2>"$stderr_file"
then
    echo "❌ Expected not yet valid certificate to be rejected"
    exit 1
fi

assert_stderr \
    "Certificate is not yet valid" \
    "Expected explicit not-yet-valid-certificate error"

echo "✅ rejects not yet valid certificate"

echo
echo "→ rejects expired certificate"

reset_run_dir
cp tests/fixtures/tls/fullchain.pem "$run_dir/fullchain.pem"
cp tests/fixtures/tls/privkey.pem "$run_dir/privkey.pem"

if TLS_VALIDATION_NOW=2208988800 \
    templates/bredland/validate-issued-certificate.sh.template \
        "$run_dir" \
        >"$stdout_file" \
        2>"$stderr_file"
then
    echo "❌ Expected expired certificate to be rejected"
    exit 1
fi

assert_stderr \
    "Certificate has expired" \
    "Expected explicit expired-certificate error"

echo "✅ rejects expired certificate"

echo
echo "→ accepts valid certificate"

reset_run_dir
cp tests/fixtures/tls/fullchain.pem "$run_dir/fullchain.pem"
cp tests/fixtures/tls/privkey.pem "$run_dir/privkey.pem"

if ! TLS_VALIDATION_NOW=1798761600 \
    templates/bredland/validate-issued-certificate.sh.template \
        "$run_dir" \
        >"$stdout_file" \
        2>"$stderr_file"
then
    echo "❌ Expected valid certificate to be accepted"
    exit 1
fi

assert_stderr \
    "" \
    "Expected no errors"

echo "✅ accepts valid certificate"

echo
echo "→ rejects incomplete certificate chain"

reset_run_dir
cp tests/fixtures/tls/leaf-cert.pem "$run_dir/fullchain.pem"
cp tests/fixtures/tls/privkey.pem "$run_dir/privkey.pem"

if TLS_VALIDATION_NOW=1798761600 \
    templates/bredland/validate-issued-certificate.sh.template \
        "$run_dir" \
        >"$stdout_file" \
        2>"$stderr_file"
then
    echo "❌ Expected incomplete certificate chain to be rejected"
    exit 1
fi

assert_stderr \
    "Certificate chain is incomplete" \
    "Expected explicit incomplete-certificate-chain error"

echo "✅ rejects incomplete certificate chain"

echo
echo "→ rejects incoherent certificate chain"

reset_run_dir

cp tests/fixtures/tls/leaf-cert.pem "$run_dir/fullchain.pem"
cp tests/fixtures/tls/privkey.pem "$run_dir/privkey.pem"

openssl req \
    -x509 \
    -newkey rsa:2048 \
    -nodes \
    -sha256 \
    -days 1 \
    -subj '/CN=Wrong Test CA' \
    -keyout "$run_dir/wrong-ca-key.pem" \
    -out "$run_dir/wrong-ca-cert.pem" \
    >/dev/null 2>&1

cat "$run_dir/wrong-ca-cert.pem" >> "$run_dir/fullchain.pem"

if TLS_VALIDATION_NOW=1798761600 \
    templates/bredland/validate-issued-certificate.sh.template \
        "$run_dir" \
        >"$stdout_file" \
        2>"$stderr_file"
then
    echo "❌ Expected invalid certificate chain to be rejected"
    exit 1
fi

assert_stderr \
    "Certificate chain is invalid" \
    "Expected explicit invalid-certificate-chain error"


echo "✅ rejects incoherent certificate chain"


