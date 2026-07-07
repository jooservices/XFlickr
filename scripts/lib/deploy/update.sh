#!/usr/bin/env bash
# Backward-compatible update router.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/update-docker.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/host/update-host.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/mode.sh"

deploy_update_stack() {
    local mode
    mode="$(deploy_resolve_mode "$(xf_script_root)")"

    case "$mode" in
        host) deploy_update_host_stack ;;
        *) deploy_update_docker_stack ;;
    esac
}
