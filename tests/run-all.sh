#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root"

echo "==> Shell tests"
set +e
tests/sh/run-all.sh
sh_rc=$?
set -e

echo
set +e
echo "==> PHP tests"
tests/php/run-all.sh
php_rc=$?
set -e

echo
echo "==> Overall summary"

if [[ $sh_rc -eq 0 ]]; then
    echo "✅ Shell tests"
else
    echo "❌ Shell tests"
fi

if [[ $php_rc -eq 0 ]]; then
    echo "✅ PHP tests"
else
    echo "❌ PHP tests"
fi

if [[ $sh_rc -eq 0 && $php_rc -eq 0 ]]; then
    echo
    echo "✅ All tests passed"
    exit 0
else
    echo
    echo "❌ Test failures"
    exit 1
fi