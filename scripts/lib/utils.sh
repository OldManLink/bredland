#!/usr/bin/env bash

execute_remote_command()
{
    local remote="${1:?Missing remote}"
    local command="${2:?Missing remote command}"

    env -u LC_CTYPE -u LC_ALL -u LANG \
        ssh "$remote" "$command"
}

execute_rsync() {
    local source="${1:?Missing source}"
    local destination="${2:?Missing destination}"

    env -u LC_CTYPE -u LC_ALL -u LANG \
        rsync -a --delete "$source" "$destination"
}