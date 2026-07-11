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

while IFS= read -r -d '' file; do
    echo -n "Checking ${file#./} ... "

    if output="$(php -l "$file" 2>&1)"; then
        echo "OK"
    else
        echo "FAILED"
        echo "$output"
        failed=1
    fi
done < <(
    find . \
        -name '*.php' \
        -not -path './tests/docker/*' \
        -print0 |
    sort -z
)

if (( failed )); then
    exit 1
fi