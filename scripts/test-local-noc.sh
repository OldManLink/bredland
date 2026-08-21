#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root"

PHP_TEST_IMAGE="bredland/php55-test"
docker_build_output="$(mktemp)"

preview_container=""
preview_ready="build/rendered-noc/.preview-ready"

cleanup()
{
    if [[ -n "$preview_container" ]]; then
        docker stop "$preview_container" >/dev/null 2>&1 || true
    fi

    rm -f "$preview_ready"
    rm -f "$docker_build_output"
}

trap cleanup EXIT INT TERM

docker_build_rc=0

preview=false

case "${1:-}" in
    "")
        ;;
    --preview)
        preview=true
        ;;
    *)
        echo "Usage: $0 [--preview]" >&2
        exit 2
        ;;
esac

docker_args=(
    --rm
    --platform linux/amd64
    -v "$repo_root:/app"
    -w /app
)

if $preview; then
    docker_args+=(
        -p 127.0.0.1:8000:8000
        -e LOCAL_NOC_PREVIEW=1
    )
fi

docker build \
    --platform linux/amd64 \
    -t "$PHP_TEST_IMAGE" \
    tests/docker/php55 \
    >"$docker_build_output" 2>&1 \
    || docker_build_rc=$?

if (( docker_build_rc != 0 )); then
    cat "$docker_build_output"
    exit "$docker_build_rc"
fi

if ! $preview; then
    docker run \
        "${docker_args[@]}" \
        "$PHP_TEST_IMAGE" \
        bash scripts/in-container/test-local-noc.sh

    exit $?
fi

rm -f "$preview_ready"

preview_container="bredland-local-noc-preview-$$"

docker run \
    "${docker_args[@]}" \
    --name "$preview_container" \
    "$PHP_TEST_IMAGE" \
    bash scripts/in-container/test-local-noc.sh &

docker_pid=$!

echo
echo "Waiting for local NOC integration checks..."

for _ in {1..30}; do
    if [[ -f "$preview_ready" ]]; then
        break
    fi

    if ! kill -0 "$docker_pid" 2>/dev/null; then
        set +e
        wait "$docker_pid"
        rc=$?
        set -e

        exit "$rc"
    fi

    sleep 1
done

if [[ ! -f "$preview_ready" ]]; then
    echo "❌ Timed out waiting for local NOC preview"
    exit 1
fi

preview_url="http://127.0.0.1:8000/index.php"

echo
echo "🌐 Opening live local NOC:"
echo "   $preview_url"

open "$preview_url"

echo
read -r -p "Press Enter to stop the local NOC preview... "
