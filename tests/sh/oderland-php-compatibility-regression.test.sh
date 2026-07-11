#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$repo_root"

tmp="tests/tmp-invalid.php"

trap 'rm -f "$tmp"' EXIT

cat >"$tmp" <<'PHP'
<?php
function collect(...$items)
{
}
PHP

if tests/sh/oderland-php-compatibility.test.sh; then
    echo "Expected compatibility test to fail"
    exit 1
fi

echo "✅ Compatibility test reports syntax failures"