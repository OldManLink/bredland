#!/usr/bin/env bash

set -euo pipefail

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

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root"

PHP_TEST_IMAGE="bredland/php55-test"
docker_build_output="$(mktemp)"

preview_pid=""

cleanup()
{
    if [[ -n "$preview_pid" ]]; then
        kill "$preview_pid" 2>/dev/null || true
        wait "$preview_pid" 2>/dev/null || true
    fi

    rm -f "$docker_build_output"
}

trap cleanup EXIT INT TERM

docker_build_rc=0

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

set +e

docker run --rm \
    --platform linux/amd64 \
    -e COLUMNS="${COLUMNS:-80}" \
    -v "$repo_root:/app" \
    -w /app \
    "$PHP_TEST_IMAGE" \
    bash scripts/in-container/compare-dashboard.sh

compare_rc=$?

set -e

if $preview && (( compare_rc <= 1 )); then
    preview_port=8000
    preview_url="http://127.0.0.1:${preview_port}/index.html"
    preview_log="build/preview-server.log"

    python3 -m http.server "$preview_port" \
        --bind 127.0.0.1 \
        --directory build/render-index \
        > "$preview_log" 2>&1 &

    preview_pid=$!

    sleep 1

    if ! kill -0 "$preview_pid" 2>/dev/null; then
        echo "Preview server failed to start:" >&2
        cat "$preview_log" >&2
        exit 2
    fi

    open "$preview_url"

    echo
    read -r -p "Does the preview look and behave correctly? [y/N] " answer

    case "$answer" in
        y|Y|yes|YES)
            echo "✅ Preview approved"
            compare_rc=0
            ;;
        *)
            echo "❌ Preview rejected"
            compare_rc=1
            ;;
    esac
fi

exit "$compare_rc"
