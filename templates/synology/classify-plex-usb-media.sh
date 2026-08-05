#!/bin/sh

set -eu

SSH_TARGET=${SSH_TARGET:-peter@arcanel-storage}
USB_ROOT=${USB_ROOT:-/volumeUSB1/usbshare1-2}
PLEX_ROOT=${PLEX_ROOT:-/volume1/media}

execute=no

case "${1:-}" in
    '')
        ;;
    --execute)
        execute=yes
        ;;
    *)
        printf 'Usage: %s [--execute]\n' "$0" >&2
        exit 1
        ;;
esac

ssh "$SSH_TARGET" sh -s -- \
    "$USB_ROOT" \
    "$PLEX_ROOT" \
    "$execute" <<'REMOTE'
set -eu

usb_root=$1
plex_root=$2
execute=$3

staging_movies=$usb_root/Movies
staging_tv=$usb_root/TV

plex_movies=$plex_root/Movies
plex_tv=$plex_root/TV

if [ ! -d "$usb_root" ]; then
    printf 'ERROR: USB root does not exist: %s\n' "$usb_root" >&2
    exit 1
fi

if [ ! -d "$plex_movies" ]; then
    printf 'ERROR: Plex Movies directory does not exist: %s\n' \
        "$plex_movies" >&2
    exit 1
fi

if [ ! -d "$plex_tv" ]; then
    printf 'ERROR: Plex TV directory does not exist: %s\n' \
        "$plex_tv" >&2
    exit 1
fi

tmp=${TMPDIR:-/tmp}/classify-plex-usb-media.$$

movie_plan=$tmp.movies
tv_plan=$tmp.tv
collision_log=$tmp.collisions

cleanup()
{
    rm -f "$movie_plan" "$tv_plan" "$collision_log"
}

trap cleanup EXIT HUP INT TERM

: > "$movie_plan"
: > "$tv_plan"
: > "$collision_log"

is_system_directory()
{
    case "$1" in
        '@eaDir'|'@tmp'|'$RECYCLE.BIN'|'System Volume Information')
            return 0
            ;;
    esac

    return 1
}

has_season_directory()
{
    season_parent=$1

    for season_child in "$season_parent"/Season\ *; do
        [ -d "$season_child" ] && return 0
    done

    return 1
}

relative_to_usb_root()
{
    printf '%s\n' "${1#"$usb_root"/}"
}

record_movie()
{
    movie_source=$1
    movie_relative_source=$(relative_to_usb_root "$movie_source")
    movie_destination_name=${movie_source##*/}

    printf '%s\t%s\n' \
        "$movie_relative_source" \
        "$movie_destination_name" >> "$movie_plan"
}

record_tv_series()
{
    tv_source=$1
    tv_relative_source=$(relative_to_usb_root "$tv_source")
    tv_destination_name=${tv_source##*/}

    printf '%s\t%s\n' \
        "$tv_relative_source" \
        "$tv_destination_name" >> "$tv_plan"
}

print_movie_moves()
{
    movie_collection=$1
    movie_collection_name=${movie_collection##*/}

    printf 'MOVIES    %s\n' "$movie_collection_name"

    for movie_child in "$movie_collection"/*; do
        [ -d "$movie_child" ] || continue

        record_movie "$movie_child"

        movie_relative_source=$(relative_to_usb_root "$movie_child")

        printf '    mv "%s" "Movies/"\n' \
            "$movie_relative_source"
    done

    printf '\n'
}

print_tv_move()
{
    tv_series=$1
    tv_series_name=${tv_series##*/}

    record_tv_series "$tv_series"

    tv_relative_source=$(relative_to_usb_root "$tv_series")

    printf 'TV        %s\n' "$tv_series_name"
    printf '    mv "%s" "TV/"\n' "$tv_relative_source"
    printf '\n'
}

print_mixed_moves()
{
    mixed_collection=$1
    mixed_collection_name=${mixed_collection##*/}

    printf 'MIXED     %s\n' "$mixed_collection_name"

    if [ -d "$mixed_collection/Movies" ]; then
        for mixed_movie in "$mixed_collection/Movies"/*; do
            [ -d "$mixed_movie" ] || continue

            record_movie "$mixed_movie"

            mixed_movie_relative=$(relative_to_usb_root "$mixed_movie")

            printf '    mv "%s" "Movies/"\n' \
                "$mixed_movie_relative"
        done
    fi

    if [ -d "$mixed_collection/TV" ]; then
        for mixed_tv in "$mixed_collection/TV"/*; do
            [ -d "$mixed_tv" ] || continue

            record_tv_series "$mixed_tv"

            mixed_tv_relative=$(relative_to_usb_root "$mixed_tv")

            printf '    mv "%s" "TV/"\n' \
                "$mixed_tv_relative"
        done
    fi

    printf '\n'
}

check_staging_directory()
{
    staging_directory=$1

    if [ -e "$staging_directory" ]; then
        printf 'STAGING:%s\n' \
            "$staging_directory" >> "$collision_log"

        printf '\nERROR: Staging destination already exists:\n'
        printf '  %s\n' "$staging_directory"
    fi
}

check_source_duplicates()
{
    duplicate_kind=$1
    duplicate_plan=$2

    duplicate_names=$(cut -f2 "$duplicate_plan" | sort | uniq -d)

    if [ -z "$duplicate_names" ]; then
        return
    fi

    printf '\nDuplicate %s names within the USB source:\n' \
        "$duplicate_kind"

    old_ifs=$IFS
    IFS='
'

    for duplicate_name in $duplicate_names; do
        [ -n "$duplicate_name" ] || continue

        printf '%s:%s\n' \
            "$duplicate_kind" \
            "$duplicate_name" >> "$collision_log"

        printf '  %s\n' "$duplicate_name"

        awk -F '\t' -v wanted="$duplicate_name" '
            $2 == wanted {
                printf "    %s\n", $1
            }
        ' "$duplicate_plan"
    done

    IFS=$old_ifs
}

check_plex_collisions()
{
    plex_kind=$1
    plex_plan=$2
    plex_destination_directory=$3

    while IFS='	' read -r plex_relative_source plex_destination_name; do
        [ -n "$plex_destination_name" ] || continue

        plex_destination=$plex_destination_directory/$plex_destination_name

        if [ -e "$plex_destination" ]; then
            printf '%s:%s\n' \
                "$plex_kind" \
                "$plex_destination_name" >> "$collision_log"

            printf '\n%s already exists in the Plex library:\n' \
                "$plex_kind"

            printf '  Source:      %s\n' "$plex_relative_source"
            printf '  Destination: %s\n' "$plex_destination"
        fi
    done < "$plex_plan"
}

execute_plan()
{
    printf '\nCreating staging directories\n'
    mkdir "$staging_movies" "$staging_tv"

    printf '\nMoving movie directories\n'

    while IFS='	' read -r movie_source movie_destination_name; do
        [ -n "$movie_source" ] || continue

        printf '    mv "%s" "Movies/"\n' "$movie_source"

        mv \
            "$usb_root/$movie_source" \
            "$staging_movies/$movie_destination_name"
    done < "$movie_plan"

    printf '\nMoving TV-series directories\n'

    while IFS='	' read -r tv_source tv_destination_name; do
        [ -n "$tv_source" ] || continue

        printf '    mv "%s" "TV/"\n' "$tv_source"

        mv \
            "$usb_root/$tv_source" \
            "$staging_tv/$tv_destination_name"
    done < "$tv_plan"

    printf '\nMove complete.\n'
}

printf 'USB root:  %s\n' "$usb_root"
printf 'Plex root: %s\n' "$plex_root"

if [ "$execute" = yes ]; then
    printf 'Mode:      EXECUTE\n\n'
else
    printf 'Mode:      DRY RUN\n\n'
fi

for top_level_path in "$usb_root"/*; do
    [ -d "$top_level_path" ] || continue

    top_level_name=${top_level_path##*/}

    if is_system_directory "$top_level_name"; then
        printf 'SKIP      %s\n\n' "$top_level_name"
        continue
    fi

    if [ -d "$top_level_path/Movies" ] ||
        [ -d "$top_level_path/TV" ]; then
        print_mixed_moves "$top_level_path"
        continue
    fi

    if has_season_directory "$top_level_path"; then
        print_tv_move "$top_level_path"
        continue
    fi

    print_movie_moves "$top_level_path"
done

printf 'Safety checks\n'
printf '%s\n' '-------------'

check_staging_directory "$staging_movies"
check_staging_directory "$staging_tv"

check_source_duplicates MOVIE "$movie_plan"
check_source_duplicates TV "$tv_plan"

check_plex_collisions MOVIE "$movie_plan" "$plex_movies"
check_plex_collisions TV "$tv_plan" "$plex_tv"

movie_count=$(wc -l < "$movie_plan" | tr -d ' ')
tv_count=$(wc -l < "$tv_plan" | tr -d ' ')
collision_count=$(wc -l < "$collision_log" | tr -d ' ')

printf '\nSummary\n'
printf '%s\n' '-------'
printf 'Movie directories: %s\n' "$movie_count"
printf 'TV series:         %s\n' "$tv_count"

if [ "$collision_count" -eq 0 ]; then
    printf 'Collisions:        none\n'
    printf '\nMove plan is collision-free.\n'
else
    printf 'Collisions:        %s\n' "$collision_count"
    printf '\nMove plan is NOT safe to execute.\n'
fi

if [ "$execute" = no ]; then
    printf 'Dry run complete. No changes made.\n'
    exit 0
fi

if [ "$collision_count" -ne 0 ]; then
    printf 'Execution aborted. No changes made.\n' >&2
    exit 1
fi

execute_plan

REMOTE

