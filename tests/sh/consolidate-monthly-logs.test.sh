#!/usr/bin/env bash

set -euo pipefail

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

data_dir="$tmpdir/data"
mkdir -p "$data_dir"

cat > "$tmpdir/secrets.env" <<EOF
NOC_DATA_DIR=$data_dir
EOF

printf '%s\n%s\n' \
  '{"host":"mikrotik","ts":"2026-06-01T12:00:00Z"}' \
  '{"host":"mikrotik","ts":"2026-06-01T12:05:00Z"}' \
  | gzip -c > "$data_dir/mikrotik-2026-06-01.jsonl.gz"

printf '%s\n%s\n' \
  '{"host":"mikrotik","ts":"2026-06-02T12:00:00Z"}' \
  '{"host":"mikrotik","ts":"2026-06-02T12:05:00Z"}' \
  | gzip -c > "$data_dir/mikrotik-2026-06-02.jsonl.gz"

printf '%s\n%s\n' \
  '{"host":"bredland","ts":"2026-06-01T12:00:00Z"}' \
  '{"host":"bredland","ts":"2026-06-01T12:05:00Z"}' \
  | gzip -c > "$data_dir/bredland-2026-06-01.jsonl.gz"

printf '%s\n%s\n' \
  '{"host":"bredland","ts":"2026-06-02T12:00:00Z"}' \
  '{"host":"bredland","ts":"2026-06-02T12:05:00Z"}' \
  | gzip -c > "$data_dir/bredland-2026-06-02.jsonl.gz"

# Should not be included: current month
printf '%s\n' \
  '{"host":"mikrotik","ts":"2026-07-01T12:00:00Z"}' \
  | gzip -c > "$data_dir/mikrotik-2026-07-01.jsonl.gz"

rendered="$tmpdir/consolidate-monthly-test.sh"

BREDLAND_SECRETS_FILE="$tmpdir/secrets.env" \
    scripts/render-template.sh \
    templates/noc/consolidate-monthly-logs.sh.template \
    "$rendered"

chmod +x "$rendered"

NOC_CONSOLIDATION_DATE=2026-07-01 "$rendered"

[[ -f "$data_dir/mikrotik-2026-06.jsonl.gz" ]]
[[ -f "$data_dir/bredland-2026-06.jsonl.gz" ]]

[[ ! -f "$data_dir/mikrotik-2026-06-01.jsonl.gz" ]]
[[ ! -f "$data_dir/mikrotik-2026-06-02.jsonl.gz" ]]
[[ ! -f "$data_dir/bredland-2026-06-01.jsonl.gz" ]]
[[ ! -f "$data_dir/bredland-2026-06-02.jsonl.gz" ]]

[[ -f "$data_dir/mikrotik-2026-07-01.jsonl.gz" ]]

gzip -t "$data_dir/mikrotik-2026-06.jsonl.gz"
gzip -t "$data_dir/bredland-2026-06.jsonl.gz"

diff -u <(cat <<'EOF'
{"host":"mikrotik","ts":"2026-06-01T12:00:00Z"}
{"host":"mikrotik","ts":"2026-06-01T12:05:00Z"}
{"host":"mikrotik","ts":"2026-06-02T12:00:00Z"}
{"host":"mikrotik","ts":"2026-06-02T12:05:00Z"}
EOF
) <(gzip -dc "$data_dir/mikrotik-2026-06.jsonl.gz")

diff -u <(cat <<'EOF'
{"host":"bredland","ts":"2026-06-01T12:00:00Z"}
{"host":"bredland","ts":"2026-06-01T12:05:00Z"}
{"host":"bredland","ts":"2026-06-02T12:00:00Z"}
{"host":"bredland","ts":"2026-06-02T12:05:00Z"}
EOF
) <(gzip -dc "$data_dir/bredland-2026-06.jsonl.gz")

before="$(find "$data_dir" -type f | sort | xargs ls -l)"

NOC_CONSOLIDATION_DATE=2026-07-01 "$rendered"

after="$(find "$data_dir" -type f | sort | xargs ls -l)"

if [[ "$before" != "$after" ]]; then
    echo "Second run was not idempotent" >&2
    exit 1
fi