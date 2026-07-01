#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$repo_root"

shopt -s nullglob
test_scripts=(tests/php/*.test.php)

if (( ${#test_scripts[@]} == 0 )); then
    echo "==> no PHP tests yet"
    exit 0
fi

for test_script in "${test_scripts[@]}"; do
    echo "==> $test_script"
    php "$test_script"
done