#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root"

backup_dir="$HOME/Oderland/data/backup"
rehearsal_dir="$HOME/Oderland/data/rehearsal"
remote_data_dir="arcanel:/home/arcanel/public_html/noc/data/"

consolidation_date="${1:-$(date +%Y-%m-01)}"
target_month="$(date -j -v-1d -f "%Y-%m-%d" "$consolidation_date" "+%Y-%m")"

echo "==> Updating local backup from Oderland"
mkdir -p "$backup_dir"

env -u LC_CTYPE -u LC_ALL -u LANG \
rsync -av \
  "$remote_data_dir" \
  "$backup_dir/"

echo
echo "==> Preparing rehearsal data"
rm -rf "$rehearsal_dir"
cp -a "$backup_dir" "$rehearsal_dir"

echo
echo "==> Rendering consolidation script for Docker rehearsal"
cat > /tmp/rehearsal-secrets.env <<EOF
NOC_DATA_DIR=/data
EOF

BREDLAND_SECRETS_FILE=/tmp/rehearsal-secrets.env \
  scripts/render-template.sh \
  templates/noc/consolidate-monthly-logs.sh.template \
  /tmp/consolidate-monthly-rehearsal.sh

chmod +x /tmp/consolidate-monthly-rehearsal.sh

echo
echo "==> Rehearsing monthly consolidation"
echo "    Consolidation date : $consolidation_date"
echo "    Target month       : $target_month"

docker run --rm \
  --platform linux/amd64 \
  -e NOC_CONSOLIDATION_DATE="$consolidation_date" \
  -v "$rehearsal_dir:/data" \
  -v "/tmp/consolidate-monthly-rehearsal.sh:/consolidate-monthly.sh" \
  -w /data \
  php:5.6-cli \
  bash /consolidate-monthly.sh

echo
echo "==> Rehearsal directory"
ls -lh "$rehearsal_dir"

echo
echo "==> Validating gzip files"
gzip -t "$rehearsal_dir"/*.jsonl.gz
echo "✅ All gzip files valid"

mikrotik_archive="$rehearsal_dir/mikrotik-$target_month.jsonl.gz"

if [[ -f "$mikrotik_archive" ]]; then
    echo
    echo "==> Inspecting $mikrotik_archive"
    zless "$mikrotik_archive"
else
    echo
    echo "No MikroTik archive found for $target_month: $mikrotik_archive" >&2
fi
