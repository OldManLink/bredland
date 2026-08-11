#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd "$(dirname "$0")" && pwd)"
repo_root="$(cd "$script_dir/.." && pwd)"

candidate_index="$repo_root/build/compare-dashboard/local.normalised.html"
production_index="$repo_root/tests/fixtures/production/index.html"

candidate_style="$repo_root/templates/noc/static/style.css"
production_style="$repo_root/tests/fixtures/production/static/style.css"

candidate_script="$repo_root/templates/noc/static/dashboard.js"
production_script="$repo_root/tests/fixtures/production/static/dashboard.js"

for file in \
    "$candidate_index" \
    "$candidate_style" \
    "$candidate_script"
do
    if [[ ! -s "$file" ]]; then
        echo "❌ Missing dashboard fixture source:"
        echo "   $file"
        exit 1
    fi
done

cp "$candidate_index" "$production_index"
cp "$candidate_style" "$production_style"
cp "$candidate_script" "$production_script"

echo "✅ Dashboard fixtures promoted."