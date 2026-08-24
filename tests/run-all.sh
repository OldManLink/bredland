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
    
        docker_build_output="$(mktemp)"
        docker_build_rc=0
    
        docker build \
            --platform linux/amd64 \
            -t "$PHP_TEST_IMAGE" \
            tests/docker/php55 \
            >"$docker_build_output" 2>&1 \
            || docker_build_rc=$?
    
        if (( docker_build_rc != 0 )); then
            cat "$docker_build_output"
            rm -f "$docker_build_output"
            return "$docker_build_rc"
        fi
    
        rm -f "$docker_build_output"
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

    echo
    echo "==> JavaScript tests"
    tests/js/run-all.sh
}

start_time="$(date +%s)"

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

    if (( rc != 0 )); then
        cat "$output_file"
    fi
else
    set +e
    run_tests
    rc=$?
    set -e
fi

end_time="$(date +%s)"
elapsed=$((end_time - start_time))

if (( rc == 0 )); then
    echo "✅ All tests passed, elapsed time: ${elapsed}s"
else
    echo "❌ Test failures, elapsed time: ${elapsed}s"
    exit "$rc"
fi
