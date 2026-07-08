#!/usr/bin/env bash
# Keep this image aligned with the PHP version supported by Oderland.
PHP_TEST_IMAGE="php:5.6-cli"

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root"

if ! docker info >/dev/null 2>&1; then
    echo "ERROR: Docker is required and must be running." >&2
    exit 1
fi

if [[ "${CHECK_LOCAL_SECRETS:-0}" == "1" ]]; then
    echo "==> Verifying development environment"

    tests/sh/check-local-secrets.sh

    echo "✅ Local secrets match example config"
    echo
fi

echo "==> Starting Linux test environment ($PHP_TEST_IMAGE)"
docker run --rm \
  --platform linux/amd64 \
  -v "$repo_root:/app" \
  -w /app \
  $PHP_TEST_IMAGE \
  bash tests/in-container.sh