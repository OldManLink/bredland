#!/usr/bin/env bash

set -euo pipefail

# Verifies that the PHP files deployed to Oderland are syntactically
# compatible with the target PHP runtime (currently PHP 5.6 used as the
# closest available Docker image to Oderland's PHP 5.5).
#
# This test checks syntax only. It does not detect runtime API differences
# such as functions introduced after PHP 5.5 (e.g. hash_equals()).
# Version-dependent APIs should be accessed via compatibility.php.

repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$repo_root"

# shellcheck source=tests/sh/lib/testlib.sh
source "$repo_root/tests/sh/lib/testlib.sh"

if ! docker info >/dev/null 2>&1; then
    skip "Docker is not running"
fi

php_image="php:5.6-cli"

failed=0

for file in \
    templates/noc/telemetry.endpoint.template.php \
    templates/noc/telemetry.config.template.php \
    templates/noc/lib/*.php
do
    echo -n "Checking $(basename "$file") ... "

    set +e
    output="$(
        docker run --rm \
            --platform linux/amd64 \
            -v "$PWD:/app" \
            -w /app \
            "$php_image" \
            php -l "$file" 2>&1
    )"
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
