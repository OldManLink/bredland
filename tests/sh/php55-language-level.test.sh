#!/usr/bin/env bash
set -euo pipefail

tmp="$(mktemp --suffix=.php)"
trap 'rm -f "$tmp"' EXIT

cat >"$tmp" <<'PHP'
<?php

function collect(...$items)
{
}
PHP

if php -l "$tmp" >/dev/null 2>&1; then
    echo "Expected PHP 5.5 to reject PHP 5.6 variadic syntax" >&2
    exit 1
fi

echo "✅ PHP 5.6 syntax rejected"
