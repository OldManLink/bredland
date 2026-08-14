#!/usr/bin/env bash

pass()
{
    echo "✅ $1"
}

fail()
{
    echo "❌ $1" >&2
    return 1
}

run_step()
{
    local description="$1"
    shift

    echo "→ $description"

    if "$@"; then
        pass "$description"
    else
        fail "$description"
    fi
}
