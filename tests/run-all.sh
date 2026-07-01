#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root"

echo "==> Shell tests"
tests/sh/run-all.sh

echo
echo "==> PHP tests"
tests/php/run-all.sh