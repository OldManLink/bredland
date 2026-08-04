#!/usr/bin/env bash
# Keep this image aligned with the PHP version supported by Oderland.
PHP_TEST_IMAGE="bredland/php55-test"

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root"

quietest=false
test_args=()

for arg in "$@"; do
    case "$arg" in
        -qq)
            quietest=true
            test_args+=("-q")
            ;;
        *)
            test_args+=("$arg")
            ;;
    esac
done

run_tests()
{
    date +"%T"

    if ! docker info >/dev/null 2>&1; then
        echo "ERROR: Docker is required and must be running." >&2
        return 1
    fi

    if [[ "${CHECK_LOCAL_SECRETS:-0}" == "1" ]]; then
        echo "==> Verifying development environment"

        tests/sh/check-local-secrets.sh

        echo "✅ Local secrets match example config"
        echo
    fi

    if [[ "${SKIP_DOCKER_BUILD:-0}" != "1" ]]; then
        echo "==> Building Linux test environment ($PHP_TEST_IMAGE)"
        docker build \
          --platform linux/amd64 \
          -t "$PHP_TEST_IMAGE" \
          tests/docker/php55
    fi

    echo "==> Starting Linux test environment ($PHP_TEST_IMAGE)"

    if (( ${#test_args[@]} > 0 )); then
        docker run --rm \
          --platform linux/amd64 \
          -v "$repo_root:/app" \
          -w /app \
          "$PHP_TEST_IMAGE" \
          bash tests/in-container.sh "${test_args[@]}"
    else
        docker run --rm \
          --platform linux/amd64 \
          -v "$repo_root:/app" \
          -w /app \
          "$PHP_TEST_IMAGE" \
          bash tests/in-container.sh
    fi

    date +"%T"
}

if $quietest; then
    output_file="$(mktemp)"
    trap 'rm -f "$output_file"' EXIT

    set +e
    (
        set -e
        run_tests
    ) >"$output_file" 2>&1
    rc=$?
    set -e

    if (( rc == 0 )); then
        echo "✅ All tests passed"
    else
        cat "$output_file"
        exit "$rc"
    fi
else
    run_tests
fi
