#!/bin/bash
set -e

function error {
    echo -e "\033[1;31m$1\033[0m\n" && exit 1
}

function info {
    echo -e "\033[33m$1\033[0m"
}

function container_ip {
    local DOCKER_NETWORK="$1"
    docker inspect --format "{{(index .NetworkSettings.Networks \"${DOCKER_NETWORK}\").IPAddress}}" $2
}

function container_ips {
    local IP=""
    local DOCKER_NETWORK="$1"
    shift
    for var in "$@"; do
        if [[ -z "${IP}" ]]; then
            IP=$(container_ip $DOCKER_NETWORK ${var})
        else
            echo "${IP} ${var}"
            IP=""
        fi
    done
}

function delete_hosts_block {
    local line_start=$(grep -n "$1" /etc/hosts | head -n 1 | cut -d: -f1)
    local line_end=$(grep -n "$2" /etc/hosts | tail -n 1 | cut -d: -f1)
    if [[ ! -z "${line_start}" && ! -z "${line_end}" ]]; then
        sed -i "${line_start},${line_end}d" /etc/hosts
    fi
    true
}

function update_etc_hosts {
    local BLOCK_START="$1"
    local BLOCK_END="$2"
    shift && shift
    delete_hosts_block "${BLOCK_START}" "${BLOCK_END}"
    echo -e "${BLOCK_START}\n$@\n${BLOCK_END}" >> /etc/hosts
    info "updated /etc/hosts for ${BLOCK_START}"
}

# --- init

ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." >/dev/null 2>&1 && pwd )"

[[ "`id -u`" -ne 0 ]] && error "Le script exige les droits root -> sudo"

# --- docker containers

if [[ ! -z "$1" ]]; then
    DOCKER_BLOCK_NAME="$1"
    DOCKER_NETWORK="$2"
    shift
    update_etc_hosts \
        "# PERSO HOSTS ${DOCKER_BLOCK_NAME}" \
        "# END PERSO HOSTS ${DOCKER_BLOCK_NAME}" \
        "$(container_ips $@)"
fi
