#!/usr/bin/env bash

verify_routeros() {
    local router="$1"
    local description="$2"
    local command="$3"
    local output

    if ! output="$(ssh "$router" "$command" 2>&1)"; then
        echo "$output" >&2
        fail "$description"
    fi

    if grep -q 'VERIFY_OK' <<<"$output"; then
        pass "$description"
    else
        echo "$output" >&2
        fail "$description"
    fi
}