#!/usr/bin/env bash

execute_remote_command() {
    local command="${1:?Missing remote command}"
    local oderland_user="${ODERLAND_SSH_USER:?Missing ODERLAND_SSH_USER}"
    local oderland_host="${ODERLAND_SSH_HOST:?Missing ODERLAND_SSH_HOST}"

    env -u LC_CTYPE -u LC_ALL -u LANG \
        ssh "${oderland_user}@${oderland_host}" "$command"
}

execute_rsync() {
    local source="${1:?Missing source}"
    local destination="${2:?Missing destination}"

    env -u LC_CTYPE -u LC_ALL -u LANG \
        rsync -a --delete "$source" "$destination"
}