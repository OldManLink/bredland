#!/usr/bin/env bash

set -euo pipefail

# Verifies that all PHP files in the repository are syntactically
# compatible with the target PHP runtime.
#
# This test is expected to run inside the Linux/PHP test container.
# It checks syntax only. It does not detect runtime API differences
# such as functions introduced after PHP 5.5 (e.g. hash_equals()).
# Version-dependent APIs should be accessed via compatibility.php.

repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$repo_root"

failed=0
files=()

if (( $# > 0 )); then
    files=("$@")
else
    while IFS= read -r -d '' file; do
        files+=("$file")
    done < <(
        find . \
            -path './build' -prune -o \
            -name '*.php' \
            -not -path './tests/docker/*' \
            -print0 |
        sort -z
    )
fi

for file in "${files[@]}"; do
    echo -n "Checking ${file#./} ... "

    if output="$(php -l "$file" 2>&1)"; then
        echo "OK"
    else
        echo "FAILED"
        echo "$output"
        failed=1
    fi
done

if (( failed )); then
    exit 1
fi