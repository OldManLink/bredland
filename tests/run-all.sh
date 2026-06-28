#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")/.."

for test_script in tests/*.test.sh; do
    echo "==> $test_script"
    "$test_script"
done
