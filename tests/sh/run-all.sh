#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$repo_root"

shopt -s nullglob

for test_script in tests/sh/*.test.sh; do
    echo "==> $test_script"
    "$test_script"
done