#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root"

PHP_TEST_IMAGE="bredland/php55-test"

docker build \
    --platform linux/amd64 \
    -t "$PHP_TEST_IMAGE" \
    tests/docker/php55

docker run --rm \
    --platform linux/amd64 \
    -e COLUMNS="${COLUMNS:-80}" \
    -v "$repo_root:/app" \
    -w /app \
    "$PHP_TEST_IMAGE" \
    bash scripts/in-container/compare-dashboard.sh
