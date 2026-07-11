#!/usr/bin/env bash

set -euo pipefail

# Verifies that the PHP files deployed to Oderland are syntactically
# compatible with the target PHP runtime.
#
# This test is expected to run inside the Linux/PHP test container.
# It checks syntax only. It does not detect runtime API differences
# such as functions introduced after PHP 5.5 (e.g. hash_equals()).
# Version-dependent APIs should be accessed via compatibility.php.

repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$repo_root"

failed=0

find . \
    -name '*.php' \
    -not -path './tests/docker/*' \
    -print0 |
sort -z |
while IFS= read -r -d '' file; do
    echo -n "Checking ${file#./} ... "

    set +e
    output="$(php -l "$file" 2>&1)"
    rc=$?
    set -e

    if [[ $rc -ne 0 ]]; then
        echo "FAILED"
        echo "$output"
        failed=1
    else
        echo "OK"
    fi
done

if (( failed )); then
    exit 1
fi