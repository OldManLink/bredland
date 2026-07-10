#!/usr/bin/env bash

set -euo pipefail

root="${1:-templates/noc}"

printf 'digraph php_dependencies {\n'
printf '  rankdir=LR;\n'

while IFS= read -r -d '' file; do
    while IFS= read -r requirement; do
        dependency="$(
            printf '%s\n' "$requirement" |
                sed -E "s|.*['\"]/([^'\"]+)['\"].*|\1|"
        )"

        if [[ -z "$dependency" || "$dependency" == "$requirement" ]]; then
            echo "Unable to parse dependency in $file:" >&2
            echo "  $requirement" >&2
            exit 1
        fi

        printf '  "%s" -> "%s";\n' \
            "$(basename "$file")" \
            "$dependency"
    done < <(
        grep -E \
            "require_once[[:space:]]+__DIR__[[:space:]]*\.[[:space:]]*['\"]/" \
            "$file" || true
    )
done < <(find "$root" -type f -name '*.php' -print0)

printf '}\n'
