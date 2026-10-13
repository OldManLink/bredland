#!/usr/bin/env bash

skip() {
    echo "$1"
    exit 77
}

assert_contains()
{
    local haystack="$1"
    local needle="$2"

    if [[ "$haystack" == *"$needle"* ]]; then
        ((++passed))
    else
        echo "❌ Expected output to contain: $needle"
        ((++failed))
    fi
}

assert_not_contains()
{
    local haystack="$1"
    local needle="$2"

    if [[ "$haystack" != *"$needle"* ]]; then
        ((++passed))
    else
        echo "❌ Expected output not to contain: $needle"
        ((++failed))
    fi
}

run_timed_step()
{
    local description="${1:?Missing description}"
    shift

    local started=$SECONDS

    echo
    echo "→ $description..."

    "$@"

    local elapsed=$((SECONDS - started))

    echo "✅ $description (${elapsed}s)"
}
