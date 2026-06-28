#!/usr/bin/env bash

set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

data_dir="$tmpdir/data"
mkdir -p "$data_dir"

cat > "$tmpdir/secrets.env" <<EOF
NOC_DATA_DIR=$data_dir
EOF

cat > "$data_dir/mikrotik-2026-06-24.jsonl" <<EOF
{"host":"mikrotik","ts":"2026-06-24T12:00:00Z"}
EOF

cat > "$data_dir/mikrotik-2026-06-25.jsonl" <<EOF
{"host":"mikrotik","ts":"2026-06-25T12:00:00Z"}
EOF

cat > "$data_dir/bredland-2026-06-25.jsonl" <<EOF
{"host":"bredland","ts":"2026-06-25T12:00:00Z"}
EOF

cat > "$data_dir/mikrotik-2026-06-26.jsonl" <<EOF
{"host":"mikrotik","ts":"2026-06-26T12:00:00Z"}
EOF

rendered="$tmpdir/compress-noc-logs.sh"

BREDLAND_SECRETS_FILE="$tmpdir/secrets.env" \
    scripts/render-template.sh \
    templates/oderland/compress-noc-logs.sh.template \
    "$rendered"

chmod +x "$rendered"

NOC_ROTATION_DATE=2026-06-26 "$rendered"

[[ ! -f "$data_dir/mikrotik-2026-06-24.jsonl" ]]
[[ -f "$data_dir/mikrotik-2026-06-24.jsonl.gz" ]]

[[ ! -f "$data_dir/mikrotik-2026-06-25.jsonl" ]]
[[ -f "$data_dir/mikrotik-2026-06-25.jsonl.gz" ]]

[[ ! -f "$data_dir/bredland-2026-06-25.jsonl" ]]
[[ -f "$data_dir/bredland-2026-06-25.jsonl.gz" ]]

[[ -f "$data_dir/mikrotik-2026-06-26.jsonl" ]]
[[ ! -f "$data_dir/mikrotik-2026-06-26.jsonl.gz" ]]

gzip -t "$data_dir/mikrotik-2026-06-24.jsonl.gz"
gzip -t "$data_dir/mikrotik-2026-06-25.jsonl.gz"
gzip -t "$data_dir/bredland-2026-06-25.jsonl.gz"

diff -u <(printf '%s\n' '{"host":"mikrotik","ts":"2026-06-24T12:00:00Z"}') <(gzip -dc "$data_dir/mikrotik-2026-06-24.jsonl.gz")
diff -u <(printf '%s\n' '{"host":"mikrotik","ts":"2026-06-25T12:00:00Z"}') <(gzip -dc "$data_dir/mikrotik-2026-06-25.jsonl.gz")
diff -u <(printf '%s\n' '{"host":"bredland","ts":"2026-06-25T12:00:00Z"}') <(gzip -dc "$data_dir/bredland-2026-06-25.jsonl.gz")

before="$(find "$data_dir" -type f | sort | xargs ls -l)"

NOC_ROTATION_DATE=2026-06-26 "$rendered"

after="$(find "$data_dir" -type f | sort | xargs ls -l)"

if [[ "$before" != "$after" ]]; then
    echo "Second run was not idempotent" >&2
    exit 1
fi

echo "compress-noc-logs tests passed"
